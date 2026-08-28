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
        Schema::create('categoria_producto_ecommerce', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_ecommerce_id')->constrained('producto_ecommerce')->onDelete('cascade');
            $table->foreignId('categoria_ecommerce_id')->constrained('categorias_ecommerce')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categoria_producto_ecommerce');
    }
};
