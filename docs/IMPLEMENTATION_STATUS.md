# Implementation status

Updated: 2026-11-21

## Milestone 0.282 — Localize learner account linking
- **Finding:** Workspace managers could link an existing student profile to an eligible learner account from the course workspace, but the linking form and its guidance were English-only.
- **Fix:** Added English/Hindi copy for the profile and account selectors, unlinked state, save/loading action, guidance, and generic fallback error. The request still sends the same selected profile and nullable account ID through the existing organization-scoped API. Added a Hindi browser regression that verifies the submitted account ID.
- **Verification:** Full Chromium suite passed (59 tests), including the learner account-linking and email-verification-hidden login journeys. Vitest passed (12 tests); TypeScript and production build passed.
- **Limitations:** API-provided errors remain in their original language. Broader remaining academic/workspace forms and error states need localization; hosted PostgreSQL and production integrations also remain outstanding.
- **Next:** Continue the shared locale through remaining high-use academic controls and common workspace error states, then proceed with ERP/LMS and release-readiness work.

## Milestone 0.281 — Localize the student roster
- **Finding:** The student directory exposed workspace profiles and allowed authorized educators to add learners, but its search, status, empty states, and creation form remained English-only.
- **Fix:** Added English/Hindi labels and states for the roster, active counts, search, student creation, and errors. Roster data remains scoped to the active organization, creation still requires `academic.manage`, and entered names/codes are preserved. Added a narrow-viewport Hindi browser journey covering search, creation, and read-only controls.
- **Verification:** Full Chromium suite passed (58 tests), including the new 390-pixel roster flow and the existing accessibility scans. Vitest passed (12 tests); TypeScript and production build passed.
- **Limitations:** Server-provided errors and most remaining workspace forms/content are not fully localized.
- **Next:** Localize the remaining academic account-linking and workspace error states while continuing ERP/LMS and release-readiness work.

## Milestone 0.280 — Localize question CSV import
- **Finding:** Assessment authoring, quiz attempts, and review were localized, but the question CSV import panel and its preview states remained English-only.
- **Fix:** Added English/Hindi copy for CSV instructions, template download, file selection, loading and importing states, row summary/status labels, type names, option counts, fallbacks, and the import action. CSV headers, payloads, preview-before-save behavior, and the existing 100-question/1 MB limits are unchanged. Added a Hindi browser journey covering preview and import.
- **Verification:** Full Chromium suite passed (57 tests), including assessment accessibility checks and local sign-in without email verification. Vitest passed (12 tests); TypeScript and production build passed.
- **Limitations:** Dynamic API-provided row validation messages remain in their original language. Other workspace text, most API validation messages, authored names, and provider/generated content are not fully localized.
- **Next:** Continue localization across remaining high-use workspace workflows while prioritizing ERP/LMS completion and release-readiness work.

## Milestone 0.279 — Localize learner quiz-taking and assessment status
- **Finding:** Learners could see translated quiz results, but active quiz instructions, actions, time limits, scheduling labels, and attempt statuses switched back to English.
- **Fix:** Added English/Hindi translations for quiz-taking prompts and actions, autosave guidance, time-limit/date formatting, assessment scheduling and availability, draft/published labels, and learner/educator attempt statuses. The learner quiz journey now switches to Hindi before starting the quiz and verifies the translated lifecycle and result status. Existing assessment permissions and API values are unchanged.
- **Verification:** Full Chromium suite passed (57 tests), including the learner lesson/quiz journey; Vitest passed (12 tests); TypeScript and production build passed.
- **Limitations:** Question import and most other workspace content, API validation messages, authored names, and provider/generated content remain untranslated.
- **Next:** Localize question import and remaining high-use workspace interactions, then continue ERP/LMS and release-readiness work.

## Milestone 0.278 — Localize assessment analytics and educator review
- **Finding:** Educators could filter question accuracy, export grades, inspect submitted answers, and grade written responses, but those analytics and review controls were English-only.
- **Fix:** Added English/Hindi translations for analytics filters, metrics, tables, attempt selection, correctness/pending states, learner answers, rubric scoring, feedback, CSV actions, and generic fallback errors. Existing server-side review permissions and grading data are unchanged.
- **Verification:** Full Chromium suite passed (57 tests); the assessment-review axe scan also passes for Hindi at desktop and mobile widths. Vitest passed (12 tests); TypeScript and production build passed. No backend code changed.
- **Limitations:** Active quiz-taking, status labels in assessment cards, and question-import UI remain partly English-only; API-provided validation messages are not translated.
- **Next:** Localize the learner quiz-taking and assessment status lifecycle, then continue ERP/LMS and release-readiness work.

## Milestone 0.277 — Localize learner assessment feedback
- **Finding:** Learners with results access could open submitted quiz answers and educator feedback, but the result panel remained English-only.
- **Fix:** Added English/Hindi copy for attempt status, scores, answer labels, no-answer states, pending review, teacher feedback, load/refresh actions, and the panel's accessible name. Results remain gated by the existing server-derived `results.view` permission.
- **Verification:** Full Chromium suite passed (57 tests), including a Hindi quiz attempt that opens and verifies its answer and feedback. Vitest passed (12 tests); TypeScript and production build passed. No backend code changed.
- **Limitations:** Educator analytics and attempt-review interfaces, the active quiz-taking flow, and question-import copy still contain untranslated text.
- **Next:** Localize assessment analytics and educator review, then address remaining workspace error states and ERP/LMS release-readiness work.

## Milestone 0.276 — Localize quiz and question authoring
- **Finding:** Assessment managers could create questions and publish timed quizzes, but the question bank, scheduling, randomization, and short-answer rubric controls remained English-only.
- **Fix:** Added English/Hindi translations for question types, difficulty, answer options, rubric criteria and weights, question-bank filters, quiz scheduling, randomization, sampling guidance, and validation/fallback errors. Correct-answer privacy and assessment API data are unchanged.
- **Verification:** Full Chromium suite passed (57 tests), including Hindi assessment-only authoring coverage; Vitest passed (12 tests); TypeScript and production build passed. No backend code changed.
- **Limitations:** Assessment attempt review and result pages still contain untranslated content. Imported question workflows and some provider/API messages also remain English-only.
- **Next:** Localize assessment review/results and the remaining workspace error states, then continue ERP/LMS and release-readiness work.

## Milestone 0.275 — Localize assignment authoring
- **Finding:** Educators creating course assignments saw English-only fields and file guidance even when the workspace used Hindi.
- **Fix:** Localized assignment title/instructions, points, workspace-timezone due-date guidance, resource-file limits, selected-file count, publish state, and invalid-time/create fallback errors. Assignment data and upload limits are unchanged.
- **Verification:** Full Chromium suite passed (57 tests), including the Hindi assignment form; Vitest passed (12 tests); TypeScript and production build passed. No backend code changed.
- **Limitations:** Assessment authoring and assignment review/submission messaging still have English-only content.
- **Next:** Localize assessment authoring and review forms, then continue the ERP/LMS and release-readiness backlog.

## Milestone 0.274 — Localize instructor assignment and enrollment
- **Finding:** Course instructors and learner enrollment were manageable from the course library but their controls and empty-state count remained English-only.
- **Fix:** Added English/Hindi copy for instructor assignments, multi-select guidance, enrollment fields and actions, live enrollment counts, and generic fallback errors. Server authorization and request data are unchanged.
- **Verification:** Full Chromium suite passed (57 tests), including Hindi instructor assignment and enrollment form checks; Vitest passed (12 tests); TypeScript and production build passed. No backend code changed.
- **Limitations:** Assignment and assessment authoring forms and much of the remaining workspace content are not fully localized.
- **Next:** Localize assignment and assessment authoring forms, then continue the remaining ERP/LMS and release-readiness backlog.

## Milestone 0.273 — Localize course and lesson authoring
- **Finding:** Course editing and lesson creation/editing still rendered English-only labels, visibility options, helper text, and fallback errors in Hindi workspaces.
- **Fix:** Added English/Hindi translations for course edit visibility and catalog settings, lesson creation and editing fields, resource types, prerequisite and availability controls, save/cancel states, and fallback errors. API payloads and authorization are unchanged.
- **Verification:** Full Chromium suite passed (57 tests), including Hindi course and lesson authoring assertions; Vitest passed (12 tests); TypeScript and production build passed. The focused browser journey passed independently. No backend code changed.
- **Limitations:** Course instructor assignment, learner enrollment, assignment authoring, and assessment authoring still have English-only content. Other authored content and API validation messages also remain untranslated.
- **Next:** Localize course instructor and enrollment forms, then assignment and assessment authoring; continue ERP/LMS and release-readiness work.

## Milestone 0.272 — Localize course creation
- **Finding:** The course library supported English and Hindi, but the create-course form and its API fallback error stayed in English.
- **Fix:** Localized the course creation heading, labels, description, price hint, submit/saving states, and fallback error in English and Hindi. Course values and the API payload are unchanged.
- **Verification:** Full Chromium suite passed (57 tests), including a Hindi course creation flow that checks the title, code, description, and INR-to-paise conversion. Vitest passed (12 tests), and the TypeScript/production build passed. The focused Laravel email-verification suite passed (3 tests, 12 assertions); local configuration reports verification disabled.
- **Limitations:** Other course editing, lesson, assignment, and assessment authoring forms still need localization. The local demo database contains synthetic accounts; the user's earlier account is not present.
- **Next:** Continue English/Hindi localization across remaining course and assessment authoring forms, then continue ERP/LMS and release-readiness work.

## Milestone 0.271 — Localize guardian-link management
- **Finding:** Managers could link active guardian accounts to student summaries, but that form and its active-link controls remained English-only while the rest of the family portal supported English and Hindi.
- **Fix:** Added shared English/Hindi translations for the guardian-link form, relationship choices, eligibility hint, loading/empty states, end-access action, and generic fallback errors. Existing permission checks and API payloads are unchanged.
- **Verification:** Full Chromium suite passed (56 tests), including a new Hindi browser workflow for populated guardian/student selectors, relationship selection, form labels, and empty state. Vitest passed (12 tests); TypeScript and production build passed. No backend code changed; the complete Laravel suite immediately before this UI milestone passed (130 tests, 2,786 assertions).
- **Limitations:** Server validation messages and most other workspace content are still not localized. Authored names and relationship data continue to display as entered.
- **Next:** Translate the remaining academic/course authoring forms and common workspace error states; then continue the ERP/LMS and release-readiness backlog.

## Milestone 0.270 — Add shareable public course pages
- **Finding:** Course discovery exposed summaries and a quick-view dialog, but visitors could not share a direct course URL. Instructor summaries on course listings could also retain profiles after the account or teacher membership became inactive.
- **Fix:** Added a public course detail endpoint and responsive `/courses/{id}` page linked from catalog cards. The endpoint rechecks active/public course and workspace state plus moderation; it returns price, active lesson count, and only public instructor fields, never lesson content or learner data. Catalog instructor summaries now require an active linked user and teacher membership. Detail pages disclose when paid checkout is unavailable and direct learners through the existing sign-in/registration path.
- **Verification:** Full Laravel suite passed (130 tests, 2,786 assertions); full Chromium suite passed (55 tests); Vitest passed (12 tests); TypeScript and production build passed. Focused public catalog/OpenAPI tests passed (6 tests, 899 assertions); focused course/educator page browser journeys passed (2 tests), including responsive overflow at 320–1440px and hidden-content cases.
- **Limitations:** Course pages do not expose lesson previews. Paid checkout UI/provider integration remains unavailable; enrollment and payment capture still use the existing authenticated and sandbox safeguards. Educator pages have no contact channel or course list.
- **Next:** Extend English/Hindi localization across authenticated workspace modules, then continue the ERP/LMS backlog and release-readiness checks; production payment, mail, AI, and conferencing require external provider configuration.

## Milestone 0.269 — Add shareable public educator profiles
- **Finding:** The public educator directory showed published profiles but offered no direct page that educators could share.
- **Fix:** Added an ID-addressable public educator endpoint and responsive `/educators/{id}` pages linked from directory cards. Detail responses reapply the active account, membership, workspace, opt-in, and moderation filters; they return only published profile and workspace fields, never contact details, employee codes, or account identifiers. Added English/Hindi copy and OpenAPI coverage.
- **Verification:** Full Laravel suite passed (130 tests, 2,773 assertions); full Chromium suite passed (54 tests); Vitest passed (12 tests); TypeScript and production build passed. Focused educator/OpenAPI tests passed (6 tests, 888 assertions), and the browser journey verified direct loading, return navigation, privacy copy, and responsive overflow across 320–1440px.
- **Limitations:** Profiles provide no contact channel or course list. Profile visibility follows the educator's current public opt-in and platform moderation state; otherwise the detail route returns 404.
- **Next:** Add shareable public course detail pages with the existing opt-in, moderation, pricing, and enrollment rules; then continue release-readiness and remaining ERP/LMS backlog.

## Milestone 0.268 — Platform-assisted account recovery
- **Finding:** Platform support could inspect account/workspace status but could not help an active user who had lost access.
- **Fix:** Added a rate-limited, allowlist-only action on an exact-looked-up active account that requests a password-reset email through Laravel's existing broker. The link goes only to the account's registered email; the admin UI never receives the password or reset token. A metadata-only platform audit event records the request. Added English/Hindi UI copy and OpenAPI documentation.
- **Verification:** Full Laravel suite passed (129 tests, 2,762 assertions); full Chromium suite passed (53 tests); Vitest passed (12 tests); TypeScript and production build passed. Focused recovery and OpenAPI tests passed.
- **Limitations:** Link delivery depends on the configured mail transport. Local development uses the log mailer and does not deliver links to an inbox. Reset-link consumption remains the user's existing email workflow.
- **Next:** Continue the remaining ERP, LMS and release-readiness backlog; hosted PostgreSQL verification and production mail/payment/AI integrations need external configuration.

## Milestone 0.267 — Moderate public course and educator listings
- **Finding:** Publicly opted-in listings had no platform-level moderation path, and hiding them by changing tenant-owned fields would erase institution intent.
- **Fix:** Added an allowlist-only platform review queue for active public courses and educator profiles, reversible hide/restore actions with optional notes, and platform audit events. Moderation state lives in a separate table; public discovery, educator summaries, free enrollment, paid order creation, and sandbox payment capture all enforce hidden state. Workspace-owned course/profile fields and existing learning records remain intact. Added English/Hindi admin UI and OpenAPI documentation.
- **Verification:** Full Laravel suite passed (127 tests, 2,749 assertions); full Chromium suite passed (53 tests); Vitest passed (12 tests); TypeScript and production build passed. PHP syntax checks and the OpenAPI export check passed.
- **Limitations:** Moderation only covers current, active public opt-in listings. It does not suspend a workspace or revoke existing enrollments/orders. Platform administration remains allowlist-configured; PostgreSQL lock behavior still requires hosted CI verification.
- **Next:** Add platform-assisted account recovery, then continue the remaining ERP, LMS and release-readiness backlog.

## Milestone 0.266 — Add audited platform account lookup
- **Finding:** Platform admins could manage organizations and subscriptions but had no account lookup for support investigations.
- **Fix:** Added an allowlist-only, rate-limited exact-email lookup with a minimal account and workspace-membership summary. Successful lookups create a metadata-only audit entry; the API omits learner/teacher profiles, credentials, and tokens. Added the lookup panel, English/Hindi copy, and OpenAPI request/response schemas.
- **Verification:** Focused platform lookup and OpenAPI tests passed (3 tests, 850 assertions); full Laravel suite passed (124 tests, 2,705 assertions); full Chromium suite passed (53 tests); Vitest passed (12 tests); TypeScript and production build passed. The platform admin browser workflow submits an exact email and displays only the returned summary.
- **Limitations:** This milestone was read-only lookup. Account recovery/support actions and content moderation were not implemented at that point. Platform admin access remains allowlist-configured.
- **Next:** Implement reversible platform moderation for opted-in public listings while preserving tenant-owned records.

## Milestone 0.265 — Cancel one dated timetable lesson
- **Finding:** Managers could cancel a whole class or section day, but the UI did not expose the API's support for canceling one weekly lesson on one date.
- **Fix:** The dated cancellation form now offers only lessons matching the selected active academic year, class, section, and weekday. Selecting a lesson sends its timetable entry ID; the recurring timetable remains intact. The extra-lesson conflict check now distinguishes a single-lesson cancellation from a whole-day cancellation.
- **Verification:** The full Laravel suite passed (122 tests, 2,684 assertions); the full Chromium suite passed (53 tests); Vitest passed (12 tests); and TypeScript/Vite production build passed. The ScheduleException regression proves the selected occurrence is omitted while another remains, and the Hindi browser workflow verifies the matching lesson selector.
- **Limitations:** The control cancels one lesson on a date; it does not edit recurring timetable entries or create cancellations across a date range.
- **Next:** Continue the remaining ERP, communication, AI, and release-readiness backlog; hosted PostgreSQL and production integrations need external configuration.

## Milestone 0.264 — Edit active dated schedule change details
- **Finding:** Workspace managers could create and cancel closures, class cancellations, and extra lessons, but could not correct a title or note after creating one.
- **Fix:** Added a tenant-scoped, row-locked update endpoint for title and notes on active exceptions. The schedule UI lets managers edit those display details; dates and schedule scope remain fixed after creation. Updated the OpenAPI schema and Hindi workflow.
- **Verification:** Schedule exception and OpenAPI tests passed; full Laravel suite passed (122 tests, 2,679 assertions). Full Chromium suite passed (53 tests); Vitest passed (12 tests); TypeScript and Vite production build passed. Browser workflow verifies the Hindi edit form and saved result.
- **Limitations:** Dates, exception types, class/section scope, and lesson assignments cannot be edited; canceled items remain immutable. PostgreSQL row-lock concurrency and hosted CI checks remain pending.
- **Next:** Add a cancel action for a single dated occurrence of a weekly timetable lesson, with assignment and tenant checks.

## Milestone 0.263 — Add a privacy-scoped public educator directory
- **Finding:** The `/educators` page explained the platform but did not let visitors discover instructors who had opted into public display.
- **Fix:** Added a searchable, paginated public educator API and directory cards on the `/educators` route. The API includes only active opted-in profiles for active teacher memberships in active workspaces and returns public profile fields without emails, user IDs, or employee codes. Added the API contract and generated OpenAPI update.
- **Verification:** Public catalog and OpenAPI API tests passed; full Laravel suite passed (122 tests, 2,665 assertions). Full Chromium suite passed (53 tests); Vitest passed (12 tests); TypeScript and Vite production build passed. The educator route was checked with synthetic data across 320–1440px widths.
- **Limitations:** Directory listings have no individual profile details page or contact workflow. Directory cards render the content that educators have published.
- **Next:** Continue the remaining ERP and production-integration backlog, prioritizing operational gaps that can be completed and verified locally.

## Milestone 0.262 — Add resumable new-workspace onboarding
- **Finding:** Newly registered owners landed directly on Overview without guidance for workspace settings, an academic year, classes, or inviting colleagues.
- **Fix:** Owner Overview now shows a guided checklist wired to the existing settings, schedule, classes, and invitation workflows. Academic-year, class, and invitation steps reflect workspace API data; reviewed and paused state persists in browser storage and can be resumed.
- **Verification:** Onboarding browser tests passed (3 tests), including reload/pause/resume, data-derived progress, 375px layout, and desktop/mobile axe scans. Full Chromium suite passed (53 tests); Vitest passed (12 tests); production build and TypeScript checks passed.
- **Limitations:** Checklist review and pause state are stored per browser, not synchronized across devices. Completion of academic year, class, and invitation steps is derived from current API records.
- **Next:** Add a privacy-scoped public educator directory, then continue the remaining ERP and production-integration backlog.

## Milestone 0.261 — Add shareable public product pages
- **Finding:** Platform features, educator tools, public courses, and pricing were only homepage anchor sections, so visitors could not link directly to those product pages.
- **Fix:** Added client-side `/features`, `/educators`, `/courses`, and `/pricing` routes using the existing responsive sections and live public catalog data, with browser-history navigation, active-page semantics, and route-specific document titles.
- **Verification:** Focused Chromium suite passed (3 tests) for direct routes, mobile navigation/focus, and homepage layout across 320–1440px; `npm run build` passed.
- **Limitations:** Route pages share the existing public marketing sections; they are client-rendered SPA routes.
- **Next:** Implement resumable organization onboarding for newly registered workspace owners.

## Milestone 0.260 — Serialize assessment answers with submission
- **Finding:** Answer saves checked attempt status before writing without the attempt lock, so an autosave could race with submission and mutate an answer after its score was calculated. Duplicate submissions also re-used a stale in-memory state after waiting for the same lock.
- **Fix:** Answer saves now lock and re-read the tenant-, assessment-, and learner-scoped attempt before rechecking status/expiry and upserting the answer. Submission rechecks status after acquiring that same attempt lock.
- **Verification:** Organization API workflow passed (16 tests, 476 assertions); full Laravel suite passed (121 tests, 2,649 assertions); PHP lint and `git diff --check` passed. SQLite does not exercise row-lock concurrency; PostgreSQL CI remains necessary.
- **Next:** Add dedicated public feature/course/pricing routes, then design the missing resumable first-workspace onboarding flow.

## Milestone 0.259 — Serialize support-feedback updates
- **Finding:** Feedback response and status changes were compared against a model loaded before the transaction, so overlapping manager updates could overwrite newer response data or miss a notification transition.
- **Fix:** The controller now locks the workspace-scoped feedback row, derives omitted fields and change flags from the locked row, saves the response/status, and updates the submitter notification in the same transaction.
- **Verification:** Focused feedback workflow passed (3 tests, 62 assertions); full Laravel suite passed (121 tests, 2,646 assertions). SQLite does not exercise row-lock concurrency; PostgreSQL CI remains necessary.
- **Next:** Continue API-scope and release-readiness audits.

## Milestone 0.258 — Serialize task lifecycle changes
- **Finding:** Task edit, completion, archive, and restore checked state before saving without a row lock, so overlapping requests could violate the archived-task rules.
- **Fix:** All four tenant-scoped lifecycle operations now lock and re-read the task in a transaction before checking state or saving.
- **Verification:** Focused task workflow passed (1 test, 31 assertions); full Laravel suite passed (121 tests, 2,646 assertions). SQLite does not exercise row-lock concurrency; PostgreSQL CI remains necessary.
- **Next:** Serialize concurrent support-feedback updates, then continue API-scope and release-readiness audits.

## Milestone 0.257 — Enforce teacher profile limits atomically
- **Finding:** Teacher profile count and `max_teachers` entitlement were checked before insert without workspace serialization, allowing concurrent requests to exceed the plan cap.
- **Fix:** Teacher profile creation now locks the active workspace, recounts active profiles, checks the current entitlement, and inserts inside one transaction.
- **Verification:** Focused entitlement test passed (1 test, 15 assertions); full Laravel suite passed (121 tests, 2,646 assertions); PHP lint and `git diff --check` passed. PostgreSQL concurrency remains a CI verification item.
- **Next:** Continue API-scope and release-readiness audits.

## Milestone 0.256 — Enforce course limits atomically
- **Finding:** Course count and `max_courses` entitlement were checked before course creation without workspace serialization, allowing concurrent requests to exceed the plan cap.
- **Fix:** Course creation now locks the active workspace, recounts active/draft courses, checks the current entitlement, revalidates optional academic-year/subject scope, and inserts in one transaction.
- **Verification:** Focused plan-entitlement test passed (1 test, 11 assertions); full Laravel suite passed (121 tests, 2,642 assertions); PHP lint and `git diff --check` passed. SQLite does not exercise row-lock concurrency; PostgreSQL CI remains necessary.
- **Next:** Continue API-scope and release-readiness audits.

## Milestone 0.255 — Enforce student limits under a workspace lock
- **Finding:** Student count and plan entitlement were checked before insert without serialization, so concurrent requests could both pass a `max_students` cap and exceed it.
- **Fix:** Student creation now locks the active workspace, recounts active students, and checks the current plan limit inside the transaction before creating the profile.
- **Verification:** Focused plan-entitlement test passed (1 test, 11 assertions); full Laravel suite passed (121 tests, 2,642 assertions); PHP lint and `git diff --check` passed. PostgreSQL concurrency is not exercised by SQLite and remains a CI verification item.
- **Next:** Continue API-scope and release-readiness audits.

## Milestone 0.254 — Serialize active academic year and timetable writes
- **Finding:** Concurrent active-year creation could leave multiple active years. Timetable overlap checks ran outside a transaction, allowing concurrent writes to reserve the same class or teacher time slot.
- **Fix:** Active-year creation now locks the organization like year activation. Timetable create/update now locks the organization and re-runs all tenant, offering, section, and overlap checks inside the transaction.
- **Verification:** Focused academic scheduling workflow passed (1 test, 342 assertions); full Laravel suite passed (121 tests, 2,642 assertions); PHP lint and `git diff --check` passed. SQLite does not exercise row-lock concurrency; PostgreSQL CI remains necessary.
- **Next:** Continue remaining API-scope and release-readiness audits.

## Milestone 0.253 — Serialize custom-role assignment and archival
- **Finding:** Role assignment checked `archived_at` outside a transaction, while archival updated the same role independently, allowing a concurrent assignment to succeed after archival.
- **Fix:** Both assignment and archival now lock the organization-scoped role row before checking or changing its state. Existing assigned members remain on archived profiles.
- **Verification:** Focused role archival/assignment test passed (1 test, 14 assertions); full Laravel suite passed (121 tests, 2,642 assertions); PHP lint and `git diff --check` passed. PostgreSQL concurrency remains a CI verification item.
- **Next:** Continue API-scope and release-readiness audits.

## Milestone 0.252 — Serialize live-session state and first access
- **Finding:** Concurrent status transitions could validate against stale session state. Concurrent first-time joins could both observe no access row and collide on its unique key.
- **Fix:** Session updates now lock and revalidate the tenant-scoped session before checking transition rules. Join requests take the same session lock, reauthorize and recheck the time window, then update/create the access record.
- **Verification:** Focused `LiveSessionTest` passed (2 tests, 46 assertions); full Laravel suite passed (121 tests, 2,642 assertions). PHP lint and diff check pending. SQLite does not exercise row-lock concurrency; PostgreSQL CI remains necessary.
- **Next:** Continue API-scope and release-readiness audits.

## Milestone 0.251 — Serialize guardian-student link lifecycle
- **Finding:** Concurrent guardian-link requests could race between lookup and insert, violating the unique workspace/student/guardian key and returning a server error. Link and end requests could also overlap without a shared lifecycle lock.
- **Fix:** Create/relink and end now lock the active workspace first, then revalidate members and lock the scoped link row. Repeated requests update the existing link and preserve its ID.
- **Verification:** Focused guardian-link workflow passed (1 test, 342 assertions); full Laravel suite passed (121 tests, 2,642 assertions); PHP lint and `git diff --check` passed. PostgreSQL concurrency remains a CI verification item.
- **Next:** Continue the remaining API-scope and release-readiness audits.

## Milestone 0.250 — Align invitation registration lock order
- **Finding:** Invitation acceptance and creation lock the workspace before invitation state, but registering a new account from an invitation locked only the invitation before querying its workspace.
- **Fix:** New-account invitation registration now locks the active workspace first, then the invitation, and rechecks whether the email already has an account inside the transaction. All three invite lifecycle paths now use workspace-first locking.
- **Verification:** Focused `OrganizationInvitationTest` passed (2 tests, 25 assertions); full Laravel suite passed (121 tests, 2,639 assertions). PostgreSQL row-lock concurrency still requires CI verification.
- **Next:** Continue remaining API-scope and release-readiness audits.

## Milestone 0.249 — Serialize workspace invitations and protect active member roles
- **Finding:** Concurrent invitation requests could create multiple active tokens for one email in a workspace. Accepting a later outstanding token could overwrite the role of a person who had already become an active member.
- **Fix:** Invitation creation and acceptance now lock the active organization first, then recheck invitation and membership state inside transactions. Acceptance rejects a token when the target is already an active member. Added regressions for duplicate pending creation and stale-token role changes.
- **Verification:** Focused `OrganizationInvitationTest` passed (2 tests, 25 assertions); full Laravel suite passed (121 tests, 2,639 assertions); PHP syntax checks and `git diff --check` passed. PostgreSQL lock concurrency remains a CI verification item.
- **Next:** Run the full API suite, then continue tenant-scope and release-readiness audits.

## Milestone 0.248 — Serialize invoice voiding with payment capture
- **Finding:** Invoice voiding checked for payments and updated status without locking, allowing it to race with manual or signed-webhook capture and produce a void invoice with a recorded payment.
- **Fix:** Invoice voiding now locks the active organization and then its tenant-scoped invoice inside a transaction, matching the lock order used by both payment paths. The payment regression asserts a paid invoice and its receipts remain intact when a subsequent void is rejected.
- **Verification:** `php artisan test --filter=FeeInvoiceTest` passed (6 tests, 97 assertions); full Laravel suite passed (121 tests, 2,636 assertions); PHP syntax checks for the controller and test passed; `git diff --check` passed. Local tests use SQLite, which does not exercise PostgreSQL row-lock concurrency; hosted PostgreSQL verification remains necessary.
- **Next:** Continue the remaining API scope and release-readiness audit; verify the locking behavior on the hosted PostgreSQL CI service when available.

## Milestone 0.247 — Scan platform-admin usage and fix contrast
- **Finding:** A new platform-admin axe scan found below-target contrast in the organization-search label and empty plan/audit states.
- **Fix:** Added a platform-admin usage/history fixture with desktop/mobile scans. Darkened platform search labels, plan-card helper text, and shared empty-state text for readable contrast across workspace surfaces.
- **Verification:** `npx playwright test tests/e2e/accessibility.spec.ts` passed (11 scenarios, 26 desktop/mobile surface scans); full `npx playwright test` passed (49 Chromium tests); `npm run build` passed; `npm test -- --run` passed (4 files, 12 tests); `git diff --check` passed.
- **Limitations:** Automated coverage remains representative rather than exhaustive. Other role-specific modules, dialogs, keyboard-only workflows, screen-reader usage, cross-browser behavior, and physical-device behavior still need review.
- **Next:** Extend automated scans to remaining teacher/admin workflows and dialogs, then continue API authorization and release-readiness checks.

## Milestone 0.246 — Scan manager announcements and AI learning for accessibility
- **Finding:** The manager announcement lifecycle and educator AI learning forms had functional browser coverage, but the accessibility suite did not scan their forms, controls, and generated content states.
- **Fix:** Added synthetic owner and educator fixtures with desktop/mobile axe scans for announcements and AI tutor/teaching-draft forms and results.
- **Verification:** `npx playwright test tests/e2e/accessibility.spec.ts` passed (10 scenarios, 24 desktop/mobile surface scans); full `npx playwright test` passed (48 Chromium tests); `git diff --check` passed.
- **Limitations:** Automated scans still cover representative workflows rather than every role, dialog, and interaction state. Manual screen-reader, cross-browser, and physical-device checks remain pending.
- **Next:** Extend axe coverage to the remaining teacher/admin workflows and dialogs, then continue API authorization and release-readiness checks.

