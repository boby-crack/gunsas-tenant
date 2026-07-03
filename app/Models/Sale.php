<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id', 
        'durian_variety_id', 
        'date',
        'buah_sold_kg', 'buah_sold_butir', 'buah_price_per_kg', 'buah_subtotal',
        'fresh_sold_kg', 'fresh_sold_pack', 'fresh_price_per_kg', 'fresh_subtotal',
        'frozen_sold_kg', 'frozen_sold_pack', 'frozen_price_per_kg', 'frozen_subtotal',
        'grand_total_revenue'
    ];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }
}