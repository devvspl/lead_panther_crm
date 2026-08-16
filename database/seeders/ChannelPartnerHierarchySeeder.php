<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ChannelPartner;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\User;

class ChannelPartnerHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $partnerOrgs = Organization::where('type', 'channel_partner')->get();
        $firstProject = Project::first();
        $salesExecs = User::role('sales-executive')->get();

        if ($firstProject && $partnerOrgs->count() >= 3) {
            foreach ($partnerOrgs as $index => $pOrg) {
                $cp = ChannelPartner::create([
                    'organization_id' => $pOrg->id,
                    'project_id' => $firstProject->id,
                    'name' => $pOrg->name . ' Team',
                    'status' => 'active',
                ]);

                // Create Sales Team for this Channel Partner
                $team = SalesTeam::create([
                    'ownable_type' => ChannelPartner::class,
                    'ownable_id' => $cp->id,
                    'name' => $cp->name . ' Sales Squad',
                ]);

                // Assign 3 sales executives to this team
                $execSlice = $salesExecs->slice($index * 3, 3);
                foreach ($execSlice as $exec) {
                    SalesTeamMember::create([
                        'sales_team_id' => $team->id,
                        'user_id' => $exec->id,
                        'role' => 'agent',
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
