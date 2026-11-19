# Independent review of the 16f/16g fixes and the payment findings (17 Nov 2026)

Reviewer: a fresh agent that had not seen the earlier work, source-only (no code executed). Scope: diff `4c86f52..5009aa2` and frontend `3ef8411..380d3e2`.

## Our fixes (16f, 16g): verdict
No regression found. Each change closes what it targets (details in the commit history). Residual items, none blocking:

| # | Severity | Item | Status |
|---|---|---|---|
| 1 | Medium | The repo copy of `DonationController.php` still had the broken `show()` after 16g, so a later deploy from `app/` would bring the 500 back | **Fixed 17 Nov** (repo synced with the deployed file) |
| 2 | Medium | Login limiter is keyed by email only (5/min): anyone can lock a known user out by spamming that email. Fix: key by `email\|ip` (`AppServiceProvider.php:20`) | Open (our file; next bundle) |
| 3 | Low | `Viewer::isStaff()` is a deny-list: any new custom role counts as staff. Prefer an allow-list or permission checks | Open |
| 4 | Low | `ProfileController.php:101` falls back to the client extension (guarded by `mimes`) | Open |
| 5 | Low | `caseUpdate` title/excerpt not passed through `MailTemplates::plain()` (staff-authored) | Accepted |
| 6 | Cosmetic | `csvCell` prefixes `'` to cells starting with `+` (phone numbers) | Accepted (safe default) |

## Payment findings (Mohamed's code, NOT changed by us)
- **Kashier webhook forgery: CONFIRMED, critical.** The signature is verified (HMAC-SHA256, `hash_equals`) but the signed key list comes from the request and `status/amount/currency/merchantOrderId` are not required to be signed; a redirect signature the payer legitimately receives can be replayed with a crafted `signatureKeys`. Fix: ignore client `signatureKeys`, require a fixed signed set, reject keys containing `&` or `=`. (`KashierController.php:31, 597-724`)
- **Paymob: partially confirmed, high.** HMAC is verified, but `order.merchant_order_id` is not in the signed fields and the Paymob order/transaction id is never stored, so one real payment can be replayed against another pending donation of the same amount. References are `YSR-` + 8 random chars, so realistic abuse is double-counting one's own donations. Fix: store and require a unique Paymob transaction id; bind to the signed order id. (`PaymobController.php:39, 133, 378-443`)
- **Public routes leaking drafts/PII: confirmed, medium.** `GET /content`, `/content/{id}` (author `email`, drafts), `/stories`, `/gallery`, `/programs`, `/volunteer-campaigns` have no default published/public scope. Fix: default `published`/`is_public` scope for non-staff and drop `email` from the public resource. (`routes/api.php:139,199,216-224`)

Owner decision: **do not enable real card payments until Mohamed fixes and re-tests the two payment items.** The drafts-leak fix is small and can go in the next bundle after Mohamed reviews it.
