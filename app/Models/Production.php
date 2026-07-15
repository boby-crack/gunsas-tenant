<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    use HasFactory;

    public const SOURCE_NORMAL = 'normal';
    public const SOURCE_RETURN = 'return';

    public const SOURCES = [
        self::SOURCE_NORMAL => 'Stok Normal',
        self::SOURCE_RETURN => 'Buah Return',
    ];

    protected $fillable = [
        'outlet_id',
        'durian_variety_id',
        'date',
        'source_type',
        'qty_buah_butir',
        'qty_buah_kg',
        'qty_kupas_pack',
        'qty_kupas_kg',
        'qty_olahan_pack',
        'qty_olahan_kg',
        'total_usable_meat_kg',
        'shrinkage_percentage',
        'multiplier_factor',
    ];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }
}
