<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintOrder extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'customer_id', 'assigned_user_id', 'location_id', 'job_date', 'contact_name', 'contact_phone',
        'whatsapp_phone', 'job_number', 'title', 'print_type', 'description', 'specifications',
        'quantity', 'unit', 'subtotal', 'discount', 'tax', 'round_off', 'total', 'due_at',
        'status', 'production_stage', 'priority', 'artwork_path', 'notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'job_date' => 'date',
            'specifications' => 'array',
            'quantity' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'print_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrintOrderItem::class, 'print_order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PrintOrderEvent::class, 'print_order_id')->latest();
    }
}
