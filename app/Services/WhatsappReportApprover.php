<?php

namespace App\Services;

use App\Models\ProductConversion;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\WhatsappReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WhatsappReportApprover
{
    public function approve(WhatsappReport $report): array
    {
        if ($report->status === 'approved') {
            return [
                'ok' => false,
                'message' => 'Draft #' . $report->id . ' sudah pernah di-approve.',
            ];
        }

        $payload = $report->parsed_payload ?? [];
        $missingFields = $payload['missing_fields'] ?? [];

        if (! empty($missingFields)) {
            return [
                'ok' => false,
                'message' => 'Draft ini belum lengkap. Yang kurang: ' . implode(', ', $missingFields),
            ];
        }

        return DB::transaction(function () use ($report, $payload) {
            $target = match ($report->report_type) {
                'retur' => $this->createProductReturn($payload),
                'kupas' => $this->createProduction($payload),
                'frozen' => $this->createProductConversion($payload),
                default => null,
            };

            if (! $target) {
                return [
                    'ok' => false,
                    'message' => 'Jenis laporannya belum kebaca, jadi belum bisa aku approve.',
                ];
            }

            $report->update([
                'status' => 'approved',
                'target_type' => $target::class,
                'target_id' => $target->id,
                'approved_at' => now(),
            ]);

            return [
                'ok' => true,
                'message' => 'Siap, draft #' . $report->id . ' sudah aku approve.',
                'target' => $target,
                'target_label' => $this->targetLabel($target),
            ];
        });
    }

    private function createProductReturn(array $payload): ProductReturn
    {
        return ProductReturn::create([
            'outlet_id' => $payload['outlet_id'],
            'durian_variety_id' => $payload['durian_variety_id'],
            'date' => $payload['date'],
            'supplier_code' => $payload['supplier_code'] ?? null,
            'paint_color' => $payload['paint_color'] ?? null,
            'return_reason_type' => $payload['return_reason_type'] ?? 'Buah Rusak / Asam',
            'qty_butir' => $payload['qty_butir'] ?? 0,
            'qty_kg' => $payload['qty_kg'],
            'detailed_reason' => $payload['detailed_reason'] ?? 'Input dari WhatsApp',
            'status' => 'pending',
            'refund_amount' => 0,
        ]);
    }

    private function createProduction(array $payload): Production
    {
        return Production::create([
            'outlet_id' => $payload['outlet_id'],
            'durian_variety_id' => $payload['durian_variety_id'],
            'date' => $payload['date'],
            'qty_buah_butir' => $payload['qty_buah_butir'] ?? 0,
            'qty_buah_kg' => $payload['qty_buah_kg'],
            'qty_kupas_pack' => $payload['qty_kupas_pack'] ?? 0,
            'qty_kupas_kg' => $payload['qty_kupas_kg'],
            'qty_olahan_pack' => $payload['qty_olahan_pack'] ?? 0,
            'qty_olahan_kg' => $payload['qty_olahan_kg'] ?? 0,
            'total_usable_meat_kg' => $payload['total_usable_meat_kg'] ?? (($payload['qty_kupas_kg'] ?? 0) + ($payload['qty_olahan_kg'] ?? 0)),
            'shrinkage_percentage' => $payload['shrinkage_percentage'] ?? 0,
            'multiplier_factor' => $payload['multiplier_factor'] ?? 0,
        ]);
    }

    private function createProductConversion(array $payload): ProductConversion
    {
        return ProductConversion::create([
            'outlet_id' => $payload['outlet_id'],
            'durian_variety_id' => $payload['durian_variety_id'],
            'date' => $payload['date'],
            'conversion_type' => 'Kupas Fresh ke Kupas Frozen',
            'from_qty_pack' => $payload['from_qty_pack'] ?? 0,
            'from_qty_kg' => $payload['from_qty_kg'],
            'to_qty_pack' => $payload['to_qty_pack'] ?? 0,
            'to_qty_kg' => $payload['to_qty_kg'],
            'notes' => $payload['notes'] ?? 'Input dari WhatsApp',
        ]);
    }

    private function targetLabel(Model $target): string
    {
        return class_basename($target) . ' #' . $target->id;
    }
}
