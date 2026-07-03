<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->onDelete('cascade');
            $table->foreignId('durian_variety_id')->constrained()->onDelete('cascade');
            $table->date('date');

            // 1. JALUR PENJUALAN BUAH UTUH (UoM: KG)
            $table->decimal('buah_sold_kg', 8, 3)->default(0);
            $table->integer('buah_sold_butir')->default(0); // Alat bantu hitung fisik
            $table->decimal('buah_price_per_kg', 15, 2)->default(0);
            $table->decimal('buah_subtotal', 15, 2)->default(0);

            // 2. JALUR PENJUALAN DAGING FRESH (UoM: KG)
            $table->decimal('fresh_sold_kg', 8, 3)->default(0);
            $table->integer('fresh_sold_pack')->default(0); // Alat bantu hitung fisik
            $table->decimal('fresh_price_per_kg', 15, 2)->default(0);
            $table->decimal('fresh_subtotal', 15, 2)->default(0);

            // 3. JALUR PENJUALAN DURPAS FROZEN (UoM: KG)
            $table->decimal('frozen_sold_kg', 8, 3)->default(0);
            $table->integer('frozen_sold_pack')->default(0); // Alat bantu hitung fisik
            $table->decimal('frozen_price_per_kg', 15, 2)->default(0);
            $table->decimal('frozen_subtotal', 15, 2)->default(0);

            // TOTAL REVENUE HARI TERSEBUT
            $table->decimal('grand_total_revenue', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};