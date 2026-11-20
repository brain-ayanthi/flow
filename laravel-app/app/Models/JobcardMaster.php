<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobcardMaster extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'type', 'code', 'name', 'unit', 'rate', 'charge_mode', 'metadata', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function digitalRatesAsParticular(): HasMany
    {
        return $this->hasMany(JobcardDigitalRate::class, 'particular_id');
    }

    public function digitalRatesAsMedia(): HasMany
    {
        return $this->hasMany(JobcardDigitalRate::class, 'media_id');
    }

    public function digitalRatesAsSize(): HasMany
    {
        return $this->hasMany(JobcardDigitalRate::class, 'size_id');
    }

    public function digitalRatesAsSide(): HasMany
    {
        return $this->hasMany(JobcardDigitalRate::class, 'side_id');
    }
}
