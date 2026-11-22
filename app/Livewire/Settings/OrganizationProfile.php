<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\Organization;

class OrganizationProfile extends Component
{
    public ?Organization $organization = null;
    public string $name = '';
    public string $billingEmail = '';
    public string $phone = '';
    public string $leadAssignmentMode = 'automatic';

    public function mount(): void
    {
        $this->organization = Organization::find(auth()->user()?->organization_id) ?? Organization::first();
        if ($this->organization) {
            $this->name = $this->organization->name;
            $this->billingEmail = auth()->user()?->email ?? 'billing@organization.com';
            $this->phone = '+91 9876543210';
            $this->leadAssignmentMode = $this->organization->getLeadAssignmentMode();
        }
    }

    public function setLeadAssignmentMode(string $mode): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('Super Admin') || ($user->organization_id === $this->organization?->id && $user->hasAnyRole(['Builder/Developer', 'Builder', 'builder', 'builder-admin']))), 403);

        if (!in_array($mode, [Organization::ASSIGNMENT_MODE_AUTOMATIC, Organization::ASSIGNMENT_MODE_MANUAL])) {
            return;
        }

        $this->organization = Organization::find(auth()->user()?->organization_id) ?? $this->organization;
        if ($this->organization) {
            $this->organization->update([
                'lead_assignment_mode' => $mode,
                'auto_assign_enabled' => ($mode === Organization::ASSIGNMENT_MODE_AUTOMATIC),
            ]);
            $this->leadAssignmentMode = $mode;
            $this->dispatch('toast', type: 'success', message: 'Lead assignment mode updated to ' . ($mode === Organization::ASSIGNMENT_MODE_AUTOMATIC ? 'Automatic Assignment' : 'Manual Assignment (Builder / Developer)') . '.');
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'billingEmail' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'leadAssignmentMode' => 'required|in:automatic,manual',
        ]);

        $this->organization = Organization::find(auth()->user()?->organization_id) ?? Organization::first();
        if ($this->organization) {
            $this->organization->update([
                'name' => $this->name,
                'lead_assignment_mode' => $this->leadAssignmentMode,
                'auto_assign_enabled' => ($this->leadAssignmentMode === Organization::ASSIGNMENT_MODE_AUTOMATIC),
            ]);
            $this->dispatch('toast', type: 'success', message: 'Organization profile updated successfully.');
        }
    }

    public function render()
    {
        return view('livewire.settings.organization-profile')->layout('layouts.app');
    }
}
