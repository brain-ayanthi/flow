<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintOrderItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['print_order_id', 'description', 'quantity', 'unit', 'unit_price', 'line_total', 'specifications'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'specifications' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'print_order_id');
    }
}
