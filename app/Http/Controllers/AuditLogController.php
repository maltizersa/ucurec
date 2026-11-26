<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = trim((string) $request->query('action'));

        $logs = AuditLog::query()
            ->with('user:id,name,role')
            ->when($action !== '', fn ($query) => $query->where('action', 'like', "{$action}%"))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'action' => $action,
            'actionGroups' => [
                'auth' => 'Sign-in',
                'user' => 'Accounts',
                'account' => 'Own account',
                'application' => 'Applications',
                'document' => 'Documents',
                'screening' => 'Screening',
                'assignment' => 'Reviewer assignments',
                'review' => 'Reviews',
                'decision' => 'Decisions',
                'register' => 'Register exports',
                'backup' => 'Backups',
            ],
        ]);
    }
}
