<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CreditWallet;
use App\Models\NotificationLog;
use App\Models\Client;

class CheckLowCreditBalances extends Command
{
    protected $signature = 'credits:check-low-balances';
    protected $description = 'Inspect client credit wallets and create low/zero balance notification logs';

    public function handle(): int
    {
        $wallets = CreditWallet::with('client')->get();
        $countExhausted = 0;
        $countLow = 0;

        foreach ($wallets as $wallet) {
            $balance = (float) $wallet->balance;

            if ($balance <= 0.00) {
                NotificationLog::create([
                    'notifiable_type' => Client::class,
                    'notifiable_id' => $wallet->client_id,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Credits Exhausted',
                        'message' => 'Your credit balance is 0. Lead ingestion and distribution are paused.',
                        'type' => 'exhausted',
                    ]),
                    'sent_at' => now(),
                ]);
                $countExhausted++;
            } elseif ($balance < 100.00) {
                NotificationLog::create([
                    'notifiable_type' => Client::class,
                    'notifiable_id' => $wallet->client_id,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Credits Low',
                        'message' => "Your credit balance is low ({$balance} credits remaining). Please recharge soon.",
                        'type' => 'low',
                    ]),
                    'sent_at' => now(),
                ]);
                $countLow++;
            }
        }

        $this->info("Low balance check complete: {$countExhausted} exhausted, {$countLow} low balance notifications logged.");
        return Command::SUCCESS;
    }
}
