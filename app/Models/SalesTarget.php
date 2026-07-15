<?php

namespace App\Models;

use App\Services\SalesTargetCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'period_type',
        'period_start',
        'period_end',
        'metric',
        'target_amount',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'target_amount' => 'float',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function actualAmount(): float
    {
        return app(SalesTargetCalculator::class)->actualForTarget($this);
    }

    public function achievementPercentage(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        return ($this->actualAmount() / $this->target_amount) * 100;
    }
}
