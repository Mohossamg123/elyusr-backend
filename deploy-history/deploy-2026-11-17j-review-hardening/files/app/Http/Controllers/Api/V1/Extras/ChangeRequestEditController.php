<?php

namespace App\Http\Controllers\Api\V1\Extras;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\StaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Edit what an editor sent before approving it («مراجعة المحتوى»):
 *   PATCH /admin/change-requests/{id}/payload   { changes: {field: value, …}, note? }
 *   GET   /admin/change-requests/{id}/revisions
 * Only a PENDING request can be edited, only by an admin / super admin, and every edit keeps the
 * previous payload in change_request_revisions (who, when, which fields). Approving still runs the
 * target route with its own validation, so an edit can never skip a rule.
 * The content_change_requests table and its controller are the other developer's; this only
 * updates the payload column of a pending row.
 */
class ChangeRequestEditController extends Controller
{
    /** Same names EditorApprovalMiddleware redacts at submission, plus a few more. */
    private const BLOCKED = [
        'password', 'password_confirmation', 'current_password', 'secret', 'secret_key', 'api_key', 'apikey', 'public_key',
        'private_key', 'privatekey', 'access_token', 'refresh_token', 'client_secret', 'token', 'remember_token',
        'authorization', 'hmac_secret', 'credentials',
    ];
    private const MAX_BYTES = 400000;

    private function reviewer(?User $user): void
    {
        $ok = StaffAccess::isSuperAdmin($user)
            || ($user && $user->roles()->whereIn('slug', ['admin', 'super-admin'])->exists());
        abort_unless($ok, 403, 'المراجعة للإدارة بس.');
    }

    private function sensitive(string $key): bool
    {
        return in_array(strtolower(str_replace(['-', ' '], '_', $key)), self::BLOCKED, true);
    }

    private function hasSensitiveKey(array $value): bool
    {
        foreach ($value as $k => $v) {
            if (is_string($k) && $this->sensitive($k)) {
                return true;
            }
            if (is_array($v) && $this->hasSensitiveKey($v)) {
                return true;
            }
        }

        return false;
    }

    private function decode($value): array
    {
        $v = is_string($value) ? json_decode($value, true) : $value;

        return is_array($v) ? $v : [];
    }

    public function update(Request $request, int $id, ActivityLogService $log): JsonResponse
    {
        $this->reviewer($request->user());
        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $result = DB::transaction(function () use ($id, $data, $user) {
            // Lock the row so two edits cannot overwrite each other and an edit never lands on a request that was just reviewed.
            $row = DB::table('content_change_requests')->where('id', $id)->lockForUpdate()->first();
            abort_if(! $row, 404, 'الطلب مش موجود.');
            abort_unless($row->status === 'pending', 422, 'الطلب ده اتراجع قبل كده ومينفعش يتعدّل.');
            abort_if($row->module === 'editor-registration', 422, 'طلبات انضمام المحررين مالهاش تعديل.');
            abort_if(strtoupper((string) $row->method) === 'DELETE', 422, 'طلب الحذف مالوش حقول تتعدّل.');

            $before = $this->decode($row->payload);
            $after = $before;
            $changed = [];
            foreach ($data['changes'] as $key => $value) {
                $key = (string) $key;
                abort_unless(preg_match('/^[A-Za-z0-9_]{1,64}$/', $key) === 1, 422, "اسم حقل مش مقبول: {$key}");
                abort_if(str_starts_with($key, '_') || $this->sensitive($key), 422, "الحقل ده مينفعش يتعدّل: {$key}");
                // Only fields the submitter already sent: an edit can fix a value, never add a new field to the request.
                abort_unless(array_key_exists($key, $before), 422, "الحقل ده مش في الطلب الأصلي: {$key}");
                abort_unless(is_null($value) || is_scalar($value) || is_array($value), 422, "قيمة مش مقبولة: {$key}");
                abort_if(is_array($value) && $this->hasSensitiveKey($value), 422, "الحقل فيه مفتاح حساس: {$key}");
                if (($before[$key] ?? null) !== $value) {
                    $after[$key] = $value;
                    $changed[] = $key;
                }
            }
            if (! $changed) {
                return ['changed' => []];
            }
            $json = json_encode($after, JSON_UNESCAPED_UNICODE);
            abort_if($json === false || strlen($json) > self::MAX_BYTES, 422, 'المحتوى كبير أوي.');

            $touched = DB::table('content_change_requests')->where('id', $id)->where('status', 'pending')->update([
                'payload' => $json,
                'updated_at' => now(),
            ]);
            abort_if($touched !== 1, 409, 'الطلب اتغيّر وانت بتعدّل، افتحه تاني.');
            DB::table('change_request_revisions')->insert([
                'change_request_id' => $id,
                'user_id' => $user->id,
                'payload' => json_encode($before, JSON_UNESCAPED_UNICODE),
                'changed_fields' => json_encode($changed),
                'note' => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['changed' => $changed];
        });
        $changed = $result['changed'];
        if (! $changed) {
            return response()->json(['data' => ['id' => $id, 'changed' => []], 'message' => 'مفيش تغيير.']);
        }

        $log->custom('change_request_edited', null, "تعديل طلب مراجعة #{$id} قبل الموافقة (" . implode('، ', $changed) . ')', ['id' => $id, 'fields' => $changed]);

        return response()->json(['data' => ['id' => $id, 'changed' => $changed]]);
    }

    public function revisions(Request $request, int $id): JsonResponse
    {
        $this->reviewer($request->user());
        $rows = DB::table('change_request_revisions')->where('change_request_id', $id)->orderByDesc('id')->limit(50)->get();
        $names = User::whereIn('id', $rows->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id' => $r->id,
            'by' => $names[$r->user_id] ?? null,
            'at' => (string) $r->created_at,
            'note' => $r->note,
            'fields' => $this->decode($r->changed_fields),
            'payload' => $this->decode($r->payload),
        ])->values()]);
    }
}
