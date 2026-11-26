<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ReviewFile;
use App\Services\ApplicationService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewFileController extends Controller
{
    public function download(ReviewFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        AuditLog::record('review_file.downloaded', $file->assignment->application, 'Downloaded a review form.', [
            'review_file_id' => $file->id,
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
