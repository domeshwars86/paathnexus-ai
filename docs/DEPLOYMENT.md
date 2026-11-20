# Deployment

Nothing is deployed yet. Deployment must be verified against an actual staging URL before described as live.

## Planned Render topology
- Static React frontend.
- Laravel 13 Docker web service.
- Managed PostgreSQL and Redis-compatible key-value service.
- Separate queue worker and scheduler when jobs are introduced.
- External object storage, AI, conferencing, and payment providers configured through secrets.

## Readiness checklist
1. Build and type-check frontend; run backend and tenant isolation tests.
2. Configure production environment with debug disabled and strong generated secrets.
3. Provision database, Redis and storage under approved account limits.
4. Run reviewed migrations with a rollback/backup plan.
5. Verify `/api/v1/health` liveness and `/api/v1/health/ready` database readiness, auth flows, tenant boundaries, logs, queue and scheduled jobs.
6. Run smoke checks on the deployed URL and document the verified commit and date.

`render.yaml`, `api/Dockerfile`, and `.github/workflows/ci.yml` provide a starter deployment/CI definition. CI runs Vitest, Chromium Playwright public-site responsive/auth checks, synthetic student lesson/quiz, guardian privacy, educator course/lesson flows, a production-preview offline app-shell check, the frontend build, and API migration/feature tests on SQLite and PostgreSQL 17; the hosted PostgreSQL job has not yet run on GitHub. The workflow and Render blueprint have not been exercised in their hosted environments; Docker is not installed locally. The blueprint uses free Render services for review only; free database persistence and service availability are not production guarantees. No deployment has been created or verified. Set the `APP_KEY` secret, allowlisted platform admin email, exact frontend origin, provider credentials as required, and review database backup/retention and paid plan needs before deploying real data. The free-plan blueprint does not automate migrations because Render's `preDeployCommand` requires a paid web-service plan. Before each deploy that includes pending migrations, take and verify a database backup, then run `php artisan migrate --force` through an approved deployment operation. If automatic pre-deploy migrations are required, select and approve a paid plan before changing the blueprint.

## Required environment values
- **API URL and signed links:** Set `APP_URL` to the public API origin and `FRONTEND_URL` to the exact HTTPS frontend origin. These are used by signed email verification and password-reset links.
- **Email:** Configure Laravel's `MAIL_MAILER` and provider transport settings (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_ENCRYPTION` as required), plus a verified `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`. Do not deploy with the local `log` mailer: it records message content and links in application logs instead of delivering them.
- **Email verification:** Set `PAATHNEXUS_REQUIRE_EMAIL_VERIFICATION=true` in staging and production. Verification defaults to off only when `APP_ENV=local` so local accounts are not blocked by the log mailer.
- **Laravel scheduler:** Run `php artisan schedule:work` as a supervised process or invoke `php artisan schedule:run` every minute. This publishes scheduled announcements and captures aggregate platform usage daily at 23:55 UTC; usage history starts with the first successful snapshot.
- **Secrets:** Store mail credentials and `APP_KEY` in the hosting provider's secret settings. Do not put production values in `.env.example`, source control, or build-time frontend variables.
- **Verification:** Send and receive a real verification email and password-reset email from the staging domain, verify link signatures and expiry, and confirm the frontend redirect before enabling real users.
