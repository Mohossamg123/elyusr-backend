<?php

namespace App\Http\Controllers\Api\V1\Extras;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * GET /api/v1/public/metal-prices: today's gold and silver price per gram in EGP, for the zakat calculator.
 * Computed on the server from the world spot price (USD per troy ounce) and the USD/EGP rate, cached for
 * 10 minutes so visitors never call third parties from their browsers. If the sources are down, the last
 * good answer (kept 7 days) is returned with stale=true; if there is none yet the answer is 503 and the
 * page asks the visitor to type the price. Nothing is invented here.
 */
class MetalPriceController extends Controller
{
    private const OUNCE_GRAMS = 31.1035;

    public function __invoke(): JsonResponse
    {
        $fresh = Cache::get('metal_prices_fresh');
        if ($fresh) {
            return $this->ok($fresh, false);
        }

        // After a failed fetch wait a minute before asking the sources again, and let one request at a time ask.
        $data = null;
        if (! Cache::has('metal_prices_backoff')) {
            $lock = Cache::lock('metal_prices_fetch', 20);
            if ($lock->get()) {
                try {
                    $data = $this->fetch();
                    if (! $data) {
                        Cache::put('metal_prices_backoff', 1, now()->addSeconds(60));
                    }
                } finally {
                    $lock->release();
                }
            }
        }
        if ($data) {
            Cache::put('metal_prices_fresh', $data, now()->addMinutes(10));
            Cache::put('metal_prices_last', $data, now()->addDays(7));

            return $this->ok($data, false);
        }

        $last = Cache::get('metal_prices_last');
        if ($last) {
            return $this->ok($last, true);
        }

        return response()->json(['message' => 'أسعار المعادن مش متاحة دلوقتي.'], 503);
    }

    private function ok(array $d, bool $stale): JsonResponse
    {
        return response()->json(['data' => $d + ['stale' => $stale]])->header('Cache-Control', 'public, max-age=300');
    }

    private function fetch(): ?array
    {
        try {
            $gold = Http::connectTimeout(3)->timeout(5)->acceptJson()->get('https://api.gold-api.com/price/XAU')->json('price');
            $silver = Http::connectTimeout(3)->timeout(5)->acceptJson()->get('https://api.gold-api.com/price/XAG')->json('price');
            $egp = Http::connectTimeout(3)->timeout(5)->acceptJson()->get('https://open.er-api.com/v6/latest/USD')->json('rates.EGP');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        $gold = (float) $gold;
        $silver = (float) $silver;
        $egp = (float) $egp;
        // Sanity bounds: a wrong answer from a source must not become a nisab.
        if ($gold < 500 || $gold > 20000 || $silver < 2 || $silver > 500 || $egp < 5 || $egp > 500) {
            return null;
        }

        return [
            'gold_24_per_gram' => round($gold / self::OUNCE_GRAMS * $egp, 2),
            'silver_999_per_gram' => round($silver / self::OUNCE_GRAMS * $egp, 2),
            'usd_egp' => round($egp, 4),
            'currency' => 'EGP',
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
