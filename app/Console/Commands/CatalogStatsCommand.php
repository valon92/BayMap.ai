<?php

namespace App\Console\Commands;

use App\Models\Catalog\PlatformCandidate;
use App\Services\Catalog\CountryCatalogStatsService;
use App\Services\Catalog\PlatformCuratorService;
use Illuminate\Console\Command;

class CatalogStatsCommand extends Command
{
    protected $signature = 'catalog:stats {--country= : ISO2 filter} {--pending : Only countries with pending candidates}';

    protected $description = 'Show global catalog coverage per country (live vs pending analysis)';

    public function handle(CountryCatalogStatsService $stats): int
    {
        app(CountryCatalogStatsService::class)->refreshAll();

        $rows = collect($stats->globalSummary())
            ->when($this->option('country'), fn ($c) => $c->where('iso2', strtoupper((string) $this->option('country'))))
            ->when($this->option('pending'), fn ($c) => $c->where('candidates_pending', '>', 0))
            ->sortByDesc('candidates_pending')
            ->values();

        if ($rows->isEmpty()) {
            $this->warn('No catalog stats found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ISO', 'Country', 'Live', 'Verified', 'Candidates', 'Pending', 'Coverage %'],
            $rows->map(fn ($r) => [
                $r['iso2'],
                $r['name'],
                $r['platforms_live'],
                $r['platforms_verified'],
                $r['candidates_total'],
                $r['candidates_pending'],
                $r['coverage_pct'],
            ])->all(),
        );

        $target = config('catalog.curation.target_platforms_per_country', 50);
        $this->newLine();
        $this->line("Target live platforms per country: {$target} (configurable via CATALOG_TARGET_PLATFORMS)");

        return self::SUCCESS;
    }
}
