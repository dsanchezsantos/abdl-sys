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
        Schema::table('feiras', function (Blueprint $table) {
            $table->string('ultimo_batch_id')->nullable();
            $table->string('status_integridade')->default('INTEGRO'); // INTEGRO, FALHA_PARCIAL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feiras', function (Blueprint $table) {
            $table->dropColumn(['ultimo_batch_id', 'status_integridade']);
        });
    }
};
