<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignPaused
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;
    public string $reason;

    public function __construct(Lead $lead, string $reason = 'Credit balance exhausted')
    {
        $this->lead = $lead;
        $this->reason = $reason;
    }
}
