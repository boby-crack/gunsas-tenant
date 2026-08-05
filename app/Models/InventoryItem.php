<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    public const FRUIT_CATEGORIES = [
        'buah_utuh',
        'kupas_fresh',
        'durpas_frozen',
    ];

    public const DURIAN_VARIANT_CATEGORIES = [
        'buah_utuh',
        'kupas_fresh',
        'durpas_frozen',
        'produk_olahan',
    ];

    public const SELLABLE_CATEGORIES = [
        'produk_jualan',
        'produk_olahan',
        'buah_utuh',
        'kupas_fresh',
        'durpas_frozen',
    ];

    public const CATEGORY_OPTIONS = [
        'Produk Jualan' => [
            'produk_jualan' => 'Produk Jualan Non-Durian',
            'produk_olahan' => 'Produk Olahan Durian',
        ],
        'Perlengkapan & Inventory' => [
            'packaging' => 'Packaging / Kemasan',
            'stiker' => 'Stiker & Label',
            'bahan_baku' => 'Bahan Baku',
            'operasional' => 'Perlengkapan Operasional',
            'lainnya' => 'Lainnya',
        ],
        'Produk Durian Sistem' => [
            'buah_utuh' => 'Buah Utuh',
            'kupas_fresh' => 'Kupas Fresh',
            'durpas_frozen' => 'Durpas Frozen',
        ],
    ];

    public static function categoryOptions(): array
    {
        return self::CATEGORY_OPTIONS;
    }

    public static function categoryLabel(?string $category): string
    {
        foreach (self::CATEGORY_OPTIONS as $options) {
            if (isset($options[$category])) {
                return $options[$category];
            }
        }

        return $category ? str($category)->replace('_', ' ')->title()->toString() : '-';
    }

    public static function isSellableCategory(?string $category): bool
    {
        return in_array($category, self::SELLABLE_CATEGORIES, true);
    }

    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit',
        'durian_variety_id',
        'default_unit_cost',
        'is_active',
        'is_sellable',
        'notes',
    ];

    protected $casts = [
        'default_unit_cost' => 'float',
        'is_active' => 'boolean',
        'is_sellable' => 'boolean',
    ];

    public function durianVariety()
    {
        return $this->belongsTo(DurianVariety::class);
    }

    public function isDurianProduct(): bool
    {
        return in_array($this->category, self::FRUIT_CATEGORIES, true);
    }
}
