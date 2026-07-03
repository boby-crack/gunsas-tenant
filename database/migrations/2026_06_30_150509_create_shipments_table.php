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
    Schema::create('shipments', function (Blueprint $table) {
        $table->id();
        // 🔗 Menghubungkan logistik pengiriman ini ke data Outlet
        $table->foreignId('outlet_id')->constrained()->onDelete('cascade');
        $table->foreignId('durian_variety_id')->constrained()->onDelete('cascade');
        $table->date('date')->label('Tanggal');
        $table->decimal('modal_price', 15, 2); // HARGA MODAL /butir
        $table->integer('qty_sent_butir');     // QTY YANG DIKIRIM/butir
        $table->integer('qty_received_butir'); // QTY YANG DITERIMA /butir
        
        // Menghitung otomatis selisih butir di database
        $table->integer('qty_difference_butir')->virtualAs('qty_sent_butir - qty_received_butir');
        
        $table->decimal('qty_sent_kg', 8, 3);  // QTY YANG DIKIRIM/kg
        $table->decimal('average_weight', 8, 3); // RATA - RATA BERAT
        $table->decimal('value_purchase', 15, 2); // VALUE PURCHASE (Modal x Diterima)
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
