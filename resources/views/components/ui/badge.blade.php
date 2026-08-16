@props([
    'status' => 'new',
    'color' => null,
])

@php
$badgeStatus = strtolower(str_replace([' ', '-'], '_', $status));

$colorClasses = match(true) {
    !is_null($color) && $color === 'blue' => 'bg-blue-50 text-blue-700',
    !is_null($color) && $color === 'amber' => 'bg-amber-50 text-amber-700',
    !is_null($color) && $color === 'purple' => 'bg-purple-50 text-purple-700',
    !is_null($color) && $color === 'orange' => 'bg-orange-50 text-orange-700',
    !is_null($color) && $color === 'green' => 'bg-green-50 text-green-700',
    !is_null($color) && $color === 'gray' => 'bg-gray-100 text-gray-600',
    !is_null($color) && $color === 'red' => 'bg-red-50 text-red-700',
    
    // Status Family Mapping (Section 2.3)
    in_array($badgeStatus, ['new', 'assigned']) => 'bg-blue-50 text-blue-700',
    in_array($badgeStatus, ['contacted', 'in_progress']) => 'bg-amber-50 text-amber-700',
    in_array($badgeStatus, ['qualified', 'interested']) => 'bg-purple-50 text-purple-700',
    in_array($badgeStatus, ['site_visit', 'meeting', 'negotiation']) => 'bg-orange-50 text-orange-700',
    in_array($badgeStatus, ['booking', 'payment', 'closed_won']) => 'bg-green-50 text-green-700',
    in_array($badgeStatus, ['lost', 'rejected', 'replaced']) => 'bg-gray-100 text-gray-600',
    in_array($badgeStatus, ['sla_breached', 'danger']) => 'bg-red-50 text-red-700',
    
    default => 'bg-blue-50 text-blue-700',
};
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-pill text-xs font-semibold ' . $colorClasses]) }}>
    {{ $slot->isEmpty() ? ucwords(str_replace('_', ' ', $status)) : $slot }}
</span>
