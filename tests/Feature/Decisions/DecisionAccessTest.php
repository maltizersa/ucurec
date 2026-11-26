<?php

namespace Tests\Feature\Decisions;

use App\Enums\Role;
use App\Models\Application;
use App\Models\Decision;
use App\Models\User;
use App\Services\DecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

/**
 * The researcher sees only the released decision and documents; the basis
 * and minutes stay internal.
 */
class DecisionAccessTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    private Application $application;

    private Decision $decision;

    private User $chair;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        $this->application = $this->awaitingDecisionApplication(route: 'full');
        $this->chair = User::factory()->chair()->create();
        $secretariat = User::factory()->secretariat()->create();
        $service = app(DecisionService::class);

        $this->decision = $service->create($this->application, $secretariat, [
            'category' => 'approved',
            'decision_date' => today()->toDateString(),
            'approval_date' => today()->toDateString(),
            'expiry_date' => today()->addYear()->toDateString(),
            'basis' => 'SECRET-BASIS: approved at the 3rd regular meeting.',
            'consolidated_comments' => null,
        ]);
        $service->uploadFile($this->decision, $secretariat, Decision::FILE_LETTER, $this->pdf('approval-letter.pdf'));
        $service->uploadFile($this->decision, $secretariat, Decision::FILE_MINUTES, $this->pdf('minutes-extract.pdf'));
        $service->sendForConfirmation($this->decision, $secretariat);
    }

    private function file(string $kind)
    {
        return $this->decision->files()->where('kind', $kind)->first();
    }

    public function test_researcher_sees_nothing_before_release(): void
    {
        $researcher = $this->application->researcher;

        $this->actingAs($researcher)->get("/applications/{$this->application->id}")
            ->assertOk()
            ->assertDontSee('REC decisions')
            ->assertDontSee('approval-letter');
        $this->actingAs($researcher)->get("/decision-files/{$this->file(Decision::FILE_LETTER)->id}/download")->assertForbidden();
        $this->actingAs($researcher)->get("/decisions/{$this->decision->id}")->assertForbidden();
    }

    public function test_after_release_researcher_gets_the_letter_but_not_the_basis_or_minutes(): void
    {
        app(DecisionService::class)->confirmAndRelease($this->decision, $this->chair);
        $researcher = $this->application->researcher;

        $this->actingAs($researcher)->get("/applications/{$this->application->id}")
            ->assertOk()
            ->assertSee('REC decisions')
            ->assertSee('Approved')
            ->assertSee('Signed official letter')
            ->assertDontSee('SECRET-BASIS')
            ->assertDontSee('minutes extract');

        $this->actingAs($researcher)->get("/decision-files/{$this->file(Decision::FILE_LETTER)->id}/download")
            ->assertOk()->assertDownload('approval-letter.pdf');
        $this->actingAs($researcher)->get("/decision-files/{$this->file(Decision::FILE_MINUTES)->id}/download")->assertForbidden();
        $this->actingAs($researcher)->get("/decisions/{$this->decision->id}")->assertForbidden();
    }

    public function test_other_researchers_reviewers_and_admin_cannot_get_decision_files(): void
    {
        app(DecisionService::class)->confirmAndRelease($this->decision, $this->chair);
        $letter = $this->file(Decision::FILE_LETTER);

        $others = [
            User::factory()->create(),
            User::factory()->reviewer()->create(),
            User::factory()->admin()->create(['is_main_admin' => true]),
        ];

        foreach ($others as $user) {
            $this->actingAs($user)->get("/decision-files/{$letter->id}/download")->assertForbidden();
            $this->actingAs($user)->get("/decisions/{$this->decision->id}")->assertForbidden();
        }
    }

    public function test_secretariat_and_chair_see_the_full_record(): void
    {
        foreach ([Role::Secretariat, Role::Chair] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get("/decisions/{$this->decision->id}")
                ->assertOk()
                ->assertSee('SECRET-BASIS')
                ->assertSee('minutes-extract.pdf');
            $this->actingAs($user)->get("/decision-files/{$this->file(Decision::FILE_MINUTES)->id}/download")->assertOk();
        }
    }

    public function test_released_decision_is_locked(): void
    {
        app(DecisionService::class)->confirmAndRelease($this->decision, $this->chair);
        $secretariat = User::factory()->secretariat()->create();

        $this->actingAs($secretariat)->put("/decisions/{$this->decision->id}", [
            'category' => 'disapproved', 'decision_date' => today()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($secretariat)->delete("/decisions/{$this->decision->id}/files/{$this->file(Decision::FILE_LETTER)->id}")->assertForbidden();

        $this->assertSame('approved', $this->decision->fresh()->category);
    }
}
