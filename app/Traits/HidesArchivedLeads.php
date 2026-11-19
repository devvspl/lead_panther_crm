<?php

namespace App\Traits;

trait HidesArchivedLeads
{
    public static function bootHidesArchivedLeads(): void
    {
        if (static::class === \App\Models\Lead::class) {
            static::saving(function ($lead) {
                if ($lead->isDirty(['archived_at','archived_by','archive_reason'])) \App\Services\LeadArchiveService::authorize();
            });
            static::deleting(fn ($lead) => \App\Services\LeadArchiveService::assertPermanentDeletionAllowed($lead));
        }
    }

    public function newEloquentBuilder($query)
    {
        return new \App\Models\Builders\ArchiveAwareBuilder($query);
    }
}
