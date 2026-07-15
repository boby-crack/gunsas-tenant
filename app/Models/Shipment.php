<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'inventory_item_id',
        'shipment_mode',
        'shipment_direction',
        'product_type',
        'durian_variety_id',
        'date',
        'modal_price',
        'qty_sent_butir',
        'qty_received_butir',
        'qty_sent_kg',
        'qty_received_kg',
        'generic_qty_sent',
        'generic_qty_received',
        'generic_unit',
        'generic_unit_cost',
        'generic_total_amount',
        'average_weight',
        'value_purchase',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function durianVariety()
    {
        return $this->belongsTo(DurianVariety::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
