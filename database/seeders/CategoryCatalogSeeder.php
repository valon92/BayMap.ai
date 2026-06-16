<?php

namespace Database\Seeders;

use App\Models\Catalog\Category;
use App\Support\CategoryCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CategoryCatalog::ALL as $index => $slug) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::title(str_replace('_', ' ', $slug)),
                    'sort_order' => $index + 1,
                    'enabled' => true,
                ],
            );
        }

        Category::query()->updateOrCreate(
            ['slug' => 'marketplace'],
            ['name' => 'General Marketplace', 'sort_order' => 99, 'enabled' => true],
        );
    }
}
