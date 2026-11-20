# Architecture

## Current state
The current repository has a responsive React + TypeScript public site and workspace SPA plus a Laravel 13 API. The SPA uses Sanctum bearer tokens via a Vite `/api` proxy. Organizations, memberships and invitations, academic rosters, attendance, schedules, courses and learning progress, assessments, guardian links, announcements, invoices, plans, and reports persist in the local SQLite development database. PostgreSQL is the deployment target but has not yet been validated.

## Target architecture
- Modular monolith: Laravel 13 REST API with PostgreSQL, Sanctum for first-party SPA authentication, policies and tenant-aware query scopes.
- React + TypeScript + Vite frontend with responsive layouts and an app-shell service worker; TanStack Query, React Hook Form, and Zod remain possible future additions.
- Redis for cache, queue, and rate limiting; external object storage for uploads; provider abstraction for AI, payments, and conferencing.
- Separate deployable frontend, API, queue worker, and scheduler services, with managed PostgreSQL and Redis-compatible storage.

## Tenant boundary
Every tenant-owned row carries an `organization_id`. Resolve the active organization from the authenticated membership, scope reads and writes server-side, authorize actions through policies, and verify parent-child relationships. Global support operations must be explicit and audited. Frontend switching is a convenience only, never an authorization boundary.

## Initial module boundaries
Identity and organizations; academic structure; people and enrollment; attendance; courses and content; assessment; guardian portal; analytics; AI learning; billing; communication; platform administration.

## Request flow
Browser SPA -> Sanctum-authenticated Laravel API -> policy + validation -> domain action -> PostgreSQL transaction -> queued events where needed. External providers are isolated behind interfaces and can be disabled gracefully.
