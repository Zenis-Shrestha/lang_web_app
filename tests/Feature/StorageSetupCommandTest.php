<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Mockery;
use Tests\TestCase;

class StorageSetupCommandTest extends TestCase
{
    private string $sandbox;
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = sys_get_temp_dir() . '/imis-storage-setup-' . bin2hex(random_bytes(8));
        $this->files = new Filesystem();
        $this->app->useStoragePath($this->sandbox . '/runtime');
        foreach (['local', 'public', 'importtax', 'importwatersupply', 'importswmpayment'] as $disk) {
            config(['filesystems.disks.' . $disk => [
                'driver' => 'local', 'root' => $this->sandbox . '/' . $disk,
            ]]);
        }
    }

    public function test_registered_command_creates_required_directories(): void
    {
        $this->artisan('storage:setup')->assertExitCode(0);
        foreach ([
            'runtime/framework/cache/data', 'runtime/framework/sessions',
            'runtime/framework/views', 'runtime/logs', 'local/cwis/safety-documents',
            'local/cwis/reset-backups', 'public/emptyings/houses',
            'public/emptyings/receipts', 'public/cwis/equity-documents',
            'importtax', 'importwatersupply', 'importswmpayment',
        ] as $directory) {
            $this->assertDirectoryExists($this->sandbox . '/' . $directory);
        }
    }

    public function test_existing_uploads_survive_repeated_setup(): void
    {
        $directory = $this->sandbox . '/public/emptyings/houses';
        $this->files->ensureDirectoryExists($directory);
        $upload = $directory . '/existing-upload.jpg';
        $this->files->put($upload, 'original-upload-contents');
        $this->artisan('storage:setup')->assertExitCode(0);
        $this->artisan('storage:setup')->assertExitCode(0);
        $this->assertSame('original-upload-contents', $this->files->get($upload));
    }

    public function test_permission_errors_are_reported_with_a_failure_exit_code(): void
    {
        $blocked = storage_path('logs');
        $realFiles = $this->files;
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('isWritable')->andReturnUsing(
            fn ($path) => $path !== $blocked && $realFiles->isWritable($path)
        );
        $this->app->instance(Filesystem::class, $files);
        $this->artisan('storage:setup')
            ->expectsOutput('Unable to prepare ' . $blocked . ': Directory does not exist or is not writable.')
            ->assertExitCode(1);
    }

    public function test_a_file_in_place_of_a_directory_is_not_overwritten(): void
    {
        $parent = storage_path('framework/cache');
        $this->files->ensureDirectoryExists($parent);
        $obstruction = $parent . '/data';
        $this->files->put($obstruction, 'preserve-this-file');
        $this->artisan('storage:setup')->assertExitCode(1);
        $this->assertSame('preserve-this-file', $this->files->get($obstruction));
    }
}
