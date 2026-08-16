<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use App\Models\User;
use App\Livewire\Reports\ReportsContainer;
use App\Livewire\Reports\SourcePerformance;
use App\Livewire\Reports\SlaDashboard;
use App\Livewire\Reports\ReplacementRate;
use App\Livewire\Reports\FollowupPerformance;
use App\Livewire\Reports\RevenueAndBookings;
use App\Livewire\Reports\SalesExecutivePerformance;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class ReportingDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'sales-executive']);
        Role::create(['name' => 'client']);
    }

    public function test_reports_container_hub_renders_successfully(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get('/reports');
        $response->assertOk();
    }

    public function test_all_six_report_components_render_and_export_excel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)->test(SourcePerformance::class)->assertOk()->call('exportExcel');
        Livewire::actingAs($user)->test(SlaDashboard::class)->assertOk()->call('exportExcel');
        Livewire::actingAs($user)->test(ReplacementRate::class)->assertOk()->call('exportExcel');
        Livewire::actingAs($user)->test(FollowupPerformance::class)->assertOk()->call('exportExcel');
        Livewire::actingAs($user)->test(RevenueAndBookings::class)->assertOk()->call('exportExcel');
        Livewire::actingAs($user)->test(SalesExecutivePerformance::class)->assertOk()->call('exportExcel');
    }
}
