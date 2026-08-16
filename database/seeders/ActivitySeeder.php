<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lead;
use App\Models\Followup;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Proposal;
use App\Models\ProjectUnit;
use App\Models\User;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $salesExecs = User::role('sales-executive')->get();
        $advancedLeads = Lead::whereNotIn('status', ['new'])->get();

        foreach ($advancedLeads as $lead) {
            $agentId = $lead->assigned_to ?? $salesExecs->random()->id;

            // Followup for all contacted/advanced leads
            Followup::create([
                'lead_id' => $lead->id,
                'due_at' => now()->addDays(rand(1, 10)),
                'note' => 'Followup call regarding property preferences and budget availability.',
                'status' => rand(0, 1) ? 'completed' : 'pending',
                'created_by' => $agentId,
            ]);

            // Meeting for interested/negotiation leads
            if (in_array($lead->status, ['interested', 'site_visit', 'meeting', 'negotiation', 'closed_won'])) {
                Meeting::create([
                    'lead_id' => $lead->id,
                    'scheduled_at' => now()->subDays(rand(1, 5)),
                    'location' => $lead->city . ' Site Office',
                    'participants' => json_encode([$lead->name, 'Sales Exec']),
                    'type' => 'In-Person Meeting',
                    'notes' => 'Discussed project floor plan and booking terms.',
                    'outcome' => 'Positive',
                ]);
            }

            // Site Visit for site_visit, negotiation, closed_won leads
            if (in_array($lead->status, ['site_visit', 'negotiation', 'closed_won'])) {
                $unit = ProjectUnit::where('project_id', $lead->project_id)->first();
                SiteVisit::create([
                    'lead_id' => $lead->id,
                    'project_unit_id' => $unit->id ?? null,
                    'visit_date' => now()->subDays(rand(2, 10)),
                    'executive_id' => $agentId,
                    'attendance' => 'attended',
                    'outcome' => 'Client liked 3 BHK layout',
                    'remarks' => 'Completed site tour successfully.',
                ]);
            }

            // Proposal for negotiation, closed_won leads
            if (in_array($lead->status, ['negotiation', 'closed_won'])) {
                $unit = ProjectUnit::where('project_id', $lead->project_id)->first();
                if ($unit) {
                    Proposal::create([
                        'lead_id' => $lead->id,
                        'project_unit_id' => $unit->id,
                        'price' => $unit->price ?? 8500000.00,
                        'discount' => 200000.00,
                        'validity_date' => now()->addDays(15),
                        'terms' => 'Standard payment schedule: 10% booking, 80% construction linked, 10% possession.',
                        'sent_at' => now()->subDays(3),
                        'viewed_at' => now()->subDays(2),
                        'status' => 'accepted',
                    ]);
                }
            }
        }
    }
}
