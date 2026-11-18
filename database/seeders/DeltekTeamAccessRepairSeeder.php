<?php
namespace Database\Seeders;

use App\Models\{AuditLog, Organization, SalesTeam, SalesTeamMember, User};
use App\Services\SeederRunService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Repair the confirmed restored-data conflict without recreating users or teams. */
class DeltekTeamAccessRepairSeeder extends Seeder
{
    public function run(): void
    {
        $tracking = app(SeederRunService::class);
        if (!$tracking->isRecording()) {
            $tracking->run(class_basename(static::class), auth()->user());
            return;
        }
        DB::transaction(function () {
            $org = Organization::where('name', 'Deltek')->where('status', 'active')->sole();
            $leader = User::where('organization_id', $org->id)->where('email', 'brijesh@deltek.leadpanther.test')->where('status', 'active')->role('Team Leader')->sole();
            $team = SalesTeam::where('ownable_type', Organization::class)->where('ownable_id', $org->id)->where('leader_id', $leader->id)->lockForUpdate()->sole();
            $membership = SalesTeamMember::where('sales_team_id', $team->id)->where('user_id', $leader->id)->lockForUpdate()->firstOrFail();
            if (!(bool) $membership->is_active || $membership->role !== 'Team Leader') {
                $before = $membership->only(['is_active', 'role']);
                $membership->update(['is_active' => true, 'role' => 'Team Leader']);
                AuditLog::create(['user_id' => auth()->id(), 'organization_id' => $org->id, 'action' => 'organization.leader_membership_confirmed',
                    'subject_type' => User::class, 'subject_id' => $leader->id, 'from_value' => json_encode($before),
                    'to_value' => json_encode(['team_id' => $team->id, 'is_active' => true, 'role' => 'Team Leader']), 'created_at' => now()]);
            }
            $this->command?->info('Brijesh team membership confirmed. Accounts, executives and lead assignments preserved.');
        });
    }
}
