from __future__ import annotations

from fastapi import Depends, FastAPI, Header, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from app.jobs import enqueue_process, job_status
from app.pdf_ops import crop_region_from_pdf_key
from app.settings import settings

app = FastAPI(title="TNF PDF Service", version="1.0.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8080", "http://127.0.0.1:8080"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def verify_secret(x_service_secret: str | None = Header(default=None, alias="X-Service-Secret")) -> None:
    if not x_service_secret or not settings.service_secret:
        raise HTTPException(status_code=401, detail="missing service secret")
    if x_service_secret != settings.service_secret:
        raise HTTPException(status_code=403, detail="invalid service secret")


class ProcessRequest(BaseModel):
    source_url: str
    external_id: str
    idempotency_key: str | None = None


class CropRequest(BaseModel):
    job_id: str
    page: int = Field(ge=1)
    x0: float
    y0: float
    x1: float
    y1: float


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/pdf/process", dependencies=[Depends(verify_secret)])
def pdf_process(body: ProcessRequest) -> dict:
    jid = enqueue_process(body.source_url, body.external_id, body.idempotency_key)
    return {"job_id": jid, "status": "queued"}


@app.get("/pdf/{job_id}/status", dependencies=[Depends(verify_secret)])
def pdf_status(job_id: str) -> dict:
    data = job_status(job_id)
    if not data:
        raise HTTPException(status_code=404, detail="job not found")
    return data


@app.post("/pdf/crop", dependencies=[Depends(verify_secret)])
def pdf_crop(body: CropRequest) -> dict:
    data = job_status(body.job_id)
    if not data:
        raise HTTPException(status_code=404, detail="job not found")
    pdf_key = data.get("pdf_s3_key")
    if not pdf_key:
        raise HTTPException(status_code=400, detail="pdf not available")
    try:
        _, url = crop_region_from_pdf_key(
            pdf_key, body.page, body.x0, body.y0, body.x1, body.y1, body.job_id
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e)) from e
    return {"url": url}
