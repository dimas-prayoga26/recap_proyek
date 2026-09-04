<?php

namespace App\Http\Controllers;

use App\Support\ExchangeRateService;
use Illuminate\Http\JsonResponse;

class ExchangeRateController extends Controller
{
    public function usdToIdr(ExchangeRateService $exchangeRateService): JsonResponse
    {
        return response()->json([
            'rate' => $exchangeRateService->usdToIdr(),
        ]);
    }
}
