<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLog;

class AuditLogObserver
{
    protected array $hiddenFields = [
        'password',
        'remember_token',
        'access_token',
        'app_secret',
        'secret',
        'api_key',
        'token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function created(Model $model): void
    {
        $attributes = $this->maskSensitive($model->getAttributes());
        $this->logAction($model, 'created', null, json_encode($attributes));
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        if (empty($dirty)) return;

        $original = array_intersect_key($model->getOriginal(), $dirty);
        if (\App\Support\AuditRedactor::sensitive((string) $model->getAttribute('key'))) {
            if (array_key_exists('value', $dirty)) $dirty['value'] = '********';
            if (array_key_exists('value', $original)) $original['value'] = '********';
        }

        $this->logAction(
            $model,
            'updated',
            json_encode($this->maskSensitive($original)),
            json_encode($this->maskSensitive($dirty))
        );
    }

    public function deleted(Model $model): void
    {
        $attributes = $this->maskSensitive($model->getAttributes());
        $this->logAction($model, 'deleted', json_encode($attributes), null);
    }

    protected function maskSensitive(array $attributes): array
    {
        return \App\Support\AuditRedactor::clean($attributes);
    }

    protected function logAction(Model $model, string $event, ?string $fromValue, ?string $toValue): void
    {
        $modelName = class_basename($model);
        $notification = $model instanceof \Illuminate\Notifications\DatabaseNotification;
        $action = $notification ? 'notification.'.($event === 'updated' && $model->wasChanged('read_at') ? 'read' : $event) : strtolower($modelName) . '.' . $event;
        $orgId = $model->getAttribute('organization_id');
        if (! $orgId && $model instanceof \App\Models\Organization) $orgId = $model->id;
        if (! $orgId && $model->getAttribute('client_id')) $orgId = \App\Models\User::find($model->client_id)?->organization_id;
        if (! $orgId && $model->getAttribute('lead_id')) $orgId = \App\Models\Lead::withoutGlobalScopes()->find($model->lead_id)?->client?->organization_id;
        if (! $orgId && $model instanceof \App\Models\Campaign) $orgId = \App\Models\Project::withoutGlobalScopes()->find($model->project_id)?->client?->organization_id;

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => get_class($model),
            'subject_id' => is_numeric($model->getKey()) ? $model->getKey() : null,
            'organization_id' => $orgId,
            'context' => ['record_id' => $model->getKey(), 'notification_id' => $notification ? $model->getKey() : null, 'category' => $notification ? 'notification' : 'model', 'status' => 'success',
                'lead_id' => $model instanceof \App\Models\Lead ? $model->id : $model->getAttribute('lead_id'),
                'project_id' => $model instanceof \App\Models\Project ? $model->id : $model->getAttribute('project_id'),
                'campaign_id' => $model instanceof \App\Models\Campaign ? $model->id : $model->getAttribute('campaign_id')],
            'from_value' => $fromValue,
            'to_value' => $toValue,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
