# PaathNexus AI contributor guide

## Product and current state
- Build PaathNexus AI as a secure, multi-tenant education platform for Indian institutions, educators, students, and guardians.
- The repository contains a React + TypeScript + Vite SPA and Laravel 13 API. Sign-in, account display-name updates, workspace onboarding, academic dashboards, attendance, timetable, learning, assessment, guardian, billing, and platform administration workflows are API-backed; confirm persistence and scope in the relevant controllers/tests before documenting any additional workflow.
- Keep implementation status and known limitations factual. Update `docs/IMPLEMENTATION_STATUS.md`, `docs/ROADMAP.md`, and `docs/KNOWN_ISSUES.md` after each meaningful milestone.

## Engineering conventions
- Use strict TypeScript, small components, semantic HTML, accessible labels, and keyboard-operable controls.
- Keep tenant checks in server-side policies and query scopes. Never trust client-supplied organization IDs or client-only permission checks.
- Use synthetic data only. Never add student personal data or secrets to fixtures, commits, logs, or documentation.
- Validate all inputs at the server boundary. Prefer explicit service/action classes for domain operations.
- Document API and schema changes. Add focused tests for behavior and especially tenant isolation.
- Keep dependencies stable and compatible with the supported Node and PHP runtimes. Do not commit generated secrets or local environment files.

## Local development
- Install JavaScript dependencies with `npm install`.
- Start the frontend using `npm run dev`.
- Build and type-check using `npm run build`.
- Backend work lives in `api/`; see `docs/ARCHITECTURE.md`, `api/.env.example`, and the project docs.

## Git
- Use focused `codex/` branches and descriptive commits when Git metadata is writable.
- Avoid destructive resets and force pushes.