## Milestone 0.245 — Revoke learner access when courses become drafts
- **Finding:** A learner with a retained enrollment could start a published assessment by its known ID and receive a course live-session URL after a manager changed that course to draft. Learner-facing lists already hid the draft course, but the direct assessment and session paths did not recheck course status.
- **Fix:** Require an active course before creating a new assessment attempt, while allowing an already-started in-progress attempt to resume so learners can finish work they began. Restrict learner live-session listings to active course enrollments and reject direct meeting access for draft courses. Preserve each learner's own submitted assessment results as historical records. Added regressions covering the supported active-to-draft transition, retained result access, preserved in-progress work, absent new attempt/access records, and the disappearing session.
- **Verification:** Focused assessment and live-session tests passed (5 tests, 64 assertions); full Laravel suite passed (121 tests, 2,634 assertions); PHP syntax and diff checks passed.
- **Limitations:** Retained enrollment and assessment history remain unchanged; only new attempts and learner session access are revoked, while an already-started in-progress attempt can be resumed/submitted and each learner can read their own historical result. Hosted PostgreSQL and deployment verification remain pending.
- **Next:** Continue API tenant-scope and release-readiness audits, then verify hosted PostgreSQL and release gates.

## Milestone 0.244 — Enforce teacher course scope for AI tutor context
- **Finding:** A teacher with `courses.manage` could attach another teacher’s private course and lesson to an AI tutor request. The course-manager permission bypassed enrollment checks, and the tutor did not apply the existing created-or-assigned teacher course scope before forwarding lesson content.
- **Fix:** Resolve tutor course context through `TeacherCourseScope`, retaining manager and learner access while restricting teachers to courses they created or were assigned. Added a regression proving an out-of-scope request returns 404 before provider traffic or usage metering.
- **Verification:** Focused AI tutor tests passed (4 tests, 54 assertions); full Laravel suite passed (118 tests, 2,613 assertions); PHP syntax checks and `git diff --check` passed.
- **Limitations:** This closes lesson-context access for tutor requests; provider retention review and live-provider adversarial evaluation remain pending. Hosted PostgreSQL and deployment verification also remain pending.
- **Next:** Continue route-by-route authorization and tenant-scope review, then verify hosted PostgreSQL and release gates.

## Milestone 0.243 — Re-check announcement audiences after role changes
- **Finding:** A member who changed from teacher to student could still read a previously delivered teachers-only announcement from the notification feed, and the hidden item counted as unread.
- **Fix:** Re-evaluate the member’s current role when listing announcement notifications and counting unread items. The mark-read endpoint now also returns 404 for notifications outside the member’s current audience. Added a regression covering role downgrade, hidden feed item, unread total, mark-read denial, and visibility for a current teacher.
- **Verification:** Focused announcement tests passed (4 tests, 99 assertions); full Laravel suite passed (117 tests, 2,609 assertions).
- **Limitations:** Hosted PostgreSQL CI remains pending; the role-change regression uses a persisted membership role update.
- **Next:** Continue route-by-route tenant and role authorization review, then verify hosted PostgreSQL and release gates.

## Milestone 0.242 — Scope assignment grading and test migration recovery
- **Finding:** Assignment-submission lists filtered teachers to assigned learners, but the direct grading endpoint did not, allowing a teacher with course-management access to grade another section’s submission. CI exercised migrations through feature tests but did not explicitly run rollback and re-application.
- **Fix:** Apply the assigned-student scope to course-assignment grading and return 404 for out-of-scope submissions. Added a regression proving an assigned learner can be graded while an unassigned learner’s grade and feedback remain unchanged. Added SQLite/PostgreSQL migration fresh → rollback → re-apply steps to CI. Adjusted named-foreign-key rollback paths for SQLite while retaining PostgreSQL constraint names.
- **Verification:** Focused scope test passed (1 test, 10 assertions); full Laravel suite passed (116 tests, 2,588 assertions); local disposable SQLite fresh → rollback → migrate passed. PostgreSQL migration smoke is configured in CI but not run in this local environment.
- **Limitations:** Hosted PostgreSQL CI and deployment remain unverified. Additional API scope combinations and production integrations still need review.
- **Next:** Continue route-by-route tenant and role authorization review; verify PostgreSQL and deployment gates in hosted CI when available.

## Milestone 0.241 — Scan guardian and assessment workflows
- **Finding:** Axe coverage did not yet include guardian learning/billing or assessment authoring/review. The assessment scans found muted labels below WCAG contrast targets and a horizontally scrollable analytics table that was not keyboard-focusable.
- **Fix:** Added desktop/mobile scans for the guardian family summary and invoices, assessment authoring, and answer review with rubric grading. Darkened muted dashboard and assessment labels; made the question-accuracy table a labeled, keyboard-focusable region.
- **Verification:** Accessibility suite passed (8 scenarios, 18 viewport scans); full Chromium suite passed (46 tests); Vitest passed (12 tests); `npm run build` passed; production PWA offline test passed (1 test).
- **Limitations:** Automated coverage still does not include every teacher/admin workflow or dialog. Manual screen-reader, keyboard-only, cross-browser, and physical-device checks remain pending.
- **Next:** Extend accessibility scans to remaining teacher/admin workflows, then continue API authorization, PostgreSQL, and release-readiness checks.

## Milestone 0.240 — Scan learner and educator attendance states
- **Finding:** The initial axe pass covered public/auth screens and the owner dashboard, but not a learner dashboard or an educator’s attendance-entry dialog. These states exposed additional low-contrast attendance and roster details.
- **Fix:** Extended automated checks to the learner dashboard and attendance dialog at desktop and mobile sizes. Darkened student attendance summaries, roster identifiers, form labels, dialog instructions, and neutral controls to maintain text contrast without changing surface colors or layout.
- **Verification:** The accessibility suite passed (5 scenarios, 10 viewport scans); all Chromium tests passed (43 tests); `npm run build` passed; the production PWA offline test passed (1 test).
- **Limitations:** These scans cover public home, sign-in, owner dashboard, learner dashboard, and educator attendance dialog. Guardian views, other teacher/admin modules, remaining dialogs, manual screen-reader checks, and physical devices remain pending.
- **Next:** Extend axe scans to guardian learning/billing and assessment authoring/review states, then continue API authorization and release-readiness checks.

## Milestone 0.239 — Add automated responsive accessibility scans
- **Finding:** Responsive, keyboard, and selected contrast checks existed, but automated WCAG rule scans did not cover the public page, sign-in, or an authenticated workspace dashboard. The first scan found low-contrast workspace metadata, nav labels, and dashboard captions.
- **Fix:** Added axe-core Playwright checks for public home, sign-in, and the authenticated owner dashboard at 1280×900 and 375×812. Darkened workspace neutral text colors to meet scan thresholds; kept public/auth colors that already passed. The accessibility spec runs in the existing CI Chromium suite. Also replaced a stale hard-coded announcement date in the browser fixture with a future Asia/Kolkata date.
- **Verification:** Axe scenarios passed (3 tests, 6 viewport scans); full Chromium suite passed (41 tests); PWA offline test passed (1 test); Vitest passed (12 tests); `npm run build` passed; npm audit found 0 vulnerabilities.
- **Limitations:** Automated checks cover these three page states at two widths, not every module, dialog, role, or intermediate interaction. Physical screen-reader, keyboard-only, and mobile-device reviews remain pending.
- **Next:** Add learner and educator dialog scans, then continue the remaining API authorization, PostgreSQL, and release-readiness checks.

## Milestone 0.238 — Verify section-scoped assessment grading
- **Finding:** Teacher section scope was covered for assessment lists, analytics, review, and exports, but the short-answer grading mutation had no regression proving an out-of-section attempt could not be changed.
- **Fix:** Extended the section-scope test with a rubric-backed short-answer response. It verifies an assigned teacher can grade within the assigned section, the grader/feedback/rubric/score persist, and an attempt from another section returns 404 with its answer and score unchanged. No controller change was needed.
- **Verification:** Focused scope test passed (1 test, 36 assertions); full Laravel suite passed (115 tests, 2,578 assertions).
- **Limitations:** This covers section assignment for grading but not all teacher multi-class combinations or every mutation boundary. Hosted PostgreSQL, production integrations, staging, and physical assistive-technology checks remain pending.
- **Next:** Extend automated accessibility scans to more role-specific views and dialogs, then continue the remaining route authorization and release-readiness audit.

## Milestone 0.237 — Verify section-scoped assessment and import contracts
- **Finding:** Whole-class teacher assessment access was covered, but section assignments and filters could still regress independently. The CSV import preview contract also did not declare the existing eight-option limit, and runtime tests did not fully assert valid and malformed row projections.
- **Fix:** Added a section-assignment regression covering attempt lists, analytics, review, grade export, and an explicit out-of-scope learner filter. Added the question import option limit to OpenAPI and expanded rubric/import schema and runtime assertions for valid option objects, invalid type/difficulty values, row errors, and imported question fields. No controller behavior change was needed.
- **Verification:** Focused section/import/OpenAPI tests passed (4 tests, 882 assertions); full Laravel suite passed (115 tests, 2,568 assertions); `php artisan openapi:export --check` passed. Local sign-in verification tests passed (3 tests, 12 assertions); email verification is disabled in local Laravel and Vite config, and the restarted API and frontend health checks return 200 at ports 8013 and 5191.
- **Limitations:** These tests cover one teacher/section pairing and CSV row variants, not every multi-class, rubric, or importer failure combination. Hosted PostgreSQL, production integrations, staging, and physical assistive-technology checks remain pending.
- **Next:** Verify section-scoped assessment grading and continue the remaining route authorization and release-readiness audit.

## Milestone 0.236 — Verify teacher assessment result scopes
- **Finding:** Teacher class-assignment scope was applied in attempt lists, analytics, and grade CSV export, but those endpoints lacked a direct regression proving an unassigned learner could not appear or be selected by filter.
- **Fix:** Added a synthetic workspace test with a teacher assigned to one class and an unassigned classmate. It verifies attempt lists, aggregate analytics, and grade exports include only the assigned learner, including when an API filter explicitly names the unassigned learner. No controller change was needed.
- **Verification:** Focused teacher-scope test passed (1 test, 23 assertions); full Laravel suite passed (114 tests, 2,509 assertions).
- **Limitations:** This covers whole-class assignment for these three endpoints. Section-specific scope is covered in milestone 0.237; teacher analytics with multiple classes, hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Continue the assessment analytics/import and route authorization audit, then run remaining release-readiness checks.

## Milestone 0.235 — Verify assessment tenant boundaries and local sign-in
- **Finding:** Assessment result and review queries already scope by organization, assessment, and linked learner, but dedicated tests did not explicitly cover swapped tenant, assessment, and attempt identifiers. The local preview was also showing catalog request errors while its old API worker was unresponsive.
- **Fix:** Added a focused tenant-isolation regression for same-tenant access and foreign organization, assessment, attempt-result, and attempt-review identifiers. No controller change was needed. Changed the local-only Laravel cache/session stores to file, restarted the API, and confirmed email verification remains disabled. The browser now shows the sign-in form without verification controls and loads the synthetic catalog.
- **Verification:** Focused test passed (1 test, 6 assertions); the full Laravel suite passed (113 tests, 2,486 assertions); `php artisan openapi:export --check` passed. `/api/v1/health` returned 200; Chrome loaded the homepage course and plan catalog and displayed the sign-in screen without a verification prompt.
- **Limitations:** This regression covers learner results and manager review attempt scoping, not every teacher-assignment, analytics, export, or import combination. Sign-in was visually checked without submitting credentials. Hosted PostgreSQL, production email delivery, staging, and physical assistive-technology checks remain pending.
- **Next:** Audit assessment analytics, exports, imports, and teacher-assignment scope, then continue remaining API and release-readiness checks.

## Milestone 0.234 — Audit assessment attempt and review responses
- **Finding:** Assessment attempt summaries and teacher review projections include nullable score/timestamps, pending review state, rubric information, and answer-key fields; existing runtime checks emphasized grading behavior more than the full response shape.
- **Fix:** Added OpenAPI assertions for attempt and review required fields, status enums, nullable grading/rubric fields, and selected/correct option structures. Expanded the written-answer regression to validate the instructor review payload before grading while retaining student-side answer-key omission checks. No controller behavior change was needed.
- **Verification:** Focused OpenAPI/organization-access tests passed (17 tests, 1,269 assertions); the full Laravel suite passed (112 tests, 2,480 assertions); `php artisan openapi:export --check` passed.
- **Limitations:** This covers learner result and instructor attempt-review projections in the written-answer path. Objective review, rubric response variants, analytics, imports, and cross-tenant assessment cases still need a comprehensive final audit; hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Audit rubric-based review output and tenant-boundary cases, then run release-readiness checks.

## Milestone 0.233 — Audit learner assessment result responses
- **Finding:** The learner result projection intentionally omits answer correctness and answer-key data while exposing pending/reviewed grading feedback; contract coverage asserted only the response reference and absence of correctness fields.
- **Fix:** Added schema assertions for the required result envelope, nullable answer/feedback fields, and selected-option references. Strengthened the runtime written-answer path to check attempt totals, answer fields, pending state, empty selected options, and absent correctness data before grading. No controller behavior change was required.
- **Verification:** Focused OpenAPI/organization-access tests passed (17 tests, 1,238 assertions); `php artisan openapi:export --check` passed.
- **Limitations:** This covers the student assessment-result projection and written-answer pending/reviewed path. Other assessment authoring, import, analytics, and grading paths still need comprehensive contract/scope review; hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Continue field-level auditing of assessment attempt/review responses and verify tenant-scoped cases before release-readiness checks.

## Milestone 0.232 — Audit guardian invoice and payment responses
- **Finding:** Invoice listings are scoped to a student's own profile or a guardian's active links, and summaries/collections have distinct capability gates; the response contract assertions did not fully pin nullable profile fields, payment shapes, or pagination metadata.
- **Fix:** Added OpenAPI assertions for invoice/payment required fields, nullable student and description/date values, invoice/payment enums, summary metrics, collection metadata, and nested payment references. Extended runtime checks for the guardian's linked-child invoice projection, payment details, and reconciled balance. No controller behavior change was required.
- **Verification:** Focused invoice/OpenAPI tests passed (7 tests, 876 assertions); `php artisan openapi:export --check` passed.
- **Limitations:** This covers invoice list, student/guardian visibility, payment records, summaries, and collections contract shapes. Other API response families remain under review; hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Continue the response audit with assessment result/review endpoints and cross-tenant invoice authorization cases.

## Milestone 0.231 — Audit assignment and submission response contracts
- **Finding:** Assignment APIs return different fields to learners and staff, while submissions carry nullable grading values and private attachment metadata; the existing response assertions checked route schemas and only a small subset of runtime fields.
- **Fix:** Added OpenAPI assertions for assignment and submission required fields, optional learner submission projections, staff-only submission counts, allowed statuses, nullable values, and attachment schemas. Extended the learner/staff workflow regression to verify pre-submission nulls, newly submitted fields, and roster identity fields. No controller behavior change was needed.
- **Verification:** Focused OpenAPI and organization-access tests passed (17 tests, 1,208 assertions); `php artisan openapi:export --check` passed.
- **Limitations:** This covers assignment listing/submission/grading responses and attachment metadata contracts. Other learning and billing response families still require field-level and tenant-scope audits; PostgreSQL CI, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Audit the guardian invoice list and payment summaries, then continue route-by-route authorization and tenant-isolation review.

## Milestone 0.230 — Audit learner dashboard response contracts
- **Finding:** The learner dashboard had route-level response schemas and a populated-data test, but nullable fields, bounded projections, and the response for a student membership without an active profile lacked focused contract coverage.
- **Fix:** Added OpenAPI assertions for the required dashboard shape, assignment/recommendation/result limits, and nullable assignment, result, and attendance values. Added a runtime regression for the no-active-profile response. The controller and schema agree; no runtime behavior change was needed.
- **Verification:** Focused OpenAPI/student-dashboard tests passed (3 tests, 788 assertions); the full Laravel suite passed (112 tests, 2,377 assertions); `php artisan openapi:export --check` passed.
- **Limitations:** This audit covers the learner dashboard response and no-active-profile branch; assignment submission and assessment result endpoints still require detailed field-level review. PostgreSQL CI, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Audit learner assignment submission/result and guardian invoice responses, then continue route-by-route authorization and tenant-isolation review.

## Milestone 0.229 — Audit guardian learner-summary contracts
- **Finding:** Guardian child summaries contain capability-conditional sections, nullable values for learners with no attendance/results, and bounded nested lists; existing OpenAPI assertions covered only the outer response and homework reference.
- **Fix:** Added contract assertions for the required base and nested fields, optional attendance/learning/results projections, nullable timestamps and labels, homework submission states, and nested-list limits. Added a runtime regression for an actively linked learner with no attendance, course, or assessment data. No controller behavior change was needed.
- **Verification:** Focused OpenAPI/custom-role tests passed (14 tests, 876 assertions); the full Laravel suite passed (111 tests, 2,354 assertions); `php artisan openapi:export --check` passed.
- **Limitations:** This audit covers the guardian learner-summary route and its empty-data and capability-omission branches. Other response families still need controller-to-schema and tenant-scope review; PostgreSQL CI, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Audit learner assignment/result and guardian invoice responses, then continue route-by-route response, authorization, and tenant-isolation review.

## Milestone 0.228 — Audit workspace dashboard response nullability
- **Finding:** The workspace dashboard’s OpenAPI contract only asserted one permission-gated metric and the schedule list, despite several additional counts and display fields becoming null for roles without academic, attendance, or task permissions.
- **Fix:** Added exact required-field assertions for the dashboard response, type/nullability checks for permission-gated metrics and nullable schedule-entry fields, and guardian runtime assertions for the no-access null branch. The controller and schema agreed; no runtime behavior needed changing.
- **Verification:** Focused OpenAPI/organization-access tests passed (17 tests, 1,149 assertions); the complete Laravel suite passed (110 tests, 2,320 assertions), including the route-to-generated-document consistency check.
- **Limitations:** This audit covers the workspace dashboard response and its guardian no-access branch. Other nested nullable, conditional, and permission-scoped response fields still require the planned route-by-route review. Hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Audit the guardian learner-summary response against its controller branches and generated schema, then continue the remaining response-field and tenant-scope review.

## Milestone 0.227 — Localize academic timetable and dated schedule changes
- **Finding:** Hindi workspaces still showed English academic-year, class setup, weekly timetable, assignment, and date-specific schedule controls.
- **Fix:** Localized academic calendar management, weekly lesson editing, teacher/subject assignments, closure/cancellation/extra-lesson forms, statuses, errors, and empty states. Weekday names use locale-aware long and short forms; academic dates and assignment history use the selected locale and workspace timezone where applicable. Date-only values remain stable across browser timezones.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 38 Chromium Playwright tests passed, including a Hindi fixture with an active timetable entry and dated extra lesson; the production PWA offline-shell test passed; all 110 Laravel tests and 2,299 assertions passed; `php artisan openapi:export --check` passed. The main bundle is 333.37 kB (94.04 kB gzip), and the workspace bundle is 254.10 kB (53.23 kB gzip).
- **Limitations:** Learner-authored schedule titles/notes, class and teacher names, and API validation messages remain as entered/returned. Exception details cannot be edited, and cancelling an individual weekly lesson is not implemented. The Laravel suite must run in a local test context where PHP can read workspace files; the restricted sandbox reports `is_readable=false` for all workspace files.
- **Next:** Continue the controller-to-OpenAPI response and tenant-scope audit, then repeat release checks and the hosted PostgreSQL workflow when repository access is available.

## Milestone 0.226 — Localize live classrooms and attendance
- **Finding:** Live-session controls, meeting/recording actions, status labels, attendance form, and provider/privacy guidance remained English in Hindi workspaces.
- **Fix:** Translated session scheduling and audience controls, timezone-labelled date fields, live/completed/cancelled states, provider names, join/recording actions, attendance capture, privacy notes, empty/loading/error states, and localized meeting times using the workspace timezone.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 37 Chromium Playwright tests passed, including Hindi live-session labels and persisted attendance fixture; the production PWA offline-shell test passed. The production main bundle is 333.37 kB (94.04 kB gzip), and the workspace bundle is 242.95 kB (51.09 kB gzip).
- **Limitations:** Weekly timetable and dated academic schedule configuration remain mostly English. Live meetings rely on external providers; no media hosting or provider-verified attendance is implemented. Hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize weekly timetable and dated schedule management, then finish remaining educator/admin workflows and release verification.

## Milestone 0.225 — Localize workspace invitations
- **Finding:** Workspace managers could issue private one-use invitations and link accepted accounts, but form labels, role/status text, instructions, expiry dates, and link-sharing messages remained English.
- **Fix:** Translated invitation creation, role and lifecycle labels, dates, one-use link disclosure, copy states, accepted-account linking guidance, and destination actions. The message continues to state that email delivery is not configured; account verification remains conditional on workspace settings.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 36 Chromium Playwright tests passed, including Hindi invitation creation/link display and account-linking flows; the production PWA offline-shell test passed. The production main bundle is 326.80 kB (92.64 kB gzip), and the workspace bundle is 243.12 kB (51.29 kB gzip).
- **Limitations:** Invitation email delivery is not configured; accepted account names and email addresses remain authored data. Schedule, live-session, and guardian-link controls are not fully localized. Hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize class schedule and live-session workflows, then continue release verification and hosted PostgreSQL validation.

## Milestone 0.224 — Localize the educator AI-learning workflow
- **Finding:** The learning tutor and teacher assistant accepted Hindi questions/draft language, but their privacy notices, context controls, teaching draft options, status labels, and response controls remained English.
- **Fix:** Localized tutor and teacher-assistant headings, privacy guidance, course/lesson selection, question and topic fields, all draft types, language choices, progress states, editable-draft labels, and review-before-use guidance. AI-authored answers and drafts remain provider output; the requested output language is passed unchanged. Preserved the existing accessible name for the generated draft in both locales.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 35 Chromium Playwright tests passed, including learner AI tutor and educator Hindi question/draft flows; the production PWA offline-shell test passed. The production main bundle is 322.15 kB (91.75 kB gzip), and the workspace bundle is 243.34 kB (51.50 kB gzip).
- **Limitations:** No live AI provider is configured; browser verification used API fixtures. Remaining educator/admin modules, hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize educator invitations, class schedule, and live-session workflows, then continue release verification and hosted PostgreSQL validation.

## Milestone 0.223 — Localize platform operations and feature controls
- **Finding:** Platform administrators switching to Hindi still encountered English usage summaries, history tables, organization search/lifecycle controls, audit metadata, and AI feature flags.
- **Fix:** Translated platform usage metrics and snapshot chart/table labels, locale-formatted snapshot and audit dates, organization search/status/count/pagination controls, reversible access confirmation, audit summaries, feature-flag status/actions, and known AI flag names/descriptions. Localized the platform page title, breadcrumb, description, and navigation label. Server-authored organization and plan names and technical audit event keys remain as supplied.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 34 Chromium Playwright tests passed, including Hindi organization and feature-flag controls plus platform plan review; the production PWA offline-shell test passed. The production main bundle is 315.34 kB (90.15 kB gzip), and the workspace bundle is 243.91 kB (51.92 kB gzip).
- **Limitations:** Other untranslated educator/admin modules and most API validation messages remain. Hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize the educator AI-learning workflow, then continue the remaining educator/admin modules and release verification.

## Milestone 0.222 — Localize platform plan authoring and review
- **Finding:** Platform administrators could switch to Hindi, but plan creation/editing and subscription-request review controls remained English.
- **Fix:** Translated plan audience and billing interval choices, create/edit labels, custom pricing guidance, entitlement JSON labels, configured-plan metadata, verified-reference field, approval/rejection actions, pending counts, and local JSON validation errors. Approval still requires the existing verified contract/payment reference.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); a focused Chromium scenario passed for Hindi plan authoring fields and verified request approval payload; the production PWA offline-shell test passed. The production main bundle is 307.00 kB (88.37 kB gzip), and the workspace bundle is 242.35 kB (51.93 kB gzip).
- **Limitations:** Platform usage, organization lifecycle, audit history, and feature-flag labels remain English. API validation and server errors remain in the source language. Hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize platform usage, organization management, audit, and feature-flag sections, then continue remaining educator/admin screens and release verification.

## Milestone 0.221 — Localize the Help Centre
- **Finding:** Members selecting Hindi could submit localized feedback, but the Help Centre FAQ, search, email-contact callout, and sidebar shortcut remained English. One invitation answer also incorrectly implied verification was always required.
- **Fix:** Localized all six FAQ topics and answers, search label/placeholder and empty state, contact and feedback actions, and sidebar help card. Invitation guidance now accurately says verification is required only when enabled for that workspace; authored email addresses remain unchanged.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); four focused Chromium scenarios passed (feedback manager response, member history, notification deep link, and Hindi Help Centre search); the production PWA offline-shell test passed. The production main bundle is 303.41 kB (87.63 kB gzip), and the workspace bundle is 241.95 kB (51.97 kB gzip).
- **Limitations:** FAQ search is browser-side substring matching over the six built-in entries. Remaining platform-admin and educator screens, hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize platform-admin plan authoring/review, then continue remaining educator/admin screens and API/release verification.

## Milestone 0.220 — Localize member and manager feedback
- **Finding:** Feedback submission and manager response history remained English in workspaces using Hindi, despite the rest of the member support workflow being available.
- **Fix:** Localized feedback categories, form labels, privacy guidance, character counts, submission and update states, member status history, manager response controls, empty/loading states, and date/count formatting. User-authored feedback and manager response text remain in the language entered.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all three feedback-focused Chromium scenarios passed; the production PWA offline-shell test passed. The current production chunks are 297.42 kB (86.36 kB gzip) for the main bundle and 242.79 kB (52.41 kB gzip) for the workspace bundle.
- **Limitations:** API validation messages and authored feedback/response text are not translated. Platform-admin authoring/review and remaining educator/admin screens, hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize the Help Centre and remaining platform-admin/educator screens, then continue API authorization/schema, release-readiness, and hosted PostgreSQL verification.

## Milestone 0.219 — Localize educator academic reports
- **Finding:** Educators could select Hindi elsewhere in the workspace, but academic report filters, attendance/course/assignment summaries, assessment analytics, and export headers remained English.
- **Fix:** Translated workspace/assigned-class report views, date/class/student filters, summary cards, score bands, assessment trends and breakdown tables, empty states, print/CSV actions, and CSV headers. Month names, percentages, and number formatting follow the selected locale; class, student, course, and assessment names remain authored.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); the report/CSV focused browser test passed on the final build; all 32 Chromium Playwright tests passed; the production PWA offline-shell test passed.
- **Limitations:** Report API errors and authored academic names remain in their source language. Feedback, most other educator/admin screens, hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize member and manager feedback workflows, then continue remaining educator/admin screens and release-readiness work.

## Milestone 0.218 — Localize workspace announcements
- **Finding:** Workspace managers could create, schedule, edit, withdraw, and restore announcements, but the controls, audience/status labels, and timestamps remained English in Hindi workspaces.
- **Fix:** Translated announcement authoring/editing forms, audience choices, publish/schedule actions, access hints, lifecycle states, action buttons, and empty/loading states. Scheduled/published timestamps follow the selected locale and workspace timezone; authored announcement titles and messages remain unchanged.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); the four announcement-focused Chromium scenarios passed; all 32 Chromium Playwright tests passed; the production PWA offline-shell test passed. Coverage includes existing-audience edit, workspace-timezone scheduling, withdrawal, and restoration in Hindi.
- **Limitations:** API validation responses and authored announcement text remain in their source language. Other educator/admin modules, hosted PostgreSQL, cross-browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize educator reports and feedback, then continue release-readiness and PostgreSQL CI work when a runner is available.

## Milestone 0.217 — Localize workspace plan billing
- **Finding:** Workspace owners could request a plan change, but the subscription summary, entitlement limits, plan catalog, pricing, and pending request status remained English.
- **Fix:** Localized the owner plan page, supported entitlement labels, audiences, billing intervals, pricing, and pending/active states. Request dates and INR prices follow the selected locale; the manual-review/no-payment note remains visible in both languages. Educator-authored plan names and descriptions stay in their source language.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 32 Chromium Playwright tests passed, including an owner Hindi plan-change request and pending-state fixture; the production PWA offline-shell test passed.
- **Limitations:** Platform-admin plan authoring/review screens, arbitrary entitlement labels, API errors, and educator-authored plan names/descriptions remain untranslated. The full Laravel suite last passed at 110 tests and 2,299 assertions; hosted PostgreSQL and physical accessibility checks remain pending.
- **Next:** Localize remaining high-use educator/admin workflows and address release-readiness gaps, then validate PostgreSQL CI when a runner becomes available.

## Milestone 0.216 — Localize fee invoices and collections
- **Finding:** Fee invoice issue, payment reconciliation, receipts, and collections reporting remained English when the workspace was switched to Hindi.
- **Fix:** Translated invoice summaries and statuses, issue-date filters, issue/payment forms, method labels, empty states, collections table and pagination, and CSV headers. Currency and visible dates now follow Hindi/English locale formatting; printable payment receipts use the active locale and workspace timezone.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 31 Chromium Playwright tests passed, including invoice pagination/filter/export, collection export, and receipt workflows in English plus Hindi UI/receipt assertions; the production PWA offline-shell test passed.
- **Limitations:** The plan/subscription billing page, API validation messages, user-authored invoice/course text, and other workspace modules remain partly or wholly untranslated. The full Laravel suite last passed at 110 tests and 2,299 assertions; hosted PostgreSQL and physical accessibility checks remain pending.
- **Next:** Localize plan/subscription billing and the remaining high-use educator/admin workflows, then validate PostgreSQL CI when a runner becomes available.

## Milestone 0.215 — Localize guardian family summaries
- **Finding:** Guardians who selected Hindi still saw their linked learners’ attendance, learning progress, assessment results, upcoming exams, and homework status in English.
- **Fix:** Translated the family-summary panel, empty/loading states, metrics, attendance and result headings, homework statuses, timezone-aware exam/deadline dates, and CSV export headers. Summary dates use the guardian workspace timezone and the selected locale.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); the focused guardian Hindi browser test passed; all 31 Chromium Playwright tests passed; the production PWA offline-shell test passed. The browser fixture verifies translated headings, homework status, and the download action.
- **Limitations:** Guardian account-link management forms, most other workspace modules, API errors, and authored student/course content remain untranslated. Hosted PostgreSQL, other browser engines, and physical assistive-technology checks remain pending.
- **Next:** Localize fee and billing workflows, then continue remaining educator/admin pages and validate PostgreSQL CI when a runner becomes available.

## Milestone 0.214 — Localize educator tasks and class attendance
- **Finding:** Educators could choose Hindi, but shared task management and the class roster/attendance flow still used English labels and date text.
- **Fix:** Translated task filters, create/edit controls, empty states, action labels, assigned-task metadata, due dates, class/student forms, roster actions, attendance history, and the attendance dialog/status choices. Task presentation refreshes when the locale changes; entered task, class, and learner names remain as authored.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 30 Chromium Playwright tests passed; the production PWA offline-shell test passed. Browser coverage verifies Hindi task creation controls, class roster, attendance dialog keyboard close/focus return, and existing role workflows.
- **Limitations:** API validation/error responses and authored task/class/student content remain in their source language. Schedule configuration, billing, and other educator/admin modules are not yet translated.
- **Next:** Translate guardian summaries and fee/billing workflows, then continue the remaining educator/admin pages and validate PostgreSQL CI when a runner is available.

