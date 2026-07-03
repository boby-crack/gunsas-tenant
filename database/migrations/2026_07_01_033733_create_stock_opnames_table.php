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
    Schema::create('stock_opnames', function (Blueprint $table) {
        $table->id();
        $table->foreignId('outlet_id')->constrained()->onDelete('cascade');
        $table->foreignId('durian_variety_id')->constrained()->onDelete('cascade');
        $table->date('date');
        
        // Jenis produk yang dicek fisik (Buah Utuh / Kupas Fresh / Durpas Frozen)
        $table->string('product_type'); 
        
        $table->decimal('system_qty_kg', 8, 3);   // Berat di aplikasi (Contoh: 50 Kg)
        $table->decimal('physical_qty_kg', 8, 3); // Timbangan riil lapangan (Contoh: 45 Kg)
        
        // Selisih otomatis terhitung: Fisik - Sistem (Contoh: -5 Kg)
        $table->decimal('difference_qty_kg', 8, 3); 
        $table->string('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
