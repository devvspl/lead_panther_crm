<?php
namespace App\Models;
class InternalLeadProcessing extends \Illuminate\Database\Eloquent\Model
{
    use \App\Traits\HidesArchivedLeads;

    protected $table = 'internal_lead_processing'; protected $guarded = [];
    protected $casts = ['last_contacted_at' => 'datetime', 'next_followup_at' => 'datetime', 'verified_at' => 'datetime', 'handover_requested_at' => 'datetime', 'forwarded_at' => 'datetime'];
    public function lead() { return $this->belongsTo(Lead::class)->withoutGlobalScopes()->with(['campaign' => fn ($q) => $q->withoutGlobalScopes()->with(['project' => fn ($p) => $p->withoutGlobalScopes()]), 'leadSource']); }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function status() { return $this->belongsTo(InternalProcessingStatus::class, 'status_code', 'code'); }
    public function events() { return $this->hasMany(InternalLeadProcessingEvent::class); }
}
