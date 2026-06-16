<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryCatalogStat extends Model
{
    protected $fillable = [
        'country_id',
        'candidates_total',
        'candidates_pending',
        'platforms_live',
        'platforms_verified',
        'last_analyzed_at',
    ];

    protected $casts = [
        'last_analyzed_at' => 'datetime',
    ];

    /** @return BelongsTo<Country, CountryCatalogStat> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
