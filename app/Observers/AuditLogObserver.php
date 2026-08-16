<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLog;

class AuditLogObserver
{
    public function created(Model $model): void
    {
        $this->logAction($model, 'created', null, json_encode($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        if (empty($dirty)) return;

        $original = array_intersect_key($model->getOriginal(), $dirty);

        $this->logAction(
            $model,
            'updated',
            json_encode($original),
            json_encode($dirty)
        );
    }

    public function deleted(Model $model): void
    {
        $this->logAction($model, 'deleted', json_encode($model->getAttributes()), null);
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
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
