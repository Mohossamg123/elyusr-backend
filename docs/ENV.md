# Environment variables (names only)

Values are on the server in `.env` (not in git). Copy `.env.example` for local work.

| Group | Names |
|---|---|
| App | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG` (false in production), `APP_URL` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |
| Payments (Kashier) | see `config/services.php` and `KashierController`; production needs the live URL, not `test-api.kashier.io` |
| Social login | see `SocialAuthController` (it reads `env()` directly, so keep config caching off or move these to `config/services.php`) |
| Seeding | `SUPER_ADMIN_PASSWORD` (required when seeding; there must be no default) |
