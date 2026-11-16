<?php
namespace App\Models;
class SeederRun extends \Illuminate\Database\Eloquent\Model {
    protected $guarded=[];
    protected $casts=['started_at'=>'datetime','completed_at'=>'datetime','reverted_at'=>'datetime'];
    public function changes() { return $this->hasMany(SeederRunChange::class); }
    public function user() { return $this->belongsTo(User::class); }
}
