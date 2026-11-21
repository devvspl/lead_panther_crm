<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaAdRecord extends Model
{
    protected $guarded = [];
    protected $casts = ['data' => 'array', 'synced_at' => 'datetime', 'report_date' => 'date'];
    public function account() { return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id'); }
    public function crmCampaign() { return $this->belongsTo(Campaign::class, 'crm_campaign_id'); }
}
