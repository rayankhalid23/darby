# -*- coding: utf-8 -*-
"""
اختبارات صارمة لمشروع driver_monitoring_ai.

تغطي: سلامة البيانات، التقسيم، جودة النموذج، واجهة التنبؤ، وحالات الحافة.
"""

from __future__ import annotations

import json
import subprocess
import sys
from collections import Counter
from pathlib import Path

import pytest

PROJECT_ROOT = Path(__file__).resolve().parents[1]
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

from src import data_prep  # noqa: E402
from src.data_prep import (  # noqa: E402
    LABELS,
    PER_LABEL,
    RAW_PATH,
    TEST_PATH,
    TRAIN_PATH,
    _word_bigrams,
    clean_text,
    cluster_near_duplicates,
    dedup_key,
    load_jsonl,
    stratified_split,
)
from src.predictor import (  # noqa: E402
    ACTIONS,
    LOW_CONFIDENCE_THRESHOLD,
    MAX_TEXT_LENGTH,
    ModelNotFoundError,
    Predictor,
    get_predictor,
    reset_predictor,
)
from src.train_model import MODEL_PATH  # noqa: E402

REQUIRED_KEYS = {
    "label", "confidence", "action", "severity",
    "message_ar", "low_confidence", "scores", "cleaned_text",
}


# ------------------------------------------------------------------ fixtures
@pytest.fixture(scope="session", autouse=True)
def ensure_artifacts():
    """يبني البيانات والنموذج تلقائياً إذا كانت مفقودة."""
    if not (RAW_PATH.exists() and TRAIN_PATH.exists() and TEST_PATH.exists()):
        subprocess.run([sys.executable, "src/data_prep.py"],
                       cwd=PROJECT_ROOT, check=True)
    if not MODEL_PATH.exists():
        subprocess.run([sys.executable, "src/train_model.py"],
                       cwd=PROJECT_ROOT, check=True)


@pytest.fixture(scope="session")
def predictor(ensure_artifacts):
    reset_predictor()
    return get_predictor()


@pytest.fixture(scope="session")
def raw_records(ensure_artifacts):
    return load_jsonl(RAW_PATH)


# ======================================================= 1) سلامة البيانات
class TestDataIntegrity:

    def test_raw_file_has_exactly_4000_rows(self, raw_records):
        assert len(raw_records) == 4000

    def test_raw_is_perfectly_balanced(self, raw_records):
        counts = Counter(r["label"] for r in raw_records)
        assert set(counts) == set(LABELS)
        assert all(counts[label] == PER_LABEL for label in LABELS), counts

    def test_no_duplicate_texts_after_normalization(self, raw_records):
        keys = [dedup_key(r["complaint_text"]) for r in raw_records]
        assert len(set(keys)) == len(keys), "توجد نصوص مكررة بعد التطبيع"

    def test_every_row_has_valid_schema(self, raw_records):
        for i, rec in enumerate(raw_records):
            assert set(rec) == {"complaint_text", "label"}, f"سطر {i}"
            assert isinstance(rec["complaint_text"], str)
            assert rec["complaint_text"].strip(), f"نص فارغ في السطر {i}"
            assert rec["label"] in LABELS, f"تصنيف غير معروف في السطر {i}"

    def test_texts_are_clean(self, raw_records):
        for rec in raw_records:
            text = rec["complaint_text"]
            assert text == text.strip()
            assert "  " not in text, f"مسافات مزدوجة: {text!r}"
            assert "\n" not in text and "\t" not in text

    def test_texts_contain_arabic(self, raw_records):
        for rec in raw_records:
            assert any("؀" <= ch <= "ۿ" for ch in rec["complaint_text"])

    def test_raw_file_is_valid_utf8_jsonl(self):
        with RAW_PATH.open(encoding="utf-8") as fh:
            for lineno, line in enumerate(fh, 1):
                if line.strip():
                    json.loads(line)  # يرفع استثناءً لو تالف

    def test_normal_file_has_1000_unique_rows(self):
        records = load_jsonl(data_prep.DATA_DIR / "normal_data.jsonl")
        assert len(records) == 1000
        assert len({r["complaint_text"] for r in records}) == 1000
        assert {r["label"] for r in records} == {"normal"}


