<x-email.layout :title="$subject ?? 'Lead Panther Notification'">
    @if(!empty($greeting))<p style="margin:0 0 16px;font-weight:700;color:#0A0A0A;">{{ $greeting }}</p>@endif
    @foreach($introLines as $line)<p style="margin:0 0 16px;">{{ $line }}</p>@endforeach
    @isset($actionText)
        <x-email.action :url="$actionUrl" :label="$actionText" />
    @endisset
    @foreach($outroLines as $line)<p style="margin:24px 0 0;">{{ $line }}</p>@endforeach
    @if(!empty($salutation))<p style="margin:24px 0 0;">{{ $salutation }}</p>@endif
</x-email.layout>
