<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Metadata and EXPLAIN only; never migrates, seeds, ANALYZEs tables or executes DDL. */
class AuditDatabasePerformance extends Command
{
    protected $signature = 'performance:database-audit';
    protected $description = 'Print actual index metadata and bounded query plans through a read-only database session';

    public function handle(): int
    {
        $name = 'performance_audit_readonly';
        $config = config('database.connections.'.config('database.default'));
        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error('Run this command against the deployed MySQL/MariaDB connection. SQLite fixture plans are recorded by NavigationAuditTest.');
            return self::FAILURE;
        }
        $config['options'][\PDO::ATTR_TIMEOUT] = 3;
        config(['database.connections.'.$name => $config]);
        $connection = DB::connection($name);
        try {
            $connection->statement('SET SESSION TRANSACTION READ ONLY');
            $connection->beginTransaction();
            $schema = $connection->getSchemaBuilder();
            $inventory = [];
            foreach ($schema->getTableListing() as $table) {
                $inventory[$table] = ['indexes' => $schema->getIndexes($table), 'foreign_keys' => $schema->getForeignKeys($table)];
            }
            $queries = [];
            if ($schema->hasColumns('leads', ['assigned_to', 'archived_at', 'current_stage'])) {
                $queries[] = ['sql' => 'SELECT id FROM leads WHERE assigned_to = ? AND archived_at IS NULL AND current_stage = ? ORDER BY id DESC LIMIT 25', 'bindings' => [0, 'new']];
            }
            if ($schema->hasColumns('followups', ['assigned_to', 'status', 'due_at'])) {
                $queries[] = ['sql' => 'SELECT id FROM followups WHERE assigned_to = ? AND status = ? AND due_at >= ? ORDER BY due_at LIMIT 25', 'bindings' => [0, 'pending', now()->startOfDay()->toDateTimeString()]];
            }
            if ($schema->hasColumns('notifications', ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'])) {
                $queries[] = ['sql' => 'SELECT id FROM notifications WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL ORDER BY created_at DESC LIMIT 6', 'bindings' => [\App\Models\User::class, 0]];
            }
            $plans = [];
            foreach ($queries as $query) $plans[] = ['sql' => $query['sql'], 'explain' => $connection->select('EXPLAIN '.$query['sql'], $query['bindings'])];
            $this->line(json_encode(['version' => $connection->selectOne('SELECT VERSION() as version')->version,
                'read_only' => true, 'inventory' => $inventory, 'plans' => $plans,
                'limitations' => 'Plans use synthetic actor ID 0, not actual tenant selectivity. EXPLAIN rows are estimates. Capture real scoped page queries on staging before approving indexes.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Database audit unavailable ('.class_basename($e).', code '.$e->getCode().'). No schema or data changes performed.');
            return self::FAILURE;
        } finally {
            if ($connection->transactionLevel() > 0) $connection->rollBack();
            DB::purge($name);
        }
    }
}
