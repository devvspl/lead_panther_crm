<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\NotificationTemplate;

use Livewire\WithPagination;

class NotificationTemplates extends Component
{
    use WithPagination;

    public ?int $selectedId = null;
    public string $key = '';
    public string $channel = 'whatsapp';
    public string $subject = '';
    public string $body = '';

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
        $templates = NotificationTemplate::latest('id')->paginate(15);

        return view('livewire.admin.notification-templates', [
            'templates' => $templates,
        ])->layout('layouts.app');
    }
}
