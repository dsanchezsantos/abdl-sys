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
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feira')->constrained('feiras')->onDelete('cascade');
            $table->string('sell_number');
            $table->bigInteger('pagamento_id_api');
            $table->string('tag_code')->nullable();
            $table->string('cpf')->nullable();
            $table->string('payment_way'); // Meio de pagamento
            $table->decimal('value', 12, 2);
            $table->string('payment_group')->nullable(); // Ex: Escola Municipal...
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            // Index para performance no "Filtro de Ouro"
            $table->index(['id_feira', 'sell_number']);
            $table->index(['id_feira', 'tag_code']);
            $table->index(['id_feira', 'payment_way']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
