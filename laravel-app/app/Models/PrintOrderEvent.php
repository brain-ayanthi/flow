<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintOrderEvent extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['print_order_id', 'changed_by', 'from_stage', 'to_stage', 'note', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PrintOrder::class, 'print_order_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
