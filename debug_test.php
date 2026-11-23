<?php
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

\ = App\Models\Organization::create(['name' => 'Org One', 'type' => 'builder']);
\ = App\Models\User::factory()->create(['organization_id' => \->id]);
\->assignRole('Builder/Developer');
\ = App\Models\LeadSource::firstOrCreate(['name' => 'Org Source']);
\ = App\Models\Lead::factory()->create([
    'client_id' => \->id,
    'lead_source_id' => \->id,
    'delivery_pending' => false,
    'status' => 'new',
    'current_stage' => 'new',
    'created_at' => now(),
]);

auth()->login(\);
\ = Livewire\Livewire::actingAs(\)->test(App\Livewire\Leads\LeadKanban::class);
\ = \->get('analyticsData');
echo 'TOTAL LEADS: ' . \['kpis']['total'] . PHP_EOL;
echo 'DIRECT QUERY: ' . App\Models\Lead::count() . PHP_EOL;
echo 'PANEL QUERY: ' . App\Models\Lead::panelQuery()->count() . PHP_EOL;
echo 'PANEL SQL: ' . App\Models\Lead::panelQuery()->toSql() . PHP_EOL;
echo 'PANEL BINDINGS: ' . json_encode(App\Models\Lead::panelQuery()->getBindings()) . PHP_EOL;
