<?php

namespace Database\Seeders;

use App\Services\Catalog\CatalogSyncService;
use Illuminate\Database\Seeder;

class PlatformCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(CatalogSyncService::class)->syncPlatformsFromConfig();
    }
}
