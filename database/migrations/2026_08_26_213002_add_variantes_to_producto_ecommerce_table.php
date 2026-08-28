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
        Schema::table('producto_ecommerce', function (Blueprint $table) {
            $table->foreignId('grupo_variante_id')->nullable()->after('producto_id')
                ->constrained('grupo_variantes')->onDelete('set null');

            // Tamaño y color son ejes independientes y opcionales — un
            // producto puede tener uno, el otro, ambos, o ninguno.
            $table->string('variante_tamano')->nullable()->after('grupo_variante_id');
            $table->string('variante_color_nombre')->nullable()->after('variante_tamano');
            $table->string('variante_color_hex', 7)->nullable()->after('variante_color_nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('producto_ecommerce', function (Blueprint $table) {
            $table->dropForeign(['grupo_variante_id']);
            $table->dropColumn([
                'grupo_variante_id',
                'variante_tamano',
                'variante_color_nombre',
                'variante_color_hex',
            ]);
        });
    }
};
