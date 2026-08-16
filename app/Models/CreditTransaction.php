<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Events\CreditReserved;
use App\Events\CampaignPaused;
use App\Traits\ClientScoped;

class CreditTransaction extends Model
{
    use ClientScoped;

    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function package()
    {
        return $this->belongsTo(CreditPackage::class);
    }

    /**
     * Reserve credits for a newly created lead with a pessimistic row lock.
     */
    public static function reserveForLead(Lead $lead): bool
    {
        return DB::transaction(function () use ($lead) {
            $wallet = CreditWallet::where('client_id', $lead->client_id)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = CreditWallet::create([
                    'client_id' => $lead->client_id,
                    'balance' => 0.00,
                ]);
            }

            $cost = 10.00;

            if ($wallet->balance > 0) {
                $before = (float) $wallet->balance;
                $after = max(0.00, $before - $cost);

                $wallet->update(['balance' => $after]);

                $tx = static::create([
                    'client_id' => $lead->client_id,
                    'lead_id' => $lead->id,
                    'package_id' => null,
                    'credit_before' => $before,
                    'credit_used' => $cost,
                    'credit_after' => $after,
                    'transaction_type' => 'reserve',
                    'created_at' => now(),
                ]);

                event(new CreditReserved($lead, $tx));
                return true;
            } else {
                $lead->update([
                    'current_stage' => 'pending_credit',
                    'status' => 'pending_credit',
                ]);

                event(new CampaignPaused($lead, 'Credit balance exhausted'));

                NotificationLog::create([
                    'notifiable_type' => Client::class,
                    'notifiable_id' => $lead->client_id,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Recharge required',
                        'message' => 'Credit balance exhausted. Lead ' . $lead->lead_code . ' placed on pending_credit hold.',
                    ]),
                    'sent_at' => now(),
                ]);

                return false;
            }
        });
    }
}
