# Lutapp Architecture Plan

Status: Phase 2 architecture baseline

This document records the architecture agreed from the existing Lutapp codebase. It is intentionally additive: existing authentication, Notes, Bible tables, user data, and user-owned worktree changes remain in place until a later migration or feature phase explicitly updates them.

## Current foundation

- Laravel 13.18.1 with PHP 8.3.
- Blade server-rendered interface with Vite and Tailwind CSS 4.
- Eloquent models with the existing session guard.
- MySQL for local development and PostgreSQL as the deployment target.
- Database-backed sessions, cache, and queues.
- Existing domains: users, notes, tasks, events, hymns, Bible books, Bible verses, translations, bookmarks, highlights, and reading history.

## Domain boundaries

### Identity and preferences

`users` remains the authentication root. User-facing preferences belong in `user_preferences`; application locale and Bible translation are separate values. Future profile images and notification settings should extend preferences or use dedicated tables rather than adding unrelated columns to every domain table.

### Application localization

The interface language is independent of Bible content. A future `languages` table should contain stable locale codes such as `en`, `en-US`, `en-GB`, `yo`, `fr`, `de`, `es`, `pt`, `ar`, `zh`, and `hi`. User preference stores the selected locale. Blade, validation, mail, and JavaScript strings should resolve through Laravel localization resources.

### Bible content

The canonical content graph is:

`bible_translations -> bible_verses -> bible_books`

`bible_books` remains the canonical book catalog. `bible_verses.translation_id` is the canonical translation relationship. Importers must resolve a translation by code and must never write the legacy `translation` string column. Translation records must include license/provenance metadata and may only be activated when the content is legally distributable.

There must be one canonical reading-history table. Existing data must be inspected before choosing whether to preserve `bible_reading_history` or migrate to `bible_reading_histories`; no blind drop or rename is permitted.

### Notes

`notes` remains the owner-scoped root. Future additive tables should cover tags, categories, Bible verse links, attachments, reminders, and audit/security state. A note passcode is separate from the account password. Recovery must verify a hashed answer, rate-limit attempts, and complete an explicit passcode reset before reporting success.

### Daily content

Daily content is stored by effective date and content type. A refresh must read the same active record for the same date. Generation or editorial workflows may create records, but the reader must not generate fresh content synchronously on every request.

### Platform services

External providers are behind interfaces in `app/Services`:

- `AiProvider` for Bible and Notes assistance.
- `MapProvider` for geocoding and directions.
- `WeatherProvider` for current and city/church weather.
- `PaymentProvider` for intent creation and server-side verification.

Provider credentials stay in environment configuration and are never exposed to Blade or browser JavaScript.

## Migration rules

1. Inspect the live migration table and schema before writing a migration.
2. Prefer additive migrations and backfills over destructive changes.
3. Do not create a second table for an entity already represented by an existing migration.
4. Use Laravel schema APIs that work for MySQL and PostgreSQL; avoid vendor SQL such as `SHOW INDEXES`.
5. Do not rely on a hard-coded foreign-key ID such as translation `1`.
6. Make seeders idempotent with stable codes or natural keys.
7. Keep large Bible imports out of the default application seeder unless the data is explicitly present and licensed.
8. Test migrations against SQLite for fast feature tests and MySQL/PostgreSQL-compatible schema assumptions before deployment.

## Authorization boundaries

All user-owned resources must be authorized by policy or an equivalent owner scope. Future administrative content must use explicit roles and policies; normal users must not gain administrative capabilities through route exposure or mass assignment.

## Implementation gates

### Gate A: foundation and authentication

- Verify migration status without changing data.
- Fix registration verification delivery and unverified-user routing.
- Add feature tests for registration, verification, login, logout, reset, and authorization.

### Gate B: localization and first launch

- Add language selection before login/register.
- Persist locale preference and apply middleware consistently.
- Separate interface locale from Bible translation selection.

### Gate C: Bible and Notes hardening

- Resolve the duplicate reading-history design.
- Repair the importer and make translation seeding idempotent.
- Complete note recovery without plaintext answers or false success messages.
- Add tests for Bible ownership, translation filtering, note privacy, lockout, and recovery.

### Gate D: content and services

- Add stored daily content and editorial/admin boundaries.
- Add AI, maps, weather, and payments through provider contracts.
- Add books, music, hymns, videos, and photos only with upload validation and licensing metadata.

### Gate E: production readiness

- Add notifications, queues, policies, rate limits, file storage rules, audit logging, and deployment configuration.
- Run the full test suite, migration checks, route checks, build, and security review.

## Immediate implementation target

The next coding phase should be Gate A. It should be additive and limited to authentication correctness, migration inspection tooling, and focused tests. Bible imports, translation data, and existing Notes functionality must remain untouched until the live schema has been verified.
