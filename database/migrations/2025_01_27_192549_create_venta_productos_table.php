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
        Schema::create('venta_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained()->onDelete('cascade'); // Si se borra una venta, se eliminan sus productos
            $table->foreignId('producto_id')->nullable()->constrained()->onDelete('set null'); // Para no perder datos si se elimina el producto
            $table->string('nombre_producto'); // Se guarda el nombre del producto en la venta
            $table->decimal('precio_unitario', 10, 2); // Precio en el momento de la venta
            $table->integer('cantidad'); // Cantidad vendida
            $table->decimal('subtotal', 10, 2); // Total de ese producto en la venta
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_productos');
    }
};
