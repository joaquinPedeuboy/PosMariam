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
        Schema::table('venta_productos', function (Blueprint $table) {
            $table->dropForeign(['vencimiento_id']);
        });

        Schema::table('venta_productos', function (Blueprint $table) {
            $table->unsignedBigInteger('vencimiento_id')->nullable()->change();
        });

        Schema::table('venta_productos', function (Blueprint $table) {
            $table->foreign('vencimiento_id')
                  ->references('id')
                  ->on('producto_vencimientos')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_productos', function (Blueprint $table) {
            $table->dropForeign(['vencimiento_id']);
        });

        Schema::table('venta_productos', function (Blueprint $table) {
            $table->unsignedBigInteger('vencimiento_id')->nullable(false)->change();
        });

        Schema::table('venta_productos', function (Blueprint $table) {
            $table->foreign('vencimiento_id')
                  ->references('id')
                  ->on('producto_vencimientos')
                  ->onDelete('cascade');
        });
    }
};
