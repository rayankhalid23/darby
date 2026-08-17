# -*- coding: utf-8 -*-
"""
واجهة التنبؤ للربط مع نظام الويب.

الاستخدام السريع:
    from src.predictor import predict
    predict("السواق كان يستخدم الموبايل وهو يسوق")
    # -> {"label": "driver_alert", "confidence": 0.87, "action": "...", ...}
"""

from __future__ import annotations

import math
import sys
import warnings
from pathlib import Path
from threading import Lock

import joblib
import sklearn

PROJECT_ROOT = Path(__file__).resolve().parents[1]
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

from src.data_prep import clean_text  # noqa: E402

DEFAULT_MODEL_PATH = PROJECT_ROOT / "model.joblib"

MAX_TEXT_LENGTH = 5000
LOW_CONFIDENCE_THRESHOLD = 0.45

# ما الذي يفعله نظام الويب عند كل تصنيف
ACTIONS = {
    "normal": {
        "action": "no_action",
        "severity": 0,
        "message_ar": "لا يوجد إجراء مطلوب، ملاحظة عادية.",
    },
    "ignore": {
        "action": "log_only",
        "severity": 1,
        "message_ar": "شكوى بسيطة، تُسجَّل في السجل بدون إجراء.",
    },
    "driver_alert": {
        "action": "notify_driver",
        "severity": 2,
        "message_ar": "إرسال تنبيه للسائق ومتابعة السلوك.",
    },
    "deactivate": {
        "action": "suspend_driver",
        "severity": 3,
        "message_ar": "مخالفة جسيمة: إيقاف السائق وتحويل الحالة للإدارة.",
    },
}


class ModelNotFoundError(FileNotFoundError):
    """يُرفع عندما يكون ملف النموذج غير موجود."""


def _softmax(values: list[float]) -> list[float]:
    top = max(values)
    exps = [math.exp(v - top) for v in values]
    total = sum(exps)
    return [e / total for e in exps]


