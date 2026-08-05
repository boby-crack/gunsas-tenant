<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'inventory_item_id',
        'item_name',
        'category',
        'unit',
        'quantity',
        'unit_price',
        'gross_sales',
        'discount_amount',
        'sales_return_amount',
        'net_sales',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'gross_sales' => 'float',
        'discount_amount' => 'float',
        'sales_return_amount' => 'float',
        'net_sales' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
