<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['location_id', 'code', 'name', 'method_type', 'settings', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
