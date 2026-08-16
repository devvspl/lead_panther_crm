<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lead;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $leads = Lead::take(50)->get();
        $admin = User::first();

        foreach ($leads as $lead) {
            AuditLog::create([
                'user_id' => $lead->assigned_to ?? $admin->id,
                'action' => 'lead.status_updated',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'from_value' => 'new',
                'to_value' => $lead->status,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) LeadPantherCRM/1.0',
                'created_at' => $lead->created_at,
            ]);
        }
    }
}
