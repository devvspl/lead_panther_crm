<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CreditPackage;
use App\Models\Client;
use App\Models\CreditWallet;

class CreditPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => 'Starter Pack', 'credit_count' => 100, 'price' => 5000.00, 'validity_days' => 30],
            ['name' => 'Growth Pack', 'credit_count' => 500, 'price' => 22000.00, 'validity_days' => 60],
            ['name' => 'Pro Pack', 'credit_count' => 1200, 'price' => 50000.00, 'validity_days' => 90],
            ['name' => 'Enterprise Pack', 'credit_count' => 3000, 'price' => 110000.00, 'validity_days' => 120],
        ];

        foreach ($packages as $pkg) {
            CreditPackage::firstOrCreate(['name' => $pkg['name']], $pkg);
        }

        $clients = Client::all();
        foreach ($clients as $client) {
            CreditWallet::firstOrCreate(
                ['client_id' => $client->id],
                ['balance' => 5000.00]
            );
        }
    }
}
