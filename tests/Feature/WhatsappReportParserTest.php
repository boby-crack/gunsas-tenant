<?php

namespace Tests\Feature;

use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\StockOpname;
use App\Models\WhatsappReport;
use App\Services\WhatsappReportApprover;
use App\Services\WhatsappReportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappReportParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_opname_header_ignores_sales_text_before_it_and_approves_opname(): void
    {
        $this->seedMasterDataForWhatsappSample();

        $parsed = app(WhatsappReportParser::class)->parse($this->sampleMixedReport());
        $opnamePayload = $parsed['parsed_payload'];

        $this->assertSame('opname', $parsed['report_type']);
        $this->assertSame('pending_approval', $parsed['status'], $parsed['error_notes'] ?? '');
        $this->assertArrayNotHasKey('reports', $opnamePayload);
        $this->assertArrayNotHasKey('sales_items', $opnamePayload);

        $this->assertSame('TB Bintaro', $opnamePayload['outlet_name']);
        $this->assertSame('2026-07-12', $opnamePayload['date']);
        $this->assertCount(5, $opnamePayload['opname_items']);
        $this->assertCount(5, $opnamePayload['inventory_items']);

        $report = WhatsappReport::create([
            'sender' => '628123',
            'raw_message' => $this->sampleMixedReport(),
            'report_type' => $parsed['report_type'],
            'parsed_payload' => $parsed['parsed_payload'],
            'confidence' => $parsed['confidence'],
            'status' => $parsed['status'],
            'error_notes' => $parsed['error_notes'],
        ]);

        $result = app(WhatsappReportApprover::class)->approve($report);

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame('approved', $report->refresh()->status);
        $this->assertSame(10, StockOpname::count());
    }

    public function test_whatsapp_webhook_secret_blocks_invalid_requests(): void
    {
        config()->set('services.fonnte.webhook_secret', 'secret-123');

        $this->postJson('/webhook/whatsapp', [
            'sender' => '628123',
            'message' => 'halo',
        ])->assertForbidden();

        $this->assertSame(0, WhatsappReport::count());
    }

    public function test_whatsapp_webhook_secret_allows_valid_requests(): void
    {
        config()->set('services.fonnte.webhook_secret', 'secret-123');

        $this->postJson('/webhook/whatsapp', [
            'sender' => '628123',
            'message' => 'halo',
        ], [
            'X-Webhook-Secret' => 'secret-123',
        ])->assertOk();

        $this->assertSame(1, WhatsappReport::count());
    }

    public function test_weekly_stock_opname_keeps_operational_items_out_of_sales(): void
    {
        $this->seedMasterDataForWhatsappSample();

        $parsed = app(WhatsappReportParser::class)->parse($this->sampleWeeklyOpnameReport());
        $payload = $parsed['parsed_payload'];

        $this->assertSame('opname', $parsed['report_type']);
        $this->assertSame('pending_approval', $parsed['status'], $parsed['error_notes'] ?? '');
        $this->assertArrayNotHasKey('reports', $payload);
        $this->assertSame('TIPTOP RAWAMANGUN', $payload['outlet_name']);
        $this->assertSame('2026-08-23', $payload['date']);

        $this->assertCount(6, $payload['opname_items']);
        $this->assertCount(7, $payload['inventory_items']);

        $frozenByVariety = collect($payload['opname_items'])
            ->where('product_type', 'Daging Frozen')
            ->keyBy('durian_variety_name');

        $this->assertSame(0.765, $frozenByVariety['MONTHONG']['physical_qty_kg']);
        $this->assertSame(3.0, $frozenByVariety['MONTHONG']['physical_qty_pack']);
        $this->assertSame(4.356, $frozenByVariety['BAWOR']['physical_qty_kg']);
        $this->assertSame(13.0, $frozenByVariety['BAWOR']['physical_qty_pack']);
        $this->assertSame(0.268, $frozenByVariety['MASMUAR']['physical_qty_kg']);
        $this->assertSame(1.0, $frozenByVariety['MASMUAR']['physical_qty_pack']);
        $this->assertSame(0.39, $frozenByVariety['LOKAL PREMIUM']['physical_qty_kg']);
        $this->assertSame(1.0, $frozenByVariety['LOKAL PREMIUM']['physical_qty_pack']);

        $inventoryByName = collect($payload['inventory_items'])->keyBy('inventory_item_name');

        $this->assertSame(75.0, $inventoryByName['soaker pad']['physical_qty']);
        $this->assertSame(2.0, $inventoryByName['Sarung Tangan Plastik']['physical_qty']);
    }

    private function seedMasterDataForWhatsappSample(): void
    {
        Outlet::create([
            'name' => 'TB BSD',
            'aliases' => 'tb bsd',
            'partner_share_percent' => 20,
        ]);

        Outlet::create([
            'name' => 'TB Bintaro',
            'aliases' => 'tb bintaro',
            'partner_share_percent' => 20,
        ]);

        Outlet::create([
            'name' => 'TIPTOP RAWAMANGUN',
            'aliases' => 'tiptop rawamangun',
            'partner_share_percent' => 20,
        ]);

        foreach (['MONTHONG', 'MUSANG KING', 'BLACKTHORN', 'BAWOR', 'MASMUAR', 'LOKAL PREMIUM'] as $name) {
            DurianVariety::create(['name' => $name]);
        }

        foreach ([
            'Pancake isi 2',
            'Pancake isi 8',
            'Es Krim Kecil',
            'Es Krim Besar',
            'Brunchesee Durian',
            'Soes Durian',
            'Soes Coklat',
            'Strudel Durian',
            'Strudel Coklat',
            'Durian Brulee',
            'Mille Crepe Durian',
            'Eggtart isi 2 Durian',
            'Eggtart isi 2 Blueberry',
            'Eggtart isi 2 Cheese',
            'Eggtart isi 4 Mix',
            'Mochi 2 Durian',
            'Mochi 2 Coklat',
            'Mochi 2 Cream Cheese',
            'Mochi 6 Durian',
            'Mochi 6 Coklat',
            'Mochi 6 Cream Cheese',
            'Brownies',
        ] as $name) {
            InventoryItem::create([
                'name' => $name,
                'category' => 'produk_jualan',
                'unit' => 'unit',
                'default_unit_cost' => 0,
                'is_active' => true,
                'is_sellable' => true,
            ]);
        }

        foreach ([
            ['Thinwall', 'pcs'],
            ['Stiker Batang', 'pcs'],
            ['Stiker Durpas', 'pcs'],
            ['Sarung Tangan Plastik', 'pack'],
            ['tissue', 'pack'],
            ['Sendok Tester', 'pack'],
            ['Tusuk Gigi', 'pack'],
            ['soaker pad', 'pcs'],
        ] as [$name, $unit]) {
            InventoryItem::create([
                'name' => $name,
                'category' => 'packaging',
                'unit' => $unit,
                'default_unit_cost' => 0,
                'is_active' => true,
                'is_sellable' => false,
            ]);
        }
    }

    private function sampleMixedReport(): string
    {
        return <<<'TEXT'
Tanggal :2 Agustus
Nama outlet: TB BSD
Pancake isi 2 : 1
Pancake isi 8 : 1
Eskrim kecil :13
Eskrim besar :6
Burnchesee durian :8
Soes durian:2
Soes coklat :0
Strudel durian:2
Strudel coklat:1
Durian brulle :14
Milecrape durian:6
Eggtart isi 2 durian : 4
Eggtart isi 2 blueberry:2
Eggtart isi 2 chesee: 3
Eggtart isi 4 mix : 1
Mochi 2 durian:6
Mochi 2 coklat:6
Mochi 2 Cream cheese:2
Mochi 6 durian:7
Mochi 6 coklat:3
Mochi 6 Cream cheese:5
Brownies :3
STOK OPNAME MINGGUAN
12 JULI 2026
NAMA OTLET :  TB Bintaro
JENIS DURIAN : MONTHONG
BUAH UTUH (butir):  -
BUAH UTUH (kg) : -kg
KUPAS FRESH (pack) : -
KUPAS FRESH ( kg ) : - kg
DURPAS FROZEN ( pack) : 58
DURPAS FROZEN (kg) : 27,985 kg
JENIS DURIAN : MUSANG KING
BUAH UTUH (butir):  1
BUAH UTUH (kg) : 2,02kg
KUPAS FRESH (pack) : 4
KUPAS FRESH ( kg ) : 1,06kg
DURPAS FROZEN ( pack) : -
DURPAS FROZEN (kg) : - kg
JENIS DURIAN : BLACKTHORN
BUAH UTUH (butir):  1
BUAH UTUH (kg) : 1,60kg
KUPAS FRESH (pack) : 1
KUPAS FRESH ( kg ) : 0,480kg
DURPAS FROZEN ( pack) : -
DURPAS FROZEN (kg) : - kg
THINWALL : 28 pcs
Stiker batang:  88 pcs
Stiker durpas : 102 pcs
Handglove : - pack
Tisu : - pack
Sendok tester: 3pack
Tusuk gigi: 2pack
++++++++++++++++++++++
TEXT;
    }

    private function sampleWeeklyOpnameReport(): string
    {
        return <<<'TEXT'
STOK OPNAME MINGGUAN

Tanggal : 23 Agustus 2026
NAMA OTLET : Tiptop Rawamangun
JENIS DURIAN : monthong 
BUAH UTUH (butir): 6
BUAH UTUH (kg) : 16.906kg
KUPAS FRESH (pack) : 4
KUPAS FRESH ( kg ) : 1.076
DURPAS FROZEN MONTHONG (pack) : 3 
DURPAS FROZEN MONTHONG (kg) : 765gr

Jenis durian : Bawor
DURPAS FROZEN BAWOR ( pack) : 13
DURPAS FROZEN BAWOR (kg) : 4.356kg

Jenis durian : Masmuar
DURPAS FROZEN MASMUAR (pack) : 1 (268gr) 
DURPAS FROZEN LOKAL PREMIUM : 1 (390gr) 
 
Thinwall  :  4pcs
Stiker batang: 40pcs 
Stiker durpas : 210pcs
Sendok tester : 1 pack
Tusuk gigi : 1
Sarung tangan plastik : 2 pack
Soakerpad : 75pcs
TEXT;
    }
}
