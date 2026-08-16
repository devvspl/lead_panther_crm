@props(['activities' => []])

<div class="flow-root">
    <ul role="list" class="-mb-8">
        @forelse($activities as $act)
            @php
                $type = $act['type'] ?? 'activity';
                $title = $act['title'] ?? 'Activity Recorded';
                $date = $act['date'] ?? now();
                $notes = $act['notes'] ?? '';
                $status = $act['status'] ?? 'completed';

                $iconColor = match($type) {
                    'followup' => 'bg-blue-500 text-white',
                    'meeting' => 'bg-purple-500 text-white',
                    'site_visit' => 'bg-amber-500 text-white',
                    'proposal' => 'bg-indigo-500 text-white',
                    'booking' => 'bg-green-600 text-white',
                    'payment' => 'bg-emerald-500 text-white',
                    default => 'bg-gray-400 text-white',
                };
            @endphp
            <li>
                <div class="relative pb-8">
                    @if(!$loop->last)
                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-border" aria-hidden="true"></span>
                    @endif
                    <div class="relative flex space-x-3 items-start">
                        <div>
                            <span class="h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-surface text-xs font-bold {{ $iconColor }}">
                                {{ strtoupper(substr($type, 0, 1)) }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0 bg-canvas/60 p-3 rounded-card border border-border">
                            <div class="flex items-center justify-between text-xs font-bold text-ink">
                                <span>{{ $title }}</span>
                                <span class="text-[10px] font-normal text-muted">{{ \Carbon\Carbon::parse($date)->format('M d, Y H:i') }}</span>
                            </div>
                            @if($notes)
                                <p class="text-xs text-muted mt-1">{{ $notes }}</p>
                            @endif
                            @if($status)
                                <div class="mt-2">
                                    <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded-pill bg-surface border border-border text-ink">
                                        {{ $status }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </li>
        @empty
            <li class="text-center py-6 text-xs text-muted">
                No activity records found for this lead.
            </li>
        @endforelse
    </ul>
</div>
