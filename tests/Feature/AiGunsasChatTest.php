<?php

namespace Tests\Feature;

use App\Livewire\AiGunsasChat;
use App\Models\Outlet;
use App\Services\GunsasAiAnalyst;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGunsasChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_from_question_detect_group_and_month(): void
    {
        Outlet::create([
            'name' => 'TIPTOP RAWAMANGUN',
            'group_name' => 'tiptop',
            'aliases' => 'tiptop rawamangun',
            'partner_share_percent' => 20,
        ]);

        $component = new AiGunsasChat();
        $method = new \ReflectionMethod($component, 'filtersFromQuestion');
        $method->setAccessible(true);

        $filters = $method->invoke($component, 'apakah bulan juli grup tiptop kita untung?');

        $this->assertSame('tiptop', $filters['outlet_group']);
        $this->assertNull($filters['outlet_id']);
        $this->assertSame('2026-07-01', $filters['date_from']);
        $this->assertSame('2026-07-31', $filters['date_until']);
    }

    public function test_profit_question_is_answered_from_local_calculation_without_ai_api(): void
    {
        $answer = app(GunsasAiAnalyst::class)->answer(
            'apakah bulan juli grup tiptop kita untung?',
            [
                'filters' => [
                    'date_from' => '2026-07-01',
                    'date_until' => '2026-07-31',
                    'outlet_name' => 'TipTop',
                ],
                'sales' => [
                    'net_sales' => 10000000,
                    'gunsas_revenue' => 8000000,
                ],
                'costs' => [
                    'hpp_sales' => 4500000,
                    'expenses' => 1000000,
                    'inventory_usage' => 250000,
                    'opname_loss' => 100000,
                ],
                'returns' => [
                    'loss_final' => 150000,
                ],
                'profit' => [
                    'net_profit' => 2000000,
                    'net_margin' => 25,
                    'net_asset_position' => 2500000,
                ],
                'inventory' => [
                    'amount' => 500000,
                ],
            ],
            [
                'sales' => [
                    'summary' => [
                        'records' => 3,
                    ],
                ],
            ],
        );

        $this->assertStringContainsString('TipTop periode 2026-07-01 sampai 2026-07-31 untung', $answer);
        $this->assertStringContainsString('Profit bersih: **Rp 2.000.000**', $answer);
        $this->assertStringContainsString('Margin bersih: **25,00%**', $answer);
    }
}
