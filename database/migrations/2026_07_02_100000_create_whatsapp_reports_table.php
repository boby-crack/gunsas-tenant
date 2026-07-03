<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_reports', function (Blueprint $table) {
            $table->id();
            $table->string('sender')->nullable();
            $table->text('raw_message');
            $table->string('report_type')->nullable();
            $table->json('parsed_payload')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('status')->default('needs_review');
            $table->text('error_notes')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_reports');
    }
};
