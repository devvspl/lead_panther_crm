<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use App\Models\PortalAccount;
use App\Models\IntegrationCredential;
use App\Models\AuditLog;
use Throwable;

class GitSyncService
{
    protected string $workingDir;

    public function __construct(?string $workingDir = null)
    {
        $this->workingDir = $workingDir ?: base_path();
    }

    /**
     * Get or create the PortalAccount model for Git.
     */
    public function getGitAccount(): PortalAccount
    {
        return PortalAccount::firstOrCreate(
            ['type' => 'git'],
            [
                'name' => 'Git Repository Integration',
                'status' => 'active',
            ]
        );
    }

    /**
     * Get stored Git credentials decrypted without static hardcoded fallbacks.
     */
    public function getCredentials(): array
    {
        $account = $this->getGitAccount();
        $user = auth()->user();

        $gitConfigName = trim($this->runCommand(['git', 'config', 'user.name'])['stdout'] ?? '');
        $gitConfigEmail = trim($this->runCommand(['git', 'config', 'user.email'])['stdout'] ?? '');

        $committerName = $account->getCredential('committer_name') ?: ($gitConfigName ?: ($user?->name ?: ''));
        $committerEmail = $account->getCredential('committer_email') ?: ($gitConfigEmail ?: ($user?->email ?: ''));

        return [
            'remote_url' => $account->getCredential('remote_url') ?: $this->detectRemoteUrl(),
            'username' => $account->getCredential('username') ?: '',
            'access_token' => $account->getCredential('access_token') ?: '',
            'default_branch' => $account->getCredential('default_branch') ?: $this->detectCurrentBranch(),
            'committer_name' => $committerName,
            'committer_email' => $committerEmail,
        ];
    }

    /**
     * Save Git credentials encrypted in integration_credentials.
     */
    public function saveCredentials(
        string $remoteUrl,
        string $username,
        ?string $token,
        string $defaultBranch,
        ?string $committerName = null,
        ?string $committerEmail = null
    ): void {
        $account = $this->getGitAccount();
        $account->update(['status' => 'active', 'name' => 'Git Repository Integration']);

        $account->setCredential('remote_url', trim($remoteUrl));
        $account->setCredential('username', trim($username));
        $account->setCredential('default_branch', trim($defaultBranch));

        $name = trim($committerName ?? '');
        $email = trim($committerEmail ?? '');

        if (!empty($name)) {
            $account->setCredential('committer_name', $name);
            $this->runCommand(['git', 'config', 'user.name', $name]);
        }

        if (!empty($email)) {
            $account->setCredential('committer_email', $email);
            $this->runCommand(['git', 'config', 'user.email', $email]);
        }

        if (!empty($token)) {
            $account->setCredential('access_token', trim($token));
        }
    }

    /**
     * Translate cryptic Git errors into clear, actionable messages for the user.
     */
    public function translateGitError(string $rawError): string
    {
        $lower = strtolower($rawError);

        if (str_contains($lower, 'invalid username or token') || str_contains($lower, 'password authentication is not supported') || str_contains($lower, 'authentication failed')) {
            return 'GitHub Authentication Failed: The Personal Access Token is invalid, expired, or missing repository write permissions. Please generate a token with repo / Contents (write) scope and update it in Repository Connection.';
        }

        if (str_contains($lower, 'empty ident name') || str_contains($lower, 'author identity unknown') || str_contains($lower, 'tell me who you are')) {
            return 'Git Author Identity Missing: Git requires an Author Name and Email to create commits. Please fill in your Author Name and Email in the Repository Connection tab.';
        }

        if (str_contains($lower, 'permission') && str_contains($lower, 'denied')) {
            return 'Permission Denied: Your GitHub user or token does not have write access to push to this repository.';
        }

        if ((str_contains($lower, 'rejected') && str_contains($lower, 'fetch first')) || str_contains($lower, 'remote contains work') || str_contains($lower, 'non-fast-forward')) {
            return 'Remote Repository Ahead: The remote branch has new commits that are not present locally. Please click "1. Pull Latest" to merge remote updates before pushing.';
        }

        if (str_contains($lower, 'conflict') || str_contains($lower, 'merge conflict')) {
            return 'Merge Conflict Detected: There are conflicting changes between local files and the remote branch. Please resolve conflicts before continuing.';
        }

        if (str_contains($lower, 'could not resolve host')) {
            return 'Network Error: Could not resolve git remote host. Please verify server internet connectivity and the Remote URL.';
        }

        return $rawError ?: 'Unknown Git error occurred. Check the console output below.';
    }

