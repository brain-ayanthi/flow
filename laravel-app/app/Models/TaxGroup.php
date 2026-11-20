<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxGroup extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['code', 'name', 'description', 'is_inclusive', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_inclusive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function rates(): BelongsToMany
    {
        return $this->belongsToMany(TaxRate::class, 'tax_group_rates')
            ->withPivot(['tenant_id', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