# ==================================================== 2) التنظيف والتطبيع
class TestTextCleaning:

    @pytest.mark.parametrize("raw,expected", [
        ("  نص   فيه   مسافات  ", "نص فيه مسافات"),
        ("سطر\nجديد", "سطر جديد"),
        ("تاب\tهنا", "تاب هنا"),
        ("مـــمـــدود", "ممدود"),
    ])
    def test_clean_text_normalizes(self, raw, expected):
        assert clean_text(raw) == expected

    def test_clean_text_is_idempotent(self):
        text = "  السائق   كان\tهادئ  "
        assert clean_text(clean_text(text)) == clean_text(text)

    def test_clean_text_rejects_non_string(self):
        for bad in (None, 123, [], {}):
            with pytest.raises(TypeError):
                clean_text(bad)

    def test_dedup_key_matches_punctuation_variants(self):
        assert dedup_key("تأخر 3 دقايق، قال كذا.") == dedup_key("تأخر 3 دقايق قال كذا")

    def test_dedup_key_matches_hamza_variants(self):
        assert dedup_key("أغنية قديمة") == dedup_key("اغنية قديمة")

    def test_dedup_key_separates_different_texts(self):
        assert dedup_key("السائق هادئ") != dedup_key("السائق متهور")


# ======================================================== 3) تقسيم البيانات
class TestSplit:

    def test_split_sizes_are_80_20(self):
        train, test = load_jsonl(TRAIN_PATH), load_jsonl(TEST_PATH)
        assert len(train) == 3200
        assert len(test) == 800
        assert len(train) + len(test) == 4000

    def test_split_is_stratified(self):
        for path, per_label in ((TRAIN_PATH, 800), (TEST_PATH, 200)):
            counts = Counter(r["label"] for r in load_jsonl(path))
            assert all(counts[label] == per_label for label in LABELS), counts

    def test_no_leakage_between_train_and_test(self):
        train = {dedup_key(r["complaint_text"]) for r in load_jsonl(TRAIN_PATH)}
        test = {dedup_key(r["complaint_text"]) for r in load_jsonl(TEST_PATH)}
        assert not (train & test), "تسريب بيانات بين التدريب والاختبار"

    def test_no_near_duplicate_template_leakage_between_train_and_test(self):
        """
        يكتشف تسريب "القوالب" (نفس هيكل الجملة بكلمة مختلفة، مثال:
        "كاد يخبط في حافلة" مقابل "كاد يخبط في دراجة") الذي لا يكتشفه dedup_key
        وحده — لكل سجل اختبار، يجب ألا يوجد سجل تدريب بنفس تصنيفه يتجاوز تشابه
        Jaccard على ثنائيات الكلمات عتبة 0.5 (نفس العتبة المستخدمة في التقسيم).
        """
        train_by_label: dict[str, list[set]] = {}
        for r in load_jsonl(TRAIN_PATH):
            train_by_label.setdefault(r["label"], []).append(_word_bigrams(r["complaint_text"]))

        leaks = []
        for r in load_jsonl(TEST_PATH):
            test_bigrams = _word_bigrams(r["complaint_text"])
            if not test_bigrams:
                continue
            for train_bigrams in train_by_label.get(r["label"], []):
                if not train_bigrams:
                    continue
                union = len(test_bigrams | train_bigrams)
                jaccard = (len(test_bigrams & train_bigrams) / union) if union else 0.0
                if jaccard >= 0.5:
                    leaks.append(r["complaint_text"])
                    break

        assert not leaks, f"تسريب قوالب بين التدريب والاختبار ({len(leaks)} حالة): {leaks[:3]}"

    def test_cluster_near_duplicates_groups_template_variants_together(self):
        records = [
            {"complaint_text": "بنتي قالت إن السواق كاد يخبط في حافلة", "label": "driver_alert"},
            {"complaint_text": "بنتي قالت إن السواق كاد يخبط في دراجة", "label": "driver_alert"},
            {"complaint_text": "السائق محترم جداً وشكراً له على كل شي", "label": "normal"},
        ]
        clusters = cluster_near_duplicates(records)
        cluster_of = {}
        for idx, cluster in enumerate(clusters):
            for i in cluster:
                cluster_of[i] = idx

        assert cluster_of[0] == cluster_of[1], "نفس القالب يجب أن يقع في نفس العنقود"
        assert cluster_of[2] != cluster_of[0], "نص مختلف تماماً يجب ألا يُجمَّع مع القالب الآخر"

    def test_split_is_deterministic(self, raw_records):
        a_train, a_test = stratified_split(raw_records)
        b_train, b_test = stratified_split(raw_records)
        assert [r["complaint_text"] for r in a_train] == \
               [r["complaint_text"] for r in b_train]
        assert [r["complaint_text"] for r in a_test] == \
               [r["complaint_text"] for r in b_test]

    def test_split_rejects_invalid_ratio(self):
        for ratio in (0, 1, -0.5, 1.5):
            with pytest.raises(ValueError):
                stratified_split([{"complaint_text": "x", "label": "normal"}], ratio)

    def test_balance_raises_when_class_is_short(self):
        few = [{"complaint_text": f"نص {i}", "label": "normal"} for i in range(10)]
        with pytest.raises(ValueError):
            data_prep.balance(few)


