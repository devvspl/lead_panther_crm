<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $userId;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $userId = $this->userId ?? 0;
        $runningKey = 'backup_running_' . $userId;
        $startKey = 'backup_start_time_' . $userId;

        try {
            Storage::disk('backup_storage')->makeDirectory('');
            Storage::disk('documents')->makeDirectory('');

            $exitCode = Artisan::call('backup:run');
            if ($exitCode !== 0) {
                Log::error('Backup job failed', ['output' => Artisan::output()]);
            }
        } catch (Throwable $e) {
            Log::error('Backup job exception: ' . $e->getMessage());
        } finally {
            Cache::forget($runningKey);
            Cache::forget($startKey);
        }
    }
}
