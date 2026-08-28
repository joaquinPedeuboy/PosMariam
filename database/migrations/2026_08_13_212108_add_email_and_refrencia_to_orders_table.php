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
            // Snapshot del email al momento de la compra, mismo criterio que
            // nombre_contacto/telefono_contacto: si el cliente cambia el email
            // de su cuenta después, el pedido conserva con qué email se hizo.
            $table->string('email_contacto')->after('telefono_contacto');

            // Referencia del lugar de entrega (ej: "casa con portón verde",
            // "depto 4B, timbre no funciona"). Opcional.
            $table->string('referencia_direccion')->nullable()->after('codigo_postal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['email_contacto', 'referencia_direccion']);
        });
    }
};
