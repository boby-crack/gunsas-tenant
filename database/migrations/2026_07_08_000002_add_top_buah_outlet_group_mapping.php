<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('outlets')
            ->where('name', 'like', '%TOP BUAH%')
            ->update(['group_name' => 'top_buah']);
    }

    public function down(): void
    {
        DB::table('outlets')
            ->where('group_name', 'top_buah')
            ->update(['group_name' => null]);
    }
};
