# SmartSchool

**SmartSchool** is a school management system for one school.

Laravel 13 + MySQL/MariaDB, Blade + Tailwind + Alpine (mobile-friendly).

## Install on Linux (one command)
```sh
curl -fsSL https://raw.githubusercontent.com/Charles494-prog/SmartSchoolWebsite/main/install.sh | bash
```
It installs what's needed, asks Offline (LAN) or Online, prepares the database and prints the address to open.
The mode chosen here (`SMARTSCHOOL_MODE` in `.env`) is the single source of truth, read through `App\Support\School`.

## Start / stop
- Double-click **SmartSchool** on the Desktop, or run `./start-school.sh` (opens http://127.0.0.1:8000).
- `./stop-school.sh` to stop.

## Demo accounts (password `Demo12345`)
admin@school.test (password `Admin12345`), registrar@, cashier@, teacher@, parent@, student@, librarian@, nurse@, guidance@, hr@, guard@ — all `@school.test`.

## Modules
Online admission (documents, approval) · student profiles, guardians, emergency contacts, medical notes · sections, subjects, teachers, timetable (clash check) · DepEd gradebook (WW/PT/QA weights, transmutation, submit/approve lock) · SF9 report card, SF10 permanent record · honor roll & ranking · promotion & transfers · QR ID cards + QR/USB/camera check-in, daily & monthly attendance, absent/late parent alerts · fees, discounts/scholarships, installment plans, payments & official receipts, statements of account, overdue reminders · parent/student portal · announcements, calendar, messaging, notifications · library · assignments & submissions · clinic & guidance (restricted) · employees & payroll (SSS/PhilHealth/Pag-IBIG/tax estimates, payslips) · analytics dashboard · audit log (every change incl. scores) · daily backups (`php artisan school:backup`, scheduled 01:00) · privacy notice & data export (RA 10173).

## Developer
```
php artisan migrate:fresh --seed                      # empty school (admin only)
php artisan migrate:fresh --seed --seeder=DemoSeeder  # sample school
php artisan test
npm run build
```
Email goes to `storage/logs/laravel.log` until you set `MAIL_*` in `.env` (e.g. Gmail SMTP).
