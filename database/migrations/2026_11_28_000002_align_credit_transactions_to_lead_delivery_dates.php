<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('credit_transactions') || !Schema::hasTable('leads')) {
            return;
        }

        $hasDeliveredAt = Schema::hasColumn('leads', 'delivered_at');
        $hasCreatedAt = Schema::hasColumn('leads', 'created_at');

        // 1. Fetch active transactions with lead_id
        $txs = DB::table('credit_transactions')
            ->where('is_legacy', false)
            ->whereNotNull('lead_id')
            ->select('id', 'lead_id')
            ->get();

        if ($txs->isNotEmpty()) {
            $leadIds = $txs->pluck('lead_id')->unique()->filter()->values()->all();

            $selectCols = ['id'];
            if ($hasDeliveredAt) {
                $selectCols[] = 'delivered_at';
            }
            if ($hasCreatedAt) {
                $selectCols[] = 'created_at';
            }

            $leads = DB::table('leads')
                ->whereIn('id', $leadIds)
                ->select($selectCols)
                ->get()
                ->keyBy('id');

            foreach ($txs as $tx) {
                if (isset($leads[$tx->lead_id])) {
                    $l = $leads[$tx->lead_id];
                    $targetDate = null;
                    if ($hasDeliveredAt && !empty($l->delivered_at)) {
                        $targetDate = $l->delivered_at;
                    } elseif ($hasCreatedAt && !empty($l->created_at)) {
                        $targetDate = $l->created_at;
                    }

                    if (!empty($targetDate)) {
                        DB::table('credit_transactions')
                            ->where('id', $tx->id)
                            ->update(['created_at' => $targetDate]);
                    }
                }
            }
        }

        // 2. For reconciliation transactions, align timestamp to precede the earliest active lead consumption
        $reconciliations = DB::table('credit_transactions')
            ->where('transaction_type', 'reconciliation')
            ->get();

        foreach ($reconciliations as $rec) {
            $earliestLeadTx = DB::table('credit_transactions')
                ->where('client_id', $rec->client_id)
                ->where('is_legacy', false)
                ->whereNotNull('lead_id')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($earliestLeadTx && $earliestLeadTx->created_at <= $rec->created_at) {
                $adjustedDate = Carbon::parse($earliestLeadTx->created_at)->subHours(1)->toDateTimeString();
                DB::table('credit_transactions')->where('id', $rec->id)->update(['created_at' => $adjustedDate]);
            }
        }
    }

    public function down(): void
    {
    }
};
