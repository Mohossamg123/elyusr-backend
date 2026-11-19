<?php

namespace App\Http\Controllers\Api\V1\Extras;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Article versions for the editor («النسخ السابقة»):
 *   POST /admin/blog-revisions            { slug, snapshot: {title_ar, body_ar, …}, note? }   keep the version about to be replaced
 *   GET  /admin/blog-revisions?slug=…     the latest versions of one article
 * Restoring is done by the editor: it loads a version into the form and the admin saves it like any edit.
 * Admins and super admins only (an editor's save goes through «مراجعة المحتوى» and has its own history).
 */
class BlogRevisionController extends Controller
{
    private const KEEP = 40;
    private const MAX_BYTES = 400000;
    private const FIELDS = [
        'slug', 'status', 'image_url', 'og_image_url', 'category_ar', 'category_en', 'focus_keyword',
        'title_ar', 'title_en', 'excerpt_ar', 'excerpt_en', 'body_ar', 'body_en', 'tags_ar', 'tags_en', 'scheduled_at',
    ];

    private function admin(?User $user): void
    {
        $ok = StaffAccess::isSuperAdmin($user)
            || ($user && $user->roles()->whereIn('slug', ['admin', 'super-admin'])->exists());
        abort_unless($ok, 403, 'سجل النسخ للإدارة بس.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->admin($request->user());
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:190', 'regex:/^[\pL\pN_\-]+$/u'],
            'snapshot' => ['required', 'array'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        // Only the article's own fields are kept (nothing else a client might send along).
        $snap = array_intersect_key($data['snapshot'], array_flip(self::FIELDS));
        $json = json_encode($snap, JSON_UNESCAPED_UNICODE);
        abort_if($json === false || strlen($json) > self::MAX_BYTES, 422, 'النسخة كبيرة أوي.');

        $last = DB::table('blog_post_revisions')->where('slug', $data['slug'])->orderByDesc('id')->first();
        if ($last && json_decode($last->snapshot, true) == $snap) {
            return response()->json(['data' => ['id' => $last->id, 'duplicate' => true]]);
        }
        $id = DB::transaction(function () use ($data, $json, $snap, $request) {
            $id = DB::table('blog_post_revisions')->insertGetId([
                'slug' => $data['slug'],
                'user_id' => $request->user()->id,
                'snapshot' => $json,
                'title' => mb_substr((string) ($snap['title_ar'] ?? ''), 0, 300),
                'note' => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $old = DB::table('blog_post_revisions')->where('slug', $data['slug'])->orderByDesc('id')->skip(self::KEEP)->take(1000)->pluck('id');
            if ($old->isNotEmpty()) {
                DB::table('blog_post_revisions')->whereIn('id', $old)->delete();
            }

            return $id;
        });

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->admin($request->user());
        $slug = (string) $request->query('slug', '');
        abort_unless($slug !== '' && preg_match('/^[\pL\pN_\-]+$/u', $slug) === 1, 422, 'الرابط المختصر مطلوب.');
        $rows = DB::table('blog_post_revisions')->where('slug', $slug)->orderByDesc('id')->limit(self::KEEP)->get();
        $names = User::whereIn('id', $rows->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id' => $r->id,
            'by' => $names[$r->user_id] ?? null,
            'at' => (string) $r->created_at,
            'title' => $r->title,
            'note' => $r->note,
            'snapshot' => json_decode($r->snapshot, true) ?: [],
        ])->values()]);
    }
}
