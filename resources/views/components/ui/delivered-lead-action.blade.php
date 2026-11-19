@props(['item'])
<button type="button" wire:click="viewDeliveredLead({{ $item->id }})" title="View complete lead details" aria-label="View details for {{ $item->lead?->lead_code }}" class="inline-flex items-center gap-1.5 rounded-lg border border-border px-2.5 py-1.5 text-xs text-primary hover:bg-canvas"><x-ui.team-icon name="leads" />View</button>
