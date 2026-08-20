<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use App\Livewire\Concerns\HasAdvancedTable;

class FailedJobsBrowser extends Component
{
    use HasAdvancedTable;

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Job ID', 'prefix' => '#', 'class' => 'font-mono font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'queue', 'label' => 'Connection / Queue', 'render' => fn($row) => '<div class="font-mono text-xs"><span class="font-bold text-ink">' . e($row->connection) . '</span> / <span class="text-muted">' . e($row->queue) . '</span></div>', 'sortable' => false, 'priority' => 1],
            ['key' => 'payload', 'label' => 'Job Payload Name', 'render' => fn($row) => '<div class="max-w-xs truncate font-mono text-[11px] text-ink" title="' . e($row->payload) . '">' . e(\Illuminate\Support\Str::limit($row->payload, 60)) . '</div>', 'sortable' => false, 'priority' => 1],
            ['key' => 'exception', 'label' => 'Failure Exception', 'render' => fn($row) => '<div class="max-w-xs text-red-600 font-mono text-[10px] truncate" title="' . e($row->exception) . '">' . e(\Illuminate\Support\Str::limit($row->exception, 80)) . '</div>', 'sortable' => false, 'priority' => 2],
            ['key' => 'failed_at', 'label' => 'Failed At', 'class' => 'font-mono text-muted whitespace-nowrap text-[11px]', 'sortable' => true, 'priority' => 2],
            ['key' => 'actions', 'label' => 'Actions', 'align' => 'right', 'render' => fn($row) => '<div class="flex items-center justify-end space-x-3 whitespace-nowrap"><button wire:click="retryJob(' . $row->id . ')" class="text-xs font-bold text-primary hover:underline cursor-pointer">Retry 🔄</button><button wire:click="forgetJob(' . $row->id . ')" class="text-xs font-bold text-danger hover:underline cursor-pointer">Delete 🗑️</button></div>', 'sortable' => false, 'priority' => 1],
        ];
    }

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
        $query = DB::table('failed_jobs');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('queue', 'like', "%{$this->search}%")
                  ->orWhere('payload', 'like', "%{$this->search}%")
                  ->orWhere('exception', 'like', "%{$this->search}%");
            });
        }

        $sortField = in_array($this->sortField, ['failed_at', 'id', 'queue']) ? $this->sortField : 'failed_at';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $failedJobs = $query->orderBy($sortField, $sortDir)->paginate($this->perPage);

        return view('livewire.admin.failed-jobs-browser', [
            'failedJobs' => $failedJobs,
        ])->layout('layouts.app');
    }
}
