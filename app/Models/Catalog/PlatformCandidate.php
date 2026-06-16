<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlatformCandidate extends Model
{
    public const STATUS_DISCOVERED = 'discovered';

    public const STATUS_ANALYZING = 'analyzing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LIVE = 'live';

    protected $fillable = [
        'country_id',
        'category_id',
        'name',
        'domain',
        'base_url',
        'status',
        'source',
        'adapter_guess',
        'priority_score',
        'trust_estimate',
        'listings_estimate',
        'analysis',
        'review_notes',
        'analyzed_at',
        'reviewed_at',
    ];

    protected $casts = [
        'analysis' => 'array',
        'analyzed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<Country, PlatformCandidate> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Category, PlatformCandidate> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasOne<Platform> */
    public function platform(): HasOne
    {
        return $this->hasOne(Platform::class, 'candidate_id');
    }

    public function isReadyForReview(): bool
    {
        return in_array($this->status, [self::STATUS_NEEDS_REVIEW, self::STATUS_ANALYZING], true)
            && $this->analyzed_at !== null;
    }
}
