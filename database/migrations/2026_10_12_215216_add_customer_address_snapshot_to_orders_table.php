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
            // Estas columnas ya se agregaron antes del fallo.
            // No volver a crearlas:
            // customer_address_id, tipo_domicilio, calle

            $table->string('numero_calle', 30)->nullable();
            $table->string('piso', 20)->nullable();
            $table->string('departamento', 30)->nullable();
            $table->string('torre_edificio', 100)->nullable();
            $table->text('informacion_adicional')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_address_id',
                'tipo_domicilio',
                'calle',
                'numero_calle',
                'piso',
                'departamento',
                'torre_edificio',
                'informacion_adicional',
            ]);
        });
    }
};
