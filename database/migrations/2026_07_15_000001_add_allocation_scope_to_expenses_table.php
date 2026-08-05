<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('allocation_scope')->nullable()->after('outlet_id')->index();
            $table->string('allocation_group')->nullable()->after('allocation_scope')->index();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn(['allocation_scope', 'allocation_group']);
        });
    }
};
