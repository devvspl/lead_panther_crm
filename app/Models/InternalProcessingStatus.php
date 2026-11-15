<?php
namespace App\Models;
class InternalProcessingStatus extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'internal_processing_statuses'; protected $primaryKey = 'code'; public $incrementing = false; protected $keyType = 'string'; public $timestamps = false;
    protected $guarded = []; protected $casts = ['is_system' => 'boolean', 'terminal' => 'boolean', 'can_forward' => 'boolean'];
}
