@props(['stats'])
<dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
    @foreach(['total' => 'Total leads received', 'today' => 'Delivered today', 'remaining' => 'Remaining daily capacity', 'pending' => 'Pending queue', 'failed' => 'Failed (awaiting retry)'] as $field => $label)
        <div class="rounded-lg border border-border bg-canvas p-3">
            <dt class="text-muted">{{ $label }}</dt>
            <dd class="font-semibold text-ink mt-1">{{ $stats[$field] === null ? 'Unlimited' : number_format($stats[$field]) }}</dd>
        </div>
    @endforeach
    @foreach(['next' => 'Next scheduled delivery', 'last' => 'Last successful delivery'] as $field => $label)
        <div class="rounded-lg border border-border bg-canvas p-3">
            <dt class="text-muted">{{ $label }}</dt>
            <dd class="font-semibold text-ink mt-1">{{ $stats[$field] ? \Carbon\Carbon::parse($stats[$field], config('app.timezone'))->setTimezone($stats['timezone'])->format('M d, Y H:i') : 'None' }}</dd>
            @if($field === 'next' && $stats[$field] && \Carbon\Carbon::parse($stats[$field], config('app.timezone'))->isPast())
                <dd class="text-muted mt-1">Due; waiting for the processor or delivery eligibility.</dd>
            @endif
        </div>
    @endforeach
</dl>
