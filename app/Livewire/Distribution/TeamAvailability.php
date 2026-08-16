<?php

namespace App\Livewire\Distribution;

use Livewire\Component;
use App\Models\User;

use Livewire\WithPagination;

class TeamAvailability extends Component
{
    use WithPagination;

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
        $query = User::role('sales-executive');
        if ($query->count() === 0) {
            $query = User::query();
        }

        $members = $query->latest('id')->paginate(15);

        return view('livewire.distribution.team-availability', [
            'members' => $members,
        ])->layout('layouts.app');
    }
}
