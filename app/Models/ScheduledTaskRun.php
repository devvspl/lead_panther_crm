<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScheduledTaskRun extends Model {
    protected $guarded=[];
    protected $casts=['started_at'=>'datetime','finished_at'=>'datetime','duration_ms'=>'integer','exit_code'=>'integer'];
    public function requester() { return $this->belongsTo(User::class,'requested_by'); }
}
