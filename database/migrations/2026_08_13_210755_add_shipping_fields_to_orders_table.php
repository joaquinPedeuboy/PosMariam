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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('estado_envio')->nullable()->after('estado');
            // pendiente | preparando | enviado | entregado
            // Nulo hasta que el pedido esté pagado (antes de eso no hay nada que envíar).

            $table->string('transportista')->nullable()->after('codigo_postal');
            $table->string('numero_seguimiento')->nullable()->after('transportista');

            $table->timestamp('enviado_at')->nullable()->after('pagado_at');
            $table->timestamp('entregado_at')->nullable()->after('enviado_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'estado_envio',
                'transportista',
                'numero_seguimiento',
                'enviado_at',
                'entregado_at',
            ]);
        });
    }
};
