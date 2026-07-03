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
    Schema::create('purchases', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->foreignId('durian_variety_id')->constrained()->onDelete('cascade');
        $table->string('supplier_name')->nullable();
        $table->integer('qty_butir')->default(0);
        $table->decimal('qty_kg', 8, 3);
        $table->decimal('price_per_kg', 15, 2);
        $table->decimal('total_amount', 15, 2);
        $table->string('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
