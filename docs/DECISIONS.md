# Decision log

## 2026-11-19 — Preserve announcement history when withdrawing
- **Decision:** Managers withdraw published or scheduled announcements from member views and can restore them to their prior state. Keep notification rows and read state; do not hard-delete announcement history.
- **Reason:** A mistaken announcement may need to be hidden quickly while preserving a recoverable record and avoiding duplicate deliveries on restore.
- **Trade-off:** Withdrawn notices remain visible to workspace managers, and notifications are hidden from members until the notice is restored.

## 2026-11-19 — Move the local API to port 8013
- **Decision:** Run the local Laravel API on loopback port 8013 and route Vite's `/api` proxy there.
- **Reason:** The process on 8012 held a listener but did not accept HTTP connections; a fresh server on 8013 returned the PaathNexus health response directly and through Vite.
- **Trade-off:** Keep the old 8012 process untouched and out of the active development path.

## 2026-11-19 — Publish scheduled announcements to the active audience
- **Decision:** Store future publish times in UTC from the workspace timezone input. Keep scheduled content manager-only until due, then deliver notifications to active workspace members matching the fixed audience at publish time.
- **Reason:** A scheduled message should not leak before its intended time, and audience filtering must be applied when recipient rows are created.
- **Trade-off:** Newly active members in the selected audience at publication time receive the announcement; deployments must run Laravel's scheduler every minute.

## 2026-11-19 — Narrow manager roles without inheriting wildcard access
- **Decision:** Support custom profiles for `admin`, `educator`, `teacher`, `student`, and `guardian` memberships. Owners cannot receive profiles. Each profile retains required core access and can select only capabilities configured for its base role; educator profiles use the explicit capability catalog rather than storing `*`.
- **Reason:** Institutions need least-privilege manager roles while preserving each membership's original data scope. Restricting profiles to their configured capability set prevents a profile from adding permissions beyond its built-in role.
- **Trade-off:** New capabilities must be added to the configured catalog before they can be selected in a custom profile. Archiving preserves assignments and narrowed permissions until restore or explicit unassignment so a role change cannot silently broaden access.

## 2026-11-19 — Extend narrowed permission profiles to students
- **Decision:** Allow tenant-scoped profiles for `teacher` and `student` memberships, recording each profile's immutable `base_role`. A student profile must retain workspace, course, and results read; attendance, fee invoice, live-session, and assessment-taking capabilities remain optional. Server policies compare the profile's base role to the active membership role.
- **Reason:** Student dashboard attendance, fee access, and taking assessments can be independently limited without changing the learner's identity or ownership scope. Results remain required because assessment attempt and feedback flows currently assume students can see their own results.
- **Historical trade-off, later resolved:** Guardian summaries aggregate multiple linked learners and needed a separate capability/data-projection audit before guardians could use custom profiles. That audit and guardian profiles were completed in milestone 0.82.

## 2026-11-19 — Keep custom teacher permissions separate from teacher data scope
- **Decision:** Store workspace-specific teacher permission profiles in a tenant-bound role table and keep each assigned membership's semantic base role as `teacher`. A profile may only select existing teacher capabilities and must retain core workspace, academic, attendance-read, and course-read access. Archived profiles retain their configuration, assignments, and narrowed permissions until restored or explicitly unassigned.
- **Reason:** Teacher assignment, course, and student access is scoped by the membership's teacher identity. Replacing that identity with a custom role string would bypass or break those established resource scopes. Permission profiles let managers tailor actions while retaining the existing teacher data boundary.
- **Trade-off:** Profiles can only narrow teacher permissions today. Other base roles and new permission grants require a separate audit of role-string checks and route policies. Role changes are logged in the existing audit store; a workspace audit explorer is still future work.

## 2026-11-18 — Start with a teacher-facing vertical slice
- **Decision:** Build the first interface as a teacher workspace dashboard with local demo actions.
- **Reason:** The repository had no application files. This creates a visible, coherent product foundation before introducing sensitive student data or backend assumptions.
- **Trade-off:** It is not yet connected to authentication, tenant APIs, or persistent data and must not be represented as a functioning ERP.

## 2026-11-18 — Follow the requested baseline stack
- **Decision:** Use React + TypeScript + Vite for the SPA and target Laravel 13/PHP 8.3+ for the planned API.
- **Reason:** Installed PHP is 8.4.11 and Node is 20.19.1. Laravel's official 13.x release notes list PHP 8.3 as the minimum; official React version documentation lists React 19.3 as latest on this date.
- **Trade-off:** Composer on the host emits PHP 8.4 deprecation notices, but dependency resolution, tests, and builds complete successfully.

## 2026-11-18 — Keep local services on separate ports
- **Decision:** Run the frontend on 5174 and Laravel API on 8002, with Vite proxying `/api` to the API.
- **Reason:** Port 5173 is used by another local project, while ports 8000 and 8001 are occupied by other local services.
- **Trade-off:** Local run instructions must start both development servers.

## 2026-11-19 — Avoid a local API port collision
- **Decision:** Keep the frontend on 5174 and run the PaathNexus API on 8012, with Vite proxying `/api` to that port.
- **Reason:** Port 8002 was serving an unrelated API, which sent PaathNexus sign-in requests to the wrong application.
- **Trade-off:** Local API startup and `APP_URL` use 8012; the conflicting service on 8002 remains untouched.

## 2026-11-18 — Start with configuration-based membership capabilities
- **Decision:** Keep the existing membership role string and map built-in roles to capabilities in server configuration, enforced by Laravel policies.
- **Reason:** This establishes server-side least-privilege checks without prematurely adding a custom role-management data model.
- **Trade-off:** Organizations cannot yet customize role grants; persisted custom roles are a later phase.

