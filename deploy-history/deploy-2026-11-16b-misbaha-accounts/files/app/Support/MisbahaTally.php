<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Who said how much. A signed-in account has one row per room (room 0 = the general misbaha) per day;
 * visitors without an account only add to a per-day anonymous total (no identity is stored).
 * Counting must never fail because of this: any error is swallowed.
 */
class MisbahaTally
{
    public static function add(?int $userId, int $roomId, int $n): void
    {
        if ($n < 1) {
            return;
        }
        try {
            $day = now()->toDateString();
            if ($userId) {
                $key = ['user_id' => $userId, 'room_id' => max(0, $roomId), 'day' => $day];
                DB::table('misbaha_user_counts')->insertOrIgnore($key + ['n' => 0, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('misbaha_user_counts')->where($key)->update(['n' => DB::raw('n + ' . $n), 'updated_at' => now()]);
            } else {
                $key = ['day' => $day, 'scope' => $roomId > 0 ? 'rooms' : 'general'];
                DB::table('misbaha_guest_days')->insertOrIgnore($key + ['n' => 0]);
                DB::table('misbaha_guest_days')->where($key)->update(['n' => DB::raw('n + ' . $n)]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
