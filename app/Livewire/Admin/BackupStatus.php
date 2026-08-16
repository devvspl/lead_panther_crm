<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Throwable;

use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;

class BackupStatus extends Component
{
    use WithPagination;

    public function runBackup(): void
    {
        try {
            Storage::disk('backup_storage')->makeDirectory('');
            Storage::disk('documents')->makeDirectory('');

            $exitCode = Artisan::call('backup:run');
            if ($exitCode === 0) {
                $this->dispatch('toast', type: 'success', message: 'Backup completed successfully! Snapshot saved to destination storage disk.');
            } else {
                $output = Artisan::output();
                \Illuminate\Support\Facades\Log::error('Backup command failed', ['output' => $output]);
                $this->dispatch('toast', type: 'error', message: 'Backup failed. Check system logs for details.');
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Backup execution error: {$e->getMessage()}");
        }
    }

    public function cleanBackups(): void
    {
        try {
            $exitCode = Artisan::call('backup:clean');
            if ($exitCode === 0) {
                $this->dispatch('toast', type: 'success', message: 'Old backup files cleaned up according to retention policy.');
            } else {
                $output = Artisan::output();
                \Illuminate\Support\Facades\Log::error('Backup clean failed', ['output' => $output]);
                $this->dispatch('toast', type: 'error', message: 'Cleanup failed. Check system logs for details.');
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Cleanup error: {$e->getMessage()}");
        }
    }

    public function render()
    {
        $backupDisk = Storage::disk('backup_storage');
        $files = [];
        $totalSize = 0;
        $lastBackupTime = 'Never';

        if ($backupDisk->exists('')) {
            $allFiles = $backupDisk->allFiles();
            foreach ($allFiles as $file) {
                if (str_ends_with($file, '.gitkeep')) {
                    continue;
                }
                $size = $backupDisk->size($file);
                $time = $backupDisk->lastModified($file);
                $totalSize += $size;

                $files[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => number_format($size / (1024 * 1024), 2) . ' MB',
                    'modified_at' => date('Y-m-d H:i:s', $time),
                ];
            }

            if (!empty($files)) {
                usort($files, function ($a, $b) {
                    return strcmp($b['modified_at'], $a['modified_at']);
                });
                $lastBackupTime = $files[0]['modified_at'];
            }
        }

        $allCollection = collect($files);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $items = $allCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedArchives = new LengthAwarePaginator(
            $items,
            $allCollection->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page']
        );

        return view('livewire.admin.backup-status', [
            'archives' => $paginatedArchives,
            'totalSizeFormatted' => number_format($totalSize / (1024 * 1024), 2) . ' MB',
            'lastBackupTime' => $lastBackupTime,
            'isHealthy' => true,
        ])->layout('layouts.app');
    }
}
