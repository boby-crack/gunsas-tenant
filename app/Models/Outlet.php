<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'group_name',
        'location',
        'aliases',
        'partner_share_percent',
    ];

    public const GROUPS = [
        'tiptop' => 'TipTop',
        'total_buah' => 'Total Buah',
        'top_buah' => 'Top Buah',
        'puncak' => 'Puncak',
    ];

    public static function normalizeGroupName(?string $group): ?string
    {
        if (! filled($group)) {
            return null;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', trim($group)));

        if (array_key_exists($normalized, self::GROUPS)) {
            return $normalized;
        }

        foreach (self::GROUPS as $key => $label) {
            $labelKey = strtolower(str_replace([' ', '-'], '_', $label));

            if ($normalized === $labelKey) {
                return $key;
            }
        }

        return $normalized;
    }

    public function salesTargets()
    {
        return $this->hasMany(SalesTarget::class);
    }
}
