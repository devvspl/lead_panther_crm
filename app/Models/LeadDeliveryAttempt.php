<?php
namespace App\Models;
class LeadDeliveryAttempt extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = [];
    public function run() { return $this->belongsTo(LeadDeliveryRun::class, 'run_id'); }
}
