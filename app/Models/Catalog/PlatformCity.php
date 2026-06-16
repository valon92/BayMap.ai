<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformCity extends Model
{
    protected $fillable = ['platform_id', 'city'];

    /** @return BelongsTo<Platform, PlatformCity> */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
