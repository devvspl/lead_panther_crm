<?php

namespace Tests\Feature\Performance;

use App\Livewire\Dashboard\DashboardShell;
use App\Livewire\Leads\LeadKanban;
use App\Models\{Campaign, LeadSource, MetaAdAccount, Organization, PortalAccount, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Event};
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Performance tests require isolated SQLite :memory: before migrations can run.');
        }
    }

    public function test_prefetched_meta_counts_match_real_records_without_queries_during_render(): void
    {
        Event::fake();
        $page = PortalAccount::create(['type' => 'meta', 'name' => 'Page']);
        $account = MetaAdAccount::create(['portal_account_id' => $page->id, 'ad_account_id' => '111', 'name' => 'Ads']);
        $account->pages()->attach($page);
        foreach (['campaign', 'campaign', 'adset', 'ad', 'insight'] as $i => $kind) $account->records()->create([
            'kind' => $kind, 'remote_id' => (string) $i, 'ad_account_id' => '111', 'data' => [], 'synced_at' => now()]);
        $account = MetaAdAccount::withCount(['pages', 'campaigns', 'adsets', 'ads', 'insights'])->findOrFail($account->id);
        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });
        for ($i = 0; $i < 4; $i++) $this->assertSame(['campaigns' => 2, 'adsets' => 1, 'ads' => 1, 'insights' => 1, 'pages' => 1], $account->sync_counts);
        $this->assertSame(0, $queries);
    }

    public function test_grouped_stage_and_member_totals_preserve_tenant_visibility_and_empty_stages(): void
    {
        Event::fake();
        Role::findOrCreate('Builder/Developer');
        Role::findOrCreate('Sales Executive');
        $org = Organization::create(['name' => 'Visible tenant', 'status' => 'active']);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']); $owner->assignRole('Builder/Developer');
        $member = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']); $member->assignRole('Sales Executive');
        $other = User::factory()->create(['status' => 'active']); $other->assignRole('Builder/Developer');
        $source = LeadSource::create(['name' => 'Source']);
        $project = Project::create(['client_id' => $owner->id, 'name' => 'Project']);
        $campaign = Campaign::create(['project_id' => $project->id, 'lead_source_id' => $source->id, 'name' => 'Campaign']);
        foreach ([['new', false, false, $owner->id, $member->id], ['contacted', false, false, $owner->id, $member->id],
            ['interested', true, false, $owner->id, $member->id], ['booking', false, true, $owner->id, $member->id],
            ['booking', false, false, $other->id, $other->id]] as $i => [$stage, $pending, $archived, $clientId, $assigned]) {
            DB::table('leads')->insert(['lead_code' => 'SCOPE-'.$i, 'name' => 'Scoped lead', 'mobile' => '980000000'.$i,
                'client_id' => $clientId, 'assigned_to' => $assigned, 'campaign_id' => $campaign->id, 'lead_source_id' => $source->id,
                'current_stage' => $stage, 'status' => 'new', 'delivery_pending' => $pending, 'archived_at' => $archived ? now() : null,
                'internal_processing_pending' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($owner);
        $component = Livewire::test(LeadKanban::class);
        $counts = $component->viewData('stageCounts');
        $this->assertSame(1, $counts['new']);
        $this->assertSame(1, $counts['contacted']);
        $this->assertSame(0, $counts['booking']);
        $data = Livewire::test(DashboardShell::class, ['role' => 'builder'])->instance()->builderDashboardData;
        $stats = $data['team_performance']->firstWhere('name', $member->name);
        $this->assertSame(2, $stats['assigned_leads']);
        $this->assertSame(1, $stats['contacted_leads']);
        $this->assertSame(0, $stats['bookings']);
    }
}
