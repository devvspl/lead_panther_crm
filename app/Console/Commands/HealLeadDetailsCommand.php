<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Support\LeadPropertyAndBudgetExtractor;

class HealLeadDetailsCommand extends Command
{
    protected $signature = 'leads:heal-meta-details {--all : Process all leads}';
    protected $description = 'Heal static default budget and property type values using actual Meta submissions and form answers.';

    public function handle(): int
    {
        $this->info('Starting lead property type and budget healing...');

        $query = Lead::withoutGlobalScopes();
        if (!$this->option('all')) {
            $query->where(function ($q) {
                $q->whereNull('property_type')
                  ->orWhere('property_type', '')
                  ->orWhere('property_type', 'Residential')
                  ->orWhere('property_type', '2 BHK')
                  ->orWhere('budget', 7500000.00)
                  ->orWhereNull('budget')
                  ->orWhere('budget', '<=', 0);
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

                $resolvedBudget = LeadPropertyAndBudgetExtractor::resolveBudget($lead);
                $resolvedPropertyType = LeadPropertyAndBudgetExtractor::resolvePropertyType($lead);

                if ($originalBudget != $resolvedBudget || $originalPropertyType !== $resolvedPropertyType) {
                    $lead->updateQuietly([
                        'budget' => $resolvedBudget,
                        'property_type' => $resolvedPropertyType,
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
