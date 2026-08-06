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
        Schema::table('editora_representantes', function (Blueprint $table) {
            $table->dropUnique('editora_representantes_id_feira_editora_representante_unique');
            $table->unique(['id_feira', 'editora'], 'editora_representantes_id_feira_editora_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editora_representantes', function (Blueprint $table) {
            $table->dropUnique('editora_representantes_id_feira_editora_unique');
            $table->unique(['id_feira', 'editora', 'representante'], 'editora_representantes_id_feira_editora_representante_unique');
        });
    }
};
