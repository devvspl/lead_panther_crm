<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\ClientUser;
use App\Models\Client;
use App\Models\SalesTeamMember;
use App\Models\SalesTeam;

class VerifyUserAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'account:verify {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify user access, organization status, assigned roles, and required pivot table links';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Account Verification Result for {$email}:");
            $this->error("FAIL - User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        $this->info("==========================================");
        $this->info("ACCOUNT ACCESS DIAGNOSTIC REPORT");
        $this->info("==========================================");
        $this->line("User ID:           {$user->id}");
        $this->line("User Name:         {$user->name}");
        $this->line("User Email:        {$user->email}");
        $this->line("User Status:       {$user->status} " . ($user->is_active ? "[ACTIVE]" : "[INACTIVE]"));

        // 1. Organization Check
        $org = $user->organization;
        $orgStatusStr = $org ? "{$org->name} (ID: {$org->id}) - Status: {$org->status} " . ($org->is_active ? "[ACTIVE]" : "[INACTIVE]") : "NONE";
        $this->line("Organization:      {$orgStatusStr}");

        // 2. Roles Check
        $roles = $user->roles->pluck('name')->toArray();
        $rolesStr = !empty($roles) ? implode(', ', $roles) : "NONE";
        $this->line("Assigned Roles:    {$rolesStr}");

        // 3. Client Pivot Check
        $isClientUser = $user->hasRole('client') || $user->hasRole('Client');
        $clientUserLink = ClientUser::where('user_id', $user->id)->first();
        $clientStr = "N/A (Not Client Role)";

        if ($isClientUser) {
            if ($clientUserLink) {
                $client = Client::find($clientUserLink->client_id);
                $clientName = $client ? $client->name : 'Unknown';
                $primaryStr = $clientUserLink->is_primary_contact ? ' (Primary Contact)' : '';
                $clientStr = "Linked to Client ID {$clientUserLink->client_id} ({$clientName}){$primaryStr}";
            } else {
                $clientStr = "MISSING PIVOT LINK";
            }
        }
        $this->line("Client Link:       {$clientStr}");

        // 4. Sales Team Check
        $isSalesExec = $user->hasRole('sales-executive') || $user->hasRole('Sales Executive');
        $salesMemberLink = SalesTeamMember::where('user_id', $user->id)->first();
        $salesTeamStr = "N/A (Not Sales Exec Role)";

        if ($isSalesExec) {
            if ($salesMemberLink) {
                $salesTeam = SalesTeam::find($salesMemberLink->sales_team_id);
                $teamName = $salesTeam ? $salesTeam->name : 'Unknown';
                $salesTeamStr = "Linked to Sales Team ID {$salesMemberLink->sales_team_id} ({$teamName}) - Member Role: {$salesMemberLink->role}";
            } else {
                $salesTeamStr = "MISSING SALES TEAM LINK";
            }
        }
        $this->line("Sales Team Link:   {$salesTeamStr}");
        $this->info("------------------------------------------");

        // Verification Rules
        $failures = [];

        if (!$user->is_active) {
            $failures[] = "User status is inactive ({$user->status})";
        }

        if (!$org) {
            $failures[] = "User has no organization assigned";
        } elseif (!$org->is_active) {
            $failures[] = "User organization '{$org->name}' is inactive ({$org->status})";
        }

        if (empty($roles)) {
            $failures[] = "User has no Spatie permissions role assigned";
        }

        if ($isClientUser && !$clientUserLink) {
            $failures[] = "User has 'client' role but lacks a client_users pivot row";
        }

        if ($isSalesExec && !$salesMemberLink) {
            $failures[] = "User has 'sales-executive' role but lacks a sales_team_members row";
        }

        if (empty($failures)) {
            $this->info("VERIFICATION STATUS: PASS - This account has active status, valid organization, assigned roles, and required pivot links.");
            return Command::SUCCESS;
        } else {
            $this->error("VERIFICATION STATUS: FAIL");
            foreach ($failures as $failure) {
                $this->error("  - {$failure}");
            }
            return Command::FAILURE;
        }
    }
}
