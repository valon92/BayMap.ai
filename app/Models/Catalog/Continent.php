<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Continent extends Model
{
    protected $fillable = ['code', 'name', 'sort_order'];

    /** @return HasMany<Country> */
    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }
}
