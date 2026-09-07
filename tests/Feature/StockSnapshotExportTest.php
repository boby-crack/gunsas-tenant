<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSnapshotExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_snapshot_export_uses_get_route_download(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('reports.stock-snapshot.export', [
            'date_from' => '2026-08-01',
            'date_until' => '2026-08-31',
            'product_type' => 'Daging Olahan',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment; filename=laporan-sisa-stok-2026-08-01-2026-08-31.xlsx',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_stock_snapshot_page_accepts_outlet_filter_from_get_query(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'name' => 'TIPTOP RAWAMANGUN',
            'group_name' => 'tiptop',
            'partner_share_percent' => 15,
        ]);

        $response = $this->actingAs($user)->get('/admin/stock-snapshot?' . http_build_query([
            'filters' => [
                'date_from' => '2026-08-01',
                'date_until' => '2026-08-31',
                'outlet_ids' => [$outlet->id],
            ],
        ]));

        $response->assertOk();
        $response->assertSee('Sisa Stok');
        $response->assertSee('TIPTOP RAWAMANGUN');
    }
}
