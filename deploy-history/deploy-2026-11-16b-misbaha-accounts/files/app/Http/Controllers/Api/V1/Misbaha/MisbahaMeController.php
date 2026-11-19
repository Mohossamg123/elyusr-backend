<?php

namespace App\Http\Controllers\Api\V1\Misbaha;

use App\Http\Controllers\Controller;
use App\Models\MisbahaRoom;
use App\Support\MisbahaTally;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The misbaha and accounts.
 *   POST  /misbaha/count                 public, throttled: the general misbaha (signed-in = saved to the account)
 *   GET   /me/misbaha                    account: my totals, last 30 days, my shared misbahas, where I counted
 *   PATCH /me/misbaha-rooms/{slug}       account: edit / hide a misbaha I created
 *   GET   /admin/misbaha/overview        team: signed-in vs anonymous counts
 */
class MisbahaMeController extends Controller
{
    private function room(MisbahaRoom $r): array
    {
        return [
            'slug' => $r->slug, 'kind' => $r->kind ?: 'deceased', 'gender' => $r->gender, 'name' => $r->name,
            'total_count' => (int) $r->total_count, 'target_total' => $r->target_total ? (int) $r->target_total : null,
            'dhikr_id' => $r->dhikr_id, 'is_hidden' => (bool) $r->is_hidden, 'created_at' => $r->created_at,
        ];
    }

    public function count(Request $request)
    {
        $n = (int) $request->validate(['count' => ['required', 'integer', 'min:1', 'max:500']])['count'];
        $uid = $request->user('sanctum')?->id;
        MisbahaTally::add($uid, 0, $n);

        $total = $uid ? (int) DB::table('misbaha_user_counts')->where(['user_id' => $uid, 'room_id' => 0])->sum('n') : null;

        return response()->json(['total' => $total]);
    }

    public function mine(Request $request)
    {
        $uid = $request->user()->id;
        $t = DB::table('misbaha_user_counts')->where('user_id', $uid);

        $days = (clone $t)->where('day', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('day, SUM(n) as n')->groupBy('day')->orderBy('day')->get()
            ->map(fn ($d) => ['day' => $d->day, 'n' => (int) $d->n])->values();

        $where = DB::table('misbaha_user_counts as c')->join('misbaha_rooms as r', 'r.id', '=', 'c.room_id')
            ->where('c.user_id', $uid)->where('c.room_id', '>', 0)->where('r.is_hidden', false)
            ->selectRaw('r.slug, r.name, SUM(c.n) as n')->groupBy('r.id', 'r.slug', 'r.name')
            ->orderByDesc('n')->limit(10)->get()
            ->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name, 'n' => (int) $r->n])->values();

        return response()->json(['data' => [
            'general_total' => (int) (clone $t)->where('room_id', 0)->sum('n'),
            'rooms_total' => (int) (clone $t)->where('room_id', '>', 0)->sum('n'),
            'days' => $days,
            'created' => MisbahaRoom::where('user_id', $uid)->orderByDesc('id')->limit(100)->get()->map(fn ($r) => $this->room($r))->values(),
            'counted_in' => $where,
        ]]);
    }

    public function updateRoom(Request $request, string $slug)
    {
        $room = MisbahaRoom::where('slug', $slug)->where('user_id', $request->user()->id)->firstOrFail();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'target_total' => ['sometimes', 'nullable', 'integer', 'in:1000,10000,100000,1000000'],
            'dhikr_id' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/'],
            'is_hidden' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['name'])) {
            $data['name'] = trim(strip_tags($data['name']));
        }
        $room->update($data);

        return response()->json(['data' => $this->room($room->fresh())]);
    }

    public function adminOverview()
    {
        $from = now()->subDays(29)->toDateString();
        $users = DB::table('misbaha_user_counts')->where('day', '>=', $from);

        return response()->json(['data' => [
            'signed_in_30d' => (int) (clone $users)->sum('n'),
            'signed_in_users_30d' => (int) (clone $users)->distinct()->count('user_id'),
            'anonymous_30d' => (int) DB::table('misbaha_guest_days')->where('day', '>=', $from)->sum('n'),
            'anonymous_today' => (int) DB::table('misbaha_guest_days')->where('day', now()->toDateString())->sum('n'),
        ]]);
    }
}
