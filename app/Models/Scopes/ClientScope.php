<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ClientScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();

        // Super Admin bypasses multi-tenant scoping
        if ($user->hasRole('Super Admin')) {
            return;
        }

        $clientId = $user->organization_id ?? $user->client_id ?? null;

        if ($clientId) {
            if ($model->getTable() === 'campaigns') {
                $builder->whereHas('project', function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                });
            } else {
                $builder->where($model->getTable() . '.client_id', $clientId);
            }
        }
    }
}
