<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxRate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'code', 'name', 'calculation_type', 'rate', 'is_compound',
        'valid_from', 'valid_until', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_compound' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(TaxGroup::class, 'tax_group_rates')
            ->withPivot(['tenant_id', 'sort_order'])
            ->withTimestamps();
    }
}
