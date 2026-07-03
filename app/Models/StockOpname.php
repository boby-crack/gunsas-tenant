<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = ['outlet_id', 'durian_variety_id', 'date', 'product_type', 'system_qty_kg', 'physical_qty_kg', 'difference_qty_kg', 'notes'];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }
}