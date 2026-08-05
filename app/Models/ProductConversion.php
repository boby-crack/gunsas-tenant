<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductConversion extends Model
{
    use HasFactory;

    public const TYPE_FRESH_TO_FROZEN = 'Kupas Fresh ke Kupas Frozen';
    public const TYPE_FRESH_LOSS = 'Kupas Fresh Loss / Olahan';

    protected $fillable = [
        'outlet_id', 'durian_variety_id', 'date', 'conversion_type',
        'from_qty_pack', 'from_qty_kg', 'to_qty_pack', 'to_qty_kg', 'notes'
    ];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }

    public static function conversionTypeOptions(): array
    {
        return [
            self::TYPE_FRESH_TO_FROZEN => 'Kupas Fresh ke Durpas Frozen',
            self::TYPE_FRESH_LOSS => 'Kupas Fresh Loss / Olahan',
        ];
    }

    public static function normalizeConversionType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        if ($normalized === '') {
            return self::TYPE_FRESH_TO_FROZEN;
        }

        if (str_contains($normalized, 'loss')
            || str_contains($normalized, 'olahan')
            || str_contains($normalized, 'busuk')
            || str_contains($normalized, 'rusak')
            || str_contains($normalized, 'spoil')) {
            return self::TYPE_FRESH_LOSS;
        }

        return self::TYPE_FRESH_TO_FROZEN;
    }

    public function isFreshLoss(): bool
    {
        return $this->conversion_type === self::TYPE_FRESH_LOSS;
    }
}
