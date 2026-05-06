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
        Schema::create('venda_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feira')->constrained('feiras')->onDelete('cascade');
            $table->string('sell_number');
            $table->integer('sale_type')->nullable();
            $table->decimal('total_value', 12, 2);
            $table->dateTime('date_hour');
            $table->string('box')->nullable();
            
            // Coluna vital para resiliência: indica se detalhes (itens/pagamentos) foram extraídos
            $table->boolean('processado')->default(false);
            
            // Espelho exato da resposta da API
            $table->json('raw_payload')->nullable();
            
            $table->timestamps();

            // Restrição única: id_feira + sell_number (Multi-Tenancy e Idempotência)
            $table->unique(['id_feira', 'sell_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venda_headers');
    }
};
