<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private SearchOrchestratorService $orchestrator) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:12000000',
            'locale' => 'nullable|string|in:en,sq',
            'location_scope' => 'nullable|string|in:auto,city,local,country,region,continent,world,universal,global',
            'market_mode' => 'nullable|string|in:auto,global,continent,country,countries',
            'market_code' => 'nullable|string|max:96',
            'filters' => 'nullable',
            'page' => 'nullable|integer|min:1|max:50',
            'per_page' => 'nullable|integer|min:6|max:48',
        ]);

        $query = trim($validated['q'] ?? '');
        $image = $validated['image'] ?? null;

        if ($image && str_contains($image, 'base64,')) {
            $image = explode('base64,', $image, 2)[1];
        }

        if (! $image && strlen($query) < 3) {
            return response()->json(['message' => 'Provide text (3+ chars) or a product image.'], 422);
        }

        @set_time_limit((int) config('search.max_execution_seconds', 300));

        $filters = $validated['filters'] ?? [];
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?? [];
        }

        $result = $this->orchestrator->search(
            $query ?: 'find this product',
            is_array($filters) ? $filters : [],
            $validated['locale'] ?? null,
            $image,
            $validated['location_scope'] ?? 'auto',
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 12),
            $validated['market_mode'] ?? null,
            $validated['market_code'] ?? null,
        );

        return response()->json($result);
    }
}
