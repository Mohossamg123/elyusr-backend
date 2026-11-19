# Security notes

Open items found while preparing this repo (16 Nov 2026). Mark each one done in a PR.

1. `database/seeders/SuperAdminSeeder.php` falls back to a fixed password when `SUPER_ADMIN_PASSWORD` is missing. Make the seeder fail instead, and make sure the live Super Admin password is not that value.
2. The Super Admin email is `admin@alyusr.com`, a domain with no mailbox, so verification and reset codes sent to it bounce. Change it to a real address (`info@elyusr.org` or a person's).
3. Kashier is configured with the test endpoint. Switch to production keys and URL before taking real card payments.
4. `payment_reference` is not stored by `DonationController@store`. If the transaction number should be kept, add it (decision for the owner).
5. Keep `APP_DEBUG=false` and never log request bodies that contain passwords or card data.
6. Auth endpoints are rate limited; keep `email/verification-code` (3/min) and `email/verify` (10/min), plus the per-address 30 s resend wait in `EmailCode::cooldown`.

## Donation flow review (16 Nov 2026)
Checked `POST donations/submit` (public), `StoreDonationRequest` and `DonationController@store`:
- Good: `throttle:10,1` on submit and `30,1` on track; amount 1 to 1,000,000; receipt limited to jpg/png/webp/pdf and 5 MB; exactly one target (program, project, campaign or case) enforced; phone required for guests; client-sent `payment_details` and `payment_reference` are **deliberately dropped** in `store` (a guest must not be able to write payment facts; the gateways write them).
- Decision for the owner: for bank transfers, keep the donor's typed reference as a separate "claimed reference" field, shown in the dashboard as unverified.
- To do (Mohamed): receipts are stored on the `public` disk (`donations/receipts`). File names are random, but anyone holding a link can open a donor's transfer proof: move to a private disk and serve through an authenticated route (migrate old files too).
- To do (Mohamed): a short idempotency window so a double tap or retry cannot create two donations (same donor, amount and target within a minute returns the first one).
- Keep list endpoints paginated and eager-loaded (see WORKFLOW).

## Security audit pass (16 Nov 2026) — Cloudflare `security-audit` skill, manual quick pass
**Scope and honesty:** the skill is installed in the repo (`.agents/skills/security-audit`, git-ignored). The full workflow runs delegated agents with a sandbox and a budget; what was done here is a **partial, source-only pass by one reviewer** on the highest-risk surfaces. It is **not** a complete audit. Re-run the full workflow (`security audit this codebase`) from a session that can run sub-agents.

Checked, no finding:
- Payment webhooks: Kashier (`signature` / `x-kashier-signature`) and Paymob (`hmac`) are verified with `hash_hmac` + `hash_equals` before any donation is touched.
- SQL: every `DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw` is either constant or parameterised (`LOWER(email) = ?`).
- Social sign-in (Google, Facebook): provider token is validated server-side (app id checked for Facebook); staff accounts are refused on this path (`PUBLIC_ROLES`), so a social identity cannot take over an admin by e-mail match.
- Uploads: each controller validates type and size (`jpg/jpeg/png/webp/avif/mp4/webm`, receipts add `pdf`); no SVG upload endpoint.
- Auth rate limits: `login` limiter by IP and by e-mail, `register` 5/min.
- Front end: `dangerouslySetInnerHTML` appears only for static JSON-LD, chart CSS and one animation; blog HTML goes through the sanitiser in `src/data/blog.ts`.

Still open (needs the owner or Mohamed, nothing here is new):
1. Items 1–4 above (default seeder password, Super Admin mailbox, Kashier test URL, `payment_reference`).
2. Receipts on the `public` disk (see Donation flow review).
3. Idempotency window on donation submit.
4. Not checked in this pass: object-level authorisation on every `me/*` and `admin/*` route (route list has ~314 endpoints), mass assignment on every model (`$fillable` lists exist; no `$guarded = []` found), queue/mail header injection, dependency CVEs (`composer audit` could not run: no network from the build shell).
