<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lead;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;

class CreditTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $leads = Lead::all();

        foreach ($leads as $lead) {
            $wallet = CreditWallet::firstOrCreate(
                ['client_id' => $lead->client_id],
                ['balance' => 10000.00]
            );

            $creditCost = 10.00; // 10 credits per lead
            $balanceBefore = $wallet->balance;
            $balanceAfter = max(0, $balanceBefore - $creditCost);

            CreditTransaction::create([
                'client_id' => $lead->client_id,
                'lead_id' => $lead->id,
                'package_id' => null,
                'credit_before' => $balanceBefore,
                'credit_used' => $creditCost,
                'credit_after' => $balanceAfter,
                'transaction_type' => 'deduct',
                'created_at' => $lead->created_at,
            ]);

            // Update wallet balance to reflect transaction
            $wallet->update(['balance' => $balanceAfter]);
        }
    }
}
