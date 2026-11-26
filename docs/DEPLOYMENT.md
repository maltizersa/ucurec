# Deployment, backups and restore

For the university IT administrator. University IT and the responsible REC officers should review access,
backup and deployment arrangements (the checklist at the end) **before live use**. Use only fictional records
during development and the pilot.

## 1. Server requirements

- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `ctype`, `tokenizer`, `xml`
- MySQL 8 or MariaDB 10.6+
- Composer 2, and Node.js 20+ (only to build the CSS/JS once)
- A web server (Apache or Nginx/IIS) with **HTTPS** (university certificate)
- Storage for backups that the university controls and that is **separate from this server's disk**
  (e.g. a network share mapped on the server)

## 2. Folder layout and web root

- Point the web server's document root to the **`public/`** folder only. Nothing else in the project may be
  reachable from the web.
- Uploaded files are stored in `DOCUMENTS_PATH` (default `storage/app/private/documents`), outside `public/`,
  under random file names. They are only ever sent through the application after a permission check.
- The web server user needs write access to `storage/`, `bootstrap/cache/`, `DOCUMENTS_PATH` and `BACKUP_PATH`.

## 3. Production `.env`

Copy `.env.example` to `.env` and set at least:

```
APP_NAME="UCUREC Ethics Review"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ucurec.example.edu.ph
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ucurec
DB_USERNAME=ucurec_app        # a dedicated account, not root
DB_PASSWORD=...               # set by IT; never committed

SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true    # cookies only over HTTPS

LOG_LEVEL=warning

DOCUMENTS_PATH=D:\ucurec-data\documents        # outside the web root
BACKUP_PATH=\\fileserver\ucurec-backups         # separate, university-controlled storage
BACKUP_PASSWORD=...                             # encrypts backups (AES-256); keep a copy offline
BACKUP_KEEP=14
BACKUP_TIME=01:00

MAIL_MAILER=smtp              # emails temporary passwords (PHPMailer over SMTP)
MAIL_HOST=smtp.example.edu.ph
MAIL_PORT=587
MAIL_ENCRYPTION=tls           # tls (STARTTLS, port 587), ssl (port 465) or none
MAIL_USERNAME=ucurec@example.edu.ph
MAIL_PASSWORD=...             # SMTP password or app password; never committed
MAIL_FROM_ADDRESS=ucurec@example.edu.ph
MAIL_FROM_NAME="UCUREC Ethics Review"

UCUREC_ADMIN_NAME="UCUREC Main Administrator"
UCUREC_ADMIN_EMAIL=...        # the main administrator account created by migrate
UCUREC_ADMIN_PASSWORD=...     # temporary; must be changed at first sign-in
```

`APP_ENV=production` also turns on: HTTPS links only, a Content-Security-Policy header, and refusal to run the
demo seeders.

## 4. Install

```
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate          # first install only
php artisan migrate --force       # creates the tables and the main administrator
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Never run `php artisan db:seed` on the live server.** The demo seeders create accounts with a shared,
published password (they refuse to run when `APP_ENV=production`).

First sign-in: use the main administrator email and the temporary `UCUREC_ADMIN_PASSWORD`. The system forces a
new password. Then create the staff accounts (Secretariat, Chairperson, reviewers) under **User accounts**.
Researchers sign up themselves and are activated by the Secretariat.

### Email (temporary passwords)

When the IT administrator creates an account or resets a password, the system emails a temporary password to the
account's address with PHPMailer over SMTP. The password is never shown to the administrator or written to any log,
and the user must change it at the first sign-in. If email is not set up or sending fails, the password is shown
once to the administrator instead, and the failure is written to the audit log.

After setting the `MAIL_*` values, check them:

```
php artisan config:clear
php artisan ucurec:mail-test your.address@example.edu.ph
```

Use the university's mail server or a dedicated UCUREC mailbox. With Google Workspace or Gmail, use
`smtp.gmail.com`, port `587`, `tls`, and an **app password** (requires 2-step verification), not the account's
normal password. Personal Gmail accounts have daily sending limits.

## 5. Updating to a new version

```
php artisan ucurec:backup          # always back up first
php artisan down
git pull                           # or copy the new release
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 6. Backups

`php artisan ucurec:backup` writes one zip file to `BACKUP_PATH` with:

- every database table (records, history, audit log) as JSON lines,
- every uploaded file (application documents, review forms, decision letters, minutes),
- a `manifest.json` with a SHA-256 checksum for every entry.