# ========================================================= 4) جودة النموذج
class TestModelQuality:

    def test_model_file_exists_and_not_empty(self):
        assert MODEL_PATH.exists()
        assert MODEL_PATH.stat().st_size > 1000

    def test_model_knows_all_four_labels(self, predictor):
        assert set(predictor.labels) == set(LABELS)

    def test_saved_metrics_meet_threshold(self, predictor):
        metrics = predictor.metrics
        assert metrics["test_accuracy"] >= 0.90, metrics
        assert metrics["test_macro_f1"] >= 0.90, metrics
        assert metrics["cv5_mean_accuracy"] >= 0.90, metrics

    def test_accuracy_on_holdout_test_set(self, predictor):
        records = load_jsonl(TEST_PATH)
        texts = [r["complaint_text"] for r in records]
        gold = [r["label"] for r in records]
        pred = [p["label"] for p in predictor.predict_batch(texts)]
        accuracy = sum(g == p for g, p in zip(gold, pred)) / len(gold)
        assert accuracy >= 0.95, f"الدقة منخفضة: {accuracy:.4f}"

    def test_per_class_recall_is_high(self, predictor):
        records = load_jsonl(TEST_PATH)
        pred = predictor.predict_batch([r["complaint_text"] for r in records])
        hits, totals = Counter(), Counter()
        for rec, out in zip(records, pred):
            totals[rec["label"]] += 1
            hits[rec["label"]] += rec["label"] == out["label"]
        for label in LABELS:
            recall = hits[label] / totals[label]
            assert recall >= 0.90, f"recall منخفض لـ {label}: {recall:.3f}"

    def test_no_severe_misclassification(self, predictor):
        """يجب ألا تُصنَّف مخالفة جسيمة كملاحظة عادية والعكس."""
        records = load_jsonl(TEST_PATH)
        pred = predictor.predict_batch([r["complaint_text"] for r in records])
        for rec, out in zip(records, pred):
            pair = {rec["label"], out["label"]}
            assert pair != {"deactivate", "normal"}, rec["complaint_text"]

    @pytest.mark.parametrize("text,expected", [
        ("الحمد لله الأولاد وصلوا البيت بالسلامة والسواق محترم ومؤدب جداً.", "normal"),
        ("شكراً للكابتن، وصل في الوقت المحدد وكل شي تمام.", "normal"),
        ("تأخر دقيقتين قال في زحمة على الدوار.", "ignore"),
        ("ما ردش على التلفون مرة وحدة الصبح.", "ignore"),
        ("السواق كان يستخدم الموبايل ويكتب رسايل وهو يسوق بسرعة.", "driver_alert"),
        ("بنتي قالت إن السواق كاد يخبط في سيارة وهو يغير الحارة فجأة.", "driver_alert"),
        ("السائق شتم ولدي بألفاظ نابية وطرده من الباص في نص الطريق.", "deactivate"),
        ("السائق كان يشرب الخمر وهو يسوق بالعيال، لازم إيقافه فوراً.", "deactivate"),
    ])
    def test_predicts_unseen_examples_correctly(self, predictor, text, expected):
        assert predictor.predict(text)["label"] == expected

    def test_predictions_are_deterministic(self, predictor):
        text = "السواق كان يسرع بشكل خطير في منطقة المدارس."
        first = predictor.predict(text)
        second = predictor.predict(text)
        assert first["label"] == second["label"]
        assert first["confidence"] == second["confidence"]

    def test_prediction_invariant_to_extra_whitespace(self, predictor):
        base = "السائق كان هادئ وملتزم بالمواعيد طول الأسبوع."
        noisy = f"   {base.replace(' ', '   ')}  \n"
        assert predictor.predict(base)["label"] == predictor.predict(noisy)["label"]