## Milestone 0.213 — Localize the learner dashboard and study planner
- **Finding:** Learners could switch to Hindi, but their primary learning summary and study-planning form remained English. A role-validation fallthrough also redirected valid student pages to the staff Overview.
- **Fix:** Translated learner dashboard cards, no-data states, attendance/result statuses, study-plan form and recommendation controls, and timezone-aware date formatting. Student page validation now uses its own allowed-page set and no longer falls into staff navigation checks. Updated browser API fixtures to return the student dashboard contract.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 29 Chromium Playwright tests passed; the production PWA offline-shell test passed. Focused browser coverage verifies the learner dashboard and planner in Hindi and English, student workspace switching, feedback notifications, and mobile navigation.
- **Limitations:** The printable certificate, API validation/errors, authored course content, guardian workflows, and most educator/admin modules remain in their original language.
- **Next:** Localize the educator task and class-management workflows, then continue guardian and billing views; validate PostgreSQL CI when a runner is available.

## Milestone 0.212 — Localize signed-in workspace navigation
- **Finding:** The English/Hindi selector was available on the public site and sign-in screens but disappeared after login; workspace navigation and its shared account/search controls stayed English.
- **Fix:** Kept the saved locale active in the signed-in workspace, synchronized the document language, translated the main role-based navigation and shared top-bar labels, and added a compact language control to the workspace header. The overview now localizes the role-aware greeting, date, learner/guardian summaries, and attendance/report shortcuts. Internal page identifiers remain stable while their navigation labels change.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 28 Chromium Playwright tests passed; the production PWA offline-shell test passed. Browser coverage includes a fixture-backed workspace locale switch, saved Hindi selection, translated overview, and 375px mobile navigation.
- **Limitations:** Dashboard/module content, most forms and validation messages, and user-authored course/plan text remain in their original language. Locale preference is browser-local rather than synchronized to an account.
- **Next:** Translate high-use learner and educator page content in bounded sections, then validate PostgreSQL CI when a runner is available.

## Milestone 0.211 — Add Hindi localization to public and sign-in flows
- **Finding:** The responsive public site and account entry screens were English-only, limiting access for the initial Indian audience.
- **Fix:** Added a reusable locale context and English/Hindi translation dictionary, a keyboard-accessible language selector, and localStorage preference persistence. The public marketing/catalog/pricing flow and auth forms now render in Hindi or English. Course and plan titles/descriptions remain in their source language. The authenticated workspace stays English for now and resets the document language accordingly.
- **Verification:** `npm run build` passed; Vitest passed (12 tests); all 27 Chromium tests passed, including Hindi selection, persistence after reload, sign-in translation, 320px overflow, and WCAG text contrast; the production PWA offline-shell test passed.
- **Limitations:** Workspace modules, API validation messages, and user-authored catalog content are not translated. Locale selection is per browser, not a profile setting synchronized across devices.
- **Next:** Extend the translation layer into workspace content and core learner/teacher journeys, then validate the PostgreSQL CI path when a runner is available.

## Milestone 0.210 — Personalize study suggestions with reviewed results
- **Finding:** Learner study planning used unfinished courses and upcoming unsent assignments, but ignored a learner's recent assessment performance.
- **Fix:** The student dashboard now adds a short course-review suggestion when the learner's latest available reviewed result in an active enrolled course is below 70%. Upcoming assignments, review suggestions, and incomplete courses are prioritized together. Results are read only for the authenticated learner, within active enrolled courses, and only when the effective workspace policy permits `results.view`; pending reviews and zero-point assessments are ignored. The API schema and planner description now reflect the rules-based behavior.
- **Verification:** Student dashboard regression passed (1 test, 26 assertions); OpenAPI contract regression passed; full Laravel suite passed (110 tests, 2,299 assertions); `npm run build` passed.
- **Limitations:** The recommendation threshold is a fixed transparent rule, not an adaptive or AI-generated evaluation. It is intentionally limited to the latest eight results returned to the learner dashboard.
- **Next:** Continue product localization/accessibility gaps and validate the configured PostgreSQL CI path when a runner is available.

## Milestone 0.209 — Complete local frontend and browser regression pass
- **Finding:** The previous verification covered selected frontend/auth flows but not the entire responsive, interaction, and production PWA browser suites after the latest changes.
- **Fix:** Ran the complete frontend and browser regression sets against the current build and local preview. No regression fixes were required.
- **Verification:** Vitest passed (12 tests across 4 files); Chromium Playwright passed all 26 public/workspace tests, including responsive viewport and WCAG contrast checks; the production PWA offline-shell test passed on port 5176 because port 5175 was occupied; the frontend production build and full Laravel suite passed (110 tests, 2,296 assertions). Current `npm audit` and `composer audit --locked` found no advisories.
- **Limitations:** PostgreSQL hosted CI, deployed staging, cross-browser/device screen-reader checks, and third-party provider operations remain unverified.
- **Next:** Continue release readiness with environment/CI checks and pursue independent product work while hosted validation remains unavailable.

## Milestone 0.208 — Add audited platform feature flags
- **Finding:** AI availability had provider settings and quotas, but platform administrators could not disable the AI tutor or teacher assistant through a server-enforced control.
- **Fix:** Added persistent global flags for both AI features, platform-admin-only list/update endpoints, change-only audit entries, and server-side enforcement in both AI endpoints. Added the accessible controls to Platform administration and documented the route contract. Applied the additive feature-flag migration to the local database.
- **Verification:** Full Laravel suite passed (110 tests, 2,296 assertions); `php artisan openapi:export --check` passed through the OpenAPI test; frontend `npm run build` passed; the focused local auth browser regression passed (1 test). The local login smoke check succeeded for the synthetic demo owner and its temporary token was revoked.
- **Limitations:** Flags are global and only the two AI features are configurable. Hosted PostgreSQL and production deployment remain unverified.
- **Next:** Continue release and accessibility verification, then expand platform controls and the remaining ERP/LMS backlog.

## Milestone 0.207 — Restore local sign-in without email verification
- **Finding:** Local sign-in was being reported as blocked by email verification even though local development is intended to bypass that gate.
- **Fix:** Cleared Laravel's config cache and started the local API on port 8013 with `auth.require_verified_email=false`. The verification/resend screen remains opt-in through `VITE_REQUIRE_EMAIL_VERIFICATION`; added the missing platform feature-flags panel declaration so the existing frontend compiles again.
- **Verification:** `php artisan config:show auth.require_verified_email` reports `false`; the focused Laravel auth suite passed (3 tests, 12 assertions); `npm run build` passed. The local health endpoint returned HTTP 200 and a live synthetic demo login succeeded, then its temporary token was revoked. The browser preview remains available on port 5174.
- **Limitations:** The prior account was not present in the local database; a synthetic demo account is available instead and does not restore an earlier account or password.
- **Next:** Continue release verification and expand the remaining platform administration backlog.

## Milestone 0.206 — Constrain public course and platform subscription enums
- **Finding:** Public course organization types and platform directory subscription statuses were emitted from validated, finite domain values but allowed arbitrary strings in OpenAPI.
- **Fix:** Constrained course organization type to the supported workspace types plus null, and subscription state to active, change_pending, or null; added generated-contract assertions.
- **Verification:** OpenAPI export and `--check` passed; focused OpenAPI/public-course/platform tests passed (9 tests, 754 assertions); full API suite passed (108 tests, 2,267 assertions).
- **Limitations:** The response enum/nullability audit continues. Hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Continue auditing response fields against route/controller outputs and complete release/accessibility verification.

## Milestone 0.205 — Constrain public subscription plan enums
- **Finding:** Public subscription plan responses emitted the same validated audience and billing interval values as platform plans, but the public response schema accepted any string.
- **Fix:** Restricted `SubscriptionPlan.audience` to `all`, `institution`, or `educator` and `billing_interval` to `month`, `year`, or `custom`; added OpenAPI regression assertions.
- **Verification:** OpenAPI export and `--check` passed; focused OpenAPI/subscription tests passed (4 tests, 717 assertions); full API suite passed (108 tests, 2,265 assertions).
- **Limitations:** The enum audit continues across remaining response schemas. Hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Review remaining response enums and nested field nullability, then run release and accessibility verification.

## Milestone 0.204 — Bound learner dashboard assessment scores
- **Finding:** Assessment scores are calculated from nonnegative question points, but the learner dashboard result schema did not express the score lower bound.
- **Fix:** Added a `minimum: 0` constraint for non-null learner result scores and a generated-contract assertion.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax passed; focused OpenAPI/dashboard tests passed (2 tests, 713 assertions); full API suite passed (108 tests, 2,263 assertions).
- **Limitations:** The response-field audit is ongoing. Hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Continue the route-by-route audit of nested response values and omitted permission-scoped fields.

## Milestone 0.203 — Require subject labels in dashboard schedule entries
- **Finding:** Workspace and learner dashboards derive schedule subjects from either a required timetable subject or a required schedule-exception title, but OpenAPI allowed `subject: null`.
- **Fix:** Made dashboard schedule `subject` non-null in the shared response schema and added a contract assertion.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax passed; focused OpenAPI, organization-dashboard, and schedule-exception tests passed (18 tests, 1,148 assertions); full API suite passed (108 tests, 2,262 assertions).
- **Limitations:** Other nullable and conditional response fields remain under review. Hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Finish checking teacher/guardian dashboard conditionals and continue release/accessibility verification.

## Milestone 0.202 — Constrain learner dashboard status values
- **Finding:** The learner dashboard's `submission_status` field accepted any string, although assignment submissions can only be `submitted` or `graded`, and the field is null when there is no submission.
- **Fix:** Restricted the OpenAPI field to the two emitted statuses plus null, added a schema regression assertion, and asserted the no-submission null branch in the dashboard feature test.
- **Verification:** OpenAPI export and `--check` passed; focused OpenAPI/dashboard tests passed (2 tests, 711 assertions); full API suite passed (108 tests, 2,261 assertions).
- **Limitations:** This continues the controller-to-schema audit; other nullable and conditional fields still need review. Hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Continue response-schema review for teacher/guardian dashboard payloads and field-level omissions, then complete release and accessibility verification.

## Milestone 0.201 — Constrain learner assessment result status values
- **Finding:** The learner dashboard's assessment result schema accepted any `review_status` string although persisted assessment attempts emit only `not_required`, `pending`, or `reviewed`.
- **Fix:** Restricted the generated `StudentAssessmentResult.review_status` schema to the three controller-supported values and added an OpenAPI regression assertion. Confirmed the nullable title, course title, score, and submission timestamp fields against the student dashboard response builder.
- **Verification:** OpenAPI export and `--check` passed; recursive scan of the generated contract found no generic request/response references across 160 operations, 70 request bodies, and 329 response media entries; focused OpenAPI feature test passed (1 test, 686 assertions); full API suite passed (108 tests, 2,259 assertions).
- **Limitations:** This validates the audited learner result contract only; other nullable and conditional fields still need route-by-route review. Hosted PostgreSQL, production integrations, and physical assistive-technology checks remain pending.
- **Next:** Continue controller-to-schema auditing for assessment review/answer responses and remaining academic dashboard fields; rerun frontend/browser checks after the broader API audit.

## Milestone 0.200 — Specify assessment results and platform integration responses
- **Finding:** Assessment answer saving, short-answer grading/review, and grade downloads, along with webhook, health, public course order/enrollment, email-verification redirect, and private attachment results, still used generic success envelopes or media types.
- **Fix:** Added explicit assessment response variants and review records, CSV and binary media types, signed webhook request/header/response contracts, checkout/order and enrollment results, health readiness variants, and the verification redirect response.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; OpenAPI feature test passed (1 test, 685 assertions); public enrollment, payment webhook, attachment, health/auth tests passed (25 tests, 491 assertions); full Laravel SQLite suite passed (108 tests, 2,258 assertions).
- **Limitations:** This removes generic JSON request/response envelopes but a route-by-route audit of remaining nullable/conditional fields is still required. Live payment/provider integration, hosted PostgreSQL and physical accessibility remain externally pending.
- **Next:** Run the complete API suite and audit OpenAPI schema accuracy, then continue accessibility/release checks and documented production integration blockers.

## Milestone 0.199 — Specify platform directory and usage responses
- **Finding:** Platform organization search, audit timeline, usage totals/history, and pending subscription requests still used generic success envelopes.
- **Fix:** Added paginated directory/audit records, aggregate usage snapshots, and pending plan-change response schemas.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; OpenAPI, platform organization, usage snapshot, and subscription tests passed (10 tests, 415 assertions).
- **Limitations:** Some selected health, payment, checkout, binary, and assessment responses were not yet covered at this milestone.
- **Next:** Add exact assessment answer/review/download and integration response contracts.

## Milestone 0.198 — Specify communication and subscription responses
- **Finding:** Announcement, task, subscription, and platform plan operations had explicit request contracts but their success responses still used the generic API envelope.
- **Fix:** Added route-specific announcement/list/create, task/list/assignee, subscription/change, plan, workspace status, and subscription-resolution response schemas with accurate nullable fields and creation/request statuses.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; OpenAPI feature test passed (1 test, 333 assertions). Relevant announcement, subscription, platform, and organization behavior tests passed in milestone 0.197.
- **Limitations:** Other generic success responses remained at this milestone, including platform directories/usage, course checkout, health and selected assessment actions. PostgreSQL, hosted deployment, and physical accessibility checks remain pending.
- **Next:** Continue response schema coverage for platform directory/usage and remaining assessment actions; rerun the full API/browser suite after the response audit.

## Milestone 0.197 — Replace remaining generic request schemas
- **Finding:** The last generic request bodies covered AI tutor/drafts, announcements, tasks, plan requests, platform plan administration, organization status, and subscription review.
- **Fix:** Added explicit request schemas for every route, including update-field rules, plan entitlements, role/audience/status enums, and AI content bounds. The OpenAPI feature test now rejects any operation that regresses to the generic `JsonObject` request schema.
- **Verification:** Fresh OpenAPI scan found zero generic request bodies; export and `--check` passed; focused OpenAPI, announcement, subscription, platform, and organization tests passed; full Laravel SQLite suite passed (108 tests, 1,898 assertions).
- **Limitations:** This closes generic request schemas but does not prove every nested response schema is exhaustive. PostgreSQL/hosted deployment and physical accessibility checks remain pending.
- **Next:** Audit response-contract completeness, then continue release, accessibility, and externally blocked production validation work.

## Milestone 0.196 — Specify AI tutor and teacher-assistant contracts
- **Finding:** AI tutor and teacher-assistant requests/responses had bounded validation and privacy handling but remained generic in OpenAPI.
- **Fix:** Added tutor context/input/output schemas, teacher draft type/language and text bounds, usage token structures, and explicit response envelopes; descriptions retain runtime personal-data and course/lesson checks.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; focused OpenAPI test passed (1 test, 243 assertions); AI feature tests passed (3 tests, 50 assertions); full Laravel SQLite suite now passes (108 tests, 1,898 assertions).
- **Limitations:** No live AI provider credentials are configured, so only fake-provider and unavailable paths were exercised.
- **Next:** Complete response-contract audits and continue release/accessibility verification.

## Milestone 0.195 — Specify assessment authoring and grading requests
- **Finding:** Assessment/question creation, learner answers, manual grading, and CSV imports had validated API behavior but several requests were still generic in OpenAPI.
- **Fix:** Added bounded question and assessment requests, objective/short-answer answer variants, rubric criteria/scores, manual grade alternatives, required multipart CSV uploads, and explicit question/import response contracts including the invalid-row import response.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; focused OpenAPI feature test passed (1 test, 236 assertions); question CSV feature tests passed (2 tests, 24 assertions); full Laravel SQLite suite passed (108 tests, 1,809 assertions); `npm run build`, Vitest (12 tests), Chromium Playwright (26 tests), and the production PWA offline test (1 test) passed.
- **Limitations:** PostgreSQL and hosted deployment remain unverified.
- **Next:** Continue explicit request schemas for the remaining generic modules, then run the full API/browser suite and complete release/accessibility audits.

## Milestone 0.194 — Specify fee and collections API contracts
- **Finding:** Fee invoice, manual payment, summary, and collections routes already enforced currency precision, idempotency, date filters, and tenant/role scope, but their requests and responses were generic in OpenAPI.
- **Fix:** Added invoice creation and payment request schemas, paise-based invoice/payment/summary/collection responses, paginated metadata, list/collections date query parameters, exact creation statuses, and the void response. The payment contract includes the required UUID idempotency key and payment method enumeration.
- **Verification:** OpenAPI export and `--check` passed; exporter/test PHP syntax checks passed; focused OpenAPI test passed (1 test, 213 assertions); fee invoice feature tests passed (6 tests, 79 assertions).
- **Limitations:** Payments are manually reconciled ledger entries; no gateway transfer is initiated. External provider checkout and reconciliation remain unimplemented.
- **Next:** Continue field-level schemas for assessment and AI workflows, then run the full API/browser suite and complete release/accessibility audits.

## Milestone 0.193 — Specify organization and invitation contracts
- **Finding:** Organization creation/settings, member listing, and invitation operations had concrete validation and role-scoped response data, but their OpenAPI requests/responses remained generic.
- **Fix:** Added organization create/update, membership, member-list, invitation create/list/revoke/accept schemas and accurate 201/200 statuses. Organization membership schema now includes nullable custom-role fields. Invitation token is documented as a one-time secret, not as an example or fixture.
- **Verification:** OpenAPI export and `--check` passed; exporter and test syntax checks passed; focused OpenAPI feature test passed (1 test, 196 assertions).
- **Limitations:** Invite mail delivery remains unconfigured; the create response returns a one-time token for the authorized manager to deliver through an approved channel. Most remaining workspace request schemas still need field-level detail.
- **Next:** Continue covering generic requests in academic, assessment, and fee workflows; then repeat full API and release audits.

## Milestone 0.192 — Remove unused request bodies from lifecycle actions
- **Finding:** Multiple state-transition endpoints accepted no request data but were exported with optional generic JSON bodies.
- **Fix:** Removed request-body contracts from catalog enroll/order, year activation, announcement withdrawal/restoration, assessment start/submit, invoice void, live-session join, schedule cancellation, study-plan completion, and task lifecycle actions. Added regression assertions for each operation.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; the focused OpenAPI feature test passed (1 test, 185 assertions).
- **Limitations:** Many remaining operations that do accept input still need explicit field-level request schemas.
- **Next:** Continue replacing generic request schemas, beginning with organization settings and membership workflows.

## Milestone 0.191 — Specify auth profile and logout contracts
- **Finding:** Display-name updates had bounded server validation and a stable response, while logout revoked the current token, but OpenAPI described the profile payload generically and showed an unused logout request body.
- **Fix:** Added the bounded profile request and typed user response; removed the body from logout. Added route/schema assertions.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; focused OpenAPI feature test passed (1 test, 171 assertions).
- **Limitations:** Email updates and account recovery/support flows remain intentionally outside the profile endpoint.
- **Next:** Continue schema coverage for remaining workspace endpoints, then prioritize release and accessibility verification.

## Milestone 0.190 — Specify recipient notification contracts
- **Finding:** Workspace notifications merge announcement and feedback records into one recipient-scoped list, but the generated contract did not describe either variant or the read acknowledgement.
- **Fix:** Added a bounded response with explicit `announcement`/`feedback` types, nullable linked IDs and read timestamps, unread count, and the idempotent read result. Removed the request body from the bodyless mark-read action and added contract assertions.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; independent JSON assertions verified variants, item bound, response reference, and absence of a request body. The full Laravel suite passed (108 tests, 1,740 assertions) with the in-memory SQLite test database.
- **Limitations:** OpenAPI describes the linked-ID relationship in prose; runtime recipient/workspace authorization remains enforced by the controller. Other module payloads still need schemas.
- **Next:** Continue explicit schemas for the remaining generic workspace modules and complete the release/accessibility audits.

## Milestone 0.189 — Specify custom workspace role contracts
- **Finding:** Custom-role endpoints returned several distinct response shapes and two actions had no request body, but the generated contract represented them as generic JSON requests/responses.
- **Fix:** Added schemas for role-index permissions by base role, role/member lists, create/update, archive/restore, assign/unassign responses, and route-specific creation status. Removed misleading request bodies from bodyless restore and member-assignment operations. Added route and schema assertions.
- **Verification:** OpenAPI export and `--check` passed; PHP syntax checks passed; independent generated-JSON assertions verified role route references, dynamic permission constraints, status codes, and bodyless actions. The full Laravel suite passed (108 tests, 1,740 assertions) with the in-memory SQLite test database.
- **Limitations:** Permission strings are runtime-configured by base role and required core/dependency permissions remain server-validated. Other workspace endpoints still use generic JSON schemas.
- **Next:** Continue explicit schemas for the remaining generic workspace modules and complete the release/accessibility audits.

## Milestone 0.188 — Specify workspace feedback API contracts
- **Finding:** Feedback endpoints validated category, message, status, and response fields and deliberately withheld submitter names from member responses, but OpenAPI still described these operations as generic JSON objects.
- **Fix:** Added explicit store/update request schemas, submitter and manager response variants, bounded list responses, nullable response timestamps, and route-specific 201 creation status. Added schema assertions for request fields and the separation between member and manager payloads.
- **Verification:** `php artisan openapi:export` and `php artisan openapi:export --check` passed; PHP syntax checks passed for the exporter and contract test; an independent JSON assertion checked request/response route references, validation bounds, and that member item schema omits submitter names. The full Laravel suite passed (108 tests, 1,740 assertions) with the in-memory SQLite test database.
- **Limitations:** Most other workspace API modules still contain generic response/request schemas. Hosted Render validation, Docker build, and production integrations remain unverified.
- **Next:** Specify feedback-notification response contracts and continue remaining API and accessibility audits.

## Milestone 0.187 — Align the free Render blueprint with supported deployment behavior
- **Finding:** The blueprint selected Render's free web-service plan while specifying `preDeployCommand`, which Render only supports on paid web services. Its health check also used liveness even though an operation-critical database readiness route exists.
- **Fix:** Pointed Render health checks at `/api/v1/health/ready` and removed the unsupported pre-deploy command. Documented the required reviewed, backed-up manual migration operation for free-plan deploys and the paid-plan choice needed for automated pre-deploy migrations.
- **Verification:** Render YAML parse and structural checks passed. `php artisan route:list --path=api/v1/health --json` confirms the liveness and readiness routes are registered. The readiness route has feature coverage for `200` after a minimal database query and `503` on database failure. Rechecked local auth config (`APP_ENV=local`, `auth.require_verified_email=false`). The sandboxed PHPUnit process cannot pass its own `is_readable` bootstrap check; the full suite subsequently passed using the isolated in-memory SQLite config in an approved run. Render hosted validation and Docker build remain unverified.
- **Limitations:** No hosted Render deployment was created. Free-plan migration execution remains an explicit operator step; provider backups and staging smoke tests are still required.
- **Next:** Validate the deployment pipeline in CI or a no-cost preview account when repository hosting is connected; then continue the remaining field-level OpenAPI coverage and accessibility/release audits.

## Milestone 0.186 — Defer the authenticated workspace bundle
- **Finding:** The public homepage and sign-in path downloaded the full 454 kB application entry, including all workspace dashboards and ERP/LMS screens before a user authenticated.
- **Fix:** Split the authenticated workspace shell and its panels into a lazy-loaded module. The public/auth bootstrap now loads that module only after a valid session is established; existing organization switching, sign-out, and profile-update callbacks are passed through. Added a PWA regression that verifies the deferred workspace asset is still precached for offline availability.
- **Verification:** Production build passed; the largest initial entry chunk fell from 454.42 kB to 216.34 kB (gzip 119.45 to 68.12 kB), with a separate 236.16 kB workspace chunk. Vitest passed (12 tests); all Chromium tests passed (26); PWA offline test passed (1) and confirmed the workspace chunk is cached.
- **Limitations:** The workspace module still contains multiple large panels in one deferred chunk. This is loaded after authentication; further splitting should be based on route-level measurements.
- **Next:** Continue the remaining end-to-end ERP/LMS requirements and release-readiness work; prioritize confirmed functional gaps over further bundle splitting.

## Milestone 0.185 — Add guardian homework visibility
- **Finding:** The guardian portal could show attendance, course progress, and results, but omitted the homework status explicitly required by the parent portal workflow.
- **Fix:** Added a capability-gated, linked-learner homework projection using published assignments in active course enrollments, including due date and submitted/graded/not-submitted state. Added the view to the guardian dashboard and CSV, documented its scope, and specified the response in OpenAPI.
- **Verification:** Full Laravel SQLite suite passed (108 tests, 1,719 assertions); guardian capability/OpenAPI focused suite passed (13 tests, 271 assertions); generated OpenAPI `--check` passed; Vitest passed (12 tests); `npm run build` passed; targeted guardian Chromium journey passed at 320, 375, 430, 768, 1024, and 1440 px (1 test). The build's initial JS chunk is 454.42 kB.
- **Limitations:** The guardian sees at most eight published assignments that are not overdue; submission text, attachments, and scores are not exposed.
- **Next:** Continue with the highest-priority incomplete ERP/LMS requirements and deployment readiness work.

## Milestone 0.184 — Specify schedule exception and live-session contracts
- **Finding:** Schedule exceptions and live-session operations enforced domain validation and tenant/audience authorization, but their OpenAPI routes exposed generic JSON payloads and inaccurate default success responses.
- **Fix:** Added list/detail/request schemas for schedule exceptions and live sessions, including date/time fields, conditional audience requirements, live-session status transitions, URL handling, roster records, and complete-roster attendance. Mapped route-specific responses and creation statuses, documented the academic-year filter, and asserted the generated contracts.
- **Verification:** Full Laravel SQLite suite passed (108 tests, 1,706 assertions); focused OpenAPI test passed (1 test, 144 assertions); generated contract `--check` and PHP syntax checks passed.
- **Limitations:** Conditional business rules, teacher audience scope, and exact roster completeness remain enforced at runtime. Provider-verified conferencing presence is not integrated.
- **Next:** Continue role-capability and tenant-scope audits, then add explicit schemas for remaining workspace modules.

## Milestone 0.183 — Specify teacher profile and member-link contracts
- **Finding:** Teacher profile creation/listing, manager-controlled member linking, and self-managed public profile operations had real authorization and privacy behavior but incomplete OpenAPI response/request shapes.
- **Fix:** Added explicit schemas and route mappings for teacher records and active lists, unlinked member candidates, profile creation, nullable member link/unlink, and a teacher's public profile update. Profile status accepts active or inactive because the manager link endpoint can return an inactive profile; list results remain active-only. Public fields are nullable in listings for profiles other than the caller's own.
- **Verification:** Full Laravel suite passed (108 tests, 1,694 assertions); focused OpenAPI test passed (1 test, 132 assertions); generated contract `--check` and PHP syntax checks passed.
- **Limitations:** Ownership checks, active member eligibility, and per-profile public-field redaction remain runtime authorization rules; the schemas do not grant access.
- **Next:** Continue capability, tenant-scope, and accessibility audits.

## Milestone 0.182 — Restore local sign-in preview
- **Finding:** The local API already disables email verification (`APP_ENV=local`, `PAATHNEXUS_REQUIRE_EMAIL_VERIFICATION=false`), and the sign-in UI hides verification controls unless explicitly enabled. The local SQLite database currently has zero user accounts, so old credentials cannot authenticate.
- **Fix:** Cleared Laravel's configuration cache, confirmed the runtime verification setting is off, and reused the existing PaathNexus browser preview on port 5174. No verification-flow change was needed.
- **Verification:** `npm run build` passed. Chrome showed the sign-in form without a verification prompt or resend link; local registration signs in without an email link. Account-count inspection returned zero.
- **Limitations:** No local user or database backup was found, so there are no saved credentials to share. Create an account from the sign-up screen to use the local preview; local registration signs in without an email link. Restore the database from OneDrive version history if the prior account is needed.
- **Next:** Continue academic API contract coverage and capability/data-scope audits.

## Milestone 0.181 — Specify teacher assignments and timetable contracts
- **Finding:** Class-teacher assignments, assignment history, subject offerings, and timetable operations exposed role-scoped payloads and scheduling constraints through generic OpenAPI schemas.
- **Fix:** Added schemas for teacher assignment lists/details/history, active subject offerings and end responses, timetable list/create/update payloads, and their requests. Documented academic-year and weekday filters, time formats, section-specific assignment data, and the subject or subject-ID requirement.
- **Verification:** Full Laravel suite passed (108 tests, 1,684 assertions); focused OpenAPI contract test passed (1 test, 122 assertions); generated contract `--check` and PHP syntax check passed.
- **Limitations:** Class/teacher overlap, subject-offering, and teacher-section scope checks remain enforced at runtime. Teacher profile and member-link responses remain generic.
- **Next:** Add contracts for remaining teacher profile and subject offering setup operations, then continue capability and tenant-scope audits.

## Milestone 0.180 — Specify academic year, section, and subject contracts
- **Finding:** Academic-year activation, scoped section listings, and subject catalog APIs had stable response shapes but remained generic in the route contract.
- **Fix:** Added list/detail schemas and request contracts for academic years, sections, and subjects, including date ordering descriptions, status enums, active-only list semantics, and section-to-class references.
- **Verification:** Full Laravel suite passed (108 tests, 1,668 assertions); focused OpenAPI contract test passed (1 test, 106 assertions); generated contract `--check` and PHP syntax check passed.
- **Limitations:** The server still enforces unique names/codes, workspace references, and teacher scope; those dynamic checks cannot be represented completely in the shared schemas. Teacher assignments and timetable responses remain generic.
- **Next:** Add contracts for remaining teacher profile and subject offering setup operations, then continue capability and tenant-scope audits.

## Milestone 0.179 — Specify academic rosters and attendance contracts
- **Finding:** Class and student rosters, class enrollment, and attendance sessions were core academic workflows with teacher/section-scoped payloads but no explicit OpenAPI contracts.
- **Fix:** Added schemas for class lists and rosters, active student lists and creation, class enrollment, bounded attendance history, legacy attendance summaries, and complete class attendance writes. Documented student identity fields and per-record attendance statuses.
- **Verification:** Full Laravel suite passed (108 tests, 1,659 assertions); focused OpenAPI contract test passed (1 test, 97 assertions); generated contract `--check` and PHP syntax check passed.
- **Limitations:** Teacher class and section scopes are enforced by existing server-side query scopes and policies; shared OpenAPI shapes do not grant access. Academic year, timetable, and remaining academic response contracts are not yet explicit.
- **Next:** Specify teacher assignment and timetable response contracts, then continue capability and tenant-scope audits.

