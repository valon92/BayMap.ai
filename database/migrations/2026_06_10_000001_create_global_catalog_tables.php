<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continent_id')->constrained()->cascadeOnDelete();
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->nullable();
            $table->string('name');
            $table->string('currency', 3)->nullable();
            $table->string('default_locale', 12)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['continent_id', 'enabled']);
        });

        Schema::create('country_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->timestamps();

            $table->unique(['country_id', 'alias']);
            $table->index('alias');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('base_url')->nullable();
            $table->string('adapter', 32)->default('generic');
            $table->string('scraper', 64)->nullable();
            $table->string('primary_country', 2)->nullable();
            $table->boolean('is_global')->default(false);
            $table->unsignedSmallInteger('priority')->default(50);
            $table->unsignedTinyInteger('trust_score')->default(70);
            $table->unsignedTinyInteger('speed_score')->default(75);
            $table->json('settings')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'primary_country']);
            $table->index(['enabled', 'is_global']);
        });

        Schema::create('category_platform', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->nullable();
            $table->timestamps();

            $table->unique(['platform_id', 'category_id']);
            $table->index(['category_id', 'platform_id']);
        });

        Schema::create('country_platform', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['platform_id', 'country_id']);
            $table->index(['country_id', 'enabled']);
        });

        Schema::create('platform_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('city');
            $table->timestamps();

            $table->unique(['platform_id', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_cities');
        Schema::dropIfExists('country_platform');
        Schema::dropIfExists('category_platform');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('country_aliases');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('continents');
    }
};
