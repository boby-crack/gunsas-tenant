<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('productions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('outlet_id')->constrained()->onDelete('cascade');
        $table->foreignId('durian_variety_id')->constrained()->onDelete('cascade');
        $table->date('date');
        
        // 1. INPUT BAHAN BAKU
        $table->integer('qty_buah_butir');
        $table->decimal('qty_buah_kg', 8, 3);
        
        // 2. OUTPUT HASIL KUPASAN (2 Jalur Utama)
        $table->integer('qty_kupas_pack')->default(0);
        $table->decimal('qty_kupas_kg', 8, 3)->default(0); // Kupas Fresh
        
        $table->integer('qty_olahan_pack')->default(0);
        $table->decimal('qty_olahan_kg', 8, 3)->default(0); // Daging Olahan (Termasuk yang rusak)
        
        // 3. PERHITUNGAN OTOMATIS YIELD
        $table->decimal('total_usable_meat_kg', 8, 3); // Total daging didapat (Fresh + Olahan)
        $table->decimal('shrinkage_percentage', 5, 2);  // % Susut (Murni Kulit & Biji)
        $table->decimal('multiplier_factor', 5, 2);     // Angka Pengkali Modal
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
