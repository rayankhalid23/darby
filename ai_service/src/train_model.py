# -*- coding: utf-8 -*-
"""
تدريب نموذج تصنيف الشكاوى: TF-IDF (كلمات + حروف) + LinearSVC.

يُحفظ الناتج في model.joblib بجذر المشروع، ويحتوي على:
    {"pipeline": Pipeline, "labels": [...], "metrics": {...}, "meta": {...}}
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

import joblib
import sklearn
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics import accuracy_score, classification_report, f1_score
from sklearn.model_selection import cross_val_score
from sklearn.pipeline import FeatureUnion, Pipeline
from sklearn.svm import LinearSVC

PROJECT_ROOT = Path(__file__).resolve().parents[1]
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

from src.data_prep import (  # noqa: E402
    LABELS,
    TEST_PATH,
    TRAIN_PATH,
    clean_text,
    load_jsonl,
)

MODEL_PATH = PROJECT_ROOT / "model.joblib"
METRICS_PATH = PROJECT_ROOT / "metrics.json"
SEED = 42


def build_pipeline() -> Pipeline:
    """
    دمج تمثيلين نصيين:
      - word 1-2 gram : يلتقط العبارات المفتاحية ("كاد يخبط"، "وقف يشتري").
      - char_wb 2-5   : يعالج اختلاف اللهجات والإملاء والتصريف في العربية،
                        وهو ما يرفع الدقة بوضوح على بيانات متعددة اللهجات.
    LinearSVC مناسب للنصوص عالية الأبعاد والمتفرقة (sparse).
    """
    features = FeatureUnion([
        ("word", TfidfVectorizer(
            analyzer="word",
            ngram_range=(1, 2),
            sublinear_tf=True,
            min_df=1,
            max_df=0.9,
        )),
        ("char", TfidfVectorizer(
            analyzer="char_wb",
            ngram_range=(2, 5),
            sublinear_tf=True,
            min_df=2,
        )),
    ])

    return Pipeline([
        ("features", features),
        ("clf", LinearSVC(
            C=1.0,
            class_weight="balanced",
            loss="squared_hinge",
            max_iter=5000,
            random_state=SEED,
        )),
    ])


def load_split(path: Path) -> tuple[list[str], list[str]]:
    records = load_jsonl(path)
    if not records:
        raise ValueError(f"الملف فارغ: {path} — شغّل src/data_prep.py أولاً")
    texts = [clean_text(r["complaint_text"]) for r in records]
    labels = [r["label"] for r in records]
    return texts, labels


def train(verbose: bool = True) -> dict:
    """يدرّب النموذج ويحفظه، ويُرجع قاموس المقاييس."""
    x_train, y_train = load_split(TRAIN_PATH)
    x_test, y_test = load_split(TEST_PATH)

    pipeline = build_pipeline()
    pipeline.fit(x_train, y_train)

    y_pred = pipeline.predict(x_test)
    accuracy = float(accuracy_score(y_test, y_pred))
    macro_f1 = float(f1_score(y_test, y_pred, average="macro"))

    cv_scores = cross_val_score(
        build_pipeline(), x_train, y_train, cv=5, scoring="accuracy", n_jobs=1
    )

    metrics = {
        "test_accuracy": round(accuracy, 4),
        "test_macro_f1": round(macro_f1, 4),
        "cv5_mean_accuracy": round(float(cv_scores.mean()), 4),
        "cv5_std": round(float(cv_scores.std()), 4),
        "train_size": len(x_train),
        "test_size": len(x_test),
    }

    payload = {
        "pipeline": pipeline,
        "labels": sorted(set(y_train)),
        "metrics": metrics,
        "meta": {
            "model": "TF-IDF(word 1-2 + char_wb 2-5) + LinearSVC",
            "seed": SEED,
            "expected_labels": list(LABELS),
            "sklearn_version": sklearn.__version__,
        },
    }
    joblib.dump(payload, MODEL_PATH)
    METRICS_PATH.write_text(
        json.dumps(metrics, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    if verbose:
        print("[train_model] تقرير التصنيف على مجموعة الاختبار:")
        print(classification_report(y_test, y_pred, digits=4, zero_division=0))
        print(f"[train_model] accuracy = {accuracy:.4f} | macro-F1 = {macro_f1:.4f}")
        print(f"[train_model] CV(5) = {cv_scores.mean():.4f} "
              f"(±{cv_scores.std():.4f})")
        print(f"[train_model] تم حفظ النموذج -> {MODEL_PATH}")

    return metrics


def main() -> None:
    try:
        sys.stdout.reconfigure(encoding="utf-8")
    except (AttributeError, ValueError):
        pass

    metrics = train()
    if metrics["test_accuracy"] < 0.85:
        raise SystemExit(
            f"❌ الدقة {metrics['test_accuracy']:.4f} أقل من الحد الأدنى 0.85"
        )
    print("[train_model] تم بنجاح ✅")


if __name__ == "__main__":
    main()
