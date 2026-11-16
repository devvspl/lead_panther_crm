<?php
namespace Tests\Feature\Admin;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use App\Models\{User, Organization, SalesTeam, SalesTeamMember, SeederRun, SeederRunChange, CreditPackage, LeadSource, Lead};
use App\Services\SeederRunService;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class SeederRollbackTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void {
        parent::setUp(); Role::firstOrCreate(['name'=>'Super Admin','guard_name'=>'web']);
        $this->admin=User::factory()->create(['status'=>'active']); $this->admin->assignRole('Super Admin');
    }
    public function test_run_tracks_created_rows_and_revert_preserves_preexisting_and_unrelated_data(): void {
        $existing=CreditPackage::create(['name'=>'Starter Pack','credit_count'=>7,'price'=>70,'validity_days'=>7]);
        $service=app(SeederRunService::class); $run=$service->run('CreditPackageSeeder',$this->admin);
        $this->assertSame('completed',$run->status); $this->assertGreaterThan(0,$run->change_count);
        $unrelated=CreditPackage::create(['name'=>'Created after seeding','credit_count'=>9,'price'=>90,'validity_days'=>9]);
        $preview=$service->preview($run->id,$this->admin); $this->assertSame([],$preview['conflicts']);
        $service->revert($run->id,$this->admin);
        $this->assertSame('reverted',$run->fresh()->status); $this->assertSame(2,CreditPackage::count());
        $this->assertEquals(7,$existing->fresh()->credit_count); $this->assertNotNull($unrelated->fresh());
        $this->assertDatabaseHas('audit_logs',['action'=>'seeder.reverted']);
        $this->expectException(\DomainException::class); $service->revert($run->id,$this->admin);
    }
    public function test_deltek_seed_and_revert_remove_its_accounts_team_and_organization_only(): void {
        $service=app(SeederRunService::class); $users=User::count(); $orgs=Organization::count();
        $run=$service->run('DeltekSeeder',$this->admin);
        $this->assertSame(12,User::where('email','like','%@deltek.leadpanther.test')->count());
        $this->assertDatabaseHas('organizations',['name'=>'Deltek']);
        $this->assertSame([],$service->preview($run->id,$this->admin)['conflicts']);
        $service->revert($run->id,$this->admin);
        $this->assertSame($users,User::count()); $this->assertSame($orgs,Organization::count());
        $this->assertSame(0,SalesTeam::count()); $this->assertSame(0,SalesTeamMember::count());
        $this->assertTrue($this->admin->fresh()->hasRole('Super Admin'));
        $this->assertNotNull(SeederRunChange::first());
    }
    public function test_idempotent_rerun_does_not_claim_preexisting_accounts_or_overwrite_passwords(): void {
        $service=app(SeederRunService::class); $first=$service->run('DeltekSeeder',$this->admin);
        $user=User::where('name','Brijesh')->firstOrFail(); $user->update(['password'=>Hash::make('changed-password')]); $hash=$user->fresh()->password;
        $second=$service->run('DeltekSeeder',$this->admin);
        $this->assertSame(0,$second->change_count); $this->assertSame($hash,$user->fresh()->password);
        $this->assertNotEmpty($service->preview($first->id,$this->admin)['conflicts']);
        $this->expectException(\DomainException::class); $service->revert($first->id,$this->admin);
    }
    public function test_new_business_dependencies_block_revert_without_cascading_real_records(): void {
        $service=app(SeederRunService::class); $run=$service->run('DeltekSeeder',$this->admin);
        $owner=User::where('name','Deltek')->firstOrFail(); $exec=User::where('name','SE1')->firstOrFail();
        $lead=Lead::create(['client_id'=>$owner->id,'assigned_to'=>$exec->id,'lead_source_id'=>LeadSource::create(['name'=>'Real incoming'])->id,'lead_code'=>'LP-AFTER-SEED','name'=>'Real customer','mobile'=>'9000000000']);
        $this->assertNotEmpty($service->preview($run->id,$this->admin)['conflicts']);
        try { $service->revert($run->id,$this->admin); $this->fail('Expected dependency protection.'); } catch(\DomainException $e) {}
        $this->assertNotNull($lead->fresh()); $this->assertSame('completed',$run->fresh()->status);
        $this->assertSame(12,User::where('email','like','%@deltek.leadpanther.test')->count());
    }
    public function test_updated_and_deleted_rows_are_restored_from_encrypted_snapshots(): void {
        $org=Organization::create(['name'=>'Existing organization']); $source=LeadSource::create(['name'=>'Existing source']);
        app()->bind(\Database\Seeders\CreditPackageSeeder::class,fn()=>new class extends Seeder {
            public function run(): void {
                DB::table('organizations')->where('name','Existing organization')->update(['name'=>'Changed by seeder']);
                DB::table('lead_sources')->where('name','Existing source')->delete();
            }
        });
        $service=app(SeederRunService::class); $run=$service->run('CreditPackageSeeder',$this->admin);
        $this->assertSame(2,$run->change_count); $this->assertNull($source->fresh());
        $encrypted=DB::table('seeder_run_changes')->value('before_values');
        $this->assertStringNotContainsString('Existing organization',$encrypted);
        $service->revert($run->id,$this->admin);
        $this->assertSame('Existing organization',$org->fresh()->name); $this->assertNotNull($source->fresh());
    }
    public function test_failed_seeder_rolls_back_partial_changes_and_retains_failure_audit(): void {
        app()->bind(\Database\Seeders\CreditPackageSeeder::class,fn()=>new class extends Seeder {
            public function run(): void { Organization::create(['name'=>'Must roll back']); throw new \RuntimeException('Fixture failure'); }
        });
        try { app(SeederRunService::class)->run('CreditPackageSeeder',$this->admin); $this->fail('Expected failure.'); } catch(\RuntimeException $e) {}
        $this->assertDatabaseMissing('organizations',['name'=>'Must roll back']);
        $this->assertDatabaseHas('seeder_runs',['status'=>'failed']); $this->assertDatabaseHas('audit_logs',['action'=>'seeder.failed']);
    }
    public function test_non_admin_cannot_seed_or_revert(): void {
        $user=User::factory()->create(); $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SeederRunService::class)->run('CreditPackageSeeder',$user);
    }
    public function test_seeder_ui_shows_deltek_without_old_label_and_reverts_after_preview(): void {
        $git=$this->createMock(\App\Services\GitSyncService::class);
        $git->method('getCredentials')->willReturn([]); $git->method('getRepoStatus')->willReturn(['current_branch'=>'main','current_commit'=>'test','short_commit'=>'test','last_commit_info'=>'Test fixture','modified_files'=>[],'modified_count'=>0,'ahead_count'=>0,'behind_count'=>0,'recent_commits'=>[]]);
        $git->method('getRemoteBranches')->willReturn([]); $git->method('getCommitHistory')->willReturn([]); $git->method('getBackupBranches')->willReturn([]);
        $this->app->instance(\App\Services\GitSyncService::class,$git);
        $run=app(SeederRunService::class)->run('CreditPackageSeeder',$this->admin);
        $component=\Livewire\Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\GitSync::class)->set('dbManagerTab','seeders')->set('dbSearch','Deltek')->assertSee('DeltekSeeder')->assertDontSee('DeltekDemoSeeder')->assertSee('Client Accounts');
        $component->call('previewSeederRevert',$run->id)->assertSee('Revert Seeder Data')->assertSee('credit_packages');
        $component->call('confirmSeederRevert',$run->id)->assertDispatched('toast');
        $this->assertSame('reverted',$run->fresh()->status);
    }
    public function test_ddl_is_blocked_before_execution_and_partial_records_are_rolled_back(): void {
        app()->bind(\Database\Seeders\CreditPackageSeeder::class,fn()=>new class extends Seeder {
            public function run(): void { Organization::create(['name'=>'Do not keep']); DB::statement('DROP TABLE organizations'); }
        });
        try { app(SeederRunService::class)->run('CreditPackageSeeder',$this->admin); $this->fail('Expected DDL rejection.'); } catch(\DomainException $e) {}
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('organizations')); $this->assertDatabaseMissing('organizations',['name'=>'Do not keep']);
    }
    public function test_later_polymorphic_team_ownership_blocks_deleting_a_seeded_organization(): void {
        $service=app(SeederRunService::class); $run=$service->run('DeltekSeeder',$this->admin);
        $org=Organization::where('name','Deltek')->firstOrFail();
        SalesTeam::create(['ownable_type'=>Organization::class,'ownable_id'=>$org->id,'name'=>'Team created after the seeder']);
        $this->assertNotEmpty($service->preview($run->id,$this->admin)['conflicts']);
        $this->expectException(\DomainException::class); $service->revert($run->id,$this->admin);
    }
    public function test_existing_record_can_be_restored_before_deleting_its_new_seeded_parent(): void {
        $user=User::factory()->create(['organization_id'=>null]);
        app()->bind(\Database\Seeders\CreditPackageSeeder::class,fn()=>new class extends Seeder {
            public function run(): void {
                $org=Organization::create(['name'=>'New parent']);
                DB::table('users')->whereNull('organization_id')->update(['organization_id'=>$org->id]);
            }
        });
        $service=app(SeederRunService::class); $run=$service->run('CreditPackageSeeder',$this->admin);
        $this->assertNotNull($user->fresh()->organization_id);
        $this->assertSame([],$service->preview($run->id,$this->admin)['conflicts']);
        $service->revert($run->id,$this->admin);
        $this->assertNull($user->fresh()->organization_id); $this->assertDatabaseMissing('organizations',['name'=>'New parent']);
    }
    public function test_legacy_deltek_rename_keeps_identity_and_password_and_revert_restores_original_labels(): void {
        $org=Organization::create(['name'=>'Deltek Demo']);
        $owner=User::factory()->create(['name'=>'Deltek','email'=>'deltek@demo.leadpanther.test','organization_id'=>$org->id]); $hash=$owner->password;
        $service=app(SeederRunService::class); $run=$service->run('DeltekSeeder',$this->admin);
        $this->assertSame('Deltek',$org->fresh()->name); $this->assertSame('deltek@deltek.leadpanther.test',$owner->fresh()->email); $this->assertSame($hash,$owner->fresh()->password);
        $this->assertSame([],$service->preview($run->id,$this->admin)['conflicts']);
        $service->revert($run->id,$this->admin);
        $this->assertSame('Deltek Demo',$org->fresh()->name); $this->assertSame('deltek@demo.leadpanther.test',$owner->fresh()->email); $this->assertSame($hash,$owner->fresh()->password);
    }
}
