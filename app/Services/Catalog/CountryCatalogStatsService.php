<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Country;
use App\Models\Catalog\CountryCatalogStat;
use App\Models\Catalog\Platform;
use App\Models\Catalog\PlatformCandidate;

class CountryCatalogStatsService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function globalSummary(): array
    {
        return Country::query()
            ->where('enabled', true)
            ->with('catalogStat')
            ->orderBy('name')
            ->get()
            ->map(fn (Country $country) => $this->rowForCountry($country))
            ->all();
    }

    public function refreshForCountry(int $countryId): CountryCatalogStat
    {
        $live = Platform::query()
            ->routable()
            ->where(function ($q) use ($countryId) {
                $q->whereHas('countries', fn ($c) => $c->where('countries.id', $countryId))
                    ->orWhere('primary_country', Country::query()->where('id', $countryId)->value('iso2'));
            })
            ->count();

        $verified = Platform::query()
            ->whereIn('status', [Platform::STATUS_VERIFIED, Platform::STATUS_LIVE])
            ->where(function ($q) use ($countryId) {
                $q->whereHas('countries', fn ($c) => $c->where('countries.id', $countryId))
                    ->orWhere('primary_country', Country::query()->where('id', $countryId)->value('iso2'));
            })
            ->count();

        $candidatesTotal = PlatformCandidate::query()->where('country_id', $countryId)->count();
        $candidatesPending = PlatformCandidate::query()
            ->where('country_id', $countryId)
            ->whereIn('status', [
                PlatformCandidate::STATUS_DISCOVERED,
                PlatformCandidate::STATUS_ANALYZING,
                PlatformCandidate::STATUS_NEEDS_REVIEW,
            ])
            ->count();

        return CountryCatalogStat::query()->updateOrCreate(
            ['country_id' => $countryId],
            [
                'candidates_total' => $candidatesTotal,
                'candidates_pending' => $candidatesPending,
                'platforms_live' => $live,
                'platforms_verified' => $verified,
                'last_analyzed_at' => now(),
            ],
        );
    }

    public function refreshAll(): int
    {
        $count = 0;
        foreach (Country::query()->where('enabled', true)->pluck('id') as $countryId) {
            $this->refreshForCountry((int) $countryId);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowForCountry(Country $country): array
    {
        $stat = $country->catalogStat;

        return [
            'iso2' => $country->iso2,
            'name' => $country->name,
            'continent' => $country->continent?->code,
            'candidates_total' => $stat?->candidates_total ?? 0,
            'candidates_pending' => $stat?->candidates_pending ?? 0,
            'platforms_live' => $stat?->platforms_live ?? 0,
            'platforms_verified' => $stat?->platforms_verified ?? 0,
            'coverage_pct' => $this->coveragePercent($stat?->platforms_live ?? 0),
        ];
    }

    private function coveragePercent(int $liveCount): float
    {
        $target = (int) config('catalog.curation.target_platforms_per_country', 50);

        return $target > 0 ? round(min(100, ($liveCount / $target) * 100), 1) : 0.0;
    }
}
