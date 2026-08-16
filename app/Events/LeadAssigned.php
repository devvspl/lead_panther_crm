<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\User;
use App\Models\LeadAssignment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;
    public User $user;
    public ?LeadAssignment $assignment;

    public function __construct(Lead $lead, User $user, ?LeadAssignment $assignment = null)
    {
        $this->lead = $lead;
        $this->user = $user;
        $this->assignment = $assignment;
    }
}
