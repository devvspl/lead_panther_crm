@props(['organization'])
<div class="min-w-0 md:min-w-[180px] space-y-1.5" title="Total credits = available balance + lead usage after refunds. Manual adjustments change available funding.">
    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs">
        <span class="font-mono font-semibold text-ink">{{ number_format((float)$organization->available_credits, 2) }} <span class="text-muted font-normal">/ {{ number_format((float)$organization->total_credits, 2) }}</span></span>
        <span class="text-muted">{{ number_format((float)$organization->usage_percent, 1) }}% used</span>
    </div>
    <div role="progressbar" aria-label="Credit usage for {{ $organization->name }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $organization->usage_percent }}" class="h-1.5 w-full overflow-hidden rounded-full bg-primary/10">
        <div class="h-full rounded-full bg-primary" style="width: {{ (float)$organization->usage_percent }}%"></div>
    </div>
</div>
