<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$l = \App\Models\Lead::with(['client.organization', 'campaign.project'])->find(1719);
if (!$l) {
    echo "Lead 1719 not found\n";
    exit;
}
echo "Lead ID: {$l->id}\n";
echo "Lead Code: {$l->lead_code}\n";
echo "Client ID: " . ($l->client_id ?? 'NULL') . "\n";
echo "Client Name: " . ($l->client?->name ?? 'NULL') . "\n";
echo "Client Org ID: " . ($l->client?->organization_id ?? 'NULL') . "\n";
echo "Client Org Name: " . ($l->client?->organization?->name ?? 'NULL') . "\n";
echo "Campaign: " . ($l->campaign?->name ?? 'NULL') . "\n";
echo "Project: " . ($l->project?->name ?? 'NULL') . "\n";
echo "Assigned To: " . ($l->assigned_to ?? 'NULL') . "\n";

echo "\n--- ALL ORGANIZATIONS ---\n";
foreach (\App\Models\Organization::all() as $org) {
    echo "Org {$org->id}: {$org->name}\n";
}
