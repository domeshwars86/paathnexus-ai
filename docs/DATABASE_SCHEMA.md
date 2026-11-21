# Database schema plan

SQLite is used for the local development database and tests. PostgreSQL remains the deployment target. The initial users, Sanctum token, organizations, membership, and account status tables are implemented.

## Core entities
- `users`: global identity, verified email/phone, password hash, status, timestamps.
- `organizations`: name, slug, type, locale, timezone, status, JSON settings, timestamps.
- `memberships`: organization, user, status, joined_at; unique `(organization_id, user_id)`.
- `roles`, `permissions`, `membership_roles`: organization-scoped role assignments and permission mapping.
- `academic_years`: organization, name, start/end dates, status.
- `classes`, `sections`, `subjects`: organization-scoped academic structure with validated parent links.
- `student_profiles`, `teacher_profiles`, `guardian_links`: organization-scoped profile relationships. Student and teacher profiles may link to a same-workspace membership; teacher public title, bio, and opt-in flag are stored on the teacher profile.
- `class_teacher_assignments`: workspace, class, optional section, teacher profile, academic year, assigning user, timestamps, and optional end time.
- `class_teacher_assignment_events`: append-only assignment, reassignment, and removal history with actor and old/new teacher references.
- `organization_subject_offerings`: workspace, class, optional section, subject, academic year, creator, timestamps, and optional end time.
- `courses`: workspace, unique course code, title, description, optional subject/year, status, integer `price_paise` (zero for free), public visibility, and creator.
- `course_lessons`: tenant-scoped ordered lesson content linked to a course, with optional external HTTPS resource type and URL metadata.
- `course_instructors`: unique course-to-teacher assignments scoped by organization; composite foreign keys require both the course and active-workspace membership records to belong to that organization.
- `course_lesson_prerequisites`: same-course directed prerequisite edges; both lesson references are tenant/course-scoped composite foreign keys.
- `course_certificates`: one immutable completion snapshot per organization/course/student, with unique private reference code and issue timestamp.
- `live_sessions`: workspace-scoped external meeting schedule for one class/section or course, HTTPS meeting/recording URLs, lifecycle status, and creator membership.
- `live_session_accesses`: unique workspace/session/member access log with first/last request time and count; not a provider attendance assertion.
- `course_enrollments`: tenant-scoped course/student links with active/withdrawn status and enrolling user.
- `course_orders`: tenant-scoped paid-course orders with buyer/student/course references, frozen integer paise total, pending/paid/expired state, provider/event/transaction references, and 30-minute expiry. Composite foreign keys keep course, learner, and buyer membership in one organization.
- `lesson_progress`: per-enrolled-student lesson status, completion timestamp, and last updating user.
- `assignments`: course/optional lesson, title, instructions, due date, maximum points, lifecycle status, and creator.
- `assignment_submissions`: enrolled learner's text response, submitted timestamp, grade, feedback, grading user, and status.
- `questions`, `question_options`: tenant-scoped prompts and answer options, including a private correctness key.
- `assessments`, `assessment_questions`: course quiz settings (optional randomized question order and sample size) and ordered question/point assignments.
- `assessment_attempts`, `attempt_answers`: enrolled learner attempts, time limits, persisted sampled question IDs/order, selected options or written responses, objective/manual scores, grader feedback and attribution, and submitted/reviewed timestamps.
- `guardian_student_links`: tenant-scoped guardian-to-student access, relationship label, linking actor, active/ended status, and revocation timestamp; composite tenant foreign keys bind both parties to workspace memberships/profiles.
- `ai_usage_records`: workspace/member, AI feature, outcome, token counts, configured model, and timestamps. Prompt text and lesson excerpts are not stored.
- `announcements`, `workspace_notifications`: workspace announcement content, optional scheduled publication time, optional prior status while withdrawn, and per-member announcement delivery/read state. Composite foreign keys bind authors and recipients to active-workspace membership rows.
- `fee_invoices`: student fee amounts stored in paise, due date, issued/partially-paid/paid/void lifecycle, and issuing workspace member. Composite foreign keys prevent assigning an invoice to a student or issuer in another workspace.
- `fee_payments`: tenant-scoped reconciled receipt ledger, integer paise amounts, payment method/reference, unique idempotency key and receipt number, and optional recording workspace member. Composite keys bind every invoice and non-null record author to the same workspace. Source and provider event fields distinguish sandbox webhook entries from manual entries. The `(organization_id, received_at, id)` index supports tenant-scoped, stable date-range collection reports.
- `payment_webhook_events`: minimal provider event digest, outcome, and optional fee-payment or course-order link for signature-verified replay handling; raw webhook bodies are not retained.
- `platform_usage_snapshots`: one row per UTC date with aggregate platform workspace, active membership, learner, course, and subscription counts; it stores no tenant or person identifiers.
- `student_study_plans`: private learner study sessions with optional enrolled-course association, scheduled time, duration, and planned/completed lifecycle; composite organization/student and organization/course references preserve tenant integrity.
- `subscription_plans`, `organization_subscriptions`: global plan catalog with JSON entitlements and a current/requested plan per workspace. Existing workspaces are assigned the seeded Free plan during migration.
- `platform_audit_logs`: platform plan and subscription decisions with actor, subject, event, and review details.
- `platform_content_moderations`: platform-only hide/visible state for opted-in public courses and educator profiles, keyed by content type and UUID; tenant content fields are preserved, with an optional moderation reason, actor, and timestamp.
- `organization_invitations`: workspace, target email, restricted role, hashed single-use token, inviter, expiry, acceptance, and revocation timestamps.
- `enrollments`: organization, student, class/section, academic year, status.
- `attendance_sessions`, `attendance_records`: organization, class/optional section/date, recorder, student, status; unique student/session.
- `courses`, `modules`, `lessons`, `course_enrollments`, `lesson_progress`: content and learning progress.
- `assignments`, `submissions`, `questions`, `exams`, `exam_attempts`, `exam_answers`: assessment lifecycle.
- `audit_logs`: actor, organization, action, resource type/id, redacted metadata, timestamp.

