<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\NotificationTemplate;
use App\Livewire\Concerns\HasAdvancedTable;

class NotificationTemplates extends Component
{
    use HasAdvancedTable;

    public ?int $selectedId = null;
    public string $key = '';
    public string $channel = 'whatsapp';
    public string $subject = '';
    public string $body = '';

    public function tableColumns(): array
    {
        return [
            ['key' => 'key', 'label' => 'Template Key', 'class' => 'font-bold font-mono text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'channel', 'label' => 'Channel', 'class' => 'px-2 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-canvas text-ink border border-border inline-block', 'sortable' => true, 'priority' => 1],
            ['key' => 'subject', 'label' => 'Subject / Header', 'class' => 'text-ink font-medium', 'default' => '—', 'sortable' => false, 'priority' => 2],
            ['key' => 'body', 'label' => 'Body Preview', 'render' => fn($row) => '<span class="text-muted truncate max-w-xs block" title="' . e($row->body) . '">' . e(\Illuminate\Support\Str::limit($row->body, 50)) . '</span>', 'sortable' => false, 'priority' => 1],
            ['key' => 'actions', 'label' => 'Actions', 'align' => 'right', 'render' => fn($row) => '<div class="flex items-center justify-end"><button wire:click="editTemplate(' . $row->id . ')" class="text-xs font-semibold text-accent hover:underline cursor-pointer">Edit</button></div>', 'sortable' => false, 'priority' => 1],
        ];
    }

    public function editTemplate(int $id): void
    {
        $tpl = NotificationTemplate::find($id);
        if ($tpl) {
            $this->selectedId = $tpl->id;
            $this->key = $tpl->key;
            $this->channel = $tpl->channel;
            $this->subject = $tpl->subject ?? '';
            $this->body = $tpl->body;
        }
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'key' => 'required|string',
            'channel' => 'required|string',
            'body' => 'required|string',
        ]);

        NotificationTemplate::updateOrCreate(
            ['id' => $this->selectedId],
            [
                'key' => strtolower(str_replace(' ', '_', $this->key)),
                'channel' => $this->channel,
                'subject' => $this->subject,
                'body' => $this->body,
            ]
        );

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Notification template saved successfully.');
    }

    public function resetForm(): void
    {
        $this->selectedId = null;
        $this->key = '';
        $this->channel = 'whatsapp';
        $this->subject = '';
        $this->body = '';
    }

    public function render()
    {
        $query = NotificationTemplate::query();

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('key', 'like', "%{$this->search}%")
                  ->orWhere('channel', 'like', "%{$this->search}%")
                  ->orWhere('subject', 'like', "%{$this->search}%")
                  ->orWhere('body', 'like', "%{$this->search}%");
            });
        }

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('channel', $this->statusFilter);
        }

        $sortField = in_array($this->sortField, ['key', 'channel', 'id']) ? $this->sortField : 'id';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $templates = $query->orderBy($sortField, $sortDir)->paginate($this->perPage);

        return view('livewire.admin.notification-templates', [
            'templates' => $templates,
        ])->layout('layouts.app');
    }
}
