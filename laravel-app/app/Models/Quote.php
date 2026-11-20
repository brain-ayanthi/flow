<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'customer_id', 'location_id', 'converted_order_id', 'quote_number', 'title', 'status', 'currency',
        'issued_at', 'expiry_at', 'subtotal', 'discount', 'tax', 'total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expiry_at' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'converted_order_id');
    }
}