class Predictor:
    """غلاف حول pipeline المحفوظ، آمن للاستخدام من خيوط متعددة (thread-safe)."""

    def __init__(self, model_path: Path | str = DEFAULT_MODEL_PATH):
        self.model_path = Path(model_path)
        if not self.model_path.exists():
            raise ModelNotFoundError(
                f"ملف النموذج غير موجود: {self.model_path} — "
                f"شغّل: python src/train_model.py"
            )
        payload = joblib.load(self.model_path)
        if not isinstance(payload, dict) or "pipeline" not in payload:
            raise ValueError(f"ملف نموذج غير صالح: {self.model_path}")

        self.pipeline = payload["pipeline"]
        self.labels: list[str] = list(payload.get("labels")
                                      or self.pipeline.classes_)
        self.metrics: dict = payload.get("metrics", {})
        self.meta: dict = payload.get("meta", {})

        # توافقية النموذج المحفوظ (joblib) مع نسخة sklearn الحالية غير مضمونة رسمياً
        # عبر sklearn حتى بين إصدارات فرعية — نحذّر فقط بدل الفشل الصامت أو الانهيار.
        trained_version = self.meta.get("sklearn_version")
        if trained_version and trained_version != sklearn.__version__:
            warnings.warn(
                f"النموذج {self.model_path.name} دُرِّب بإصدار scikit-learn={trained_version} "
                f"لكن البيئة الحالية تستخدم {sklearn.__version__} — قد تختلف النتائج، "
                f"يُفضَّل إعادة التدريب بنفس بيئة التشغيل.",
                RuntimeWarning,
                stacklevel=2,
            )

    # ---------------------------------------------------------------- utils
    @staticmethod
    def validate_text(text) -> str:
        """تتحقق من المدخل وتُرجع النص المنظّف، أو ترفع استثناءً واضحاً."""
        if text is None:
            raise ValueError("النص المُدخل لا يمكن أن يكون None")
        if not isinstance(text, str):
            raise TypeError(f"النص المُدخل يجب أن يكون str، وصل: {type(text).__name__}")
        if len(text) > MAX_TEXT_LENGTH:
            raise ValueError(
                f"النص طويل جداً ({len(text)} حرف)، الحد الأقصى {MAX_TEXT_LENGTH}"
            )
        cleaned = clean_text(text)
        if not cleaned:
            raise ValueError("النص المُدخل فارغ بعد التنظيف")
        return cleaned

    def _scores(self, cleaned_texts: list[str]) -> list[dict]:
        """درجات احتمالية تقريبية مشتقة من decision_function الخاصة بـ LinearSVC."""
        margins = self.pipeline.decision_function(cleaned_texts)
        classes = list(self.pipeline.classes_)

        results = []
        for row in margins:
            row = list(row) if hasattr(row, "__iter__") else [-float(row), float(row)]
            probs = _softmax([float(v) for v in row])
            results.append(dict(zip(classes, probs)))
        return results

    # ------------------------------------------------------------- public API
    def predict(self, text) -> dict:
        """تصنيف نص واحد وإرجاع نتيجة جاهزة لواجهة الويب."""
        cleaned = self.validate_text(text)
        scores = self._scores([cleaned])[0]
        label = max(scores, key=scores.get)
        confidence = float(scores[label])
        action = ACTIONS.get(label, ACTIONS["ignore"])

        return {
            "label": label,
            "confidence": round(confidence, 4),
            "action": action["action"],
            "severity": action["severity"],
            "message_ar": action["message_ar"],
            "low_confidence": confidence < LOW_CONFIDENCE_THRESHOLD,
            "scores": {k: round(float(v), 4) for k, v in scores.items()},
            "cleaned_text": cleaned,
        }

    def predict_batch(self, texts) -> list[dict]:
        """تصنيف قائمة نصوص دفعة واحدة (أسرع من الاستدعاء المفرد)."""
        if isinstance(texts, str):
            raise TypeError("predict_batch تتوقع قائمة نصوص، لا نصاً مفرداً")
        texts = list(texts)
        if not texts:
            return []
        cleaned = [self.validate_text(t) for t in texts]

        outputs = []
        for text_scores, text in zip(self._scores(cleaned), cleaned):
            label = max(text_scores, key=text_scores.get)
            confidence = float(text_scores[label])
            action = ACTIONS.get(label, ACTIONS["ignore"])
            outputs.append({
                "label": label,
                "confidence": round(confidence, 4),
                "action": action["action"],
                "severity": action["severity"],
                "message_ar": action["message_ar"],
                "low_confidence": confidence < LOW_CONFIDENCE_THRESHOLD,
                "scores": {k: round(float(v), 4) for k, v in text_scores.items()},
                "cleaned_text": text,
            })
        return outputs

    def health(self) -> dict:
        """فحص جاهزية النموذج — مفيد لنقطة /health في نظام الويب."""
        return {
            "status": "ok",
            "model_path": str(self.model_path),
            "labels": self.labels,
            "metrics": self.metrics,
            "meta": self.meta,
        }


# ------------------------------------------------------------------ singleton
_predictor: Predictor | None = None
_lock = Lock()


def get_predictor(model_path: Path | str = DEFAULT_MODEL_PATH) -> Predictor:
    """يُحمّل النموذج مرة واحدة فقط ويعيد استخدامه (مهم لأداء الويب)."""
    global _predictor
    with _lock:
        if _predictor is None or Path(model_path) != _predictor.model_path:
            _predictor = Predictor(model_path)
        return _predictor


def reset_predictor() -> None:
    """إعادة تعيين الـ singleton (يُستخدم في الاختبارات وبعد إعادة التدريب)."""
    global _predictor
    with _lock:
        _predictor = None


def predict(text) -> dict:
    """دالة مختصرة للاستدعاء المباشر من نظام الويب."""
    return get_predictor().predict(text)


def predict_batch(texts) -> list[dict]:
    return get_predictor().predict_batch(texts)


if __name__ == "__main__":
    samples = [
        "الحمد لله الأولاد وصلوا البيت بالسلامة والسواق محترم ومؤدب.",
        "تأخر دقيقتين قال في زحمة على الدوار.",
        "السواق كان يستخدم الموبايل ويكتب رسايل وهو يسوق بسرعة.",
        "السائق شتم ولدي بألفاظ نابية وطرده من الباص في نص الطريق.",
    ]
    for item in predict_batch(samples):
        print(f"{item['label']:<13} | {item['confidence']:.3f} | "
              f"{item['action']:<15} | {item['cleaned_text'][:50]}")
