<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'durian_variety_id',
        'date',
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