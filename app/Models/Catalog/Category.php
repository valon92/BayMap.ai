<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['slug', 'name', 'sort_order', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /** @return BelongsToMany<Platform> */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'category_platform')
            ->withPivot(['priority'])
            ->withTimestamps();
    }
}
