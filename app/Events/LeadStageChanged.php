<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadStageChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;
    public string $fromStage;
    public string $toStage;
    public int $changedByUserId;

    public function __construct(Lead $lead, string $fromStage, string $toStage, int $changedByUserId)
    {
        $this->lead = $lead;
        $this->fromStage = $fromStage;
        $this->toStage = $toStage;
        $this->changedByUserId = $changedByUserId;
    }
}
