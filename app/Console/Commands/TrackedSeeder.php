<?php
namespace App\Console\Commands;
use App\Services\SeederRunService;
class TrackedSeeder extends \Illuminate\Console\Command {
    protected $signature='db:seed-tracked {--class=DatabaseSeeder} {--revert=} {--preview=}';
    protected $description='Run a seeder with a reversible database manifest, or preview/revert a tracked run';
    public function handle(SeederRunService $service): int {
        try {
            if ($id=$this->option('preview')) { $this->line(json_encode($service->preview((int)$id),JSON_PRETTY_PRINT)); return self::SUCCESS; }
            if ($id=$this->option('revert')) { $service->revert((int)$id); $this->info('Seeder run reverted. Audit history retained.'); return self::SUCCESS; }
            $run=$service->run($this->option('class')); $this->info("Run #{$run->id}: {$run->seeder_class}; {$run->change_count} reversible changes recorded.");
            return self::SUCCESS;
        } catch(\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
