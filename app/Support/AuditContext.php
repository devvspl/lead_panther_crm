<?php
namespace App\Support;

class AuditContext
{
    public static function current(): array
    {
        $request = request();
        $user = auth()->user();
        $actor = $request->attributes->has('audit_actor_snapshot') ? $request->attributes->get('audit_actor_snapshot') : ($user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $user->getRoleNames()->all(), 'organization_id' => $user->organization_id] : null);
        $reference = $request->attributes->get('audit_request_id');
        if (! $reference) { $reference = (string) \Illuminate\Support\Str::uuid(); $request->attributes->set('audit_request_id', $reference); }
        $params = $request->route()?->parameters() ?? [];
        $action = $request->route()?->getActionName();
        $controller = $action && str_contains($action, '@') ? explode('@', $action, 2) : [$action, null];
        foreach ($params as $key => $param) if (is_object($param) && method_exists($param, 'getKey')) $params[$key] = $param->getKey();
        $url = $request->url();
        foreach ($params as $key => $value) if (AuditRedactor::sensitive($key) && is_scalar($value)) $url = str_replace([(string) $value, rawurlencode((string) $value)], '********', $url);
        return AuditRedactor::clean([
            'request_id' => $reference, 'http_method' => $request->route() ? $request->method() : null,
            'url' => $request->route() ? $url : null,
            'controller' => $controller[0], 'controller_method' => $controller[1], 'route' => $request->route()?->getName(),
            'parameters' => $params, 'query' => $request->query(), 'payload' => $request->except(['components']),
            'actor' => $actor,
            'session_reference' => $request->hasSession() && $request->session()->isStarted() ? hash_hmac('sha256', $request->session()->getId(), config('app.key') ?: 'audit-reference') : null,
        ]);
    }
}
