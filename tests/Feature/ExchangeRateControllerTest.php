<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_endpoint_returns_cached_rate_without_exposing_api_key(): void
    {
        Cache::forget('exchange-rate.usd-idr');
        Http::preventStrayRequests();
        Http::fake([
            'currencyapi.net/*' => Http::response([
                'valid' => true,
                'base' => 'USD',
                'rates' => ['IDR' => 17250],
            ]),
        ]);

        $response = $this->getJson(route('kurs.usd-idr'));

        $response
            ->assertOk()
            ->assertExactJson(['rate' => 17250.0]);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), config('services.currencyapi.base_url').'/api/v2/rates')
                && $request['key'] === config('services.currencyapi.key')
                && $request['base'] === 'USD';
        });
    }

    public function test_endpoint_reuses_cached_rate_without_calling_api_again(): void
    {
        Cache::forget('exchange-rate.usd-idr');
        Http::preventStrayRequests();
        Http::fake([
            'currencyapi.net/*' => Http::response([
                'valid' => true,
                'base' => 'USD',
                'rates' => ['IDR' => 17250],
            ]),
        ]);

        $this->getJson(route('kurs.usd-idr'))->assertOk();
        $this->getJson(route('kurs.usd-idr'))->assertOk();

        Http::assertSentCount(1);
    }
}
