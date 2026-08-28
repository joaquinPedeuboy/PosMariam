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
        Schema::create('producto_ecommerce', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->unique()->constrained('productos')->onDelete('cascade');
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(false);
            $table->boolean('destacado')->default(false);
            $table->double('precio_online')->nullable();
            $table->integer('stock_maximo_web')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_ecommerce');
    }
};
