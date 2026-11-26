<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Services\ApplicationService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to get a stored file: every request is checked by
 * DocumentPolicy on the server and written to the audit log.
 */
class DocumentController extends Controller
{
    public function download(Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        AuditLog::record('document.downloaded', $document->documentSet->application, 'Downloaded a file.', [
            'document_id' => $document->id,
            'version' => $document->documentSet->version,
        ]);

        return Storage::disk(ApplicationService::DISK)->download(
            $document->stored_path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