# ================================================ 5) عقد مخرجات التنبؤ
class TestPredictionContract:

    def test_output_has_all_required_keys(self, predictor):
        out = predictor.predict("السواق تأخر خمس دقايق بسبب الزحمة.")
        assert REQUIRED_KEYS <= set(out)

    def test_confidence_within_zero_and_one(self, predictor):
        out = predictor.predict("السائق محترم ومؤدب.")
        assert 0.0 <= out["confidence"] <= 1.0

    def test_scores_cover_all_labels_and_sum_to_one(self, predictor):
        out = predictor.predict("السائق كان يسوق بسرعة جنونية.")
        assert set(out["scores"]) == set(LABELS)
        assert abs(sum(out["scores"].values()) - 1.0) < 0.01

    def test_label_matches_highest_score(self, predictor):
        out = predictor.predict("السائق ضرب ولدي على وجهه.")
        assert out["label"] == max(out["scores"], key=out["scores"].get)

    def test_action_mapping_is_consistent(self, predictor):
        out = predictor.predict("السائق كان ينظر في الموبايل أثناء القيادة.")
        expected = ACTIONS[out["label"]]
        assert out["action"] == expected["action"]
        assert out["severity"] == expected["severity"]
        assert out["message_ar"] == expected["message_ar"]

    def test_severity_ordering_is_sane(self):
        assert (ACTIONS["normal"]["severity"] < ACTIONS["ignore"]["severity"]
                < ACTIONS["driver_alert"]["severity"]
                < ACTIONS["deactivate"]["severity"])

    def test_actions_cover_every_label(self):
        assert set(ACTIONS) == set(LABELS)

    def test_low_confidence_flag_matches_threshold(self, predictor):
        out = predictor.predict("السائق محترم جداً وشكراً له.")
        assert out["low_confidence"] == (out["confidence"] < LOW_CONFIDENCE_THRESHOLD)

    def test_json_serializable_for_web(self, predictor):
        out = predictor.predict("السواق تأخر دقيقتين.")
        assert json.loads(json.dumps(out, ensure_ascii=False))["label"] == out["label"]


