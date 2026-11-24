# SmartSchool – project rules

Single-school management system. Laravel 13 (PHP 8.5), MySQL/MariaDB (SQLite in tests), Blade + Tailwind v4 + Alpine.js, Vite. PDFs via barryvdh/laravel-dompdf, QR via bacon/bacon-qr-code, webcam QR via html5-qrcode.

## Workflow
- Large requests: give a phased plan first and wait for approval. Implement one phase at a time; after each phase run tests/lint/build, report failures honestly, and wait for the go-ahead.
- Every schema change is a migration. Ask before anything destructive (dropping data, re-seeding a live DB, deleting files). Never run `db:seed` or `migrate:fresh` against the live database.
- Every change ships with tests and passes: `php artisan test`, `./vendor/bin/pint --test`, `npm run build`.
- Don't commit unless asked.

## Structure & conventions
- `app/Http/Controllers` – one controller per area (Students, Sections, Gradebook, Attendance, Finance, Portal, Chat, …). Simple list/create/edit screens are data-driven in `app/Crud/Resources.php` + `CrudController` (fields, rules, roles, filters, `locked` fields).
- `app/Services` – business rules (Grading, Billing, Attendance, Chat, Enrollment, Payroll). Keep rules out of views/controllers.
- `app/Support` – Role constants, Nav (sidebar + Ctrl+K palette), Qr, Photo, helpers (`ampm()`).
- Models use `$guarded = ['id']` and the `Auditable` trait (every create/update/delete → `audit_logs` with old/new values). Keep both.
- Settings are key/value in `settings` via `Setting::get/put` with defaults in `Setting::DEFAULTS`. Mode/feature flags must be read from one central settings service – never scatter checks.
- Roles in `App\Support\Role`; route groups use `role:` middleware; record-level checks live in controllers/policies (`User::canViewStudent`, teacher ownership of `ClassSubject`, adviser-only daily attendance, guard = gate scanner only).
- Views: layouts in `resources/views/components/layouts/{app,guest}.blade.php`; components `x-field`, `x-toggle`, `x-filters`, `x-page-header`, `x-empty`, `x-stat`, `x-tile`, `x-icon` (inline SVG, no emoji), `x-chat`.
- Times shown to users are 12-hour AM/PM (`ampm()`), dates like "Nov 25, 2026". Timezone Asia/Manila.
- Tests: `tests/Feature/SchoolTest.php` (seeds `DemoSeeder`, crawls every page per role), `tests/Unit/GradingTest.php`.

## Standing rules
**UI**: mobile-first and responsive (320/375/768/1024/1440, no horizontal scroll), 44px touch targets, WCAG AA, design tokens (spacing/type/color/radius/shadow) with dark mode, loading/empty/error/disabled/success states on interactive components, confirmation dialog on every destructive action, minimal color (one accent, color only for meaning), plain-language labels, SVG icons only.

**Backend**: validate all input server-side; authorize on the server (never trust hidden UI); parameterized queries only; no secrets in code (encrypt stored credentials); typed errors; structured logs with request IDs; rate limiting on auth/write/public endpoints; no stack traces to clients; secure cookies; security headers (CSP, HSTS, …); env validation at startup.

**Performance**: no N+1 queries, indexes for filters/joins, paginate lists, cache only where measured, optimize images and bundles.

**Privacy**: student data is personal data of minors (RA 10173). Collect the minimum, restrict by role, log access to sensitive records, keep retention rules.