## Milestone 0.178 — Specify guardian links and child-summary contracts
- **Finding:** Guardian membership candidates, child links, revocation, and child summary APIs returned sensitive and capability-filtered shapes that were not explicitly described in OpenAPI.
- **Fix:** Added schemas for guardian candidates and link management, relationship request enums, link reactivation responses, link revocation, and the guardian child summary. Attendance, learning, and results projections remain optional in the child shape because the API omits each projection when its capability is absent.
- **Verification:** Full Laravel suite passed (108 tests, 1,646 assertions); focused OpenAPI contract test passed (1 test, 84 assertions); generated contract `--check` and PHP syntax check passed.
- **Limitations:** The schemas describe the shared envelope; server-side guardian-link ownership and independent capability checks remain authoritative. Other academic and administrative workflows still need explicit contracts.
- **Next:** Specify academic-year, section, subject, teacher assignment, and timetable response contracts, then continue capability and tenant-scope audits.

## Milestone 0.177 — Specify enrollment and student study-plan contracts
- **Finding:** Course enrollment, private study-plan, and learner-account linking endpoints still exposed generic OpenAPI payloads despite having distinct request and response shapes.
- **Fix:** Added schemas for active course enrollment lists and creation, the student's bounded study-plan list/create/complete flow, student member candidates, and profile-member linking. Added request schemas for enrollment, future scheduled study plans, and nullable member unlinking.
- **Verification:** Full Laravel suite passed (108 tests, 1,638 assertions); focused OpenAPI contract test passed (1 test, 76 assertions); generated contract `--check` and PHP syntax check passed.
- **Limitations:** Enrollment and student linking remain subject to server-side workspace membership, course scope, and academic permissions; student study plans remain private to the authenticated linked learner. Other workspace contracts still need coverage.
- **Next:** Cover remaining academic and administrative response contracts, then continue capability and tenant-scope audits.

## Milestone 0.176 — Specify course, lesson, and progress contracts
- **Finding:** Course and lesson authoring, instructor assignment, prerequisite display, and learner progress operations returned stable, role-scoped payloads without concrete OpenAPI request/response schemas.
- **Fix:** Added course list/detail, instructor, lesson list/detail, course progress, and lesson progress schemas. Added request contracts for course create/update, instructor replacement, lesson create/update, and lesson progress updates, including HTTPS resource URLs, prerequisite uniqueness, and the optional manager-supplied learner ID.
- **Verification:** Full Laravel suite passed (108 tests, 1,628 assertions); focused OpenAPI contract test passed (1 test, 66 assertions); generated contract `--check` and PHP syntax check passed.
- **Limitations:** Learner versus manager access is enforced by server policies and query scopes; OpenAPI describes shared response shapes and does not replace those checks. Enrollment and remaining learning payloads still need dedicated schemas.
- **Next:** Continue OpenAPI coverage for remaining academic, guardian, and administrative workflows, then proceed with capability and data-scope audits.

## Milestone 0.175 — Specify assignment and submission contracts
- **Finding:** Assignment authoring, learner submissions, staff review, grading, and attachment upload returned distinct payloads, but their OpenAPI operations used generic schemas.
- **Fix:** Added explicit response schemas for assignment lists/details, learner submissions, submission review rows, grading responses, and attachments. Added request contracts for assignment creation, text or multipart file submissions, grading, and resource uploads. Documented returned-nullable staff counts, optional learner submissions, list-only learner identity, and date validation behavior.
- **Verification:** Full Laravel suite passed (108 tests, 1,613 assertions); focused OpenAPI contract test passed (1 test, 51 assertions); generated contract `--check` and PHP syntax check passed.
- **Limitations:** The maximum grading score depends on the selected assignment and remains enforced by the API at runtime. Other workspace payloads still need dedicated schemas.
- **Next:** Add enrollment and remaining learning response contracts, then proceed with capability and data-scope audits.

## Milestone 0.174 — Specify assessment authoring and results contracts
- **Finding:** Assessment operations exposed generic response bodies in OpenAPI despite returning multiple distinct shapes for authoring, attempts, analytics, and student results.
- **Fix:** Added schemas for assessment summaries, attempt summaries/lists, analytics, student answer/result envelopes, and in-progress attempt questions. Documented date/student filters for analytics, attempt lists, and grade exports. Student result and in-progress question schemas intentionally omit answer-key correctness fields.
- **Verification:** Full Laravel suite passed (108 tests, 1,607 assertions); focused OpenAPI contract test passed (1 test, 45 assertions); exporter `--check` and PHP syntax check passed.
- **Limitations:** Question bank and grading request/response schemas and other learning module endpoints remain incomplete; server-side assessment capabilities and teacher/student scopes are still authoritative.
- **Next:** Specify course/lesson and learning-progress response schemas, then continue capability/data-scope audits.

## Milestone 0.173 — Specify academic report response and filters
- **Finding:** The academic report exposes workspace or teacher-assigned summaries, learner rows, and several assessment rollups, but its OpenAPI operation had no concrete response schema or filter types.
- **Fix:** Added schemas for report summary metrics, nullable percentages, score bands, monthly trends, per-assessment/course aggregates, individual student rows, scope, class choices, and applied filters. Documented UUID class/student filters and inclusive date query parameters.
- **Verification:** Full Laravel suite passed (108 tests, 1,600 assertions); focused OpenAPI contract test passed (1 test, 38 assertions); exporter `--check` and PHP syntax check passed.
- **Limitations:** The report's tenant and teacher class/course scoping is enforced in the API and separately covered by `AcademicReportTest`; other academic module contracts remain incomplete.
- **Next:** Add an explicit response schema for assessment authoring/results endpoints, then continue capability/data-scope audits.

## Milestone 0.172 — Specify the learner dashboard response
- **Finding:** The learner dashboard combines enrollment progress, private certificates, upcoming work, study suggestions, assessment outcomes, schedule details, and attendance summaries, but its API contract was generic.
- **Fix:** Added schemas for the learner dashboard envelope and nested course/certificate, assignment, recommendation, assessment-result, schedule, and attendance records. The contract explicitly represents missing linked profiles, nullable scores/dates, capability-filtered results, and bounded dashboard lists. Regression checks the route response ref and key nested fields.
- **Verification:** Full Laravel suite passed (108 tests, 1,596 assertions); focused OpenAPI contract test passed (1 test, 34 assertions); exporter `--check` and PHP syntax check passed.
- **Limitations:** Other tenant module request/response bodies remain generic and are documented in the API specification.
- **Next:** Add explicit request/response schemas for remaining high-traffic academic endpoints, then continue capability/data-scope audits.

## Milestone 0.171 — Specify the workspace dashboard response
- **Finding:** The primary workspace dashboard returned several capability-filtered count, attendance, class, and schedule values, but the generated API contract exposed only a generic response object.
- **Fix:** Added a dashboard response schema covering nullable hidden counts, attendance summaries and series, class student counts, schedule entries, closure notices, weekday, and active academic year. Collection bounds and date/time formats reflect the controller output; the OpenAPI test checks the response reference and nullable count shape.
- **Verification:** Full Laravel suite passed (108 tests, 1,593 assertions); focused OpenAPI contract test passed (1 test, 31 assertions); exporter `--check` and PHP syntax check passed.
- **Limitations:** Other tenant module response schemas remain incomplete; dashboard values continue to follow server-side capability and teacher-assignment scopes.
- **Next:** Add response schemas for additional high-traffic tenant endpoints, starting with the student dashboard, then continue capability/data-scope audits.

## Milestone 0.170 — Specify workspace search response contract
- **Finding:** Workspace search is a shared tenant-scoped feature, but the machine-readable contract represented its polymorphic result list only as a generic response object.
- **Fix:** Added the OpenAPI search response envelope and result schema, including all seven result categories, mixed integer/UUID identifiers, required titles/subtitles, and the 20-result bound. The query contract specifies the 2–80 character `q` constraint. Regression coverage checks the response ref, categories, query constraints, and tenant bearer requirement.
- **Verification:** Full Laravel suite passed (108 tests, 1,590 assertions); focused OpenAPI contract test passed (1 test, 28 assertions); export consistency passed.
- **Limitations:** Other tenant module responses still mostly use generic schemas. Search visibility and tenant scope remain enforced and tested in the service/controller.
- **Next:** Add a response schema for the primary workspace dashboard, then continue capability/data-scope audits.

## Milestone 0.169 — Document auth and public catalog response schemas
- **Finding:** OpenAPI request schemas were improving, but sign-in and public catalog clients still received generic response descriptions and string-typed pagination filters.
- **Fix:** Added response schemas for auth sessions, current-user data, conditional email-verification results, generic auth messages, public course listings and instructor summaries, and subscription plans. Authentication and catalog operations now reference their concrete response envelopes. Public catalog, workspace search, organization admin, audit-log, and usage-history query parameters declare appropriate types and limits.
- **Verification:** Full Laravel suite passed (108 tests, 1,587 assertions); focused OpenAPI contract test passed (1 test, 25 assertions); generated contract `--check` and PHP syntax checks passed.
- **Limitations:** Most workspace module request and response bodies remain generic in OpenAPI and are detailed in `docs/API_SPECIFICATION.md`.
- **Next:** Add response schemas for major tenant-scoped workspace endpoints, then continue capability and data-scope audits.

## Milestone 0.168 — Specify authentication request schemas in OpenAPI
- **Finding:** The generated contract described authenticated request fields only as generic objects, including sign-in and account creation payloads.
- **Fix:** Added explicit OpenAPI request schemas for sign-in, workspace registration, invitation registration, email-only verification/reset requests, and password reset, including required fields, limits, password policy descriptions, and organization-type choices. Authentication request bodies are marked required; the route drift regression checks sign-in schema and registration enum values.
- **Verification:** Full Laravel suite passed (108 tests, 1,577 assertions); focused OpenAPI test passed (1 test, 15 assertions); exporter `--check` and PHP lint passed.
- **Limitations:** Most non-auth request/response payloads remain generic schemas; detailed endpoint rules remain in `docs/API_SPECIFICATION.md`.
- **Next:** Add detailed response and public catalog schemas to the OpenAPI contract, then continue capability/data-scope audits.

## Milestone 0.167 — Verify platform usage history visualization
- **Finding:** The platform usage browser fixture only checked that the history chart and table appeared; it did not verify that bars represented different daily counts or that the table returned exact records in newest-first order.
- **Fix:** Expanded the synthetic usage-history fixture to contain distinct active-workspace totals and added assertions for accessible chart text, each bar's date/count label, all eight table columns, descending dates, and exact active counts.
- **Verification:** The focused platform-usage Chromium test passed; the full Chromium suite passed (26 tests) against the updated regression.
- **Limitations:** Usage snapshots remain count-only and begin after the scheduler first captures a snapshot; there is no historical backfill.
- **Next:** Continue capability/data-scope and accessibility audits, then validate PostgreSQL and deployment configuration when hosted environments are available.

## Milestone 0.166 — Publish a generated OpenAPI contract
- **Finding:** The API had no machine-readable route contract, making it difficult for API clients and reviewers to discover implemented endpoints and authentication requirements.
- **Fix:** Added `php artisan openapi:export` and `--check`, generated `docs/openapi.json` from the registered Laravel routes, and clarified summaries for sign-in, workspace search, and health checks. The contract includes bearer-token security, path/query parameters, generic request/response schemas, and multipart assignment attachments. Added regression coverage for route drift, unique operation IDs, public sign-in, protected search, and readiness health.
- **Verification:** Full Laravel suite passed (108 tests, 1,573 assertions); focused OpenAPI test passed (1 test, 11 assertions); exporter `--check` and PHP lint passed; Vitest passed (12 tests); `npm run build` and the 26-test Chromium suite passed.
- **Limitations:** OpenAPI request and response schemas are structural; field-level validation and authorization detail remain in `docs/API_SPECIFICATION.md`. Hosted PostgreSQL and staging validation remain pending.
- **Next:** Continue release-readiness and capability/data-scope audits, then validate PostgreSQL and deployment configuration when hosted environments are available.

## Milestone 0.165 — Add database readiness health check
- **Finding:** The public `/health` endpoint reported process liveness only; deployments had no separate signal that database-backed requests could be served.
- **Fix:** Added public `/health/ready` to execute a minimal database probe. It returns a generic `503 not_ready` response when the connection fails and never exposes driver errors or connection details. `/health` remains database-independent for liveness. Documented the separation in the API, deployment checklist, and decision log; the Render blueprint continues using liveness.
- **Verification:** Full Laravel suite passed (107 tests, 1,562 assertions); focused organization-access tests passed (16 tests, 426 assertions); Vitest passed (12 tests); production build and PHP syntax checks passed. The 26 Chromium tests and PWA offline test passed earlier in this execution.
- **Limitations:** The readiness probe checks database connectivity only. Queue, storage, mail, AI, and other external dependencies are not probed. Hosted PostgreSQL/Render validation remains pending.
- **Next:** Continue release-readiness and capability/data-scope audits; validate PostgreSQL and deployment configuration when hosted environments are available.

## Milestone 0.164 — Search scoped attendance history
- **Finding:** Attendance history was not indexed in workspace search, and the attendance page did not show the history rows returned by its API.
- **Fix:** Extracted attendance session visibility into a shared scope used by the session index and workspace search. Search matches class names or exact ISO dates only for users with both `attendance.read` and `academic.view`, and applies the same active teacher class/section/course assignments and latest-30 result window. The Attendance page now shows recent sessions and authorized per-learner marks.
- **Verification:** Full Laravel suite passed (106 tests, 1,557 assertions); focused workspace search passed (7 tests, 73 assertions); Vitest passed (12 tests); TypeScript/production build and PHP syntax checks passed; all 26 Chromium Playwright tests passed on isolated port 5180; the production-preview offline PWA test passed (1 test).
- **Limitations:** Search matches attendance class names and exact `YYYY-MM-DD` dates; results are limited to the same 30 latest scoped sessions shown in attendance history. Cross-browser and physical-device checks remain pending.
- **Next:** Continue release readiness by running CI against PostgreSQL and validating deployment configuration when hosted environments are available.

## Milestone 0.163 — Search visible workspace announcements
- **Finding:** Workspace search omitted announcement records, so members could not find notices by their title or message.
- **Fix:** Search now indexes announcement titles and bodies within the active workspace. It follows the announcement listing's audience and status rules: members see published notices for all members or their own audience, while academic managers can also find scheduled and withdrawn items. Selecting a result opens the manager announcement view or a member's notifications panel.
- **Verification:** Full Laravel suite passed (105 tests, 1,545 assertions); the focused workspace-search suite passed (6 tests, 61 assertions); Vitest passed (12 tests); `npm run build` passed. The new Playwright check could not start because the existing dev server occupies port 5174 and the separate-port server did not become reachable in this execution environment.
- **Limitations:** Member result selection opens the recent notifications panel, which contains up to 100 items.
- **Next:** Continue PostgreSQL and release-readiness verification when the required local or hosted environment is available.

## Milestone 0.162 — Add opt-in local demo workspaces
- **Finding:** The local SQLite database has no accounts, while the old default seeder created a generic account with a predictable password. That did not provide safe, usable demo sign-in.
- **Fix:** Removed the generic seeded account and added an idempotent synthetic school and educator demo seed. It runs only when explicitly enabled in `APP_ENV=local`, requires a private environment-supplied password, and refuses to seed if that password is missing or weak. The README documents the demo emails and setup without storing a password.
- **Verification:** Full Laravel suite passed (104 tests, 1,530 assertions). The focused seeder suite passed (4 tests, 22 assertions). A complete fresh migration and seed succeeded against isolated in-memory SQLite.
- **Limitations:** The persistent local database was not seeded because no password was provided. Existing account passwords cannot be recovered. Hosted PostgreSQL validation remains unavailable without the repository connection.
- **Next:** Local sign-in is configured to skip email verification. Restart the API after configuration changes; for a fresh demo login, opt in to the documented seed and choose its password. Continue release-readiness work and hosted PostgreSQL validation when repository access is available.

## Milestone 0.161 — Cache the production PWA entry screens
- **Finding:** The service worker cached the HTML entry and initial bundle, but React's lazily loaded authentication chunk was missing after an offline reload, leaving the app on its loading fallback.
- **Fix:** The production worker now reads the built entry bundle, precaches referenced split JS/CSS chunks, serves cached app assets offline, and falls back to the cached root document. API requests remain network-only. The frontend CI builds before running the production-preview PWA smoke test.
- **Verification:** `npm run build` passed; all frontend tests passed (12); the standard Chromium suite passed 24 tests and the production-preview PWA Chromium test passed, including offline reload of the current tab.
- **Limitations:** Only the application shell and built assets work offline; authenticated workspace data and API mutations require a connection. Device install prompts, OS integration, and physical-device offline behavior remain unverified.
- **Next:** Connect the repository to run the PostgreSQL CI job. Validate deployment configuration only when an approved staging environment is available.

## Milestone 0.160 — Assert the CI database driver
- **Finding:** The PostgreSQL CI job set PostgreSQL connection variables, but no test proved Laravel/PHPUnit actually used that driver instead of silently falling back to SQLite.
- **Fix:** Added a feature assertion comparing Laravel's active database driver with `PAATHNEXUS_EXPECT_DB_CONNECTION`. Both CI API jobs now set this expectation explicitly, so the PostgreSQL job fails if test bootstrap selects another driver.
- **Verification:** Full Laravel suite passed with the SQLite driver (100 tests, 1,508 assertions). A deliberate mismatch probe confirmed the new assertion fails when PostgreSQL is expected but SQLite is active. A local PostgreSQL server and connected PaathNexus GitHub repository are unavailable here.
- **Limitations:** PostgreSQL migration and feature compatibility still need the hosted CI job to run.
- **Next:** Connect the PaathNexus repository and inspect the PostgreSQL workflow result.

## Milestone 0.159 — Search active lesson content
- **Finding:** Workspace search could find a course by its title or code but could not locate courses from a lesson title or its learning text.
- **Fix:** Course matches now include active lesson title/body matches. Results open the scoped course and identify the matching lesson. Draft lessons are excluded; student and guardian search remains restricted to actively enrolled courses, while teacher course queries retain the existing teacher scope.
- **Verification:** Full Laravel suite passed in isolated in-memory SQLite (99 tests, 1,507 assertions); all frontend tests passed (12); production build passed; the full Chromium suite passed all 24 tests, including the educator course-search journey.
- **Limitations:** Lesson and announcement matching use case-insensitive substring search and are not full-text search. Attendance is not indexed.
- **Next:** Continue release-readiness work, including reviewing the configured PostgreSQL CI job in a connected repository and completing the pending hosted validation.

## Milestone 0.158 — Include fee invoices in workspace search
- **Finding:** Members had to open Fee invoices and search manually; the workspace search did not index invoice identifiers or titles.
- **Fix:** Workspace search now returns fee invoice number, title, and student code for members with `fees.view`. Student results are restricted to their own learner profile, while guardian results are restricted to actively linked learners; all queries are organization-scoped.
- **Verification:** Full Laravel suite passed in isolated in-memory SQLite (98 tests, 1,500 assertions); all frontend tests passed (12); production build passed; the full Chromium suite passed all 24 tests.
- **Limitations:** Lesson bodies are indexed by later milestone 0.159, announcement title/body by milestone 0.163, and attendance is still not indexed. Results remain capped at 20 across record types.
- **Next:** Run the complete Laravel, frontend, build, and Chromium suites; then continue release-readiness work and validate the PostgreSQL deployment path in CI.

## Milestone 0.157 — Export all academic report summaries
- **Finding:** The academic report showed score bands, monthly trends, and assessment/course summaries, but its CSV contained only student rows.
- **Fix:** The CSV now includes typed rows for student metrics, score bands, monthly assessment aggregates, per-assessment results, and per-course rollups, with shared filter scope and formula-safe cell encoding.
- **Verification:** Full Laravel suite passed in isolated in-memory SQLite (97 tests, 1,484 assertions); all frontend tests passed (12); production build passed; the full Chromium suite passed all 24 tests, including checks for the exported aggregate rows.
- **Limitations:** Consumers should use the `Record type` column to distinguish row shapes in the single CSV file.
- **Next:** Continue release-readiness and remaining ERP/LMS work; define academic-period and cohort semantics before implementing cohort comparisons.

## Milestone 0.156 — Summarize assessment results by course
- **Finding:** Reports listed assessment results separately but did not provide a direct course-level comparison.
- **Fix:** Added a course rollup showing each course's completed attempt count and attempt-weighted average score in the report API and accessible UI.
- **Security:** Rollups clone the same scoped assessment query and join courses by organization and course ID; teacher results remain limited to assigned learners and accessible courses.
- **Verification:** Full Laravel suite passed in isolated in-memory SQLite (97 tests, 1,484 assertions); all frontend tests passed (12); production build passed; the full Chromium suite passed all 24 tests, including responsive report, print, CSV, and local verification-screen coverage.
- **Limitations:** Rollups cover completed attempts in the selected report filters; term/cohort comparisons and trend comparisons between courses are not implemented.
- **Next:** Add term/cohort comparisons only after defining stable academic-period and cohort semantics; continue other ERP/LMS readiness work meanwhile.

## Milestone 0.155 — Break down results by assessment
- **Finding:** Workspace and teacher reports aggregated assessment results across all assessments, making it difficult to identify which assessment needed follow-up.
- **Fix:** Added a per-assessment summary with course, assessment, attempt count, and average score to the report API and accessible report UI.
- **Security:** The breakdown clones the same filtered attempt query and joins assessment/course records on organization and course IDs. Teacher course, assigned learner/class, tenant, review/status, and date scopes remain applied.
- **Verification:** Full Laravel suite passed in isolated in-memory SQLite (97 tests, 1,474 assertions); all frontend tests passed (12); production build passed; Playwright discovers all 24 tests, and the focused academic-report Chromium journey passed, including mobile overflow checks, print mode, and CSV export.
- **Limitations:** Results are grouped by assessment within each course; result comparisons across separate cohorts remain future work.
- **Next:** Define stable academic-period and cohort semantics before adding cross-cohort comparison, then continue release readiness and remaining ERP/LMS work.

## Milestone 0.154 — Show monthly assessment trends
- **Finding:** The academic report had an overall average and score distribution but did not show how completed assessment performance changed over time.
- **Fix:** Added monthly attempt counts and attempt-weighted average percentages grouped by submission month. The UI renders a semantic, accessible trend table alongside the score bands.
- **Security:** The trend query clones the report's existing assessment-attempt scope, including tenant, learner/class, teacher-accessible course, status/review, and date filters. Month grouping has SQLite and PostgreSQL expressions, with a MySQL-compatible fallback.
- **Verification:** Full Laravel suite passed in isolated in-memory SQLite (97 tests, 1,462 assertions); all frontend tests passed (12 tests); production build passed; Playwright discovers all 24 browser tests. Browser execution remains pending because the focused report run did not reach web-server readiness.
- **Limitations:** Averages are grouped by submission month across the selected scope; per-assessment and course-level breakdowns remain future work.
- **Next:** Add an assessment-level breakdown while preserving each teacher's assigned course scope.

## Milestone 0.153 — Add scoped assessment score distribution
- **Finding:** Academic reports showed only the average assessment result, which hid how completed graded attempts were distributed across score ranges.
- **Fix:** Added counts for `<60%`, `60–74%`, `75–89%`, and `90%+` attempts to the report API and an accessible range/count/share table to the academic report. The distribution uses the report's existing active organization, learner/class, teacher-accessible course, review-status, completion, and date filters.
- **Security:** Counts are aggregated from the same tenant- and teacher-scoped query used for individual assessment metrics; no additional learner identifiers are returned.
- **Verification:** Full Laravel suite passed in isolated in-memory SQLite (97 tests, 1,449 assertions); all frontend tests passed (12 tests); production build passed; Playwright discovers all 24 browser tests, including the updated academic-report journey. A focused report browser run did not reach Playwright output before the web-server readiness wait was cancelled at about 60 seconds.
- **Limitations:** Score bands aggregate across the selected report scope; per-assessment and time-series breakdowns remain future work.
- **Next:** Add an assessment-level trend view that preserves the existing tenant and teacher course scopes, then continue release readiness.

## Milestone 0.152 — Report reconciled collections by received date
- **Finding:** Fee overview totals were all-time only, so managers could not isolate reconciled money received during a selected period.
- **Fix:** Added a manager-only, tenant-scoped collections endpoint with inclusive `received_at` date bounds in the workspace timezone, paginated payment rows, and aggregate amount/count totals. The UI has a separate collections report, defaults to the current workspace month, and can export every payment in that range to formula-safe CSV. All-time invoice balances remain separate and clearly labeled. Additive migration `.051` adds the organization/received-date index; it was applied to local SQLite without changing ledger rows.
- **Security:** The report requires `academic.manage`; the query scopes payments to the active organization before aggregation or pagination and returns only receipt/invoice/student summary fields.
- **Verification:** Focused fee API tests passed (6 tests, 79 assertions); full Laravel suite passed in isolated in-memory SQLite (97 tests, 1,439 assertions). All frontend tests passed (12 tests); production build passed; Playwright discovers all 24 browser tests. Browser execution remains pending due to local Playwright networking.
- **Limitations:** The collections report covers recorded payments only; live gateway settlement, refunds, and accounting-system integration remain unavailable.
- **Next:** Run the fee collections browser journey when local networking permits, then continue release readiness and the remaining ERP/LMS backlog.

## Milestone 0.151 — Paginate and filter fee invoices
- **Finding:** The fee API capped results at the latest 200 invoices, preventing larger workspaces from reviewing or exporting older invoices.
- **Fix:** Added validated API pagination (25 per UI page, up to 100 per request) and inclusive invoice-issued date filters interpreted in the workspace timezone. The tenant/member visibility scope is applied before pagination. The invoice screen now has date filters, page navigation, result totals, and CSV export that fetches every page matching the selected issue-date range.
- **Verification:** Focused fee API tests passed (5 tests, 67 assertions); full Laravel suite passed in isolated in-memory SQLite (96 tests, 1,427 assertions). All frontend tests passed (12 tests); production build passed; Playwright lists all 24 browser tests. A browser journey now covers paging, date filters, and multi-page export but could not be executed because local Playwright networking did not complete.
- **Limitations:** Date filters select invoices by issue date; payment receipt dates and all-time summary cards are not date-filtered. Pagination uses offset pages.
- **Next:** Run the invoice browser journey when local networking permits; continue financial reporting and release readiness.

## Milestone 0.150 — Export visible invoices and payments to CSV
- **Finding:** Fee invoices and reconciled receipts could be viewed and printed, but there was no structured export for family records or workspace bookkeeping.
- **Fix:** Added an invoice CSV download with invoice totals, balances, due dates, statuses, and one row per reconciled payment including receipt number, method, transaction reference, and timestamp. Invoices without payments still produce a row. The export uses the existing formula-safe CSV encoder and the API's role-scoped invoice list, so learner/guardian exports stay limited to records already visible to that user.
- **Verification:** Full frontend suite passed (12 tests); production build passed. Added a Chromium download assertion to the fee invoice journey, and Playwright lists all 24 browser tests, but execution remains unavailable due to local server/networking issues.
- **Limitations:** The API returns at most the latest 200 invoices, and export contains that list; date-range filtering, pagination, accounting-system integrations, payment reconciliation imports, and server-generated PDFs remain future work.
- **Next:** Re-run the fee-invoice browser journey when Playwright can reach the local app; continue reporting and release-readiness work.

## Milestone 0.149 — Print and save reconciled fee receipts
- **Finding:** Invoice payment rows exposed receipt numbers and references, but families and staff could not produce a receipt document for a recorded payment.
- **Fix:** Each reconciled payment now opens a responsive, isolated receipt view with the workspace, learner, invoice, payment amount, method, reference, and receipt number. The browser print dialog can print it or save it as PDF. Receipt fields are HTML-escaped before rendering.
- **Verification:** `npm run build` passed. Added a Chromium regression for opening a receipt and checking its receipt number and payment reference; the test could not complete because the existing 5174 dev server conflicted with Playwright's server and its alternate-port run did not respond under current sandbox networking.
- **Limitations:** PDF output uses the browser print/save dialog; the application does not create a standalone PDF file or send receipts by email.
- **Next:** Run the focused receipt browser test when local Playwright networking is available; continue release hardening and the remaining product backlog.

## Milestone 0.148 — Keep email verification UI opt-in
- **Finding:** A production-mode frontend preview forced the verification screen even when the local API had verification disabled and no inbox delivery was configured.
- **Fix:** The authentication screen now shows verification UI only when `VITE_REQUIRE_EMAIL_VERIFICATION=true`; an unset flag keeps the local sign-in flow free of the resend step.
- **Verification:** `auth.require_verified_email` resolves to false in the local Laravel config and `npm run build` passed.
- **Limitations:** The local SQLite database currently contains zero users and organizations; this UI change does not recover the previously used account.
- **Next:** Restore local account data from OneDrive history if available, then confirm sign-in in the browser.

## Milestone 0.147 — Capture and review historical platform usage
- **Finding:** Platform administration showed only current aggregate counts, so operators could not see how workspace, membership, learner, course, or subscription totals changed over time.
- **Fix:** Added one idempotent daily aggregate snapshot at 23:55 UTC, a 7–365 day allowlist-protected history API, and an accessible chart/table in Platform administration. Snapshots contain counts only; they do not store workspace, member, or learner identifiers. History begins after the scheduler captures its first snapshot and is not backfilled.
- **Verification:** Snapshot feature tests passed (2 tests, 13 assertions); full Laravel suite passed in isolated in-memory SQLite (95 tests, 1,413 assertions); Vitest passed (10 tests); production build passed. Migration `.050` is applied to the current empty local database. A focused Chromium regression was added but not executed in this sandbox.
- **Next:** Continue data-scope/accessibility review; local database recovery and PostgreSQL/staging remain pending.

## Milestone 0.146 — Detect labeled Indian passport and voter IDs in AI flows
- **Finding:** AI input checks and output/context redaction covered emails, phone numbers, PAN-like values, and 12-digit numbers, but did not catch labeled passport numbers or voter IDs.
- **Fix:** Tutor questions and teacher-assistant requests now reject passport and voter-ID values when labeled. Lesson context and generated tutor/teacher text redact those values while preserving the labels.
- **Verification:** AI feature tests passed (3 tests, 50 assertions); the full Laravel suite passed in isolated in-memory SQLite (93 tests, 1,400 assertions). PHP syntax checks passed.
- **Next:** Continue the remaining API capability/data-scope and accessibility audits; local database recovery and hosted PostgreSQL verification remain pending.

## Milestone 0.145 — Align fee invoice listing with the fee-view capability
- **Finding:** The fee-invoice API accepted `fees.view`, then also required `academic.manage` for admin and educator invoice lists, conflicting with the UI and capability model.
- **Fix:** Active owners, admins, and educators with `fees.view` can list workspace invoices. Student and guardian lists stay scoped to self and active linked learners. Fee summaries, payment recording, and invoice voiding still require academic-management access.
- **Verification:** Full Laravel suite passed in an isolated in-memory SQLite environment: 93 tests, 1,394 assertions. The fee-invoice feature file passed (4 tests, 53 assertions).
- **Data note:** The local SQLite database currently contains zero users and organizations. A PHPUnit workaround used earlier bypassed `phpunit.xml` and selected the local database; no repository backup was found. Check OneDrive version history before recreating local accounts.
- **Next:** Restore the local development database if a OneDrive version is available, then continue the pending access-scope and accessibility audits.

