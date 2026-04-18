"""RQ worker entrypoint: `python -m app.worker`"""

from __future__ import annotations

from redis import Redis
from rq import Worker

from app.settings import settings


def main() -> None:
    redis_conn = Redis.from_url(settings.redis_url)
    worker = Worker(["default"], connection=redis_conn)
    worker.work(with_scheduler=False)


if __name__ == "__main__":
    main()
