<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationDocumentController;
use App\Http\Controllers\ApplicationRegisterController;
use App\Http\Controllers\ApplicationSubmissionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DecisionController;
use App\Http\Controllers\DecisionFileController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReviewAssignmentController;
use App\Http\Controllers\ReviewerController;
use App\Http\Controllers\ReviewFileController;
use App\Http\Controllers\RevisionRoutingController;
use App\Http\Controllers\ScreeningController;
use App\Http\Controllers\UserAccountActionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/register/pending', [RegisterController::class, 'pending'])->name('register.pending');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Access to each action is decided by UserPolicy.
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('/users/{user}/activate', [UserAccountActionController::class, 'activate'])->name('users.activate');
        Route::post('/users/{user}/deactivate', [UserAccountActionController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/reset-password', [UserAccountActionController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('role:admin')
            ->name('audit-logs.index');

        // Module 6: CSV application register (Secretariat and Chair).
        // Declared before the resource so "register" is not read as an {application}.
        Route::get('/applications/register.csv', ApplicationRegisterController::class)->name('applications.register');

        // Module 2: applications and screening. Access is decided by
        // ApplicationPolicy and DocumentPolicy.
        Route::resource('applications', ApplicationController::class);
        Route::post('/applications/{application}/documents', [ApplicationDocumentController::class, 'store'])
            ->name('applications.documents.store');
        Route::delete('/applications/{application}/documents/{document}', [ApplicationDocumentController::class, 'destroy'])
            ->name('applications.documents.destroy');
        Route::post('/applications/{application}/submit', [ApplicationSubmissionController::class, 'store'])
            ->name('applications.submit');

        Route::post('/applications/{application}/screening/start', [ScreeningController::class, 'start'])
            ->name('applications.screening.start');
        Route::get('/applications/{application}/screening', [ScreeningController::class, 'create'])
            ->name('applications.screening.create');
        Route::post('/applications/{application}/screening', [ScreeningController::class, 'store'])
            ->name('applications.screening.store');

        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
            ->name('documents.download');

        // Module 3: reviewer coordination. Secretariat and Chair actions
        // (ApplicationPolicy::coordinateReview, ReviewAssignmentPolicy).
        Route::post('/applications/{application}/assignments', [ReviewAssignmentController::class, 'store'])
            ->name('applications.assignments.store');
        Route::post('/applications/{application}/review-route', [ReviewAssignmentController::class, 'route'])
            ->name('applications.review-route');
        Route::post('/applications/{application}/forward', [ReviewAssignmentController::class, 'forward'])
            ->name('applications.forward');
        Route::post('/assignments/{assignment}/approve', [ReviewAssignmentController::class, 'approve'])
            ->name('assignments.approve');
        Route::post('/assignments/{assignment}/reject', [ReviewAssignmentController::class, 'reject'])
            ->name('assignments.reject');
        Route::post('/assignments/{assignment}/cancel', [ReviewAssignmentController::class, 'cancel'])
            ->name('assignments.cancel');

        // The reviewer's own assignments.
        Route::get('/reviews', [ReviewerController::class, 'index'])->middleware('role:reviewer')->name('reviews.index');
        Route::get('/reviews/{assignment}', [ReviewerController::class, 'show'])->name('reviews.show');
        Route::post('/reviews/{assignment}/respond', [ReviewerController::class, 'respond'])->name('reviews.respond');
        Route::post('/reviews/{assignment}/files', [ReviewerController::class, 'uploadFile'])->name('reviews.files.store');
        Route::delete('/reviews/{assignment}/files/{file}', [ReviewerController::class, 'removeFile'])->name('reviews.files.destroy');
        Route::post('/reviews/{assignment}/submit', [ReviewerController::class, 'submit'])->name('reviews.submit');

        Route::get('/review-files/{file}/download', [ReviewFileController::class, 'download'])
            ->name('review-files.download');

        // Modules 4 and 5: decisions, letters, and revisions
        // (ApplicationPolicy::recordDecision / routeRevision, DecisionPolicy).
        Route::get('/applications/{application}/decisions/create', [DecisionController::class, 'create'])
            ->name('applications.decisions.create');
        Route::post('/applications/{application}/decisions', [DecisionController::class, 'store'])
            ->name('applications.decisions.store');
        Route::post('/applications/{application}/route-revision', [RevisionRoutingController::class, 'store'])
            ->name('applications.route-revision');

        Route::get('/decisions/{decision}', [DecisionController::class, 'show'])->name('decisions.show');
        Route::put('/decisions/{decision}', [DecisionController::class, 'update'])->name('decisions.update');
        Route::post('/decisions/{decision}/files', [DecisionController::class, 'uploadFile'])->name('decisions.files.store');
        Route::delete('/decisions/{decision}/files/{file}', [DecisionController::class, 'removeFile'])->name('decisions.files.destroy');
        Route::post('/decisions/{decision}/send', [DecisionController::class, 'sendForConfirmation'])->name('decisions.send');
        Route::post('/decisions/{decision}/confirm', [DecisionController::class, 'confirm'])->name('decisions.confirm');
        Route::post('/decisions/{decision}/return', [DecisionController::class, 'sendBack'])->name('decisions.return');

        Route::get('/decision-files/{file}/download', [DecisionFileController::class, 'download'])
            ->name('decision-files.download');
    });
});
