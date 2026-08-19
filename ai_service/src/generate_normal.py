# -*- coding: utf-8 -*-
"""
مولّد بيانات تصنيف "normal".

تصنيف normal = ملاحظة أو تعليق من ولي الأمر لا يحمل أي شكوى فعلية
(شكر، اطمئنان، تأكيد وصول، سؤال تنظيمي عادي، ثناء على السائق).
يجب أن يبقى مختلفاً بوضوح عن:
  - ignore        : شكوى تافهة/بسيطة (تأخر دقيقتين، ما ردش على التلفون).
  - driver_alert  : سلوك خطر يستدعي تنبيه السائق.
  - deactivate    : مخالفة جسيمة تستدعي إيقاف السائق.

التوليد تركيبي (combinatorial) مع خلط لهجات: فصحى/خليجي/مصري/مغاربي/شامي،
مطابقةً لأسلوب باقي ملفات البيانات.
"""

from __future__ import annotations

import json
import random
from itertools import product
from pathlib import Path

TARGET_COUNT = 1000
LABEL = "normal"
SEED = 20240812

DATA_DIR = Path(__file__).resolve().parents[1] / "data"
OUTPUT_PATH = DATA_DIR / "normal_data.jsonl"

DRIVER = [
    "السائق",
    "السواق",
    "الكابتن",
    "الشوفير",
    "سائق الباص",
    "كابتن الحافلة",
    "السائق المسؤول عن الخط",
]

CHILD = [
    "ولدي",
    "بنتي",
    "العيال",
    "الأولاد",
    "ابني",
    "ابنتي",
    "الطلاب",
    "أطفالي",
]

PRAISE = [
    "محترم ومؤدب",
    "هادي وملتزم",
    "دايماً في الموعد",
    "يسوق بهدوء وأمان",
    "متعاون وخلوق",
    "حريص على سلامة العيال",
    "نظيف ومرتب ومنظم",
    "صبور ويعامل الصغار بلطف",
    "ملتزم بقوانين المرور",
    "يتأكد من الأحزمة قبل ما يتحرك",
]

THANK = [
    "شكراً له وللإدارة",
    "الله يعطيه العافية",
    "تسلم إيديه",
    "ما شاء الله عليه",
    "نشكركم على الخدمة",
    "بارك الله فيكم",
    "ربي يحفظه",
    "جزاكم الله خير",
]

TIME_OK = [
    "في الوقت المحدد بالضبط",
    "قبل الموعد بخمس دقايق",
    "على الساعة تماماً",
    "في وقته المعتاد",
    "بدون أي تأخير",
    "مثل كل يوم في نفس التوقيت",
]

TRIP = [
    "رحلة الصباح",
    "رحلة العودة",
    "طريق المدرسة",
    "خط الرجعة من المدرسة",
    "مشوار اليوم",
    "الرحلة اليومية",
]

CALM_STATE = [
    "كل شي تمام",
    "ما في أي مشكلة",
    "الوضع ممتاز",
    "كله على ما يرام",
    "ولا ملاحظة عندي",
    "الحمد لله كل شي طبيعي",
    "ما عندي أي شكوى",
]

QUESTION = [
    "حبيت أتأكد من موعد الرحلة بكرة",
    "أستفسر عن نقطة التجمع الجديدة",
    "أحب أعرف إذا فيه تغيير في الخط الأسبوع الجاي",
    "بسأل عن إجراءات تغيير عنوان الاستلام",
    "ودي أعرف رقم الباص الجديد",
    "استفسار بسيط عن جدول الإجازة",
    "أبغى أعرف وين ألاقي تطبيق المتابعة",
]

NOTICE = [
    "ولدي بيكون مسافر الأسبوع الجاي فما يحتاج الباص",
    "بنتي عندها موعد دكتور بكرة وبتغيب عن الرحلة",
    "بنغير عنوان البيت الشهر الجاي وراح أبلغكم رسمياً",
    "العيال بيرجعوا مع والدهم اليوم فقط",
    "ابني بيتأخر شوي اليوم بسبب حصة تقوية",
    "حبيت أعلمكم إن رقم تلفوني تغير",
]

CONFIRM = [
    "وصلوا البيت بالسلامة",
    "وصل المدرسة بأمان",
    "نزلوا قدام البيت مباشرة",
    "استلمتهم عند البوابة عادي",
    "رجعوا سالمين والحمد لله",
    "وصلت البنت وهي مبسوطة",
]

BEHAVIOR_OK = [
    "كان يسوق بسرعة معقولة طول الطريق",
    "وقف في المكان الصحيح وانتظر لين ركبوا",
    "تأكد من إغلاق الأبواب قبل التحرك",
    "كان الجو داخل الباص مريح ونظيف",
    "التزم بالمسار المتفق عليه بدون تغيير",
    "نبهنا بمكالمة قبل ما يوصل بخمس دقايق",
    "ساعد الصغار وهم ينزلون من الباص",
    "كان هادئ وما رفع صوته على أحد",
]