## Milestone 0.144 — Keep local sign-in clear when email delivery is unavailable
- **Finding:** The local mail driver only logs verification messages, while the auth screen redirected unverified accounts into a resend flow that could not deliver email.
- **Fix:** Local API verification is disabled in `api/.env`, and the auth screen stays on sign-in if the API returns a verification response while `VITE_REQUIRE_EMAIL_VERIFICATION` is unset. Set `VITE_REQUIRE_EMAIL_VERIFICATION=true` explicitly to show verification UI in environments that deliver email.
- **Verification:** Laravel reports `auth.require_verified_email=false`; the API server was restarted on port 8013; production build passed. A focused Playwright test was added, but the test runner could not reach its local server under the current sandbox networking restrictions.
- **Next:** Confirm sign-in with the user's existing account in the open local browser; then continue remaining API scope and accessibility audits.

## Milestone 0.143 — Preserve unread totals beyond the notification preview
- **Finding:** The API counts every unread notification while the popover returns only the newest 100. Recomputing the count from that preview caused the badge to undercount after a read.
- **Fix:** The popover now uses the server's total unread count and decrements it by one for each successfully marked notification.
- **Verification:** Browser regression starts with 115 unread, renders the newest 100, marks one read, and confirms both counts move to 114. Full Chromium suite passed (23 tests); production build passed.
- **Next:** Continue capability/data-scope and accessibility audits; PostgreSQL/staging and physical assistive-technology checks remain pending.

## Milestone 0.142 — Meet WCAG AA contrast for supporting text
- **Finding:** Public course/footer metadata and authentication descriptions, tabs, placeholders, and footer text used colors below 4.5:1 contrast on their light backgrounds.
- **Fix:** Darkened muted text and form hint colors on public and authentication surfaces while preserving the existing visual hierarchy.
- **Verification:** A browser contrast check reads computed foreground/background colors for representative course metadata, footer, navigation, auth description, footer, placeholder, and tab text; all meet 4.5:1. Full Chromium suite passed (23 tests); production build passed.
- **Next:** Continue capability/data-scope and accessibility audits; physical screen-reader, device, and browser-zoom checks remain pending.

## Milestone 0.141 — Enforce teacher learner scope on progress writes
- **Finding:** A teacher managing a course could update progress and trigger a completion certificate for any enrolled learner, even outside the teacher's active class/section assignments.
- **Fix:** Progress writes now require a teacher's target learner to be in the teacher's active assigned-student scope; other users retain their existing self or manager scope. Out-of-scope targets return 404 before progress or certificate creation.
- **Verification:** Added regression coverage for successful progress by an assigned learner and a 404 for another enrolled learner, with no progress or certificate row for the latter. Full Laravel SQLite suite passed (92 tests, 1,386 assertions); Vitest passed (10 tests); production build passed; full Chromium suite passed (23 tests).
- **Next:** Continue capability/data-scope and accessibility audits; PostgreSQL/staging verification remains pending.

## Milestone 0.140 — Notify members about feedback updates
- **Finding:** Members could review replies only by reopening Feedback, and a manager status-only update unintentionally cleared an existing reply.
- **Fix:** Feedback status/reply changes now create or refresh one private in-app notification for the submitter. The notification center merges feedback alerts with announcements, marks each member's own alert read, and opens Feedback when selected. Status-only updates preserve the existing reply.
- **Database:** Applied additive migration `2026_11_19_000049_create_workspace_feedback_notifications`; composite workspace-feedback and workspace-membership foreign keys keep alerts tenant-scoped.
- **Verification:** Full Laravel SQLite suite passed (92 tests, 1,380 assertions); full Chromium suite passed (21 tests), including member navigation from the alert; Vitest passed (10 tests); production build passed.
- **Next:** Continue capability/data-scope and accessibility audits. PostgreSQL/staging validation, physical assistive-technology checks, and approved external email/push/provider integrations remain pending.

## Milestone 0.139 — Respond to and track workspace feedback
- **Finding:** Members could submit feedback and managers could read it, but submitters had no way to see progress and managers could not record a response or resolution state.
- **Fix:** Added open/resolved status, manager response and responder timestamps, a private member endpoint for each submitter's own feedback, and manager-only workspace response/status updates. Updated the Feedback page to let managers reply and resolve/reopen items and let members review their own status and replies.
- **Database:** Applied additive migration `2026_11_19_000048_add_response_status_to_support_feedback`; existing feedback remains open and attributable, with no response.
- **Verification:** Focused API tests passed (3 tests, 44 assertions), including response visibility and cross-workspace/role denials. Full Laravel SQLite suite passed (92 tests, 1,362 assertions); Vitest passed (10 tests); production build passed; full Chromium suite passed (20 tests), including manager reply/resolution and member history journeys.
- **Next:** Continue capability/data-scope and accessibility audits. Automatic email/push notification, PostgreSQL/staging validation, and physical assistive-technology checks remain pending.

## Milestone 0.138 — Identify learners in grading controls
- **Finding:** Each submission form repeated the accessible names “Score” and “Feedback,” making it unclear which learner would receive a grade when navigating by form controls.
- **Fix:** Score labels now identify the learner and maximum points; feedback labels identify the learner.
- **Verification:** Added a Chromium case with two synthetic submissions and verified distinct score and feedback names for each learner. Production build passed; Vitest passed (10 tests); the complete Chromium suite passed (18 tests). The API implementation was unchanged; its full suite passed at 91 tests and 1,334 assertions, including whole-class dashboard counts.
- **Next:** Continue capability/data-scope and accessibility audits. PostgreSQL/staging validation and physical screen-reader/mobile checks remain pending.

## Milestone 0.137 — Preserve teacher section scope across classes and announce current navigation
- **Finding:** The classes API reused the teacher's union of assigned student IDs for each class, which could reveal a learner from an unassigned section in a second class. Authenticated workspace navigation marked its current destination visually but did not expose that state to assistive technology.
- **Fix:** Eager-loaded class rosters, dashboard per-class counts/enrollment totals, and academic report filters/labels now constrain each enrollment by its matching class/section assignment. Main navigation, Tools, Settings, and Help Centre destination controls expose `aria-current="page"` while active.
- **Verification:** Added cross-class roster and academic-report regressions where a learner is in an assigned section in one class and an unassigned section in another. Teacher class labels and filtered reports now use the same per-class section assignment scope. Full Laravel SQLite suite passed (91 tests, 1,334 assertions); Vitest passed (10 tests); production build passed; full Chromium suite passed (17 tests), including assertions that current-page state moves from Reports to Courses.
- **Next:** Continue the capability/data-scope and accessibility audits. PostgreSQL/staging validation and physical screen-reader/mobile checks remain pending.

## Milestone 0.136 — Withdraw and restore announcements
- **Finding:** Managers could edit announcements but could not remove an incorrect or premature notice from member views without deleting its history.
- **Fix:** Added manager-only, workspace-scoped withdraw and restore endpoints. Withdrawn announcements stay in storage with their prior published/scheduled state, disappear from member lists and notification inboxes, and return with existing notification/read history on restore. Added manager controls to the workspace UI.
- **Database:** Applied the additive local SQLite migration adding `announcements.withdrawn_status`; existing rows are preserved.
- **Verification:** API announcement regressions passed (3 tests, 78 assertions), including tenant authorization, notification retention, and restore. Full Laravel SQLite suite passed (90 tests, 1,312 assertions); Vitest passed (10 tests); production build passed; full Chromium suite passed (17 tests), including withdrawal/restoration. The local test commands required elevated execution for PHPUnit's Composer bootstrap and Playwright's isolated server binding.
- **Next:** Complete API/browser regression execution, then continue capability/data-scope and accessibility reviews. PostgreSQL/staging verification and production scheduler supervision remain pending.

## Milestone 0.135 — Schedule audience-scoped announcements
- **Finding:** Announcements published immediately, so managers could not prepare a workspace update for a future date/time.
- **Fix:** Added optional future `scheduled_at` in the workspace timezone, manager-only visibility before publication, an every-minute Laravel scheduler command, and idempotent notification delivery to active audience members when due. Managers can edit scheduled or published title/message while audience remains fixed. Local composer development now runs `schedule:work`.
- **Verification:** Announcement API regressions passed (2 tests, 57 assertions), including manager-only visibility, due publication, audience delivery, and duplicate-run safety. Full Laravel SQLite suite passed (89 tests, 1,291 assertions); Vitest passed (10 tests); production build passed; full Chromium suite passed (16 tests), including scheduling from the manager UI.
- **Next:** Add a recoverable withdrawal/archive flow for announcements and continue capability/data-scope and accessibility reviews. Production scheduler supervision and PostgreSQL/staging verification remain pending.

## Milestone 0.134 — Apply the pending assessment availability migration locally
- **Finding:** The local SQLite workspace was missing the additive assessment open/close timestamp migration, despite the code and clean test databases supporting scheduled exams.
- **Fix:** Previewed and applied the migration adding nullable `opens_at`/`closes_at` columns and indexes; existing records were preserved.
- **Verification:** Migration status shows no pending migrations; both columns exist. The scheduled-exam feature regression passed (1 test, 14 assertions).
- **Next:** Continue capability/data-scope and accessibility reviews; PostgreSQL CI and staging verification remain pending.

## Milestone 0.133 — Restore local API reachability
- **Finding:** The PHP process on port 8012 held a listener but would not accept HTTP connections, so the running Vite site could not reach Laravel.
- **Fix:** Started a fresh loopback-only API on port 8013, moved the Vite proxy and local `APP_URL` examples/config to 8013, and left the unresponsive 8012 process untouched.
- **Verification:** `GET /api/v1/health` returned 200 directly on 8013 and through the frontend at 5174. A synthetic login through Vite returned 401 for invalid credentials, not an email-verification denial; Laravel reports local verification disabled. Existing full API (88 tests, 1,265 assertions), Vitest (10 tests), production build, and 15-test Chromium results remain green.
- **Next:** Continue capability/data-scope and accessibility reviews. Staging, PostgreSQL CI, external integrations, and physical assistive-technology checks remain pending.

## Milestone 0.132 — Let managers correct published announcements
- **Finding:** Workspace announcements published immediately, but a manager could not correct a typo or changed detail after recipients were notified.
- **Fix:** Added a tenant-scoped, capability-protected update endpoint and editor for announcement title and message. The published audience cannot be changed; existing notification rows and recipient scope stay intact, and notification history renders the corrected content.
- **Verification:** Full Laravel SQLite suite passed (88 tests, 1,265 assertions), including cross-workspace denial, non-manager denial, immutable audience, and notification-content updates. Vitest passed (10 tests); production TypeScript/Vite build passed; full Chromium suite passed (15 tests), including the announcement edit journey.
- **Next:** Continue capability/data-scope and accessibility reviews. Announcement delete/scheduling, PostgreSQL CI, staging, external providers, and physical assistive-technology checks remain pending.

## Milestone 0.131 — Make local email verification bypass explicit
- **Finding:** Local verification was disabled by default, but the setting was implicit; adding an explicit local override also caused strict-mode feature tests to inherit the local value.
- **Fix:** Set `PAATHNEXUS_REQUIRE_EMAIL_VERIFICATION=false` in the local API environment, cleared cached config, and made strict verification tests opt in explicitly so local login stays unblocked while production verification remains covered.
- **Verification:** Laravel reports `auth.require_verified_email=false` locally. At this milestone, the full SQLite suite passed (88 tests, 1,260 assertions); strict verification, local bypass, and resend behavior all pass.
- **Next:** Keep verification disabled for local log-mailer development; staging and production must enable it and configure actual mail delivery.

## Milestone 0.130 — Add narrowed custom roles for admins and educators
- **Finding:** Custom permission profiles supported teachers, students, and guardians, while admins and educators retained their full default capabilities.
- **Fix:** Admin and educator memberships can now receive workspace-specific profiles that only remove capabilities from their base role. Educator profiles use explicit configured capabilities instead of inheriting `*`; owners remain unassignable. Management permissions retain their required read capabilities. The role editor lists all five supported member types, and manager navigation/screens follow effective capabilities.
- **Verification:** Full Laravel SQLite suite passed (88 tests, 1,260 assertions), including permission denial, capability dependency rejection, unknown-capability rejection, owner exclusion, and cross-role assignment checks. Vitest passed (10 tests), including 5 custom-role component tests; production TypeScript/Vite build passed; full Chromium suite passed (14 tests), including responsive role configuration and restricted-admin navigation.
- **Next:** Continue capability/data-scope audits and accessibility review. PostgreSQL CI, staging, external production providers, and physical assistive-technology checks remain pending.

## Milestone 0.129 — Guide managers from accepted invitations to profile links
- **Finding:** Accepted teacher, student, and guardian invitations became active workspace memberships, but the manager had to discover the separate academic-profile or guardian-link workflow.
- **Fix:** Accepted invitation rows now explain the follow-up and provide direct navigation to Schedule for teacher profiles, Courses for student profiles, or the Guardian links section. Actions appear only for managers with `academic.manage`.
- **Verification:** Production TypeScript/Vite build passed; Vitest passed (9 tests); the full Chromium suite passed (12 tests), including checks that student invitations open Courses, teacher invitations open Schedule, and guardian invitations scroll to Guardian links.
- **Next:** Continue capability/data-scope and accessibility reviews. Hosted PostgreSQL, staging, production email/payment/AI providers, and physical assistive-technology checks remain pending.

## Milestone 0.128 — Clarify archived custom-role permissions
- **Finding:** The custom-role archive confirmation said assigned members would return to their standard permissions, while the API intentionally retains archived assignments until restore or unassign.
- **Fix:** Updated archive confirmation/status copy to describe retained permissions, replaced staff-only labels with custom/member-role wording, and included guardians in the empty member-assignment state.
- **Verification:** Vitest passed (9 tests), including a regression for archived-role permission copy; production TypeScript check and build passed.
- **Next:** Continue release-readiness and capability/accessibility reviews; PostgreSQL and deployment validation remain pending.

## Milestone 0.127 — Add platform-wide active usage metrics
- **Finding:** Platform admins could inspect counts one workspace at a time, but had no current aggregate view of platform scale.
- **Fix:** Added an allowlist-protected `GET /platform/usage` endpoint and platform admin cards for total/active/suspended workspaces, active memberships, learner profiles, active courses, and active subscriptions. Member/course/subscription totals exclude suspended workspaces; no member contact details are returned.
- **Verification:** Focused API test passed (1 test, 9 assertions), including allowlist denial and exclusion of suspended workspace activity. Full Laravel SQLite suite passed (87 tests, 1,239 assertions), build passed, Vitest passed (8 tests), and full Chromium suite passed (11 tests), including the platform admin dashboard.
- **Next:** Add historical usage trends only with defined retention and aggregation needs; continue PostgreSQL and deployment verification.

## Milestone 0.126 — Route local sign-in to the PaathNexus API
- **Finding:** Port 8002 served an unrelated `edtechlive-api` while Vite proxied PaathNexus requests there, so sign-in was reaching the wrong backend.
- **Fix:** Moved the local PaathNexus API to port 8012, updated the Vite proxy, `APP_URL`, environment examples, and run instructions. The other service on 8002 was left untouched.
- **Verification:** `http://127.0.0.1:5174/api/v1/health` returned `{"status":"ok","service":"paathnexus-api"}` through Vite. Local verification remains disabled by the `APP_ENV=local` default, and all API, Vitest, build, and Chromium tests passed.
- **Next:** Continue release-readiness and capability/data-scope reviews; hosted PostgreSQL and deployment checks remain pending.

## Milestone 0.125 — Add workspace fee summary
- **Finding:** Staff could issue invoices and record reconciled payments, but had no all-time view of billed, collected, outstanding, and overdue amounts.
- **Fix:** Added a manager-only, tenant-scoped summary endpoint and fee overview cards. Totals use integer paise from invoices and recorded receipts, exclude void invoices, and show overdue balances based on due dates. No gateway or estimated payment data is included.
- **Verification:** Fee invoice feature tests passed (3 tests, 45 assertions), including cross-workspace isolation and student denial. Full Laravel SQLite suite passed (86 tests, 1,230 assertions), production build passed, Vitest passed (8 tests), and the full Chromium suite passed (10 tests), including the educator fee overview journey.
- **Next:** Continue release-readiness and capability/data-scope reviews; hosted PostgreSQL and deployment checks remain pending.

## Milestone 0.124 — Keep the local email-verification bypass out of tests
- **Finding:** An explicit local `.env` override was also loaded by PHPUnit, weakening tests that assert verification is required outside local development.
- **Fix:** Removed the redundant override. The configuration default disables verification only when `APP_ENV=local`; test and non-local environments continue to require it by default.
- **Verification:** `php artisan config:show auth.require_verified_email` reports `false` locally. The full Laravel SQLite suite passed (85 tests, 1,217 assertions).
- **Next:** Continue release-readiness and capability/data-scope reviews; hosted PostgreSQL and deployment checks remain pending.

## Milestone 0.123 — Name learner assignment submission controls
- **Finding:** The learner's assignment response textarea had placeholder text but no accessible name.
- **Fix:** Added an assignment-specific accessible name so screen-reader users can distinguish submission fields when multiple assignments appear together. Added a Playwright assertion to the learner course journey.
- **Verification:** `npm run build` passed; the focused learner browser journey and full Chromium suite passed (10 tests); Vitest passed (8 tests).
- **Next:** Continue accessibility and API scope reviews; physical screen-reader checks remain pending.

## Milestone 0.122 — Audit PHP dependencies in CI
- **Fix:** Both SQLite and PostgreSQL API CI jobs now run `composer audit --locked` against the committed lock file.
- **Verification:** `composer audit --locked` passed locally with no security advisories. Hosted CI confirmation remains pending.
- **Next:** Confirm the audit in hosted CI; continue capability/data-scope and accessibility reviews.

## Milestone 0.121 — Add print-ready academic reports
- **Finding:** Academic reporting offered CSV only, with no print layout for sharing or saving a report as PDF.
- **Fix:** Added an accessible Print / Save PDF action using the browser print dialog. Print styles hide workspace navigation and filters, expand the report table, and format summary cards and rows for paper/PDF output. CSV remains available.
- **Verification:** The report browser test stubs `window.print`, emulates print media, verifies navigation/actions are hidden and report content remains visible, and checks CSV export. Production build passed; full Chromium suite passed (10 tests).
- **Limit:** PDF output is produced through the browser's print dialog; the application does not generate or directly download a PDF file.
- **Next:** Continue capability/data-scope and accessibility reviews; PostgreSQL hosted CI, physical screen-reader checks, and external production integrations remain outstanding.

## Milestone 0.120 — Verify academic report assignment totals in browser
- **Finding:** The assignment workload metrics had backend coverage and a production build, but their browser rendering and CSV projection were not exercised together.
- **Fix:** Added an educator browser journey with a synthetic workspace fixture. It verifies the summary total, per-student assigned/submitted/graded values, responsive overflow behavior, CSV filename, and exported assignment columns/row.
- **Verification:** Focused Playwright test passed; full Chromium suite passed (10 tests) with the configured two-worker default.
- **Next:** Continue capability/data-scope audits and accessibility review; PostgreSQL hosted CI, physical screen-reader checks, and external production provider validation remain outstanding.

## Milestone 0.119 — Correct keyboard semantics for auth mode controls
- **Finding:** Sign-in/create-account mode switches used ARIA tab roles without the associated tab panels, roving focus, or arrow-key behavior.
- **Fix:** Replaced the incomplete tab pattern with a labeled button group using `aria-pressed`; native Enter/Space activation selects modes. Playwright now defaults to two workers, with `PLAYWRIGHT_WORKERS` available to override the local setting.
- **Verification:** The auth browser test checks group semantics, absence of tab roles, and keyboard switching. Chromium suite passed (9 tests) with two workers; Vitest passed (8 tests); production build passed. An eight-worker run had one learner-flow timeout under machine load; the same flow passed with two workers.
- **Audit:** A follow-up review of student, guardian, course, and assessment projections found no additional confirmed tenant/capability leak.
- **Next:** Continue focused API-scope and accessibility review; staging, PostgreSQL hosted CI, and physical screen-reader checks remain outstanding.

## Milestone 0.118 — Allow unverified sign-in in local development
- **Finding:** The local API uses Laravel's log mailer, so verification links are not delivered to the user's inbox and an unverified account cannot complete sign-in from the UI.
- **Fix:** Email verification is now disabled by default only when `APP_ENV=local`, allowing local sign-in and workspace access without the verification screen. `PAATHNEXUS_REQUIRE_EMAIL_VERIFICATION` can override this; non-local environments default to requiring verification. The existing signed verification flow remains available when required.
- **Verification:** Added a regression proving local-mode sign-in grants a token usable by authenticated workspace routes, while the default test environment still rejects unverified users. Focused verification feature tests passed (3 tests, 12 assertions); the full Laravel SQLite suite passed (85 tests, 1,217 assertions).
- **Next:** Continue report and API capability audits, responsive/accessibility checks, and production readiness work. Staging must explicitly set `PAATHNEXUS_REQUIRE_EMAIL_VERIFICATION=true`; email transport and PostgreSQL validation remain pending.

## Milestone 0.117 — Add active assignment workload to academic reports
- **Finding:** Reports showed submitted and graded assignments without the number of published tasks assigned to each currently enrolled learner; retained submissions from ended enrollments could inflate counts.
- **Fix:** Academic report API now counts published assignments for active enrollments in active courses, scopes teacher totals to accessible courses, and excludes submissions outside active enrollment. The report cards, student table, and CSV include assigned, submitted, and graded totals.
- **Verification:** Added regression coverage for ended enrollment history, selected-date activity, and teacher course scope. Focused Laravel tests passed (4 tests, 60 assertions); full Laravel SQLite suite passed (84 tests, 1,214 assertions); Vitest passed (8 tests); production frontend build passed.
- **Next:** Continue the capability/data-scope and accessibility audits; richer report measures and export formats, PostgreSQL CI, and live-provider integrations remain pending.

## Milestone 0.116 — Scope learning completion to active enrollment
- **Finding:** Guardian and academic reports counted completed lesson records from ended course enrollments, while denominators used current active courses/lessons; withdrawn-course history could inflate completion above 100%.
- **Fix:** Count only active lessons in active courses with active student enrollments in guardian summaries and academic reports, matching the learner dashboard.
- **Verification:** Added guardian and academic-report regressions with ended enrollments and retained progress. Focused tests passed; full Laravel SQLite suite passed (84 tests / 1,209 assertions).
- **Next:** Continue capability/data-scope and accessibility audits; PostgreSQL CI and live-provider integrations remain pending.

## Milestone 0.115 — Align guardian attendance rate semantics
- **Finding:** Student dashboards and academic reports counted `late` attendance as present, while guardian summaries counted only `present`, understating linked learners' attendance rates.
- **Fix:** Count both `present` and `late` records in guardian attendance percentages.
- **Verification:** Added a four-status guardian regression (present, late, absent, excused); focused test passed. Full Laravel SQLite suite passed (83 tests / 1,202 assertions).
- **Next:** Continue capability/data-scope and accessibility audits; PostgreSQL CI and live-provider AI/payment integrations remain pending.

## Milestone 0.114 — Expand offline AI prompt-boundary fixtures
- **Finding:** Existing fake-provider coverage exercised malicious lesson text but did not explicitly verify direct learner and teacher instructions remain separated from system safety rules.
- **Fix:** Added prompt-override attempts to learner tutor and teacher-assistant fixtures; assertions confirm the attacks remain in user messages while the safety rules stay in the system message.
- **Verification:** Focused AI feature tests passed (3 tests / 44 assertions). Full Laravel SQLite suite passed (82 tests / 1,199 assertions).
- **Next:** Run live-provider adversarial evaluation only after an approved provider and retention review; continue accessibility and capability/data-scope audits meanwhile.

## Milestone 0.113 — Add public-site landmarks and a skip link
- **Finding:** The public page's main landmark also contained its header and footer, and keyboard users had no direct link past navigation.
- **Fix:** Restructured the page into banner, main, and contentinfo landmarks and added a visible-on-focus skip link targeting the main content.
- **Verification:** Full Chromium Playwright suite passed (9 tests), including landmark discovery, keyboard skip-link focus, and all responsive/workspace flows. TypeScript/Vite production build passed with a 421 kB initial JS chunk.
- **Next:** Continue capability/data-scope and accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.112 — Verify section-level report attendance scope
- **Finding:** Section-level report coverage checked student roster visibility but did not prove attendance aggregates exclude whole-class and other-section sessions.
- **Fix:** Added section, whole-class, and other-section attendance fixtures; assert a section-assigned teacher's report counts only assigned-section sessions and the attendance list remains scoped.
- **Verification:** Section-scope and academic-report tests passed (5 tests / 103 assertions). Full Laravel SQLite suite passed (82 tests / 1,199 assertions).
- **Next:** Continue capability/data-scope and accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.111 — Expose the educator workflow to assistive technology
- **Finding:** The public educator workflow used an `aria-label` on a generic `div`, so the label was not announced as a named region.
- **Fix:** Changed the wrapper to a named section and added a browser assertion that the workflow is discoverable as a region.
- **Verification:** Focused Playwright homepage test passed on local port 5182; `npm run build` passed with a 421 kB initial JavaScript chunk.
- **Next:** Continue capability/data-scope and accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.110 — Scope teacher academic report aggregates
- **Finding:** Teacher reports limited students and class labels to assigned classes, but activity aggregates included all organization-wide attendance and course activity for those students.
- **Fix:** Restrict teacher report attendance to assigned classes/sections (and legacy unclassified sessions recorded by the teacher). Restrict course enrollment, lesson progress, assignments, and assessment attempts to courses the teacher created or was assigned.
- **Verification:** Academic report feature tests passed (4 tests / 53 assertions), including a regression comparing teacher and owner report results. Full Laravel SQLite suite passed (82 tests / 1,196 assertions).
- **Next:** Continue capability/data-scope and accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.109 — Audit public API route boundaries
- **Finding:** The authenticated course-order and order-status routes were declared twice in the route source. Laravel exposed one runtime route, but the source duplication could make later route changes ambiguous.
- **Fix:** Removed the duplicate declarations. Reviewed the public route inventory: health is minimal; catalog reads are rate-limited; auth routes are throttled; email verification requires a signed URL and is throttled; the sandbox webhook is throttled and verifies its signature. Organization routes remain behind Sanctum, active-account, and verified-email middleware.
- **Verification:** Catalog and course checkout feature tests passed (6 tests / 60 assertions), including malformed course-order UUID rejection. The full Laravel SQLite suite passed (81 tests / 1,182 assertions), and `route:list` reports no duplicate method/URI pairs.
- **Next:** Continue API capability/data-scope and accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.108 — Fix mobile assessment overflow and broaden workspace viewport coverage
- **Finding:** A 320 px Playwright viewport exposed a 435 px minimum-width grid item on the teacher quiz-authoring page. Existing dashboard checks covered the public site and guardian workspace, but not learner and staff workspaces.
- **Fix:** Allow nested course-detail grid children and academic form controls to shrink within their available columns. Extended the responsive browser helper across learner dashboard/course views, guardian dashboard, teacher assessment authoring, and educator course authoring at 320, 375, 430, 768, 1024, and 1440 px.
- **Verification:** Full Playwright suite passed (9 tests), including the six-width checks; Vitest passed (8 tests across 3 files); TypeScript/Vite production build passed with a 421 kB initial JS chunk.
- **Next:** Continue capability/data-scope review and accessibility checks. Physical devices, assistive technology, live-provider AI evaluation, and PostgreSQL CI remain pending.

## Milestone 0.107 — Align role navigation with accessible workspace views
- **Findings:** Switching into a student workspace while AI Learning was selected could reset the learner away from the tutor. Scoped teachers could also see Reports and attendance shortcuts without all capabilities required by those views.
- **Fix:** Preserve AI Learning as a valid student destination. Show Reports and its weekly shortcut only when `reports.view`, `academic.view`, and `attendance.read` are all present; show Take attendance only with `attendance.read`.
- **Verification:** Added browser regressions for switching workspaces into a student account and for a teacher with `reports.view` but no attendance permission. Full Playwright suite passed (9 tests); Vitest passed (8 tests across 3 files); TypeScript/Vite production build passed with a 421 kB initial JS chunk.
- **Next:** Continue API capability/data-scope and responsive/accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.106 — Verify announcement and notification tenant isolation
- **Finding:** Announcement and read-state queries were tenant-scoped in the controller, but the feature test did not cover one user with active memberships in multiple workspaces.
- **Coverage:** Added regression assertions that a student's announcements, notification list, and read acknowledgement in one workspace cannot access another workspace's records, even when that same account belongs to both.
- **Verification:** Focused `AnnouncementTest` passed (1 test / 26 assertions). Full Laravel SQLite suite passed (81 tests / 1,181 assertions).
- **Next:** Continue API capability/data-scope and responsive/accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.105 — Split secondary screens into on-demand chunks
- **Finding:** The production entry bundle exceeded Vite's 500 kB warning threshold because authentication, marketplace, role settings, question import, analytics, and learner results were included up front.
- **Fix:** Lazy-load those standalone screens and render accessible loading text while each chunk loads. The main minified JavaScript bundle dropped from about 501 kB to 421 kB; the deferred screen chunks range from 2.3 to 27.6 kB.
- **Verification:** `npm run build` passed with no chunk-size warning; all frontend tests passed (8 tests across 3 files). All 8 Playwright browser tests passed on port 5182 with local loopback access enabled, covering responsive layouts, navigation focus, auth entry, learner quiz completion, guardian summaries, teacher assessment scope, and educator course authoring.
- **Next:** Continue API capability/data-scope and responsive/accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.104 — Unify safe CSV encoding and finish download-scope review
- **Finding:** Guardian summary CSVs escaped formula characters only at the first character, unlike academic and grade exports, which also protected values with leading whitespace.
- **Fix:** Added a shared browser CSV encoder that quotes cells, doubles embedded quotes, escapes spreadsheet formula prefixes after whitespace, and emits a UTF-8 BOM. Guardian and academic report downloads now use it. Reviewed assignment attachment downloads and grade exports: both enforce active tenant/course access, learner ownership or teacher assignment scope, and grade-export management permission.
- **Verification:** Full frontend suite passed (8 tests across 3 files), including the focused CSV regressions; `npm run build` passed. Vite reports the existing ~501 kB minified JavaScript chunk warning. No additional attachment/export scope gap was found in this review.
- **Next:** Continue API capability/data-scope and responsive/accessibility audits. Live-provider AI evaluation and PostgreSQL CI remain pending.

## Milestone 0.103 — Structure lesson context and harden prompt-injection instructions
- **Finding:** Redacted lesson titles and excerpts were concatenated into free-form prose. Untrusted lesson content could contain instruction-like text or imitate surrounding prompt boundaries.
- **Fix:** Serialize the redacted title and excerpt as JSON reference data and explicitly instruct the tutor to use them only for factual context, never to follow embedded instructions or reveal prompts/private data.
- **Verification:** The AI feature test includes malicious-looking directions in both title and lesson content. It decodes the actual provider request and checks structured fields, preserved untrusted text, explicit system constraints, and the existing PII redactions. Focused AI tests passed (3 tests / 44 assertions); full Laravel SQLite suite passed (81 tests / 1,175 assertions).
- **Next:** Evaluate prompt injection against a live model only when an approved provider is configured; broaden adversarial fixtures and keep reviewing API scope. Prompt wording alone cannot guarantee model resistance; broader AI evaluation and PostgreSQL verification remain pending.

