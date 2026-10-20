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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Nombre identificativo: Casa, Trabajo, etc.
            $table->string('nombre', 100);

            // casa | departamento | otro
            $table->string('tipo_domicilio', 30);

            $table->string('calle', 150);
            // String para permitir números como S/N.
            $table->string('numero', 30);

            $table->string('piso', 20)->nullable();
            $table->string('departamento', 30)->nullable();
            $table->string('torre_edificio', 100)->nullable();

            $table->text('informacion_adicional')->nullable();
            $table->string('referencia_envio', 255)->nullable();

            $table->string('ciudad', 100);
            $table->string('provincia', 100);
            $table->string('codigo_postal', 20)->nullable();

            $table->string('nombre_contacto', 150);
            $table->string('telefono_contacto', 30);

            $table->boolean('es_predeterminada')->default(false);

            $table->timestamps();

            $table->index(['customer_id', 'es_predeterminada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
