<?php

namespace App\Observers;

use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadMessage;
use App\Models\LeadCommunication;
use App\Models\SiteVisit;
use Illuminate\Database\Eloquent\Model;

class LeadScoreObserver
{
    public function created(Model $model): void
    {
        $this->recalculate($model);
    }

    public function updated(Model $model): void
    {
        $this->recalculate($model);
    }

    protected function recalculate(Model $model): void
    {
        $leadId = null;

        if ($model instanceof Lead) {
            $leadId = $model->id;
        } elseif ($model instanceof LeadCall || $model instanceof LeadMessage || $model instanceof LeadCommunication || $model instanceof SiteVisit) {
            $leadId = $model->lead_id;
        }

        if (!$leadId) {
            return;
        }

        $lead = Lead::find($leadId);
        if ($lead) {
            $newScore = $lead->calculateScore();
            if ($lead->lead_score !== $newScore) {
                Lead::where('id', $lead->id)->update(['lead_score' => $newScore]);
            }
        }
    }
}
