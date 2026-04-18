from __future__ import annotations

import uuid
from typing import Any

import boto3
from botocore.client import BaseClient

from app.settings import settings


def s3_client() -> BaseClient:
    return boto3.client(
        "s3",
        endpoint_url=settings.s3_endpoint_url,
        aws_access_key_id=settings.s3_access_key,
        aws_secret_access_key=settings.s3_secret_key,
        region_name=settings.s3_region,
    )


def put_bytes(key: str, body: bytes, bucket: str | None = None, content_type: str = "application/octet-stream") -> None:
    b = bucket or settings.s3_bucket_pdfs
    s3_client().put_object(Bucket=b, Key=key, Body=body, ContentType=content_type)


def presign_get(key: str, bucket: str, expires: int = 3600) -> str:
    return s3_client().generate_presigned_url(
        "get_object",
        Params={"Bucket": bucket, "Key": key},
        ExpiresIn=expires,
    )


def fetch_bytes_from_s3(key: str, bucket: str) -> bytes:
    obj = s3_client().get_object(Bucket=bucket, Key=key)
    return obj["Body"].read()


def new_job_id() -> str:
    return str(uuid.uuid4())
