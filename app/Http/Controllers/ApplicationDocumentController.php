<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Services\ApplicationService;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Researcher uploads and removes files in the draft document set.
 */
class ApplicationDocumentController extends Controller
{
    public function __construct(private ApplicationService $service) {}

    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('ucurec.document_categories')))],
            'file' => UploadRules::rules(),
        ], UploadRules::messages());

        $this->service->uploadDocument($application, $request->user(), $validated['category'], $validated['file']);

        return redirect()->to(route('applications.show', $application).'#documents')
            ->with('success', 'File uploaded.');
    }

    public function destroy(Request $request, Application $application, Document $document): RedirectResponse
    {
        abort_unless($document->documentSet->application_id === $application->id, 404);
        $this->authorize('delete', $document);

        $this->service->removeDocument($document, $request->user());

        return redirect()->to(route('applications.show', $application).'#documents')
            ->with('success', 'File removed from the draft.');
    }
}
