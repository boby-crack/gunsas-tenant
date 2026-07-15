<?php

namespace Database\Seeders;

use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'gunsas@gmail.com'],
            [
                'name' => 'Gunsas Admin',
                'role' => 'super_admin',
                'is_active' => true,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $monthong = DurianVariety::updateOrCreate(['name' => 'MONTHONG']);

        foreach ([
            ['name' => 'TIPTOP RAWAMANGUN', 'location' => 'Rawamangun', 'aliases' => "rawamangun\nrwm\np rawamangun", 'partner_share_percent' => 15],
            ['name' => 'TIPTOP PONDOK BAMBU', 'location' => 'Pondok Bambu', 'aliases' => "pondok bambu\np bambu\nbambu", 'partner_share_percent' => 15],
            ['name' => 'TIPTOP PONDOK GEDE', 'location' => 'Pondok Gede', 'aliases' => "pondok gede\np gede\ngede", 'partner_share_percent' => 15],
        ] as $outlet) {
            Outlet::updateOrCreate(
                ['name' => $outlet['name']],
                $outlet,
            );
        }

        foreach ([
            [
                'name' => 'Buah Utuh MONTHONG',
                'sku' => 'DUR-MONTHONG-BUAH',
                'category' => 'buah_utuh',
                'unit' => 'kg',
                'durian_variety_id' => $monthong->id,
                'default_unit_cost' => 0,
                'notes' => 'Produk durian utama untuk stok buah utuh.',
            ],
            [
                'name' => 'Kupas Fresh MONTHONG',
                'sku' => 'DUR-MONTHONG-FRESH',
                'category' => 'kupas_fresh',
                'unit' => 'kg',
                'durian_variety_id' => $monthong->id,
                'default_unit_cost' => 0,
                'notes' => 'Modal dihitung dari hasil produksi.',
            ],
            [
                'name' => 'Durpas Frozen MONTHONG',
                'sku' => 'DUR-MONTHONG-FROZEN',
                'category' => 'durpas_frozen',
                'unit' => 'kg',
                'durian_variety_id' => $monthong->id,
                'default_unit_cost' => 0,
                'notes' => 'Modal dihitung dari konversi fresh ke frozen.',
            ],
            [
                'name' => 'Thinwall',
                'sku' => 'SUP-THINWALL',
                'category' => 'packaging',
                'unit' => 'pcs',
                'durian_variety_id' => null,
                'default_unit_cost' => 0,
                'notes' => 'Packaging untuk daging durian.',
            ],
            [
                'name' => 'Stiker Batang',
                'sku' => 'SUP-STIKER-BATANG',
                'category' => 'packaging',
                'unit' => 'pcs',
                'durian_variety_id' => null,
                'default_unit_cost' => 0,
                'notes' => 'Stiker penanda buah.',
            ],
            [
                'name' => 'Stiker Durpas',
                'sku' => 'SUP-STIKER-DURPAS',
                'category' => 'packaging',
                'unit' => 'pcs',
                'durian_variety_id' => null,
                'default_unit_cost' => 0,
                'notes' => 'Stiker untuk produk durpas frozen.',
            ],
            [
                'name' => 'Sendok Tester',
                'sku' => 'SUP-SENDOK-TESTER',
                'category' => 'perlengkapan',
                'unit' => 'pack',
                'durian_variety_id' => null,
                'default_unit_cost' => 0,
                'notes' => 'Perlengkapan tester outlet.',
            ],
            [
                'name' => 'Tusuk Gigi',
                'sku' => 'SUP-TUSUK-GIGI',
                'category' => 'perlengkapan',
                'unit' => 'pack',
                'durian_variety_id' => null,
                'default_unit_cost' => 0,
                'notes' => 'Perlengkapan outlet.',
            ],
            [
                'name' => 'Sarung Tangan Plastik',
                'sku' => 'SUP-SARUNG-TANGAN-PLASTIK',
                'category' => 'perlengkapan',
                'unit' => 'pack',
                'durian_variety_id' => null,
                'default_unit_cost' => 0,
                'notes' => 'Perlengkapan handling produk.',
            ],
        ] as $item) {
            InventoryItem::updateOrCreate(
                ['sku' => $item['sku']],
                ['is_active' => true, ...$item],
            );
        }
    }
}
