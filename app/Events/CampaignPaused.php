<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\Client;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignPaused
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;
    public mixed $client;
    public string $reason;

    public function __construct(Lead $lead, string $reason = 'Credit balance exhausted')
    {
        $this->lead = $lead;
        $this->client = $lead->client;
        $this->reason = $reason;
    }
}
