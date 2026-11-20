<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceScheme extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'location_id', 'code', 'name', 'prefix', 'suffix', 'next_number', 'padding',
        'reset_period', 'last_reset_key', 'is_active',
    ];

    protected function casts(): array
    {
        return ['next_number' => 'integer', 'padding' => 'integer', 'is_active' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
