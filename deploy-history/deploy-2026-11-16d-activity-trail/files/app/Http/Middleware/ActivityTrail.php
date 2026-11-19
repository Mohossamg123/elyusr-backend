<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityResources;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safety net for the activity log: every staff change (a write on a route protected by `permission:` or under
 * `admin/`) and every refused attempt (403) leaves a line, even where the controller logs nothing itself.
 *  - controllers that already log a richer entry in the same request are not duplicated;
 *  - request VALUES are never stored (only the field names) so passwords and tokens cannot leak into the log;
 *  - logging never breaks the request (wrapped, runs after the response is sent).
 * Also records successful and failed sign-ins.
 */
class ActivityTrail
{
    private const WRITES = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const VERBS = ['POST' => 'create', 'PUT' => 'update', 'PATCH' => 'update', 'DELETE' => 'delete'];
    private const VERB_AR = ['create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function record(Request $request, Response $response): void
    {
        if (! in_array($request->method(), self::WRITES, true) || ! $request->route()) {
            return;
        }
        $status = $response->getStatusCode();
        $uri = $request->route()->uri();

        if ($uri === 'api/v1/login') {
            $this->signIn($request, $status);

            return;
        }

        $user = $request->user('sanctum');
        if (! $user || app()->bound('activity.logged')) {
            return;
        }
        $staff = str_contains($uri, '/admin/') || collect($request->route()->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));
        $denied = $status === 403;
        if (! $staff || ! ($denied || ($status >= 200 && $status < 300))) {
            return;
        }

        $verb = self::VERBS[$request->method()];
        $segment = ActivityResources::segment($uri);
        $name = ActivityResources::label($segment);
        $id = collect($request->route()->parameters())->map(fn ($v) => is_object($v) ? ($v->getKey() ?? null) : $v)->filter(fn ($v) => is_scalar($v))->last();

        ActivityLog::create([
            'user_id' => $user->id,
            'module' => $segment,
            'action' => $denied ? 'denied' : $verb,
            'description' => ($denied ? 'محاولة مرفوضة: ' : '') . self::VERB_AR[$verb] . ' — ' . $name . ($id !== null ? " #{$id}" : ''),
            'summary' => ($denied ? 'محاولة مرفوضة: ' : '') . self::VERB_AR[$verb] . ' — ' . $name . ($id !== null ? " #{$id}" : ''),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'entity_type' => $segment,
            'entity_id' => is_numeric($id) ? (int) $id : null,
            'role' => $user->roles()->pluck('slug')->first(),
            'metadata' => ['route' => $uri, 'method' => $request->method(), 'status' => $status, 'fields' => array_slice(array_keys($request->except(['password', 'password_confirmation', 'current_password', 'token'])), 0, 40)],
        ]);
    }

    private function signIn(Request $request, int $status): void
    {
        $email = strtolower(trim((string) $request->input('email')));
        $user = $email !== '' ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null;
        $ok = $status >= 200 && $status < 300;
        // Only sign-ins of known accounts are logged (an unknown e-mail has no one to attribute it to).
        if (! $user) {
            return;
        }
        ActivityLog::create([
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => $ok ? 'login' : 'login_failed',
            'description' => $ok ? 'تسجيل دخول' : 'محاولة دخول فاشلة',
            'summary' => $ok ? 'تسجيل دخول' : 'محاولة دخول فاشلة',
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'entity_type' => 'auth',
            'role' => $user->roles()->pluck('slug')->first(),
            'metadata' => ['status' => $status],
        ]);
    }
}
