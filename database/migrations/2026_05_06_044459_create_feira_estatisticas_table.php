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
        Schema::create("feira_estatisticas", function (Blueprint $table) {
            $table->id();
            $table->foreignId("id_feira")->unique()->constrained("feiras")->onDelete("cascade");
            
            $table->decimal("faturamento_bruto", 12, 2)->default(0);
            $table->decimal("faturamento_liquido_valido", 12, 2)->default(0);
            $table->decimal("ticket_medio", 12, 2)->default(0);
            
            $table->integer("total_livros_vendidos")->default(0);
            $table->integer("qtd_inconsistencias_catalogo")->default(0);
            
            $table->jsonb("dados_graficos")->nullable();
            
            $table->datetime("atualizado_em")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("feira_estatisticas");
    }
};
