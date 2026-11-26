# UCUREC Ethics Review Tracking System

Capstone web application for the Urdaneta City University Research Ethics Committee (UCUREC):
receive research applications, screen them for completeness, coordinate reviewers, handle
revisions, and release official decisions.

Stack: Laravel 12 · Blade + Bootstrap 5 · MySQL/MariaDB · PHPUnit

## Build status

| Phase | Scope | Status |
|---|---|---|
| 0 | Setup, sign-in, researcher sign-up + activation, roles, user management, audit log | Done |
| 1 | Applications and screening | Done |
| 2 | Reviewer coordination | Done |
| 3 | Revisions and decisions | Done |
| 4 | Tracking and records | Done |
| 5 | Safeguards, backups, handover | Done |

## Documentation

- [docs/USER_GUIDE.md](docs/USER_GUIDE.md): how each role uses the system
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md): live server setup, backups, restore and the pre-launch checklist
- [docs/SAFEGUARDS.md](docs/SAFEGUARDS.md): each safeguard in the proposal, where it is built, and its tests

## Local setup

1. Copy `.env.example` to `.env`, then set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
2. Install and prepare:

   ```
   composer install
   npm install
   php artisan key:generate
   php artisan migrate --seed
   npm run build
   ```

3. Run `php artisan serve` and open http://localhost:8000

Run the tests with `php artisan test` (they use an in-memory SQLite database, not MySQL).

## Demo accounts (fictional — development only)

`php artisan db:seed` creates these accounts. The shared password is in
`database/seeders/DatabaseSeeder.php` (`DEMO_PASSWORD`). Never seed the live server.

| Email | Role |
|---|---|
| admin@ucurec.test | IT Administrator |
| secretariat@ucurec.test | REC Secretariat |
| chair@ucurec.test | REC Chairperson |
| reviewer1@ucurec.test … reviewer3@ucurec.test | Reviewer |
| researcher1@ucurec.test, researcher2@ucurec.test | Researcher |
| pending.researcher@ucurec.test | Researcher waiting for activation |

Fictional demo applications (submitted, draft, incomplete, for review) for the demo accounts:

```
php artisan db:seed --class=DemoApplicationSeeder
```

## Applications and screening (Module 2)

- A researcher starts a **draft** with the short record only (title, PI and contact, adviser, college, research type),
  uploads files by category, and submits. The first submission gets a reference number such as `UCUREC-2026-0001`.
- Every submission is a numbered **document set** (version 1, 2, …). Submitted files are never changed or deleted;
  a resubmission starts a new version that carries the earlier files forward.
- The Secretariat starts screening and marks each item of the fixed checklist as Present / Missing / Not applicable.
  Any Missing item returns the application as **Incomplete** with the list shown to the researcher; otherwise it
  moves to **For Review**. Completeness is not ethical approval.
- Files are stored on the private `documents` disk (`storage/app/private/documents`, or `DOCUMENTS_PATH`) under
  random names. Downloads go through `DocumentController`, which checks permission on the server and logs each download.
- Researchers see only their own applications; the Secretariat and Chair see submitted applications;
  the IT administrator has no access to applications or files.

## Reviewer coordination (Module 3)

- While an application is **For Review**, the Secretariat proposes reviewers (reviewer accounts only) with a due date.
  Each assignment is tied to the exact document version being reviewed.
- The **Chairperson authorizes** (or declines, with a reason) each proposal. Reviewers never see unauthorized proposals.
- The reviewer **declares any conflict of interest** and accepts or declines. A declared conflict always closes the
  assignment and blocks the files; that reviewer cannot be proposed again for the application.
- After accepting, the reviewer can download **only the assigned version**, uploads the completed UCUREC Forms 4A/4B
  (Word or PDF), picks a recommendation, and submits. A submitted review is locked.
- Assignments past their due date are flagged **Overdue** for the Secretariat, Chair and reviewer. The Secretariat can
  cancel an open assignment and propose someone else.
- The Secretariat **forwards for REC decision** (status Awaiting REC Decision) once every assignment is closed and at
  least one review is in (none needed for the Exempt route). Recommendations are advice to the Committee and never
  become the decision automatically.
