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
        Schema::create('erros_integracao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feira')->constrained('feiras')->onDelete('cascade');
            $table->integer('pagina');
            $table->jsonb('payload_recebido')->nullable();
            $table->text('mensagem_erro')->nullable();
            $table->string('status')->default('PENDENTE'); // PENDENTE, RESOLVIDO
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erros_integracao');
    }
};
