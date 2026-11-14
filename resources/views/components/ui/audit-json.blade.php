@props(['value'])
@if($value === null || $value === [] || $value === '')
    <p class="text-xs text-muted p-3 bg-canvas rounded-lg">Unavailable for this record.</p>
@else
    <pre class="text-xs text-ink font-mono bg-canvas border border-border rounded-lg p-3 max-h-72 overflow-auto whitespace-pre-wrap break-words">{{ json_encode(\App\Support\AuditRedactor::payload($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
@endif
