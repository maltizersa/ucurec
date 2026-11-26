<?php

namespace Tests\Feature\Applications;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

/**
 * Server-side permission checks for pages and file downloads.
 */
class AccessControlTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_researcher_sees_only_own_applications(): void
    {
        $mine = $this->submittedApplication();
        $theirs = $this->submittedApplication();
        $theirDocument = $theirs->latestSubmittedSet->documents->first();

        $this->actingAs($mine->researcher)->get('/applications')
            ->assertOk()
            ->assertSee($mine->reference_no)
            ->assertDontSee($theirs->reference_no);

        $this->actingAs($mine->researcher)->get("/applications/{$theirs->id}")->assertForbidden();
        $this->actingAs($mine->researcher)->get("/documents/{$theirDocument->id}/download")->assertForbidden();
    }

    public function test_it_administrator_and_reviewers_cannot_open_applications_or_files(): void
    {
        $application = $this->submittedApplication();
        $document = $application->latestSubmittedSet->documents->first();

        foreach ([Role::Admin, Role::Reviewer] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get('/applications')->assertForbidden();
            $this->actingAs($user)->get("/applications/{$application->id}")->assertForbidden();
            $this->actingAs($user)->get("/documents/{$document->id}/download")->assertForbidden();
        }

        // Even the main administrator.
        $main = User::factory()->admin()->create(['is_main_admin' => true]);
        $this->actingAs($main)->get("/documents/{$document->id}/download")->assertForbidden();
    }

    public function test_staff_cannot_see_a_researchers_unsubmitted_draft(): void
    {
        $application = $this->draftApplication();
        $application->researcher->refresh();
        app(\App\Services\ApplicationService::class)
            ->uploadDocument($application, $application->researcher, 'protocol', $this->pdf());
        $document = $application->draftSet()->first()->documents->first();

        foreach ([Role::Secretariat, Role::Chair] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get("/applications/{$application->id}")->assertForbidden();
            $this->actingAs($user)->get("/documents/{$document->id}/download")->assertForbidden();
            $this->actingAs($user)->get('/applications')->assertDontSee($application->title);
        }
    }

    public function test_secretariat_downloads_a_submitted_file_and_it_is_logged(): void
    {
        $application = $this->submittedApplication();
        $document = $application->latestSubmittedSet->documents->firstWhere('category', 'protocol');
        $secretariat = User::factory()->secretariat()->create();

        $response = $this->actingAs($secretariat)->get("/documents/{$document->id}/download");

        $response->assertOk();
        $response->assertDownload('protocol.pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.downloaded',
            'user_id' => $secretariat->id,
            'subject_id' => $application->id,
        ]);
    }

    public function test_owner_can_download_own_files(): void
    {
        $application = $this->submittedApplication();
        $document = $application->latestSubmittedSet->documents->first();

        $this->actingAs($application->researcher)->get("/documents/{$document->id}/download")->assertOk();
    }

    public function test_researcher_cannot_screen_or_see_internal_names(): void
    {
        $application = $this->submittedApplication();
        $researcher = $application->researcher;

        $this->actingAs($researcher)->post("/applications/{$application->id}/screening/start")->assertForbidden();
        $this->actingAs($researcher)->get("/applications/{$application->id}/screening")->assertForbidden();
    }
}
