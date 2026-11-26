<?php

/*
|--------------------------------------------------------------------------
| UCUREC reference lists
|--------------------------------------------------------------------------
|
| PLACEHOLDER VALUES. Replace these with the official UCUREC lists once the
| Committee provides them. Lists that staff must edit at runtime (screening
| checklist, decision categories) move to database tables in later phases.
|
*/

return [

    'colleges' => [
        'College of Arts and Sciences',
        'College of Business and Accountancy',
        'College of Computer Studies',
        'College of Criminal Justice Education',
        'College of Education',
        'College of Engineering and Architecture',
        'College of Nursing',
        'Graduate School',
    ],

    'research_types' => [
        'Undergraduate thesis',
        'Graduate thesis or dissertation',
        'Faculty research',
        'Institutional research',
        'Externally funded research',
        'Other',
    ],

    /*
    | Document categories a researcher picks when uploading a file.
    | `required` categories must be present before an application can be
    | submitted; the Secretariat checklist below decides completeness.
    */
    'document_categories' => [
        'application_form' => ['label' => 'UCUREC application form', 'required' => true],
        'protocol' => ['label' => 'Research protocol / proposal', 'required' => true],
        'informed_consent' => ['label' => 'Informed consent / assent forms', 'required' => false],
        'instruments' => ['label' => 'Data collection instruments', 'required' => false],
        'cv' => ['label' => 'CV of the principal investigator', 'required' => false],
        'endorsement' => ['label' => 'Adviser / dean endorsement', 'required' => false],
        'response_letter' => ['label' => 'Response to REC comments', 'required' => false],
        'other' => ['label' => 'Other supporting document', 'required' => false],
    ],

    /*
    | Fixed completeness checklist used by the Secretariat. Each screening
    | stores a copy of the labels, so editing this list never changes old
    | screening records.
    */
    'screening_checklist' => [
        'application_form' => 'Accomplished and signed UCUREC application form',
        'protocol' => 'Complete research protocol / proposal',
        'informed_consent' => 'Informed consent / assent forms (English and local language)',
        'instruments' => 'Data collection instruments (questionnaire, interview guide, etc.)',
        'cv' => 'Curriculum vitae of the principal investigator',
        'endorsement' => 'Endorsement from the adviser or dean',
    ],

    'review_routes' => [
        'exempt' => 'Exempt from review',
        'expedited' => 'Expedited review',
        'full' => 'Full committee review',
    ],

    /*
    | Reviewer recommendations. A recommendation is advice to the Committee
    | and never becomes the REC decision automatically.
    */
    'review_recommendations' => [
        'approve' => 'Approve',
        'minor_modifications' => 'Minor modifications',
        'major_modifications' => 'Major modifications',
        'disapprove' => 'Disapprove',
    ],

    // Completed review forms the reviewer uploads (existing Word or PDF forms).
    'review_form_types' => [
        'form_4a' => 'UCUREC Form 4A',
        'form_4b' => 'UCUREC Form 4B',
        'other' => 'Other reviewer notes',
    ],

    /*
    | UCUREC-approved decision categories.
    |   outcome  = 'revision' sends the application back to the researcher
    |              (For Revision); 'final' ends it (Decision Released).
    |   approval = asks for an approval date (and expiry when applicable).
    |   passed   = counted as "passed" in the dashboard charts.
    */
    'decision_categories' => [
        'approved' => ['label' => 'Approved', 'outcome' => 'final', 'approval' => true, 'passed' => true],
        'minor_modifications' => ['label' => 'Minor modifications required', 'outcome' => 'revision', 'approval' => false, 'passed' => false],
        'major_modifications' => ['label' => 'Major modifications required', 'outcome' => 'revision', 'approval' => false, 'passed' => false],
        'exempted' => ['label' => 'Exempted from review', 'outcome' => 'final', 'approval' => false, 'passed' => true],
        'disapproved' => ['label' => 'Disapproved', 'outcome' => 'final', 'approval' => false, 'passed' => false],
    ],

    // Default number of days a reviewer gets, used to pre-fill the due date.
    'review_days' => 14,

    /*
    | Email through SMTP with PHPMailer (account passwords). Set
    | MAIL_MAILER=smtp and the MAIL_* values in .env, then check them with
    | `php artisan ucurec:mail-test you@example.com`. While email is not
    | set up, the temporary password is shown once on screen instead.
    |   MAIL_ENCRYPTION: tls (STARTTLS, usually port 587), ssl (port 465), or none
    */
    'mail' => [
        'enabled' => env('MAIL_MAILER') === 'smtp',
        'host' => env('MAIL_HOST'),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'timeout' => (int) env('MAIL_TIMEOUT', 15),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME', 'UCUREC Ethics Review'),
    ],

    /*
    | Backups of the database and all uploaded documents (php artisan
    | ucurec:backup, scheduled daily). BACKUP_PATH must point to storage the
    | university controls and that is separate from this server's disk,
    | e.g. a mapped network share. Set BACKUP_PASSWORD to encrypt archives
    | (AES-256); keep that password outside this server.
    */
    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'password' => env('BACKUP_PASSWORD'),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'time' => env('BACKUP_TIME', '01:00'),
    ],

    'uploads' => [
        'extensions' => ['pdf', 'doc', 'docx'],
        'max_kb' => 20480, // 20 MB per file
    ],

    /*
    |--------------------------------------------------------------------------
    | Main administrator account
    |--------------------------------------------------------------------------
    |
    | Created by `php artisan migrate` (see the create_main_administrator_account
    | migration). It is the highest account in the system: an IT Administrator
    | that no other administrator can edit, deactivate, or reset.
    |
    | Override these in .env (UCUREC_ADMIN_NAME / _EMAIL / _PASSWORD). The
    | default password must be changed at the first sign-in. Set
    | UCUREC_ADMIN_EMAIL to an empty value to skip creating the account.
    |
    */

    'main_admin' => [
        'name' => env('UCUREC_ADMIN_NAME') ?: 'UCUREC Main Administrator',
        'email' => env('UCUREC_ADMIN_EMAIL', 'ucurec@gmail.com'),
        'password' => env('UCUREC_ADMIN_PASSWORD') ?: 'ucurecadmin1234',
    ],

];
