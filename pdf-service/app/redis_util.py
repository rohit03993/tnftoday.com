from __future__ import annotations

import json
from typing import Any

import redis

from app.settings import settings


def conn() -> redis.Redis:
    return redis.from_url(settings.redis_url)


def job_key(job_id: str) -> str:
    return f"tnf:pdf:job:{job_id}"


def idem_key(key: str) -> str:
    return f"tnf:pdf:idem:{key}"


def set_job(job_id: str, data: dict[str, Any]) -> None:
    c = conn()
    c.set(job_key(job_id), json.dumps(data), ex=86400 * 7)


def get_job(job_id: str) -> dict[str, Any] | None:
    c = conn()
    raw = c.get(job_key(job_id))
    if not raw:
        return None
    return json.loads(raw)


def set_idem(idempotency_key: str, job_id: str) -> None:
    conn().set(idem_key(idempotency_key), job_id, ex=86400 * 7)


def get_idem(idempotency_key: str) -> str | None:
    v = conn().get(idem_key(idempotency_key))
    return v.decode() if v else None
