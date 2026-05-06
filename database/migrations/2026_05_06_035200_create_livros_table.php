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
        Schema::create('livros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feira')->constrained('feiras')->onDelete('cascade');
            $table->bigInteger('produto_id_api');
            $table->string('produto'); // Nome do livro (higienizado e em UPPERCASE no Service)
            $table->decimal('valor', 12, 2);
            
            // Campos de Enriquecimento Analítico (Manuais)
            $table->string('editora')->default('NAO INFORMADO');
            $table->string('representante')->default('NAO INFORMADO');
            $table->string('categoria')->nullable()->default('NAO INFORMADO');
            $table->string('isbn')->default('NAO INFORMADO');
            
            $table->timestamps();

            // Restrição única: id_feira + produto_id_api (Duplo ID)
            $table->unique(['id_feira', 'produto_id_api']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livros');
    }
};
