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
        // 1. Limpar os livros duplicados mantendo apenas o registro com menor ID para cada combinação de feira e produto
        \Illuminate\Support\Facades\DB::statement("
            DELETE FROM livros a 
            USING livros b 
            WHERE a.id > b.id 
              AND a.id_feira = b.id_feira 
              AND a.produto = b.produto
        ");

        Schema::table('livros', function (Blueprint $table) {
            // 2. Remover restrição única antiga
            $table->dropUnique(['id_feira', 'produto_id_api']);
            
            // 3. Adicionar nova restrição única baseada no nome do produto
            $table->unique(['id_feira', 'produto']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('livros', function (Blueprint $table) {
            $table->dropUnique(['id_feira', 'produto']);
            $table->unique(['id_feira', 'produto_id_api']);
        });
    }
};
