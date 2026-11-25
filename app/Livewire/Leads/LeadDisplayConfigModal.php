<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use App\Models\Organization;
use Livewire\Attributes\On;
use Livewire\Component;

class LeadDisplayConfigModal extends Component
{
    public bool $isOpen = false;

    public ?int $organizationId = null;

    public string $organizationName = '';

    public array $config = [];

    /**
     * All configurable lead drawer fields with descriptions.
     */
    public array $fieldDefinitions = [
        'phone' => [
            'label' => 'Phone / Mobile',
            'category' => 'Customer Info',
            'description' => 'Customer mobile number, call shortcut, and WhatsApp chat button.',
        ],
        'email' => [
            'label' => 'Email Address',
            'category' => 'Customer Info',
            'description' => 'Customer email address and contact details.',
        ],
        'city' => [
            'label' => 'City / Location',
            'category' => 'Customer Info',
            'description' => 'City, preferred area, or locality of the customer.',
        ],
        'project' => [
            'label' => 'Project',
            'category' => 'Project & Budget',
            'description' => 'CRM Project associated with the lead.',
        ],
        'property_type' => [
            'label' => 'Property Type',
            'category' => 'Project & Budget',
            'description' => 'Residential, commercial, unit type or BHK configuration.',
        ],
        'budget' => [
            'label' => 'Budget',
            'category' => 'Project & Budget',
            'description' => 'Customer investment budget (subject to financial permissions).',
        ],
        'requirement' => [
            'label' => 'Customer Requirements',
            'category' => 'Requirements & Notes',
            'description' => 'General inquiry notes, manual requirements, or portal comments.',
        ],
        'meta_submission' => [
            'label' => 'Lead Form Responses',
            'category' => 'Campaign & Form Intelligence',
            'description' => 'Parsed question-and-answer responses from online lead forms.',
        ],
        'meta_context' => [
            'label' => 'Campaign Attribution',
            'category' => 'Campaign & Form Intelligence',
            'description' => 'Campaign, Ad Set, Ad creative name, and Form attribution.',
        ],
    ];

    #[On('open-display-config')]
    public function open(?int $leadId = null, ?int $organizationId = null): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $organization = null;

        // 1. Resolve organization by explicit ID
        if ($organizationId) {
            $organization = Organization::find($organizationId);
        }

        // 2. Resolve organization by lead
        if (! $organization && $leadId) {
            $lead = Lead::withoutGlobalScopes()->with(['client.organization', 'assignedTo.organization'])->find($leadId);
            if ($lead) {
                $organization = $lead->organization;
            }
        }

        // 3. Fallback to user's own organization
        if (! $organization && $user->organization_id) {
            $organization = Organization::find($user->organization_id);
        }

        if (! $organization) {
            $this->dispatch('toast', type: 'error', message: 'No associated organization found to configure.');

            return;
        }

        // Authorization check: Super Admin OR Organization Client/Admin
        $isSuperAdmin = $user->hasAnyRole(['Super Admin', 'super-admin']);
        $isOrgAdmin = ($user->organization_id === $organization->id) && $user->hasAnyRole(['Client', 'client', 'Builder', 'builder', 'Builder/Developer', 'Admin', 'admin']);

        if (! $isSuperAdmin && ! $isOrgAdmin && $user->organization_id !== $organization->id) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized: You can only configure display settings for your own organization.');

            return;
        }

        $this->organizationId = $organization->id;
        $this->organizationName = $organization->name;
        $this->config = $organization->getLeadDisplayConfig();
        $this->isOpen = true;
    }

    public function selectAll(): void
    {
        foreach (array_keys($this->fieldDefinitions) as $key) {
            $this->config[$key] = true;
        }
    }

    public function deselectAll(): void
    {
        foreach (array_keys($this->fieldDefinitions) as $key) {
            $this->config[$key] = false;
        }
    }

    public function save(): void
    {
        $user = auth()->user();
        if (! $user || ! $this->organizationId) {
            return;
        }

        $organization = Organization::findOrFail($this->organizationId);

        $isSuperAdmin = $user->hasAnyRole(['Super Admin', 'super-admin']);
        $isOrgAdmin = ($user->organization_id === $organization->id) && $user->hasAnyRole(['Client', 'client', 'Builder', 'builder', 'Builder/Developer', 'Admin', 'admin']);

        if (! $isSuperAdmin && ! $isOrgAdmin && $user->organization_id !== $organization->id) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized.');

            return;
        }

        // Sanitize and ensure boolean values for all keys
        $cleanConfig = [];
        foreach (array_keys($this->fieldDefinitions) as $key) {
            $cleanConfig[$key] = (bool) ($this->config[$key] ?? false);
        }

        $organization->update(['lead_display_config' => $cleanConfig]);

        $this->isOpen = false;
        $this->dispatch('toast', type: 'success', message: "Lead display preferences saved for '{$organization->name}'.");
        $this->dispatch('lead-display-config-updated');
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function render()
    {
        return view('livewire.leads.lead-display-config-modal');
    }
}