## 2026-11-18 — Restrict global plan administration to an explicit allowlist
- **Decision:** Authorize platform plan management by the normalized email values in `PAATHNEXUS_PLATFORM_ADMIN_EMAILS`; keep tenant owner permissions separate. Paid/custom plan changes remain requests until an allowlisted administrator records a verified reference.
- **Reason:** The platform has no persisted global administrator identity flow or payment provider credentials yet. This provides an explicit, deployment-controlled boundary without allowing users to elevate themselves or implying an online payment succeeded.
- **Trade-off:** The platform owner must configure the allowlist in the API environment, and paid plan fulfillment remains a manual reviewed step until a sandbox payment adapter and reconciliation flow are implemented.

## 2026-11-18 — Keep course discovery opt-in
- **Decision:** Public catalog pages only include active courses that an authorized course manager explicitly marks public; new courses default to private.
- **Reason:** Workspace course content belongs to the institution or educator unless they choose to advertise it. Public discovery responses need only expose course summary metadata.
- **Trade-off:** A separate marketplace purchase/enrollment flow is still needed before a visitor can access course material.

## 2026-11-19 — Store course prices in integer paise and block unpaid access
- **Decision:** Store course prices as integer `price_paise`, default existing courses to free, show prices in public discovery, and return payment-required for direct self-enrollment in any nonzero-priced course until checkout is integrated.
- **Reason:** INR prices need exact decimal arithmetic, and publishing a price must never imply that payment was collected or silently grant paid access.
- **Trade-off:** Paid courses can be advertised but cannot be self-enrolled until a provider-neutral order ledger and verified checkout event are implemented. No gateway is configured and no money moves.

## 2026-11-19 — Grant paid-course access only from a signed sandbox capture
- **Decision:** Create a short-lived, tenant-bound course order in explicit sandbox mode; unlock enrollment only after a signed capture for the exact snapshotted amount. Persist only the event digest and payment/order references for replay safety.
- **Reason:** Course prices and order requests must not be confused with successful payment. Exact-amount capture, expiry, tenant-scoped relations, and idempotent event handling form the payment boundary without real money movement.
- **Trade-off:** The backend sandbox contract is present, but no live checkout UI/provider adapter is enabled. Production paid access requires an approved provider integration and credentials.

## 2026-11-18 — Keep private API traffic out of the PWA cache
- **Decision:** Cache only the public application shell and built static assets in the service worker; `/api/*` requests remain network-only.
- **Reason:** Learner names, attendance, assignments, and results must not be retained in a shared browser cache across sessions.
- **Trade-off:** API data is unavailable offline until a consent-aware offline data model is designed.

## 2026-11-18 — Assign course instructors and link external learning resources
- **Decision:** Keep the original course creator as a course manager and add a tenant-scoped instructor assignment relation. Store only HTTPS links and a resource kind for lesson videos/documents; deliver them through the existing authenticated lesson response and let the browser open the provider resource. Allow prerequisites only from earlier lessons in the same course.
- **Reason:** Co-teaching requires explicit assignments, while course enrollment and existing class/section scopes must continue to protect learner records. External providers should handle media delivery rather than the Laravel API. Ordering prerequisites before dependent lessons makes the dependency graph acyclic and easy to validate.
- **Trade-off:** The platform does not upload, transcode, embed, sign, or retain media files. Providers with expiring links need a future storage integration; prerequisites remain lesson-level.

## 2026-11-18 — Issue private course completion certificates
- **Decision:** Issue one immutable completion snapshot once a learner has completed all currently active lessons. Keep certificate references inside the authenticated learner dashboard until a privacy-reviewed verification flow is designed.
- **Reason:** Learners and institutions need evidence of completion, and the app can verify its own progress records without implying external accreditation.
- **Trade-off:** Adding lessons later does not revoke an issued snapshot. Public validation and institution-specific certificate templates are not implemented.

## 2026-11-18 — Use external providers for live classrooms
- **Decision:** Store HTTPS meeting/recording links, release meeting links only through an audience-checked time-windowed join route, and log access without treating it as attendance proof. Staff attendance writes use the existing attendance ledger.
- **Reason:** Conferencing should handle media transport and provider-specific identity; the ERP remains the source of authorized schedules and institution-marked attendance.
- **Trade-off:** Calendar synchronization and verified join/leave events require an approved provider integration and signed webhooks.

## 2026-11-19 — Keep AI teacher outputs as unsaved review drafts
- **Decision:** Gate teaching-material generation on `courses.manage`, meter it through the shared AI quota and usage ledger, and return editable drafts without publishing or persisting generated text. Require teacher review before reuse.
- **Reason:** AI can accelerate lesson preparation while preserving educator ownership, limiting stored learner/provider data, and preventing unchecked generated material from becoming course content.
- **Trade-off:** Drafts must be copied or saved manually by the teacher; prompt filtering is only a partial detector and provider retention terms still need review before real learner data is sent.

## 2026-11-19 — Separate API liveness and readiness
- **Decision:** Keep `/api/v1/health` as a database-independent liveness response and add `/api/v1/health/ready` to probe the database with a generic `ready` or `not_ready` response.
- **Reason:** Orchestrators need to distinguish a running API process from one that can serve database-backed requests, while public health responses must not expose connection details.
- **Trade-off:** The starter Render blueprint continues to use liveness for its health check; operators can probe readiness separately to avoid coupling process restarts to transient database unavailability.
