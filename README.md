# TNF News Platform

WordPress (CMS + public site + workflows) + FastAPI (PDF processing).

## Quick start

See **[docs/local-setup.md](docs/local-setup.md)**, **[docs/api-contracts.md](docs/api-contracts.md)**, **[docs/auth-implementation.md](docs/auth-implementation.md)**, **[docs/deployment.md](docs/deployment.md)**, **[docs/go-live-checklist.md](docs/go-live-checklist.md)**, **[docs/seo-setup.md](docs/seo-setup.md)**, and **[docs/performance-caching.md](docs/performance-caching.md)**.

```text
infra/          Docker Compose (optional; still gitignored)
wordpress/      Custom wp-content (themes + plugins)
pdf-service/    FastAPI + RQ worker — tracked in Git; copy your app here, then pull on server
docs/           Setup & API contracts (gitignored)
```

**PDF service in Git:** add your local `pdf-service` project under `pdf-service/` in this repo, commit, and `git pull` on the server. Then install Python deps there, run with systemd (e.g. `127.0.0.1:8000`), and add nginx `location ^~ /pdf/` as documented in ops / deployment notes.

## License

Proprietary / your project — adjust as needed.
