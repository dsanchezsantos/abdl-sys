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
        Schema::create('cartoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feira')->constrained('feiras')->onDelete('cascade');
            $table->string('tag_code');
            $table->string('grupo')->nullable();
            $table->string('classificacao')->default('ALUNO');
            $table->string('identificacao_aluno')->nullable();
            $table->timestamps();

            $table->unique(['id_feira', 'tag_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartoes');
    }
};
