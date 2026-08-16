<?php

namespace App\Livewire\Activity;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\User;

class MeetingForm extends Component
{
    public Lead $lead;

    public string $scheduledAt = '';
    public string $location = 'Client Office / Virtual';
    public string $meetingType = 'in_person';
    public string $notes = '';
    public array $participantIds = [];

    public function mount(Lead $lead): void
    {
        $this->lead = $lead;
        $this->scheduledAt = now()->addDay()->setHour(11)->setMinute(0)->format('Y-m-d\TH:i');
    }

    public function scheduleMeeting(): void
    {
        $this->validate([
            'scheduledAt' => 'required',
            'location' => 'required|string',
        ]);

        Meeting::create([
            'lead_id' => $this->lead->id,
            'scheduled_at' => $this->scheduledAt,
            'location' => $this->location,
            'type' => $this->meetingType,
            'notes' => $this->notes,
            'participants' => $this->participantIds,
            'outcome' => 'scheduled',
        ]);

        $this->lead->update(['current_stage' => 'Meeting', 'status' => 'meeting']);

        $this->dispatch('toast', type: 'success', message: 'Meeting scheduled successfully and stage updated to Meeting.');
    }

    public function render()
    {
        $executives = User::all();
        $leadMeetings = Meeting::where('lead_id', $this->lead->id)->latest('scheduled_at')->get();

        return view('livewire.activity.meeting-form', [
            'executives' => $executives,
            'leadMeetings' => $leadMeetings,
        ]);
    }
}
