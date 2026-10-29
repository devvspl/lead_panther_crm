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

        $clientId = $user->id;
        $orgId = $user->organization_id ?? null;

        if ($model->getTable() === 'campaigns') {
            $builder->whereHas('project', function ($q) use ($clientId, $orgId) {
                $q->where(function ($sub) use ($clientId, $orgId) {
                    $sub->where('client_id', $clientId);
                    if ($orgId) {
                        $sub->orWhere('client_id', $orgId);
                    }
                });
            });
        } else {
            $builder->where(function ($q) use ($model, $clientId, $orgId) {
                $q->where($model->getTable() . '.client_id', $clientId);
                if ($orgId) {
                    $q->orWhere($model->getTable() . '.client_id', $orgId);
                }
            });
        }
    }
}
