<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDeliveryPlan extends Model
{
    protected $guarded = [];
    protected $casts = ['settings' => 'array', 'next_run_at' => 'datetime'];
    public function items() { return $this->hasMany(LeadDeliveryItem::class, 'plan_id'); }
    public function runs() { return $this->hasMany(LeadDeliveryRun::class, 'plan_id'); }

    public function getFrequencyLabelAttribute(): string
    {
        $freq = $this->settings['frequency'] ?? '';
        return match ($freq) {
            'random_1_30' => 'Random (1–30 Minutes)',
            '15_minutes' => 'Every 15 minutes',
            '30_minutes' => 'Every 30 minutes',
            'immediate' => 'Immediately',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'manual' => 'Manual Only',
            default => ucwords(str_replace('_', ' ', (string) $freq))
        };
    }
}
