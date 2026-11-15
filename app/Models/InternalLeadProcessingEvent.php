<?php
namespace App\Models;
class InternalLeadProcessingEvent extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false; protected $guarded = []; protected $casts = ['details' => 'array', 'created_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
}
