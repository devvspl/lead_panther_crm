<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadDeliveryItem;
use App\Models\LeadMetadata;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileRentLeadsFromDeveloperPanelsSeeder extends Seeder
{
    /**
     * Official Meta Lead IDs exported directly from Meta Ads for the Studio Campaign:
     * - EXACTLY 15 Buy Leads
     * - EXACTLY 17 Rent Leads
     */
    public const META_BUY_LEAD_IDS = [
        '1792946815340165', // parmannd gaud (+18076776642)
        '2431330290730477', // 𝕸𝖊𝖊𝖙 𝕭𝖆𝖎𝖘𝖑𝖆 (+919548810007)
        '2704859793289128', // S A Rizvi (+919419003835)
        '4225391184262318', // pankaj verma (+917351198894)
        '2515022295570132', // IvorEgbert (+919810980774)
        '1751184556349028', // Bobby Verma (+919897833367)
        '4167815900022565', // 𝐑𝐎𝐇𝐀𝐍 (+916205481173)
        '4824423581120963', // Shailender Singh (+919456272980)
        '1171824265379756', // Rajeev Srivastava (+919935637538)
        '1614316630735420', // Jayanta Moitra (+919810032481)
        '1825110525207900', // Arvind Kushwaha (+919560740048)
        '2404069763664307', // Arun Kumar Rana (+918941049274)
        '1197179182662858', // Bachan Bisht (+919410393935)
        '1367388575366608', // Nandu Rana (+918825047826)
        '2405215573516205', // Amala Mary G (+919443626502)
    ];

    public const META_RENT_LEAD_IDS = [
        '1850113739520768',  // shaleen (+919516908083)
        '1007547355746646',  // Aparna (+917838857933)
        '28973044469063912', // Vikas (+918571089707)
        '1615048679891342',  // Manish (+918580741116)
        '1492554516142457',  // Nitesh Pratap Singh (+917974044299)
        '1661071275698755',  // MUKUL SEN (+916294110078)
        '1158514093768503',  // Anil dhaakad (+918085405256)
        '1903723190797454',  // Dr sunil Kumar chaudhary (+918969761969)
        '4694797637514660',  // Dr. Ayushi Sharma (+919204766255)
        '1869125570779285',  // Parmod Sehgal (+918130894622)
        '1545977224031903',  // Anwar (+917983014868)
        '1870922554572180',  // Amit Jha (+918130297005)
        '1439161037923066',  // یاور عباس وانی (+917006905866)
        '1443179321218085',  // sohan singh (+919829257843)
        '1656131196540959',  // Tushar Pundir (+919711104737)
        '1407045808171745',  // Sachin Bej (+919883678419)
        '2395326834629175',  // Shaili Gupta (+918802608428)
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command?->info('================================================================');
        $this->command?->info('Reconciliation: Reconciling Studio Meta Leads via Official Meta Dataset');
        $this->command?->info('================================================================');

        // 1. Identify Truegrowth Realty organization and client
        $targetOrg = Organization::where('name', 'like', '%Truegrowth Realty%')->first()
            ?? Organization::where('name', 'like', '%Truegrowth%')->first();
        if (!$targetOrg) {
            $this->command?->warn('Truegrowth Realty organization not found.');
            return;
        }

        $targetClient = $targetOrg->clients()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder']))
            ->first() ?? $targetOrg->clients()->first() ?? User::where('organization_id', $targetOrg->id)->first();

        if (!$targetClient) {
            $this->command?->warn('Target client user not found for Truegrowth Realty.');
            return;
        }

        $truegrowthClientId = $targetClient->id;
        $clientUserIds = User::where('organization_id', $targetOrg->id)->pluck('id')->all();

        $this->command?->info("Target Organization : {$targetOrg->name} (ID: {$targetOrg->id})");
        $this->command?->info("Target Client User   : {$targetClient->name} (ID: {$truegrowthClientId})");

        // Identify source organization (Gulmohar Studio 135)
        $sourceOrg = Organization::where('name', 'like', '%Gulmohar%')->where('name', 'like', '%135%')->first()
            ?? Organization::where('name', 'like', '%Gulmohar%')->first();
        $sourceClient = $sourceOrg?->clients()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder']))
            ->first() ?? $sourceOrg?->clients()->first();
        $sourceClientId = $sourceClient?->id ?? 110;

        // Locate target campaign and project
        $targetProject = Project::withoutGlobalScopes()
            ->where('client_id', $truegrowthClientId)
            ->where('name', 'like', '%Studio%')
            ->first() ?? Project::withoutGlobalScopes()->where('name', 'like', '%Studio%')->first();

        $targetCampaign = Campaign::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('name', 'like', '%Studio Noida Exp 50%')
                  ->orWhere('name', 'like', '%Studio%');
            })
            ->latest('id')
            ->first();

        $targetCampaignId = $targetCampaign?->id;
        $targetProjectId = $targetProject?->id ?? ($targetCampaign?->project_id);

        $superAdmin = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'super-admin']))->first();
        $defaultClientId = $superAdmin?->id ?? 1;

        $wallet = CreditWallet::firstOrCreate(['client_id' => $truegrowthClientId], ['balance' => 0]);

        DB::beginTransaction();
        try {
            // ========================================================================
            // STEP 1: Process RENT Leads (Keep in Master Lead Pool)
            // ========================================================================
            $this->command?->info("\n--- Processing RENT Leads (Enforcing Master Lead Pool) ---");
            $rentProcessed = 0;
            $rentRefunded = 0;

            // Find all rent leads: known rent Meta IDs OR explicit rent intent on target developer
            $rentCandidateLeadIds = LeadMetadata::where('key', 'meta_leadgen_id')
                ->whereIn('value', self::META_RENT_LEAD_IDS)
                ->pluck('lead_id')
                ->all();

            $leadsToReclaim = Lead::withoutGlobalScopes()
                ->where(function ($q) use ($clientUserIds, $rentCandidateLeadIds) {
                    if (!empty($rentCandidateLeadIds)) {
                        $q->whereIn('id', $rentCandidateLeadIds);
                    }
                    $q->orWhereIn('client_id', $clientUserIds);
                })
                ->with(['metadata'])
                ->get()
                ->filter(function ($lead) {
                    $metaId = $lead->metadata->firstWhere('key', 'meta_leadgen_id')?->value;
                    return in_array($metaId, self::META_RENT_LEAD_IDS) || $this->isExplicitRentLead($lead);
                });

            foreach ($leadsToReclaim as $lead) {
                $rentProcessed++;

                // Move lead to Master Lead Pool (client_id = 1)
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
                    'delivery_revoke_reason' => 'Reconciled: Rent lead retained in Master Lead Pool (violates Buy Leads Only rule)',
                ]);

                // Reset assignment
                LeadAssignment::where('lead_id', $lead->id)->whereNull('unassigned_at')->update(['unassigned_at' => now()]);

                // Revoke delivery items for Truegrowth
                LeadDeliveryItem::where('lead_id', $lead->id)->whereIn('client_id', $clientUserIds)->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'failure_reason' => 'Reconciled: Rent lead retained in Master Lead Pool',
                ]);

                // Refund credits if previously charged and not yet refunded
                $chargedTx = CreditTransaction::withoutGlobalScopes()
                    ->where('client_id', $truegrowthClientId)
                    ->where('lead_id', $lead->id)
                    ->where('is_legacy', false)
                    ->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve'])
                    ->first();

                if ($chargedTx) {
                    $cost = (int) $chargedTx->credit_used;
                    $alreadyRefunded = CreditTransaction::withoutGlobalScopes()
                        ->where('client_id', $truegrowthClientId)
                        ->where('lead_id', $lead->id)
                        ->where('is_legacy', false)
                        ->where('transaction_type', 'refund')
                        ->exists();

                    if (!$alreadyRefunded && $cost > 0) {
                        $before = (int) $wallet->balance;
                        $after = $before + $cost;
                        $wallet->update(['balance' => $after]);

                        CreditTransaction::create([
                            'client_id' => $truegrowthClientId,
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

                        $rentRefunded += $cost;
                    }
                }

                $this->command?->line("  [RENT] Lead {$lead->lead_code} ({$lead->name}) kept in Master Pool (client_id = {$defaultClientId})");
            }

            // ========================================================================
            // STEP 2: Process the 15 Explicit BUY Leads (Deliver to Truegrowth Realty)
            // ========================================================================
            $this->command?->info("\n--- Processing 15 BUY Leads (Assigning to Truegrowth Realty) ---");
            $buyProcessed = 0;
            $buyDebited = 0;

            foreach (self::META_BUY_LEAD_IDS as $buyMetaId) {
                $leadMeta = LeadMetadata::where('key', 'meta_leadgen_id')->where('value', $buyMetaId)->first();
                $lead = $leadMeta ? Lead::withoutGlobalScopes()->find($leadMeta->lead_id) : null;

                if (!$lead) {
                    continue;
                }

                $buyProcessed++;

                // Ensure lead belongs to Truegrowth Realty
                Lead::withoutGlobalScopes()->whereKey($lead->id)->update([
                    'client_id' => $truegrowthClientId,
                    'campaign_id' => $targetCampaignId ?: $lead->campaign_id,
                    'delivered_at' => $lead->delivered_at ?: ($lead->created_at ?: now()),
                    'delivery_pending' => false,
                    'credit_delivery_confirmed' => true,
                    'do_not_send_to_client' => false,
                    'delivery_revoked_at' => null,
                    'delivery_revoke_reason' => null,
                ]);

                // Ensure Delivery Item exists with status = 'sent' for Truegrowth Realty
                $deliveryItem = LeadDeliveryItem::firstOrNew(['lead_id' => $lead->id, 'client_id' => $truegrowthClientId]);
                $deliveryItem->plan_id = $deliveryItem->plan_id ?? 1;
                $deliveryItem->source_client_id = $deliveryItem->source_client_id ?? $defaultClientId;
                $deliveryItem->source_campaign_id = $deliveryItem->source_campaign_id ?? $lead->campaign_id;
                $deliveryItem->project_id = $targetProjectId ?? $lead->campaign?->project_id;
                $deliveryItem->campaign_id = $targetCampaignId ?: $lead->campaign_id;
                $deliveryItem->requested_by = $deliveryItem->requested_by ?? $defaultClientId;
                $deliveryItem->status = 'sent';
                $deliveryItem->sent_at = $deliveryItem->sent_at ?: ($lead->created_at ?: now());
                $deliveryItem->revoked_at = null;
                $deliveryItem->failure_reason = null;
                $deliveryItem->save();

                // Reverse any mistaken reconciliation refund if one was issued by the earlier seeder run
                $refundTx = CreditTransaction::withoutGlobalScopes()
                    ->where('client_id', $truegrowthClientId)
                    ->where('lead_id', $lead->id)
                    ->where('is_legacy', false)
                    ->where('transaction_type', 'refund')
                    ->where('description', 'like', '%Reconciliation refund%')
                    ->first();

                if ($refundTx) {
                    $cost = (int) $refundTx->credit_used;
                    $before = (int) $wallet->balance;
                    $after = max(0, $before - $cost);
                    $wallet->update(['balance' => $after]);

                    $refundTx->delete();
                    $buyDebited += $cost;
                }

                // Delete reconciliation audit if present
                LeadMetadata::where('lead_id', $lead->id)->where('key', 'reconciliation_audit')->delete();

                $this->command?->info("  [BUY RESTORED] Lead {$lead->lead_code} ({$lead->name}) -> Truegrowth Realty (client_id = {$truegrowthClientId})");
            }

            // ========================================================================
            // STEP 3: Revert Historical / Transferred Leads Back to Source (Gulmohar)
            // Only the official 15 Meta Buy Leads belong to Truegrowth Realty panel
            // ========================================================================
            $this->command?->info("\n--- Reverting Non-Meta Leads back to Source Organization ---");
            $revertedCount = 0;

            $metaBuyLeadIds = LeadMetadata::where('key', 'meta_leadgen_id')
                ->whereIn('value', self::META_BUY_LEAD_IDS)
                ->pluck('lead_id')
                ->all();

            $nonMetaLeadsOnTruegrowth = Lead::withoutGlobalScopes()
                ->where(function ($q) use ($clientUserIds) {
                    $q->whereIn('client_id', $clientUserIds)
                      ->orWhereHas('deliveryItems', fn ($di) => $di->whereIn('client_id', $clientUserIds)->whereIn('status', ['sent', 'accepted']));
                })
                ->whereNotIn('id', $metaBuyLeadIds)
                ->with(['metadata', 'deliveryItems'])
                ->get();

            foreach ($nonMetaLeadsOnTruegrowth as $lead) {
                $origClientId = (int) ($lead->metadata->firstWhere('key', 'transferred_from_client_id')?->value ?: $sourceClientId);
                $origCampaignId = (int) ($lead->metadata->firstWhere('key', 'transferred_from_campaign_id')?->value ?: $lead->campaign_id);

                Lead::withoutGlobalScopes()->whereKey($lead->id)->update([
                    'client_id' => $origClientId,
                    'campaign_id' => $origCampaignId,
                    'assigned_to' => null,
                ]);

                // Revoke delivery items for Truegrowth
                LeadDeliveryItem::where('lead_id', $lead->id)->whereIn('client_id', $clientUserIds)->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'failure_reason' => 'Reverted to source organization: only 15 Meta Buy leads belong to Truegrowth Realty',
                ]);

                LeadMetadata::where('lead_id', $lead->id)->whereIn('key', [
                    'transferred_from_organization',
                    'transferred_to_organization',
                    'transferred_from_client_id',
                    'transferred_from_campaign_id',
                    'transferred_at',
                    'reconciliation_audit',
                ])->delete();

                $revertedCount++;
                $this->command?->line("  [REVERTED] Lead {$lead->lead_code} ({$lead->name}) -> returned to Client ID {$origClientId}");
            }

            DB::commit();

            $this->command?->info('================================================================');
            $this->command?->info("RECONCILIATION COMPLETE:");
            $this->command?->info("Rent Leads Kept in Master Lead Pool (client_id = 1) : {$rentProcessed}");
            $this->command?->info("Buy Leads Delivered to Truegrowth Realty (Panel)   : {$buyProcessed}");
            $this->command?->info("Non-Meta Leads Reverted to Source (Gulmohar)       : {$revertedCount}");
            $this->command?->info("Credits Refunded for Rent Leads                    : {$rentRefunded}");
            $this->command?->info("Credits Rebalanced for Buy Leads                   : {$buyDebited}");
            $this->command?->info('================================================================');

            Log::info("[ReconcileRentLeadsFromDeveloperPanelsSeeder] Finished. {$rentProcessed} rent in Pool, {$buyProcessed} buy in Truegrowth, {$revertedCount} reverted to source.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error("Reconciliation failed: " . $e->getMessage());
            Log::error("[ReconcileRentLeadsFromDeveloperPanelsSeeder] Failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Check if a lead has explicit rent intent.
     */
    protected function isExplicitRentLead(Lead $lead): bool
    {
        $m = $lead->metadata->firstWhere('key', 'meta_form_answers');
        if ($m && !empty($m->value)) {
            $dec = json_decode($m->value, true);
            if (is_array($dec)) {
                foreach ($dec as $k => $v) {
                    $normK = preg_replace('/[^a-z0-9]/', '', strtolower((string)$k));
                    $valStr = strtolower(trim(is_array($v) ? implode(' ', $v) : (string)$v));
                    if (str_contains($normK, 'wanttobuystudio') || str_contains($normK, 'wanttobuy')) {
                        if ($valStr === 'rent' || str_contains($valStr, 'rent')) return true;
                    }
                }
            }
        }

        $r = $lead->metadata->firstWhere('key', 'raw_json');
        if ($r && !empty($r->value)) {
            $raw = json_decode($r->value, true);
            if (is_array($raw) && !empty($raw['field_data'])) {
                foreach ($raw['field_data'] as $fd) {
                    $normK = preg_replace('/[^a-z0-9]/', '', strtolower($fd['name'] ?? ''));
                    if (str_contains($normK, 'wanttobuystudio') || str_contains($normK, 'wanttobuy')) {
                        $vals = $fd['values'] ?? [];
                        $valStr = strtolower(trim(is_array($vals) ? ($vals[0] ?? '') : (string)$vals));
                        if ($valStr === 'rent' || str_contains($valStr, 'rent')) return true;
                    }
                }
            }
        }

        $req = strtolower((string)$lead->requirement);
        if (str_contains($req, 'want to buy studio: rent') || str_contains($req, 'want to buy studio__: rent')) {
            return true;
        }

        return false;
    }
}
