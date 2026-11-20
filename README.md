# PaathNexus AI

**One Platform. Infinite Possibilities for Learning.**

PaathNexus AI is a multi-tenant education workspace with a public product/course discovery site and a React + TypeScript application backed by a Laravel 13 API. It includes academic rosters, attendance, timetables, teacher and student portals, courses, assignments, quizzes, guardian summaries, invitations, subscription plans, in-app announcements, fee invoices, AI tutor foundation, academic reports, and platform plan administration. The repository is an actively developed MVP; production integrations and release checks remain documented in `docs/KNOWN_ISSUES.md`.

## Run locally

Requirements: Node.js 20.19+ and npm. The current environment uses Node 20.19.1.

```sh
npm install
npm run dev
```

The local development server uses port `5174` by default so it can run alongside other projects on `5173`. The Laravel API uses port `8013`; Vite proxies `/api` requests there. Open `http://localhost:5174/` for the public homepage and workspace application.

Vite uses a strict port so it does not silently move the site to another address. If startup reports that `5174` is already in use, check whether the workspace site is already running at the URL above before starting a second instance.

Production bundle and type check:

```sh
npm test
npx playwright install chromium
npm run test:e2e
npm run build
```

The React component suite uses Vitest and React Testing Library. The Playwright suite checks the public site at 320, 375, 430, 768, 1024, and 1440 pixels and exercises mobile keyboard navigation and sign-in/registration controls. The app works with Node.js 20.19+; the current Playwright release requires Node.js 22 or newer. CI runs both test suites before the production build.

## Laravel API

Laravel 13 API source lives in `api/`. The local `.env` currently uses SQLite for quick development; `.env.example` targets PostgreSQL.

```sh
cd api
composer install
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8013
```

Configure `api/.env` for PostgreSQL before migrating. For SQLite local development, set `DB_CONNECTION=sqlite` and create the empty file with `php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"`.

To create an opt-in synthetic demo workspace locally, set `PAATHNEXUS_DEMO_SEED=true` and choose a private `PAATHNEXUS_DEMO_PASSWORD` in ignored `api/.env`, then run `php artisan db:seed` from `api/`. The same password applies to the four demo accounts: `owner@demo.paathnexus.test`, `teacher@demo.paathnexus.test`, `student@demo.paathnexus.test`, and `guardian@demo.paathnexus.test`. The owner can switch between the demo academy and independent educator workspace. Seeding is idempotent and refuses to run unless `APP_ENV=local`; do not reuse the password outside local development.

The API health check is `http://localhost:8013/api/v1/health`. Run backend feature tests with `php artisan test` from `api/`. Scheduled announcements publish through Laravel's every-minute scheduler; run `php artisan schedule:work` in another API terminal during local development.

The machine-readable API route contract is [`docs/openapi.json`](docs/openapi.json); field-level behavior and authorization requirements are described in [`docs/API_SPECIFICATION.md`](docs/API_SPECIFICATION.md).

Registration accepts a user and initial organization, and returns a Sanctum personal access token. The sign-in screen is connected to these endpoints. Tokens are kept in tab-scoped session storage and expire after 60 minutes by default.

## Current workflows
- Explore the public product page, live plan catalog, and explicitly public course catalog.
- Register a workspace, sign in, and switch between active organization memberships.
- Create classes and students, enroll students, and record daily attendance from the My classes/Attendance views.
- Search the active student roster and add student profiles from Students; enroll learners in classes from My classes.
- Create academic years, sections, subjects, and teacher profiles from Schedule; link profiles to teacher accounts, offer subjects by class/section, assign or change class teachers with history, and schedule offered subjects. Overview shows today's lessons for the active year.
- Create courses and ordered lessons from Courses, enroll roster students, link student accounts, and track started/completed progress with course completion summaries.
- Publish course assignments, accept enrolled learner text submissions, and grade submissions with points and feedback.
- Students can plan and complete private study sessions, optionally linked to courses in which they are enrolled.
- Build single-choice question banks, publish timed course quizzes with optional opening/closing windows, save learner answers, and automatically score submitted attempts.
- Link guardian memberships to student profiles; guardians can review attendance, learning progress, assessment results, upcoming exams for active enrolled courses, and download a scoped summary.
- Ask the configured AI tutor questions with optional authorized lesson context; configure `AI_API_KEY` and `AI_MODEL` before enabling live provider traffic.
- Teachers with course-management access can generate editable lesson plans, outlines, objectives, question sets, revision notes, and rubrics in English or Hindi. Drafts are not saved or published and must be reviewed; configure the API's AI provider before use.
- Publish announcements to workspace audiences and deliver them through the in-app notification inbox.
- Schedule announcements in the workspace timezone; managers can see upcoming items, and notifications are created when each item publishes. Managers can also withdraw or restore notices without losing delivery/read history.
- Search Help Centre answers, contact support by email, or send workspace feedback. Members can review their own status and manager replies and receive private in-app alerts when status or replies change; managers can respond and resolve feedback. Email and push notifications are not configured.
- Issue and void student fee invoices; students and linked guardians can view balances and receipts. Staff record reconciled offline payments, and a signed sandbox webhook can simulate a capture for development; no checkout or money transfer is performed.
- Configure subscription plans and workspace limits; set `PAATHNEXUS_PLATFORM_ADMIN_EMAILS` in the API environment to enable platform plan administration.
- Review workspace student, class, enrollment, pending task, and attendance summaries on Overview.
- Add and complete tasks; records are stored for the active organization and remain after reload.
- Review all workspace tasks from Tasks, filter open/completed work, and add tasks from that page.
- Sign in or create an account and organization through the Laravel API; switch between active organizations in the workspace picker.
- Review live academic reports filtered by class, learner, or date range, and export the current filtered report as CSV. The Overview “This week” action opens a Monday-to-today activity report.
- Invite teachers, students, and guardians with one-time links, then accept with the invited email account.
- Responsive sidebar works on compact viewports.
- Search workspace classes, learners, courses, and tasks from the top bar; results follow each member's workspace and academic access.

Class rosters, attendance, academic years, sections, subject and teacher catalogs, timetables, courses, lessons, learner progress, assignments, quiz attempts, invoices, plans, announcements, invitations, and memberships are persisted in the local API database. Roster, learning, attendance, reports, and finance access is scoped to the selected organization and checked against role capabilities. See the known-issues document for unimplemented areas and external setup requirements.

## Project notes
- [Architecture](docs/ARCHITECTURE.md)
- [Product requirements](docs/PRODUCT_REQUIREMENTS.md)
- [Database schema](docs/DATABASE_SCHEMA.md)
- [API specification](docs/API_SPECIFICATION.md)
- [Roadmap](docs/ROADMAP.md)
- [Implementation status](docs/IMPLEMENTATION_STATUS.md)
- [Security](docs/SECURITY.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Decision log](docs/DECISIONS.md)
- [Known issues](docs/KNOWN_ISSUES.md)

See `.env.example` and `api/.env.example` for development and deployment configuration keys. No production deployment is active yet.
