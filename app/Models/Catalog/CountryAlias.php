<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryAlias extends Model
{
    protected $fillable = ['country_id', 'alias'];

    /** @return BelongsTo<Country, CountryAlias> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
