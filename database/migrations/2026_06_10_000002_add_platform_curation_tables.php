<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->string('status', 24)->default('live')->after('enabled');
            $table->string('source', 32)->default('config')->after('status');
            $table->unsignedTinyInteger('coverage_score')->nullable()->after('speed_score');
            $table->text('analysis_notes')->nullable()->after('settings');
            $table->timestamp('verified_at')->nullable()->after('analysis_notes');
            $table->foreignId('candidate_id')->nullable()->after('verified_at');

            $table->index(['status', 'enabled', 'primary_country']);
        });

        Schema::create('platform_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('domain');
            $table->string('base_url');
            $table->string('status', 24)->default('discovered');
            $table->string('source', 32)->default('manual');
            $table->string('adapter_guess', 32)->nullable();
            $table->unsignedTinyInteger('priority_score')->default(50);
            $table->unsignedTinyInteger('trust_estimate')->nullable();
            $table->unsignedInteger('listings_estimate')->nullable();
            $table->json('analysis')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['country_id', 'domain']);
            $table->index(['country_id', 'status']);
            $table->index(['status', 'category_id']);
        });

        Schema::table('platforms', function (Blueprint $table) {
            $table->foreign('candidate_id')->references('id')->on('platform_candidates')->nullOnDelete();
        });

        Schema::create('country_catalog_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('candidates_total')->default(0);
            $table->unsignedInteger('candidates_pending')->default(0);
            $table->unsignedInteger('platforms_live')->default(0);
            $table->unsignedInteger('platforms_verified')->default(0);
            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_catalog_stats');

        Schema::table('platforms', function (Blueprint $table) {
            $table->dropForeign(['candidate_id']);
            $table->dropColumn([
                'status', 'source', 'coverage_score', 'analysis_notes', 'verified_at', 'candidate_id',
            ]);
        });

        Schema::dropIfExists('platform_candidates');
    }
};
