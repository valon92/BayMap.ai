<?php

namespace App\Console\Commands;

use App\Services\Providers\GlobalProviderRegistry;
use App\Services\Providers\ProviderIntelligenceService;
use Illuminate\Console\Command;

class ProviderStatsCommand extends Command
{
    protected $signature = 'providers:stats
                            {--country= : Filter registry summary by ISO2 country code}
                            {--top=15 : Top providers by intelligence metrics}';

    protected $description = 'Show Global Provider Registry stats and quality metrics';

    public function handle(GlobalProviderRegistry $registry, ProviderIntelligenceService $intelligence): int
    {
        $this->info('BuyMap.ai Global Provider Registry');
        $this->line('Total providers: '.$registry->count());

        $country = strtoupper((string) ($this->option('country') ?? ''));
        if ($country !== '') {
            $this->newLine();
            $this->info("Providers for {$country}:");
            foreach (['automotive', 'fashion', 'electronics', 'real_estate', 'marketplace'] as $category) {
                $list = $registry->forCountryCategory($country, $category);
                if ($list === []) {
                    continue;
                }
                $names = array_map(fn (array $p) => $p['provider_name'], $list);
                $this->line("  {$category}: ".implode(', ', array_slice($names, 0, 8)).(count($names) > 8 ? '…' : ''));
            }
        }

        $top = $intelligence->topProviders((int) $this->option('top'));
        if ($top !== []) {
            $this->newLine();
            $this->info('Top providers by success rate (rolling window):');
            $this->table(
                ['Platform', 'Samples', 'Success', 'Avg ms', 'Avg results'],
                array_map(fn (array $row) => [
                    $row['platform_slug'],
                    $row['samples'],
                    round($row['success_rate'] * 100, 1).'%',
                    $row['avg_latency_ms'],
                    $row['avg_result_count'],
                ], $top),
            );
        } else {
            $this->comment('No provider metrics recorded yet — run searches to populate intelligence.');
        }

        return self::SUCCESS;
    }
}
