<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditDispute extends Model
{
    protected $guarded = [];
    protected $casts = ['resolved_at' => 'datetime'];
    public function transaction() { return $this->belongsTo(CreditTransaction::class, 'credit_transaction_id'); }
}
