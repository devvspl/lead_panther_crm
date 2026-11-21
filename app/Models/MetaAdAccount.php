<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaAdAccount extends Model
{
    protected $guarded = [];
    protected $hidden = ['access_token', 'sync_state'];
    protected $casts = ['access_token' => 'encrypted', 'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime', 'retry_after' => 'datetime', 'sync_state' => 'array'];

    public function integration() { return $this->belongsTo(PortalAccount::class, 'portal_account_id'); }
    public function pages() { return $this->belongsToMany(PortalAccount::class, 'meta_ad_account_pages'); }
    public function records() { return $this->hasMany(MetaAdRecord::class); }
}
