<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogBrowser extends Component
{
    use HasAdvancedTable;

    public ?int $selectedUserId = null;
    public string $actionType = '';
    public string $subjectType = '';
    public string $dateRange = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono text-muted text-[11px]'],
            ['key' => 'created_at', 'label' => 'Timestamp', 'type' => 'date', 'sortable' => true, 'priority' => 1, 'format' => 'M d, Y H:i:s'],
            ['key' => 'user_name', 'label' => 'User', 'type' => 'text', 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'action', 'label' => 'Action', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeStyle' => function ($val) {
                if (str_contains($val, 'delete') || str_contains($val, 'revert') || str_contains($val, 'hard') || str_contains($val, 'reject')) {
                    return 'bg-red-50 text-red-700 border border-red-200';
                }
                if (str_contains($val, 'create') || str_contains($val, 'approve') || str_contains($val, 'success')) {
                    return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                }
                if (str_contains($val, 'update') || str_contains($val, 'edit') || str_contains($val, 'pull')) {
                    return 'bg-blue-50 text-blue-700 border border-blue-200';
                }
                return 'bg-purple-50 text-purple-700 border border-purple-200';
            }],
            ['key' => 'subject_summary', 'label' => 'Subject', 'type' => 'text', 'priority' => 2, 'class' => 'font-mono text-muted'],
            ['key' => 'changes_summary', 'label' => 'Changes', 'type' => 'text', 'priority' => 1, 'class' => 'font-mono text-xs max-w-xs truncate text-muted'],
            ['key' => 'formatted_ip', 'label' => 'IP Address', 'type' => 'text', 'priority' => 2, 'class' => 'font-mono text-[11px] text-muted'],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Logs'],
            ['key' => 'today', 'label' => 'Today'],
            ['key' => 'week', 'label' => 'This Week'],
            ['key' => 'auth', 'label' => 'Auth & Sessions'],
            ['key' => 'git_sync', 'label' => 'Git Deployments'],
        ];
    }

    protected function getFilteredQuery()
    {
        $query = AuditLog::with('user');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('action', 'like', '%' . $this->search . '%')
                  ->orWhere('to_value', 'like', '%' . $this->search . '%')
                  ->orWhere('from_value', 'like', '%' . $this->search . '%')
                  ->orWhere('ip_address', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $this->search . '%'));
            });
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

        // Quick filter pills
        if ($this->statusFilter === 'today') {
            $query->whereDate('created_at', now());
        } elseif ($this->statusFilter === 'week') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($this->statusFilter === 'auth') {
            $query->where(function ($q) {
                $q->where('action', 'like', '%auth%')
                  ->orWhere('action', 'like', '%login%')
                  ->orWhere('action', 'like', '%impersonat%')
                  ->orWhere('action', 'like', '%password%');
            });
        } elseif ($this->statusFilter === 'git_sync') {
            $query->where(function ($q) {
                $q->where('subject_type', 'git_sync')
                  ->orWhere('action', 'like', '%git%');
            });
        }

        if ($this->dateRange) {
            if ($this->dateRange === 'today') {
                $query->whereDate('created_at', now());
            } elseif ($this->dateRange === 'week') {
                $query->where('created_at', '>=', now()->subDays(7));
            } elseif ($this->dateRange === 'month') {
                $query->where('created_at', '>=', now()->subDays(30));
            } elseif ($this->dateRange === 'custom' && $this->customFrom && $this->customTo) {
                $query->whereBetween('created_at', [$this->customFrom . ' 00:00:00', $this->customTo . ' 23:59:59']);
            }
        }

        if (!empty($this->sortField)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('id');
        }

        return $query;
    }

    public function render()
    {
        $logs = $this->getFilteredQuery()->paginate($this->perPage);
        $users = User::orderBy('name')->get();

        return view('livewire.admin.audit-log-browser', [
            'logs' => $logs,
            'users' => $users,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = $this->getFilteredQuery()->get();
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
