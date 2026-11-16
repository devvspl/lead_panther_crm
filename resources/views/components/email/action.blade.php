@props(['url', 'label' => 'Open Lead Panther', 'design' => null])
@php
    $design = $design ?? app(\App\Services\EmailDesignService::class)->active();
    $accent = '#111827';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 24px;"><tr><td bgcolor="{{ $accent }}" style="border-radius:{{ $design === 'minimal' ? '6px' : '10px' }};background:{{ $accent }};text-align:center;">
    <a href="{{ $url }}" style="display:inline-block;border:1px solid {{ $accent }};border-radius:{{ $design === 'minimal' ? '6px' : '10px' }};padding:14px 24px;mso-padding-alt:14px 24px;font-size:14px;line-height:20px;font-weight:700;letter-spacing:0.1px;color:#ffffff;text-decoration:none;">{{ $label }} &nbsp; &rarr;</a>
</td></tr></table>
<p style="margin:0;font-size:11px;line-height:1.7;color:#6B7280;">Button not working? <a href="{{ $url }}" style="color:#6B7280;text-decoration:underline;">Open this link in your browser</a>.</p>
