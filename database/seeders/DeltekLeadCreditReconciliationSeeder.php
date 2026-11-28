<?php

namespace Database\Seeders;

use App\Models\{AuditLog, CreditTransaction, CreditWallet, Organization, User};
use App\Services\LeadCreditService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Opt-in, one-time allocation reconciliation; never part of DatabaseSeeder. */
class DeltekLeadCreditReconciliationSeeder extends Seeder
{
    public const ALLOCATION = 1000;
    public const HISTORICAL_LEAD_COST = 1;
    public const AUDIT_ACTION = 'lead_credits.deltek_1000_reconciled';

    public function run(): void
    {
        $actor = auth()->user();
        if ($actor) {
            abort_unless($actor->status === 'active' && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
        } else {
            abort_unless(app()->runningInConsole(), 403);
            $actor = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'super-admin']))
                ->orderBy('id')->firstOrFail();
        }

        $summary = DB::transaction(function () use ($actor) {
            $organizations = Organization::whereRaw('LOWER(name) = ?', ['deltek'])->lockForUpdate()->get();
            if ($organizations->count() !== 1) throw new \RuntimeException('Exactly one Deltek organization is required; no credits were changed.');
            $organization = $organizations->sole();
            // Identify the actual billing owner from existing records, never by a hardcoded ID/email.
            $owners = User::where('organization_id', $organization->id)->where(function ($q) {
                $q->whereIn('id', DB::table('client_wallets')->select('client_id'))
                    ->orWhereIn('id', DB::table('projects')->whereNotNull('client_id')->select('client_id'))
                    ->orWhereIn('id', DB::table('leads')->whereNotNull('client_id')->select('client_id'));
            })->lockForUpdate()->get();
            if ($owners->count() !== 1) throw new \RuntimeException('Deltek must have exactly one identifiable billing owner; review its wallets/projects/lead owners first.');
            $client = $owners->sole();
            $completed = AuditLog::where('action', self::AUDIT_ACTION)->where('subject_type', CreditWallet::class)
                ->where('organization_id', $organization->id)->exists();
            if ($completed) {
                $wallet = CreditWallet::withoutGlobalScopes()->where('client_id', $client->id)->firstOrFail();
                return ['already_applied' => true, 'client_id' => $client->id, 'remaining' => $wallet->balance];
            }

            $wallet = CreditWallet::withoutGlobalScopes()->where('client_id', $client->id)->lockForUpdate()->first();
            $before = (int) ($wallet?->balance ?? 0);
            $transactions = CreditTransaction::withoutGlobalScopes()->where('client_id', $client->id);
            $active = (clone $transactions)->where('is_legacy', false)->whereNotNull('lead_id');
            $used = max(0, (int) (clone $active)->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve'])->sum('credit_used')
                - (int) (clone $active)->where('transaction_type', 'refund')->sum('credit_used'));
            $charged = (clone $active)->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve'])->pluck('lead_id')->all();
            // Do not back-charge a lead that already has a recorded refund, including legacy returns.
            $refunded = (clone $transactions)->where('transaction_type', 'refund')->whereNotNull('lead_id')->pluck('lead_id')->all();
            // Historical delivery evidence includes archived leads. Archiving does not refund credits.
            // Raw reads are deliberate: no lead/assignment mutation or observer is triggered here.
            $delivered = DB::table('leads')->where('client_id', $client->id)->whereNotNull('delivered_at')
                ->where('delivery_pending', false)->where('internal_processing_pending', false)
                ->whereNotIn('status', ['duplicate', 'rejected', 'invalid'])
                ->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid'])
                ->orderBy('id')->lockForUpdate()->get(['id', 'lead_code']);
            $missing = $delivered->whereNotIn('id', array_unique(array_merge($charged, $refunded)))->values();
            $newConsumption = $missing->count() * self::HISTORICAL_LEAD_COST;
            if ($used + $newConsumption > self::ALLOCATION) {
                throw new \RuntimeException('Delivered-lead consumption exceeds the 1,000-credit allocation. No changes were applied; review the allocation first.');
            }

            $service = app(LeadCreditService::class);
            $reconciliation = $service->reconcile($client, self::ALLOCATION - $used, $before,
                'Deltek: replace the legacy/dummy allocation with 1,000 lead credits; retain already recorded credit consumption.', $actor);

            $firstDelivered = $delivered->first();
            $earliestDelivery = $firstDelivered && !empty($firstDelivered->delivered_at)
                ? \Carbon\Carbon::parse($firstDelivered->delivered_at)->subHours(1)
                : \Carbon\Carbon::parse('2026-11-18 07:00:00');
            $reconciliation->update(['created_at' => $earliestDelivery]);

            foreach ($missing as $lead) {
                $legacyId = (clone $transactions)->where('is_legacy', true)->where('lead_id', $lead->id)
                    ->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve'])->orderBy('id')->value('id');
                $entry = $service->adjust($client, self::HISTORICAL_LEAD_COST, 'deduct',
                    'Deltek allocation reconciliation: delivered lead '.$lead->lead_code.($legacyId ? '; legacy entry #'.$legacyId : '; historical delivery evidence'), $actor);
                
                $leadDeliveredAt = !empty($lead->delivered_at) ? $lead->delivered_at : (!empty($lead->created_at) ? $lead->created_at : now());
                $entry->update([
                    'transaction_type' => 'lead_consumption',
                    'lead_id' => $lead->id,
                    'delivery_charge_lead_id' => $lead->id,
                    'created_at' => $leadDeliveredAt,
                ]);
            }
            $wallet = CreditWallet::withoutGlobalScopes()->where('client_id', $client->id)->firstOrFail();
            $summary = ['allocation' => self::ALLOCATION, 'client_id' => $client->id, 'delivered_leads' => $delivered->count(),
                'previously_used' => $used, 'backfilled_leads' => $missing->count(), 'historical_cost_per_lead' => self::HISTORICAL_LEAD_COST,
                'new_consumption' => $newConsumption, 'used' => $wallet->used_credits, 'remaining' => $wallet->balance,
                'reconciliation_transaction_id' => $reconciliation->id];
            AuditLog::create(['user_id' => $actor->id, 'organization_id' => $organization->id, 'action' => self::AUDIT_ACTION,
                'subject_type' => CreditWallet::class, 'subject_id' => $wallet->id,
                'from_value' => ['remaining' => $before], 'to_value' => $summary,
                'context' => ['category' => 'lead_credits', 'status' => 'success', 'seeder' => static::class], 'created_at' => now()]);
            return $summary + ['already_applied' => false];
        }, 3);

        if ($summary['already_applied']) {
            $this->command?->info('Deltek reconciliation was already applied. No additional grant/deduction. Current remaining: '.$summary['remaining'].' credits.');
        } else {
            $this->command?->info('Deltek: allocation 1,000; used '.$summary['used'].'; remaining '.$summary['remaining'].' credits. Historical leads charged now: '.$summary['backfilled_leads'].'.');
        }
    }
}
