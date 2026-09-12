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
            // Default true a propósito: las categorías que ya existen
            // siguen apareciendo en la barra tal cual hoy, sin que nadie
            // tenga que tildar nada a mano de entrada. Se van desmarcando
            // desde el admin las que no quieran verse ahí.
            $table->boolean('mostrar_en_menu_principal')->default(true)->after('orden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categorias_ecommerce', function (Blueprint $table) {
            $table->dropColumn('mostrar_en_menu_principal');
        });
    }
};
