<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_stock_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->restrictOnDelete();

            $table->foreignId('producto_vencimiento_id')
                ->nullable()
                ->constrained('producto_vencimientos')
                ->restrictOnDelete();

            $table->foreignId('oferta_id')
                ->nullable()
                ->constrained('ofertas')
                ->restrictOnDelete();

            $table->unsignedInteger('cantidad');

            $table->string('estado', 20)->default('activa');

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['estado', 'expires_at']);
            $table->index(['producto_vencimiento_id', 'estado']);
            $table->index(['oferta_id', 'estado']);
        });

        DB::statement("
            ALTER TABLE order_stock_reservations
            ADD CONSTRAINT chk_reservation_single_stock_source
            CHECK (
                (
                    producto_vencimiento_id IS NOT NULL
                    AND oferta_id IS NULL
                )
                OR
                (
                    producto_vencimiento_id IS NULL
                    AND oferta_id IS NOT NULL
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_stock_reservations');
    }
};
