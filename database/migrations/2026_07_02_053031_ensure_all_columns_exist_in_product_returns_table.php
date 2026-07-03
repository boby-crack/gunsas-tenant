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
        Schema::table('product_returns', function (Blueprint $table) {
            // 🔒 Cek & Tambah kolom shipment_id jika belum ada
            if (!Schema::hasColumn('product_returns', 'shipment_id')) {
                $table->unsignedBigInteger('shipment_id')->nullable()->after('durian_variety_id');
            }

            // 🔒 Cek & Tambah kolom status jika belum ada
            if (!Schema::hasColumn('product_returns', 'status')) {
                $table->string('status')->default('pending')->after('date');
            }

            // 🔒 Cek & Tambah kolom kuantitas verifikasi supplier jika belum ada
            if (!Schema::hasColumn('product_returns', 'supplier_accepted_qty_butir')) {
                $table->integer('supplier_accepted_qty_butir')->nullable()->after('qty_butir');
            }
            if (!Schema::hasColumn('product_returns', 'supplier_accepted_qty_kg')) {
                $table->decimal('supplier_accepted_qty_kg', 8, 3)->nullable()->after('qty_kg');
            }

            // 🔒 Cek & Tambah kolom nominal uang refund jika belum ada
            if (!Schema::hasColumn('product_returns', 'refund_amount')) {
                $table->decimal('refund_amount', 15, 2)->default(0)->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Jalur aman, tidak perlu drop jika tujuannya memperbaiki field yang tertinggal
    }
};