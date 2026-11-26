<?php

namespace Tests\Feature\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_researcher_starts_a_draft_with_the_short_record(): void
    {
        $researcher = User::factory()->create();

        $response = $this->actingAs($researcher)->post('/applications', $this->applicationData());

        $application = Application::sole();
        $response->assertRedirect(route('applications.show', $application));
        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertNull($application->reference_no);
        $this->assertSame(1, $application->documentSets()->count());
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'to_status' => 'draft']);
    }

    public function test_only_researchers_can_start_applications(): void
    {
        foreach ([Role::Secretariat, Role::Chair, Role::Reviewer, Role::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->post('/applications', $this->applicationData())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_upload_is_stored_privately_under_a_random_name(): void
    {
        $application = $this->draftApplication();

        $this->actingAs($application->researcher)
            ->post("/applications/{$application->id}/documents", [
                'category' => 'protocol',
                'file' => $this->pdf('My Protocol (final).pdf'),
            ])
            ->assertRedirect();

        $document = Document::sole();
        $this->assertSame('My Protocol (final).pdf', $document->original_name);
        $this->assertMatchesRegularExpression('#^applications/\d+/[0-9a-f-]{36}\.pdf$#', $document->stored_path);
        $this->assertSame(64, strlen($document->sha256));
        Storage::disk('documents')->assertExists($document->stored_path);
        $this->assertFileDoesNotExist(public_path($document->stored_path));
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.uploaded']);
    }

    public function test_disallowed_and_oversized_files_are_rejected(): void
    {
        $application = $this->draftApplication();
        $url = "/applications/{$application->id}/documents";

        $this->actingAs($application->researcher)
            ->post($url, ['category' => 'protocol', 'file' => UploadedFile::fake()->create('tool.exe', 10, 'application/x-msdownload')])
            ->assertSessionHasErrors('file');

        // A text file renamed to .pdf: the content check catches it.
        $this->post($url, ['category' => 'protocol', 'file' => UploadedFile::fake()->create('fake.pdf', 10, 'text/plain')])
            ->assertSessionHasErrors('file');

        $this->post($url, ['category' => 'protocol', 'file' => $this->pdf('huge.pdf', config('ucurec.uploads.max_kb') + 1)])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_submit_requires_the_required_documents(): void
    {
        $application = $this->draftApplication();
        $researcher = $application->researcher;

        $this->actingAs($researcher)->post("/applications/{$application->id}/documents", [
            'category' => 'protocol', 'file' => $this->pdf(),
        ]);

        $this->post("/applications/{$application->id}/submit", ['confirm' => '1'])
            ->assertSessionHasErrors('submit');

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }

    public function test_submission_assigns_sequential_reference_numbers_and_locks_the_files(): void
    {
        $first = $this->submittedApplication();
        $second = $this->submittedApplication();
        $year = now()->year;

        $this->assertSame("UCUREC-{$year}-0001", $first->reference_no);
        $this->assertSame("UCUREC-{$year}-0002", $second->reference_no);
        $this->assertSame(ApplicationStatus::Submitted, $first->status);
        $this->assertNotNull($first->submitted_at);

        // No more uploads or removals once submitted.
        $researcher = $first->researcher;
        $document = $first->latestSubmittedSet->documents->first();

        $this->actingAs($researcher)
            ->post("/applications/{$first->id}/documents", ['category' => 'other', 'file' => $this->pdf()])
            ->assertForbidden();
        $this->actingAs($researcher)
            ->delete("/applications/{$first->id}/documents/{$document->id}")
            ->assertForbidden();
        $this->actingAs($researcher)->get("/applications/{$first->id}/edit")->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_submission_requires_confirmation(): void
    {
        $application = $this->draftApplication();
        $researcher = $application->researcher;
        foreach (['application_form', 'protocol'] as $category) {
            $this->actingAs($researcher)->post("/applications/{$application->id}/documents", ['category' => $category, 'file' => $this->pdf()]);
        }

        $this->post("/applications/{$application->id}/submit")->assertSessionHasErrors('confirm');
        $this->post("/applications/{$application->id}/submit", ['confirm' => '1'])->assertRedirect();

        $this->assertSame(ApplicationStatus::Submitted, $application->fresh()->status);
    }

    public function test_researcher_can_remove_a_draft_file_and_delete_an_unsubmitted_draft(): void
    {
        $application = $this->draftApplication();
        $researcher = $application->researcher;

        $this->actingAs($researcher)->post("/applications/{$application->id}/documents", ['category' => 'protocol', 'file' => $this->pdf()]);
        $document = Document::sole();

        $this->delete("/applications/{$application->id}/documents/{$document->id}")->assertRedirect();
        $this->assertDatabaseCount('documents', 0);
        Storage::disk('documents')->assertMissing($document->stored_path);

        $this->delete("/applications/{$application->id}")->assertRedirect('/applications');
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_submitted_application_cannot_be_deleted(): void
    {
        $application = $this->submittedApplication();

        $this->actingAs($application->researcher)->delete("/applications/{$application->id}")->assertForbidden();
        $this->assertDatabaseHas('applications', ['id' => $application->id]);
    }
}
