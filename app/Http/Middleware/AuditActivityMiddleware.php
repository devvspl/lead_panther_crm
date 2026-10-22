<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditActivityMiddleware
{
    /**
     * Keys to mask in logged payloads.
     */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'access_token',
        'token',
        'secret',
        'app_secret',
        'api_key',
        '_token',
        'cookie',
        'authorization',
        'two_factor_code',
    ];

    /**
     * Paths/patterns to ignore from general request logging.
     */
    protected array $ignoredPaths = [
        'up',
        'health',
        '_debugbar*',
        'build/*',
        'favicon.ico',
        'livewire/livewire.js*',
        'livewire/preview-file*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $this->logActivitySafely($request, $response);

        return $response;
    }

    protected function logActivitySafely(Request $request, Response $response): void
    {
        try {
            if ($this->shouldSkip($request)) {
                return;
            }

            if ($request->is('livewire/update')) {
                $this->logLivewireActivity($request, $response);
            } else {
                $this->logControllerActivity($request, $response);
            }
        } catch (Throwable $e) {
            // Fail open: auditing should never crash user transactions
        }
    }

    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->ignoredPaths as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        // Skip GET requests to the audit logs browser itself to avoid recursion
        if ($request->isMethod('GET') && ($request->is('admin/audit-logs*') || $request->route()?->getName() === 'admin.audit-logs')) {
            return true;
        }

        // Only log activity when authenticated or when submitting auth/webhook endpoints
        if (!auth()->check()) {
            // Allow logging of unauthenticated webhooks or public signed actions
            if (!$request->is('api/webhooks*') && !$request->is('proposals/view*') && !$request->is('leads/manual*')) {
                return true;
            }
        }

        return false;
    }

    protected function logLivewireActivity(Request $request, Response $response): void
    {
        $components = $request->input('components', []);
        if (!is_array($components)) {
            return;
        }

        $userId = auth()->id();
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            $memo = $component['snapshot']['memo'] ?? ($component['memo'] ?? []);
            $componentName = $memo['name'] ?? 'unknown-component';

            // Skip internal audit logs browser updates from polling
            if ($componentName === 'admin.audit-log-browser') {
                continue;
            }

            $calls = $component['calls'] ?? [];
            if (!empty($calls) && is_array($calls)) {
                foreach ($calls as $call) {
                    $method = $call['method'] ?? 'unknown_method';
                    $rawParams = $call['params'] ?? [];
                    $params = $this->sanitizePayload(is_array($rawParams) ? $rawParams : [$rawParams]);

                    // Infer subject id from first parameter if numeric/id
                    $subjectId = null;
                    if (!empty($rawParams) && is_array($rawParams) && is_numeric($rawParams[0] ?? null)) {
                        $subjectId = (int) $rawParams[0];
                    }

                    AuditLog::create([
                        'user_id' => $userId,
                        'action' => 'livewire.' . $componentName . '.' . $method,
                        'subject_type' => 'Livewire: ' . $componentName,
                        'subject_id' => $subjectId,
                        'from_value' => null,
                        'to_value' => json_encode([
                            'method' => $method,
                            'params' => $params,
                            'status' => $response->getStatusCode(),
                        ]),
                        'ip_address' => $ip,
                        'user_agent' => $userAgent,
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }

    protected function logControllerActivity(Request $request, Response $response): void
    {
        $route = $request->route();
        $routeName = $route ? $route->getName() : null;
        $actionName = $route ? $route->getActionName() : null;

        $shortController = null;
        $controllerMethod = null;
        if ($actionName && is_string($actionName) && str_contains($actionName, '@')) {
            $parts = explode('@', $actionName);
            $shortController = class_basename($parts[0]);
            $controllerMethod = $parts[1] ?? '';
        } elseif ($actionName && is_string($actionName) && class_exists($actionName)) {
            $shortController = class_basename($actionName);
            $controllerMethod = '__invoke';
        }

        // Construct intuitive action name
        if ($routeName) {
            $action = 'route.' . $routeName;
        } elseif ($shortController) {
            $action = 'controller.' . $shortController . '@' . $controllerMethod;
        } else {
            $action = 'request.' . strtolower($request->method()) . '.' . trim(str_replace('/', '.', $request->path()), '.');
        }

        // Extract first bound model ID or route parameter as subject_id
        $subjectId = null;
        if ($route) {
            foreach ($route->parameters() as $param) {
                if (is_object($param) && method_exists($param, 'getKey')) {
                    $subjectId = $param->getKey();
                    break;
                } elseif (is_numeric($param)) {
                    $subjectId = (int) $param;
                    break;
                }
            }
        }

        $subjectType = $shortController
            ? 'Controller: ' . $shortController
            : ($routeName ? 'Route: ' . $routeName : 'Path: /' . $request->path());

        $sanitizedInput = $this->sanitizePayload($request->except($this->sensitiveKeys));
        $sanitizedQuery = $this->sanitizePayload($request->query());

        $fromValue = !empty($sanitizedQuery) ? json_encode(['query' => $sanitizedQuery]) : null;
        $toValue = json_encode([
            'http_method' => $request->method(),
            'path' => '/' . $request->path(),
            'status' => $response->getStatusCode(),
            'input' => $sanitizedInput,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'from_value' => $fromValue,
            'to_value' => $toValue,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }

    protected function sanitizePayload(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $cleaned = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;

            foreach ($this->sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $cleaned[$key] = '********';
            } elseif (is_array($value)) {
                $cleaned[$key] = $this->sanitizePayload($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}
