<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$strNoNewlines = "Meta Lead Submission: • Would You Like To Schedule A Site Visit To Dholera: Yes, I Want A Site Visit • When Are You Planning To Purchase: Immediately (0-30 Days) • Suitable Time For Call ☎: 12 Noon To 4 PM • Which Type Of Plot Are You Interested In At Dholera: 488 Sq. Yard Commercial Plot ₹moneyplant: 155-600 Sq. Yard × ₹11, 500 = ₹17.83 लाख से ₹69 लाख कुल निवेश l/sq. Yard Total Investment: ₹19.5 Lakh Approx. • Meta Context: Campaign: SIR Inside Plots 1- Palacio | AdSet: SIR Inside Plots | Ad: SIR Inside Plots | Form: #3198112120381812";

$mockLead = new \App\Models\Lead();
$mockLead->requirement = $strNoNewlines;

$parsed = \App\Support\MetaSubmissionParser::parse($mockLead);
print_r($parsed);
