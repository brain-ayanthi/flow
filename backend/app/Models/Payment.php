<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'invoice_id', 'subscription_id', 'location_id', 'contact_id', 'payment_method_id', 'approved_by', 'amount', 'currency',
        'method', 'status', 'reference', 'receipt_path', 'provider_event_id',
        'provider_payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    protected $appends = ['receipt_number'];

    public function getReceiptNumberAttribute(): ?string
    {
        if (! $this->exists || $this->invoice_id === null || $this->status !== 'succeeded') {
            return null;
        }

        return 'RCP-' . str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'contact_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
