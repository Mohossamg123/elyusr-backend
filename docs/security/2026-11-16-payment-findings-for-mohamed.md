# Payment findings for Mohamed (16 Nov 2026) — URGENT, not yet fixed

Found by an independent source-only hunter (Cloudflare `security-audit` workflow, run-1), then re-read by the integrator.
**Status: unfixed. These files are Mohamed's; nothing was changed on the server.** Needs his review, then a deploy bundle with the usual fingerprint check.

## 1. Kashier webhook: attacker chooses what is signed  — CRITICAL (source-confirmed, not executed)
`KashierController::verifySignature()` (≈ line 596-720) reads `data.signatureKeys` **from the request body** and signs only those keys.
`merchantOrderId`, `status`, `amount`, `currency`, `transactionId` (what the handler then acts on) are not required to be among them,
and key names are concatenated unencoded (`$key.'='.rawurlencode($value)`), so a key containing `&` and `=` injects delimiters.
The redirect flow (`redirect()`, ≈ 446-500) hands the payer a signature made with the **same api_key** over `paymentStatus=…&…&currency=EGP`.
A donor can replay that signature as the webhook signature with `signatureKeys=["<redirect string without '=EGP'>"]`, `data[<that key>]="EGP"`, and forged `status=SUCCESS`, own `merchantOrderId`, own amount.
Result: a donation is marked paid with no payment (totals inflated, receipts/notifications fire, targets can be closed). `event=refund` is unsigned too.
**Smallest fix:** (a) ignore the client's `signatureKeys`; require a fixed set `merchantOrderId, status, amount, currency, transactionId` to be present and signed (Kashier's documented list/order); (b) reject key names containing `&` or `=`; (c) read `event`/`status`/`amount`/`merchantOrderId` only from the verified set; (d) defence in depth: query Kashier's order API before marking paid. Add a regression test for the injection payload.

## 2. Paymob callback binds the donation by an unsigned field — HIGH
`PaymobController::verifyHmac()` (≈ 402) signs Paymob's 20 fields, which do **not** include `order.merchant_order_id`; the handler finds the donation by that field and checks only `amount_cents`.
One real payment of X EGP can be replayed with another pending donation's reference (same X) → that donation becomes paid. Neither Paymob order id nor transaction id is stored or checked for reuse.
**Smallest fix:** store Paymob's order id on the donation at creation and require the signed `order.id` to match; unique index on (gateway, transaction id); optionally confirm with Paymob's API.

## 3. Donor identity bound to an unverified e-mail — MEDIUM (needs live role data)
`DeliverySteps::donorIdsOf()` and `NotifyDonor::accountFor()` match by e-mail only; `register` and `login` never require `email_verified_at`.
Registering with someone else's e-mail can expose that donor's notifications (and, if the `donor` role has `donations.view`, the donations list with phone/address).
**Fix:** only trust the e-mail when `users.email_verified_at` is set. (Our sign-up now sends a confirmation code, but nothing enforces it.)

## Hardening notes
- `DonationController::show` uses an undefined `$request` → always 500 (fails closed, but the donor check never runs).
- Paymob refund does not call `DonationTotals::removed`; the paid branch skips `donation_campaign_id` totals → totals can drift.
- A soft-deleted pending donation makes a late paid webhook 404 (money without a record).
- `base_url` is editable by `payment_methods.update` and receives the gateway secret → keep that permission super-admin only.
- Kashier base URL is still `test-api.kashier.io` (see SECURITY.md item 3). Reconcile `paid` donations against the gateway settlement report to look for past forgeries.
