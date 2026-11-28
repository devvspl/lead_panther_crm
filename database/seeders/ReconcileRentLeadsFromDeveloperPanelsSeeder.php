<?php

namespace Database\Seeders;

use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadDeliveryItem;
use App\Models\LeadFilterRule;
use App\Models\LeadMetadata;
use App\Models\Organization;
use App\Models\User;
use App\Services\LeadFilterRuleEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileRentLeadsFromDeveloperPanelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Identifies rent leads that were incorrectly delivered or assigned to developer/builder
     * panels (such as Truegrowth Realty / Gulmohar Studio) despite active "Buy Leads Only"
     * rules, moves them back to the Master Lead Pool (client_id = 1), revokes delivery items,
     * resets assignment, and refunds any deducted credits with audit ledger entries.
     */
    public function run(): void
    {
        $this->command?->info('================================================================');
        $this->command?->info('Starting Reconciliation: Reclaiming Rent Leads from Developer Panels');
        $this->command?->info('================================================================');

        // 1. Identify target organizations that have Buy-Only filtering rules
        $rules = LeadFilterRule::where('is_active', true)
            ->where(function ($q) {
                $q->where('name', 'like', '%Buy%')
                  ->orWhere('action', 'include');
            })
            ->get();

        $targetOrgIds = $rules->pluck('organization_id')->filter()->unique()->all();

        // Also explicitly ensure Truegrowth Realty and Gulmohar Studio organizations are covered
        $knownOrgs = Organization::where(function ($q) {
            $q->where('name', 'like', '%Truegrowth%')
              ->orWhere('name', 'like', '%Gulmohar Studio%');
        })->pluck('id')->all();

        $allTargetOrgIds = array_unique(array_merge($targetOrgIds, $knownOrgs));

        if (empty($allTargetOrgIds)) {
            $this->command?->warn('No target organizations with Buy-Only rules found.');
            return;
        }

        $orgs = Organization::whereIn('id', $allTargetOrgIds)->get();
        $this->command?->info('Checking organizations: ' . $orgs->pluck('name')->implode(', '));

        $clientUserIds = User::whereIn('organization_id', $allTargetOrgIds)->pluck('id')->all();

        if (empty($clientUserIds)) {
            $this->command?->warn('No client users found for target organizations.');
            return;
        }

        // 2. Platform / Master Lead Pool default client ID
        $superAdmin = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'super-admin']))->first();
        $defaultClientId = $superAdmin?->id ?? 1;

        $ruleEngine = app(LeadFilterRuleEngine::class);

        // 3. Find candidate leads belonging to these client user IDs
        $candidateLeads = Lead::withoutGlobalScopes()
            ->whereIn('client_id', $clientUserIds)
            ->with(['metadata', 'deliveryItems'])
            ->get();

        $this->command?->info("Found {$candidateLeads->count()} total leads currently associated with target developer clients.");

        $reconciledCount = 0;
        $refundedCredits = 0;

        DB::beginTransaction();
        try {
            foreach ($candidateLeads as $lead) {
                $leadOrgId = User::find($lead->client_id)?->organization_id;

                // Evaluate lead against the organization's filter rules
                $filterEval = $ruleEngine->evaluateModel($lead, $leadOrgId, $lead->campaign_id);

                // Check raw form answers for explicit rent intent
                $isRentLead = false;
                $answersMeta = $lead->metadata->firstWhere('key', 'meta_form_answers');
                $rawMeta = $lead->metadata->firstWhere('key', 'raw_json');

                if ($answersMeta && !empty($answersMeta->value)) {
                    $decoded = json_decode($answersMeta->value, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $k => $v) {
                            $valStr = strtolower(is_array($v) ? implode(' ', $v) : (string)$v);
                            if (str_contains(strtolower($k), 'want_to_buy') && str_contains($valStr, 'rent')) {
                                $isRentLead = true;
                                break;
                            }
                        }
                    }
                }

                if (!$isRentLead && $rawMeta && !empty($rawMeta->value)) {
                    $rawLower = strtolower($rawMeta->value);
                    if (str_contains($rawLower, 'want_to_buy_studio') && str_contains($rawLower, 'rent')) {
                        $isRentLead = true;
                    }
                }

                // If rule evaluation failed (e.g. failed "Buy Leads Only") or is explicitly rent
                if (!$filterEval->passed || $isRentLead) {
                    $reconciledCount++;
                    $prevClientId = $lead->client_id;
                    $prevClient = User::find($prevClientId);

                    $this->command?->line("------------------------------------------------------------");
                    $this->command?->warn("Reconciling Lead {$lead->lead_code} (ID: {$lead->id}): '{$lead->name}', Mobile: {$lead->mobile}");
                    $this->command?->line("  Reason: Failed filter rule '{$filterEval->matchedRule?->name}' (Intent: Rent)");
                    $this->command?->line("  Current Owner: {$prevClient?->name} (User ID: {$prevClientId}, Org: {$prevClient?->organization?->name})");

                    // A. Check and refund any charged credits
                    $chargedTx = CreditTransaction::withoutGlobalScopes()
                        ->where('client_id', $prevClientId)
                        ->where('lead_id', $lead->id)
                        ->where('is_legacy', false)
                        ->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve'])
                        ->first();

                    if ($chargedTx) {
                        $cost = (int) $chargedTx->credit_used;
                        $alreadyRefunded = CreditTransaction::withoutGlobalScopes()
                            ->where('client_id', $prevClientId)
                            ->where('lead_id', $lead->id)
                            ->where('is_legacy', false)
                            ->where('transaction_type', 'refund')
                            ->exists();

                        if (!$alreadyRefunded && $cost > 0) {
                            $wallet = CreditWallet::firstOrCreate(['client_id' => $prevClientId], ['balance' => 0]);
                            $before = (int) $wallet->balance;
                            $after = $before + $cost;
                            $wallet->update(['balance' => $after]);

                            CreditTransaction::create([
                                'client_id' => $prevClientId,
                                'lead_id' => $lead->id,
                                'package_id' => null,
                                'credit_before' => $before,
                                'credit_used' => $cost,
                                'credit_after' => $after,
                                'transaction_type' => 'refund',
                                'description' => "Reconciliation refund for Lead {$lead->lead_code}: returned to Master Lead Pool (Rent lead violation of Buy Leads Only rule)",
                                'is_legacy' => false,
                                'created_by' => $defaultClientId,
                                'created_at' => now(),
                            ]);

                            $refundedCredits += $cost;
                            $this->command?->info("  Refunded {$cost} credits to {$prevClient?->name} wallet (New balance: {$after})");
                        }
                    }

                    // B. Revoke any delivery items for this client
                    $deliveryItems = LeadDeliveryItem::where('lead_id', $lead->id)
                        ->whereIn('client_id', $clientUserIds)
                        ->get();

                    foreach ($deliveryItems as $item) {
                        $updateData = [
                            'status' => 'revoked',
                            'revoked_at' => now(),
                            'failure_reason' => 'Reconciled: Rent lead returned to Master Lead Pool (violates Buy Leads Only rule)',
                        ];
                        if (\Illuminate\Support\Facades\Schema::hasColumn('lead_delivery_items', 'revocation_reason')) {
                            $updateData['revocation_reason'] = 'Reconciled: Rent lead returned to Master Lead Pool (violates Buy Leads Only rule)';
                        }
                        $item->update($updateData);
                        $this->command?->line("  Revoked delivery item #{$item->id}");
                    }

                    // C. Reset lead assignment
                    LeadAssignment::where('lead_id', $lead->id)
                        ->whereNull('unassigned_at')
                        ->update(['unassigned_at' => now()]);

                    // D. Update Lead Master Record back to Master Lead Pool
                    Lead::withoutGlobalScopes()->whereKey($lead->id)->update([
                        'client_id' => $defaultClientId,
                        'delivered_at' => null,
                        'delivery_pending' => false,
                        'credit_delivery_confirmed' => false,
                        'assigned_to' => null,
                        'executive_assigned_at' => null,
                        'executive_response_due_at' => null,
                        'executive_first_response_at' => null,
                        'delivery_revoked_at' => now(),
                        'delivery_revoke_reason' => 'Reconciled: Rent lead returned to Master Lead Pool (violates Buy Leads Only rule)',
                    ]);

                    // Record an audit entry in metadata
                    LeadMetadata::create([
                        'lead_id' => $lead->id,
                        'key' => 'reconciliation_audit',
                        'value' => json_encode([
                            'reconciled_at' => now()->toIso8601String(),
                            'previous_client_id' => $prevClientId,
                            'transferred_to_client_id' => $defaultClientId,
                            'reason' => 'Rent lead returned to Master Lead Pool (violates Buy Leads Only rule)',
                        ]),
                    ]);

                    $this->command?->info("  Lead {$lead->lead_code} successfully returned to Master Lead Pool (client_id = {$defaultClientId}).");
                }
            }

            DB::commit();

            $this->command?->info('================================================================');
            $this->command?->info("Reconciliation Complete!");
            $this->command?->info("Total leads reconciled: {$reconciledCount}");
            $this->command?->info("Total credits refunded: {$refundedCredits}");
            $this->command?->info('================================================================');

            Log::info("[ReconcileRentLeadsFromDeveloperPanelsSeeder] Reconciled {$reconciledCount} leads back to Master Lead Pool, refunded {$refundedCredits} credits.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error("Reconciliation failed: " . $e->getMessage());
            Log::error("[ReconcileRentLeadsFromDeveloperPanelsSeeder] Failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}
