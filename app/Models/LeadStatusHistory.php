<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStatusHistory extends Model
{
    protected $table = 'lead_status_history';
    protected $guarded = [];

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
