<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_code',
        'date',
        'inventory_item_id',
        'purchase_mode',
        'durian_variety_id',
        'supplier_name',
        'qty_butir',
        'qty_kg',
        'generic_qty',
        'generic_unit',
        'generic_unit_cost',
        'generic_total_amount',
        'price_per_kg',
        'total_amount',
        'notes',
    ];

    public function setSupplierCodeAttribute($value): void
    {
        $this->attributes['supplier_code'] = filled($value)
            ? strtoupper(trim((string) $value))
            : null;
    }

    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }
    public function inventoryItem() { return $this->belongsTo(InventoryItem::class); }
}
