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

    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit',
        'durian_variety_id',
        'default_unit_cost',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'default_unit_cost' => 'float',
        'is_active' => 'boolean',
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
