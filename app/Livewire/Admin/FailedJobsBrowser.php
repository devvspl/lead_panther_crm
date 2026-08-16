<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class FailedJobsBrowser extends Component
{
    use WithPagination;

    public function retryJob(int $id): void
    {
        try {
            Artisan::call('queue:retry', ['id' => [$id]]);
            $this->dispatch('toast', type: 'success', message: "Failed job #{$id} dispatched back to queue worker.");
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Retry failed: {$e->getMessage()}");
        }
    }

    public function retryAll(): void
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
            $this->dispatch('toast', type: 'success', message: "All failed jobs dispatched back to queue worker.");
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Retry all failed: {$e->getMessage()}");
        }
    }

    public function forgetJob(int $id): void
    {
        try {
            Artisan::call('queue:forget', ['id' => $id]);
            $this->dispatch('toast', type: 'success', message: "Failed job #{$id} removed from database.");
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Forget job failed: {$e->getMessage()}");
        }
    }

    public function render()
    {
        $failedJobs = DB::table('failed_jobs')->latest('failed_at')->paginate(10);

        return view('livewire.admin.failed-jobs-browser', [
            'failedJobs' => $failedJobs,
        ])->layout('layouts.app');
    }
}
