<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExchangeRateService
{
    public function usdToIdr(): float
    {
        $cacheKey = 'exchange-rate.usd-idr';
        $cachedRate = Cache::get($cacheKey);

        if (is_numeric($cachedRate) && (float) $cachedRate > 0) {
            return (float) $cachedRate;
        }

        $rate = $this->fetchUsdToIdr();

        if ($rate !== null) {
            Cache::put($cacheKey, $rate, (int) config('services.frankfurter.usd_idr_cache_ttl', 300));

            return $rate;
        }

        return $this->fallbackUsdToIdrRate();
    }

    private function fetchUsdToIdr(): ?float
    {
        try {
            $response = Http::baseUrl((string) config('services.frankfurter.base_url'))
                ->connectTimeout(3)
                ->timeout(5)
                ->retry(2, 200)
                ->get('/v2/rates', [
                    'base' => 'USD',
                    'quotes' => 'IDR',
                ]);

            if (! $response->successful()) {
                return null;
            }

            return $this->rateFromPayload($response->json());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function rateFromPayload(array $payload): ?float
    {
        $rate = data_get($payload, '0.rate') ?? data_get($payload, 'rates.IDR');

        if (is_numeric($rate) && (float) $rate > 0) {
            return (float) $rate;
        }

        return null;
    }

    private function fallbackUsdToIdrRate(): float
    {
        return (float) config('services.frankfurter.usd_idr_fallback_rate', 16300);
    }
}
