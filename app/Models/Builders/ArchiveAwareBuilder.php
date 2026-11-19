<?php

namespace App\Models\Builders;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

/** Tenant-scope bypasses must never implicitly reveal archived leads. */
class ArchiveAwareBuilder extends Builder
{
    protected bool $includeArchived = false;

    public function withArchived(): static
    {
        \App\Services\LeadArchiveService::authorize();
        $this->includeArchived = true;
        return $this;
    }

    public function onlyArchived(): static
    {
        return $this->withArchived()->whereNotNull('leads.archived_at');
    }

    public function applyScopes()
    {
        if ($this->includeArchived) \App\Services\LeadArchiveService::authorize();
        $builder = parent::applyScopes();
        // Always clone: repeated counts, pagination and relationship queries must stay independent.
        $builder = clone $builder;
        if (!$this->includeArchived) {
            $builder->callScope(function ($builder) {
                if ($this->model instanceof Lead) {
                    $builder->whereNull($this->model->qualifyColumn('archived_at'));
                } elseif ($this->model->getTable() === 'payments') {
                    $builder->whereIn('payments.booking_id', \App\Models\Booking::withoutGlobalScopes()->select('id'));
                } else {
                    $column = $this->model->qualifyColumn('lead_id');
                    $builder->whereExists(fn ($q) => $q->selectRaw('1')->from('leads as archive_visible_lead')
                        ->whereColumn('archive_visible_lead.id', $column)->whereNull('archive_visible_lead.archived_at'));
                }
            });
        }
        return $builder;
    }

    public function update(array $values)
    {
        if ($this->model instanceof Lead && array_intersect(array_keys($values), ['archived_at','archived_by','archive_reason'])) {
            \App\Services\LeadArchiveService::authorize();
        }
        return parent::update($values);
    }

    public function delete()
    {
        if ($this->model instanceof Lead) {
            \App\Services\LeadArchiveService::authorize();
            foreach ((clone $this)->get() as $lead) \App\Services\LeadArchiveService::assertPermanentDeletionAllowed($lead);
        }
        return parent::delete();
    }
}
