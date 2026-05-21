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
            'q' => 'required|string|min:3|max:500',
            'locale' => 'nullable|string|in:en,sq',
            'filters' => 'nullable',
        ]);

        $filters = $validated['filters'] ?? [];
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?? [];
        }

        $result = $this->orchestrator->search(
            $validated['q'],
            is_array($filters) ? $filters : [],
            $validated['locale'] ?? null
        );

        return response()->json($result);
    }
}
