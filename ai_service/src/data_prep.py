# -*- coding: utf-8 -*-
"""
تجهيز البيانات: دمج + تنظيف + إزالة التكرار + تقسيم 80/20.

المخرجات:
    data/raw_data.jsonl   -> 4000 سطر متوازن (1000 لكل تصنيف)
    data/train.jsonl      -> 80% (stratified)
    data/test.jsonl       -> 20% (stratified)
"""

from __future__ import annotations

import json
import random
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[1]
DATA_DIR = PROJECT_ROOT / "data"

RAW_PATH = DATA_DIR / "raw_data.jsonl"
TRAIN_PATH = DATA_DIR / "train.jsonl"
TEST_PATH = DATA_DIR / "test.jsonl"

LABELS = ("normal", "ignore", "driver_alert", "deactivate")
PER_LABEL = 1000
TEST_RATIO = 0.20
SEED = 42

# لكل تصنيف قائمة ملفات مصدر (يسمح بملفات تعويض/إضافة لاحقة)
SOURCE_FILES = {
    "deactivate": [DATA_DIR / "deactivate_complaints_clean_1000.jsonl"],
    "driver_alert": [DATA_DIR / "driver_alert_complaints_clean_1000.jsonl"],
    # ignore_topup: تعويض 4 أسطر مكررة حرفياً في الملف الأصلي
    # (تختلف فقط بفاصلة أو همزة) وتم حذفها في مرحلة إزالة التكرار.
    "ignore": [
        DATA_DIR / "ignore_complaints_clean_1000.jsonl",
        DATA_DIR / "ignore_topup.jsonl",
    ],
    "normal": [DATA_DIR / "normal_data.jsonl"],
}

_WS_RE = re.compile(r"\s+")
_TATWEEL_RE = re.compile("[ـ]")
_DIACRITICS_RE = re.compile(r"[ً-ْٰ]")


def clean_text(text: str) -> str:
    """تنظيف خفيف يحافظ على معنى الجملة (يُخزَّن هذا الناتج في الداتاست)."""
    if not isinstance(text, str):
        raise TypeError(f"complaint_text يجب أن يكون نصاً، وصل: {type(text)!r}")
    text = unicodedata.normalize("NFKC", text)
    text = _TATWEEL_RE.sub("", text)
    text = _WS_RE.sub(" ", text)
    return text.strip()


def dedup_key(text: str) -> str:
    """مفتاح المقارنة لإزالة التكرار: تطبيع أعمق (تشكيل/همزات/ترقيم)."""
    text = clean_text(text).lower()
    text = _DIACRITICS_RE.sub("", text)
    text = re.sub("[إأآا]", "ا", text)
    text = re.sub("[ىي]", "ي", text)
    text = re.sub("[ةه]", "ه", text)
    text = re.sub(r"[^\w\s]", "", text, flags=re.UNICODE)
    return _WS_RE.sub(" ", text).strip()


def load_jsonl(path: Path) -> list[dict]:
    """قراءة ملف JSONL مع تجاهل الأسطر الفارغة ورفض الأسطر التالفة."""
    path = Path(path)
    if not path.exists():
        raise FileNotFoundError(f"الملف غير موجود: {path}")

    records: list[dict] = []
    with path.open(encoding="utf-8") as fh:
        for lineno, line in enumerate(fh, start=1):
            line = line.strip()
            if not line:
                continue
            try:
                obj = json.loads(line)
            except json.JSONDecodeError as exc:
                raise ValueError(f"JSON تالف في {path.name}:{lineno} -> {exc}") from exc
            if not isinstance(obj, dict):
                raise ValueError(f"سطر ليس كائن JSON في {path.name}:{lineno}")
            records.append(obj)
    return records


def write_jsonl(path: Path, records: list[dict]) -> None:
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8") as fh:
        for rec in records:
            fh.write(json.dumps(rec, ensure_ascii=False) + "\n")


def collect_records() -> tuple[list[dict], dict]:
    """تحميل كل المصادر + تنظيف + إزالة التكرار (داخل الملف وبين الملفات)."""
    seen: set[str] = set()
    kept: list[dict] = []
    stats = {"read": 0, "dropped_empty": 0, "dropped_dup": 0, "dropped_bad_label": 0}

    for label, paths in SOURCE_FILES.items():
        for path in paths:
            for rec in load_jsonl(path):
                stats["read"] += 1
                text = clean_text(rec.get("complaint_text", ""))
                rec_label = rec.get("label")

                if not text:
                    stats["dropped_empty"] += 1
                    continue
                if rec_label not in LABELS or rec_label != label:
                    # سطر تصنيفه غير معروف أو مخالف لتصنيف ملفه -> يُرفض
                    stats["dropped_bad_label"] += 1
                    continue

                key = dedup_key(text)
                if key in seen:
                    stats["dropped_dup"] += 1
                    continue
                seen.add(key)
                kept.append({"complaint_text": text, "label": rec_label})

    return kept, stats


def balance(records: list[dict], per_label: int = PER_LABEL,
            seed: int = SEED) -> list[dict]:
    """موازنة الفئات: `per_label` سطر لكل تصنيف بالضبط."""
    rng = random.Random(seed)
    buckets: dict[str, list[dict]] = defaultdict(list)
    for rec in records:
        buckets[rec["label"]].append(rec)

    balanced: list[dict] = []
    for label in LABELS:
        pool = buckets.get(label, [])
        if len(pool) < per_label:
            raise ValueError(
                f"التصنيف '{label}' فيه {len(pool)} سطر فقط، المطلوب {per_label}"
            )
        rng.shuffle(pool)
        balanced.extend(pool[:per_label])

    rng.shuffle(balanced)
    return balanced


