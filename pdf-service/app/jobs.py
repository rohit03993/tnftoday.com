from __future__ import annotations

import httpx

from app.pdf_ops import render_pdf_to_pages
from app.redis_util import get_job, set_job
from app.settings import settings
from app.storage import new_job_id, put_bytes


def run_pdf_job(job_id: str, source_url: str, external_id: str) -> None:
    set_job(
        job_id,
        {
            "job_id": job_id,
            "external_id": external_id,
            "status": "processing",
            "pages": [],
            "pdf_s3_key": f"{job_id}/source.pdf",
        },
    )

    try:
        r = httpx.get(source_url, follow_redirects=True, timeout=120.0)
        r.raise_for_status()
        pdf_bytes = r.content
        pdf_key = f"{job_id}/source.pdf"
        put_bytes(pdf_key, pdf_bytes, bucket=settings.s3_bucket_pdfs, content_type="application/pdf")

        pages = render_pdf_to_pages(pdf_bytes, job_id)
        set_job(
            job_id,
            {
                "job_id": job_id,
                "external_id": external_id,
                "status": "ready",
                "pages": pages,
                "pdf_s3_key": pdf_key,
            },
        )
        _notify_wordpress(job_id, external_id, pages)
    except Exception as e:  # noqa: BLE001
        set_job(
            job_id,
            {
                "job_id": job_id,
                "external_id": external_id,
                "status": "failed",
                "error": str(e),
                "pages": [],
                "pdf_s3_key": f"{job_id}/source.pdf",
            },
        )


def _notify_wordpress(job_id: str, external_id: str, pages: list) -> None:
    url = settings.wordpress_callback_url.strip()
    if not url:
        return
    secret = settings.wp_callback_secret
    try:
        httpx.post(
            url,
            json={
                "job_id": job_id,
                "external_id": external_id,
                "status": "ready",
                "pages": pages,
            },
            headers={"X-WP-Callback-Secret": secret},
            timeout=30.0,
        )
    except Exception:  # noqa: BLE001
        pass


def enqueue_process(source_url: str, external_id: str, idempotency_key: str | None) -> str:
    from redis import Redis
    from rq import Queue

    from app.redis_util import get_idem, set_idem

    if idempotency_key:
        existing = get_idem(idempotency_key)
        if existing:
            return existing

    job_id = new_job_id()
    if idempotency_key:
        set_idem(idempotency_key, job_id)

    set_job(
        job_id,
        {
            "job_id": job_id,
            "external_id": external_id,
            "status": "queued",
            "pages": [],
            "pdf_s3_key": f"{job_id}/source.pdf",
        },
    )

    q = Queue(connection=Redis.from_url(settings.redis_url), default_timeout=600)
    q.enqueue(run_pdf_job, job_id, source_url, external_id, job_id=f"pdf-{job_id}")
    return job_id


def job_status(job_id: str) -> dict | None:
    return get_job(job_id)
