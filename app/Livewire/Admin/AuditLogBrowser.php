<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogBrowser extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $selectedUserId = null;
    public string $actionType = '';
    public string $subjectType = '';

    public function render()
    {
        $query = AuditLog::with('user');

        if ($this->search) {
            $query->where('action', 'like', '%' . $this->search . '%');
        }

        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }

        if ($this->actionType) {
            $query->where('action', 'like', $this->actionType . '%');
        }

        if ($this->subjectType) {
            $query->where('subject_type', 'like', '%' . $this->subjectType . '%');
        }

        $logs = $query->latest('id')->paginate(15);
        $users = User::all();

        return view('livewire.admin.audit-log-browser', [
            'logs' => $logs,
            'users' => $users,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $query = AuditLog::with('user');

        if ($this->search) {
            $query->where('action', 'like', '%' . $this->search . '%');
        }

        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }

        if ($this->actionType) {
            $query->where('action', 'like', $this->actionType . '%');
        }

        if ($this->subjectType) {
            $query->where('subject_type', 'like', '%' . $this->subjectType . '%');
        }

        $data = $query->latest('id')->get();
        $filename = "audit-logs_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Log ID', 'Timestamp', 'User Name', 'Action', 'Subject Type', 'Subject ID', 'From Value', 'To Value', 'IP Address'];
        $columns = [
            'id',
            fn($l) => $l->created_at ? $l->created_at->format('M d, Y H:i:s') : '',
            fn($l) => $l->user?->name ?: 'System',
            'action',
            fn($l) => class_basename($l->subject_type),
            'subject_id',
            'from_value',
            'to_value',
            'ip_address',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->search ? " | Search: {$this->search}" : '');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'System Audit Logs Directory',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['action']
            ),
            $filename
        );
    }
}
