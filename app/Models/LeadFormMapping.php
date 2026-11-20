<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFormMapping extends Model
{
    protected $guarded = [];
    protected $casts = ['meta_created_at' => 'datetime'];

    public static function parseMetaCreatedTime($value): ?\Carbon\Carbon
    {
        if (!is_string($value) || trim($value) === '') return null;
        try { return \Carbon\Carbon::parse($value)->setTimezone(config('app.timezone')); }
        catch (\Throwable) { return null; }
    }

    public function portalAccount(): BelongsTo
    {
        return $this->belongsTo(PortalAccount::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
