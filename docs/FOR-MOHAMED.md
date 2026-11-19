# For Mohamed — how we work on this repo

This repo is the **backend only** (Laravel 12). The website (React) lives in a different private repo and never contains PHP; this repo never contains frontend code.

1. Work on a branch, open a pull request into `main`; the owner reviews and merges.
2. The live server is deployed from `deploy-history`-style packages: a script that checks file fingerprints (refuses to overwrite a file changed on the server), makes a backup, and writes a `rollback.sh`. Do not edit files directly on the server; if you must, tell the owner so the repo gets the same change.
3. New migrations: add them here, run by path (`php artisan migrate --path=...`), never `migrate` blindly.
4. Secrets (`.env`, keys, passwords) never go in the repo or in chat. See `docs/ENV.md`.
5. Open security items for you are in `docs/SECURITY.md` (private disk for receipts, Kashier production URL, seeder default password, Super Admin email).
6. Read `docs/API-CONTRACT.md` before changing a response shape: the frontend depends on it.
