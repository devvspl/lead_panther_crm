<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadMetadata extends Model
{
    public $timestamps = false;
    protected $table = 'lead_metadata';
    protected $guarded = [];
}