CLOSER = [
    "استمروا على هذا المستوى",
    "نتمنى يستمر الحال كذا",
    "هذا هو المطلوب بالضبط",
    "راضين تماماً عن الخدمة",
    "ما نحتاج أي إجراء، بس حبيت أوثق الملاحظة",
    "الرجاء إيصال شكري له",
    "تحية طيبة لكم",
    "نقدر جهودكم",
]

DAY = [
    "اليوم",
    "أمس",
    "الصباح",
    "هالأسبوع",
    "من بداية الترم",
    "طول الشهر الماضي",
    "من أول يوم دراسة",
]


def _templates() -> list[tuple[str, dict]]:
    """كل قالب = (نص فيه {slots}, خريطة الـ slots إلى قوائم القيم)."""
    return [
        ("{day} {driver} كان {praise}، {thank}.",
         {"day": DAY, "driver": DRIVER, "praise": PRAISE, "thank": THANK}),

        ("{child} رجعوا من {trip} و{calm}، {thank}.",
         {"child": CHILD, "trip": TRIP, "calm": CALM_STATE, "thank": THANK}),

        ("{driver} وصل {time_ok} و{confirm}.",
         {"driver": DRIVER, "time_ok": TIME_OK, "confirm": CONFIRM}),

        ("{behavior}، {closer}.",
         {"behavior": BEHAVIOR_OK, "closer": CLOSER}),

        ("{calm} في {trip}، {driver} {praise}.",
         {"calm": CALM_STATE, "trip": TRIP, "driver": DRIVER, "praise": PRAISE}),

        ("ما عندي شكوى، بس {question}.",
         {"question": QUESTION}),

        ("{notice}، {closer}.",
         {"notice": NOTICE, "closer": CLOSER}),

        ("{child} قالوا إن {driver} {praise} {day}، {thank}.",
         {"child": CHILD, "driver": DRIVER, "praise": PRAISE, "day": DAY,
          "thank": THANK}),

        ("{driver} {behavior}، {calm}.",
         {"driver": DRIVER, "behavior": BEHAVIOR_OK, "calm": CALM_STATE}),

        ("{thank}، {child} {confirm} و{calm}.",
         {"thank": THANK, "child": CHILD, "confirm": CONFIRM,
          "calm": CALM_STATE}),

        ("حبيت أشكر {driver} على {trip}، كان {praise}.",
         {"driver": DRIVER, "trip": TRIP, "praise": PRAISE}),

        ("{day} {confirm}، {driver} {praise} و{closer}.",
         {"day": DAY, "confirm": CONFIRM, "driver": DRIVER, "praise": PRAISE,
          "closer": CLOSER}),

        ("رسالة تنظيمية فقط: {notice}.",
         {"notice": NOTICE}),

        ("{question}، وغير كذا {calm}.",
         {"question": QUESTION, "calm": CALM_STATE}),

        ("{driver} وصل {time_ok} في {trip}، {closer}.",
         {"driver": DRIVER, "time_ok": TIME_OK, "trip": TRIP,
          "closer": CLOSER}),
    ]


def generate(count: int = TARGET_COUNT, seed: int = SEED) -> list[str]:
    """يولّد `count` جملة فريدة موزعة بالتساوي تقريباً على كل القوالب."""
    rng = random.Random(seed)
    templates = _templates()

    # نبني فضاء كامل لكل قالب ثم نخلطه، حتى نضمن التفرد بدون حلقة عشوائية
    # قد لا تنتهي.
    pools: list[list[str]] = []
    for text, slots in templates:
        keys = list(slots)
        combos = list(product(*(slots[k] for k in keys)))
        rng.shuffle(combos)
        pools.append([text.format(**dict(zip(keys, c))) for c in combos])

    capacity = sum(len(p) for p in pools)
    if capacity < count:
        raise ValueError(
            f"فضاء التوليد ({capacity}) أصغر من المطلوب ({count})"
        )

    sentences: list[str] = []
    seen: set[str] = set()
    cursors = [0] * len(pools)
    # round-robin على القوالب => توزيع متوازن للأنماط اللغوية
    while len(sentences) < count:
        progressed = False
        for i, pool in enumerate(pools):
            if len(sentences) >= count:
                break
            while cursors[i] < len(pool):
                candidate = pool[cursors[i]]
                cursors[i] += 1
                progressed = True
                if candidate not in seen:
                    seen.add(candidate)
                    sentences.append(candidate)
                    break
        if not progressed:
            break

    if len(sentences) < count:
        raise ValueError(f"تم توليد {len(sentences)} فقط من أصل {count}")

    rng.shuffle(sentences)
    return sentences


def main() -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    sentences = generate()

    with OUTPUT_PATH.open("w", encoding="utf-8") as fh:
        for text in sentences:
            fh.write(json.dumps(
                {"complaint_text": text, "label": LABEL},
                ensure_ascii=False,
            ) + "\n")

    print(f"[generate_normal] تم إنشاء {len(sentences)} سطر فريد -> {OUTPUT_PATH}")


if __name__ == "__main__":
    main()
