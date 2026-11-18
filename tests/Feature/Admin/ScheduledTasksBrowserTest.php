<?php
namespace Tests\Feature\Admin;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use App\Models\{User,Organization,ScheduledTaskRun,AuditLog};
use App\Services\ScheduledTaskService;
use App\Jobs\RunScheduledTask;
use App\Livewire\Admin\ScheduledTasksBrowser;
use Livewire\Livewire;

class ScheduledTasksBrowserTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void {
        parent::setUp();
        \Spatie\Permission\Models\Role::create(['name'=>'Super Admin']);
        $this->admin=User::factory()->create(['status'=>'active']); $this->admin->assignRole('Super Admin'); $this->actingAs($this->admin);
    }
    private function service(): ScheduledTaskService { return app(ScheduledTaskService::class); }
    private function task($callback,string $key='client-delivery-cycles') {
        $task=app(Schedule::class)->call($callback)->name($key)->everyMinute()->withoutOverlapping(15);
        $this->service()->instrument($task); return $task;
    }
    public function test_registered_task_details_and_worker_order(): void {
        $events=$this->service()->events();
        $this->assertCount(11,$events);
        $this->assertSame('queue-worker',array_key_last($events));
        $this->assertStringContainsString('--max-time=20',$events['queue-worker']->command);
        Livewire::test(ScheduledTasksBrowser::class)->assertSee('Scheduled Tasks &amp; Run History',false)->call('inspectTask','client-delivery-cycles')
            ->assertSet('showDetails',true)->assertSee('recover queued/running batches');
    }
    public function test_task_browser_is_available_inside_the_existing_git_sync_page(): void {
        $this->mock(\App\Services\GitSyncService::class,function($mock) {
            $mock->shouldReceive('getCredentials')->andReturn([]);
            $mock->shouldReceive('getRepoStatus')->andReturn(['current_branch'=>'main','current_commit'=>'abc123','short_commit'=>'abc123','last_commit_info'=>'Test commit','modified_files'=>[],'recent_commits'=>[],'working_directory_clean'=>true,'upstream_configured'=>true,'modified_count'=>0,'ahead_count'=>0,'behind_count'=>0]);
            $mock->shouldReceive('getRemoteBranches','getCommitHistory','getBackupBranches')->andReturn([]);
        });
        Livewire::test(\App\Livewire\Admin\GitSync::class)->call('setActiveTab','tasks')
            ->assertSet('activeTab','tasks')->assertSeeLivewire(ScheduledTasksBrowser::class)->assertSee('Scheduled Tasks');
    }
    public function test_automatic_task_records_actual_database_execution(): void {
        $task=$this->task(fn()=>Organization::create(['name'=>'Automatically processed','status'=>'active']));
        $task->run(app());
        $this->assertDatabaseHas('organizations',['name'=>'Automatically processed']);
        $run=ScheduledTaskRun::where('task_key','client-delivery-cycles')->firstOrFail();
        $this->assertSame('scheduled',$run->origin); $this->assertSame('completed',$run->status);
        $this->assertNotNull($run->started_at); $this->assertNotNull($run->finished_at); $this->assertSame(0,$run->exit_code);
        $this->assertDatabaseHas('audit_logs',['action'=>'scheduler.completed','subject_id'=>$run->id]);
    }
    public function test_manual_run_is_queued_executes_once_and_prevents_duplicate_requests(): void {
        Queue::fake();
        $this->task(fn()=>Organization::create(['name'=>'Manually processed','status'=>'active']));
        $run=$this->service()->request('client-delivery-cycles',$this->admin);
        $this->assertSame('queued',$run->status); Queue::assertPushed(RunScheduledTask::class,fn($job)=>$job->runId===$run->id);
        try { $this->service()->request('client-delivery-cycles',$this->admin); $this->fail('Duplicate request allowed'); }
        catch(\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('task',$e->errors()); }
        (new RunScheduledTask($run->id))->handle($this->service());
        (new RunScheduledTask($run->id))->handle($this->service());
        $this->assertSame('completed',$run->fresh()->status);
        $this->assertSame(1,Organization::where('name','Manually processed')->count());
        $this->assertSame($this->admin->id,$run->fresh()->requested_by);
    }
    public function test_failure_details_are_recorded_and_credentials_masked(): void {
        Queue::fake();
        $this->task(fn()=>throw new \RuntimeException('Service failed token=private-value password=hidden-value'));
        $run=$this->service()->request('client-delivery-cycles',$this->admin);
        $this->service()->runManual($run->id);
        $run->refresh(); $this->assertSame('failed',$run->status); $this->assertSame(1,$run->exit_code);
        $this->assertStringContainsString('********',$run->error); $this->assertStringNotContainsString('private-value',$run->error);
        Livewire::test(ScheduledTasksBrowser::class)->call('inspectRun',$run->id)->assertSee('Service failed')->assertDontSee('private-value');
    }
    public function test_lock_and_task_conditions_are_respected(): void {
        Queue::fake();
        $task=$this->task(fn()=>Organization::create(['name'=>'Should not run']));
        $this->assertTrue($task->mutex->create($task));
        $run=$this->service()->request('client-delivery-cycles',$this->admin); $this->service()->runManual($run->id);
        $this->assertSame('skipped',$run->fresh()->status); $this->assertDatabaseMissing('organizations',['name'=>'Should not run']);
        $task->mutex->forget($task); $task->skip(fn()=>true);
        $run=$this->service()->request('client-delivery-cycles',$this->admin); $this->service()->runManual($run->id);
        $this->assertSame('skipped',$run->fresh()->status);
    }
    public function test_manual_queue_worker_runs_without_waiting_for_a_worker(): void {
        Queue::fake();
        $this->task(fn()=>Organization::create(['name'=>'Worker invoked']),'queue-worker');
        $run=$this->service()->request('queue-worker',$this->admin);
        $this->assertSame('completed',$run->status); $this->assertDatabaseHas('organizations',['name'=>'Worker invoked']);
        Queue::assertNotPushed(RunScheduledTask::class);
    }
    public function test_manual_actions_and_history_are_super_admin_only(): void {
        $user=User::factory()->create(['status'=>'active']);
        Livewire::actingAs($user)->test(ScheduledTasksBrowser::class)->assertForbidden();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->request('client-delivery-cycles',$user);
    }
    public function test_arbitrary_commands_are_rejected(): void {
        Queue::fake();
        Livewire::test(ScheduledTasksBrowser::class)->call('runTask','migrate:fresh')->assertHasErrors('task');
        $this->assertSame(0,ScheduledTaskRun::count()); Queue::assertNotPushed(RunScheduledTask::class);
    }
    public function test_queued_run_rechecks_administrator_permission_before_execution(): void {
        Queue::fake(); $this->task(fn()=>Organization::create(['name'=>'Revoked action']));
        $run=$this->service()->request('client-delivery-cycles',$this->admin);
        $this->admin->update(['status'=>'inactive']);
        $this->service()->runManual($run->id);
        $this->assertSame('failed',$run->fresh()->status); $this->assertDatabaseMissing('organizations',['name'=>'Revoked action']);
    }
    public function test_output_is_stored_with_secrets_redacted(): void {
        $task=$this->task(fn()=>true);
        $this->service()->starting($task);
        $run=ScheduledTaskRun::latest('id')->firstOrFail();
        file_put_contents(storage_path('logs/scheduled-task-'.$run->id.'.log'),'Processed 2 leads; api_key="hidden-secret"');
        $task->exitCode=0; $this->service()->finished($task);
        $this->assertStringContainsString('Processed 2 leads',$run->fresh()->output);
        $this->assertStringNotContainsString('hidden-secret',$run->fresh()->output);
        $this->assertFileDoesNotExist(storage_path('logs/scheduled-task-'.$run->id.'.log'));
    }
    public function test_history_filters_and_pagination_use_stored_records(): void {
        for($i=0;$i<17;$i++) ScheduledTaskRun::create(['task_key'=>'client-delivery-cycles','label'=>'Delivery history '.$i,'expression'=>'* * * * *','timezone'=>'UTC','origin'=>'scheduled','status'=>$i===16 ? 'failed':'completed']);
        Livewire::test(ScheduledTasksBrowser::class)->assertViewHas('runs',fn($runs)=>$runs->total()===17 && $runs->count()===15)
            ->set('statusFilter','failed')->assertViewHas('runs',fn($runs)=>$runs->total()===1)
            ->set('taskFilter','queue-worker')->assertViewHas('runs',fn($runs)=>$runs->total()===0);
    }
    public function test_a_failed_callback_can_be_run_again_by_the_same_worker(): void {
        Queue::fake(); $attempt=0;
        $this->task(function() use(&$attempt) { if(++$attempt===1) throw new \RuntimeException('First attempt failed'); return Organization::create(['name'=>'Retry succeeded']); });
        $first=$this->service()->request('client-delivery-cycles',$this->admin); $this->service()->runManual($first->id);
        $this->assertSame('failed',$first->fresh()->status);
        $second=$this->service()->request('client-delivery-cycles',$this->admin); $this->service()->runManual($second->id);
        $this->assertSame('completed',$second->fresh()->status); $this->assertDatabaseHas('organizations',['name'=>'Retry succeeded']);
    }
}
