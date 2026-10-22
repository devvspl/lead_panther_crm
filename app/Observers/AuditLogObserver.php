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
        foreach ($this->hiddenFields as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = '********';
            }
        }
        return $attributes;
    }

    protected function logAction(Model $model, string $event, ?string $fromValue, ?string $toValue): void
    {
        $modelName = class_basename($model);
        $action = strtolower($modelName) . '.' . $event;

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => get_class($model),
            'subject_id' => $model->getKey(),
            'from_value' => $fromValue,
            'to_value' => $toValue,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
