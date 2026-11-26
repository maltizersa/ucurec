<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DecisionFile;
use App\Services\ApplicationService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DecisionFileController extends Controller
{
    public function download(DecisionFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        AuditLog::record('decision_file.downloaded', $file->decision->application, 'Downloaded a decision file.', [
            'decision_file_id' => $file->id,
            'kind' => $file->kind,
        ]);

        return Storage::disk(ApplicationService::DISK)->download(
            $file->stored_path,
            $file->original_name,
            [
                'Content-Type' => $file->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
