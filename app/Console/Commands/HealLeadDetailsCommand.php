<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Support\LeadPropertyAndBudgetExtractor;

class HealLeadDetailsCommand extends Command
{
    protected $signature = 'leads:heal-meta-details {--all : Process all leads}';
    protected $description = 'Heal static default budget, property type, location, city, and name values using actual Meta submissions and form answers.';

    public function handle(): int
    {
        $this->info('Starting lead property type, budget, location, and details healing...');

        $query = Lead::withoutGlobalScopes();
        if (!$this->option('all')) {
            $query->where(function ($q) {
                $q->whereNull('property_type')
                  ->orWhere('property_type', '')
                  ->orWhere('property_type', 'Residential')
                  ->orWhere('property_type', '2 BHK')
                  ->orWhere('property_type', 'LIKE', '%Sq. Ft.')
                  ->orWhere('property_type', 'LIKE', '%sqft')
                  ->orWhereNull('location')
                  ->orWhere('location', '')
                  ->orWhere('budget', 7500000.00)
                  ->orWhereNull('budget')
                  ->orWhere('budget', '<=', 0)
                  ->orWhere('city', 'Mumbai')
                  ->orWhereIn('name', ['Meta Lead User', 'Inbound Lead', 'Meta Lead']);
            });
        }

        $total = $query->count();
        $this->info("Found {$total} lead(s) to inspect.");

        $updatedCount = 0;
        $bar = $this->output->createProgressBar($total);

        $query->chunk(100, function ($leads) use (&$updatedCount, $bar) {
            foreach ($leads as $lead) {
                $originalBudget = $lead->budget;
                $originalPropertyType = $lead->property_type;
                $originalLocation = $lead->location;
                $originalCity = $lead->city;
                $originalName = $lead->name;

                $resolvedBudget = LeadPropertyAndBudgetExtractor::resolveBudget($lead);
                $resolvedPropertyType = LeadPropertyAndBudgetExtractor::resolvePropertyType($lead);
                $resolvedLocation = LeadPropertyAndBudgetExtractor::resolveLocation($lead);
                $resolvedCity = LeadPropertyAndBudgetExtractor::resolveCity($lead);
                $resolvedName = LeadPropertyAndBudgetExtractor::resolveName($lead);

                if ($originalBudget != $resolvedBudget 
                    || $originalPropertyType !== $resolvedPropertyType 
                    || $originalLocation !== $resolvedLocation
                    || $originalCity !== $resolvedCity
                    || $originalName !== $resolvedName) {
                    $lead->updateQuietly([
                        'budget' => $resolvedBudget,
                        'property_type' => $resolvedPropertyType,
                        'location' => $resolvedLocation,
                        'city' => $resolvedCity,
                        'name' => $resolvedName,
                    ]);
                    $updatedCount++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Successfully updated {$updatedCount} lead(s) with actual data.");

        return Command::SUCCESS;
    }
}
