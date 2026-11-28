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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileRentLeadsFromDeveloperPanelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Precisely identifies rent leads (intent = 'rent') delivered to developer/builder
     * panels (such as Truegrowth Realty / Gulmohar Studio) despite active "Buy Leads Only"
     * rules, moves them to the Master Lead Pool (client_id = 1), and refunds credits.
     *
     * Also automatically detects and RESTORES any legitimate Buy leads that were mistakenly
     * moved to the Master Lead Pool, restoring their ownership, delivery status, and wallet balance.
     */
    public function run(): void
    {
        $this->command?->info('================================================================');
        $this->command?->info('Starting Reconciliation: Precise Rent Lead Reclaim & Buy Lead Restoration');
        $this->command?->info('================================================================');

        // 1. Identify target organizations that have Buy-Only filtering rules
        $targetOrgs = Organization::where(function ($q) {
            $q->where('name', 'like', '%Truegrowth%')
              ->orWhere('name', 'like', '%Gulmohar Studio%');
        })->get();

        if ($targetOrgs->isEmpty()) {
            $this->command?->warn('No target organizations found.');
            return;
        }

        $allTargetOrgIds = $targetOrgs->pluck('id')->all();
        $this->command?->info('Target organizations: ' . $targetOrgs->pluck('name')->implode(', '));

        $clientUserIds = User::whereIn('organization_id', $allTargetOrgIds)->pluck('id')->all();

        // 2. Default platform / admin client ID
        $superAdmin = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'super-admin']))->first();
        $defaultClientId = $superAdmin?->id ?? 1;

        // 3. Truegrowth Realty primary client user ID
        $truegrowthOrg = $targetOrgs->first(fn ($o) => str_contains($o->name, 'Truegrowth'));
        $truegrowthClient = $truegrowthOrg?->clients()->first() ?? User::where('organization_id', $truegrowthOrg?->id)->first();
        $truegrowthClientId = $truegrowthClient?->id ?? 112;

        // 4. Candidate leads:
        //    a) Leads currently belonging to target developer clients
        //    b) Leads currently in Master Lead Pool (client_id = 1) that have reconciliation_audit metadata
        $reconciledMetaLeadIds = LeadMetadata::where('key', 'reconciliation_audit')->pluck('lead_id')->all();

        $candidateLeads = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($clientUserIds, $reconciledMetaLeadIds) {
                $q->whereIn('client_id', $clientUserIds);
                if (!empty($reconciledMetaLeadIds)) {
                    $q->orWhereIn('id', $reconciledMetaLeadIds);
                }
            })
            ->with(['metadata', 'deliveryItems'])
            ->get();

        $this->command?->info("Found {$candidateLeads->count()} total candidate leads to evaluate.");

        $rentReclaimedCount = 0;
        $buyRestoredCount = 0;
        $refundedCredits = 0;
        $debitedCredits = 0;

        DB::beginTransaction();
        try {
            foreach ($candidateLeads as $lead) {
                $isRent = $this->isExplicitRentLead($lead);
                $auditMeta = $lead->metadata->firstWhere('key', 'reconciliation_audit');
                $auditData = $auditMeta ? json_decode($auditMeta->value, true) : null;
                $originalClientId = $auditData['previous_client_id'] ?? ($lead->client_id != $defaultClientId ? $lead->client_id : $truegrowthClientId);

                if ($isRent) {
                    // ==========================================
                    // ACTION: RECLAIM RENT LEAD TO MASTER POOL
                    // ==========================================
                    if ((int)$lead->client_id !== (int)$defaultClientId) {
                        $rentReclaimedCount++;
                        $prevClientId = $lead->client_id;
                        $prevClient = User::find($prevClientId);

                        $this->command?->line("------------------------------------------------------------");
                        $this->command?->warn("[RENT LEAD RECLAIMED] Lead {$lead->lead_code} (ID: {$lead->id}): '{$lead->name}', Mobile: {$lead->mobile}");
                        $this->command?->line("  Intent: RENT (Violates Buy Leads Only rule)");
                        $this->command?->line("  Moving from {$prevClient?->name} (User ID: {$prevClientId}) to Master Lead Pool (ID: {$defaultClientId})");

                        // Refund charged credits
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

                        // Revoke delivery items
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
                        }

                        // Reset assignment
                        LeadAssignment::where('lead_id', $lead->id)
                            ->whereNull('unassigned_at')
                            ->update(['unassigned_at' => now()]);

                        // Update Lead master record
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

                        LeadMetadata::updateOrCreate(
                            ['lead_id' => $lead->id, 'key' => 'reconciliation_audit'],
                            ['value' => json_encode([
                                'reconciled_at' => now()->toIso8601String(),
                                'previous_client_id' => $prevClientId,
                                'transferred_to_client_id' => $defaultClientId,
                                'reason' => 'Rent lead returned to Master Lead Pool (violates Buy Leads Only rule)',
                            ])]
                        );
                    }
                } else {
                    // ==========================================
                    // ACTION: RESTORE BUY LEAD BACK TO DEVELOPER
                    // ==========================================
                    // If this buy lead is currently in Master Lead Pool (client_id = 1), restore it!
                    if ((int)$lead->client_id === (int)$defaultClientId) {
                        $buyRestoredCount++;
                        $targetClientId = $originalClientId ?: $truegrowthClientId;
                        $targetClient = User::find($targetClientId);

                        $this->command?->line("------------------------------------------------------------");
                        $this->command?->info("[BUY LEAD RESTORED] Lead {$lead->lead_code} (ID: {$lead->id}): '{$lead->name}', Mobile: {$lead->mobile}");
                        $this->command?->line("  Intent: BUY / Valid Lead");
                        $this->command?->line("  Restoring to {$targetClient?->name} (User ID: {$targetClientId}, Org: {$targetClient?->organization?->name})");

                        // Reverse any previous reconciliation refund if one was issued
                        $refundTx = CreditTransaction::withoutGlobalScopes()
                            ->where('client_id', $targetClientId)
                            ->where('lead_id', $lead->id)
                            ->where('is_legacy', false)
                            ->where('transaction_type', 'refund')
                            ->where('description', 'like', '%Reconciliation refund%')
                            ->latest('created_at')
                            ->first();

                        if ($refundTx) {
                            $wallet = CreditWallet::firstOrCreate(['client_id' => $targetClientId], ['balance' => 0]);
                            $before = (int) $wallet->balance;
                            $cost = (int) $refundTx->credit_used;
                            $after = max(0, $before - $cost);
                            $wallet->update(['balance' => $after]);

                            // Clean up the reconciliation refund or create reversing consumption
                            $refundTx->delete();
                            $debitedCredits += $cost;
                            $this->command?->info("  Reversed reconciliation credit refund for {$targetClient?->name} (New balance: {$after})");
                        }

                        // Restore delivery items to 'sent'
                        $deliveryItems = LeadDeliveryItem::where('lead_id', $lead->id)
                            ->where('client_id', $targetClientId)
                            ->get();

                        foreach ($deliveryItems as $item) {
                            $item->update([
                                'status' => 'sent',
                                'revoked_at' => null,
                                'failure_reason' => null,
                            ]);
                        }

                        // Restore Lead Master Record to target developer
                        Lead::withoutGlobalScopes()->whereKey($lead->id)->update([
                            'client_id' => $targetClientId,
                            'delivered_at' => $lead->delivered_at ?: ($lead->created_at ?: now()),
                            'delivery_pending' => false,
                            'credit_delivery_confirmed' => true,
                            'delivery_revoked_at' => null,
                            'delivery_revoke_reason' => null,
                        ]);

                        // Remove reconciliation audit metadata so lead is clean
                        LeadMetadata::where('lead_id', $lead->id)->where('key', 'reconciliation_audit')->delete();

                        $this->command?->info("  Lead {$lead->lead_code} successfully restored to {$targetClient?->name}.");
                    }
                }
            }

            DB::commit();

            $this->command?->info('================================================================');
            $this->command?->info("Reconciliation & Restoration Complete!");
            $this->command?->info("Rent Leads Kept/Reclaimed to Master Lead Pool : {$rentReclaimedCount}");
            $this->command?->info("Buy Leads Restored to Builder/Developer Panel : {$buyRestoredCount}");
            $this->command?->info("Credits Refunded for Rent Leads               : {$refundedCredits}");
            $this->command?->info("Credits Rebalanced for Restored Buy Leads     : {$debitedCredits}");
            $this->command?->info('================================================================');

            Log::info("[ReconcileRentLeadsFromDeveloperPanelsSeeder] Reclaimed {$rentReclaimedCount} rent leads, restored {$buyRestoredCount} buy leads.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error("Reconciliation failed: " . $e->getMessage());
            Log::error("[ReconcileRentLeadsFromDeveloperPanelsSeeder] Failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Precisely determine if a lead explicitly expressed rent intent.
     */
    protected function isExplicitRentLead(Lead $lead): bool
    {
        // 1. Check meta_form_answers metadata
        $m = $lead->metadata->firstWhere('key', 'meta_form_answers');
        if ($m && !empty($m->value)) {
            $dec = json_decode($m->value, true);
            if (is_array($dec)) {
                foreach ($dec as $k => $v) {
                    $normK = preg_replace('/[^a-z0-9]/', '', strtolower((string)$k));
                    $valStr = strtolower(trim(is_array($v) ? implode(' ', $v) : (string)$v));
                    if (str_contains($normK, 'wanttobuystudio') || str_contains($normK, 'wanttobuy')) {
                        if ($valStr === 'rent' || str_contains($valStr, 'rent')) {
                            return true;
                        }
                    }
                }
            }
        }

        // 2. Check raw_json metadata
        $r = $lead->metadata->firstWhere('key', 'raw_json');
        if ($r && !empty($r->value)) {
            $raw = json_decode($r->value, true);
            if (is_array($raw) && !empty($raw['field_data'])) {
                foreach ($raw['field_data'] as $fd) {
                    $normK = preg_replace('/[^a-z0-9]/', '', strtolower($fd['name'] ?? ''));
                    if (str_contains($normK, 'wanttobuystudio') || str_contains($normK, 'wanttobuy')) {
                        $vals = $fd['values'] ?? [];
                        $valStr = strtolower(trim(is_array($vals) ? ($vals[0] ?? '') : (string)$vals));
                        if ($valStr === 'rent' || str_contains($valStr, 'rent')) {
                            return true;
                        }
                    }
                }
            }
        }

        // 3. Check individual metadata keys
        foreach ($lead->metadata as $meta) {
            $normK = preg_replace('/[^a-z0-9]/', '', strtolower((string)$meta->key));
            if (str_contains($normK, 'wanttobuystudio') || str_contains($normK, 'wanttobuy')) {
                $valStr = strtolower(trim((string)$meta->value));
                if ($valStr === 'rent' || str_contains($valStr, 'rent')) {
                    return true;
                }
            }
        }

        // 4. Check requirement text
        $req = strtolower((string)$lead->requirement);
        if (str_contains($req, 'want to buy studio: rent') || str_contains($req, 'want to buy studio__: rent')) {
            return true;
        }

        return false;
    }
}
