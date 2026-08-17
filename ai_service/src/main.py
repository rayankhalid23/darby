# -*- coding: utf-8 -*-
"""
واجهة HTTP (FastAPI) لخدمة تصنيف شكاوى السائقين — نقطة الربط الفعلية مع تطبيق Laravel.

التشغيل (من داخل مجلد ai_service/):
    uvicorn src.main:app --host 127.0.0.1 --port 8000

نقطة النهاية الرئيسية:
    POST /api/v1/predict
    Body: {"driver_id": 5, "complaint_text": "نص الشكوى..."}
    Response: نفس الهيكلية الكاملة التي يُرجعها src.predictor.predict():
        {label, confidence, action, severity, message_ar, low_confidence, scores, cleaned_text}
"""

from __future__ import annotations

import sys
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[1]
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

from fastapi import FastAPI, HTTPException  # noqa: E402
from pydantic import BaseModel, Field  # noqa: E402

from src.predictor import ModelNotFoundError, get_predictor, predict as run_predict  # noqa: E402

app = FastAPI(
    title="Driver Complaint AI Classifier",
    description="يصنّف نص شكوى موجّهة ضد سائق ويقرر الإجراء الإداري المناسب.",
    version="1.0.0",
)


class PredictRequest(BaseModel):
    driver_id: int
    complaint_text: str = Field(..., min_length=1, max_length=5000)


class PredictResponse(BaseModel):
    label: str
    confidence: float
    action: str
    severity: int
    message_ar: str
    low_confidence: bool
    scores: dict[str, float]
    cleaned_text: str


@app.get("/health")
def health() -> dict:
    """فحص جاهزية النموذج — لفحوصات التشغيل (health checks)."""
    try:
        return get_predictor().health()
    except ModelNotFoundError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@app.post("/api/v1/predict", response_model=PredictResponse)
def predict_endpoint(payload: PredictRequest) -> dict:
    """يستقبل نص شكوى ويرجّع قرار النموذج الكامل. driver_id يُستقبل للسياق/التسجيل المستقبلي."""
    try:
        return run_predict(payload.complaint_text)
    except ModelNotFoundError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except (ValueError, TypeError) as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
