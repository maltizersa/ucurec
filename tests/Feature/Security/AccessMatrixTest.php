<?php

namespace Tests\Feature\Security;

use App\Models\Application;
use App\Models\Decision;
use App\Models\User;
use App\Services\DecisionService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

/**
 * One table of who may open what, checked on the server for pages and
 * file downloads ("Enforce access permissions on the server for both pages
 * and file downloads").
 */
class AccessMatrixTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, string> */
    private array $urls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        $this->users = [
            'admin' => User::factory()->admin()->create(['is_main_admin' => true]),
            'secretariat' => User::factory()->secretariat()->create(),
            'chair' => User::factory()->chair()->create(),
            'assigned reviewer' => User::factory()->reviewer()->create(),
            'other reviewer' => User::factory()->reviewer()->create(),
            'owner researcher' => User::factory()->create(),
            'other researcher' => User::factory()->create(),
        ];

        $application = $this->releasedApplication();
        $assignment = $application->reviewAssignments()->first();
        $decision = $application->decisions()->first();

        $this->urls = [
            'users list' => '/users',
            'audit log' => '/audit-logs',
            'applications list' => '/applications',
            'application page' => "/applications/{$application->id}",
            'application document' => '/documents/'.$application->latestSubmittedSet->documents->first()->id.'/download',
            'review page' => "/reviews/{$assignment->id}",
            'review form file' => '/review-files/'.$assignment->files->first()->id.'/download',
            'decision page' => "/decisions/{$decision->id}",
            'signed letter' => '/decision-files/'.$decision->files->firstWhere('kind', Decision::FILE_LETTER)->id.'/download',
            'minutes extract' => '/decision-files/'.$decision->files->firstWhere('kind', Decision::FILE_MINUTES)->id.'/download',
            'CSV register' => '/applications/register.csv',
        ];
    }

    private function releasedApplication(): Application
    {
        $application = $this->forReviewApplication($this->users['owner researcher']);
        $s = $this->users['secretariat'];
        $reviews = app(ReviewService::class);
        $decisions = app(DecisionService::class);

        $reviews->setReviewRoute($application, $s, 'full');
        $assignment = $reviews->propose($application, $s, $this->users['assigned reviewer'], today()->addWeek());
        $reviews->approve($assignment, $this->users['chair']);
        $reviews->respond($assignment, $this->users['assigned reviewer'], true, false, null, null);
        $reviews->uploadReviewFile($assignment, $this->users['assigned reviewer'], 'form_4a', $this->pdf('form-4a.pdf'));
        $reviews->submitReview($assignment, $this->users['assigned reviewer'], 'approve', null);
        $reviews->forwardForDecision($application->fresh(), $s);

        $decision = $decisions->create($application->fresh(), $s, [
            'category' => 'approved',
            'decision_date' => today()->toDateString(),
            'approval_date' => today()->toDateString(),
        ]);
        $decisions->uploadFile($decision, $s, Decision::FILE_LETTER, $this->pdf('letter.pdf'));
        $decisions->uploadFile($decision, $s, Decision::FILE_MINUTES, $this->pdf('minutes.pdf'));
        $decisions->sendForConfirmation($decision, $s);
        $decisions->confirmAndRelease($decision, $this->users['chair']);

        return $application->fresh();
    }

    public function test_access_matrix(): void
    {
        // Y = allowed (200), N = refused (403).
        $matrix = [
            //                        admin  secr.  chair  assgnd other  owner  other
            //                                             rev.   rev.   res.   res.
            'users list' => ['Y', 'Y', 'N', 'N', 'N', 'N', 'N'],
            'audit log' => ['Y', 'N', 'N', 'N', 'N', 'N', 'N'],
            'applications list' => ['N', 'Y', 'Y', 'N', 'N', 'Y', 'Y'],
            'application page' => ['N', 'Y', 'Y', 'N', 'N', 'Y', 'N'],
            'application document' => ['N', 'Y', 'Y', 'Y', 'N', 'Y', 'N'],
            'review page' => ['N', 'N', 'N', 'Y', 'N', 'N', 'N'],
            'review form file' => ['N', 'Y', 'Y', 'Y', 'N', 'N', 'N'],
            'decision page' => ['N', 'Y', 'Y', 'N', 'N', 'N', 'N'],
            'signed letter' => ['N', 'Y', 'Y', 'N', 'N', 'Y', 'N'],
            'minutes extract' => ['N', 'Y', 'Y', 'N', 'N', 'N', 'N'],
            'CSV register' => ['N', 'Y', 'Y', 'N', 'N', 'N', 'N'],
        ];

        $roles = array_keys($this->users);
        $failures = [];

        foreach ($matrix as $target => $expectations) {
            foreach ($expectations as $i => $expected) {
                $role = $roles[$i];
                $status = $this->actingAs($this->users[$role])->get($this->urls[$target])->getStatusCode();
                $wanted = $expected === 'Y' ? 200 : 403;

                if ($status !== $wanted) {
                    $failures[] = "{$role} → {$target}: expected {$wanted}, got {$status}";
                }
            }
        }

        $this->assertSame([], $failures, "Access rules broken:\n".implode("\n", $failures));
    }

    public function test_guests_are_sent_to_sign_in_for_everything(): void
    {
        foreach ($this->urls as $target => $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_stored_files_have_no_public_url(): void
    {
        $document = \App\Models\Document::first();

        $this->get('/storage/'.$document->stored_path)->assertNotFound();
        $this->get('/'.$document->stored_path)->assertNotFound();
        $this->assertStringNotContainsString(
            realpath(public_path()),
            (string) realpath(config('filesystems.disks.documents.root')) ?: config('filesystems.disks.documents.root'),
        );
    }
}
