<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuando el webhook confirma un pago pero no hay stock físico
     * suficiente para cubrirlo (venta simultánea de las últimas unidades),
     * acá queda registrado cuántas unidades de ESTE item puntual no se
     * pudieron descontar. null = todo se descontó normalmente.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('faltante')->nullable()->after('cantidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('faltante');
        });
    }
};
