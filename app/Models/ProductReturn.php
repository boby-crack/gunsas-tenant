<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'durian_variety_id',
        'shipment_id',
        'return_type',
        'supplier_code',
        'paint_color',
        'date',
        'return_reason_type',
        'qty_butir',
        'qty_kg',
        'qty_to_supplier_butir',
        'qty_to_supplier_kg',
        'detailed_reason',
        'status',
        'supplier_accepted_qty_butir',
        'supplier_accepted_qty_kg',
        'refund_amount',
    ];

    public function setSupplierCodeAttribute($value): void
    {
        $this->attributes['supplier_code'] = filled($value)
            ? strtoupper(trim((string) $value))
            : null;
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function durianVariety()
    {
        return $this->belongsTo(DurianVariety::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
