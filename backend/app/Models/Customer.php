<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'phone', 'email', 'billing_address', 'shipping_address', 'notes',
        'is_customer', 'is_supplier', 'is_default', 'contact_code', 'tax_registration_number',
        'payment_term_days', 'opening_balance', 'opening_balance_type', 'credit_limit',
    ];

    protected function casts(): array
    {
        return [
            'is_customer' => 'boolean',
            'is_supplier' => 'boolean',
            'is_default' => 'boolean',
            'payment_term_days' => 'integer',
            'opening_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PrintOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
