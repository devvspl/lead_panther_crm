<?php

namespace Tests\Feature\Performance;

use App\Models\{Campaign, LeadSource, Organization, Project, User};
use App\Support\NavigationConfig;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Event, Http, Queue, Schema};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

/** Explicit opt-in: creates fixtures ONLY in phpunit's in-memory SQLite database. */
class NavigationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Navigation audit requires isolated SQLite :memory: before migrations can run.');
        }
    }

    public function test_measure_all_role_sidebar_destinations(): void
    {
        if (getenv('RUN_NAVIGATION_AUDIT') !== '1') $this->markTestSkipped('Opt-in navigation benchmark.');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        Queue::fake();
        Event::fake();
        $org = Organization::create(['name' => 'Benchmark tenant', 'status' => 'active']);
        $actors = [];
        foreach (['Super Admin', 'Builder/Developer', 'Team Leader', 'Sales Executive'] as $roleName) {
            $role = Role::findOrCreate($roleName);
            foreach (['view-team-reports', 'view-team-leads', 'view-team-members', 'view-team-followups'] as $permission) $role->givePermissionTo(Permission::findOrCreate($permission));
            $actor = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
            $actor->assignRole($role);
            $actors[] = $actor;
        }
        $source = LeadSource::create(['name' => 'Benchmark']);
        for ($i = 0; $i < 20; $i++) {
            $member = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
            $member->assignRole('Sales Executive');
        }
        $portal = \App\Models\PortalAccount::create(['name' => 'Benchmark Page', 'type' => 'meta', 'status' => 'active']);
        for ($i = 0; $i < 4; $i++) {
            $account = \App\Models\MetaAdAccount::create(['portal_account_id' => $portal->id, 'name' => 'Benchmark ads '.$i, 'ad_account_id' => (string) (12300 + $i), 'status' => 'connected']);
            $account->pages()->attach($portal);
        }
        $project = Project::create(['name' => 'Benchmark project', 'client_id' => $actors[1]->id]);
        $campaign = Campaign::create(['name' => 'Benchmark campaign', 'project_id' => $project->id, 'lead_source_id' => $source->id]);
        // Raw test fixture insert deliberately avoids assignment/delivery/credit observers.
        $rows = [];
        for ($i = 0; $i < 1000; $i++) $rows[] = ['lead_code' => 'PERF-'.$i, 'name' => 'Benchmark lead', 'mobile' => (string) (9800000000 + $i),
            'client_id' => $actors[1]->id, 'assigned_to' => $actors[3]->id, 'campaign_id' => $campaign->id,
            'lead_source_id' => $source->id, 'status' => 'new', 'current_stage' => 'new', 'delivery_pending' => false,
            'internal_processing_pending' => false, 'created_at' => now()->subDays($i % 14), 'updated_at' => now()];
        foreach (array_chunk($rows, 100) as $chunk) DB::table('leads')->insert($chunk);

        $capture = false; $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$capture, &$queries) {
            if (!$capture) return;
            $queries[] = ['sql' => $event->sql, 'ms' => $event->time,
                'identity' => hash('sha256', $event->sql.serialize($event->bindings))];
        });
        $results = []; $plans = [];
        foreach ($actors as $actor) {
            $this->actingAs($actor);
            $role = NavigationConfig::getRoleKey($actor);
            $sections = NavigationConfig::getNavItemsForUser($actor);
            foreach (array_merge($sections['primary'] ?? [], $sections['database'] ?? []) as $item) {
                // These operational screens inspect remote Git/filesystem/migration state in mount.
                if (in_array($item['key'], ['git-sync', 'backups', 'dev-tools'])) {
                    $results[] = ['role' => $role, 'key' => $item['key'], 'path' => parse_url($item['url'], PHP_URL_PATH), 'unmeasured' => 'Operational side effects require a controlled staging browser session.'];
                    continue;
                }
                $runs = [];
                for ($run = 0; $run < 3; $run++) {
                    $queries = []; $capture = true; $start = hrtime(true);
                    $response = $this->get($item['url']);
                    $elapsed = (hrtime(true) - $start) / 1e6; $capture = false;
                    $sorted = collect($queries)->sortByDesc('ms');
                    $runs[] = ['response_ms' => round($elapsed, 2), 'status' => $response->status(), 'queries' => count($queries),
                        'query_ms' => round(array_sum(array_column($queries, 'ms')), 2), 'html_bytes' => strlen($response->getContent()),
                        'duplicate_queries' => count($queries) - count(array_unique(array_column($queries, 'identity'))),
                        'repeated_sql' => collect($queries)->groupBy('sql')->map(fn ($group) => ['sql' => $group->first()['sql'], 'count' => $group->count(), 'ms' => round($group->sum('ms'), 2)])->sortByDesc('count')->take(10)->values()->all(),
                        'slowest' => $sorted->take(5)->map(fn ($q) => ['sql' => $q['sql'], 'ms' => $q['ms']])->values()->all()];
                    // SQLite plans document only this fixture engine, never MySQL plans.
                    foreach ($sorted->take(5) as $q) {
                        if (!preg_match('/^select\b/i', ltrim($q['sql']))) continue;
                        $key = hash('sha256', $q['sql']);
                        if (isset($plans[$key])) continue;
                        // Bind NULL for shape-only plans; no private bindings are serialized.
                        try { $plans[$key] = ['sql' => $q['sql'], 'plan' => DB::select('EXPLAIN QUERY PLAN '.$q['sql'], array_fill(0, substr_count($q['sql'], '?'), null))]; }
                        catch (\Throwable) { $plans[$key] = ['sql' => $q['sql'], 'plan_unavailable' => true]; }
                    }
                }
                $results[] = ['role' => $role, 'key' => $item['key'], 'path' => parse_url($item['url'], PHP_URL_PATH), 'runs' => $runs];
            }
        }
        $tables = [];
        foreach (Schema::getTableListing() as $table) $tables[$table] = Schema::getIndexes($table);
        $label = preg_replace('/[^a-z0-9_-]/i', '', getenv('PERFORMANCE_AUDIT_LABEL') ?: 'local');
        $directory = base_path('project-documentation/performance');
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        file_put_contents($directory.'/'.$label.'.json', json_encode(['environment' => 'Isolated SQLite :memory: fixtures; not production or browser timings',
            'fixture_leads' => 1000, 'runs_per_page' => 3, 'results' => $results, 'indexes' => $tables, 'plans' => array_values($plans)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->assertNotEmpty($results);
    }
}