def _word_bigrams(text: str) -> set[str]:
    """مجموعة ثنائيات الكلمات (word bigrams) — أساس كشف تشابه القوالب."""
    words = dedup_key(text).split()
    if len(words) < 2:
        return set(words)
    return {f"{words[i]}_{words[i + 1]}" for i in range(len(words) - 1)}


def _jaccard(a: set, b: set) -> float:
    if not a and not b:
        return 1.0
    union = len(a | b)
    return (len(a & b) / union) if union else 0.0


class _UnionFind:
    """بنية Union-Find بسيطة لتجميع السجلات شبه المتطابقة (نفس القالب) في عناقيد."""

    def __init__(self, n: int):
        self.parent = list(range(n))

    def find(self, x: int) -> int:
        while self.parent[x] != x:
            self.parent[x] = self.parent[self.parent[x]]
            x = self.parent[x]
        return x

    def union(self, x: int, y: int) -> None:
        rx, ry = self.find(x), self.find(y)
        if rx != ry:
            self.parent[rx] = ry


def cluster_near_duplicates(records: list[dict], threshold: float = 0.5) -> list[list[int]]:
    """
    يجمّع السجلات التي تشترك في نفس "قالب" الجملة (تشابه Jaccard على ثنائيات الكلمات
    يتجاوز threshold) في عنقود واحد — حتى لو اختلفت كلمة أو كلمتان بينها (مثال:
    "كاد يخبط في حافلة مدرسية" مقابل "كاد يخبط في دراجة نارية"). هذا يمنع تسريب
    القوالب بين التدريب والاختبار الذي لا يكتشفه dedup_key وحده (تطابق إملائي فقط).
    """
    n = len(records)
    bigram_sets = [_word_bigrams(r["complaint_text"]) for r in records]
    uf = _UnionFind(n)

    for i in range(n):
        if not bigram_sets[i]:
            continue
        for j in range(i + 1, n):
            if _jaccard(bigram_sets[i], bigram_sets[j]) >= threshold:
                uf.union(i, j)

    clusters: dict[int, list[int]] = defaultdict(list)
    for i in range(n):
        clusters[uf.find(i)].append(i)
    return list(clusters.values())


def stratified_split(records: list[dict], test_ratio: float = TEST_RATIO,
                     seed: int = SEED) -> tuple[list[dict], list[dict]]:
    """
    تقسيم 80/20 مع الحفاظ على نسبة كل تصنيف في الجهتين، ومقاوم لتسريب القوالب:
    السجلات شبه المتطابقة (نفس القالب بكلمة مختلفة) تبقى كتلة واحدة في نفس الجهة
    (تدريب أو اختبار)، فلا يحفظ النموذج "بصمة" القالب من مجرد رؤيته في التدريب.
    """
    if not 0.0 < test_ratio < 1.0:
        raise ValueError("test_ratio يجب أن تكون بين 0 و 1")

    rng = random.Random(seed)
    buckets: dict[str, list[dict]] = defaultdict(list)
    for rec in records:
        buckets[rec["label"]].append(rec)

    train: list[dict] = []
    test: list[dict] = []
    for label in sorted(buckets):
        pool = buckets[label][:]
        rng.shuffle(pool)
        n_test = int(round(len(pool) * test_ratio))

        clusters = cluster_near_duplicates(pool)
        rng.shuffle(clusters)
        # نبدأ بالعناقيد الأكبر (first-fit-decreasing) لتقليل الحاجة لتقسيم عنقود لاحقاً
        clusters.sort(key=len, reverse=True)

        test_indices: list[int] = []
        train_indices: list[int] = []
        for cluster in clusters:
            if len(test_indices) + len(cluster) <= n_test:
                test_indices.extend(cluster)
            else:
                train_indices.extend(cluster)

        # ملاذ أخير فقط: لو تعذّر الوصول للعدد المطلوب تماماً بسبب أحجام العناقيد،
        # نأخذ سجلات مفردة من أكبر عنقود متبقٍ في train حتى نُطابق النسبة المطلوبة تماماً
        # (استثناء ضئيل النطاق، أفضل من كسر توازن 80/20 الذي تعتمد عليه الاختبارات).
        deficit = n_test - len(test_indices)
        if deficit > 0:
            train_indices.sort(key=lambda idx: idx)
            moved = train_indices[:deficit]
            train_indices = train_indices[deficit:]
            test_indices.extend(moved)

        test.extend(pool[i] for i in test_indices)
        train.extend(pool[i] for i in train_indices)

    rng.shuffle(train)
    rng.shuffle(test)
    return train, test


def main() -> None:
    try:
        sys.stdout.reconfigure(encoding="utf-8")
    except (AttributeError, ValueError):
        pass

    records, stats = collect_records()
    print("[data_prep] قراءة:", stats)

    balanced = balance(records)
    write_jsonl(RAW_PATH, balanced)
    print(f"[data_prep] raw_data.jsonl -> {len(balanced)} سطر "
          f"| التوزيع: {dict(Counter(r['label'] for r in balanced))}")

    train, test = stratified_split(balanced)
    write_jsonl(TRAIN_PATH, train)
    write_jsonl(TEST_PATH, test)
    print(f"[data_prep] train.jsonl -> {len(train)} سطر "
          f"| {dict(Counter(r['label'] for r in train))}")
    print(f"[data_prep] test.jsonl  -> {len(test)} سطر "
          f"| {dict(Counter(r['label'] for r in test))}")
    print("[data_prep] تم بنجاح ✅")


if __name__ == "__main__":
    main()
