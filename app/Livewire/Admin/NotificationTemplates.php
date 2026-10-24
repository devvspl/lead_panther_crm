<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Services\NotificationDeliveryService;
use App\Livewire\Concerns\HasAdvancedTable;

class NotificationTemplates extends Component
{
    use HasAdvancedTable;

    public ?int $selectedId = null;
    public string $key = '';
    public string $channel = 'whatsapp';
    public string $subject = '';
    public string $body = '';

    // Test notification dispatch state
    public string $testChannel = 'whatsapp';
    public string $testRecipient = '+91 98765 43210';
    public string $testTemplateKey = '';

    // Log filter
    public string $logChannelFilter = 'all';

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
            'channel' => 'required|in:sms,whatsapp,email,crm,push,database',
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

    public function sendTestNotification(): void
    {
        $this->validate([
            'testChannel' => 'required|in:sms,whatsapp,email,crm,push',
            'testRecipient' => 'required|string',
        ]);

        try {
            $key = !empty($this->testTemplateKey) ? $this->testTemplateKey : 'lead_assigned_' . $this->testChannel;

            $log = app(NotificationDeliveryService::class)->send(
                channel: $this->testChannel,
                recipient: $this->testRecipient,
                templateKey: $key,
                tokens: [
                    'lead_name' => 'Alexander Pierce',
                    'lead_code' => 'LP-' . rand(1000, 9999),
                    'assigned_to' => auth()->user()?->name ?? 'Lead Panther Agent',
                ]
            );

            $this->dispatch('toast', type: 'success', message: "Test notification dispatched via " . strtoupper($this->testChannel) . " (Status: " . strtoupper($log->status) . ")!");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Failed to send test notification: ' . $e->getMessage());
        }
    }

    public function retryNotification(int $logId): void
    {
        try {
            $log = app(NotificationDeliveryService::class)->retry($logId);
            $this->dispatch('toast', type: 'success', message: "Notification #{$log->id} re-delivered successfully (Status: {$log->status}, Retries: {$log->retries}).");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Retry failed: ' . $e->getMessage());
        }
    }

    public function filterLogsByChannel(string $channel): void
    {
        $this->logChannelFilter = $channel;
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

        // Recent Notification Delivery Logs
        $logsQuery = NotificationLog::latest('id');
        if ($this->logChannelFilter !== 'all') {
            $logsQuery->where('channel', $this->logChannelFilter);
        }
        $logs = $logsQuery->take(15)->get();

        return view('livewire.admin.notification-templates', [
            'templates' => $templates,
            'logs' => $logs,
        ])->layout('layouts.app');
    }
}
