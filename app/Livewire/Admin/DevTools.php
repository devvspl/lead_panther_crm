<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Artisan;
use App\Models\Organization;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\CreditTransaction;
use App\Models\LeadReplacement;
use App\Models\AuditLog;

class DevTools extends Component
{
    public string $confirmPhrase = '';
    public bool $isSeeding = false;
    public bool $isClearing = false;
    public array $stats = [];
    public array $lastSeedSummary = [];

    public function mount(): void
    {
        if (!in_array(config('app.env'), ['local', 'staging', 'testing'])) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->hasRole('super-admin'))) {
            abort(403);
        }

        $this->refreshStats();
    }

    public function refreshStats(): void
    {
        $this->stats = [
            'organizations' => Organization::count(),
            'users' => User::count(),
            'clients' => Client::count(),
            'projects' => Project::count(),
            'leads' => Lead::count(),
            'credit_transactions' => CreditTransaction::count(),
            'replacements' => LeadReplacement::count(),
            'audit_logs' => AuditLog::count(),
        ];
    }

    public function reseedDatabase(): void
    {
        if (!in_array(config('app.env'), ['local', 'staging', 'testing'])) {
            abort(404);
        }

        $this->isSeeding = true;

        try {
            $exitCode = Artisan::call('db:seed', ['--force' => true]);
            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'Seeding failed with exit code ' . $exitCode);
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'dev_tools.reseed_database',
                'subject_type' => 'Database',
                'subject_id' => 0,
                'from_value' => null,
                'to_value' => 'Reseeded full demo dataset at ' . now()->toIso8601String(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            $this->refreshStats();
            $this->lastSeedSummary = [
                'organizations' => $this->stats['organizations'],
                'users' => $this->stats['users'],
                'leads' => $this->stats['leads'],
                'projects' => $this->stats['projects'],
                'transactions' => $this->stats['credit_transactions'],
            ];

            $this->dispatch('toast', type: 'success', message: 'Database reseeded successfully with full demo dataset.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Seeding failed: ' . $e->getMessage());
        } finally {
            $this->isSeeding = false;
        }
    }

    public function clearAllData(): void
    {
        if (!in_array(config('app.env'), ['local', 'staging', 'testing'])) {
            abort(404);
        }

        if (trim($this->confirmPhrase) !== 'DELETE ALL DATA') {
            $this->dispatch('toast', type: 'error', message: 'Exact confirmation phrase "DELETE ALL DATA" required.');
            return;
        }

        $this->isClearing = true;

        try {
            $exitCode = Artisan::call('dev:reset-data', ['--force' => true]);
            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'Command failed with exit code ' . $exitCode);
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'dev_tools.clear_all_data',
                'subject_type' => 'Database',
                'subject_id' => 0,
                'from_value' => 'Full Database State',
                'to_value' => 'Cleared all data down to Super Admin account at ' . now()->toIso8601String(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            $this->confirmPhrase = '';
            $this->lastSeedSummary = [];
            $this->refreshStats();

            $this->dispatch('toast', type: 'success', message: 'All database data cleared successfully. Super Admin account preserved.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Reset failed: ' . $e->getMessage());
        } finally {
            $this->isClearing = false;
        }
    }

    public function render()
    {
        return view('livewire.admin.dev-tools')->layout('layouts.app');
    }
}
