<?php

namespace Tests\Feature;

use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\StockOpname;
use App\Models\WhatsappReport;
use App\Services\WhatsappReportApprover;
use App\Services\WhatsappReportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappReportParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_sales_and_multi_variety_opname_chat_is_parsed_and_approved(): void
    {
        $this->seedMasterDataForWhatsappSample();

        $parsed = app(WhatsappReportParser::class)->parse($this->sampleMixedReport());

        $this->assertSame('mixed', $parsed['report_type']);
        $this->assertSame('pending_approval', $parsed['status'], $parsed['error_notes'] ?? '');
        $this->assertCount(2, $parsed['parsed_payload']['reports']);

        $salesPayload = $parsed['parsed_payload']['reports'][0]['parsed_payload'];
        $opnamePayload = $parsed['parsed_payload']['reports'][1]['parsed_payload'];

        $this->assertSame('TB BSD', $salesPayload['outlet_name']);
        $this->assertSame('2026-08-02', $salesPayload['date']);
        $this->assertCount(21, $salesPayload['sales_items']);

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
        $this->assertSame(1, Sale::count());
        $this->assertSame(21, Sale::first()->items()->count());
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

        foreach (['MONTHONG', 'MUSANG KING', 'BLACKTHORN'] as $name) {
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
}
