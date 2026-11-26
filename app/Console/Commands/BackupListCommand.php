<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Support\FileSize;
use Illuminate\Console\Command;

class BackupListCommand extends Command
{
    protected $signature = 'ucurec:backup-list';

    protected $description = 'List the backups in BACKUP_PATH';

    public function handle(BackupService $backups): int
    {
        $rows = array_map(fn ($b) => [$b['name'], FileSize::format($b['size']), $b['created_at']->format('Y-m-d H:i')], $backups->list());

        if (! $rows) {
            $this->components->warn('No backups yet. Run: php artisan ucurec:backup');

            return self::SUCCESS;
        }

        $this->table(['Backup', 'Size', 'Created'], $rows);

        return self::SUCCESS;
    }
}
