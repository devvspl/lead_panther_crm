<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReplacementReason;
use App\Models\Lead;
use App\Models\LeadReplacement;
use App\Models\User;

class ReplacementSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['label' => 'Invalid Phone Number', 'is_eligible' => true, 'requires_sla_check' => true],
            ['label' => 'Out of Budget', 'is_eligible' => true, 'requires_sla_check' => false],
            ['label' => 'Duplicate Lead', 'is_eligible' => true, 'requires_sla_check' => true],
            ['label' => 'Wrong Location Preference', 'is_eligible' => false, 'requires_sla_check' => false],
        ];

        $reasonModels = [];
        foreach ($reasons as $reason) {
            $reasonModels[] = ReplacementReason::firstOrCreate(['label' => $reason['label']], $reason);
        }

        $replacedLeads = Lead::where('status', 'replaced')->take(10)->get();
        $agents = User::role('sales-executive')->get();

        foreach ($replacedLeads as $index => $lead) {
            $status = ($index % 2 === 0) ? 'approved' : 'rejected';
            LeadReplacement::create([
                'lead_id' => $lead->id,
                'reason_id' => $reasonModels[array_rand($reasonModels)]->id,
                'requested_by' => $lead->assigned_to ?? $agents->random()->id,
                'requested_at' => $lead->created_at->addHours(2),
                'sla_met' => true,
                'status' => $status,
                'resolved_at' => $lead->created_at->addHours(6),
                'replacement_lead_id' => null,
            ]);
        }
    }
}
