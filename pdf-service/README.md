# TNF PDF Service (FastAPI)

This folder is tracked in Git. **Copy your full local PDF project here** (same tree that serves `/docs` and `POST /pdf/process` on your machine), replacing this file if your project already has its own README.

**Server after `git pull`:** create a venv, `pip install -r requirements.txt`, bind to `127.0.0.1:8000`, run under systemd. Point nginx `location ^~ /pdf/` to `http://127.0.0.1:8000/pdf/`. WordPress uses **Settings → TNF PDF / ePaper** base URL `https://tnftoday.com`.

Do not commit `.venv/`, `__pycache__/`, or `.env` with secrets (use `.env.example` only).
