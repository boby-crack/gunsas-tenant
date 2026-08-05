<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'grand_total_revenue', 'discount_amount', 'sales_return_amount', 'net_sales'
    ];

    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function durianVariety() { return $this->belongsTo(DurianVariety::class); }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function recalculateHeaderTotals(): void
    {
        $legacyGross = (float) $this->buah_subtotal + (float) $this->fresh_subtotal + (float) $this->frozen_subtotal;
        $itemGross = (float) $this->items()->sum('gross_sales');

        $gross = $legacyGross + $itemGross;
        $discount = (float) $this->discount_amount;
        $salesReturn = (float) $this->sales_return_amount;

        $this->forceFill([
            'grand_total_revenue' => $gross,
            'net_sales' => max(0, $gross - $discount - $salesReturn),
        ])->saveQuietly();
    }
}
