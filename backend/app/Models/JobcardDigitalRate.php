<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobcardDigitalRate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'particular_id', 'media_id', 'size_id', 'side_id', 'rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function particular(): BelongsTo
    {
        return $this->belongsTo(JobcardMaster::class, 'particular_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(JobcardMaster::class, 'media_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(JobcardMaster::class, 'size_id');
    }

    public function side(): BelongsTo
    {
        return $this->belongsTo(JobcardMaster::class, 'side_id');
    }
}
