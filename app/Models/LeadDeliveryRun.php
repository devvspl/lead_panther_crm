<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDeliveryRun extends Model
{
    protected $guarded = [];
    protected $casts = ['settings' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    public function plan() { return $this->belongsTo(LeadDeliveryPlan::class, 'plan_id'); }
    public function items() { return $this->hasMany(LeadDeliveryItem::class, 'run_id'); }
}
