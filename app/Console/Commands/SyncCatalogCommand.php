<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogSyncService;
use App\Services\Catalog\PlatformCatalogRepository;
use Illuminate\Console\Command;

class SyncCatalogCommand extends Command
{
    protected $signature = 'catalog:sync {--fresh : Re-import platforms from config/live_platforms.php}';

    protected $description = 'Sync global catalog (continents, countries, categories, platforms) into the database';

    public function handle(CatalogSyncService $sync, PlatformCatalogRepository $catalog): int
    {
        if ($this->option('fresh')) {
            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\GeoCatalogSeeder',
                '--force' => true,
            ]);
            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\CategoryCatalogSeeder',
                '--force' => true,
            ]);
        }

        app(\App\Services\Catalog\CountryCatalogStatsService::class)->refreshAll();

        $stats = $sync->syncPlatformsFromConfig();
        $catalog->flushCache();

        $this->info('Catalog sync complete.');
        $live = count((array) config('live_platforms.platforms', []));
        $global = count(CatalogSyncService::allConfigPlatforms()) - $live;
        $this->line("  Platforms in config: {$stats['platforms']} (live: {$live}, global A-Z: {$global})");
        $this->line("  Created: {$stats['created']}, Updated: {$stats['updated']}");
        $this->line('  Active platforms in DB: '.count($catalog->allPlatforms()));

        return self::SUCCESS;
    }
}
