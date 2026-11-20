<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLayout extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['location_id', 'name', 'layout', 'print_options', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'print_options' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
