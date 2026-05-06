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
        Schema::create('itens_venda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feira')->constrained('feiras')->onDelete('cascade');
            $table->string('sell_number');
            $table->bigInteger('produto_id_api');
            $table->string('name'); // Fotografia do nome no dia da venda
            $table->integer('amount');
            $table->decimal('unit_value', 12, 2);
            $table->decimal('total_value', 12, 2);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            // Index para performance nas buscas por venda
            $table->index(['id_feira', 'sell_number']);
            $table->index(['id_feira', 'produto_id_api']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itens_venda');
    }
};