## Milestone 0.102 — Expand AI privacy regression matrix
- **Coverage:** Extended provider-fake cases to test spaced Aadhaar-like values in tutor and teacher-draft output, and confirmed the teacher-assistant endpoint rejects PAN-like and international phone values before provider use. Existing coverage checks detected values in tutor prompts and lesson titles and redacts both generated response types.
- **Verification:** Focused AI tests passed (3 tests / 44 assertions). Full Laravel SQLite suite passed (81 tests / 1,175 assertions). No live provider credentials were used.
- **Next:** Continue AI safety evaluation against prompt injection, unsafe completions, and detector false positives; then resume the API scope and accessibility audits. These deterministic regex cases are not a full sensitive-data or content-moderation system.

## Milestone 0.101 — Redact detected values from AI-generated output
- **Finding:** The AI boundary screened user prompts and lesson context, but provider-generated tutor answers and teacher drafts were returned unchanged and could include contact or identity-like values.
- **Fix:** Apply the shared redactor to provider output before returning tutor answers and editable teacher drafts. Usage metering and human-review behavior remain intact.
- **Verification:** Provider fakes return synthetic email, international phone, and PAN-like values; API regressions confirm all are replaced in both output types. Focused AI tests passed (3 tests / 38 assertions); full Laravel SQLite suite passed (81 tests / 1,169 assertions).
- **Next:** Continue AI privacy-boundary and model-output evaluation. Pattern-based detection remains incomplete; PostgreSQL and physical accessibility verification remain pending.

## Milestone 0.100 — Detect formatted international phone numbers in AI inputs
- **Finding:** The privacy filters covered Indian mobile numbers but allowed formatted international numbers such as `+1 (415) 555-2671` through tutor prompts and lesson context.
- **Fix:** Detect international numbers with an explicit `+` country prefix and redact them from lesson context. The tutor rejects these values in user questions before provider use.
- **Verification:** Added synthetic formatted-number cases for context redaction and prompt rejection. Focused AI tests passed (3 tests / 38 assertions); full Laravel SQLite suite passed (81 tests / 1,169 assertions after milestone 0.101).
- **Next:** Continue the AI privacy-boundary audit and broader API scope review. Regex coverage remains partial and does not replace a full sensitive-data detector; PostgreSQL and physical accessibility checks remain pending.

## Milestone 0.99 — Detect and redact Indian PAN identifiers in AI context
- **Finding:** Email, phone, and Aadhaar-like patterns were filtered, but the common Indian PAN identifier format could pass through tutor questions and lesson context.
- **Fix:** Reject PAN-like identifiers in user-provided tutor/teacher-assistant text and redact them from authorized lesson context before provider submission.
- **Verification:** Added synthetic PAN values to the lesson-title payload test and verified the identifier is replaced; added a tutor prompt rejection regression. Focused AI tests passed (3 tests / 38 assertions after milestone 0.100). Full Laravel SQLite suite passed (81 tests / 1,169 assertions).
- **Next:** Continue expanding AI privacy-boundary coverage and the API scope audit. Pattern matching remains partial and cannot detect every sensitive value; PostgreSQL and physical accessibility verification remain pending.

## Milestone 0.98 — Redact lesson-title contact data before AI requests
- **Finding:** The tutor redacted email addresses, Indian phone numbers, and 12-digit identity-like values from lesson excerpts but sent the lesson title unchanged to the configured provider.
- **Fix:** Apply the existing contact-data redactor to both the authorized lesson title and excerpt before provider submission.
- **Verification:** Expanded the AI provider-payload regression with synthetic email and phone values in the lesson title; verified placeholders are sent and original values are absent. Full Laravel SQLite suite passed (81 tests / 1,166 assertions after milestone 0.99).
- **Next:** Continue testing AI privacy boundaries and the route/data-scope audit, then complete remaining responsive/accessibility checks. Existing pattern-based filtering is not a general sensitive-data detector; PostgreSQL and physical assistive-technology checks remain pending.

## Milestone 0.97 — Support whole-class live sessions for section teachers
- **Finding:** Live-session validation required a section whenever a class was selected, preventing whole-class sessions. Section-assigned teachers also could not see or join whole-class sessions, even though they serve an enrolled section.
- **Fixes:** Class-wide sessions may now omit `section_id` while any supplied section is still validated. Students enrolled in any class section and teachers assigned to at least one section can see and join the class-wide session. Teacher update, roster, and attendance actions remain limited to the exact assigned section/class audience.
- **Verification:** Expanded the live-session API scenario for class-wide creation, student and teacher visibility/join, manager-write denial outside the teacher's assignment, and existing section-only attendance behavior. Full Laravel SQLite suite passed (81 tests / 1,163 assertions).
- **Next:** Continue route and role-scope review, then complete remaining responsive/accessibility checks and broaden AI evaluation. PostgreSQL CI and physical assistive-technology checks remain pending.

## Milestone 0.96 — Close attendance and section metadata scope gaps
- **Findings:** Student and guardian attendance routes exposed workspace-wide session dates/rates, and dashboard trends were not scoped for those roles. Section-assigned teachers could read subject offerings, current/historical teacher assignments from other sections in their class, and metadata for classes outside their assignment. The legacy class-name attendance snapshot write could overwrite an arbitrary class aggregate using a client-supplied rate.
- **Fixes:** Attendance-session listing now requires academic and attendance read access; dashboard attendance aggregates require both capabilities. Teacher subject offerings, teacher assignment lists, and assignment history now filter to assigned sections plus class-wide metadata and return not found for unassigned classes. Legacy aggregate writes require both attendance and academic management; the class/section roster attendance workflow remains available to authorized teachers.
- **Verification:** Added API regressions for guardian attendance privacy, manager-only legacy snapshot writes, section-level offerings/assignment history, and denied access to unassigned classes. Full Laravel SQLite suite passed (81 tests / 1,157 assertions).
- **Next:** Continue the capability/data-scope review, then complete remaining responsive/accessibility checks and broaden AI evaluation. PostgreSQL CI and physical assistive-technology checks remain pending.

## Milestone 0.95 — Preserve narrowed permissions and guardian search privacy
- **Findings:** Archiving a custom role cleared its assigned members' role IDs, restoring the broader base-role permissions. Workspace search also exposed a linked learner's class names to guardians whose custom profile omitted `courses.view`.
- **Fixes:** Archiving now retains member assignments and their narrowed permissions until an authorized manager explicitly unassigns the role. The role editor shows archived assignments and explains that their permissions remain in force. Guardian enrolled-class search now requires `courses.view`.
- **Verification:** Added API regressions for archive/restore/unassign permission transitions and guardian class-name search. Full Laravel SQLite suite passed (81 tests / 1,144 assertions). Vitest passed (6 tests), including archived-role assignment visibility; TypeScript/Vite build passed.
- **Next:** Continue capability and data-scope audit, then complete accessible interaction review and AI evaluation. PostgreSQL, staging, and live-provider verification remain pending.

## Milestone 0.94 — Course instructor assignment capability and scope
- **Finding:** The course-instructor candidate and assignment endpoints checked the base role (`owner`, `admin`, or `educator`) instead of the `courses.manage` capability. This blocked teachers whose default or custom permission profile grants course management, despite the course UI exposing instructor assignment to course managers.
- **Fix:** Both endpoints now require `courses.manage`. Teacher target-course updates use the existing teacher course scope, which permits only courses the teacher created or was assigned. Read-only teacher profiles remain denied, and out-of-scope courses return not found.
- **Verification:** Added a custom-role API regression for allowed in-scope assignment, denied out-of-scope update, and denial without `courses.manage`. Full Laravel SQLite suite passed (81 tests / 1,139 assertions at that milestone).
- **Next:** Continue the route-capability audit and review accessibility; PostgreSQL and production-provider checks remain pending.

## Milestone 0.93 — Workspace-timezone-safe quiz scheduling
- **Finding:** The quiz scheduling, live-session, assignment deadline, and study-planner fields accepted workspace-local wall times but converted them using the user's browser timezone. A user outside the workspace timezone could therefore schedule items at the wrong instant.
- **Fix:** These forms now convert and display using the organization's configured IANA timezone. The forms label the timezone explicitly, reject invalid/nonexistent local times during daylight-saving clock changes, and pick the earlier instant when a fall-back time occurs twice. Quiz close time and live-session end time are checked against their start times. Learner deadlines and the workspace header also display in the workspace timezone; date-only staff task deadlines retain their calendar date.
- **Verification:** Time conversion tests cover `Asia/Kolkata`, inverse display formatting, an ambiguous `America/New_York` fall-back time, a nonexistent spring-forward time, invalid dates, and an unknown timezone. Vitest passed (5 tests), TypeScript/Vite production build passed, and the 8-test Chromium suite passed, including an Asia/Kolkata learner deadline viewed from an America/Los_Angeles browser.
- **Next:** Continue mapping each API route to its required capability, review timezone rules on PostgreSQL, and complete keyboard/screen-reader/accessibility checks. PostgreSQL service, physical devices, and screen-reader testing remain unavailable in this environment.

## Milestone 0.92 — Independent assessment-management capability
- **Finding:** `assessments.manage` was offered to school roles and editable in custom teacher profiles, but question-bank, quiz-authoring, grading, and analytics APIs used `courses.manage` instead. The learning panel also fetched the answer-key question bank for non-managers, which could fail the whole course-detail load for learners.
- **Fix:** Added the dedicated `manageAssessments` policy and applied it to question-bank reads/writes/import, quiz authoring, analytics, grade exports, answer review, and written-response grading. Course editors without that capability can read published quiz summaries, but cannot access answer keys or manage exams. Teachers with course view and assessment management can view their created/assigned courses, lessons, and published assignments without receiving course-edit/enrollment rights. The UI now gates the question bank and analytics on `assessments.manage` and no longer requests answer keys from learners.
- **Verification:** Full Laravel SQLite suite passed (80 tests / 1,129 assertions), including a custom-role regression proving the two permissions act independently. All 8 Chromium E2E tests passed, including assessment-only teacher authoring, learner no-answer-key access, and guardian dashboard overflow checks at 320, 375, 430, 768, 1024, and 1440 pixels. TypeScript/Vite build passed and Vitest passed (2 tests).
- **Next:** Milestone 0.93 corrected quiz scheduling to use workspace time; continue route-capability and timezone review, complete accessibility checks, and verify PostgreSQL CI when available.

## Milestone 0.91 — Scheduled exams and guardian calendar visibility
- **Status:** Implemented as an additive assessment migration and a tenant-scoped guardian summary projection.
- **Functionality:** Educators can set optional opening and closing times when publishing a quiz. Learners see the schedule, are prevented from starting outside the active window by both the UI and API, and can resume an already in-progress attempt. Guardians who retain both `courses.view` and `results.view` see future published exams for each linked learner's active course enrollments.
- **Privacy and compatibility:** Legacy assessments with no schedule remain immediately available. Upcoming exam queries use the active organization, linked student profile, and active enrollment course IDs; no learner attempt data is exposed in the upcoming list. Closing before opening is rejected by validation.
- **Verification:** Full Laravel SQLite suite passed (79 tests / 1,110 assertions); focused custom-role/schedule tests passed (8 tests / 61 assertions); Vitest passed (2 tests); TypeScript and Vite production build passed; all 7 Chromium E2E tests passed, including the new guardian upcoming-exam view. PostgreSQL migration and locking behavior remain unverified because no local PostgreSQL service is available.
- **Next:** Continue reviewing time-zone handling and assessment close-window semantics, then continue capability and accessibility audits. External provider integrations and deployment still need configured services/credentials.

## Milestone 0.90 — AI teacher drafting assistant
- **Status:** Implemented as a permission-gated API and workspace tool, using the configured OpenAI-compatible provider abstraction.
- **Functionality:** Teachers with effective `courses.manage` can request lesson plans, course outlines, learning objectives, MCQs, practice/homework questions, revision notes, or rubrics in English or Hindi. Topic, learner level, and bounded instructions produce an editable, explicitly unapproved draft; the app does not publish or persist the generated text.
- **Safety and cost controls:** Requests reject common email, Indian mobile, and 12-digit ID patterns; request content is not stored; provider output is size-checked; shared organization/user AI quotas, plan limits, per-minute throttling, token metering, timeout/fallback, and failure records apply. Requests do not include student/course data unless typed by the teacher.
- **Verification:** Laravel API suite passed (78 tests / 1,096 assertions), including the teacher-draft quota, PII rejection, permission, metadata, and unconfigured-provider fallback checks. Six Chromium E2E tests, 2 Vitest component tests, and the Vite production build passed; the educator browser flow exercises draft generation in the UI. The latest live npm audit found zero vulnerabilities.
- **Next:** Continue capability audit, especially optional AI and report permissions; expand AI evaluation/safety cases; PostgreSQL CI and real provider integration remain unverified until service/configuration is available.

## Milestone 0.89 — Academic report capability enforcement
- **Finding:** A custom teacher profile could retain `academic.view` and `attendance.read` while omitting `reports.view`; the reports endpoint checked only the former capabilities. The UI hid the route, but direct API access remained possible.
- **Fix:** Added the `viewReports` organization policy capability and require it in the academic reports API. Default teachers, educators, and owners already receive `reports.view`; narrowed custom teacher profiles must now explicitly retain it.
- **Regression coverage:** Added a feature test proving a custom teacher without `reports.view` receives 403. Existing manager and assigned-teacher report coverage still verifies allowed access and class scoping.
- **Verification:** Focused regression passed (1 test / 3 assertions). Full Laravel SQLite suite passed (78 tests / 1,096 assertions after milestone 0.90); Vitest passed (2 tests); Vite production build passed. Existing assigned-teacher report scope and manager report behavior remain green.
- **Next:** Continue the role-capability audit, especially live-session and course management endpoints; verify PostgreSQL CI when a runner/service is available.

## Milestone 0.88 — Educator course-authoring browser flow
- **Status:** Implemented as a Playwright integration test using an isolated synthetic educator session and API fixtures.
- **Coverage:** An educator with course and academic management capabilities creates a course, sees it in the course library, adds the first lesson, and sees the lesson details. Dashboard fixture data mirrors the API type so the test boots through the real workspace shell.
- **Fixture correction:** Bodyless API reads must not be parsed as JSON; the educator fixture now parses request bodies only on course/lesson creation. It also supplies the expected dashboard summary structure.
- **Verification:** Focused educator test passed, then the complete Chromium suite passed (6 tests); Vitest passed (2 tests); Vite production build passed; Laravel suite passed (76 tests / 1,078 assertions); live `npm audit --audit-level=moderate` found zero vulnerabilities (the sandboxed network request failed, and the authorized network retry succeeded).
- **Repository constraint:** The workspace is on `master` with no initial commit; all project files appear untracked. `.git` is read-only in this environment, so verified progress cannot be committed here.
- **Next:** Review remaining direct role-based checks for permission bypasses; continue responsive/accessibility review and validate on PostgreSQL CI. Physical devices and assistive technology still require external testing.

## Milestone 0.87 — Guardian permission-profile browser flow
- **Status:** Implemented as a Playwright browser integration test using synthetic, isolated API responses.
- **Coverage:** A linked guardian with only `courses.view` sees the learner learning summary and progress, does not see attendance or assessment results, and downloads the CSV summary. API fixtures omit disabled summaries, matching the backend privacy contract.
- **Verification:** Chromium Playwright suite passed (5 tests) at this milestone. Milestone 0.88 adds educator flow coverage (6 tests total). Vitest and production build passed (2 component tests). Laravel suite passed (76 tests / 1,078 assertions). The latest live npm audit reports zero vulnerabilities.
- **Next:** Educator course authoring coverage was added in milestone 0.88; continue capability-gated API audit and responsive/accessibility review; run PostgreSQL CI. Hosting, live AI/email/payment/conferencing credentials and real staging checks remain external release work.

## Milestone 0.86 — Learner lesson and quiz browser workflow
- **Status:** Implemented as a browser integration test using Playwright API fixtures; fixtures are isolated per test and do not write to the local database.
- **Coverage:** A seeded student session opens My learning and Courses, reads a lesson, marks it complete, starts a published quiz, saves a selected answer, submits the attempt, and sees the score. The test asserts the fixture API received each write.
- **Test correction:** Workspace initializes on Overview; the browser flow explicitly chooses My learning before opening Courses. Radio choices are activated by click, matching native input semantics.
- **Verification:** Playwright suite passed (4 tests) at completion of this milestone, including public-page responsive widths, keyboard navigation and accessible auth forms. The later guardian workflow expands the suite to 5 passing tests. Vitest passed (2 tests), frontend build passed, and Laravel passed 76 tests / 1,078 assertions.
- **Next:** Guardian browser coverage was added in milestone 0.87. Add an educator end-to-end workflow, continue capability audit and PostgreSQL validation. Physical device, alternate browser and assistive technology checks remain open.

## Milestone 0.85 — Playwright public-site responsive smoke suite
- **Status:** Implemented and executed against the local site on port `5174` using Chromium 159 and Node 24.
- **Coverage:** Browser tests check for horizontal overflow at 320, 375, 430, 768, 1024, and 1440 pixels; verify mobile navigation Escape-close, anchor navigation, and focus restoration; and exercise registration/sign-in mode switching and password visibility.
- **Accessibility fix:** The E2E flow exposed an ambiguous password field accessible name caused by the visibility button being inside its label. Added an explicit `Password` accessible name; the browser test now finds and toggles the field reliably.
- **CI:** The frontend job installs Chromium and runs the E2E suite after the Vitest component tests. Local E2E uses Node 24 because the current Playwright release requires Node 22 or newer.
- **Verification:** Playwright suite passed (3 tests); Vitest/RTL passed (2 tests); `npm run build` passed; live `npm audit` reported zero vulnerabilities. The existing dev server on `5174` was reused and left running.
- **Next:** Add course-player and learner workflow browser tests with synthetic API fixtures, then continue API-role audit and PostgreSQL verification.

## Milestone 0.84 — Frontend component-test foundation
- **Status:** Implemented with Vitest 4.1, jsdom, React Testing Library, and user-event.
- **Coverage:** Added interactive tests for narrowed guardian permission-profile creation and base-role-safe assignment options. `npm test` runs in thread workers to avoid a Windows sandbox temp-file rename failure; CI now runs the component suite before the production build.
- **Dependency security:** Initial audit found two moderate Vitest advisories. `npm audit fix` updated the compatible patch dependencies; an escalated live audit then reported zero vulnerabilities.
- **Verification:** Frontend suite passed (2 tests); `npm run build` passed. Full API suite remains green at 76 tests / 1,078 assertions. Broader component coverage and browser E2E/device testing remain.
- **Next:** Expand interaction coverage into course learning and assessment workflows, and add isolated synthetic API fixtures for browser tests.

## Milestone 0.83 — Capability-gated guardian course search
- **Status:** Implemented and regression-tested; no schema change.
- **Fix:** Workspace search no longer grants guardians course search results based only on the built-in role. Linked-child course results also require effective `courses.view`, including for custom guardian profiles.
- **Verification:** `npm run build` passed. Full SQLite/PHPUnit suite passed: 76 tests, 1,078 assertions. The guardian regression test confirms that a linked learner's private course is absent when the profile omits `courses.view`. The temporary PHPUnit bootstrap workaround was restored after execution.
- **Next:** Continue auditing capability-versus-role checks and replace confirmed permission bypasses with policy-backed checks; then proceed with frontend interaction coverage, responsive review, and PostgreSQL validation.

## Milestone 0.82 — Tenant-scoped custom guardian permission profiles
- **Status:** Implemented with the existing custom-role schema.
- **Management:** Managers can create, assign, archive, and restore narrowed guardian profiles. Guardian profiles retain required workspace and linked-student summary access; attendance, course progress, assessment results, and fee invoices can be independently removed.
- **Privacy:** Guardian summary queries execute only for enabled capabilities. The API omits disabled summary sections, and the guardian dashboard, CSV export, and workspace search only include authorized projections. Invoice listing remains independently protected by `fees.view` and linked-child scope.
- **Verification:** `npm run build` passed. Full API suite passed: 76 tests, 1,078 assertions, including guardian checks that disabled summary fields are absent, private courses stay out of search, and fee invoices return 403. Migration `.045` remains compatible; no new schema change was needed. PHPUnit required the documented temporary Windows/OneDrive bootstrap readability workaround; vendor source was restored afterward. PostgreSQL CI remains pending.
- **Next:** Audit manager/educator custom-role candidates and remaining direct role-string checks; add broader frontend and browser tests, then continue responsive/accessibility and PostgreSQL validation.

## Milestone 0.81 — Tenant-scoped custom student permission profiles
- **Status:** Implemented with additive migration `.045`.
- **Management:** Workspace owners, admins, and educators can create, edit, assign, archive, and restore narrowed permission profiles for active student memberships alongside teacher profiles. Profiles keep their `student` base identity; the required core is workspace, course, and results read. Managers can optionally remove attendance, fee invoice, live-session, and assessment-taking capabilities.
- **Authorization and privacy:** Assignment requires a matching base role. The student dashboard omits attendance and results queries when their capabilities are absent; fee invoice listing and assessment start are policy-gated server-side. Student invoice access remains limited to the learner's own profile. The Settings UI shows each profile's member type and filters assignment choices to matching profiles.
- **Verification:** `npm run build` passed. Full API suite passed: 75 tests, 1,068 assertions, including the student optional-capability and cross-base-role assignment checks. Migration `.045` applied locally. PHPUnit required the documented temporary Windows/OneDrive bootstrap readability workaround; vendor source was restored afterward. PostgreSQL CI remains pending.
- **Next:** Audit guardian-facing summaries and remaining role-string checks before enabling guardian profiles; then continue responsive/accessibility review and validate migrations and row-lock behavior on PostgreSQL.

## Milestone 0.80 — Tenant-scoped custom teacher permission profiles
- **Status:** Implemented with additive migration `.044`.
- **Management:** Workspace owners, admins, and educators can create, edit, assign, archive, and restore named permission profiles for active teacher memberships. Profiles retain their base `teacher` identity for assignment-scoped data access and may only select capabilities already available to teachers. Core workspace, academic, attendance-read, and course-read access is required.
- **Authorization:** A composite organization/custom-role foreign key prevents cross-tenant role assignment. Organization policies resolve the assigned permissions from that workspace's role record. Live-session read and management actions now check explicit capabilities so the base teacher role cannot bypass a restricted profile. Archiving retains the role record and resets assigned teachers to the standard profile; restore is supported.
- **Frontend:** The Settings page manages role definitions and assignment. Organization responses include the effective permissions so navigation and controls can follow custom task, attendance, course, and report access.
- **Verification:** Frontend TypeScript/Vite production build passed. Full API suite passed: 74 tests, 1,059 assertions (including cross-tenant, permission-escalation, teacher capability, and role archive/restore tests). Migration `.044` applied to the local development database. PHP syntax checks passed. PostgreSQL CI remains pending.
- **Next:** Extend editable profiles beyond the teacher base role after mapping remaining role-specific access checks; continue responsive/accessibility review and verify PostgreSQL migration/locking behavior.

## Milestone 0.79 — Public course dialog keyboard containment
- **Status:** Implemented in the course discovery dialog.
- **Behavior:** Tab and Shift+Tab now cycle within the open course details dialog. If focus moves outside while the dialog is open, the next Tab returns it to the first dialog control and Shift+Tab returns it to the last. Existing Escape close and trigger-focus restoration remain in place.
- **Verification:** `npm run build` passed (TypeScript check and Vite production build). Interaction was verified by code review; browser-based screen-reader and device checks remain outstanding.
- **Next:** Map the existing role-string checks to capabilities before adding persisted tenant-scoped custom roles; then continue responsive/accessibility review and validate migrations and row-lock behavior in hosted PostgreSQL CI.

## Milestone 0.78 — Public mobile navigation keyboard support
- **Status:** Implemented in the public landing page.
- **Behavior:** When the responsive navigation menu is open, Escape closes it and restores focus to the menu toggle. Choosing a section link also closes the menu and returns focus to the toggle. Existing `aria-expanded`, `aria-controls`, and labeled toggle text expose its state to assistive technology.
- **Verification:** `npm run build` passed. Behavior was verified by code review; a full device and assistive-technology session remains outstanding.
- **Next:** Continue responsive and accessibility review across public and workspace screens; hosted PostgreSQL validation remains pending.

## Milestone 0.77 — Recoverable task archive
- **Status:** Implemented with additive migration `.043`.
- **Staff workflow:** Tasks can be archived and restored from Overview or the dedicated Tasks page. The page has an Archived filter; archived records stay stored and can be restored. They must be restored before editing or completing.
- **Dashboard and search:** Archived tasks no longer count as pending or appear in workspace search.
- **Verification:** `npm run build` passed; full API suite passed: 70 tests, 1,034 assertions. PHP syntax checks passed and migration `.043` ran against local SQLite. The temporary PHPUnit bootstrap workaround was restored after tests.
- **Next:** Public navigation keyboard support was added in `.78`; continue responsive/accessibility review and broader ERP/LMS gaps.

## Milestone 0.74 — Task due-date entry
- **Status:** Implemented using the existing tenant-scoped task API and schema.
- **Staff workflow:** Both Overview and Tasks create forms can set an optional due date. The shared task cards display the due date in India-local date format; undated tasks remain labeled accordingly.
- **Verification:** `npm run build` passed. The API due-date persistence assertion passed in the full suite: 70 tests, 1,002 assertions. PHPUnit requires a temporary local workaround because Windows reports the OneDrive bootstrap as unreadable; the vendor file was restored after test execution.
- **Next:** Task editing was added in `.75`; continue responsive/accessibility review and validate PostgreSQL CI.

## Milestone 0.75 — Task editing
- **Status:** Implemented with tenant-scoped authorization and no destructive deletion.
- **Staff workflow:** Task rows on Overview and Tasks expose an edit action. Staff can update title, description, or due date, and clear optional description/date values. Successful edits refresh the row in place.
- **Security:** The update route checks task-management capability and loads the task through the active organization relation; cross-workspace updates return 404. Update requests validate field limits and reject empty bodies.
- **Verification:** `npm run build` passed; full API suite passed: 70 tests, 1,007 assertions. PHP syntax checks passed. The test run used a temporary local PHPUnit bootstrap readability workaround; the vendor file was restored immediately afterward.
- **Next:** Staff task assignment was added in `.76`; continue responsive/accessibility review and validate PostgreSQL CI.

## Milestone 0.76 — Staff task assignment
- **Status:** Implemented with additive migration `.042`.
- **Staff workflow:** Task forms can assign or unassign a task to an active owner, admin, educator, or teacher in the current workspace. Overview and Tasks display the assignee name. Student and guardian memberships never appear in the candidate list.
- **Security:** Server validation checks organization membership, active membership status, role, and user account status. Candidate listing requires `tasks.manage`; task rows expose only assignee name, not email.
- **Verification:** `npm run build` passed; full API suite passed: 70 tests, 1,017 assertions. PHP syntax checks passed and migration `.042` ran against the local SQLite development database. The PHPUnit vendor workaround was restored after test execution.
- **Next:** Recoverable archive/restore was added in `.77`; continue responsive/accessibility review and validate PostgreSQL in CI.

## Milestone 0.73 — Attendance dialog keyboard accessibility
- **Status:** Implemented in the shared class attendance workflow.
- **Dialog behavior:** Attendance opens with focus moved into the dialog, labels the dialog for assistive technology, traps Tab and Shift+Tab within the active controls, closes with Escape, and returns focus to the button that opened it.
- **Keyboard visibility:** Added a consistent focus-visible outline for links, buttons, form controls, and other tab stops in authenticated and sign-in screens. Reduced-motion preference now suppresses nonessential animation and transition motion in the main application.
- **Verification:** `npm run build` passed; interaction was validated by code review, not a live assistive-technology session.
- **Next:** Continue responsive and accessibility review across remaining modals and navigation; verify hosted PostgreSQL CI and review feedback retention before production collection.

## Milestone 0.72 — Help Centre and workspace feedback
- **Status:** Implemented and feature-tested; migration `.041` adds workspace-scoped feedback records.
- **Help Centre:** The sidebar opens searchable in-app answers for common roster, attendance, course, invitation, AI, and reports workflows. A `mailto:` link opens the user's email client for direct support.
- **Feedback:** Any active, verified organization member can submit a categorized 10–5,000 character message. Submissions are rate-limited and stored only in the active workspace. Owners, admins, and educators can review the latest 100 with submitter names; other roles cannot list feedback. No email or push delivery is implied.
- **Privacy:** The form warns users not to include student names, contact details, passwords, or confidential information. No email address is returned in the review API. Automatic expiry, reply, and resolution workflows remain future work.
- **Verification:** Focused tests passed (2 tests, 16 assertions); full API suite passed (70 tests, 1,002 assertions). `npm run build`, PHP lint, and migration checks passed.
- **Next:** Keyboard accessibility improvements were added in `.73`; continue responsive/accessibility review and verify PostgreSQL CI before release.

## Milestone 0.71 — Actionable weekly academic report
- **Status:** Implemented and API-tested; no migration was needed.
- **Dashboard action:** “This week” opens Reports with an India-time Monday-through-today date range. Reports also allow custom inclusive start/end dates and include the period in CSV export filenames.
- **Report semantics:** Date filters apply to attendance sessions, submitted assignments, and finalized assessment attempts. Course completion remains current-state. Existing class, learner, tenant, and teacher-assignment scopes still apply; inverted ranges are rejected.
- **Verification:** Academic report tests passed (3 tests, 39 assertions); full API suite passed (68 tests, 986 assertions). `npm run build` passed.
- **Next:** Help Centre and feedback workflows were added in milestone `.72`; continue remaining secondary views, then complete responsive/accessibility review and hosted PostgreSQL validation.

## Milestone 0.70 — Dedicated workspace task view
- **Status:** Implemented with the existing tenant-scoped task API; no migration was needed.
- **Staff workflow:** The Tasks destination lists all workspace tasks and supports Open, Completed, and All filters, completion actions, and task creation. The Overview shows up to four open tasks; “View all” opens the full task list.
- **Access:** The destination is shown only to owner, admin, educator, and teacher roles with task capabilities. Student and guardian workspaces do not load task data.
- **Verification:** `npm run build` passed. Full API suite passed: 67 tests, 974 assertions.
- **Next:** Actionable weekly reporting was added in milestone `.71`; continue support/help flows and other secondary views, then review responsive behavior and verify the hosted PostgreSQL CI job.

## Milestone 0.69 — Dedicated student roster
- **Status:** Implemented using the existing tenant-scoped student API; no migration was needed.
- **Staff workflow:** The Students page now has a searchable active roster and an inline add-student form for workspace managers. The roster displays student names and codes; class enrollment and attendance remain in My classes.
- **Access:** Manager roles can list/add workspace students; the API already limits teacher roster data to assigned classes. Student navigation is hidden from teachers, and switching workspaces returns users to an allowed page when their prior page is unavailable.
- **Responsive behavior:** The roster cards and create form reflow to one column on narrow screens; search has a screen-reader label.
- **Verification:** `npm run build` passed. Full API suite passed: 67 tests, 974 assertions.
- **Next:** The dedicated task list was added in `.70`; continue secondary views, accessibility/responsive review, and hosted PostgreSQL CI validation.

