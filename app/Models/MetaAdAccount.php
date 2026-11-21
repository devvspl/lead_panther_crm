<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAdAccount extends Model
{
    protected $guarded = [];
    protected $hidden = ['access_token', 'sync_state'];
    protected $casts = [
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'retry_after' => 'datetime',
        'sync_state' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(PortalAccount::class, 'portal_account_id');
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(PortalAccount::class, 'meta_ad_account_pages');
    }

    public function records(): HasMany
    {
        return $this->hasMany(MetaAdRecord::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaAdRecord::class)->where('kind', 'campaign');
    }

    public function adsets(): HasMany
    {
        return $this->hasMany(MetaAdRecord::class)->where('kind', 'adset');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(MetaAdRecord::class)->where('kind', 'ad');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaAdRecord::class)->where('kind', 'insight');
    }

    public function getSyncStageLabelAttribute(): string
    {
        if ($this->status === 'connected' && !empty($this->sync_state)) {
            $edge = (int) ($this->sync_state['edge'] ?? 0);
            if (isset($this->sync_state['pages'])) {
                return 'Syncing Pages...';
            }
            return match ($edge) {
                0 => 'Syncing Campaigns...',
                1 => 'Syncing Ad Sets...',
                2 => 'Syncing Ads...',
                3 => 'Syncing Insights...',
                default => 'Sync in progress...',
            };
        }

        if ($this->status === 'connected') {
            return 'Connected';
        }

        return ucfirst(str_replace('_', ' ', $this->status ?: 'untested'));
    }

    public function getSyncCountsAttribute(): array
    {
        $counts = $this->records()
            ->selectRaw('kind, COUNT(*) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind')
            ->all();

        return [
            'campaigns' => (int) ($counts['campaign'] ?? 0),
            'adsets' => (int) ($counts['adset'] ?? 0),
            'ads' => (int) ($counts['ad'] ?? 0),
            'insights' => (int) ($counts['insight'] ?? 0),
            'pages' => $this->pages()->count(),
        ];
    }
}
