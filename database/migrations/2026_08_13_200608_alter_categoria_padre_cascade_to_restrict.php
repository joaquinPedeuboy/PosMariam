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
        Schema::table('categorias_ecommerce', function (Blueprint $table) {
            $table->dropForeign(['categoria_padre_id']);
        });

        Schema::table('categorias_ecommerce', function (Blueprint $table) {
            $table->foreign('categoria_padre_id')
                ->references('id')->on('categorias_ecommerce')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categorias_ecommerce', function (Blueprint $table) {
            $table->dropForeign(['categoria_padre_id']);
        });

        Schema::table('categorias_ecommerce', function (Blueprint $table) {
            $table->foreign('categoria_padre_id')
                ->references('id')->on('categorias_ecommerce')
                ->onDelete('cascade');
        });
    }
};
