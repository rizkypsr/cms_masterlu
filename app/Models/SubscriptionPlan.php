<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plan';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'label',
        'price',
        'daily_limit',
        'duration_days',
        'features',
        'seq',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'daily_limit' => 'integer',
        'duration_days' => 'integer',
        'features' => 'array',
        'seq' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(Pengguna::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('seq');
    }
}
