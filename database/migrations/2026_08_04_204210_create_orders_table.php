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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique(); // ej: ORD-A3F92K1B, para mostrarle al cliente
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');

            $table->string('estado')->default('pendiente_pago');
            // pendiente_pago | pagado | fallido | cancelado

            $table->double('subtotal');
            $table->double('total');

            // Snapshot de datos de envío (no referenciamos una tabla de direcciones por ahora)
            $table->string('nombre_contacto');
            $table->string('telefono_contacto');
            $table->string('direccion_envio');
            $table->string('ciudad');
            $table->string('provincia');
            $table->string('codigo_postal')->nullable();

            $table->string('mercado_pago_preference_id')->nullable();
            $table->string('mercado_pago_payment_id')->nullable();
            $table->timestamp('pagado_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
