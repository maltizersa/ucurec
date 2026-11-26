<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Decision;
use App\Models\Screening;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\DecisionService;
use App\Services\ReviewService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * FICTIONAL demo applications at different stages, for development and
 * the pilot demo only. Needs the demo accounts from DatabaseSeeder.
 *
 *   php artisan db:seed --class=DemoApplicationSeeder
 */
class DemoApplicationSeeder extends Seeder
{
    public function run(ApplicationService $service, ReviewService $reviews): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipped: demo data is never seeded in production.');

            return;
        }

        $juan = User::where('email', 'researcher1@ucurec.test')->first();
        $carla = User::where('email', 'researcher2@ucurec.test')->first();
        $secretariat = User::where('email', 'secretariat@ucurec.test')->first();

        if (! $juan || ! $carla || ! $secretariat) {
            $this->command?->warn('Skipped: run DatabaseSeeder first (demo accounts are missing).');

            return;
        }

        // 1. Submitted, waiting for screening.
        $submitted = $this->application($service, $juan, 'Mobile Learning Apps and the Reading Comprehension of Grade 6 Pupils');
        $this->upload($service, $submitted, $juan, ['application_form', 'protocol', 'informed_consent', 'instruments']);
        $service->submit($submitted, $juan, 'Thank you. The consent forms are in English and Filipino.');

        // 2. A draft still being prepared.
        $draft = $this->application($service, $juan, 'Usability Evaluation of a Barangay Health Records System');
        $this->upload($service, $draft, $juan, ['protocol']);

        // 3. Returned as incomplete (missing CV).
        $incomplete = $this->application($service, $carla, 'Lived Experiences of Night-Shift Nurses in a Provincial Hospital');
        $this->upload($service, $incomplete, $carla, ['application_form', 'protocol', 'informed_consent', 'instruments', 'endorsement']);
        $service->submit($incomplete, $carla);
        $service->startScreening($incomplete->fresh(), $secretariat);
        $service->recordScreening($incomplete->fresh(), $secretariat, $this->checklist(['cv' => Screening::STATE_MISSING]),
            'Please upload the CV of the principal investigator.', null);

        // 4. Complete, now For Review.
        $forReview = $this->application($service, $carla, 'Hand Hygiene Compliance Among Nursing Students During Clinical Duty');
        $this->upload($service, $forReview, $carla, ['application_form', 'protocol', 'informed_consent', 'instruments', 'cv', 'endorsement']);
        $service->submit($forReview, $carla);
        $service->startScreening($forReview->fresh(), $secretariat);
        $service->recordScreening($forReview->fresh(), $secretariat, $this->checklist(), null, 'expedited');

        // 5. Reviewer coordination on application 4.
        $this->reviewers($reviews, $forReview->fresh(), $secretariat);

        // 6-8. Decisions (Phase 3).
        $chair = User::where('email', 'chair@ucurec.test')->first();
        $reviewer = User::where('email', 'reviewer1@ucurec.test')->first();
        if (! $chair || ! $reviewer) {
            return;
        }
        $decisions = app(DecisionService::class);

        // 6. Reviewed and forwarded: waiting for the Secretariat to record the decision.
        $this->throughReview($service, $reviews, $juan, $secretariat, $chair, $reviewer,
            'Screen Time and Sleep Quality of Senior High School Students', 'approve');

        // 7. Minor modifications released: the researcher must revise.
        $revise = $this->throughReview($service, $reviews, $carla, $secretariat, $chair, $reviewer,
            'Knowledge and Practices on Dengue Prevention in Two Barangays', 'minor_modifications');
        $this->decide($decisions, $revise, $secretariat, $chair, 'minor_modifications', null,
            "1. Add a withdrawal clause to the informed consent form.\n2. State where the data will be stored and for how long.");

        // 8. Approved and released.
        $approved = $this->throughReview($service, $reviews, $juan, $secretariat, $chair, $reviewer,
            'Financial Literacy of Public Market Vendors in Urdaneta City', 'approve');
        $this->decide($decisions, $approved, $secretariat, $chair, 'approved', today()->addYear()->toDateString(), null);
    }

    /**
     * Submit, screen, review by one reviewer, and forward for decision.
     */
    private function throughReview(ApplicationService $service, ReviewService $reviews, User $researcher, User $secretariat, User $chair, User $reviewer, string $title, string $recommendation): Application
    {
        $application = $this->application($service, $researcher, $title);
        $this->upload($service, $application, $researcher, ['application_form', 'protocol', 'informed_consent', 'instruments', 'cv', 'endorsement']);
        $service->submit($application, $researcher);
        $service->startScreening($application->fresh(), $secretariat);
        $service->recordScreening($application->fresh(), $secretariat, $this->checklist(), null, 'expedited');

        $assignment = $reviews->propose($application->fresh(), $secretariat, $reviewer, today()->addDays(10));
        $reviews->approve($assignment, $chair);
        $reviews->respond($assignment, $reviewer, true, false, null, null);
        $reviews->uploadReviewFile($assignment, $reviewer, 'form_4a', $this->demoFile('form-4a.pdf', 'FICTIONAL DEMO - UCUREC Form 4A'));
        $reviews->submitReview($assignment, $reviewer, $recommendation, null);
        $reviews->forwardForDecision($application->fresh(), $secretariat);

        return $application->fresh();
    }

    private function decide(DecisionService $decisions, Application $application, User $secretariat, User $chair, string $category, ?string $expiry, ?string $comments): void
    {
        $decision = $decisions->create($application, $secretariat, [
            'category' => $category,
            'decision_date' => today()->toDateString(),
            'approval_date' => $expiry ? today()->toDateString() : null,
            'expiry_date' => $expiry,
            'basis' => 'Fictional demo: decided after expedited review.',
            'consolidated_comments' => $comments,
        ]);
        $decisions->uploadFile($decision, $secretariat, Decision::FILE_LETTER,
            $this->demoFile('rec-letter.pdf', 'FICTIONAL DEMO - Signed UCUREC decision letter'));
        $decisions->sendForConfirmation($decision, $secretariat);
        $decisions->confirmAndRelease($decision, $chair);
    }

    /**
     * Reviewer 1 submitted, reviewer 2 has an overdue invitation,
     * reviewer 3 is proposed and waits for the Chair.
     */
    private function reviewers(ReviewService $reviews, Application $application, User $secretariat): void
    {
        $chair = User::where('email', 'chair@ucurec.test')->first();
        [$one, $two, $three] = [
            User::where('email', 'reviewer1@ucurec.test')->first(),
            User::where('email', 'reviewer2@ucurec.test')->first(),
            User::where('email', 'reviewer3@ucurec.test')->first(),
        ];

        if (! $chair || ! $one || ! $two || ! $three) {
            return;
        }

        $first = $reviews->propose($application, $secretariat, $one, today()->addDays(10));
        $reviews->approve($first, $chair);
        $reviews->respond($first, $one, true, false, null, null);
        $reviews->uploadReviewFile($first, $one, 'form_4a', $this->demoFile('form-4a-review.pdf', 'FICTIONAL DEMO - UCUREC Form 4A (completed)'));
        $reviews->uploadReviewFile($first, $one, 'form_4b', $this->demoFile('form-4b-review.pdf', 'FICTIONAL DEMO - UCUREC Form 4B (completed)'));
        $reviews->submitReview($first, $one, 'minor_modifications', 'Clarify how participants can withdraw from the study.');

        $second = $reviews->propose($application, $secretariat, $two, today()->addDays(7));
        $reviews->approve($second, $chair);
        $second->update(['due_at' => today()->subDays(2)]); // demo: overdue

        $reviews->propose($application, $secretariat, $three, today()->addDays(14));
    }

    private function demoFile(string $name, string $text): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ucurec');
        file_put_contents($path, $this->pdf($text));

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function application(ApplicationService $service, User $researcher, string $title): Application
    {
        return $service->createDraft($researcher, [
            'title' => $title,
            'principal_investigator' => $researcher->name,
            'pi_contact' => $researcher->email,
            'adviser' => 'Dr. Ana Villanueva',
            'college_program' => $researcher->college_program ?? config('ucurec.colleges')[0],
            'research_type' => config('ucurec.research_types')[0],
        ]);
    }

    /**
     * @param  list<string>  $categories
     */
    private function upload(ApplicationService $service, Application $application, User $researcher, array $categories): void
    {
        foreach ($categories as $category) {
            $label = config("ucurec.document_categories.{$category}.label");
            $path = tempnam(sys_get_temp_dir(), 'ucurec');
            file_put_contents($path, $this->pdf("FICTIONAL DEMO DOCUMENT - {$label}"));

            $file = new UploadedFile($path, str_replace('_', '-', $category).'.pdf', 'application/pdf', null, true);
            $service->uploadDocument($application, $researcher, $category, $file);

            @unlink($path);
        }
    }

    /**
     * @return array<string, array{state: string, note: ?string}>
     */
    private function checklist(array $overrides = []): array
    {
        $items = [];
        foreach (array_keys(config('ucurec.screening_checklist')) as $key) {
            $items[$key] = ['state' => $overrides[$key] ?? Screening::STATE_PRESENT, 'note' => null];
        }

        return $items;
    }

    /**
     * A tiny one-page PDF with a line of text.
     */
    private function pdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 14 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
