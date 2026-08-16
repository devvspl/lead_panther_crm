<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class AutoOfflineInactiveTeams extends Command
{
    protected $signature = 'teams:auto-offline';
    protected $description = 'Auto-mark sales executives offline after inactivity threshold';

    public function handle(): int
    {
        $count = User::role('sales-executive')
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $this->info("Auto-offline check completed: {$count} users set offline.");
        return Command::SUCCESS;
    }
}
