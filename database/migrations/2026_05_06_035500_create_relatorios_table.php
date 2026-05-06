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
        Schema::dropIfExists('relatorios');

        Schema::create('relatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feira')->constrained('feiras')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->string('tipo'); // GERAL, POR_EDITORA, INCONSISTENCIAS_CATALOGO
            $table->json('parametros_filtro'); // Auditoria e Reprodutibilidade
            $table->string('status')->default('FILA');
            $table->string('caminho_arquivo')->nullable();
            $table->bigInteger('tamanho_bytes')->nullable();
            $table->text('mensagem_erro')->nullable();
            $table->integer('tempo_execucao_segundos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relatorios');
    }
};
