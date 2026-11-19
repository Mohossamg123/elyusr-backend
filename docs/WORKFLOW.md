# Working agreement (backend)

Two people change the API: the developer (Mohamed El-Dakrouri) and the project owner's assistant.
The goal: **one history, nothing changed behind anyone's back.**

## Day to day
1. `git pull` on `main`, then `git checkout -b feat/<short-name>`.
2. Make the change, run `php artisan test`, commit with a clear message (what and why).
3. Push the branch and open a pull request. The owner reviews and merges.
4. Mention in the PR: new/changed endpoints, new migrations, new `.env` names, anything the front end must change.

## Getting a change onto the server
- The server is **not** a place to edit. Changes reach it as a deploy bundle (`backend-handoff/deploy-*` in the front-end repo): file fingerprints checked, a backup taken, a rollback script included.
- Before a bundle is applied, the server file's fingerprint is compared with the version in `main`. If someone changed the file on the server, the bundle stops. Fix: copy the server file into a branch first, then deploy again.
- **Migrations:** the developer writes them; they are applied by the owner after the PR is merged, on a backup. Never edit or rename a migration that already ran.

## Rules
- No passwords, tokens or API keys in code, commits, PR text or chat. Names go in `docs/ENV.md`; values go in the server `.env`.
- Keep list endpoints paginated and eager-load relations (`with(...)`, `withCount(...)`) so a page is a few queries, not one per row.
- Authorization on every write endpoint (role/permission middleware), validation in a `FormRequest`.
- Writes that must survive a double click or a retry (donations, payments) must be idempotent.
- Document each change in `docs/CHANGELOG.md` (one line, date, PR number).
