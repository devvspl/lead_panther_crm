<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lead = \App\Models\Lead::where('id', 1719)->orWhere('name', 'like', '%Harsh Kant Saxena%')->first();
if ($lead) {
    echo "Found ID: " . $lead->id . "\n";
    echo "Name: " . $lead->name . "\n";
    echo "Raw requirement: " . json_encode($lead->requirement) . "\n";
    $parsed = \App\Support\MetaSubmissionParser::parse($lead);
    echo "Parsed:\n";
    print_r($parsed);
} else {
    echo "Lead not found in local db\n";
    $latest = \App\Models\Lead::latest('id')->first();
    echo "Latest lead ID: " . ($latest ? $latest->id : 'none') . "\n";
}
