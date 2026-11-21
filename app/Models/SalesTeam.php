<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesTeam extends Model
{
    use \App\Traits\HasLeadCreditCost;
    protected $guarded = [];
    public function leader() { return $this->belongsTo(User::class, 'leader_id'); }

    public function members(): HasMany
    {
        return $this->hasMany(SalesTeamMember::class);
    }
}