With `BACKUP_PASSWORD` set, every entry is encrypted with AES-256. The newest `BACKUP_KEEP` backups are kept.
Sessions, cache and queue tables are not backed up. Backups contain confidential documents: restrict access to
`BACKUP_PATH` to IT staff responsible for backups.

### Schedule it

The backup runs every day at `BACKUP_TIME` through the Laravel scheduler. Run the scheduler every minute.

**Windows (Task Scheduler)**: create a task that runs every minute, indefinitely, whether the user is signed in
or not:

- Program: `C:\xampp\php\php.exe` (the PHP used by the site)
- Arguments: `artisan schedule:run`
- Start in: the project folder, e.g. `C:\inetpub\ucurec`

**Linux (cron)**:

```
* * * * * cd /var/www/ucurec && php artisan schedule:run >> /dev/null 2>&1
```

The IT administrator's dashboard shows the time of the last backup and turns red after 36 hours without one.
Each backup and restore is written to the audit log.

### Other commands

```
php artisan ucurec:backup-list                 # backups in BACKUP_PATH
php artisan ucurec:backup-verify <file>        # check checksums; changes nothing
php artisan ucurec:restore <file>              # restore (asks for confirmation)
```

### Database-level dump (optional, recommended by IT policy)

The application backup above is the one that is tested and restorable by the application. IT may also keep its
usual database dump, e.g. `mysqldump --single-transaction ucurec > ucurec.sql`, together with a copy of
`DOCUMENTS_PATH`.

## 7. Restore

1. Make sure the code and database version match the backup (`php artisan migrate --force` on the same release).
2. `php artisan ucurec:backup-verify <file>`. Do not continue if a checksum fails.
3. `php artisan down`
4. `php artisan ucurec:restore <file>`. It first saves a `pre-restore-…zip` safety backup of the current state,
   then replaces **all** records and documents with the backup's contents.
5. `php artisan up`, sign in, and spot-check a few applications and downloads.

The audit log is part of the backup, so after a restore it shows the history up to the backup plus a
`backup.restored` entry; entries made after the backup are kept in the `pre-restore-…zip` safety backup.

A restore refuses: a backup with any checksum mismatch, a backup from a different database version, a wrong
`BACKUP_PASSWORD`, and any file path that would leave the documents folder.

### Restore drill (do this before handover)

Run a full drill on a copy of the server (or the pilot) and record it:

| Item | Result |
|---|---|
| Date / performed by / witnessed by | |
| Backup file and its size | |
| `ucurec:backup-verify` result | |
| Records and files before (applications / documents / files on disk) | |
| Simulated loss (what was deleted) | |
| `ucurec:restore` result | |
| Records and files after | |
| Spot check: opened applications, downloaded files | |

Development drill (fictional demo data, encrypted backup, 2026-11-26):

```
Before:                applications=7 documents=34 users=9 decisions=3, files on disk=42
Backup:                12 tables / 236 rows, 42 documents, all checksums match
Simulated loss:        all applications and document records deleted, application files deleted
After loss:            applications=0 documents=0, files on disk=8
Restore:               236 rows, 42 documents restored, safety backup pre-restore-….zip created
After restore:         applications=7 documents=34 users=9 decisions=3, files on disk=42,
                       0 documents with a wrong checksum
```

The same round trip runs automatically in `tests/Feature/Security/BackupRestoreTest.php`.

## 8. Before live use: checklist for IT and the REC officers

- [ ] Site only reachable over HTTPS; `APP_DEBUG=false`; `APP_ENV=production`
- [ ] Web root is `public/`; `storage/`, `.env` and `DOCUMENTS_PATH` are not reachable from the web
- [ ] Dedicated database account with a strong password; database not reachable from outside the server
- [ ] Main administrator signed in and changed the temporary password; demo seeders never run
- [ ] Email works (`php artisan ucurec:mail-test`); a test account received its temporary password
- [ ] Staff accounts created with the correct roles; test accounts removed or deactivated
- [ ] `BACKUP_PATH` on separate, university-controlled storage, access restricted; `BACKUP_PASSWORD` stored offline
- [ ] Scheduled task runs `schedule:run` every minute; a backup appeared the next day
- [ ] Restore drill done and recorded (section 7)
- [ ] Official UCUREC lists entered in `config/ucurec.php` (colleges, research types, document categories,
      checklist, review routes, recommendations, decision categories, file types and size)
- [ ] REC officers reviewed who can see what (`docs/SAFEGUARDS.md`)
- [ ] `php artisan test` passes on the release being deployed
