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
        Schema::create('mercado_pago_conciliaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->restrictOnDelete();

            // Identificador del pago consultado en Mercado Pago.
            $table->string('payment_id', 100)->unique();

            // Referencia externa recibida desde Mercado Pago.
            $table->string('external_reference')->nullable()->index();

            // Estado informado por Mercado Pago al detectar el incidente.
            $table->string('payment_status', 30)->nullable();

            // Motivo por el que el pago necesita revisión.
            $table->string('motivo', 60);

            $table->decimal('importe_recibido', 12, 2)->nullable();
            $table->string('moneda_recibida', 10)->nullable();

            // Solo guardamos información seleccionada, no toda la respuesta
            // del proveedor, que podría incluir datos personales innecesarios.
            $table->json('detalles')->nullable();

            $table->timestamp('resuelto_at')->nullable();
            $table->text('observaciones_resolucion')->nullable();

            $table->timestamps();

            $table->index(['resuelto_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_conciliaciones');
    }
};
