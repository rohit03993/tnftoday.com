from __future__ import annotations

from typing import Any

import fitz  # PyMuPDF

from app.settings import settings
from app.storage import fetch_bytes_from_s3, put_bytes, presign_get


def render_pdf_to_pages(pdf_bytes: bytes, job_id: str) -> list[dict[str, Any]]:
    doc = fitz.open(stream=pdf_bytes, filetype="pdf")
    pages_out: list[dict[str, Any]] = []
    zoom = settings.render_dpi / 72
    mat = fitz.Matrix(zoom, zoom)

    for i in range(doc.page_count):
        page = doc.load_page(i)
        pix = page.get_pixmap(matrix=mat, alpha=False)
        img_bytes = pix.tobytes("png")
        key = f"{job_id}/page-{i + 1}.png"
        put_bytes(key, img_bytes, bucket=settings.s3_bucket_images, content_type="image/png")
        url = presign_get(key, settings.s3_bucket_images, expires=7200)
        rect = page.rect
        pages_out.append(
            {
                "page": i + 1,
                "url": url,
                "width_pt": float(rect.width),
                "height_pt": float(rect.height),
            }
        )

    doc.close()
    return pages_out


def crop_region_from_pdf_key(
    pdf_s3_key: str,
    page_num: int,
    x0: float,
    y0: float,
    x1: float,
    y1: float,
    job_id: str,
) -> tuple[str, str]:
    raw = fetch_bytes_from_s3(pdf_s3_key, settings.s3_bucket_pdfs)
    doc = fitz.open(stream=raw, filetype="pdf")
    if page_num < 1 or page_num > doc.page_count:
        doc.close()
        raise ValueError("invalid page")
    page = doc.load_page(page_num - 1)
    rect = fitz.Rect(x0, y0, x1, y1)
    clip = page.rect & rect
    if clip.is_empty:
        clip = fitz.Rect(x0, y0, x1, y1)
    pix = page.get_pixmap(matrix=fitz.Matrix(2, 2), clip=clip, alpha=False)
    img_bytes = pix.tobytes("png")
    out_key = f"{job_id}/crop-{page_num}-{abs(hash(clip))}.png"
    put_bytes(out_key, img_bytes, bucket=settings.s3_bucket_images, content_type="image/png")
    url = presign_get(out_key, settings.s3_bucket_images, expires=7200)
    doc.close()
    return out_key, url


def crop_region_from_bytes(
    pdf_bytes: bytes,
    page_num: int,
    x0: float,
    y0: float,
    x1: float,
    y1: float,
    job_id: str,
) -> str:
    doc = fitz.open(stream=pdf_bytes, filetype="pdf")
    if page_num < 1 or page_num > doc.page_count:
        doc.close()
        raise ValueError("invalid page")
    page = doc.load_page(page_num - 1)
    rect = fitz.Rect(x0, y0, x1, y1)
    clip = page.rect & rect
    if clip.is_empty:
        clip = rect
    pix = page.get_pixmap(matrix=fitz.Matrix(2, 2), clip=clip, alpha=False)
    img_bytes = pix.tobytes("png")
    out_key = f"{job_id}/crop-{page_num}-{abs(hash(clip))}.png"
    put_bytes(out_key, img_bytes, bucket=settings.s3_bucket_images, content_type="image/png")
    url = presign_get(out_key, settings.s3_bucket_images, expires=7200)
    doc.close()
    return url