## Implemented details
- `organizations`: UUID primary key, unique slug, type/status, locale/timezone, JSON settings.
- `organization_user`: FK to organization and user, role/status/joined_at, unique organization/user pair and membership lookup index.
- `organization_tasks`: UUID primary key, organization FK, creator FK, optional staff assignee FK (set null if the account is deleted), title, optional description/due date, pending/completed status, completion and archive timestamps, and tenant/status/due date, tenant/assignee/status, and tenant/archive/due date indexes.
- `support_feedback`: UUID primary key, organization and submitter FKs, category, message, open/resolved status, optional manager response and responder/time metadata, timestamps, and organization/creation-time plus organization/status indexes. Workspace deletion cascades; submitter deletion is restricted so the submission remains attributable; responder deletion clears the responder reference.
- `workspace_feedback_notifications`: per-submitter/workspace notification for the latest feedback response or status change, with read and creation timestamps. Unique feedback/member delivery plus composite workspace-feedback and workspace-membership FKs prevent cross-tenant notification links; feedback or membership deletion cascades.
- `attendance_sessions`: UUID primary key, organization and recorder FKs, class label, optional class and same-class section FKs, optional live-session link, organization-local session date, and attendance rate.
- `organization_classes`: UUID, organization, name, grade/section and status; class names are unique per organization.
- `student_profiles`: UUID, organization, workspace-unique student code, name, and status.
- `student_profiles.user_id`: nullable unique same-workspace membership link for a student account.
- `enrollments`: tenant-scoped class/student links, optional class section, and composite foreign keys to prevent cross-organization or cross-class enrollment.
- `attendance_records`: tenant, session, student, recorder, and present/absent/late/excused status; unique student per session, with tenant-scoped student FK.
- `academic_years`: tenant-scoped name, date range, and planned/active/completed status; unique name per organization.
- `organization_sections`: tenant-scoped section attached to a class, with composite class FK and per-class unique name.
- `timetable_entries`: tenant, class, optional section, academic year, subject label, weekday, time range, and room; composite tenant/class/year/section constraints.
- `schedule_exceptions`: tenant/year/date-bound closures, class or section cancellations, and extra lessons. Composite tenant FKs constrain classes, sections, timetable entries, subjects, and teachers; cancelled entries keep their status, actor, and timestamp for history.
- `organization_subjects`: workspace-unique subject code and name.
- `teacher_profiles`: workspace-unique employee code and teacher name; optional `user_id` has a composite FK to a workspace membership and is unique per organization.
- `class_teacher_assignments`: composite tenant-scoped FKs to class, teacher profile, year, and optional class section; records the assigning user and end time.
- `class_teacher_assignment_events`: immutable action snapshots with tenant-scoped references to the assignment and old/new teacher profiles.
- `organization_subject_offerings`: composite tenant-scoped FKs to class, section, year, and subject; active duplicate checks are performed while locking the parent class.
- `courses`: workspace-unique course code; optional composite tenant-scoped subject and year references; public catalog opt-in defaults false; price uses integer paise and defaults to zero.
- `course_lessons`: composite workspace/course FK and unique position within a course.
- `course_enrollments`: composite tenant-scoped course/student references and one row per learner/course.
- `lesson_progress`: composite tenant-scoped references to a course lesson and active enrollment; unique per learner/lesson.
- `assignments`: composite tenant-scoped course and optional course-lesson references; maximum points stored as decimal.
- `assignment_submissions`: composite tenant-scoped assignment and course-enrollment references; unique learner/assignment pair, decimal score, and grading audit fields.
- `assignment_attachments`: tenant/course/assignment-scoped private file metadata, optional submission association, uploader, disk/path, original filename, detected MIME type, and size. File bytes stay on the configured private filesystem disk.
- `timetable_entries.subject_id` and `teacher_profile_id`: optional composite tenant-scoped catalog references; the subject label remains for legacy entries.
- `users.account_status`: active by default.
- `personal_access_tokens`: Laravel Sanctum token storage.
- Membership roles remain string values in `organization_user.role`; initial role-to-capability grants are configured in `api/config/organization_permissions.php`. The membership pivot stores nullable `custom_role_id`, which references the tenant-scoped `organization_roles` table. Profiles have immutable base-role identity and permission arrays; owners are not eligible for a custom profile. Archiving retains the profile and assignments so permissions do not silently widen.

## Constraints and indexes
Use UUIDs or ULIDs for externally exposed identifiers. Add foreign keys and unique constraints; index tenant ID with common filtering keys. Include `organization_id` on tenant-owned rows and enforce compound relationship consistency in validation and policies. Prefer soft deletion for user-facing records where audit/history needs retention. Define data retention before collecting minors' data.

## Isolation requirement
Tests must attempt cross-organization reads, updates, and relationship injection for every tenant-owned resource. Return non-disclosing 404/403 responses consistently. A client-provided organization ID never grants access.
