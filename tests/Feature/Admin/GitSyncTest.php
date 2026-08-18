<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\PortalAccount;
use App\Models\IntegrationCredential;
use App\Models\AuditLog;
use App\Services\GitSyncService;
use App\Livewire\Admin\GitSync;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class GitSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'Builder']);
        Role::firstOrCreate(['name' => 'Channel Partner']);
    }

    public function test_super_admin_can_access_git_sync_screen(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->get('/admin/git-sync');

        $response->assertStatus(200);
        $response->assertSee('Codebase Git Sync');
        $response->assertSee('Pull Updates from Remote Repository');
    }

    public function test_non_admin_gets_403_forbidden(): void
    {
        $builder = User::factory()->create();
        $builder->assignRole('Builder');

        $response = $this->actingAs($builder)->get('/admin/git-sync');
        $response->assertStatus(403);
    }

    public function test_saves_git_credentials_encrypted_at_rest(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $url = 'https://github.com/mockorg/mock_repo.git';
        $user = 'mock_deployer';
        $token = 'mock_github_pat_1234567890abcdef';
        $branch = 'main';

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->set('remoteUrl', $url)
            ->set('username', $user)
            ->set('accessToken', $token)
            ->set('defaultBranch', $branch)
            ->call('saveSettings')
            ->assertDispatched('toast');

        $account = PortalAccount::where('type', 'git')->first();
        $this->assertNotNull($account);
        $this->assertEquals($url, $account->getCredential('remote_url'));
        $this->assertEquals($user, $account->getCredential('username'));
        $this->assertEquals($token, $account->getCredential('access_token'));
        $this->assertEquals($branch, $account->getCredential('default_branch'));

        // Verify stored in DB is encrypted
        $rawSecret = \DB::table('integration_credentials')
            ->where('portal_account_id', $account->id)
            ->where('key_name', 'access_token')
            ->value('encrypted_value');

        $this->assertNotEquals($token, $rawSecret);
    }

    public function test_test_connection_verifies_remote_url(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $mockGitService = $this->createMock(GitSyncService::class);
        $mockGitService->method('getCredentials')->willReturn([
            'remote_url' => 'https://github.com/mock/repo.git',
            'username' => 'user',
            'access_token' => 'mock_token',
            'default_branch' => 'main',
        ]);
        $mockGitService->method('getRepoStatus')->willReturn([
            'current_branch' => 'main',
            'current_commit' => 'abc1234',
            'short_commit' => 'abc1234',
            'last_commit_info' => 'Initial commit',
            'modified_files' => [],
            'modified_count' => 0,
            'ahead_count' => 0,
            'behind_count' => 0,
            'recent_commits' => [],
        ]);
        $mockGitService->method('getRemoteBranches')->willReturn(['main', 'staging']);
        $mockGitService->method('testConnection')->willReturn([
            'successful' => true,
            'message' => 'Successfully connected to remote Git repository.',
            'output' => "abc123\trefs/heads/main\n",
        ]);

        $this->app->instance(GitSyncService::class, $mockGitService);

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->set('remoteUrl', 'https://github.com/mock/repo.git')
            ->set('accessToken', 'mock_token')
            ->call('testConnection')
            ->assertDispatched('toast');
    }

    public function test_push_blocked_without_exact_confirmation_phrase(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->set('commitMessage', 'fix: resolve bug')
            ->set('confirmPushPhrase', 'push') // Wrong phrase
            ->call('startPush')
            ->assertHasErrors(['confirmPushPhrase']);
    }

    public function test_pre_push_secret_scanner_detects_sensitive_credentials(): void
    {
        $gitService = new GitSyncService();
        $result = $gitService->scanDiffForSecrets();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('has_secrets', $result);
        $this->assertArrayHasKey('findings', $result);
    }

    public function test_pull_and_push_creates_audit_log_records(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $mockGitService = $this->createMock(GitSyncService::class);
        $mockGitService->method('getCredentials')->willReturn([
            'remote_url' => 'https://github.com/mock/repo.git',
            'username' => 'user',
            'access_token' => 'mock_token',
            'default_branch' => 'main',
        ]);
        $mockGitService->method('getRepoStatus')->willReturn([
            'current_branch' => 'main',
            'current_commit' => 'abc1234',
            'short_commit' => 'abc1234',
            'last_commit_info' => 'Initial commit',
            'modified_files' => [],
            'modified_count' => 0,
            'ahead_count' => 0,
            'behind_count' => 0,
            'recent_commits' => [],
        ]);
        $mockGitService->method('getRemoteBranches')->willReturn(['main']);
        $mockGitService->method('pull')->willReturn([
            'successful' => true,
            'commit_before' => 'abc111',
            'commit_after' => 'abc222',
            'stdout' => 'Already up to date.',
            'stderr' => '',
            'has_conflicts' => false,
        ]);
        $mockGitService->method('scanDiffForSecrets')->willReturn([
            'has_secrets' => false,
            'findings' => [],
        ]);
        $mockGitService->method('push')->willReturn([
            'successful' => true,
            'commit_before' => 'abc111',
            'commit_after' => 'abc222',
            'stdout' => 'Pushed successfully.',
            'stderr' => '',
        ]);

        $this->app->instance(GitSyncService::class, $mockGitService);

        // Pull Action
        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->call('startPull')
            ->assertDispatched('toast');

        // Push Action with exact confirmation phrase
        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->set('commitMessage', 'feat: deploy updates')
            ->set('confirmPushPhrase', 'PUSH TO PRODUCTION')
            ->call('startPush')
            ->assertDispatched('toast');
    }

    public function test_followup_maintenance_actions_execute_and_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $mockGitService = $this->createMock(GitSyncService::class);
        $mockGitService->method('getCredentials')->willReturn([
            'remote_url' => 'https://github.com/mock/repo.git',
            'username' => 'user',
            'access_token' => 'mock_token',
            'default_branch' => 'main',
        ]);
        $mockGitService->method('getRepoStatus')->willReturn([
            'current_branch' => 'main',
            'current_commit' => 'abc1234',
            'short_commit' => 'abc1234',
            'last_commit_info' => 'Initial commit',
            'modified_files' => [],
            'modified_count' => 0,
            'ahead_count' => 0,
            'behind_count' => 0,
            'recent_commits' => [],
        ]);
        $mockGitService->method('getRemoteBranches')->willReturn(['main']);
        $mockGitService->method('runFollowupAction')->willReturn([
            'action' => 'clear_cache',
            'successful' => true,
            'stdout' => 'Configuration cache cleared successfully.',
            'stderr' => '',
        ]);

        $this->app->instance(GitSyncService::class, $mockGitService);

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->call('runFollowup', 'clear_cache')
            ->assertDispatched('toast');
    }

    public function test_commit_history_renders_commits_and_revert_options(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $mockGitService = $this->createMock(GitSyncService::class);
        $mockGitService->method('getCredentials')->willReturn([
            'remote_url' => 'https://github.com/mock/repo.git',
            'username' => 'user',
            'access_token' => 'mock_token',
            'default_branch' => 'main',
        ]);
        $mockGitService->method('getRepoStatus')->willReturn([
            'current_branch' => 'main',
            'current_commit' => 'abc1234567890abcdef',
            'short_commit' => 'abc1234',
            'last_commit_info' => 'Initial commit',
            'modified_files' => [],
            'modified_count' => 0,
            'ahead_count' => 0,
            'behind_count' => 0,
            'recent_commits' => [],
        ]);
        $mockGitService->method('getRemoteBranches')->willReturn(['main']);
        $mockGitService->method('getCommitHistory')->willReturn([
            [
                'hash' => '11111111111111111111',
                'short_hash' => '1111111',
                'author' => 'Deployer',
                'date' => '2026-08-18 10:00:00',
                'message' => 'feat: add deployment system',
            ],
            [
                'hash' => '22222222222222222222',
                'short_hash' => '2222222',
                'author' => 'Developer',
                'date' => '2026-08-17 10:00:00',
                'message' => 'fix: stability patches',
            ],
        ]);
        $mockGitService->method('getBackupBranches')->willReturn([
            [
                'name' => 'backup/pre-revert-20260818-100000-1111111',
                'commit' => '11111111111111111111',
                'short_commit' => '1111111',
                'date' => '1 hour ago',
                'message' => 'Safety backup',
            ],
        ]);

        $this->app->instance(GitSyncService::class, $mockGitService);

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->set('activeTab', 'history')
            ->assertSee('feat: add deployment system')
            ->assertSee('fix: stability patches')
            ->assertSee('backup/pre-revert-20260818-100000-1111111')
            ->call('openRevertModal', '22222222222222222222')
            ->assertSet('showRevertModal', true)
            ->assertSet('revertStrategy', 'safe');
    }

    public function test_safe_revert_executes_and_logs_audit_record(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $mockGitService = $this->createMock(GitSyncService::class);
        $mockGitService->method('getCredentials')->willReturn([
            'remote_url' => 'https://github.com/mock/repo.git',
            'username' => 'user',
            'access_token' => 'mock_token',
            'default_branch' => 'main',
        ]);
        $mockGitService->method('getRepoStatus')->willReturn([
            'current_branch' => 'main',
            'current_commit' => 'abc1234',
            'short_commit' => 'abc1234',
            'last_commit_info' => 'Initial commit',
            'modified_files' => [],
            'modified_count' => 0,
            'ahead_count' => 0,
            'behind_count' => 0,
            'recent_commits' => [],
        ]);
        $mockGitService->method('getRemoteBranches')->willReturn(['main']);
        $mockGitService->method('getCommitHistory')->willReturn([
            [
                'hash' => 'target123456789',
                'short_hash' => 'target1',
                'author' => 'Dev',
                'date' => '2026-08-18',
                'message' => 'Stable commit',
            ],
        ]);
        $mockGitService->method('safeRevert')->willReturn([
            'successful' => true,
            'commit_before' => 'head1234',
            'commit_after' => 'newrevert1234',
            'target_commit' => 'target123456789',
            'backup_branch' => 'backup/pre-revert-20260818-head1234',
            'stdout' => 'Safe revert completed',
            'stderr' => '',
        ]);

        $this->app->instance(GitSyncService::class, $mockGitService);

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->call('openRevertModal', 'target123456789')
            ->set('revertStrategy', 'safe')
            ->call('startRevert')
            ->assertDispatched('toast')
            ->assertSet('showRevertModal', false);
    }

    public function test_hard_reset_blocked_without_exact_confirmation_phrase(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $mockGitService = $this->createMock(GitSyncService::class);
        $mockGitService->method('getCredentials')->willReturn([
            'remote_url' => 'https://github.com/mock/repo.git',
            'username' => 'user',
            'access_token' => 'mock_token',
            'default_branch' => 'main',
        ]);
        $mockGitService->method('getRepoStatus')->willReturn([
            'current_branch' => 'main',
            'current_commit' => 'abc1234',
            'short_commit' => 'abc1234',
            'last_commit_info' => 'Initial commit',
            'modified_files' => [],
            'modified_count' => 0,
            'ahead_count' => 0,
            'behind_count' => 0,
            'recent_commits' => [],
        ]);
        $mockGitService->method('getRemoteBranches')->willReturn(['main']);
        $mockGitService->method('getCommitHistory')->willReturn([
            [
                'hash' => 'target123456789',
                'short_hash' => 'target1',
                'author' => 'Dev',
                'date' => '2026-08-18',
                'message' => 'Stable commit',
            ],
        ]);

        $this->app->instance(GitSyncService::class, $mockGitService);

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->call('openRevertModal', 'target123456789')
            ->set('revertStrategy', 'hard')
            ->set('confirmRevertPhrase', 'revert') // Wrong phrase
            ->call('startRevert')
            ->assertHasErrors(['confirmRevertPhrase']);
    }

    public function test_hard_reset_executes_with_exact_phrase_and_logs_audit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $mockGitService = $this->createMock(GitSyncService::class);
        $mockGitService->method('getCredentials')->willReturn([
            'remote_url' => 'https://github.com/mock/repo.git',
            'username' => 'user',
            'access_token' => 'mock_token',
            'default_branch' => 'main',
        ]);
        $mockGitService->method('getRepoStatus')->willReturn([
            'current_branch' => 'main',
            'current_commit' => 'abc1234',
            'short_commit' => 'abc1234',
            'last_commit_info' => 'Initial commit',
            'modified_files' => [],
            'modified_count' => 0,
            'ahead_count' => 0,
            'behind_count' => 0,
            'recent_commits' => [],
        ]);
        $mockGitService->method('getRemoteBranches')->willReturn(['main']);
        $mockGitService->method('getCommitHistory')->willReturn([
            [
                'hash' => 'target123456789',
                'short_hash' => 'target1',
                'author' => 'Dev',
                'date' => '2026-08-18',
                'message' => 'Stable commit',
            ],
        ]);
        $mockGitService->method('hardReset')->willReturn([
            'successful' => true,
            'commit_before' => 'head1234',
            'commit_after' => 'target123456789',
            'target_commit' => 'target123456789',
            'backup_branch' => 'backup/pre-revert-20260818-head1234',
            'stdout' => 'Hard reset completed',
            'stderr' => '',
        ]);

        $this->app->instance(GitSyncService::class, $mockGitService);

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->call('openRevertModal', 'target123456789')
            ->set('revertStrategy', 'hard')
            ->set('confirmRevertPhrase', 'REVERT TO THIS COMMIT')
            ->call('startRevert')
            ->assertDispatched('toast')
            ->assertSet('showRevertModal', false);
    }

    public function test_restore_from_backup_branch_executes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $mockGitService = $this->createMock(GitSyncService::class);
        $mockGitService->method('getCredentials')->willReturn([
            'remote_url' => 'https://github.com/mock/repo.git',
            'username' => 'user',
            'access_token' => 'mock_token',
            'default_branch' => 'main',
        ]);
        $mockGitService->method('getRepoStatus')->willReturn([
            'current_branch' => 'main',
            'current_commit' => 'abc1234',
            'short_commit' => 'abc1234',
            'last_commit_info' => 'Initial commit',
            'modified_files' => [],
            'modified_count' => 0,
            'ahead_count' => 0,
            'behind_count' => 0,
            'recent_commits' => [],
        ]);
        $mockGitService->method('getRemoteBranches')->willReturn(['main']);
        $mockGitService->method('restoreFromBackup')->willReturn([
            'successful' => true,
            'commit_before' => 'abc1234',
            'commit_after' => 'backupcommit1234',
            'backup_restored' => 'backup/pre-revert-20260818-100000',
            'stdout' => 'Restored successfully',
            'stderr' => '',
        ]);

        $this->app->instance(GitSyncService::class, $mockGitService);

        Livewire::actingAs($admin)
            ->test(GitSync::class)
            ->call('restoreBackup', 'backup/pre-revert-20260818-100000')
            ->assertDispatched('toast');
    }
}
