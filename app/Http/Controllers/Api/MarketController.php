<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geo\GeoLocationService;
use App\Services\Market\MarketCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function __construct(
        private MarketCatalogService $catalog,
        private GeoLocationService $geo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = in_array($request->query('locale'), ['sq', 'en'], true)
            ? $request->query('locale')
            : 'en';

        $visitor = $this->geo->resolve();

        return response()->json([
            'continents' => $this->catalog->continentsWithCountries($locale),
            'visitor' => [
                'city' => $visitor['city'] ?? null,
                'country' => $visitor['country'] ?? null,
                'country_code' => $visitor['country_code'] ?? null,
            ],
            'locale' => $locale,
        ]);
    }
}