    /**
     * Detect local repository remote URL.
     */
    public function detectRemoteUrl(): string
    {
        $result = $this->runCommand(['git', 'remote', 'get-url', 'origin']);
        return $result['successful'] ? trim($result['stdout']) : '';
    }

    /**
     * Detect current local git branch name.
     */
    public function detectCurrentBranch(): string
    {
        $result = $this->runCommand(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        return $result['successful'] ? trim($result['stdout']) : 'main';
    }

    /**
     * Build an authenticated remote URL dynamically in memory.
     */
    public function buildAuthenticatedUrl(string $remoteUrl, string $username, string $token): string
    {
        $trimmedUrl = trim($remoteUrl);
        if (empty($token) || !str_starts_with($trimmedUrl, 'https://')) {
            return $trimmedUrl;
        }

        $urlWithoutScheme = substr($trimmedUrl, 8); // remove https://
        // Remove existing user credentials if already in URL
        if (str_contains($urlWithoutScheme, '@')) {
            $urlWithoutScheme = substr($urlWithoutScheme, strpos($urlWithoutScheme, '@') + 1);
        }

        $userPart = !empty($username) ? urlencode($username) . ':' : '';
        return 'https://' . $userPart . urlencode($token) . '@' . $urlWithoutScheme;
    }

    /**
     * Test connection to remote git repository.
     */
    public function testConnection(string $remoteUrl, string $username, string $token): array
    {
        $authUrl = $this->buildAuthenticatedUrl($remoteUrl, $username, $token);

        $result = $this->runCommand(['git', 'ls-remote', '--heads', $authUrl], timeout: 30);

        if ($result['successful']) {
            return [
                'successful' => true,
                'message' => 'Successfully connected to remote Git repository.',
                'output' => $result['stdout'],
            ];
        }

        // Redact any tokens that may be present in error output
        $rawOutput = trim($result['stderr'] ?: $result['stdout']);
        if (empty($rawOutput)) {
            $rawOutput = 'Command returned exit code ' . $result['exit_code'] . ' with no error output.';
        }

        $sanitizedError = $token ? str_replace($token, '[REDACTED_TOKEN]', $rawOutput) : $rawOutput;
        $friendlyMessage = $this->translateGitError($sanitizedError);

        return [
            'successful' => false,
            'message' => $friendlyMessage,
            'output' => $sanitizedError,
        ];
    }

    /**
     * Get repository status details: branch, commit info, behind/ahead count, and status.
     */
    public function getRepoStatus(?string $branch = null): array
    {
        $branch = $branch ?: $this->detectCurrentBranch();

        // 1. Current commit SHA
        $commitSha = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');
        $shortSha = substr($commitSha, 0, 7);

        // 2. Last commit details
        $commitDetails = trim($this->runCommand(['git', 'log', '-1', '--pretty=format:%s | %an | %ad', '--date=relative'])['stdout'] ?? '');

        // 3. Git Status Porcelain
        $statusOutput = $this->runCommand(['git', 'status', '--porcelain'])['stdout'] ?? '';
        $modifiedFiles = [];
        if (!empty(trim($statusOutput))) {
            foreach (explode("\n", trim($statusOutput)) as $line) {
                if (!empty(trim($line))) {
                    $statusFlag = substr($line, 0, 2);
                    $filePath = trim(substr($line, 2));
                    $modifiedFiles[] = [
                        'flag' => trim($statusFlag),
                        'path' => $filePath,
                    ];
                }
            }
        }

        // 4. Remote tracking branch ahead / behind count
        $aheadCount = 0;
        $behindCount = 0;
        $revList = $this->runCommand(['git', 'rev-list', '--left-right', '--count', 'HEAD...origin/' . $branch]);
        if ($revList['successful'] && !empty(trim($revList['stdout']))) {
            $parts = preg_split('/\s+/', trim($revList['stdout']));
            $aheadCount = (int) ($parts[0] ?? 0);
            $behindCount = (int) ($parts[1] ?? 0);
        }

        // 5. Recent commits log (last 5)
        $recentCommits = [];
        $logRes = $this->runCommand(['git', 'log', '-5', '--pretty=format:%h|||%s|||%an|||%ad', '--date=short']);
        if ($logRes['successful'] && !empty(trim($logRes['stdout']))) {
            foreach (explode("\n", trim($logRes['stdout'])) as $row) {
                $cols = explode('|||', $row);
                if (count($cols) >= 4) {
                    $recentCommits[] = [
                        'hash' => $cols[0],
                        'message' => $cols[1],
                        'author' => $cols[2],
                        'date' => $cols[3],
                    ];
                }
            }
        }

        return [
            'current_branch' => $branch,
            'current_commit' => $commitSha,
            'short_commit' => $shortSha,
            'last_commit_info' => $commitDetails,
            'modified_files' => $modifiedFiles,
            'modified_count' => count($modifiedFiles),
            'ahead_count' => $aheadCount,
            'behind_count' => $behindCount,
            'recent_commits' => $recentCommits,
        ];
    }

    /**
     * Fetch list of remote branches.
     */
    public function getRemoteBranches(): array
    {
        $res = $this->runCommand(['git', 'branch', '-r']);
        $branches = [];

        if ($res['successful'] && !empty(trim($res['stdout']))) {
            foreach (explode("\n", trim($res['stdout'])) as $line) {
                $cleaned = trim(str_replace(['origin/', 'origin/HEAD -> '], '', $line));
                if (!empty($cleaned) && !str_contains($cleaned, '->')) {
                    $branches[] = $cleaned;
                }
            }
        }

        if (empty($branches)) {
            $branches = ['main', 'master'];
        }

        return array_unique($branches);
    }

    /**
     * Scan git diff and staged files for potential exposed secrets before pushing.
     */
    public function scanDiffForSecrets(): array
    {
        $status = $this->runCommand(['git', 'status', '--porcelain'])['stdout'] ?? '';
        $diff = $this->runCommand(['git', 'diff', 'HEAD'])['stdout'] ?? '';

        $findings = [];

        // 1. File path checks (e.g. .env, *.pem, *.key)
        $dangerousFilePatterns = [
            '/^\.env/i',
            '/\.pem$/i',
            '/\.key$/i',
            '/id_rsa/i',
            '/credentials\.json/i',
            '/service-account.*\.json/i',
        ];

        foreach (explode("\n", trim($status)) as $line) {
            $file = trim(substr($line, 2));
            foreach ($dangerousFilePatterns as $pattern) {
                if (preg_match($pattern, basename($file))) {
                    $findings[] = "Potentially sensitive file detected in git status: '{$file}'";
                }
            }
        }

        // 2. Diff line content checks (e.g. API keys, hardcoded passwords, tokens)
        $secretRegexes = [
            '/(?:api[_-]?key|app[_-]?secret|password|access[_-]?token|client[_-]?secret)\s*[:=]\s*[\'"][a-zA-Z0-9_\-\.]{12,}[\'"]/i',
            '/EAA[a-zA-Z0-9]{20,}/',
            '/ghp_[a-zA-Z0-9]{20,}/',
            '/github_pat_[a-zA-Z0-9]{20,}/',
            '/-----BEGIN (?:RSA )?PRIVATE KEY-----/',
        ];

        foreach (explode("\n", $diff) as $diffLine) {
            if (str_starts_with($diffLine, '+') && !str_starts_with($diffLine, '+++')) {
                foreach ($secretRegexes as $rgx) {
                    if (preg_match($rgx, $diffLine, $matches)) {
                        $sanitizedMatch = substr($matches[0], 0, 15) . '...[MASKED]';
                        $findings[] = "Potential secret pattern added: '{$sanitizedMatch}'";
                        break;
                    }
                }
            }
        }

        return [
            'has_secrets' => count($findings) > 0,
            'findings' => array_unique($findings),
        ];
    }

    /**
     * Execute Git Pull from remote repository.
     */
    public function pull(string $branch, ?int $userId = null): array
    {
        $creds = $this->getCredentials();

        if (empty($creds['remote_url']) || empty($creds['access_token'])) {
            return [
                'successful' => false,
                'commit_before' => 'unknown',
                'commit_after' => 'unknown',
                'stdout' => '',
                'stderr' => 'Git repository is not configured. Please enter your Remote URL and Personal Access Token in the Repository Connection tab.',
                'friendly_error' => 'Git repository is not configured. Please enter your Remote URL and Personal Access Token in the Repository Connection tab.',
                'has_conflicts' => false,
            ];
        }

        $authUrl = $this->buildAuthenticatedUrl($creds['remote_url'], $creds['username'], $creds['access_token']);
        $commitBefore = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        // Fetch first
        $fetchRes = $this->runCommand(['git', 'fetch', $authUrl, $branch], timeout: 90);

        // Pull with merge
        $pullRes = $this->runCommand(['git', 'pull', $authUrl, $branch], timeout: 120);

        $commitAfter = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        // Redact any tokens in output
        $token = $creds['access_token'];
        $stdout = $token ? str_replace($token, '[REDACTED_TOKEN]', $pullRes['stdout']) : $pullRes['stdout'];
        $stderr = $token ? str_replace($token, '[REDACTED_TOKEN]', $pullRes['stderr']) : $pullRes['stderr'];

        $hasConflicts = str_contains(strtolower($stdout . $stderr), 'conflict') || str_contains(strtolower($stdout . $stderr), 'merge conflict');
        $friendlyError = !$pullRes['successful'] ? $this->translateGitError($stderr ?: $stdout) : null;

        // Write to audit log
        $this->logAudit(
            userId: $userId,
            action: 'git_pull',
            fromValue: [
                'branch' => $branch,
                'commit_before' => $commitBefore,
            ],
            toValue: [
                'branch' => $branch,
                'commit_after' => $commitAfter,
                'successful' => $pullRes['successful'],
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]
        );

        return [
            'successful' => $pullRes['successful'],
            'commit_before' => $commitBefore,
            'commit_after' => $commitAfter,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'friendly_error' => $friendlyError,
            'has_conflicts' => $hasConflicts,
        ];
    }

    /**
     * Execute Git Push to remote repository.
     */
    public function push(string $branch, string $commitMessage, ?int $userId = null): array
    {
        $creds = $this->getCredentials();

        if (empty($creds['remote_url']) || empty($creds['access_token'])) {
            return [
                'successful' => false,
                'commit_before' => 'unknown',
                'commit_after' => 'unknown',
                'stdout' => '',
                'stderr' => 'Git repository is not configured. Please enter your Remote URL and Personal Access Token in the Repository Connection tab before pushing.',
                'friendly_error' => 'Git repository is not configured. Please enter your Remote URL and Personal Access Token in the Repository Connection tab before pushing.',
            ];
        }

        $authorName = trim($creds['committer_name'] ?? '');
        $authorEmail = trim($creds['committer_email'] ?? '');

        if (empty($authorName) || empty($authorEmail)) {
            return [
                'successful' => false,
                'commit_before' => 'unknown',
                'commit_after' => 'unknown',
                'stdout' => '',
                'stderr' => 'Git Author Name or Email is not configured. Please set your Author Name and Email in the Repository Connection tab before committing changes.',
                'friendly_error' => 'Git Author Name or Email is not configured. Please set your Author Name and Email in the Repository Connection tab before committing changes.',
            ];
        }

        // Ensure Git committer identity is configured locally
        $this->runCommand(['git', 'config', 'user.name', $authorName]);
        $this->runCommand(['git', 'config', 'user.email', $authorEmail]);

        $authUrl = $this->buildAuthenticatedUrl($creds['remote_url'], $creds['username'], $creds['access_token']);
        $commitBefore = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        // 1. Stage all modifications
        $this->runCommand(['git', 'add', '-A']);

        // 2. Commit with explicit author configuration flags
        $commitRes = $this->runCommand([
            'git',
            '-c', 'user.name=' . $authorName,
            '-c', 'user.email=' . $authorEmail,
            'commit',
            '-m', $commitMessage
        ]);

        // 3. Push to remote branch
        $pushRes = $this->runCommand(['git', 'push', $authUrl, 'HEAD:' . $branch], timeout: 120);

        $commitAfter = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        $token = $creds['access_token'];
        $stdout = $token ? str_replace($token, '[REDACTED_TOKEN]', $commitRes['stdout'] . "\n" . $pushRes['stdout']) : ($commitRes['stdout'] . "\n" . $pushRes['stdout']);
        $stderr = $token ? str_replace($token, '[REDACTED_TOKEN]', $commitRes['stderr'] . "\n" . $pushRes['stderr']) : ($commitRes['stderr'] . "\n" . $pushRes['stderr']);

        $isSuccessful = $pushRes['successful'];
        if (!$commitRes['successful'] && str_contains(strtolower($commitRes['stdout'] . $commitRes['stderr']), 'nothing to commit')) {
            $isSuccessful = $pushRes['successful'];
        } elseif (!$commitRes['successful']) {
            $isSuccessful = false;
        }

        $friendlyError = !$isSuccessful ? $this->translateGitError($stderr ?: $stdout) : null;

        // Write to audit log
        $this->logAudit(
            userId: $userId,
            action: 'git_push',
            fromValue: [
                'branch' => $branch,
                'commit_before' => $commitBefore,
                'commit_message' => $commitMessage,
            ],
            toValue: [
                'branch' => $branch,
                'commit_after' => $commitAfter,
                'successful' => $isSuccessful,
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]
        );

        return [
            'successful' => $isSuccessful,
            'commit_before' => $commitBefore,
            'commit_after' => $commitAfter,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'friendly_error' => $friendlyError,
        ];
    }

    /**
     * Get paginated commit history.
     */
    public function getCommitHistory(int $limit = 50): array
    {
        $res = $this->runCommand(['git', 'log', "-n", (string) $limit, '--pretty=format:%H|%an|%ad|%s', '--date=iso']);
        $commits = [];

        if ($res['successful'] && !empty(trim($res['stdout']))) {
            foreach (explode("\n", trim($res['stdout'])) as $line) {
                $cols = explode('|', $line, 4);
                if (count($cols) >= 4) {
                    $hash = trim($cols[0]);
                    $commits[] = [
                        'hash' => $hash,
                        'short_hash' => substr($hash, 0, 7),
                        'author' => trim($cols[1]),
                        'date' => trim($cols[2]),
                        'message' => trim($cols[3]),
                    ];
                }
            }
        }

        return $commits;
    }

    /**
     * Create and push a safety backup branch before executing destructive revert/reset.
     */
    public function createSafetyBackupBranch(string $branch): array
    {
        $creds = $this->getCredentials();
        $authUrl = $this->buildAuthenticatedUrl($creds['remote_url'], $creds['username'], $creds['access_token']);

        $headCommit = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');
        $backupBranchName = 'backup/pre-revert-' . date('Ymd-His') . '-' . substr($headCommit, 0, 7);

        // 1. Create local branch at current HEAD
        $createRes = $this->runCommand(['git', 'branch', $backupBranchName, 'HEAD']);
        if (!$createRes['successful']) {
            return [
                'successful' => false,
                'branch' => $backupBranchName,
                'commit' => $headCommit,
                'error' => 'Failed to create local safety backup branch: ' . $createRes['stderr'],
            ];
        }

        // 2. Push backup branch to remote
        $pushRes = $this->runCommand(['git', 'push', $authUrl, $backupBranchName], timeout: 120);
        if (!$pushRes['successful']) {
            return [
                'successful' => false,
                'branch' => $backupBranchName,
                'commit' => $headCommit,
                'error' => 'Failed to push safety backup branch to remote: ' . $pushRes['stderr'],
            ];
        }

        return [
            'successful' => true,
            'branch' => $backupBranchName,
            'commit' => $headCommit,
            'error' => null,
        ];
    }

    /**
     * Get list of existing safety backup branches.
     */
    public function getBackupBranches(): array
    {
        $res = $this->runCommand(['git', 'branch', '-a']);
        $backups = [];

        if ($res['successful'] && !empty(trim($res['stdout']))) {
            $uniqueNames = [];
            foreach (explode("\n", trim($res['stdout'])) as $line) {
                $cleaned = trim(str_replace(['*', 'remotes/origin/'], '', $line));
                if (str_starts_with($cleaned, 'backup/pre-revert-') && !in_array($cleaned, $uniqueNames)) {
                    $uniqueNames[] = $cleaned;

                    // Get commit info for backup branch
                    $logRes = $this->runCommand(['git', 'log', '-1', '--pretty=format:%H|%ad|%s', '--date=relative', $cleaned]);
                    $commitSha = 'unknown';
                    $date = 'unknown';
                    $message = 'Backup snapshot';

                    if ($logRes['successful'] && !empty(trim($logRes['stdout']))) {
                        $parts = explode('|', trim($logRes['stdout']), 3);
                        $commitSha = $parts[0] ?? 'unknown';
                        $date = $parts[1] ?? 'unknown';
                        $message = $parts[2] ?? 'Backup snapshot';
                    }

                    $backups[] = [
                        'name' => $cleaned,
                        'commit' => $commitSha,
                        'short_commit' => substr($commitSha, 0, 7),
                        'date' => $date,
                        'message' => $message,
                    ];
                }
            }
        }

        return $backups;
    }

    /**
     * Execute Option A: Safe Revert (creates new undo commit(s) on top of history).
     */
    public function safeRevert(string $branch, string $targetCommitSha, ?int $userId = null): array
    {
        $creds = $this->getCredentials();
        $authUrl = $this->buildAuthenticatedUrl($creds['remote_url'], $creds['username'], $creds['access_token']);

        $commitBefore = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        // Mandatory safety backup before revert
        $backup = $this->createSafetyBackupBranch($branch);
        if (!$backup['successful']) {
            return [
                'successful' => false,
                'commit_before' => $commitBefore,
                'commit_after' => $commitBefore,
                'stdout' => '',
                'stderr' => 'Safety backup failed: ' . $backup['error'] . ' — Aborting revert for protection.',
                'backup_branch' => null,
            ];
        }

        // Run git revert --no-commit targetCommitSha..HEAD
        $revertRes = $this->runCommand(['git', 'revert', '--no-commit', $targetCommitSha . '..HEAD'], timeout: 90);

        if (!$revertRes['successful']) {
            // Abort in-progress revert if it failed with conflicts
            $this->runCommand(['git', 'revert', '--abort']);
            return [
                'successful' => false,
                'commit_before' => $commitBefore,
                'commit_after' => $commitBefore,
                'stdout' => $revertRes['stdout'],
                'stderr' => 'Safe revert encountered conflicts: ' . $revertRes['stderr'],
                'backup_branch' => $backup['branch'],
            ];
        }

        $authorName = $creds['committer_name'] ?: 'Lead Panther Deployer';
        $authorEmail = $creds['committer_email'] ?: 'deploy@leadpanther.com';

        $shortTarget = substr($targetCommitSha, 0, 7);
        $commitMsg = "Revert to {$shortTarget}: Safe revert via Git Sync";
        $commitRes = $this->runCommand([
            'git',
            '-c', 'user.name=' . $authorName,
            '-c', 'user.email=' . $authorEmail,
            'commit',
            '-m', $commitMsg
        ]);

        // Push new revert commit to remote
        $pushRes = $this->runCommand(['git', 'push', $authUrl, 'HEAD:' . $branch], timeout: 120);

        $commitAfter = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        $token = $creds['access_token'];
        $stdout = $token ? str_replace($token, '[REDACTED_TOKEN]', $revertRes['stdout'] . "\n" . $commitRes['stdout'] . "\n" . $pushRes['stdout']) : ($revertRes['stdout'] . "\n" . $commitRes['stdout'] . "\n" . $pushRes['stdout']);
        $stderr = $token ? str_replace($token, '[REDACTED_TOKEN]', $revertRes['stderr'] . "\n" . $commitRes['stderr'] . "\n" . $pushRes['stderr']) : ($revertRes['stderr'] . "\n" . $commitRes['stderr'] . "\n" . $pushRes['stderr']);

        $this->logAudit(
            userId: $userId,
            action: 'git_revert_safe',
            fromValue: [
                'branch' => $branch,
                'commit_before' => $commitBefore,
                'target_commit' => $targetCommitSha,
                'backup_branch' => $backup['branch'],
            ],
            toValue: [
                'branch' => $branch,
                'commit_after' => $commitAfter,
                'successful' => $pushRes['successful'],
                'strategy' => 'safe_revert',
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]
        );

        return [
            'successful' => $pushRes['successful'],
            'commit_before' => $commitBefore,
            'commit_after' => $commitAfter,
            'target_commit' => $targetCommitSha,
            'backup_branch' => $backup['branch'],
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * Execute Option B: Hard Reset (destructive - rewrites branch history).
     */
    public function hardReset(string $branch, string $targetCommitSha, ?int $userId = null): array
    {
        $creds = $this->getCredentials();
        $authUrl = $this->buildAuthenticatedUrl($creds['remote_url'], $creds['username'], $creds['access_token']);

        $commitBefore = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        // Mandatory safety backup before hard reset
        $backup = $this->createSafetyBackupBranch($branch);
        if (!$backup['successful']) {
            return [
                'successful' => false,
                'commit_before' => $commitBefore,
                'commit_after' => $commitBefore,
                'stdout' => '',
                'stderr' => 'Safety backup failed: ' . $backup['error'] . ' — Aborting hard reset for protection.',
                'backup_branch' => null,
            ];
        }

        // Hard reset to target commit
        $resetRes = $this->runCommand(['git', 'reset', '--hard', $targetCommitSha]);

        // Force push to remote branch
        $pushRes = $this->runCommand(['git', 'push', '--force', $authUrl, 'HEAD:' . $branch], timeout: 120);

        $commitAfter = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        $token = $creds['access_token'];
        $stdout = $token ? str_replace($token, '[REDACTED_TOKEN]', $resetRes['stdout'] . "\n" . $pushRes['stdout']) : ($resetRes['stdout'] . "\n" . $pushRes['stdout']);
        $stderr = $token ? str_replace($token, '[REDACTED_TOKEN]', $resetRes['stderr'] . "\n" . $pushRes['stderr']) : ($resetRes['stderr'] . "\n" . $pushRes['stderr']);

        $this->logAudit(
            userId: $userId,
            action: 'git_revert_hard',
            fromValue: [
                'branch' => $branch,
                'commit_before' => $commitBefore,
                'target_commit' => $targetCommitSha,
                'backup_branch' => $backup['branch'],
            ],
            toValue: [
                'branch' => $branch,
                'commit_after' => $commitAfter,
                'successful' => $pushRes['successful'],
                'strategy' => 'hard_reset',
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]
        );

        return [
            'successful' => $pushRes['successful'],
            'commit_before' => $commitBefore,
            'commit_after' => $commitAfter,
            'target_commit' => $targetCommitSha,
            'backup_branch' => $backup['branch'],
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * Restore codebase to a previously created safety backup branch.
     */
    public function restoreFromBackup(string $branch, string $backupBranchName, ?int $userId = null): array
    {
        $creds = $this->getCredentials();
        $authUrl = $this->buildAuthenticatedUrl($creds['remote_url'], $creds['username'], $creds['access_token']);

        $commitBefore = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        // Create snapshot before restoring
        $newBackup = $this->createSafetyBackupBranch($branch);

        // Reset to backup branch
        $resetRes = $this->runCommand(['git', 'reset', '--hard', $backupBranchName]);

        // Push to remote
        $pushRes = $this->runCommand(['git', 'push', '--force', $authUrl, 'HEAD:' . $branch], timeout: 120);

        $commitAfter = trim($this->runCommand(['git', 'rev-parse', 'HEAD'])['stdout'] ?? 'unknown');

        $token = $creds['access_token'];
        $stdout = $token ? str_replace($token, '[REDACTED_TOKEN]', $resetRes['stdout'] . "\n" . $pushRes['stdout']) : ($resetRes['stdout'] . "\n" . $pushRes['stdout']);
        $stderr = $token ? str_replace($token, '[REDACTED_TOKEN]', $resetRes['stderr'] . "\n" . $pushRes['stderr']) : ($resetRes['stderr'] . "\n" . $pushRes['stderr']);

        $this->logAudit(
            userId: $userId,
            action: 'git_restore_backup',
            fromValue: [
                'branch' => $branch,
                'commit_before' => $commitBefore,
                'backup_restored' => $backupBranchName,
            ],
            toValue: [
                'branch' => $branch,
                'commit_after' => $commitAfter,
                'successful' => $pushRes['successful'],
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]
        );

        return [
            'successful' => $pushRes['successful'],
            'commit_before' => $commitBefore,
            'commit_after' => $commitAfter,
            'backup_restored' => $backupBranchName,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * Run Follow-up Commands (Migrate, Composer, Npm build, Cache clear).
     */
    public function runFollowupAction(string $actionType, ?int $userId = null): array
    {
        $command = match ($actionType) {
            'migrate' => ['php', 'artisan', 'migrate', '--force'],
            'composer' => ['composer', 'install', '--no-dev', '--optimize-autoloader'],
            'npm' => ['npm', 'run', 'build'],
            'clear_cache' => ['php', 'artisan', 'optimize:clear'],
            'cache_all' => ['php', 'artisan', 'optimize'],
            default => throw new \InvalidArgumentException("Invalid follow-up action: {$actionType}"),
        };

        $res = $this->runCommand($command, timeout: 180);

        $this->logAudit(
            userId: $userId,
            action: 'git_followup_' . $actionType,
            fromValue: ['action' => $actionType],
            toValue: [
                'successful' => $res['successful'],
                'stdout' => $res['stdout'],
                'stderr' => $res['stderr'],
            ]
        );

        return [
            'action' => $actionType,
            'successful' => $res['successful'],
            'stdout' => $res['stdout'],
            'stderr' => $res['stderr'],
        ];
    }

    /**
     * Helper to write structured AuditLog entry.
     */
    protected function logAudit(?int $userId, string $action, array $fromValue, array $toValue): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId ?: (auth()->id() ?? null),
                'action' => $action,
                'subject_type' => 'git_sync',
                'subject_id' => null,
                'from_value' => json_encode($fromValue),
                'to_value' => json_encode($toValue),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Fail open for audit logging exceptions
        }
    }

    /**
     * Execute a process securely with an argument array.
     */
    public function runCommand(array $command, int $timeout = 60): array
    {
        $process = new Process($command, $this->workingDir);
        $process->setTimeout($timeout);

        // Inherit parent environment so Windows Winsock, DNS (getaddrinfo), PATH, SystemRoot are available
        $systemEnv = getenv();
        if (empty($systemEnv) || !is_array($systemEnv)) {
            $systemEnv = $_SERVER;
        }

        $envVars = array_merge(
            $systemEnv,
            [
                'GIT_TERMINAL_PROMPT' => '0',
                'GIT_ASKPASS' => 'echo',
                'SystemRoot' => getenv('SystemRoot') ?: (getenv('SYSTEMROOT') ?: ($_SERVER['SystemRoot'] ?? 'C:\\Windows')),
                'SYSTEMROOT' => getenv('SystemRoot') ?: (getenv('SYSTEMROOT') ?: ($_SERVER['SystemRoot'] ?? 'C:\\Windows')),
                'WINDIR' => getenv('WINDIR') ?: ($_SERVER['WINDIR'] ?? 'C:\\Windows'),
                'PATH' => getenv('PATH') ?: ($_SERVER['PATH'] ?? ''),
                'USERPROFILE' => getenv('USERPROFILE') ?: ($_SERVER['USERPROFILE'] ?? ''),
                'TEMP' => getenv('TEMP') ?: ($_SERVER['TEMP'] ?? ''),
                'TMP' => getenv('TMP') ?: ($_SERVER['TMP'] ?? ''),
            ]
        );

        $process->setEnv($envVars);

        try {
            $process->run();

            return [
                'successful' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
        } catch (Throwable $e) {
            return [
                'successful' => false,
                'exit_code' => -1,
                'stdout' => '',
                'stderr' => $e->getMessage(),
            ];
        }
    }
}
