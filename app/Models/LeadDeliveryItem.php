<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDeliveryItem extends Model
{
    protected $guarded = [];
    protected $casts = ['sent_at' => 'datetime', 'accepted_at' => 'datetime'];
    public function lead() { return $this->belongsTo(Lead::class)->withoutGlobalScopes(); }
    public function client() { return $this->belongsTo(User::class, 'client_id'); }
    public function project() { return $this->belongsTo(Project::class)->withoutGlobalScopes(); }
    public function campaign() { return $this->belongsTo(Campaign::class)->withoutGlobalScopes(); }
    public function plan() { return $this->belongsTo(LeadDeliveryPlan::class, 'plan_id'); }
    public function run() { return $this->belongsTo(LeadDeliveryRun::class, 'run_id'); }
    public function attemptHistory() { return $this->hasMany(LeadDeliveryAttempt::class, 'item_id'); }
}