## Milestone 0.68 — Account profile editing and role-aware navigation
- **Status:** Implemented and verified; no migration was needed.
- **Account workflow:** An authenticated member can update their own display name from Profile. The API trims and validates the name (1–120 characters), returns the refreshed user, and does not permit changing another account or editing the email through this endpoint.
- **Navigation:** Teacher workspaces no longer show the Students destination, whose invitations/guardian-link view is restricted to workspace managers. Their assigned learner roster remains available from My classes and course views.
- **Verification:** Focused profile tests passed (2 tests, 13 assertions); the full API suite passed (67 tests, 974 assertions). `npm run build` and PHP syntax checks passed.
- **Next:** Dedicated roster view was added in milestone `.69`; continue the remaining secondary workspace views, accessibility/responsive review, and hosted PostgreSQL CI validation.

## Milestone 0.67 — Local website port consistency
- **Status:** Implemented and verified in the in-app browser.
- **Fix:** `npm run dev` no longer overrides Vite's configured loopback host; `npm run preview` also binds only to `127.0.0.1`. The frontend remains on port `5174`, separate from the other project on `5173`, and the API proxy remains on `8002`.
- **Verification:** Opened `http://localhost:5174/`; the public homepage rendered, and the live course and plan API sections loaded. Starting a second Vite instance correctly reports that `5174` is already occupied.
- **Next:** Continue from milestone `.66`; PostgreSQL workflow execution in hosted CI and responsive/release review remain.

## Milestone 0.66 — Rules-based learner study recommendations
- **Status:** Implemented and feature-tested; no database migration was needed.
- **Learner workflow:** The study planner suggests unsent upcoming assignments and courses with unfinished lessons. Each suggestion includes a reason and a suggested session length; “Use suggestion” fills the study focus, course, and duration so the learner can schedule it.
- **Data scope:** Recommendations are generated by the authenticated student dashboard from the learner's own active enrollments and upcoming published assignments. They do not rely on AI or external services.
- **Tests:** Focused StudentDashboard feature test passed: 1 test, 23 assertions. Full API suite passed: 65 tests, 961 assertions. `npm run build` passed.
- **Next:** Continue secondary workflows and accessibility/release review; PostgreSQL CI validation remains pending.

## Milestone 0.65 — Private assignment file attachments
- **Status:** Implemented and feature-tested on SQLite; migration `.040` creates tenant/course/assignment-scoped private attachment metadata.
- **Educator workflow:** Managers can attach reference files while creating assignments or add them later. Learners can submit up to five files with written work; authorized learners and educators can download resources and submissions they are allowed to view.
- **Security and limits:** Files stay on a private filesystem disk. Uploads accept PDF, DOC/DOCX, PNG/JPG/JPEG, and TXT up to 10 MB each and five files per assignment resource or submission. Download checks verify enrollment, submission owner, teacher assignment, and course scope. Responses use attachment disposition and `nosniff`.
- **Tests:** Focused upload/download/privacy/rejection tests passed: 2 tests, 24 assertions. Full API suite passed at milestone `.65`: 65 tests, 955 assertions. `npm run build`, migration `.040`, PHP lint, and route registration passed.
- **Deployment note:** Local storage is the development default. Production must configure `ASSIGNMENT_FILESYSTEM_DISK` to a persistent private disk; do not point it to the public disk.
- **Next:** Finish verification and continue secondary workflows and release review.

## Milestone 0.64 — Filterable academic reports
- **Status:** Implemented and feature-tested on SQLite; no schema migration was needed.
- **Educator workflow:** Academic reports can filter by active class and individual learner. Summary cards, student rows, and the existing CSV export use the selected server-side filters.
- **Access control:** Managers can choose active workspace classes. Teachers only receive their assigned classes and students; requesting an unassigned class returns 404, and student filters cannot widen the row scope.
- **Tests:** Full API suite passed: 63 tests, 931 assertions. `npm run build` passed.
- **Next:** Continue secondary workflow implementation and accessibility review. PostgreSQL migration/locking validation remains pending.

## Milestone 0.63 — Filtered assessment grade export
- **Status:** Implemented and API-tested on SQLite; migration `.039` from milestone 0.62 is applied to the local SQLite database.
- **Educator workflow:** Assessment reports can download a CSV with student, attempt, status, review state, points, maximum points, score percentage, and submission timestamp. The active student/date filters also apply to the export.
- **Access control:** Only course managers can export. Teacher results are limited to assigned students; tenant, course, and assessment scopes are checked server-side. Spreadsheet formula prefixes in text values are neutralized.
- **Tests:** `php vendor/bin/phpunit --no-configuration tests` passed: 63 tests, 921 assertions. `npm run build` passed; export route registration and PHP lint passed.
- **Next:** Filterable academic reports were added in milestone `.64`; continue broader platform workflow coverage and validate migrations/row-locking on PostgreSQL CI or staging.

## Milestone 0.62 — Reusable short-answer rubrics
- **Status:** Implemented and regression-tested on SQLite; migration `.039` adds optional rubric JSON to question bank entries and per-attempt criterion scores.
- **Educator workflow:** Short-answer authoring supports 2–10 criteria with percentage weights totaling 100. During review, educators score each criterion from 0–100, see the weighted points for the assessment's configured maximum, and can leave learner feedback. Questions without a rubric retain direct partial-point grading.
- **Access control:** Criterion weights and scores are calculated/validated on the server. Rubric definitions are included in manager question-bank and submitted-attempt review responses, and are omitted from active learner attempts and learner result responses.
- **Tests:** Covered weight validation, rubric withholding, complete criterion scoring, weighted partial credit, teacher review data, and learner feedback privacy. The full suite at milestone `.63` passes: 63 tests, 921 assertions. Frontend production build passes.
- **Next:** Grade export was added in milestone `.63`; PostgreSQL migration and row-lock validation remains.

## Milestone 0.61 — Filterable assessment analytics
- **Status:** Implemented and feature-tested on SQLite; no schema migration was needed.
- **Educator workflow:** Assessment analytics and attempt lists can filter by date range and student. The page exposes date and learner controls, blocks an inverted range, and refreshes report/review results using the active filters.
- **Access control:** Filters apply after tenant, course-manager, and teacher-assigned-student scopes. A teacher cannot widen a report by selecting an unassigned student; an empty match returns an empty report.
- **API:** Both assessment `analytics` and `attempts` GET endpoints accept optional `from=YYYY-MM-DD`, `to=YYYY-MM-DD`, and `student_id` filters.
- **Tests:** Full API suite passed: 63 tests, 894 assertions. `npm run build`, PHP lint, and route registration passed.
- **Next:** Rubric templates were added in milestone 0.62. PostgreSQL CI/staging validation and report usability/accessibility checks remain.

## Milestone 0.60 — Short answers and manual grading
- **Status:** Implemented and fully regression-tested on SQLite; migration `.038` adds learner response text, grader feedback/attribution, grading timestamps, and attempt review state. It is applied to the local SQLite database.
- **Educator workflow:** Question authoring includes a short-answer type with no option key. Submitted responses appear in attempt review; educators can assign partial points up to the configured maximum and leave feedback. Attempts become `pending` until all written responses are graded, then `reviewed`.
- **Learner workflow:** Written responses autosave and are submitted with the attempt. Learners can view their own answers, score, and educator feedback from the result panel. The result API does not return correct answer keys.
- **Reporting:** Pending written responses are excluded from finalized assessment averages and guardian/academic score summaries; the learner UI labels them as awaiting review.
- **Tests:** Full API suite passed: 63 tests, 885 assertions at milestone completion. `npm run build`, PHP lint, migration application, and route registration passed.
- **Next:** Filterable assessment reports were added in milestone 0.61. Add rubric templates; PostgreSQL migration and row-lock validation remains pending on CI/staging.

## Milestone 0.59 — Submitted attempt review
- **Status:** Implemented and regression-tested on SQLite; no schema migration was needed.
- **Educator workflow:** Course managers can choose a completed learner attempt and inspect its sampled questions, selected answers, correct answers, correctness, and points. The panel uses the attempt list already scoped to the course.
- **Access control:** Only course managers can review, teacher views are limited to assigned students, and in-progress attempts return 404 so answer keys are not disclosed before submission. Learners cannot call the review endpoint.
- **API:** Added `GET /organizations/{organization}/courses/{course}/assessments/{assessment}/attempts/{attempt}/review`.
- **Tests:** Full API suite passed: 63 tests, 843 assertions, including answer-key withholding during an open attempt and manager/learner permissions. `npm run build`, PHP lint, and route registration passed.
- **Next:** Short-answer responses and human grading were added in milestone 0.60. Add rubric templates and filterable assessment reports; validate PostgreSQL migrations/locking on CI or staging.

## Milestone 0.58 — Question-bank search and filters
- **Status:** Implemented; strict TypeScript and production build verified. No API or database changes were required.
- **Educator workflow:** The quiz builder can search question text and filter the selection list by topic and difficulty. Filters reset when switching courses, selected questions remain selected when hidden by filters, and a clear empty state appears when nothing matches.
- **Accessibility and responsive layout:** Controls have labels, support keyboard use without accidentally submitting the quiz form, and collapse to a single-column layout on narrow screens.
- **Tests:** `npm run build` passed (TypeScript check and Vite production bundle). API behavior remains covered by the milestone 0.57 full suite (63 tests, 836 assertions).
- **Next:** Submitted objective-attempt review was added in milestone 0.59. Add manually graded short answers and accessible report filters; validate PostgreSQL migrations/locking on CI or staging.

## Milestone 0.57 — Assessment analytics
- **Status:** Implemented and regression-tested on SQLite; no schema migration was needed.
- **Educator workflow:** Course managers can load assessment analytics from the quiz panel. The report shows total/completed attempts, average score percentage, pool/sample size, and a per-question table with presentations, correct answers, and accuracy.
- **Privacy and sampling:** Analytics omit learner names and identifiers. Teacher results respect assigned-student scopes. Question accuracy counts only completed attempts in which that question was presented, including per-attempt sampled exams.
- **API:** Added `GET /organizations/{organization}/courses/{course}/assessments/{assessment}/analytics`, restricted to course managers.
- **Tests:** Full API suite passed: 63 tests, 836 assertions, including sampled-question denominators, score aggregation, and learner denial. `npm run build`, PHP lint, and route registration passed.
- **Next:** Question-bank filters were added in milestone 0.58. Continue richer review and reporting; validate database migrations and row-lock behavior on PostgreSQL CI/staging.

## Milestone 0.56 — Randomized question-bank sampling
- **Status:** Implemented and regression-tested on SQLite; migration `.037` adds an optional per-attempt sample size to assessments.
- **Educator workflow:** A quiz can use every selected question or draw a configured number from the selected pool on each new attempt. The count is bounded by the pool size and the UI validates that relationship before publishing.
- **Learner workflow:** The sampled question IDs and presentation order are persisted together on the attempt. Resume returns the same sample. Learners cannot save answers for pool questions that were not selected; scoring and maximum points use only the sampled set.
- **Compatibility:** Existing assessments use their full question set. Randomized order can also be enabled independently of sampling.
- **Tests:** Full API suite passed: 63 tests, 826 assertions at milestone completion. `npm run build`, PHP lint, migration application, and route registration passed.
- **Next:** Assessment analytics was added in milestone 0.57. Add question-bank filters and validate database migrations/locking on PostgreSQL CI/staging.

## Milestone 0.55 — Validated question-bank CSV import
- **Status:** Implemented and feature-tested on SQLite; no schema migration or external package was needed.
- **Educator workflow:** Course managers can download a CSV template, upload a question file, inspect row-by-row validation, and import only after every row passes. Imported questions are added to the course question bank and selected in the quiz builder with the default one point each.
- **Format and limits:** CSV columns include prompt, type (`single_choice` or `multiple_select`), difficulty 1–5, topic, and two to eight option/correctness pairs. Correctness accepts true/false, yes/no, or 1/0. Uploads are limited to 1 MB and 100 non-empty questions.
- **Backend:** Course-scoped preview and import endpoints require course-management permission. Imports are transactional; one invalid row prevents every row from being written.
- **Tests:** Focused feature tests passed: 2 tests, 24 assertions, including both question formats, invalid rows, atomicity, and access control. Frontend strict TypeScript and Vite build passed.
- **Next:** Randomized question-bank sampling was added in milestone 0.56. Continue assessment analytics and question-bank filters; validate migrations on PostgreSQL CI/staging.

## Milestone 0.54 — Stable randomized quiz order
- **Status:** Implemented and regression-tested on SQLite; migration `.036` adds an opt-in assessment setting and stores each attempt's question order. Migrations `.034`–`.036` are applied to the local SQLite database.
- **Educator workflow:** Quiz publishing includes a “Randomize question order for each attempt” option. It defaults off, preserving the selected order for existing quizzes and newly created quizzes unless enabled.
- **Learner workflow:** A randomized order is shuffled once when an attempt is created and persisted with that attempt. Resuming an open attempt returns the same question order. Answer saves and scoring remain keyed by question ID.
- **Compatibility:** Existing attempts with no saved order fall back to authored question position. Question options and correctness privacy are unchanged.
- **Tests:** Full API suite passed: 61 tests, 788 assertions. `npm run build` passed (TypeScript check and Vite production bundle).
- **Next:** Validate migration and row-lock behavior in PostgreSQL CI/staging; then continue remaining release and accessibility gaps. PostgreSQL is unavailable locally and Git metadata is read-only in this environment.

## Milestone 0.53 — Course order ledger and sandbox capture
- **Status:** Implemented and regression-tested on SQLite; migration `.035` adds the tenant-bound course order ledger and an optional course-order reference on webhook events.
- **Implemented:** Eligible linked students can create a 30-minute order for a paid public course only when `PAYMENT_GATEWAY=sandbox`. Pending orders are reused; the amount is frozen at order creation. Authenticated buyers can read only their own order. A signed `course.payment_captured` sandbox event must match the exact amount and an unexpired order before it records the transaction reference and activates course enrollment.
- **Security and accounting:** Checkout order creation is rate-limited. The existing webhook remains disabled unless sandbox mode and a secret are configured. Event signatures cover exact raw JSON; duplicate event replay is idempotent, changed-body event reuse and transaction-reference reuse are rejected, and event storage retains only a body digest and order/payment link. No real funds move.
- **Tests:** Full API suite passed: 61 tests, 780 assertions, including invoice webhook regressions and course order capture/replay. `npm run build`, PHP lint, and route registration passed.
- **Scope:** This provides an order and sandbox webhook contract only. A production checkout adapter and end-user payment UI still require a selected provider and approved credentials.
- **Next:** Stable randomized quiz order was added in milestone 0.54. Continue PostgreSQL migration/locking validation; later integrate a provider checkout adapter when authorized provider credentials and configuration are available.

## Milestone 0.52 — Course pricing and safe paywall
- **Status:** Implemented and regression-tested on SQLite; migration `.034` adds integer INR paise pricing with a zero-price default for existing courses.
- **Implemented:** Course managers can set or update a course price in INR; API values and storage use integer paise. Public course summaries expose the price. Free public courses remain self-enrollable; paid-course self-enrollment returns HTTP 402 and creates no enrollment, including when price changes concurrently during the operation.
- **Frontend:** Course authoring, public catalog cards, course details, and the signed-in discovery page display prices. Paid courses clearly show that checkout is unavailable, and the enrollment control is disabled.
- **Tests:** Full API suite passed: 58 tests, 745 assertions. `npm run build`, PHP syntax checks, and `git diff --check` passed.
- **Scope:** No payment is collected or simulated. The next milestone must connect an order/checkout flow to verified provider events before paid access is granted.
- **Next:** Validate migration behavior in PostgreSQL CI, then implement a provider-neutral course order ledger and sandbox capture contract without live charges.

## Milestone 0.51 — Public course self-enrollment
- **Status:** Implemented and regression-tested on SQLite; existing enrollment tables and uniqueness constraints were reused.
- **Learner workflow:** Signed-in learners can browse public courses from a new workspace “Discover courses” page, enroll, and open the owning student workspace. Enrollment works only when the learner already has an active student membership and active linked student profile in the course's organization.
- **Access control:** The API rechecks that the course is published/public and the organization active, serializes enrollment with workspace suspension, rejects non-students and unlinked profiles, and returns the same active enrollment on retries. Private/draft courses are not enrollable through this endpoint.
- **Tests:** Focused catalog/enrollment tests passed: 6 tests, 41 assertions. Full API suite passed: 57 tests, 738 assertions. `npm run build` passed.
- **Scope:** This enables self-enrollment for publicly discoverable courses; course price, checkout, and educator payouts are not implemented, and this flow never collects payment.
- **Next:** Validate PostgreSQL CI and staging, then define course pricing and a provider-backed checkout contract before enabling paid marketplace access.

## Milestone 0.50 — Platform audit timeline
- **Status:** Implemented and regression-tested on SQLite; no schema changes were needed.
- **Implemented:** Allowlisted platform administrators can list the latest 25 audit events, with optional exact event and subject ID filters. The API returns actor ID, event, subject, and timestamp only; free-form audit details, notes, verification references, email addresses, and raw payloads remain hidden.
- **Frontend:** Platform administration displays recent actions with responsive wrapping and a clear indication that private notes and payloads are hidden.
- **Tests:** Full API suite passed: 54 tests, 721 assertions. The local `artisan test` bootstrap check is incompatible with this Windows/OneDrive PHP `is_readable` result, so PHPUnit was run directly with `--no-configuration` and the same SQLite test environment instead. `npm run build` passed.
- **Next:** Validate PostgreSQL CI, then continue marketplace enrollment and platform support/reporting gaps.

## Milestone 0.49 — Platform organization administration
- **Status:** Implemented and regression-tested on SQLite; no schema changes were needed.
- **Implemented:** Allowlisted platform administrators can search and paginate the organization directory, review aggregate active member/student/course counts and subscription status, and suspend or reactivate a workspace. Each status change is serialized and recorded in the platform audit log with the prior and new state and an optional note. Directory responses omit member contact details; suspension prevents tenant access without deleting data.
- **Frontend:** Platform administration includes a responsive organization search, usage summary, status control with confirmation, and pagination.
- **Tests:** Full API suite passed: 53 tests, 713 assertions, including role restrictions, search, privacy, status transitions, audit records, and suspension enforcement. `npm run build` passed.
- **Next:** Extend platform operations with privacy-scoped user/support tooling and feature configuration, then validate PostgreSQL CI and staging. Local PostgreSQL startup remains unavailable, and Git metadata is read-only in this environment, so no commit was created.

## Milestone 0.48 — Multi-question quiz builder
- **Status:** Implemented and regression-tested; existing assessment API/schema already supports question arrays, so no migration was needed.
- **Educator workflow:** Quiz creation can select multiple question-bank items, set points per question, a time limit, and allowed attempts. The question picker identifies format, topic, and difficulty; published order follows the list order.
- **Learner workflow:** One attempt displays the mixed question set, saves each single-choice or multiple-select response, and scores the configured total.
- **Tests:** Full API suite passed: 51 tests, 693 assertions, including a mixed-format, two-question quiz with per-question points and a two-attempt limit. `npm run build` passed.
- **Next:** Validate PostgreSQL CI and staging. Local PostgreSQL test-cluster initialization stalled, so PostgreSQL behavior remains unverified here.

## Milestone 0.47 — Multiple-select assessment workflow
- **Status:** Implemented and regression-tested on SQLite; existing schema already supported both question types, so no migration was needed.
- **Educator workflow:** Question authoring supports single-choice or multiple-select answer keys, topics, and difficulty levels 1–5.
- **Learner workflow:** Multiple-select quizzes use checkboxes, save selections automatically, and allow clearing every selection. The API continues to hide answer keys; scoring grants points only when the selected set exactly matches the correct set.
- **Tests:** Full API suite passed: 51 tests, 683 assertions, including multi-select creation, answer clearing, exact-match scoring, and zero score on an incorrect combination. `npm run build` passed.
- **Next:** Add multi-question quiz assembly from the existing question bank, then validate PostgreSQL CI and staging.

## Milestone 0.46 — Schedule closure uniqueness
- **Status:** Implemented and regression-tested on SQLite; no migration required.
- **Implemented:** The API rejects a second active school closure for the same workspace, academic year, and date. Cancelled closures can be replaced, preserving the existing status history.
- **Verification:** Focused schedule test passed (1 test, 34 assertions); full API suite passed (51 tests, 665 assertions). Browser loaded the public site at `http://localhost:5174/`.
- **Next:** Run the configured PostgreSQL CI job and complete staging/release checks. Docker is unavailable in this local environment.

## Milestone 0.45 — Opt-in public educator profiles
- **Status:** Implemented and regression-tested on SQLite; migration `.033` applied locally.
- **Implemented:** Linked teacher members can independently publish or hide a short public teaching title and introduction. A teacher can update only their own active profile; unlinked staff and other members cannot edit it.
- **Public discovery:** Public course cards and course details show only opted-in profiles linked to instructors assigned to that course. Private courses, inactive workspaces, and unpublished teacher profiles remain excluded.
- **Tests:** Full API suite passed after privacy-scoping unpublished profile drafts: 51 tests, 664 assertions. `npm run build` passed.
- **Next:** Run the configured PostgreSQL job in CI and complete staging/release checks. Docker is unavailable in this local environment, so the PostgreSQL service cannot be run here.

## Local development ports
- Frontend: `http://localhost:5174` (separate from the other project on 5173).
- Current API: `http://localhost:8013`; Vite proxies requests here. Port 8012 was unresponsive and is no longer used by this project.

## Milestone 0.44 — Dated schedule exceptions
- **Status:** Implemented and regression-tested on SQLite; migration `.032` applied to the local database.
- **Implemented:** Academic managers can create school closures, class/section cancellations, and dated extra lessons. Entries are tenant-scoped, constrained to an academic year, and cancelled through a status transition that preserves history. Extra lessons validate offered subjects and reject class/teacher time conflicts.
- **Frontend:** Schedule management includes a dated-change form and a retained active/cancelled list. Student and workspace dashboards remove cancelled recurring lessons, include extra lessons, and show closure or cancellation notices.
- **Tests:** Full API suite passed after subsequent milestones: 51 tests, 665 assertions. The schedule exception focused test passed: 1 test, 34 assertions. `npm run build` passed.
- **Next:** Continue closing marketplace and dashboard gaps, then validate PostgreSQL CI and staging readiness. Production payment, email, AI, and conferencing providers still need approved accounts/credentials.

## Milestone 0.41 — Responsive public website
- **Status:** Implemented; production frontend build passes and the running site was visually checked at 320px, 375px, 768px, and the default desktop viewport.
- **Implemented:** Small-screen navigation includes Platform, For educators, Courses, Pricing, and Sign in. The educator landing section explains workspace and course workflows. Published course cards open accessible detail dialogs with course, organization, subject, lesson count, and sign-in/registration actions. Course and pricing requests expose retryable error states.
- **Responsive fix:** Removed the body minimum that caused a horizontal scrollbar on narrow phones. Constrained hero grid sizing, compacted the phone header, and prevented tablet navigation labels from wrapping; checked document width at 320px, 768px, and desktop with no horizontal overflow.
- **Verification:** `npm run build` passed. The public API currently returns an empty course catalog, so course-dialog behavior is implemented but could not be exercised against a live public course.
- **Next:** Continue the ERP schedule workflow with dated exceptions and holidays, then validate the configured PostgreSQL CI job and staging smoke checks.

## Milestone 0.42 — Student attendance snapshot
- **Status:** Implemented and regression-tested on SQLite; no migration required.
- **Implemented:** Student dashboard now reports the learner’s recorded attendance count, present-or-late count, overall rate, and five most recent class records. The API scopes every value to the authenticated student and active organization.
- **Frontend:** A responsive learner dashboard card shows the attendance rate and recent class/date/status entries, including a clear no-records state.
- **Tests:** Full API suite passed: 49 tests, 613 assertions. `npm run build` passed.
- **Next:** Continue the highest-priority dashboard and ERP workflow gaps, then run PostgreSQL CI and stage smoke checks.

## Milestone 0.43 — Collision-safe timetable editing
- **Status:** Implemented and regression-tested on SQLite; no migration required.
- **Implemented:** Authorized workspace managers can update a weekly timetable entry. Updates validate the same tenant-owned class, section, subject offering, teacher, and time rules as creation, excluding the edited entry from conflict checks. Class and teacher overlaps remain rejected; foreign-organization entries are not addressable.
- **Frontend:** Schedule list entries expose an Edit action that loads the existing values into the weekly lesson form; Save changes and Cancel edit are available.
- **Tests:** Full API suite passed: 49 tests, 622 assertions. `npm run build` passed.
- **Next:** Add dated school closures and per-date timetable changes, then run PostgreSQL CI and stage smoke checks.

## Milestone 0.40 — Student study planner
- **Status:** Implemented and regression-tested on SQLite; migration `.031` applied to the local database.
- **Implemented:** Students can create upcoming study sessions, optionally attach an actively enrolled course, set a 5–240 minute duration, and mark a session complete. Plans remain private to the linked student profile and active organization.
- **Frontend:** The learner dashboard includes an accessible study-plan form and persisted upcoming/completed session list.
- **Security:** API writes require an active student membership and linked student profile. Course IDs must match an active course enrollment; users cannot read or complete another learner’s plans.
- **Tests:** Full API suite passed: 49 tests, 609 assertions. `npm run build` passed.
- **Next:** Continue the public website frontend audit and prioritized responsive improvements, then run PostgreSQL CI and stage smoke checks.

## Milestone 0.39 — Tenant-scoped workspace search
- **Status:** Implemented and verified on SQLite; responsive search results are connected to the authenticated API.
- **Implemented:** Search matches classes, learner names/codes, course titles/codes, and task titles. Results are capped and filtered by active workspace membership, teacher assignment, learner enrollment, or guardian links as applicable.
- **Frontend:** The top-bar search debounces requests, shows loading/empty/error states, and routes a selected result to its relevant workspace view.
- **Privacy:** Learners can find only their own profile and enrolled classes/courses; guardians can find only linked learners and their enrolled classes/courses; teachers see only their assigned learners, classes, and courses. Only roles with task read access see tasks.
- **Tests:** Full API suite passed: 48 tests, 595 assertions. `npm run build` passed.
- **Next:** Run the new PostgreSQL CI job and resolve any database-specific failures, then smoke-test in configured staging.

## Milestone 0.38 — Signed sandbox payment webhooks
- **Status:** Implemented and regression-tested on SQLite; migration `.030` was applied to the local database and adds provider attribution plus a minimal webhook event ledger.
- **Implemented:** A rate-limited sandbox endpoint verifies HMAC-SHA256 over the exact request body, accepts normalized captured-payment events in integer paise, records payments against tenant-scoped invoices, and handles exact redeliveries idempotently. A reused event ID with a changed body is rejected.
- **Security:** Endpoint returns 404 unless `PAYMENT_GATEWAY=sandbox`, returns 503 if its dedicated secret is missing, and returns 401 on invalid signatures. Raw payloads are not stored. No checkout, money movement, or live provider is integrated.
- **Tests:** Focused webhook tests passed (3 tests, 16 assertions); full API suite passed (45 tests, 576 assertions). Route registration verified. Frontend build was not needed because no UI changed.
- **Next:** Run the new PostgreSQL CI job and resolve any database-specific failures, then smoke-test in configured staging. Live payment integration requires an approved provider account and separate secret management.

## Milestone 0.37 — Scheduled live classrooms
- **Status:** Implemented and regression-tested on SQLite; migrations `.028` and `.029` applied locally.
- **Implemented:** Staff can schedule class/section or course sessions using an external HTTPS meeting URL. Active audience members can request the link only from 15 minutes before the start through the scheduled end. Staff control scheduled/live/completed/cancelled transitions and can attach an HTTPS recording link when completing a session.
- **Frontend:** Schedule page supports creating sessions, joining authorized meetings, updating status, opening authorized recordings, and taking attendance.
- **Attendance:** Staff attendance is written to the existing attendance session/record tables and linked to its live session. Roster completeness and class/section or course enrollment are checked.
- **Security:** Meeting URLs are never returned in session listings; join access is tenant/audience scoped and logged. Access logs show that a meeting link was requested, not verified attendance by the external provider.
- **Tests:** Full API suite passed: 42 tests, 560 assertions. `npm run build` passed.
- **Limitations:** Video remains external. Provider webhooks for verified join/leave events, calendar sync, recording retention controls, and automated attendance remain open.
- **Next:** Validate migrations on PostgreSQL and continue release preparation.

## Milestone 0.36 — Fee payment ledger and receipts
- **Status:** Implemented and regression-tested on SQLite; migration `.026` added the ledger and migration `.027` applied its composite invoice key to the existing local database.
- **Implemented:** Authorized workspace staff can record a reconciled payment against an issued invoice. The API tracks partial balances, marks fully paid invoices, rejects overpayments, and prevents voiding invoices after any payment. A request idempotency key prevents duplicate receipt creation on retries.
- **Frontend:** Staff can record payment amount, method, and transaction reference; students and linked guardians see payment totals, remaining balances, and receipt references.
- **Security:** Payment and receipt records are tenant-bound to the invoice and recording membership. This is a manual ledger entry, not a payment gateway or money movement.
- **Tests:** Full API suite passed: 41 tests, 529 assertions. `npm run build` passed.
- **Limitations:** Provider checkout, signed webhooks, automated reconciliation, receipt PDF, refunds, and accounting exports remain open.
- **Next:** Build an idempotent provider sandbox/webhook adapter, then validate financial migrations on PostgreSQL.

## Milestone 0.33 — Lesson video and document resources
- **Status:** Implemented and regression-tested on SQLite; migration `.023` applied to the local development database.
- **Implemented:** Course lessons can reference external video or document resources over HTTPS. Resource links are returned only through the already-authorized course lesson endpoint, so enrolled learner and staff access rules still apply. The Laravel app stores metadata only and never proxies media.
- **Frontend:** Course editors can attach, update, or remove a resource link; authorized learners see a link in the lesson.
- **Security:** HTTP links are rejected. The frontend opens resources in a new tab without granting the linked page access to the app window.
- **Tests:** Full API suite passed: 39 tests, 499 assertions. `npm run build` passed.
- **Limitations:** Upload/storage provider integration, signed URLs, and video playback/embed remain external work.
- **Next:** Continue LMS delivery with upload-provider integration contracts and prerequisites, then validate PostgreSQL migrations.

## Milestone 0.34 — Lesson prerequisite sequencing
- **Status:** Implemented and regression-tested on SQLite; migration `.024` applied to the local development database.
- **Implemented:** Managers can mark earlier active lessons as prerequisites. The API rejects prerequisites that are later, archived, outside the course, or duplicated. Learners see prerequisite titles and cannot start or complete a gated lesson until required lessons are complete.
- **Security:** Prerequisite relationships use composite organization/course/lesson foreign keys. Progress updates enforce the gate server-side.
- **Tests:** Full API suite passed: 40 tests, 507 assertions. `npm run build` passed.
- **Limitations:** Prerequisites are lesson-level and ordered; course-level prerequisites, cohorts, completion certificates, and a configurable completion threshold remain open.
- **Next:** Add course completion criteria and certificates, then validate the expanded migrations on PostgreSQL.

