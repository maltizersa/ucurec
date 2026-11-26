<?php

namespace Tests\Concerns;

use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationService;
use Illuminate\Http\UploadedFile;

trait CreatesApplications
{
    protected function applicationData(array $overrides = []): array
    {
        return [
            'title' => 'Effects of Study Habits on Academic Performance',
            'principal_investigator' => 'Juan Dela Cruz',
            'pi_contact' => 'juan@example.com',
            'adviser' => 'Dr. Maria Santos',
            'college_program' => config('ucurec.colleges')[0],
            'research_type' => config('ucurec.research_types')[0],
            ...$overrides,
        ];
    }

    protected function pdf(string $name = 'file.pdf', int $kilobytes = 50): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    protected function draftApplication(?User $researcher = null): Application
    {
        $researcher ??= User::factory()->create();

        return app(ApplicationService::class)->createDraft($researcher, $this->applicationData());
    }

    /**
     * A submitted application with the required documents (and consent form).
     */
    protected function submittedApplication(?User $researcher = null): Application
    {
        $application = $this->draftApplication($researcher);
        $owner = $application->researcher;
        $service = app(ApplicationService::class);

        $service->uploadDocument($application, $owner, 'application_form', $this->pdf('application-form.pdf'));
        $service->uploadDocument($application, $owner, 'protocol', $this->pdf('protocol.pdf'));
        $service->uploadDocument($application, $owner, 'informed_consent', $this->pdf('consent.pdf'));
        $service->submit($application, $owner);

        return $application->fresh();
    }

    /**
     * A submitted application that passed screening (status For Review).
     */
    protected function forReviewApplication(?User $researcher = null): Application
    {
        $application = $this->submittedApplication($researcher);
        $secretariat = User::factory()->secretariat()->create();
        $service = app(ApplicationService::class);

        $service->startScreening($application, $secretariat);
        $service->recordScreening($application->fresh(), $secretariat, $this->checklistAnswers(), null, 'expedited');

        return $application->fresh();
    }

    /**
     * An application with one submitted review, forwarded for the REC
     * decision (status Awaiting REC Decision).
     */
    protected function awaitingDecisionApplication(?User $researcher = null, string $route = 'expedited'): Application
    {
        $application = $this->forReviewApplication($researcher);
        $secretariat = User::factory()->secretariat()->create();
        $reviewer = User::factory()->reviewer()->create();
        $reviews = app(\App\Services\ReviewService::class);

        $reviews->setReviewRoute($application, $secretariat, $route);
        $assignment = $reviews->propose($application, $secretariat, $reviewer, today()->addWeek());
        $reviews->approve($assignment, User::factory()->chair()->create());
        $reviews->respond($assignment, $reviewer, true, false, null, null);
        $reviews->uploadReviewFile($assignment, $reviewer, 'form_4a', $this->pdf('form-4a.pdf'));
        $reviews->submitReview($assignment, $reviewer, 'approve', 'Looks good.');
        $reviews->forwardForDecision($application->fresh(), $secretariat);

        return $application->fresh();
    }

    /**
     * @return array<string, array{state: string, note?: string}>
     */
    protected function checklistAnswers(string $default = 'present', array $overrides = []): array
    {
        $items = [];
        foreach (array_keys(config('ucurec.screening_checklist')) as $key) {
            $items[$key] = ['state' => $overrides[$key] ?? $default, 'note' => null];
        }

        return $items;
    }
}
