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
        Schema::table('feiras', function (Blueprint $table) {
            $table->string('endpoint_url')->nullable()->after('data_fim');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feiras', function (Blueprint $table) {
            $table->dropColumn('endpoint_url');
        });
    }
};
