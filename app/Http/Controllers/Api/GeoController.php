<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geo\GeoLocationService;
use Illuminate\Http\JsonResponse;

class GeoController extends Controller
{
    public function __construct(private GeoLocationService $geo) {}

    public function detect(): JsonResponse
    {
        return response()->json($this->geo->resolve());
    }
}
