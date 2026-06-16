<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->string('provider_type', 32)->default('marketplace')->after('scraper');
            $table->string('connector_type', 32)->default('structured_scraper')->after('provider_type');
            $table->string('region', 8)->nullable()->after('primary_country');
            $table->json('search_capabilities')->nullable()->after('coverage_score');
        });

        Schema::create('provider_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('platform_slug', 64)->index();
            $table->string('category', 32)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedSmallInteger('result_count')->default(0);
            $table->boolean('success')->default(true);
            $table->string('error_message', 255)->nullable();
            $table->timestamp('searched_at');

            $table->index(['platform_slug', 'searched_at']);
            $table->index(['platform_slug', 'success']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_metrics');

        Schema::table('platforms', function (Blueprint $table) {
            $table->dropColumn([
                'provider_type',
                'connector_type',
                'region',
                'search_capabilities',
            ]);
        });
    }
};