- Reviewer identities, review forms and recommendations are internal: researchers, other reviewers and the IT
  administrator cannot see them.

## Decisions, letters and revisions (Modules 4 and 5)

- While **Awaiting REC Decision**, the Secretariat records the Committee's decision in a separate record: category
  (UCUREC-approved list in `config/ucurec.php`), decision date, approval date and expiry when applicable, a brief
  internal basis, and consolidated comments authorized for release. Reviewer recommendations are shown beside the
  form as advice only.
- The Secretariat uploads the **signed official letter or clearance** (and, for full committee review, the decision
  record or minutes extract), then sends it to the Chairperson.
- The **Chairperson confirms and releases** it, or returns it with a note. Nothing is visible to the researcher before release.
- After release the researcher sees the decision, its dates, the consolidated comments and the signed letter.
  The basis and minutes stay internal. For an approval, the decided version identifies the exact approved files.
- A revision decision moves the application to **For Revision**. The researcher uploads revised files and a
  **response to REC comments** (required) and submits a new version of the same application. The Secretariat sends it
  back to screening, to further review (new assignments target the new version), or straight to the decision.
- A final decision (approved, exempted, disapproved) moves the application to **Decision Released**.

## Tracking and records (Module 6)

- **Pie charts by college / program** on the dashboard: researcher accounts (Secretariat, Chair, IT administrator),
  submitted applications and passed applications (Secretariat, Chair). "Passed" = a released decision whose category
  has `passed => true` in `config/ucurec.php` (Approved, Exempted). Each college keeps one color in every chart, and
  the counts and percentages are listed beside each pie.
- **Pending-work lists** on each role's dashboard (what to screen, route, decide, authorize, confirm, or review),
  with how long each item has been waiting.
- **Applications list**: search by reference no., title, PI or adviser; staff can also filter by college,
  research type, review route, submission dates and **overdue review**, and sort. Each row shows how long the
  application has been in its current status.
- **Status history** and the full record (versions, screenings, reviewers, decisions) on each application page,
  with a **Print record** button for a clean paper copy.
- **CSV application register** (Secretariat and Chair) using the same filters. It has record fields only: no
  reviewer names, contact details or internal decision basis. Cells that a spreadsheet would run as formulas are
  neutralized, and each export is written to the audit log.

## Main administrator account

`php artisan migrate` creates the highest account: an IT Administrator marked as **main administrator**.
Its default name, email, and password are in `config/ucurec.php` (`main_admin`); override them with
`UCUREC_ADMIN_NAME`, `UCUREC_ADMIN_EMAIL`, and `UCUREC_ADMIN_PASSWORD` in `.env` before migrating.
The default password must be changed at the first sign-in.

- Only the main administrator can create, edit, deactivate, or reset other **administrator** accounts.
- No one else can edit, deactivate, or reset the main administrator.
- Like every administrator, it has no access to application documents.

## Roles and account rules

- **Researchers** sign up themselves; they cannot sign in until the REC Secretariat activates the account.
- **IT Administrator** creates staff accounts, activates/deactivates non-admin accounts, and resets passwords.
  A new or reset account gets a one-time temporary password, **emailed to the user** with PHPMailer over SMTP
  (`MAIL_*` in `.env`; check with `php artisan ucurec:mail-test you@example.com`). It must be changed at the next
  sign-in. If email is not set up or fails, the password is shown once to the administrator instead.
  The administrator has no access to application documents.
- **REC Secretariat** activates and deactivates researcher accounts only.
- Sign-in is throttled after 5 failed attempts; sessions expire after 30 minutes of inactivity.
- Sign-ins, failed attempts, and account changes are written to the audit log (no passwords or document contents).

## Placeholder lists

`config/ucurec.php` holds placeholder values (colleges, and later the checklist, decision categories,
and file rules). Replace them with the official UCUREC lists.

## Theme

Red and gold, taken from the Urdaneta City University logo (`public/images/ucu-logo.webp`). Colors are CSS variables at the top of `resources/css/app.css`.
