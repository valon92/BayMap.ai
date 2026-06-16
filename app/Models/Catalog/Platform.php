<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ANALYZING = 'analyzing';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_LIVE = 'live';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'slug',
        'label',
        'base_url',
        'adapter',
        'scraper',
        'provider_type',
        'connector_type',
        'primary_country',
        'region',
        'is_global',
        'priority',
        'trust_score',
        'speed_score',
        'coverage_score',
        'search_capabilities',
        'settings',
        'analysis_notes',
        'enabled',
        'status',
        'source',
        'verified_at',
        'candidate_id',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'enabled' => 'boolean',
        'settings' => 'array',
        'search_capabilities' => 'array',
        'verified_at' => 'datetime',
    ];

    /**
     * Platforms eligible for Valon worker routing.
     */
    public function scopeRoutable($query)
    {
        return $query
            ->where('enabled', true)
            ->whereIn('status', [self::STATUS_LIVE, self::STATUS_VERIFIED]);
    }

    /** @return BelongsTo<PlatformCandidate, Platform> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(PlatformCandidate::class, 'candidate_id');
    }

    /** @return BelongsToMany<Category> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_platform')
            ->withPivot(['priority'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Country> */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'country_platform')
            ->withPivot(['priority', 'enabled'])
            ->withTimestamps();
    }

    /** @return HasMany<PlatformCity> */
    public function cities(): HasMany
    {
        return $this->hasMany(PlatformCity::class);
    }

    /**
     * Shape expected by LivePlatformRegistry / scrapers.
     *
     * @return array<string, mixed>
     */
    public function toRegistryArray(): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return array_merge($settings, array_filter([
            'label' => $this->label,
            'adapter' => $this->adapter,
            'scraper' => $this->scraper,
            'provider_type' => $this->provider_type,
            'connector_type' => $this->connector_type,
            'country' => $this->primary_country,
            'region' => $this->region,
            'base_url' => $this->base_url,
            'priority' => $this->priority,
            'trust_score' => $this->trust_score,
            'speed_score' => $this->speed_score,
            'status' => $this->status,
            'search_capabilities' => $this->search_capabilities,
            'global' => $this->is_global,
            'categories' => $this->categories->pluck('slug')->values()->all(),
            'cities' => $this->cities->pluck('city')->values()->all(),
        ], fn ($value) => $value !== null && $value !== []));
    }

    /**
     * Normalized provider record for Global Provider Registry.
     *
     * @return array<string, mixed>
     */
    public function toProviderRecord(): array
    {
        return app(\App\Services\Providers\GlobalProviderRegistry::class)
            ->normalizeRecord($this->slug, $this->toRegistryArray());
    }
}
