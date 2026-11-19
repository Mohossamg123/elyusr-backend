# API contract with the front end

The front end (`src/data/laravelApi.ts`) lists every endpoint it calls. Keep both sides in step:
when a field is added or renamed here, update that file in the front-end repo in the same sprint.

Pending asks from the front end:
- `GET /public-settings` (public read of foundation settings; `settings` is admin-only now).
- Donation campaign packages: `key` column.
- Pagination on `GET /donation-campaigns` (the front end can send `page` and `per_page`).
