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
        if ($model instanceof \App\Models\Lead && \App\Support\TeamAccess::isLeader($user)) {
            \App\Support\TeamAccess::scopeLeads($builder, $user);
            return;
        }
        if (\App\Support\TeamAccess::isLeader($user) && $model instanceof \App\Models\Campaign) {
            $builder->whereIn('campaigns.id', \App\Models\Lead::panelQuery()->select('campaign_id'));
            return;
        }
        if (\App\Support\TeamAccess::isLeader($user) && $model instanceof \App\Models\Project) {
            $builder->whereIn('projects.id', \App\Models\Campaign::withoutGlobalScopes()->whereIn('id', \App\Models\Lead::panelQuery()->select('campaign_id'))->select('project_id'));
            return;
        }
        if ($model instanceof \App\Models\Lead && ! $user->hasAnyRole(['Super Admin', 'super-admin'])) $builder->where('leads.internal_processing_pending', false);
        if ($model instanceof \App\Models\Lead && \App\Models\Lead::usesSalesPanel()) {
            $builder->where('leads.delivery_pending', false);
        }

        // Super Admin bypasses multi-tenant scoping
        if ($user->hasRole(['Super Admin', 'super-admin'])) {
            return;
        }

        $clientId = $user->id;
        $orgId = $user->organization_id ?? null;

        if ($model->getTable() === 'campaigns') {
            $builder->whereHas('project', function ($q) use ($clientId, $orgId) {
                $q->where(function ($sub) use ($clientId, $orgId) {
                    $sub->where('client_id', $clientId);
                    if ($orgId) {
                        $sub->orWhere('client_id', $orgId)
                            ->orWhereIn('client_id', \App\Models\User::where('organization_id', $orgId)->select('id'))
                            ->orWhereHas('client', fn ($c) => $c->where('organization_id', $orgId));
                    }
                });
            });
        } else {
            $builder->where(function ($q) use ($model, $clientId, $orgId) {
                $q->where($model->getTable() . '.client_id', $clientId);
                if ($orgId) {
                    $q->orWhere($model->getTable() . '.client_id', $orgId)
                        ->orWhereIn($model->getTable() . '.client_id', \App\Models\User::where('organization_id', $orgId)->select('id'));
                    if (method_exists($model, 'client')) {
                        $q->orWhereHas('client', fn ($c) => $c->where('organization_id', $orgId));
                    }
                }
            });
        }
    }
}
