# Al-Yusr API (backend)

Laravel 12 API for the Al-Yusr Charity site. Live at `https://api.elyusr.org` (Hostinger).
The front end is a separate repo: `unmoky2020-bit/al-yusr-charity` (React/Vite, `https://elyusr.org`).

- **Work on a branch, open a pull request, never edit files directly on the server.** See [docs/WORKFLOW.md](docs/WORKFLOW.md).
- Environment variable names only: [docs/ENV.md](docs/ENV.md). The real `.env` lives on the server and is never committed.
- What the front end expects from the API and what is still missing: [docs/API-CONTRACT.md](docs/API-CONTRACT.md).
- Security notes for the team: [docs/SECURITY.md](docs/SECURITY.md).

## Run locally
```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # local database only
php artisan serve            # http://127.0.0.1:8000
php artisan test
```

## Folder map
| Path | What |
|---|---|
| `app/Http/Controllers/Api/V1/*` | One folder per area (Auth, Donation, Program, Mail, UserManagement…) |
| `app/Http/Resources`, `app/Http/Requests` | JSON shapes and validation |
| `app/Support` | Helpers: `EmailCode` (verification codes), `MailOutbox`/`BrandMail`/`MailRenderer` (mail), `DonationTotals` |
| `routes/api.php`, `routes/console.php` | Endpoints and scheduled jobs |
| `database/migrations` | Schema history (never edit an old migration; add a new one) |

## Repo layout
`app/ routes/ database/ ...` Laravel code; `docs/` contract, env, security, workflow; `deploy-history/` every release package; `handoff-docs/` older hand-over notes; `legacy-scaffold/` retired Node/Mongo sample (not used).
