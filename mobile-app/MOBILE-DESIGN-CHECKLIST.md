# TNF Today — mobile design rollout

Mobile styles live in **`frontend-mobile.css`** (all phones + app). App-only chrome: **`frontend-mobile-app.css`**.

After each batch: `git push` → VPS `git pull` → test on phone (browser or app).

## Page checklist

| # | Page | URL pattern | Status |
|---|------|-------------|--------|
| 1 | Global / blocks | all | Done (batch 1) |
| 2 | Header & footer | all | Done (batch 1) |
| 3 | Home | `/` | Done (batch 1) |
| 4 | Category archive | `/category/...` | Done (batch 1) |
| 5 | ePaper archive | `/epaper/` | Done (batch 1) |
| 6 | Videos archive | `/videos/` | Done (batch 1) |
| 7 | News article | single post | Done (batch 1) |
| 8 | Video single | `/videos/...` or CPT | Done (batch 1) |
| 9 | ePaper edition | `/epaper/...` | Done (batch 1) |
| 10 | Login / register / forgot | auth pages | Done (batch 1) |
| 11 | My account | `/my-account/` | Done (batch 1) |
| 12 | Static pages | About, Contact, legal | Done (batch 1) |
| 13 | Search | `?s=` | Done (batch 1) |
| 14 | 404 | missing URLs | Done (batch 1) |
| 15 | App bottom nav | `?tnf_app=1` / APK | Done |

## Test on phone

1. Normal browser: `https://tnftoday.com/` (resize or real device).
2. App preview: `https://tnftoday.com/?tnf_app=1`
3. APK after Android Studio run.

## App-native experience (Capacitor)

| Item | File |
|------|------|
| App UI (home, article, login, My Account dashboard) | `frontend-app-experience.css` |
| Blocks wp-admin in app | `mobile-app.php` |
| Not WordPress admin — member **My Account** only | same |

## Next iterations

Reply with specific pages that still look wrong (screenshot or URL). We tune that section without an APK rebuild.
