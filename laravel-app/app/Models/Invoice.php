<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'customer_id', 'contact_id', 'print_order_id', 'location_id', 'invoice_scheme_id', 'invoice_layout_id',
        'created_by', 'finalized_by', 'invoice_number', 'issued_at', 'due_at', 'currency',
        'document_status', 'sale_channel', 'subtotal', 'discount', 'tax', 'round_off', 'total', 'paid_total',
        'status', 'tax_snapshot', 'due_reminder_sent_at', 'finalized_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'tax_snapshot' => 'array',
            'due_reminder_sent_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'contact_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function invoiceScheme(): BelongsTo
    {
        return $this->belongsTo(InvoiceScheme::class);
    }

    public function invoiceLayout(): BelongsTo
    {
        return $this->belongsTo(InvoiceLayout::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'print_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
