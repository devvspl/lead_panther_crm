@props(['title' => 'Lead Panther', 'design' => null])
@php
    $design = $design ?? app(\App\Services\EmailDesignService::class)->active();
    $theme = match($design) {
        'executive' => ['accent' => '#111827', 'canvas' => '#F5F5F5', 'hero' => '#111827', 'heading' => '#FFFFFF', 'line' => '#111827'],
        'minimal' => ['accent' => '#111827', 'canvas' => '#F5F5F5', 'hero' => '#FFFFFF', 'heading' => '#0A0A0A', 'line' => '#E5E7EB'],
        default => ['accent' => '#111827', 'canvas' => '#F5F5F5', 'hero' => '#F5F5F5', 'heading' => '#0A0A0A', 'line' => '#E5E7EB'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light"><title>{{ $title }}</title>
    <style>@media only screen and (max-width:600px){.email-shell{padding:20px 12px!important}.email-pad{padding-left:24px!important;padding-right:24px!important}.email-heading{font-size:27px!important}.email-brand-note{display:none!important}}</style>
</head>
<body style="margin:0;padding:0;background:{{ $theme['canvas'] }};font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;color:#6B7280;">
    <div style="display:none;font-size:1px;line-height:1px;color:{{ $theme['canvas'] }};max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{{ $title }} — An update from your Lead Panther workspace.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $theme['canvas'] }};table-layout:fixed;">
        <tr><td align="center" class="email-shell" style="padding:40px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;table-layout:fixed;">
                <tr><td style="padding:0 4px 24px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                        <td width="44" style="width:44px;"><div style="width:40px;height:40px;line-height:40px;background:#111827;border-radius:12px;color:#FFFFFF;text-align:center;font-size:15px;font-weight:700;letter-spacing:-1px;">LP</div></td>
                        <td style="padding-left:10px;color:#111827;font-size:18px;font-weight:700;letter-spacing:-0.5px;">Lead Panther</td>
                        <td align="right" class="email-brand-note" style="font-size:10px;letter-spacing:1.8px;color:#6B7280;">YOUR CRM WORKSPACE</td>
                    </tr></table>
                </td></tr>
                <tr><td style="background:#FFFFFF;border:1px solid #E5E7EB;border-radius:{{ $design === 'executive' ? '12px' : ($design === 'minimal' ? '0' : '20px') }};overflow:hidden;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="table-layout:fixed;">
                        @if($design === 'minimal')
                            <tr><td style="height:5px;background:#111827;font-size:0;line-height:0;">&nbsp;</td></tr>
                        @endif
                        <tr><td class="email-pad" style="padding:36px 40px 30px;background:{{ $theme['hero'] }};border-bottom:1px solid {{ $theme['line'] }};">
                            <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="font-size:10px;line-height:14px;font-weight:700;letter-spacing:1.6px;color:{{ $design === 'executive' ? '#E5E7EB' : '#6B7280' }};">{{ $design === 'executive' ? 'WORKSPACE BRIEFING' : ($design === 'minimal' ? 'A NOTE FROM LEAD PANTHER' : 'KEEPING YOU IN THE LOOP') }}</td></tr></table>
                            <h1 class="email-heading" style="margin:16px 0 0;font-size:32px;line-height:1.25;font-weight:700;letter-spacing:-0.9px;color:{{ $theme['heading'] }};overflow-wrap:break-word;">{{ $title }}</h1>
                            @if($design === 'executive')<div style="width:36px;height:3px;background:#FFFFFF;margin-top:24px;font-size:0;line-height:0;">&nbsp;</div>@endif
                        </td></tr>
                        <tr><td class="email-pad" style="padding:32px 40px 36px;font-size:15px;line-height:1.8;color:#6B7280;">{{ $slot }}</td></tr>
                        <tr><td class="email-pad" style="padding:20px 40px;background:{{ $design === 'minimal' ? '#ffffff' : '#F5F5F5' }};border-top:1px solid #E5E7EB;">
                            <p style="margin:0;font-size:12px;line-height:1.7;color:#6B7280;">Sent from your Lead Panther workspace.<br>Open the app to view the latest details and manage notification preferences.</p>
                        </td></tr>
                    </table>
                </td></tr>
                <tr><td align="center" style="padding:24px 20px 0;font-size:11px;line-height:1.9;color:#6B7280;">
                    <p style="margin:0;color:#6B7280;font-weight:700;">Lead Panther CRM</p>
                    <p style="margin:3px 0 0;">{{ date('Y') }} &middot; Automated workspace notification</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
