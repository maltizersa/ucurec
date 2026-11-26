<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Support\FileSize;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'ucurec:backup';

    protected $description = 'Back up the database and all uploaded documents to BACKUP_PATH';

    public function handle(BackupService $backups): int
    {
        $this->components->info('Creating backup…');

        $result = $backups->create();

        $this->components->twoColumnDetail('File', $result['path']);
        $this->components->twoColumnDetail('Size', FileSize::format($result['size']));
        $this->components->twoColumnDetail('Tables / rows', "{$result['tables']} / {$result['rows']}");
        $this->components->twoColumnDetail('Documents', (string) $result['documents']);
        $this->components->info('Backup complete. Copy BACKUP_PATH to university-controlled storage if it is not already there.');

        return self::SUCCESS;
    }
}
