# HOFARS: Hostel Facility Damage Report System

A role-based web app for reporting, assigning and tracking hostel facility repairs.
Students report broken facilities, an admin assigns technicians, technicians upload proof of the repair, and the admin reviews and closes each job.

Built with **PHP 8 (no framework), MySQL/MariaDB (PDO), vanilla JavaScript and plain CSS**, as a final year project.

<!-- Add screenshots here: docs/login.png, docs/admin-dashboard.png, docs/report-detail.png -->

## Features

**Students**
- Sign up, log in, report damage with an optional photo
- See all their reports with status filters, a full status timeline and technician proof
- Cancel a report while it is still pending

**Technicians**
- See only the jobs assigned to them, with the ones needing action listed first
- Start a job, post progress notes, and upload photos or videos as proof
- Send a finished job to the admin for review

**Admins**
- Dashboard with totals, average fix time, reports by facility and reports by status
- Search, filter and paginate all reports
- Assign or reassign technicians
- Approve completed work (closes the report) or send it back with a note
- Create technician and admin accounts

**General**
- Dark mode, responsive layout
- Profile page: username, matric number, profile picture, password change

## Report lifecycle

```
Pending → Assigned → In Progress → Under Review → Resolved
   ↓                       ↑______________|  (admin sends it back)
Cancelled (by student, only while Pending)
```

Every change is recorded in a timeline shown on the report page.

## Security

- Passwords hashed with `password_hash`; password hashes never leave the server
- All queries are prepared statements; all output is escaped
- CSRF token on every form; session ID regenerated on login; `HttpOnly` + `SameSite` cookies
- Login throttling (5 failed attempts → 60 second lock)
- Public sign-up can only create **students**; staff accounts are created by an admin
- Role checks on every page; users can only open reports they own or are assigned to
- Uploads: real MIME type checked, size limited, stored under random names outside direct web access (`.htaccess`), and served only through `download.php` after an access check
- Database credentials live in `.env` (not in the repo)

## Setup (Laragon / XAMPP)

1. Copy this folder to `C:\laragon\www\hofars` (or your web root).
2. Create the database and tables. Either run
   `mysql -u root -p < database/schema.sql`
   or import `database/schema.sql` with phpMyAdmin / HeidiSQL.
3. Copy `.env.example` to `.env` and put in your MySQL username and password.
4. (Optional) Load fake demo data from a terminal in the project folder:
   `php database/seed.php`
5. Open `http://hofars.test/` (Laragon) or `http://localhost/hofars/`.

### Demo accounts (after running the seed)

Password for all accounts: `Demo@1234`

| Role | Email |
|---|---|
| Admin | admin@hofars.test |
| Technician | tech1@hofars.test, tech2@hofars.test |
| Student | aisyah@hofars.test, daniel@hofars.test, priya@hofars.test, hafiz@hofars.test |

Without the seed, sign up as a student on the login page. To create the first admin, run the seed or insert one admin row manually.

## Project structure

```
index.php                 login + sign-up
student_hofars.php        new report / my reports
technician_hofars.php     assigned jobs
admin_hofars.php          dashboard, filters, assignment
admin_users.php           create staff accounts
report.php                report detail, timeline, work reports, review actions
profile.php               profile + password
download.php              access-checked file serving
includes/bootstrap.php    config, DB, session, security helpers
includes/layout.php       shared header / footer
database/schema.sql       tables + facility list
database/seed.php         fake demo data (CLI only)
assets/                   style.css, app.js
```

## Deploying a live demo

Any PHP 8 + MySQL host works (for example InfinityFree, 000webhost-style free hosts, or a small VPS).
Upload the files, import `schema.sql`, create `.env`, set `APP_DEBUG=false`, and serve over HTTPS.
Make sure the `uploads/` folder is writable and that your host honours `.htaccess` (Apache).
On Nginx, add a rule denying direct access to `/uploads/`.

## Ideas for next steps

- Email or in-app notifications on assignment and resolution
- Password reset by email
- Export reports to CSV or PDF
- Automated tests
