<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Followup;
use App\Models\User;
use App\Notifications\FollowupDueNotification;

class CheckDueFollowupsCommand extends Command
{
    protected $signature = 'followups:check-due';
    protected $description = 'Check and send notifications for due follow-ups';

    public function handle(): int
    {
        $dueFollowups = Followup::where('due_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'pending');
            })
            ->get();

        $count = 0;
        foreach ($dueFollowups as $followup) {
            $user = User::find($followup->created_by) ?? User::find($followup->lead?->assigned_to) ?? User::first();
            if ($user) {
                $user->notify(new FollowupDueNotification($followup));
                $count++;
            }
        }

        $this->info("Follow-up check completed: notified for {$count} due follow-ups.");
        return Command::SUCCESS;
    }
}
