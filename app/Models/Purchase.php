<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = ['supplier_code','date', 'durian_variety_id', 'supplier_name', 'qty_butir', 'qty_kg', 'price_per_kg', 'total_amount', 'notes'];

    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }
}