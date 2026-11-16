<x-email.layout :title="$heading" :design="$design ?? null">
    <p style="margin:0;white-space:pre-line;font-size:15px;line-height:1.85;color:#6B7280;">{{ $messageBody }}</p>
    @if($actionUrl)
        <x-email.action :url="$actionUrl" label="View in Lead Panther" :design="$design ?? null" />
    @endif
</x-email.layout>
