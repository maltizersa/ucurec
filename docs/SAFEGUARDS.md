# Safeguards: what was built and how it is tested

Each row is a safeguard from the proposal ("Minimum safeguards to implement and test" and the record rules),
where it is implemented, and the automated tests that check it. Run all tests with `php artisan test`.

## Access control (server-side, pages and file downloads)

| Safeguard | Implementation | Tests |
|---|---|---|
| Permissions enforced on the server for pages **and** downloads | Policies in `app/Policies/` checked by every controller; files only through `DocumentController`, `ReviewFileController`, `DecisionFileController` | `Security/AccessMatrixTest` (every role × every sensitive page and file) |
| Researchers open only their own records | `ApplicationPolicy::view`, `Application::scopeVisibleTo` | `Applications/AccessControlTest` |
| Reviewers open only authorized assignments and the assigned version | `ReviewAssignmentPolicy`, `DocumentPolicy` (reviewer branch: exact `document_set_id`, accepted/submitted only) | `Reviews/ReviewAccessTest`, `Reviews/ReviewWorkflowTest` |
| Conflict of interest declared before files open; conflicted reviewer blocked and reassigned | `ReviewService::respond`, `ReviewService::propose` | `ReviewWorkflowTest::test_declared_conflict_blocks…` |
| Technical administration has no routine access to documents | IT admin role excluded from all application, review and decision policies (including the main administrator) | `AccessMatrixTest`, `AccessControlTest`, `ReviewAccessTest`, `DecisionAccessTest` |
| Researchers see only released decisions and documents; reviewer identities, reports and deliberation stay internal | `DecisionFilePolicy` (letter and comments only, after release); internal panels rendered only for Secretariat/Chair | `Decisions/DecisionAccessTest`, `ReviewAccessTest::test_researcher_never_sees…` |
| Reviewer recommendations never become REC decisions automatically | Decisions are separate records created by the Secretariat and released by the Chair | `DecisionWorkflowTest::test_recommendations_never_create…` |

## Accounts and sessions

| Safeguard | Implementation | Tests |
|---|---|---|
| Framework password hashing (no custom cryptography) | Laravel `hashed` cast (bcrypt) | `SafeguardsTest::test_passwords_are_hashed…` |
| Login throttling | `LoginRequest`: 5 failed attempts per email + IP, then a wait | `LoginTest::test_sign_in_is_throttled…` |
| Secure sessions and expiry | Database sessions, encrypted, HTTP-only, SameSite=Lax, 30 idle minutes; secure cookies over HTTPS in production | `SafeguardsTest::test_sessions_expire…` |
| Temporary passwords reach only the account owner | Emailed with PHPMailer over SMTP (`AccountMailer`); never shown to the administrator or logged; must be changed at first sign-in; shown once only if email is unavailable | `AccountEmailTest` |
| Staff-managed accounts, activation and deactivation | Researcher sign-up stays inactive until the Secretariat activates it; deactivated users are signed out on their next request | `RegistrationTest`, `UserManagementTest`, `LoginTest::test_user_deactivated_mid_session…` |
| HTTPS for deployment | `URL::forceScheme('https')` in production, HSTS on HTTPS requests, `SESSION_SECURE_COOKIE=true` | `docs/DEPLOYMENT.md` checklist |
| Browser protections | `SecurityHeaders` middleware: frame denial, no MIME sniffing, referrer policy; Content-Security-Policy in production | `SafeguardsTest::test_security_headers…`, `test_production_adds…` |

## Files

| Safeguard | Implementation | Tests |
|---|---|---|
| Uploads stored outside public folders | Private `documents` disk (`DOCUMENTS_PATH`), `serve` disabled | `AccessMatrixTest::test_stored_files_have_no_public_url`, `SubmissionTest::test_upload_is_stored_privately…` |
| Only REC-approved file types, size limits | `UploadRules`: extension **and** content type checked, `max_kb` limit (`config/ucurec.php`) | `SubmissionTest::test_disallowed_and_oversized_files…` |
| Safe internal file names | Random UUID names; original name kept for display only (`SafeFilename`) | `SubmissionTest::test_upload_is_stored_privately…` |
| Never overwrite an earlier submitted file | Each submission is a numbered document set; submitted sets are locked; resubmissions carry files forward without copying | `SubmissionTest`, `ScreeningTest::test_resubmission_creates_a_new_version…` |
| Every review report linked to the exact version reviewed; decision linked to the decided version | `review_assignments.document_set_id`, `decisions.document_set_id` | `DecisionWorkflowTest::test_revision_cycle…` |

## Logging

| Safeguard | Implementation | Tests |
|---|---|---|
| Log submissions, screening, assignments, conflict declarations, uploads, decisions and releases with user and time | `AuditLog::record()` in every service action; viewable by the IT administrator | `SafeguardsTest::test_key_actions_are_logged…` |
| No document contents or passwords in logs | Only IDs, categories and counts are logged; conflict details and recommendations are not logged | `LoginTest::test_wrong_password…`, `ReviewWorkflowTest::test_declared_conflict…`, `SafeguardsTest` |

## Backup and restore

| Safeguard | Implementation | Tests |
|---|---|---|
| Scheduled database and document backup to separate storage | `ucurec:backup` daily via the scheduler, `BACKUP_PATH`, optional AES-256 encryption, retention | `Security/BackupRestoreTest` |
| Demonstrated successful restore | `ucurec:restore` with checksum verification, version check, safety backup, path-traversal protection | `BackupRestoreTest::test_backup_then_restore…`, drill in `docs/DEPLOYMENT.md` |

## Records and exports

| Safeguard | Implementation | Tests |
|---|---|---|
| CSV register without confidential details | Record fields only; no reviewer names, contacts or decision basis; formula injection neutralized; export logged | `Records/TrackingAndRegisterTest` |
| Fictional records only in development | Demo seeders refuse to run in production | `SafeguardsTest::test_demo_seeders_refuse…` |
| Retain existing records; no automated deletion | No record is deleted by the system; only a researcher's never-submitted draft can be deleted by its owner | `SubmissionTest::test_submitted_application_cannot_be_deleted` |
