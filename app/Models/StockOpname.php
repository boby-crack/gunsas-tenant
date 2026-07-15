<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'inventory_item_id',
        'durian_variety_id',
        'date',
        'product_type',
        'system_qty_kg',
        'physical_qty_kg',
        'difference_qty_kg',
        'generic_unit',
        'generic_consumed_qty',
        'generic_unit_cost',
        'generic_consumed_amount',
        'notes',
    ];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }
    public function inventoryItem() { return $this->belongsTo(InventoryItem::class); }
}