## Milestone 0.35 — Course completion certificates
- **Status:** Implemented and regression-tested on SQLite; migration `.025` applied to the local development database.
- **Implemented:** Completing every active lesson issues one immutable certificate snapshot per enrolled learner and course. The learner dashboard returns the private certificate record and its completion totals.
- **Frontend:** Learners can open a printable certificate with their name, workspace, course, issue date, completion count, and unique reference code.
- **Privacy:** Certificates are exposed only inside the learner's authenticated dashboard. No public verification endpoint is enabled.
- **Tests:** Full API suite passed: 40 tests, 512 assertions. `npm run build` passed.
- **Limitations:** Certificates snapshot completion when issued; later course edits do not revoke them. Public verification and design customization remain future work.
- **Next:** Validate PostgreSQL migrations, tenant constraints, and certificate UUID/FK behavior; then continue remaining marketplace, live-class, and payment workflows.

## Milestone 0.32 — Course instructor assignments
- **Status:** Implemented and regression-tested on SQLite; migration `.022` applied to the local development database.
- **Implemented:** Workspace owners, administrators, and educators can assign active teacher memberships to a course. Assigned teachers can access its course workflows alongside the original creator. Assignments are unique per course and backed by organization/course/membership foreign keys.
- **Frontend:** Course editors can select and save multiple instructors; course lists show assigned instructor names.
- **Tests:** Full API suite passed: 39 tests, 493 assertions. `npm run build` passed.
- **Limitations:** Instructor assignment grants course management access; student records returned in course workflows remain constrained by the teacher's active class or section scope. PostgreSQL and deployed checks remain pending.
- **Next:** Lesson media and resource links were added in milestone 0.33; validate PostgreSQL migrations and tenant constraints.

## Milestone 0.16 — Guardian links and family summaries
- **Status:** Implemented and feature-tested on SQLite; local development migration applied.
- **Implemented:** Academic managers can link/unlink active guardian memberships to student profiles. Guardians can read linked students’ attendance history/rate, enrolled course lesson completion, and recent assessment results. Ending a link immediately removes summary access.
- **Frontend:** The Students page manages guardian links. Guardian memberships open a dedicated My children portal with per-student summaries and CSV downloads.
- **Privacy:** Guardian routes require an active guardian workspace membership and return only profiles with an active explicit link. Guardian accounts skip teacher dashboard and task data requests.
- **Tests:** `php artisan test --compact` passed: 17 tests, 233 assertions. `npm run build` passed.
- **Next:** See milestone 0.17 for the implemented tutor foundation; live provider configuration remains external.

## Milestone 0.17 — AI tutor foundation
- **Status:** Implemented and integration-contract tested with a fake provider; local usage-ledger migration applied. A live provider call is unverified because provider credentials/model are not configured.
- **Implemented:** Configurable OpenAI-compatible chat adapter, authorized course/lesson lookup, lesson-context PII redaction, contact/identity pattern rejection, child-safe tutor instructions, ten requests/minute throttling, daily user/workspace quotas, and token-only usage accounting. Raw prompts and lesson excerpts are not persisted.
- **Frontend:** AI Learning includes course/lesson context selection, a privacy notice, tutor interaction, and a clear unavailable/error response.
- **Tests:** `php artisan test --compact` passed: 19 tests, 250 assertions. `npm run build` passed.
- **External dependency:** Configure an approved provider API key and model before any real prompt is sent; provider data-retention review remains required.
- **Next:** See the implemented 0.18 communication milestone; fee invoices follow below.

## Milestone 0.18 — Announcements and notifications
- **Status:** Implemented and tenant/audience-tested on SQLite; local development migration applied.
- **Implemented:** Academic managers publish immediate announcements to all active members or one member audience (students, guardians, or teachers). Matching members receive private in-app notifications with per-user read state; announcement visibility and notification reads are checked against the active workspace.
- **Frontend:** Managers can publish and review announcements. The notification bell shows unread activity and supports marking notifications read.
- **Database:** Added `announcements` and `workspace_notifications` with composite workspace/member foreign keys and recipient uniqueness.
- **Tests:** API suite at this milestone passed: 20 tests, 270 assertions. `npm run build` passed.
- **Limitations:** Delivery is in-app only and created synchronously; email, push, scheduled publication, and delivery workers are not configured.
- **Next:** Fee invoices are implemented in milestone 0.19; PostgreSQL validation is still pending.

## Milestone 0.19 — Fee invoices
- **Status:** Implemented and verified on SQLite; migration applied locally.
- **Implemented:** Workspace staff can issue a student fee invoice in INR with an optional due date and void an issued invoice. Amounts are stored as integer paise; invoice numbers are workspace-scoped. Students see only invoices for their linked student profile, and guardians see invoices only for actively linked children.
- **Frontend:** Fee invoices page provides staff issuance/void controls and student/guardian read-only lists.
- **Database:** Added tenant-scoped `fee_invoices` and composite foreign keys to the student and issuing staff membership.
- **Limitations:** Invoices do not collect or record payments; plans, entitlements, receipts, reminders, and payment provider integrations are not implemented.
- **Tests:** Full API suite passed: 21 tests, 285 assertions. `npm run build` passed.
- **Next:** Add a configurable subscription plan catalog and entitlement lifecycle, followed by payment support only through a verified sandbox.

## Milestone 0.20 — Student workspace access
- **Status:** Implemented; role-specific routes use existing tenant-scoped learning and invoice APIs. Frontend production build passes.
- **Implemented:** Student memberships now open in Courses, show a learner-oriented welcome, and do not request the staff dashboard or task feed. Student navigation includes only learning, AI learning, and fee invoices; management, reports, schedule, and attendance tools are not presented.
- **Tests:** `npm run build` passed. API authorization coverage remains in the full suite (21 tests, 285 assertions).
- **Limitations:** A dedicated student performance dashboard, upcoming lesson schedule, and study planner are not implemented.
- **Next:** Build subscription plans and tenant entitlements without triggering any live financial action.

## Milestone 0.21 — Plans and workspace entitlements
- **Status:** Implemented and API-tested on SQLite; local migration applied.
- **Implemented:** Platform admins (configured by `PAATHNEXUS_PLATFORM_ADMIN_EMAILS`) can create and update the global plan catalog, pricing in paise, and workspace entitlements. Owners can view plans and request changes. Free plan changes apply immediately; paid/custom plan requests remain pending until an administrator records a verified contract/payment reference. Plan and subscription decisions are written to `platform_audit_logs`.
- **Enforcement:** Configured `max_students`, `max_courses`, and `max_teachers` limits are checked during record creation. `ai_requests_per_day` constrains the existing daily workspace quota. Unconfigured entitlements do not impose a limit.
- **Frontend:** Owners have a Plan & billing screen. Allowlisted platform admins have plan editing and pending request review.
- **Database:** Added `subscription_plans`, `organization_subscriptions`, and `platform_audit_logs`; existing workspaces are assigned the seeded Free plan without deleting existing records.
- **Tests:** Full API suite passed: 24 tests, 312 assertions. `npm run build` passed.
- **Limitations:** No online payments or webhook reconciliation; administrators must verify and record an offline reference. No invoice receipts/refunds or subscription renewal job. PostgreSQL validation remains pending.
- **Next:** Milestone 0.22 added course/lesson editing. Continue with the learner dashboard, invitations, and reporting.

## Milestone 0.22 — Course and lesson editing
- **Status:** Implemented and API-tested on SQLite; frontend production build passes.
- **Implemented:** Course managers can edit title/description and switch a course between published and draft. Draft courses stay available to authorized staff for editing and hidden from student course lists. Managers can edit lesson content and archive/restore lessons. Archived lessons are excluded from student delivery while existing progress remains stored.
- **Security:** Course and lesson changes require course-management permission and tenant-scoped course/lesson relationships. Students cannot change course content.
- **Tests:** Full API suite passed: 26 tests, 340 assertions. `npm run build` passed.
- **Limitations:** Hard deletion is not offered to preserve learner history; archived lessons remain visible to course managers.
- **Next:** Milestone 0.23 adds a dedicated learner progress dashboard and upcoming class view; continue with invitations, reporting, and public marketing flows.

## Milestone 0.23 — Student learning dashboard
- **Status:** Implemented and API-tested on SQLite; no schema migration required.
- **Implemented:** Linked student memberships receive a tenant-scoped dashboard with active enrolled courses and lesson completion, upcoming published assignments and personal submission state, recent submitted assessment results, and upcoming timetable entries for their active class enrollments.
- **Privacy:** The route is restricted to active student memberships. Course, assignment, submission, result, class, and timetable data are scoped to the student's own active enrollment/profile.
- **Frontend:** Student memberships now open to My learning with course completion, upcoming work, today's class schedule, and recent results. Staff-only navigation and dashboard requests remain hidden from student sessions.
- **Tests:** Full API suite passed: 26 tests, 340 assertions. `npm run build` passed.
- **Limitations:** Study planning and attendance snapshot are not part of this dashboard yet. Schedule shows upcoming entries for the active academic year only.
- **Next:** Milestone 0.24 adds workspace invitations. Continue by replacing report/demo states with real data.

## Milestone 0.24 — Workspace invitations
- **Status:** Implemented and feature-tested on SQLite; local development migration applied.
- **Implemented:** Owners and administrators can create seven-day invitations for teacher, student, or guardian roles, list statuses, and revoke pending invitations. Invitees can register from a link or accept after signing into the invited email account. Invitations are single-use and stored as SHA-256 token hashes.
- **Frontend:** The Students page creates invitations and displays a one-time share link. The invite screen supports new account setup and existing-account acceptance. Link delivery is manual because no mail provider is configured.
- **Security:** Invitation tokens are email-bound, role-limited, hashed at rest, time-limited, revocable, and accepted within a database transaction.
- **Tests:** Full API suite passed: 28 tests, 361 assertions. `npm run build` passed.
- **Limitations:** No email delivery or invitation resend flow. Linking invited student/teacher accounts to profiles remains a separate administrator action.
- **Next:** Milestone 0.25 adds live academic summaries and CSV export. Public product pages and discovery remain.

## Milestone 0.25 — Academic reports
- **Status:** Implemented and API-tested on SQLite; no schema migration required.
- **Implemented:** Workspace owners, admins, and educators can review real attendance records, course completion, assignment submissions/grading, and assessment averages. Student rows include class enrollment and per-student metrics; CSV export is generated from the same API response.
- **Security:** The report endpoint requires active organization membership and academic plus attendance read access. Teachers are scoped to whole-class assignments in the active year; section assignments are excluded without student section enrollment data.
- **Tests:** Focused API test passed: 2 tests, 15 assertions. Full regression passed: 32 tests, 391 assertions. `npm run build` passed.
- **Limitations:** Assignment metrics count submitted work, not assignments still outstanding. Fee revenue and platform usage reports remain unimplemented.
- **Next:** Build public homepage, feature and pricing information, and course discovery with honest catalog visibility; then complete release hardening.

## Milestone 0.26 — Public product and course discovery
- **Status:** Implemented and API-tested on SQLite; local development migration applied.
- **Implemented:** Public homepage, feature summary, plan cards from the active catalog, searchable public course list, and responsive mobile layouts. Published courses are private to the workspace by default; managers explicitly opt in to public discovery. Public responses contain course summary metadata only, never lesson material or learner data.
- **Frontend:** Course managers can toggle public catalog visibility. The landing page offers sign-in, workspace registration, pricing, course discovery, and help contact links.
- **Tests:** Full API suite passed: 32 tests, 391 assertions. `npm run build` passed.
- **Limitations:** Discovery is informational and does not enroll or accept payment. Public course enrollment, marketplace purchasing, course instructor profiles, SEO metadata, and course images remain unimplemented.
- **Next:** Milestone 0.27 completes installable app-shell support. Continue release hardening: email verification and recovery, class-scoped teacher reports, PostgreSQL/CI/Docker deploy readiness, and operational monitoring.

## Milestone 0.27 — Progressive web app shell
- **Status:** Implemented; production bundle and app-shell file checks passed.
- **Implemented:** Added an installable web manifest and brand icon, service-worker registration for production builds, and app-shell/offline fallback for the public entry page and built frontend assets. `/api/*` requests are explicitly excluded from service-worker caching.
- **Tests:** `npm run build` passed. `node --check public/sw.js` passed; both root and public manifest JSON parse. Confirmed `dist/` includes `/manifest.webmanifest`, `/paathnexus-icon.svg`, and `/sw.js`. Offline browser behavior and device install QA have not been exercised.
- **Limitations:** Background sync, push notifications, queued offline submissions, and platform-specific install QA remain unimplemented.
- **Next:** Finish account verification/recovery, section roster support, remaining demo navigation, PostgreSQL validation, and deployment smoke/monitoring. The CI and Render/Docker starters are configuration-only until run in GitHub/Render; Docker is not installed locally.

## Milestone 0.28 — Teacher data access boundaries
- **Status:** Implemented and regression-tested on SQLite; no schema migration required.
- **Implemented:** Teacher academic class/roster, student, attendance, timetable, dashboard, and report responses now use active whole-class assignments in the active year. Teachers can manage only courses they created until instructor-course assignments are modeled; course learner rosters, assignment submissions, and quiz attempts are narrowed to students in those assigned whole classes. Teachers without an active assignment receive an empty scope. Section assignments remain excluded until student section membership is modeled.
- **Compatibility:** Legacy attendance snapshots without a class ID remain visible only to the teacher who recorded them; class attendance records are visible only inside the assigned class scope.
- **Tests:** Full API suite passed: 33 tests, 420 assertions. `npm run build` passed.
- **Limitations:** The teacher-to-course rule is an interim creator boundary; a formal instructor assignment model is still needed for co-teaching and institution course ownership. PostgreSQL and deployed CI remain unverified.
- **Next:** Email verification, PostgreSQL validation, and deployment smoke/monitoring.

## Milestone 0.29 — Password recovery
- **Status:** Implemented and feature-tested on SQLite; no schema migration required.
- **Implemented:** Sign-in includes a forgot-password flow. Reset requests use a generic response to avoid email enumeration; Laravel broker tokens expire and are single-use. A successful reset replaces the password, rotates the remember token, and revokes all existing Sanctum API tokens. Reset URLs target the configured `FRONTEND_URL`.
- **Delivery:** The app uses the configured Laravel mailer. Local setup defaults to the log mailer, so reset links are written to application logs until an SMTP or other mail transport is configured.
- **Tests:** Full API suite passed: 35 tests, 430 assertions. `npm run build` passed. Browser confirmed the recovery screen at `http://localhost:5174/`.
- **Limitations:** Email verification and provider delivery configuration remain pending. Existing or unknown addresses intentionally receive indistinguishable API responses.
- **Next:** Email verification, PostgreSQL validation, and deployment smoke/monitoring.

## Milestone 0.30 — Section enrollment and scoped teaching
- **Status:** Implemented and regression-tested on SQLite; migration `.021` applied to the local development database.
- **Implemented:** Students can be enrolled into active sections. Section membership now scopes teacher rosters, student lists, course rosters/submissions/attempts, timetable entries, attendance sessions and marking, dashboards, and academic reports. Students receive both class-wide and their section-specific timetable entries. Whole-class teacher assignments retain whole-class access; section assignments expose only their section, and section attendance requires an explicit section ID.
- **Frontend:** Class roster UI supports section enrollment and section-aware attendance for managers and teachers.
- **Security:** The section foreign key is composite with organization and class, preventing cross-workspace or cross-class section links. Regression coverage verifies a section A teacher cannot read or mark section B.
- **Tests:** Full API suite passed: 36 tests, 466 assertions. `npm run build` passed.
- **Limitations:** PostgreSQL validation and deployed smoke checks remain pending. Teacher course management still uses the creator boundary until formal course-instructor assignments are added.
- **Next:** Email verification, PostgreSQL validation, deployment smoke/monitoring, and replacing the interim course creator boundary with instructor assignments.

## Milestone 0.31 — Email verification enforcement
- **Status:** Implemented and regression-tested on SQLite; existing `email_verified_at` column reused.
- **Implemented:** New registrations and invited registrations receive expiring signed verification links. Unverified users cannot sign in or use authenticated workspace routes, including with previously issued tokens. Verification is tied to the account email hash. Resend responses are generic for known and unknown addresses and are rate-limited. Successful verification returns the user to sign-in.
- **Frontend:** Registration leads to a verification prompt, sign-in explains the requirement, and users can request a new link. Successful verification redirects to a sign-in notice.
- **Delivery:** The local log mailer writes verification links to Laravel logs. Inbox delivery requires an approved mail service. `APP_URL` now targets port 8002 locally; Render uses its externally assigned service URL.
- **Tests:** Full API suite passed: 38 tests, 481 assertions. `npm run build` passed. Browser verified the return-to-sign-in page; local API health returned `ok` through the port 5174 proxy.
- **Limitations:** Production SMTP/other mail transport and verification of delivered email on a real domain remain pending.
- **Next:** Configure production mail transport, validate PostgreSQL, and run deployment smoke/monitoring checks.

## Milestone 0.14 — Assignments, submissions, and grading
- **Status:** Implemented and feature-tested on SQLite; local development migration applied. PostgreSQL validation remains pending.
- **Implemented:** Course assignments with instructions, optional lesson links, due dates, and point totals. Enrolled students can submit and resubmit written work; course managers can review, score, and return feedback. Students see only their own submission and grade.
- **Frontend:** Courses page now supports assignment publishing, student text submissions, teacher submission review, and score/feedback entry.
- **Database:** Added tenant-scoped `assignments` and `assignment_submissions` tables with enrollment and course/lesson foreign keys.
- **Tests:** `php artisan test --compact` passed: 17 tests, 202 assertions. `npm run build` passed.
- **Next:** See milestone 0.15 for the completed question bank and quiz workflow.

## Milestone 0.15 — Question banks and timed quizzes
- **Status:** Implemented and feature-tested on SQLite; local development migration applied. PostgreSQL validation remains pending.
- **Implemented:** Course-scoped single-choice question banks, published/draft quizzes, timed and attempt-limited learner attempts, answer saving, automatic objective scoring, and learner/teacher results. The answer key is hidden from learners during attempts.
- **Frontend:** Course view supports creating questions and quizzes, taking/resuming quizzes, and viewing results.
- **Database:** Added tenant-scoped `questions`, `question_options`, `assessments`, `assessment_questions`, `assessment_attempts`, and `attempt_answers` tables with composite tenant/course/enrollment relationships.
- **Tests:** `php artisan test --compact` passed: 17 tests, 217 assertions. `npm run build` passed.
- **Next:** Guardian summaries and scoped exports were added in milestone 0.16.

## Milestone 0.13 — Courses and learner progress
- **Status:** Implemented and feature-tested on SQLite; local development migration applied. PostgreSQL validation remains pending.
- **Implemented:** Tenant-scoped course catalogs, ordered lessons, course enrollment, linked student accounts, and started/completed lesson progress with course completion summaries. Students can only see courses they are enrolled in and update their own progress; course managers can enroll learners and record progress for them.
- **Frontend:** New Courses page supports course/lesson creation, enrollment, student account linking, learner progress updates, and student self-progress.
- **Database:** Added `courses`, `course_lessons`, `course_enrollments`, and `lesson_progress`; student profiles can optionally link to one active student membership in the same workspace.
- **Tests:** `php artisan test --compact` passed: 17 tests, 186 assertions. `npm run build` passed.
- **Next:** Assessments and automatic scoring were added in milestone 0.15.

## Milestone 0.12 — Subject offerings and assignment history
- **Status:** Implemented and feature-tested on SQLite; local development migration applied. PostgreSQL validation remains pending.
- **Implemented:** Class/year/section subject offerings with duplicate and tenant checks. Timetable lesson creation now validates offered subjects when a class has offerings. Class teacher assignments can be changed or ended; assignment, reassignment, and removal events are retained and listed.
- **Frontend:** Schedule supports offering/ending subjects, changing/ending class teacher assignments, and reviewing assignment history.
- **Database:** Added `organization_subject_offerings`, `class_teacher_assignment_events`, and ended timestamps on offerings and class teacher assignments.
- **Tests:** `php artisan test --compact` passed: 17 tests, 160 assertions. `npm run build` passed. Local app/API ports 5174/8002 returned HTTP 200.
- **Next:** Courses, lessons, enrollment, and learner progress were added in milestone 0.13.

## Milestone 0.11 — Teacher membership links and class ownership
- **Status:** Implemented and feature-tested on SQLite; PostgreSQL validation remains pending.
- **Implemented:** Teacher profiles can link to one active teacher membership in the same workspace. Class teacher assignments are scoped to class, optional section, and academic year, with duplicate and tenant checks.
- **Frontend:** Schedule supports linking profiles to member accounts, assigning class teachers, and viewing assignments for the selected class/year.
- **Database:** `teacher_profiles.user_id` uses a composite workspace membership foreign key; `class_teacher_assignments` uses tenant-scoped class, section, teacher, and year references.
- **Tests:** `php artisan test --compact` passed: 17 tests, 129 assertions. `npm run build` passed. PHP syntax checks passed for the controller, migration, model, and feature tests.
- **Next:** Class/section offerings and assignment update/removal history were added in milestone 0.12.

## Milestone 0.10 — Subjects and timetable teacher assignments
- **Status:** Implemented and feature-tested on SQLite; PostgreSQL validation remains pending.
- **Implemented:** Workspace subject catalog, teacher profiles, timetable foreign keys for subject/teacher, tenant checks on catalog references, and teacher names in the Overview schedule.
- **Frontend:** Schedule page supports adding subjects and teacher profiles, then assigning both to new lessons.
- **Tests:** `php artisan test --compact` passed: 17 tests, 113 assertions. `npm run build` passed.
- **Next:** Teacher membership links and class-level assignments were added in milestone 0.11.

## Milestone 0.9 — Academic calendar and timetable
- **Status:** Implemented and feature-tested on SQLite; PostgreSQL validation remains pending.
- **Implemented:** Tenant-scoped academic years (one active year), class sections, weekly timetable entries, overlap validation, and dashboard filtering by the active year and organization-local weekday.
- **Frontend:** Schedule page supports year setup, section creation, and lesson scheduling; Overview shows real lessons for today.
- **Tests:** `php artisan test --compact` passed: 17 tests, 103 assertions. `npm run build` passed.
- **Next:** Subject and teacher catalogs were added in milestone 0.10.

## Milestone 0.8 — Workspace-backed dashboard
- **Status:** Implemented and feature-tested on SQLite; PostgreSQL validation remains pending.
- **Implemented:** Dashboard summary endpoint returns active student/class/enrollment counts, unassigned students, pending tasks, recent attendance trend, and a short class list. Fields are omitted or empty when the caller lacks the matching capability.
- **Frontend:** Overview cards and attendance chart use live workspace data; the former hardcoded timetable now lists actual classes. Dashboard summary refreshes when returning to Overview.
- **Tests:** `php artisan test --compact` passed: 16 tests, 86 assertions. `npm run build` passed.
- **Next:** Academic years, sections, and timetable entries were added in milestone 0.9.

## Milestone 0.7 — Academic roster and student attendance
- **Status:** Implemented and feature-tested on SQLite; PostgreSQL validation remains pending.
- **Implemented:** Tenant-scoped class and student creation/listing, enrollments, roster display, attendance marking for every active student, daily session upsert, and attendance rate calculation. Composite foreign keys protect enrollment and attendance records from cross-tenant links.
- **Frontend:** “My classes” supports class/student setup and enrollment for owners/admins; teachers can view rosters and mark present, absent, late, or excused. Attendance updates reload per workspace.
- **Tests:** The current full suite passed: 17 tests, 160 assertions. `npm run build` passed.
- **Next:** Dashboard summary and roster data are workspace-backed; timetable management was added in milestone 0.9.

## Milestone 0.6 — Attendance snapshots
- **Status:** Implemented and feature-tested on SQLite; PostgreSQL migration check awaits a configured local database credential.
- **Implemented:** Tenant-scoped listing and upsert of daily class attendance snapshots; organization timezone determines the session date; role capabilities protect read/write endpoints.
- **Database:** `attendance_sessions` stores class label, rate, date, recorder, and tenant-scoped uniqueness.
- **Frontend:** The first snapshot UI was superseded by the roster-based per-student workflow in milestone 0.7.
- **Tests:** `php artisan test --compact` passed: 14 tests, 58 assertions. `npm run build` passed.
- **Next:** Student roster and attendance records were added in milestone 0.7.

## Milestone 0.5 — Persistent workspace tasks
- **Status:** Implemented and feature-tested on SQLite; PostgreSQL migration check awaits a configured local database credential.
- **Implemented:** Tenant-scoped task listing, creation, and completion; task CRUD authorization for owners/admins/teachers; cross-tenant isolation; UUID task records.
- **Database:** `organization_tasks` migration stores creator, description, optional due date, status, and completion time.
- **Frontend:** Dashboard loads tasks for the selected workspace, creates and completes them through the API, and shows loading, empty, and error states.
- **Tests:** `php artisan test --compact` passed: 13 tests, 48 assertions. `npm run build` passed.
- **Next:** Classes, student profiles, enrollments, and student-level attendance were added in milestone 0.7.

## Milestone 0.4 — Capability authorization
- **Status:** Implemented and feature-tested on SQLite; PostgreSQL migration check awaits a configured local database credential.
- **Implemented:** Role-to-capability map, organization policy for viewing/updating org settings and listing members, active-account middleware that revokes suspended users' presented tokens, organization settings update endpoint, member list endpoint.
- **Database:** No new tables. Existing membership role string is mapped to capabilities in `api/config/organization_permissions.php`.
- **Frontend:** Workspace selection remains available; settings and members UI are not implemented.
- **Backend:** `OrganizationPolicy`, `EnsureActiveAccount`, `PUT /api/v1/organizations/{id}`, `GET /api/v1/organizations/{id}/members`.
- **Tests:** The current full suite passed: 17 tests, 160 assertions. Includes role denials, owner update, suspended account revocation, roster/attendance/catalog tenant checks, subject offerings, teacher profile linking and assignment history, timetable conflicts, restricted attendance details, live dashboard metrics, task capability checks, and multi-organization membership.
- **Security:** Membership status and account status checked server-side; non-members get 404; member roles lacking a capability get 403.
- **Deployment:** Local SQLite only; PostgreSQL on 127.0.0.1:5432 requested password authentication, and no PostgreSQL credentials are configured for this workspace.
- **Next:** Configure a development PostgreSQL credential, validate migrations, then add persistent academic resources with tenant tests.

## Milestone 0.3 — SPA sign-in and onboarding
- **Status:** Implemented; backend and production build verified, browser form visually checked at mobile width.
- **Implemented:** Sign-in and create-workspace tabs, accessible input labels and password reveal, password and organization fields, API error handling, session hydration, sign-out and profile identity, organization switcher for active memberships, tab-scoped active workspace selection, Vite API proxy on port 5174 to Laravel port 8002.
- **Database:** Uses existing local SQLite-backed API tables; form-created memberships persist through Laravel.
- **Frontend:** `src/AuthScreen.tsx`, `src/lib/auth.ts`, `src/auth.css`, `src/App.tsx`, `vite.config.ts`.
- **Backend:** Auth and organization API endpoints.
- **Tests:** `npm run build` passed; backend feature suite passed (8 tests, 29 assertions), including multiple organization memberships; `GET /api/v1/health` through the Vite proxy returned HTTP 200. Browser preview showed both sign-in and registration forms. No end-to-end browser submission was performed.
- **Security:** 60-minute bearer token kept in tab-scoped session storage; user can sign out to revoke token. Cookie-based HttpOnly session auth is a future hardening option.
- **Deployment:** Local only; frontend `http://localhost:5174`, API `http://localhost:8002` (ports 8000 and 8001 were already in use).
- **Blockers:** Git metadata remains non-writable; PostgreSQL has not been validated.
- **Next:** Finish role-based authorization after PostgreSQL checks.

## Milestone 0.2 — API identity and tenant core
- **Status:** Implemented and feature-tested on local SQLite; PostgreSQL deployment validation remains.
- **Implemented:** Laravel 13.43 API, Sanctum, versioned health endpoint, registration, login, current user, logout token revocation, organization create/list/show, UUID organizations, unique memberships and account status.
- **Database:** Migrations for organizations/membership pivot and user status; Sanctum personal access tokens. Local DB is SQLite; PostgreSQL template is provided.
- **Frontend:** Auth UI added in milestone 0.3; dashboard remains synthetic.
- **Backend:** `api/routes/api.php`, API auth and organization controllers, Eloquent relationships, CORS config.
- **Tests:** Current suite passes: 17 tests, 160 assertions across API identity, authorization, academic rosters, dashboard summary, tasks, subject offerings, and attendance; the identity-specific milestone originally passed with 8 tests, 29 assertions.
- **Security:** Register/login are rate-limited; passwords validated at 12 characters with mixed case and numbers; cross-organization access returns 404; membership writes use DB transactions; bearer tokens expire after 60 minutes. Email verification, audit logs, and capabilities are pending.
- **Deployment:** Not deployed.
- **Blockers:** Git metadata remains non-writable, so no commit.
- **Next:** Configure a PostgreSQL password for this workspace and validate migrations.

## Milestone 0.1 — Teacher dashboard shell
- **Status:** Implemented, not fully verified.
- **Implemented:** Vite React TypeScript entry point, responsive workspace sidebar, teacher dashboard cards, CSS engagement chart, schedule, persistent task interactions, attendance snapshot action, search and profile feedback states, PWA manifest metadata.
- **Database:** None.
- **Frontend:** `src/App.tsx`, `src/main.tsx`, `src/styles.css`, `index.html`, `manifest.webmanifest`.
- **Backend:** None; the UI is a local prototype.
- **Tests:** `npm run typecheck` and `npm run build` passed (strict TypeScript and Vite production bundle). Sign-in and onboarding layouts were visually checked at mobile width. No frontend behavioral test suite exists yet.
- **Security:** No student records or credentials included. All visible names and counts are synthetic demo content. No authentication or tenant enforcement exists yet.
- **Deployment:** Not deployed; no deployment URL.
- **Blockers:** Git metadata is not writable, so branch/commit could not be created.
- **Next:** Connect dashboard metrics/actions to tenant-scoped backend resources.

## Feature status
- Teacher dashboard: Workspace counts, class roster, tasks, attendance trend, and today's timetable with subject/teacher assignments are API-backed; some secondary actions remain unimplemented.
- Tasks: Persisted per organization through the authenticated API.
- Attendance: Daily per-student records and calculated rates persist per workspace/class.
- Auth and organization membership API: Implemented and tested.
- Auth UI and onboarding: Implemented; verified by build and browser visual check.
- Academic years, sections, weekly timetable, subject catalog, and teacher profiles: Implemented.
- Teacher account linking and class-level assignments: Implemented; update/removal history is pending.
- Custom permission profiles: Admin, educator, teacher, student, and guardian memberships can be narrowed by workspace managers; owners retain full control and cannot be assigned a profile.
- LMS, exams, guardian portal, AI tutor foundation, invoicing, plans, invitations, public catalog, academic reports, and PWA shell: core MVP workflows are implemented. Production payment/email integrations, PostgreSQL validation, deployment verification, and advanced modules remain.
