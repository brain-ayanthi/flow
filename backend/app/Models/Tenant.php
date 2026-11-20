<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'default_currency', 'default_locale', 'timezone', 'financial_year_start_month',
        'financial_year_start_day', 'default_profit_margin', 'business_start_date', 'phone', 'address',
        'tax_registration_number', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'financial_year_start_month' => 'integer',
            'financial_year_start_day' => 'integer',
            'default_profit_margin' => 'decimal:2',
            'business_start_date' => 'date',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'role_id'])->withTimestamps();
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PrintOrder::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
