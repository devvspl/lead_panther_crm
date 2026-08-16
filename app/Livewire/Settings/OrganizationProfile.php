<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\Organization;

class OrganizationProfile extends Component
{
    public string $name = '';
    public string $billingEmail = '';
    public string $phone = '';

    public function mount(): void
    {
        $org = Organization::find(auth()->user()?->organization_id) ?? Organization::first();
        if ($org) {
            $this->name = $org->name;
            $this->billingEmail = auth()->user()?->email ?? 'billing@organization.com';
            $this->phone = '+91 9876543210';
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'billingEmail' => 'required|email',
        ]);

        $org = Organization::find(auth()->user()?->organization_id) ?? Organization::first();
        if ($org) {
            $org->update(['name' => $this->name]);
            $this->dispatch('toast', type: 'success', message: 'Organization profile updated successfully.');
        }
    }

    public function render()
    {
        return view('livewire.settings.organization-profile')->layout('layouts.app');
    }
}
