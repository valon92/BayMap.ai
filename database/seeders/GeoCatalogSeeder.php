<?php

namespace Database\Seeders;

use App\Models\Catalog\Continent;
use App\Models\Catalog\Country;
use App\Models\Catalog\CountryAlias;
use App\Support\SearchCountryResolver;
use Illuminate\Database\Seeder;
use ReflectionClass;

class GeoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('catalog.continents', []) as $row) {
            Continent::query()->updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'sort_order' => $row['sort_order'] ?? 0],
            );
        }

        $continents = Continent::query()->pluck('id', 'code');
        $countries = require database_path('data/world_countries.php');

        foreach ($countries as $row) {
            $continentId = $continents[$row['continent']] ?? null;
            if ($continentId === null) {
                continue;
            }

            Country::query()->updateOrCreate(
                ['iso2' => $row['iso2']],
                [
                    'continent_id' => $continentId,
                    'iso3' => $row['iso3'] ?? null,
                    'name' => $row['name'],
                    'currency' => $row['currency'] ?? null,
                    'default_locale' => $row['locale'] ?? null,
                    'enabled' => true,
                ],
            );
        }

        // Kosovo — user-assigned ISO code used across BuyMap.ai
        $europeId = $continents['EU'] ?? null;
        if ($europeId !== null) {
            Country::query()->updateOrCreate(
                ['iso2' => 'XK'],
                [
                    'continent_id' => $europeId,
                    'iso3' => 'XKX',
                    'name' => 'Kosovo',
                    'currency' => 'EUR',
                    'default_locale' => 'sq-AL',
                    'enabled' => true,
                ],
            );
        }

        $this->seedAliasesFromResolver();
    }

    private function seedAliasesFromResolver(): void
    {
        $ref = new ReflectionClass(SearchCountryResolver::class);
        $prop = $ref->getConstant('ALIASES');
        if (! is_array($prop)) {
            return;
        }

        $countries = Country::query()->pluck('id', 'iso2');

        foreach ($prop as $alias => $meta) {
            $countryId = $countries[$meta['code']] ?? null;
            if ($countryId === null) {
                continue;
            }

            CountryAlias::query()->updateOrCreate(
                ['country_id' => $countryId, 'alias' => mb_strtolower($alias)],
            );
        }
    }
}
