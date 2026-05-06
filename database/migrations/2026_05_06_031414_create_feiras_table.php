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
        Schema::create('feiras', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim');
            $table->string('evento_id_api');
            $table->string('user_id_api');
            $table->dateTime('ultima_sincronizacao_em')->nullable();
            $table->string('status')->default('PLANEJADA');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feiras');
    }
};
