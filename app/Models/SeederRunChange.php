<?php
namespace App\Models;
class SeederRunChange extends \Illuminate\Database\Eloquent\Model {
    public $timestamps=false;
    protected $guarded=[];
    protected $hidden=['before_values','after_values','record_key'];
    protected $casts=['before_values'=>'encrypted:array','after_values'=>'encrypted:array','record_key'=>'encrypted:array'];
}
