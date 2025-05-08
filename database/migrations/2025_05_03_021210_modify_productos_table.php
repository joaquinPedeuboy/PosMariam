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
        Schema::table('productos', function (Blueprint $table) {
            // 1) elimina la FK vieja
            $table->dropForeign(['departamento_id']);

            // 2) vuelve a crearla con set null
            $table->foreign('departamento_id')
                  ->references('id')->on('departamentos')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['departamento_id']);
            $table->foreign('departamento_id')
                  ->references('id')->on('departamentos')
                  ->onDelete('cascade'); // o como antes
        });
    }
};
