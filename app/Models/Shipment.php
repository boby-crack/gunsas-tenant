<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'durian_variety_id',
        'date',
        'modal_price',
        'qty_sent_butir',
        'qty_received_butir',
        'qty_sent_kg',
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
}