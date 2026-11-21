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
        $wallets = CreditWallet::with('client.organization')->whereNotNull('reconciled_at')->get();
        $countExhausted = 0;
        $countLow = 0;

        foreach ($wallets as $wallet) {
            $balance = (int) $wallet->balance;
            $threshold = max(0, (int) ($wallet->client?->organization?->min_credit_threshold ?? 50));

            if ($balance <= 0.00) {
                NotificationLog::create([
                    'notifiable_type' => Client::class,
                    'notifiable_id' => $wallet->client_id,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Credits Exhausted',
                        'message' => 'Your credit balance is 0. Client lead delivery is paused until lead credits are available.',
                        'type' => 'exhausted',
                    ]),
                    'sent_at' => now(),
                ]);
                $countExhausted++;
            } elseif ($balance < $threshold) {
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
