<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

class ProviderMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'platform_slug',
        'category',
        'country_code',
        'latency_ms',
        'result_count',
        'success',
        'error_message',
        'searched_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'searched_at' => 'datetime',
    ];
}
