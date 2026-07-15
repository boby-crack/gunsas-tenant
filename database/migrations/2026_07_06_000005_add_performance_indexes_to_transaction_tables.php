<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['date'], 'sales_date_idx');
            $table->index(['outlet_id', 'date'], 'sales_outlet_date_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['date'], 'expenses_date_idx');
            $table->index(['outlet_id', 'date'], 'expenses_outlet_date_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['date'], 'purchases_date_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->index(['date'], 'shipments_date_idx');
            $table->index(['outlet_id', 'date'], 'shipments_outlet_date_idx');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->index(['date'], 'productions_date_idx');
            $table->index(['outlet_id', 'date'], 'productions_outlet_date_idx');
        });

        Schema::table('product_conversions', function (Blueprint $table) {
            $table->index(['date'], 'product_conversions_date_idx');
            $table->index(['outlet_id', 'date', 'conversion_type'], 'product_conversions_outlet_date_type_idx');
        });

        Schema::table('product_returns', function (Blueprint $table) {
            $table->index(['date'], 'product_returns_date_idx');
            $table->index(['outlet_id', 'date', 'status'], 'product_returns_outlet_date_status_idx');
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->index(['date'], 'stock_opnames_date_idx');
            $table->index(['outlet_id', 'date', 'product_type'], 'stock_opnames_outlet_date_type_idx');
        });

        Schema::table('sales_targets', function (Blueprint $table) {
            $table->index(['metric', 'outlet_id', 'period_start', 'period_end'], 'sales_targets_metric_outlet_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->dropIndex('sales_targets_metric_outlet_period_idx');
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropIndex('stock_opnames_date_idx');
            $table->dropIndex('stock_opnames_outlet_date_type_idx');
        });

        Schema::table('product_returns', function (Blueprint $table) {
            $table->dropIndex('product_returns_date_idx');
            $table->dropIndex('product_returns_outlet_date_status_idx');
        });

        Schema::table('product_conversions', function (Blueprint $table) {
            $table->dropIndex('product_conversions_date_idx');
            $table->dropIndex('product_conversions_outlet_date_type_idx');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropIndex('productions_date_idx');
            $table->dropIndex('productions_outlet_date_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_date_idx');
            $table->dropIndex('shipments_outlet_date_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_date_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_date_idx');
            $table->dropIndex('expenses_outlet_date_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_date_idx');
            $table->dropIndex('sales_outlet_date_idx');
        });
    }
};