# ====================================================== 6) حالات الحافة
class TestEdgeCases:

    @pytest.mark.parametrize("bad", ["", "   ", "\n", "\t\t", "  \n \t "])
    def test_empty_or_whitespace_raises_value_error(self, predictor, bad):
        with pytest.raises(ValueError):
            predictor.predict(bad)

    def test_none_raises_value_error(self, predictor):
        with pytest.raises(ValueError):
            predictor.predict(None)

    @pytest.mark.parametrize("bad", [123, 4.5, [], {}, True, ("نص",)])
    def test_non_string_raises_type_error(self, predictor, bad):
        with pytest.raises(TypeError):
            predictor.predict(bad)

    def test_text_over_max_length_raises(self, predictor):
        with pytest.raises(ValueError):
            predictor.predict("ا" * (MAX_TEXT_LENGTH + 1))

    def test_text_at_max_length_is_accepted(self, predictor):
        out = predictor.predict("السائق كان متهور. " * 260)
        assert out["label"] in LABELS

    @pytest.mark.parametrize("text", [
        ".", "؟", "123456", "!!!", "😀😀", "ok", "hello world",
        "asdkjhaskdjh", "١٢٣", "-", "@#$%^&*",
        "السائق", "لا", "🚗🚌 السواق",
        "driver was very fast today",
        "السواق fast جداً today",
        "ااااااااااااااااااااااااا",
    ])
    def test_short_or_noisy_input_never_crashes(self, predictor, text):
        out = predictor.predict(text)
        assert out["label"] in LABELS
        assert 0.0 <= out["confidence"] <= 1.0
        assert REQUIRED_KEYS <= set(out)

    def test_html_and_injection_like_input_is_handled(self, predictor):
        out = predictor.predict("<script>alert('x')</script> السائق كان مسرع جداً")
        assert out["label"] in LABELS

    def test_very_long_single_word_is_handled(self, predictor):
        out = predictor.predict("سائق" * 500)
        assert out["label"] in LABELS


# =========================================================== 7) الدفعات
class TestBatch:

    def test_empty_list_returns_empty_list(self, predictor):
        assert predictor.predict_batch([]) == []

    def test_batch_matches_single_predictions(self, predictor):
        texts = [
            "السائق محترم ومؤدب وشكراً له.",
            "تأخر دقيقتين قال في زحمة.",
            "السواق كان يسوق بسرعة جنونية وسط المدرسة.",
        ]
        batch = [o["label"] for o in predictor.predict_batch(texts)]
        single = [predictor.predict(t)["label"] for t in texts]
        assert batch == single

    def test_batch_preserves_order(self, predictor):
        texts = ["السائق محترم ومؤدب.", "السائق شتم ولدي وطرده من الباص."]
        outs = predictor.predict_batch(texts)
        assert [o["cleaned_text"] for o in outs] == [clean_text(t) for t in texts]

    def test_batch_rejects_bare_string(self, predictor):
        with pytest.raises(TypeError):
            predictor.predict_batch("نص مفرد وليس قائمة")

    def test_batch_rejects_invalid_member(self, predictor):
        with pytest.raises((ValueError, TypeError)):
            predictor.predict_batch(["نص سليم", ""])

    def test_batch_accepts_generator(self, predictor):
        outs = predictor.predict_batch(t for t in ["السائق هادئ.", "السائق متهور."])
        assert len(outs) == 2


# ====================================================== 8) تحميل النموذج
class TestLoading:

    def test_missing_model_raises_model_not_found(self, tmp_path):
        with pytest.raises(ModelNotFoundError):
            Predictor(tmp_path / "no_such_model.joblib")

    def test_corrupt_model_raises_value_error(self, tmp_path):
        import joblib
        bad = tmp_path / "bad.joblib"
        joblib.dump({"not_a_pipeline": 1}, bad)
        with pytest.raises(ValueError):
            Predictor(bad)

    def test_get_predictor_returns_singleton(self, predictor):
        assert get_predictor() is get_predictor()

    def test_reset_predictor_forces_reload(self, predictor):
        first = get_predictor()
        reset_predictor()
        assert get_predictor() is not first

    def test_health_endpoint_payload(self, predictor):
        health = get_predictor().health()
        assert health["status"] == "ok"
        assert set(health["labels"]) == set(LABELS)
        assert "test_accuracy" in health["metrics"]
