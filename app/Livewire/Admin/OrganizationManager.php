<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;

use Livewire\WithPagination;

class OrganizationManager extends Component
{
    use WithPagination;

    public string $name = '';
    public string $type = 'builder';
    public string $status = 'active';

    public ?int $editingOrganizationId = null;

    public function createOrganization(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:builder,channel_partner,platform',
        ]);

        Organization::create([
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
        ]);

        $this->reset(['name', 'type', 'status']);
        $this->dispatch('toast', type: 'success', message: 'Organization created successfully.');
    }

    public function toggleStatus(int $id): void
    {
        $org = Organization::find($id);
        if ($org) {
            $newStatus = ($org->status === 'active') ? 'suspended' : 'active';
            $org->update(['status' => $newStatus]);
            $this->dispatch('toast', type: 'success', message: "Organization {$org->name} status changed to {$newStatus}.");
        }
    }

    public function render()
    {
        $organizations = Organization::withCount(['clients'])->latest('id')->paginate(10);

        return view('livewire.admin.organization-manager', [
            'organizations' => $organizations,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = Organization::withCount(['clients'])->latest('id')->get();
        $filename = "organizations-directory_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Org ID', 'Organization Name', 'Type', 'Clients Count', 'Status', 'Created At'];
        $columns = [
            'id',
            'name',
            fn($o) => str_replace('_', ' ', $o->type),
            'clients_count',
            'status',
            fn($o) => $o->created_at ? $o->created_at->format('M d, Y H:i') : '',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Multi-Tenant Organizations Directory',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['status']
            ),
            $filename
        );
    }
}
