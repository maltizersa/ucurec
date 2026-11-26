<?php

namespace Tests\Feature\Security;

use App\Models\Application;
use App\Models\Document;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;
use ZipArchive;

/**
 * "Provide a scheduled database and document backup ... Demonstrate a
 * successful restore before handover."
 *
 * Uses DatabaseMigrations (not RefreshDatabase) because a restore needs to
 * switch foreign-key checks off, which SQLite ignores inside a transaction.
 */
class BackupRestoreTest extends TestCase
{
    use CreatesApplications, DatabaseMigrations;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        $this->backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ucurec-test-backups-'.uniqid();
        config(['ucurec.backup.path' => $this->backupDir, 'ucurec.backup.password' => null, 'ucurec.backup.keep' => 14]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);
        parent::tearDown();
    }

    private function backups(): BackupService
    {
        return app(BackupService::class);
    }

    public function test_backup_then_restore_brings_back_records_and_files(): void
    {
        $application = $this->forReviewApplication();
        $files = Document::all()->mapWithKeys(fn ($d) => [$d->stored_path => Storage::disk('documents')->get($d->stored_path)])->all();
        $userCount = User::count();

        $backup = $this->backups()->create();
        $this->assertFileExists($backup['path']);
        $this->assertGreaterThan(0, $backup['documents']);

        // Disaster: records deleted, files lost, junk added.
        DB::table('applications')->delete();
        foreach (array_keys($files) as $path) {
            Storage::disk('documents')->delete($path);
        }
        User::factory()->create(['email' => 'after-backup@example.com']);

        $result = $this->backups()->restore($backup['path']);

        $restored = Application::find($application->id);
        $this->assertNotNull($restored);
        $this->assertSame($application->reference_no, $restored->reference_no);
        $this->assertSame($application->status, $restored->status);
        $this->assertSame($userCount, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'after-backup@example.com']);

        foreach ($files as $path => $content) {
            $this->assertSame($content, Storage::disk('documents')->get($path), "File not restored: {$path}");
        }
        // Stored checksums still match the restored files.
        foreach (Document::all() as $document) {
            $this->assertSame($document->sha256, hash('sha256', Storage::disk('documents')->get($document->stored_path)));
        }

        $this->assertStringStartsWith('pre-restore-', $result['safety_backup']);
        $this->assertFileExists($this->backupDir.DIRECTORY_SEPARATOR.$result['safety_backup']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.restored']);
    }

    public function test_a_changed_backup_is_rejected_before_anything_is_touched(): void
    {
        $this->submittedApplication();
        $backup = $this->backups()->create();

        $zip = new ZipArchive;
        $zip->open($backup['path']);
        $zip->addFromString('database/applications.jsonl', '{"id":999,"title":"forged"}'."\n");
        $zip->close();

        $countBefore = Application::count();

        try {
            $this->backups()->restore($backup['path']);
            $this->fail('A tampered backup was restored.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Checksum mismatch', $e->getMessage());
        }

        $this->assertSame($countBefore, Application::count());
    }

    public function test_encrypted_backup_needs_the_right_password(): void
    {
        config(['ucurec.backup.password' => 'correct-horse-battery']);
        $this->submittedApplication();
        $backup = $this->backups()->create();

        $this->assertIsArray($this->backups()->verify($backup['path']));

        config(['ucurec.backup.password' => 'wrong-password']);
        $this->expectException(RuntimeException::class);
        $this->backups()->verify($backup['path']);
    }

    public function test_restore_refuses_a_backup_from_another_database_version(): void
    {
        $backup = $this->backups()->create();
        DB::table('migrations')->insert(['migration' => '2099_01_01_000000_future_change', 'batch' => 99]);

        $this->expectExceptionMessage('different database version');
        $this->backups()->restore($backup['path']);
    }

    public function test_paths_that_escape_the_documents_folder_are_rejected(): void
    {
        $path = $this->backupDir.DIRECTORY_SEPARATOR.'evil.zip';
        File::ensureDirectoryExists($this->backupDir);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('documents/../../evil.php', '<?php echo 1;');
        $zip->addFromString('manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'migrations' => [],
            'tables' => [],
            'documents' => ['../../evil.php' => hash('sha256', '<?php echo 1;')],
        ]));
        $zip->close();

        $this->expectExceptionMessage('Unsafe file path');
        $this->backups()->verify($path);
    }

    public function test_old_backups_are_pruned(): void
    {
        config(['ucurec.backup.keep' => 2]);

        foreach (['2026-11-20 01:00:00', '2026-11-21 01:00:00', '2026-11-22 01:00:00'] as $time) {
            Carbon::setTestNow($time);
            $backup = $this->backups()->create();
            touch($backup['path'], Carbon::parse($time)->getTimestamp());
        }
        Carbon::setTestNow();

        $names = array_column($this->backups()->list(), 'name');
        $this->assertCount(2, $names);
        $this->assertNotContains('ucurec-backup-20261120-010000.zip', $names);
    }

    public function test_artisan_commands(): void
    {
        $this->submittedApplication();

        $this->artisan('ucurec:backup')->assertSuccessful();
        $name = $this->backups()->latest()['name'];

        $this->artisan('ucurec:backup-list')->expectsOutputToContain($name)->assertSuccessful();
        $this->artisan('ucurec:backup-verify', ['file' => $name])->expectsOutputToContain('All checksums match')->assertSuccessful();
        $this->artisan('ucurec:restore', ['file' => $name])
            ->expectsConfirmation('Restore this backup now?', 'no')
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();
        $this->artisan('ucurec:restore', ['file' => $name, '--force' => true])->expectsOutputToContain('Restore complete')->assertSuccessful();
    }

    public function test_admin_dashboard_shows_backup_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertSee('No backup yet');

        $this->backups()->create();
        $this->actingAs($admin)->get('/dashboard')->assertSee('Last backup of the database and documents')->assertDontSee('No backup yet');
    }
}
