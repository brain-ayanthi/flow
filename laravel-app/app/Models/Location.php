<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'phone', 'email', 'address', 'timezone', 'settings',
        'default_invoice_scheme_id', 'default_invoice_layout_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean'];
    }

    public function invoiceScheme(): BelongsTo
    {
        return $this->belongsTo(InvoiceScheme::class, 'default_invoice_scheme_id');
    }

    public function invoiceLayout(): BelongsTo
    {
        return $this->belongsTo(InvoiceLayout::class, 'default_invoice_layout_id');
    }

    public function invoiceSchemes(): HasMany
    {
        return $this->hasMany(InvoiceScheme::class);
    }

    public function invoiceLayouts(): HasMany
    {
        return $this->hasMany(InvoiceLayout::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'location_user')->withPivot('tenant_id')->withTimestamps();
    }
}
