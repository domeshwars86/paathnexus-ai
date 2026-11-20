# Product requirements

## Audiences
Indian schools, colleges, coaching institutes, training academies, independent teachers, students, and parents/guardians.

## Product goals
- A user can belong to multiple organizations and switch workspace context.
- Institutions manage academic structures, teachers, students, attendance, learning, assessments, and reports.
- Teachers organize classes and courses, track learning, and communicate with guardians.
- Students and guardians have role-appropriate portals.
- AI tutoring and content tools are provider-configurable, quota-aware, and gracefully degradable.
- Commercial plans, fees, marketplace purchases, and commissions are configurable and use sandbox payment flows in development.
- Public courses may display manager-configured INR prices stored in integer paise. Paid-course access remains blocked until a verified checkout flow records payment; local sandbox activity must never move real money.
- Platform plan configuration is restricted to emails explicitly allowlisted in the API environment. Workspace owners can request upgrades; paid/custom requests remain pending until a platform admin records a verified reference.
- Plan entitlements can cap active student profiles, courses, teachers, and daily AI requests. Limits apply only when configured for the assigned plan.

## First milestone
Provide a responsive teacher dashboard with navigation, workspace counts, classes, timetable, tasks, and attendance interactions. Classes, students, enrollments, academic years, sections, weekly timetable entries, task records, per-student attendance, and dashboard summaries are persisted per organization.

## Product principles
- Protect student and guardian privacy.
- Enforce tenant isolation on every backend resource.
- Do not fabricate endorsements, institutions, outcome claims, or integration status.
- Keep mobile layouts usable on small Android screens and forms accessible by keyboard and screen reader.
- Start in English and maintain translations through a shared locale provider. Hindi is available on the public site and account-entry screens; authenticated workspace localization remains on the roadmap.
