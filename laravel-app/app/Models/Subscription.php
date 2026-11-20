<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'plan_id', 'status', 'billing_period', 'price_at_signup', 'currency',
        'starts_at', 'trial_ends_at', 'current_period_starts_at',
        'current_period_ends_at', 'cancelled_at', 'gateway', 'gateway_reference',
        'plan_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'price_at_signup' => 'decimal:2',
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'plan_snapshot' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
