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
    Schema::create('expenses', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        // Jika diisi = biaya outlet tersebut. Jika KOSONG (null) = biaya pusat/global
        $table->foreignId('outlet_id')->nullable()->constrained()->onDelete('cascade'); 
        $table->string('category'); // Bensin, Listrik & Air, Gaji, Sewa Tempat, Perlengkapan, Lainnya
        $table->decimal('amount', 15, 2);
        $table->string('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
