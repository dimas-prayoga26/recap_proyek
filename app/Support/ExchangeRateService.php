<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExchangeRateService
{
    private const CACHE_KEY = 'exchange-rate.usd-idr';

    public function usdToIdr(): float
    {
        $cachedRate = Cache::get(self::CACHE_KEY);

        if (is_numeric($cachedRate) && (float) $cachedRate > 0) {
            return (float) $cachedRate;
        }

        $rate = $this->fetchUsdToIdr();

        if ($rate !== null) {
            Cache::put(self::CACHE_KEY, $rate, (int) config('services.currencyapi.usd_idr_cache_ttl', 86400));

            return $rate;
        }

        return $this->fallbackUsdToIdrRate();
    }

    public function forgetCachedRate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function fetchUsdToIdr(): ?float
    {
        try {
            $response = Http::baseUrl((string) config('services.currencyapi.base_url'))
                ->connectTimeout(3)
                ->timeout(5)
                ->retry(2, 200)
                ->get('/api/v2/rates', [
                    'key' => config('services.currencyapi.key'),
                    'base' => 'USD',
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
        if (($payload['valid'] ?? true) === false) {
            return null;
        }

        $rate = data_get($payload, 'rates.IDR');

        if (is_numeric($rate) && (float) $rate > 0) {
            return (float) $rate;
        }

        return null;
    }

    private function fallbackUsdToIdrRate(): float
    {
        return (float) config('services.currencyapi.usd_idr_fallback_rate', 16300);
    }
}
