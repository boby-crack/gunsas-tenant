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
    Schema::create('product_returns', function (Blueprint $table) {
        $table->id();
        $table->foreignId('outlet_id')->constrained()->onDelete('cascade');
        $table->foreignId('durian_variety_id')->constrained()->onDelete('cascade');
        $table->date('date');
        $table->string('return_reason_type'); 
        
        $table->integer('qty_butir'); // 🔄 Diubah dari qty_pack menjadi qty_butir
        $table->decimal('qty_kg', 8, 3);
        
        $table->string('detailed_reason')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_returns');
    }
};
