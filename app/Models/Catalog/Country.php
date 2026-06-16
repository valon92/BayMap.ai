<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'continent_id',
        'iso2',
        'iso3',
        'name',
        'currency',
        'default_locale',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /** @return HasOne<CountryCatalogStat> */
    public function catalogStat(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CountryCatalogStat::class);
    }

    /** @return BelongsTo<Continent, Country> */
    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class);
    }

    /** @return HasMany<CountryAlias> */
    public function aliases(): HasMany
    {
        return $this->hasMany(CountryAlias::class);
    }

    /** @return BelongsToMany<Platform> */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'country_platform')
            ->withPivot(['priority', 'enabled'])
            ->withTimestamps();
    }
}
