<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $reasons = [
            ['label' => 'Invalid Mobile Number', 'is_eligible' => 1, 'requires_sla_check' => 1],
            ['label' => 'Wrong Contact Details', 'is_eligible' => 1, 'requires_sla_check' => 1],
            ['label' => 'Duplicate Lead', 'is_eligible' => 1, 'requires_sla_check' => 1],
            ['label' => 'Fake / Incorrect Enquiry', 'is_eligible' => 1, 'requires_sla_check' => 1],
            ['label' => 'Unreachable (Policy Check)', 'is_eligible' => 1, 'requires_sla_check' => 1],
            ['label' => 'Out of Budget', 'is_eligible' => 0, 'requires_sla_check' => 0],
            ['label' => 'Other / Unsuitable Lead', 'is_eligible' => 1, 'requires_sla_check' => 0],
        ];

        foreach ($reasons as $r) {
            DB::table('replacement_reasons')->updateOrInsert(
                ['label' => $r['label']],
                [
                    'is_eligible' => $r['is_eligible'],
                    'requires_sla_check' => $r['requires_sla_check'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
    }
};
