<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\CreditTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditReserved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;
    public CreditTransaction $transaction;

    public function __construct(Lead $lead, CreditTransaction $transaction)
    {
        $this->lead = $lead;
        $this->transaction = $transaction;
    }
}
