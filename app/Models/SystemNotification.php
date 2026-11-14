<?php
namespace App\Models;

class SystemNotification extends \Illuminate\Notifications\DatabaseNotification
{
    protected $table = 'notifications';
    public function recipient() { return $this->belongsTo(User::class, 'notifiable_id'); }
    public function organization() { return $this->belongsTo(Organization::class); }
}
