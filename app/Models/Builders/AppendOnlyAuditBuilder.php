<?php
namespace App\Models\Builders;

class AppendOnlyAuditBuilder extends \Illuminate\Database\Eloquent\Builder
{
    public function update(array $values) { throw new \LogicException('Audit records are append-only.'); }
    public function delete() { throw new \LogicException('Audit records are append-only.'); }
    public function forceDelete() { throw new \LogicException('Audit records are append-only.'); }
    public function upsert(array $values, $uniqueBy, $update = null) { throw new \LogicException('Audit records are append-only.'); }
    public function increment($column, $amount = 1, array $extra = []) { throw new \LogicException('Audit records are append-only.'); }
    public function decrement($column, $amount = 1, array $extra = []) { throw new \LogicException('Audit records are append-only.'); }
    public function truncate() { throw new \LogicException('Audit records are append-only.'); }
    public function incrementEach(array $columns, array $extra = []) { throw new \LogicException('Audit records are append-only.'); }
    public function decrementEach(array $columns, array $extra = []) { throw new \LogicException('Audit records are append-only.'); }
}
