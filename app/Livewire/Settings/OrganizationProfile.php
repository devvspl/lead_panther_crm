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

    public function mount(): void
    {
        $this->organization = Organization::find(auth()->user()?->organization_id) ?? Organization::first();
        if ($this->organization) {
            $this->name = $this->organization->name;
            $this->billingEmail = auth()->user()?->email ?? 'billing@organization.com';
            $this->phone = '+91 9876543210';
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'billingEmail' => 'required|email',
            'phone' => 'nullable|string|max:50',
        ]);

        $this->organization = Organization::find(auth()->user()?->organization_id) ?? Organization::first();
        if ($this->organization) {
            $this->organization->update(['name' => $this->name]);
            $this->dispatch('toast', type: 'success', message: 'Organization profile updated successfully.');
        }
    }

    public function render()
    {
        return view('livewire.settings.organization-profile')->layout('layouts.app');
    }
}

