<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Livewire\Admin\FailedJobsBrowser;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Permission\Models\Role;

class QueueAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
    }

    public function test_failed_jobs_browser_renders_and_retries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-12345',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'ProcessInboundLeadJob']),
            'exception' => 'Test exception message',
            'failed_at' => now(),
        ]);

        $failedJob = DB::table('failed_jobs')->first();

        Livewire::actingAs($admin)
            ->test(FailedJobsBrowser::class)
            ->assertSee('ProcessInboundLeadJob')
            ->call('retryJob', $failedJob->id)
            ->call('forgetJob', $failedJob->id);
    }

    public function test_scheduled_commands_are_registered_in_console_schedule(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $commandSignatures = $events->map(function ($event) {
            return $event->command;
        })->implode(' ');

        $this->assertStringContainsString('credits:check-low-balances', $commandSignatures);
        $this->assertStringContainsString('followups:check-due', $commandSignatures);
        $this->assertStringContainsString('team:auto-offline', $commandSignatures);
    }
}
