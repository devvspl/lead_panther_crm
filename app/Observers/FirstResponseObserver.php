<?php

namespace App\Observers;

use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadMessage;
use App\Models\LeadCommunication;
use App\Models\LeadStatusHistory;
use App\Events\FirstResponseRecorded;
use Illuminate\Database\Eloquent\Model;

class FirstResponseObserver
{
    /**
     * Handle created/updated events on activity models or stage changes on Lead model.
     */
    public function created(Model $model): void
    {
        $this->evaluateResponse($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->evaluateResponse($model, 'updated');
    }

    protected function evaluateResponse(Model $model, string $event): void
    {
        $leadId = null;
        $responseType = 'activity';

        if ($model instanceof LeadCall) {
            $leadId = $model->lead_id;
            $responseType = 'call';
        } elseif ($model instanceof LeadMessage) {
            if ($model->direction && strtolower($model->direction) !== 'outbound') {
                return; // Only outbound messages qualify as executive response
            }
            $leadId = $model->lead_id;
            $responseType = 'message';
        } elseif ($model instanceof LeadCommunication) {
            if ($model->direction && strtolower($model->direction) !== 'outbound') {
                return; // Only outbound communications qualify
            }
            $leadId = $model->lead_id;
            $responseType = 'communication';
        } elseif ($model instanceof Lead) {
            $leadId = $model->id;
            $currentStage = strtolower($model->current_stage ?? '');
            if (in_array($currentStage, ['new', 'assigned', 'pending_credit'])) {
                return;
            }
            $responseType = 'stage_change';
        }

        if (!$leadId) {
            return;
        }

        $now = now()->toDateTimeString();

        // Atomic DB-level guard prevents race conditions across near-simultaneous activities
        $affectedRows = Lead::where('id', $leadId)
            ->whereNull('first_response_at')
            ->update(['first_response_at' => $now]);

        if ($affectedRows > 0) {
            $lead = Lead::find($leadId);
            if ($lead) {
                event(new FirstResponseRecorded($lead, $now, $responseType));

                if ($model instanceof Lead && $model->isDirty('current_stage')) {
                    LeadStatusHistory::create([
                        'lead_id' => $lead->id,
                        'from_status' => $lead->getOriginal('current_stage') ?? 'assigned',
                        'to_status' => $lead->current_stage,
                        'changed_by' => auth()->id() ?? $lead->assigned_to ?? 1,
                        'changed_at' => now(),
                    ]);
                }
            }
        }
    }
}
