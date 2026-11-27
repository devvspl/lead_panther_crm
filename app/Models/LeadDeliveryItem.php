<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDeliveryItem extends Model
{
    protected $guarded = [];
    protected $casts = [
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'rate_per_lead' => 'decimal:2',
        'billing_amount' => 'decimal:2',
    ];

    public function isDelivered(): bool { return in_array($this->status, ['sent', 'accepted']); }
    public function isRevoked(): bool { return $this->status === 'revoked'; }
    public function isExcluded(): bool { return $this->status === 'do_not_send'; }
    public function isPending(): bool { return in_array($this->status, ['pending', 'queued']); }
    public function lead() { return $this->belongsTo(Lead::class)->withoutGlobalScopes(); }
    public function client() { return $this->belongsTo(User::class, 'client_id'); }
    public function project() { return $this->belongsTo(Project::class)->withoutGlobalScopes(); }
    public function campaign() { return $this->belongsTo(Campaign::class)->withoutGlobalScopes(); }
    public function plan() { return $this->belongsTo(LeadDeliveryPlan::class, 'plan_id'); }
    public function run() { return $this->belongsTo(LeadDeliveryRun::class, 'run_id'); }
    public function attemptHistory() { return $this->hasMany(LeadDeliveryAttempt::class, 'item_id'); }
    public function revocations() { return $this->hasMany(LeadDeliveryRevocation::class, 'delivery_item_id'); }
}
