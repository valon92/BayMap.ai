<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Country;
use App\Models\Catalog\CountryCatalogStat;
use App\Models\Catalog\Platform;
use App\Models\Catalog\PlatformCandidate;
use App\Support\CategoryCatalog;
use Illuminate\Support\Str;

/**
 * Curates marketplace candidates — discover, analyze, approve, promote to live workers.
 */
class PlatformCuratorService
{
    public function __construct(
        private PlatformCandidateAnalyzer $analyzer,
        private PlatformCatalogRepository $catalog,
        private CountryCatalogStatsService $stats,
    ) {}

    /**
     * Register a candidate for human/AI review — NOT added to live routing yet.
     */
    public function registerCandidate(
        string $countryIso2,
        string $name,
        string $baseUrl,
        string $source = 'manual',
        ?string $categorySlug = null,
    ): PlatformCandidate {
        $country = Country::query()->where('iso2', strtoupper($countryIso2))->firstOrFail();
        $domain = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
        $domain = strtolower(preg_replace('/^www\./', '', $domain));

        $candidate = PlatformCandidate::query()->updateOrCreate(
            ['country_id' => $country->id, 'domain' => $domain],
            [
                'name' => $name,
                'base_url' => rtrim($baseUrl, '/'),
                'status' => PlatformCandidate::STATUS_DISCOVERED,
                'source' => $source,
                'category_id' => $categorySlug
                    ? Category::query()->where('slug', CategoryCatalog::normalize($categorySlug))->value('id')
                    : null,
            ],
        );

        $this->stats->refreshForCountry($country->id);

        return $candidate;
    }

    /**
     * Run automated technical analysis on a candidate.
     *
     * @return array<string, mixed>
     */
    public function analyze(int $candidateId): array
    {
        $candidate = PlatformCandidate::query()->findOrFail($candidateId);

        return $this->analyzer->analyze($candidate);
    }

    /**
     * Approve candidate and promote to live platform (still requires scraper config for full live search).
     */
    public function approve(int $candidateId, ?string $reviewNotes = null): Platform
    {
        $candidate = PlatformCandidate::query()->with('country')->findOrFail($candidateId);

        $slug = $this->uniqueSlug($candidate);
        $countryCode = $candidate->country->iso2;

        $platform = Platform::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'label' => $candidate->name,
                'base_url' => $candidate->base_url,
                'adapter' => $candidate->adapter_guess ?? 'generic',
                'primary_country' => $countryCode,
                'priority' => $candidate->priority_score,
                'trust_score' => $candidate->trust_estimate ?? 70,
                'enabled' => true,
                'status' => Platform::STATUS_VERIFIED,
                'source' => 'candidate',
                'verified_at' => now(),
                'candidate_id' => $candidate->id,
                'analysis_notes' => $reviewNotes,
            ],
        );

        if ($candidate->category_id) {
            $platform->categories()->syncWithoutDetaching([
                $candidate->category_id => ['priority' => null],
            ]);
        }

        $platform->countries()->syncWithoutDetaching([
            $candidate->country_id => ['priority' => $platform->priority, 'enabled' => true],
        ]);

        $candidate->update([
            'status' => PlatformCandidate::STATUS_LIVE,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        $this->catalog->flushCache();
        $this->stats->refreshForCountry($candidate->country_id);

        return $platform;
    }

    public function reject(int $candidateId, string $reason): PlatformCandidate
    {
        $candidate = PlatformCandidate::query()->findOrFail($candidateId);
        $candidate->update([
            'status' => PlatformCandidate::STATUS_REJECTED,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);

        $this->stats->refreshForCountry($candidate->country_id);

        return $candidate;
    }

    /**
     * Mark an existing config-imported platform as fully verified live.
     */
    public function markConfigPlatformLive(Platform $platform): void
    {
        $platform->update([
            'status' => Platform::STATUS_LIVE,
            'source' => 'config',
            'verified_at' => $platform->verified_at ?? now(),
        ]);
    }

    private function uniqueSlug(PlatformCandidate $candidate): string
    {
        $base = Str::slug($candidate->domain, '_');
        $slug = $base;
        $i = 2;

        while (Platform::query()->where('slug', $slug)->where('candidate_id', '!=', $candidate->id)->exists()) {
            $slug = $base.'_'.$i;
            $i++;
        }

        return $slug;
    }
}
