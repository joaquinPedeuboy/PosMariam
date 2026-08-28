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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');

            // Nullable + set null: si el producto se despublica/borra después, el pedido histórico se conserva
            $table->foreignId('producto_ecommerce_id')->nullable()
                ->constrained('producto_ecommerce')->onDelete('set null');

            // Snapshot: el nombre y precio AL MOMENTO DE LA COMPRA, no el actual
            $table->string('nombre_producto');
            $table->double('precio_unitario');
            $table->integer('cantidad');
            $table->double('subtotal');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
