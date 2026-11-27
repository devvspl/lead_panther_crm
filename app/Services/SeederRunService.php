<?php
namespace App\Services;

use App\Models\{SeederRun, SeederRunChange, User, AuditLog};
use Illuminate\Support\Facades\{Artisan, Cache, DB, Schema};

class SeederRunService
{
    private bool $recording=false;
    private bool $inside=false;
    private array $hooked=[];
    private string $activeConnection='';
    private array $before=[];
    private array $keys=[];
    private array $tables=[];
    private array $foreignKeys=[];
    private array $morphs=[];
    private const RETAIN=['audit_logs','seeder_runs','seeder_run_changes','migrations','cache','cache_locks','sessions','jobs','job_batches','failed_jobs'];

    public function __construct() {
        foreach(DB::getConnections() as $connection) $this->hook($connection);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\ConnectionEstablished::class,fn($event)=>$this->hook($event->connection));
    }
    private function hook($connection): void {
        $id=spl_object_id($connection);
        if (isset($this->hooked[$id])) return;
        $this->hooked[$id]=$connection;
        $connection->beforeExecuting(fn($sql,$bindings,$connection)=>$this->beforeQuery($sql,$connection->getName()));
    }

    public function isRecording(): bool { return $this->recording; }
    private function guard(?User $actor): void {
        if ($actor) abort_unless($actor->is_active && $actor->hasAnyRole(['Super Admin','super-admin']),403);
        else abort_unless(app()->runningInConsole(),403);
    }
    public function canonical(string $class): string {
        $class=str_replace('Database\\Seeders\\','',$class);
        if ($class==='DeltekDemoSeeder') $class='DeltekSeeder';
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*Seeder$/',$class) || ! is_file(database_path('seeders/'.$class.'.php'))) throw new \InvalidArgumentException('Select an existing seeder class.');
        return $class;
    }
    private function schema(): void {
        $currentDb = DB::connection()->getDatabaseName();
        $this->tables = collect(Schema::getTables())
            ->filter(function($t) use ($currentDb) {
                $schema = is_array($t) ? ($t['schema'] ?? null) : ($t->schema ?? null);
                return empty($schema) || $schema === $currentDb || $schema === 'main';
            })
            ->pluck('name')
            ->filter(fn($t) => ! str_starts_with($t, 'sqlite_'))
            ->values()
            ->all();
        $this->foreignKeys = [];
        $this->morphs = [];
    }

    private function loadForeignKeys(): void {
        $currentDb = DB::connection()->getDatabaseName();
        $cacheKey = 'seeder_run_fks_' . $currentDb;
        $this->foreignKeys = Cache::remember($cacheKey, 3600, function() {
            $fks = [];
            foreach($this->tables as $table) {
                $fks[$table] = Schema::getForeignKeys($table);
            }
            return $fks;
        });
    }

    private function keyColumns(string $table): array {
        if (isset($this->keys[$table])) return $this->keys[$table];
        $indexes=Schema::getIndexes($table);
        foreach($indexes as $index) if ($index['primary']) return $this->keys[$table]=$index['columns'];
        $columns=collect(Schema::getColumns($table))->keyBy('name');
        foreach($indexes as $index) if ($index['unique'] && ! collect($index['columns'])->contains(fn($c)=>$columns[$c]['nullable'])) return $this->keys[$table]=$index['columns'];
        throw new \DomainException("Cannot track {$table}: a primary key or non-null unique key is required.");
    }
    private function rowKey(array $row,array $columns): array { return array_intersect_key($row,array_flip($columns)); }
    private function fingerprint(array $row): string { ksort($row); return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)); }
    private function rows(string $table,bool $lock=true): array {
        $keys=$this->keyColumns($table); $q=DB::table($table);
        foreach($keys as $key) $q->orderBy($key);
        if ($lock) $q->lockForUpdate();
        $rows=$q->limit(50001)->get();
        if ($rows->count()>50000) throw new \DomainException("{$table} exceeds the safe tracked-run size; no seeder changes were committed.");
        $result=[];
        foreach($rows as $row) { $row=(array)$row; $result[$this->fingerprint($this->rowKey($row,$keys))]=$row; }
        return $result;
    }
    private function snapshot(string $table,bool $dependents): void {
        if (! array_key_exists($table,$this->before)) $this->before[$table]=$this->rows($table);
        if (! $dependents) return;
        if (empty($this->foreignKeys)) {
            $this->loadForeignKeys();
        }
        foreach($this->foreignKeys as $child=>$keys) foreach($keys as $key) {
            if ($key['foreign_table']===$table && ! in_array($child,self::RETAIN) && ! array_key_exists($child,$this->before)) $this->snapshot($child,true);
        }
    }
    public function beforeQuery(string $sql,string $connection): void {
        if (! $this->recording || $this->inside) return;
        $this->inside=true;
        try {
            $sql=ltrim($sql);
            $single=preg_replace('/\x27(?:\x27\x27|\\\\.|[^\x27])*\x27/','?', $sql);
            if (str_contains(rtrim($single,"; \t\r\n"),';')) throw new \DomainException('Multi-statement seeder SQL is not supported.');
            if (preg_match('/^(select|show|explain|savepoint|release|rollback)\b/i',$sql)) return;
            if (preg_match('/^pragma\s+(table_info|table_xinfo|index_list|index_info|index_xinfo|foreign_key_list|database_list)\b/i',$sql)) return;
            if ($connection!==$this->activeConnection) throw new \DomainException('Tracked seeders must write through the default database connection.');
            if (! preg_match('/^(insert(?:\s+or\s+\w+|\s+ignore)?\s+into|replace\s+into|update|delete\s+from)\s+[`"\[]?([a-zA-Z0-9_]+)[`"\]]?/i',$sql,$m)) throw new \DomainException('Tracked seeders cannot run DDL, truncate, connection-setting changes or unrecognized SQL.');
            $table=$m[2];
            if (in_array($table,self::RETAIN)) {
                if ($table==='audit_logs' && ! str_starts_with(strtolower($m[1]),'insert')) throw new \DomainException('Audit history cannot be modified by a seeder.');
                return;
            }
            if (! in_array($table,$this->tables)) throw new \DomainException('Seeder wrote to an unsupported table.');
            $isDelete = str_starts_with(strtolower($m[1]), 'delete');
            $this->snapshot($table, $isDelete || preg_match('/\breplace\b/i', $sql));
        } finally { $this->inside=false; }
    }
    public function run(string $class,?User $actor=null): SeederRun {
        $this->guard($actor); $class=$this->canonical($class);
        if (! Schema::hasTable('seeder_runs')) throw new \DomainException('Apply the seeder tracking migration before running seeders.');
        if ($this->recording) throw new \DomainException('Use Seeder::call for nested seeders within the current tracked run.');

        // Auto-heal abandoned running runs older than 5 minutes
        SeederRun::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(5))
            ->update([
                'status' => 'failed',
                'error' => 'Seeder execution timed out or was interrupted.',
                'completed_at' => now(),
            ]);

        $execute = function() use($class,$actor) {
            $run=SeederRun::create(['seeder_class'=>$class,'status'=>'running','user_id'=>$actor?->id,'started_at'=>now()]);
            $this->before=[]; $this->keys=[]; $this->schema();
            $this->hook(DB::connection()); $this->activeConnection=DB::connection()->getName();
            try {
                // MySQL gap/row locks and a consistent snapshot exclude unrelated concurrent writes.
                if (DB::getDriverName()==='mysql' && DB::transactionLevel()===0) DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
                DB::transaction(function() use($class,$run) {
                    $this->recording=true;
                    try { $code=Artisan::call('db:seed',['--class'=>'Database\\Seeders\\'.$class,'--force'=>true]); }
                    finally { $this->recording=false; }
                    if ($code!==0) throw new \DomainException('Seeder returned a failure; its database changes were rolled back.');
                    foreach($this->before as $table=>$before) {
                        $after=$this->rows($table); $keys=$this->keyColumns($table);
                        foreach(array_unique(array_merge(array_keys($before),array_keys($after))) as $key) {
                            $old=$before[$key] ?? null; $new=$after[$key] ?? null;
                            if ($old===$new) continue;
                            $run->changes()->create(['table_name'=>$table,'operation'=>$old===null?'insert':($new===null?'delete':'update'),
                                'record_key'=>$this->rowKey($new ?? $old,$keys),'before_values'=>$old,'after_values'=>$new]);
                        }
                    }
                    $run->update(['status'=>'completed','change_count'=>$run->changes()->count(),'completed_at'=>now()]);
                });
                $this->audit($run,'completed',$actor);
                return $run->fresh();
            } catch(\Throwable $e) {
                $run->update(['status'=>'failed','completed_at'=>now(),'error'=>'Seeder failed; tracked database transaction rolled back.']);
                $this->audit($run,'failed',$actor);
                throw $e;
            } finally { $this->recording=false; $this->before=[]; app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); }
        };

        try {
            return Cache::lock('seeder-database-operation',600)->block(2, $execute);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            Cache::lock('seeder-database-operation')->forceRelease();
            return Cache::lock('seeder-database-operation',600)->block(2, $execute);
        }
    }
    private function audit(SeederRun $run,string $action,?User $actor): void {
        AuditLog::create(['user_id'=>$actor?->id,'action'=>'seeder.'.$action,'subject_type'=>SeederRun::class,'subject_id'=>$run->id,
            'to_value'=>['class'=>$run->seeder_class,'run_id'=>$run->id,'change_count'=>$run->change_count],'created_at'=>now()]);
    }
    public function preview(int $id,?User $actor=null): array {
        $this->guard($actor); $run=SeederRun::findOrFail($id);
        return ['id'=>$run->id,'class'=>$run->seeder_class,'status'=>$run->status,'changes'=>$run->change_count,
            'tables'=>$run->changes()->selectRaw('table_name, operation, COUNT(*) as count')->groupBy('table_name','operation')->get()->toArray(),
            'conflicts'=>$this->conflicts($run)];
    }
    private function conflicts(SeederRun $run): array {
        $issues=[];
        if ($run->status!=='completed') return ['Only a completed, unreverted tracked run can be reverted.'];
        $changes=$run->changes()->get();
        if ($changes->isEmpty()) return ['This run did not change any reversible records.'];
        $reversing=[];
        foreach($changes as $change) {
            if (! Schema::hasTable($change->table_name)) { $issues[]=$change->table_name.' no longer exists.'; continue; }
            $current=DB::table($change->table_name)->where($change->record_key)->lockForUpdate()->first();
            if (($current ? (array)$current : null)!==$change->after_values) $issues[]=$change->table_name.' contains a record changed after this run.';
            $reversing[$change->table_name][$this->fingerprint($change->record_key)]=$change;
        }
        $this->schema();
        foreach($changes->where('operation','insert') as $change) {
            foreach($this->foreignKeys as $child=>$keys) foreach($keys as $fk) {
                if ($fk['foreign_table']!==$change->table_name || $child==='audit_logs') continue;
                $where=[];
                foreach($fk['columns'] as $i=>$col) $where[$col]=$change->after_values[$fk['foreign_columns'][$i]];
                if (in_array(null,$where,true)) continue;
                foreach(DB::table($child)->where($where)->lockForUpdate()->get() as $dependent) {
                    $key=$this->fingerprint($this->rowKey((array)$dependent,$this->keyColumns($child)));
                    $undo=$reversing[$child][$key] ?? null;
                    $clears=$undo && ($undo->operation==='insert' || ($undo->operation==='update' && collect($where)->contains(fn($value,$column)=>($undo->before_values[$column] ?? null)!=$value)));
                    if (! $clears) $issues[]=$change->table_name.' is referenced by records in '.$child.' outside this run.';
                }
            }
            // Morph owners/pivots/notifications have no database foreign key.
            foreach($this->morphs as [$child,$typeColumn,$idColumn]) {
                $types=DB::table($child)->whereNotNull($typeColumn)->distinct()->limit(501)->pluck($typeColumn);
                if ($types->count()>500) throw new \DomainException('Too many polymorphic entity types to verify safely.');
                foreach($types as $type) {
                    $modelClass=\Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type) ?: $type;
                    if (! str_starts_with($modelClass,'App\\Models\\') && ! str_starts_with($modelClass,'Spatie\\Permission\\Models\\')) continue;
                    if (! class_exists($modelClass) || ! is_subclass_of($modelClass,\Illuminate\Database\Eloquent\Model::class)) continue;
                    $model=new $modelClass;
                    if ($model->getTable()!==$change->table_name) continue;
                    $target=$change->after_values[$model->getKeyName()] ?? null;
                    if ($target===null) continue;
                    foreach(DB::table($child)->where($typeColumn,$type)->where($idColumn,$target)->lockForUpdate()->get() as $row) {
                        $undo=$reversing[$child][$this->fingerprint($this->rowKey((array)$row,$this->keyColumns($child)))] ?? null;
                        $clears=$undo && ($undo->operation==='insert' || ($undo->operation==='update' && (($undo->before_values[$typeColumn] ?? null)!=$type || ($undo->before_values[$idColumn] ?? null)!=$target)));
                        if (! $clears) $issues[]=$change->table_name.' has related '.$child.' records outside this run.';
                    }
                }
            }
        }
        return array_values(array_unique($issues));
    }
    public function revert(int $id,?User $actor=null): SeederRun {
        $this->guard($actor);
        return Cache::lock('seeder-database-operation',600)->block(1,function() use($id,$actor) {
            return DB::transaction(function() use($id,$actor) {
                $run=SeederRun::whereKey($id)->lockForUpdate()->firstOrFail();
                $issues=$this->conflicts($run);
                if ($issues) throw new \DomainException(implode(' ',array_slice($issues,0,4)));
                $remaining=$run->changes()->orderByDesc('id')->get()->all();
                // Keep FK checks enabled. Dependency order can differ between tables/pivots.
                while($remaining) {
                    $progress=false;
                    foreach($remaining as $index=>$change) {
                        try {
                            DB::transaction(function() use($change) {
                                $q=DB::table($change->table_name)->where($change->record_key);
                                if ($change->operation==='insert') $q->delete();
                                elseif ($change->operation==='delete') DB::table($change->table_name)->insert($change->before_values);
                                else $q->update($change->before_values);
                            });
                            unset($remaining[$index]); $progress=true;
                        } catch(\Illuminate\Database\QueryException $e) {
                            if (! in_array((string)$e->getCode(),['23000','23503','19'])) throw $e;
                        }
                    }
                    if (! $progress) throw new \DomainException('Revert would break database relationships; no changes were committed.');
                }
                $run->update(['status'=>'reverted','reverted_at'=>now(),'reverted_by'=>$actor?->id]);
                $this->audit($run,'reverted',$actor); app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
                return $run->fresh();
            });
        });
    }
}
