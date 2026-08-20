<?php

namespace App\Livewire\Distribution;

use Livewire\Component;
use App\Models\User;
use App\Livewire\Concerns\HasAdvancedTable;

class TeamAvailability extends Component
{
    use HasAdvancedTable;

    public function tableColumns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Sales Executive', 'render' => function($row) {
                $isOnline = strtolower($row->status ?? 'active') === 'active';
                $dotClass = $isOnline ? 'bg-green-500' : 'bg-gray-400';
                return '<div class="flex items-center space-x-2 font-bold text-ink"><span class="w-2.5 h-2.5 rounded-full ' . $dotClass . '"></span><span>' . e($row->name) . '</span></div>';
            }, 'sortable' => true, 'priority' => 1],
            ['key' => 'email', 'label' => 'Email', 'class' => 'text-muted font-mono', 'sortable' => true, 'priority' => 1],
            ['key' => 'phone', 'label' => 'Phone', 'class' => 'text-muted font-mono', 'default' => 'N/A', 'sortable' => false, 'priority' => 2],
            ['key' => 'status', 'label' => 'Current Duty Status', 'render' => function($row) {
                $isOnline = strtolower($row->status ?? 'active') === 'active';
                $badgeClass = $isOnline ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-600 border border-gray-200';
                $label = $isOnline ? 'ONLINE (ON DUTY)' : 'OFFLINE';
                return '<span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill ' . $badgeClass . '">' . $label . '</span>';
            }, 'sortable' => true, 'priority' => 1],
            ['key' => 'action', 'label' => 'Action', 'align' => 'right', 'render' => function($row) {
                $isOnline = strtolower($row->status ?? 'active') === 'active';
                $toggleText = $isOnline ? 'Offline' : 'Online';
                return '<div class="flex items-center justify-end"><button wire:click="toggleStatus(' . $row->id . ')" class="inline-flex items-center justify-center font-medium rounded-lg transition focus:outline-hidden cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed bg-canvas text-ink hover:bg-surface border border-border text-[10px] px-3 py-1 shadow-2xs">Toggle ' . $toggleText . '</button></div>';
            }, 'sortable' => false, 'priority' => 1],
        ];
    }

    public function toggleStatus(int $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $newStatus = strtolower($user->status ?? 'active') === 'active' ? 'inactive' : 'active';
            $user->update(['status' => $newStatus]);
            $this->dispatch('toast', type: 'success', message: "Availability for {$user->name} updated to " . strtoupper($newStatus) . ".");
        }
    }

    public function render()
    {
        $hasSalesExec = User::role('sales-executive')->exists();
        $query = $hasSalesExec ? User::role('sales-executive') : User::query();

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        $sortField = in_array($this->sortField, ['name', 'email', 'status']) ? $this->sortField : 'id';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $members = $query->orderBy($sortField, $sortDir)->paginate($this->perPage);

        return view('livewire.distribution.team-availability', [
            'members' => $members,
        ])->layout('layouts.app');
    }
}
