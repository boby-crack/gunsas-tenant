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
    Schema::create('product_conversions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('outlet_id')->constrained()->onDelete('cascade');
        $table->foreignId('durian_variety_id')->constrained()->onDelete('cascade');
        $table->date('date');
        
        // Jenis konversi, misal: "Fresh Pack ke Olahan", "Bulk ke Pack", dll.
        $table->string('conversion_type'); 
        
        // Jumlah produk asal yang dikorbankan
        $table->integer('from_qty_pack');
        $table->decimal('from_qty_kg', 8, 3);
        
        // Jumlah produk hasil konversi baru
        $table->integer('to_qty_pack');
        $table->decimal('to_qty_kg', 8, 3);
        
        $table->string('notes')->nullable(); // Catatan tambahan
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_conversions');
    }
};
