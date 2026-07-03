<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id', 'durian_variety_id', 'date', 'conversion_type',
        'from_qty_pack', 'from_qty_kg', 'to_qty_pack', 'to_qty_kg', 'notes'
    ];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }
}