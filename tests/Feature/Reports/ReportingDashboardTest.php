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

    public function test_reports_use_actual_records_and_refresh_without_static_or_cached_values(): void
    {
        $admin=User::factory()->create(); $admin->assignRole('super-admin');
        $organization=\App\Models\Organization::create(['name'=>'Actual Reports Org','status'=>'active']);
        $owner=\App\Models\Client::create(['name'=>'Actual Owner','organization_id'=>$organization->id]);
        $executive=User::factory()->create(['organization_id'=>$organization->id]); $executive->assignRole('sales-executive');
        $source=\App\Models\LeadSource::create(['name'=>'Actual Source']);
        $project=\App\Models\Project::create(['client_id'=>$owner->id,'name'=>'Actual Project']);
        $created=now()->subDay();
        $first=\App\Models\Lead::factory()->create(['client_id'=>$owner->id,'assigned_to'=>$executive->id,'project_id'=>$project->id,'lead_source_id'=>$source->id,'current_stage'=>'closed_won','status'=>'closed_won','lead_cost'=>10,'created_at'=>$created,'first_response_at'=>$created->copy()->addMinutes(5)]);
        $second=\App\Models\Lead::factory()->create(['client_id'=>$owner->id,'assigned_to'=>$executive->id,'project_id'=>$project->id,'lead_source_id'=>$source->id,'current_stage'=>'contacted','status'=>'in_progress','lead_cost'=>30,'created_at'=>$created,'first_response_at'=>$created->copy()->addMinutes(40)]);
        $unit=\App\Models\ProjectUnit::create(['project_id'=>$project->id,'unit_number'=>'R-101','status'=>'reserved']);
        $booking=\App\Models\Booking::create(['lead_id'=>$first->id,'project_unit_id'=>$unit->id,'booking_amount'=>500,'booked_at'=>now(),'status'=>'confirmed']);
        \App\Models\PaymentReceived::create(['booking_id'=>$booking->id,'amount'=>200,'method'=>'UPI','transaction_id'=>'ACTUAL-001','paid_at'=>now(),'status'=>'completed']);
        \App\Models\PaymentReceived::create(['booking_id'=>$booking->id,'amount'=>90,'method'=>'UPI','transaction_id'=>'FAILED-001','paid_at'=>now(),'status'=>'failed']);
        \App\Models\CreditTransaction::create(['client_id'=>$owner->id,'lead_id'=>$first->id,'credit_before'=>100,'credit_used'=>10,'credit_after'=>90,'transaction_type'=>'lead_consumption','created_at'=>now()]);
        \App\Models\SiteVisit::create(['lead_id'=>$first->id,'executive_id'=>$executive->id,'visit_date'=>now(),'attendance'=>'attended']);
        \App\Models\Followup::create(['lead_id'=>$first->id,'created_by'=>$executive->id,'due_at'=>now()->subHour(),'status'=>'completed','updated_at'=>now()->subHours(2)]);
        \App\Models\Followup::create(['lead_id'=>$second->id,'created_by'=>$executive->id,'due_at'=>now()->subHour(),'status'=>'pending']);
        $reason=\App\Models\ReplacementReason::create(['label'=>'Verified invalid','is_eligible'=>true]);
        \App\Models\LeadReplacement::create(['lead_id'=>$second->id,'reason_id'=>$reason->id,'requested_by'=>$owner->id,'requested_at'=>now(),'status'=>'approved']);
        $this->actingAs($admin);

        $sourceReport=Livewire::test(SourcePerformance::class);
        $sourceReport->assertViewHas('chartReportData',fn($rows)=>count($rows)===1 && $rows[0]['total_leads']===2 && (float)$rows[0]['cost_per_lead']===20.0 && (float)$rows[0]['conversion_rate']===50.0);
        $second->update(['lead_cost'=>50]);
        $sourceReport->call('$refresh')->assertViewHas('chartReportData',fn($rows)=>(float)$rows[0]['cost_per_lead']===30.0);
        Livewire::test(SlaDashboard::class)->assertViewHas('buckets',fn($b)=>$b===['excellent'=>1,'acceptable'=>0,'breached'=>1]);
        Livewire::test(SalesExecutivePerformance::class)->assertViewHas('executives',fn($rows)=>$rows[0]['assigned_leads']===2 && (float)$rows[0]['sla_contacted_rate']===50.0 && $rows[0]['site_visits']===1 && $rows[0]['bookings_closed']===1);
        Livewire::test(FollowupPerformance::class)->assertViewHas('followupData',fn($rows)=>$rows[0]['on_time']===1 && $rows[0]['overdue']===1 && (float)$rows[0]['on_time_rate']===50.0);
        Livewire::test(ReplacementRate::class)->assertViewHas('ratesData',fn($rows)=>count($rows)===1 && $rows[0]['approved_claims']===1 && (float)$rows[0]['replacement_rate']===50.0)->assertViewHas('monthlyTrend',fn($trend)=>(float)end($trend['rates'])===50.0);
        Livewire::test(RevenueAndBookings::class)->assertViewHas('roiData',fn($rows)=>count($rows)===1 && (float)$rows[0]['revenue']===200.0 && (float)$rows[0]['booking_value']===500.0 && (float)$rows[0]['outstanding']===300.0 && (float)$rows[0]['credits_spent']===10.0)->assertViewHas('monthlyChart',fn($chart)=>end($chart['bookings'])===1 && abs(end($chart['revenues'])-0.00002)<0.000001);
    }

    public function test_empty_reports_do_not_invent_sample_data_and_tabs_are_validated(): void
    {
        $admin=User::factory()->create(); $admin->assignRole('super-admin'); $this->actingAs($admin);
        Livewire::test(SourcePerformance::class)->assertViewHas('chartReportData',[])->assertDontSee('Meta Ads');
        Livewire::test(ReplacementRate::class)->assertViewHas('ratesData',[])->assertViewHas('monthlyTrend',fn($trend)=>array_sum($trend['rates'])===0);
        Livewire::test(RevenueAndBookings::class)->assertViewHas('roiData',[])->assertViewHas('monthlyChart',fn($chart)=>array_sum($chart['revenues'])==0);
        Livewire::test(SalesExecutivePerformance::class)->assertViewHas('executives',[]);
        Livewire::test(FollowupPerformance::class)->assertViewHas('followupData',[]);
        Livewire::test(SlaDashboard::class)->assertViewHas('buckets',['excellent'=>0,'acceptable'=>0,'breached'=>0]);
        $container=Livewire::test(ReportsContainer::class);
        foreach(['source','sla','replacement','followup','revenue','executive'] as $tab) $container->call('setTab',$tab)->assertSet('activeTab',$tab)->assertOk();
        $container->call('setTab','unknown')->assertStatus(422);
    }
}
