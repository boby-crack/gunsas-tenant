<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('name');
        });

        DB::table('outlets')
            ->where('name', 'like', '%TIPTOP%')
            ->update(['group_name' => 'tiptop']);

        DB::table('outlets')
            ->where(function ($query) {
                $query->where('name', 'like', '%TOTAL%')
                    ->orWhere('name', 'like', '%TOTAL BUAH%');
            })
            ->update(['group_name' => 'total_buah']);

        DB::table('outlets')
            ->where('name', 'like', '%PUNCAK%')
            ->update(['group_name' => 'puncak']);
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('group_name');
        });
    }
};
