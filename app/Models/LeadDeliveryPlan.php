<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDeliveryPlan extends Model
{
    protected $guarded = [];
    protected $casts = ['settings' => 'array', 'next_run_at' => 'datetime'];
    public function items() { return $this->hasMany(LeadDeliveryItem::class, 'plan_id'); }
    public function runs() { return $this->hasMany(LeadDeliveryRun::class, 'plan_id'); }
}
