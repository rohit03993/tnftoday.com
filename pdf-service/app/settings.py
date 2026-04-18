from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    redis_url: str = "redis://localhost:6379/0"
    s3_endpoint_url: str = "http://127.0.0.1:9000"
    s3_access_key: str = "minioadmin"
    s3_secret_key: str = "minioadmin"
    s3_region: str = "us-east-1"
    s3_bucket_pdfs: str = "tnf-pdfs"
    s3_bucket_images: str = "tnf-images"
    service_secret: str = "change-me-in-prod"
    wp_callback_secret: str = ""
    wordpress_callback_url: str = ""

    render_dpi: int = 120


settings = Settings()
