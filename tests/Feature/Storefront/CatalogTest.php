<?php
use App\Models\CategoriaEcommerce;
use App\Models\ProductoEcommerce;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lista solo productos activos', function () {
    ProductoEcommerce::factory()->create(['activo' => true]);
    ProductoEcommerce::factory()->create(['activo' => false]);

    $response = $this->getJson('/api/store/productos');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('devuelve el detalle de un producto activo por su slug', function () {
    $pe = ProductoEcommerce::factory()->create([
        'activo' => true,
        'slug' => 'perfume-test',
    ]);

    $response = $this->getJson('/api/store/productos/perfume-test');

    $response->assertOk()
        ->assertJsonFragment(['slug' => 'perfume-test', 'nombre' => $pe->producto->nombre]);
});

it('devuelve 404 al pedir un producto inactivo por su slug', function () {
    ProductoEcommerce::factory()->create([
        'activo' => false,
        'slug' => 'perfume-oculto',
    ]);

    $this->getJson('/api/store/productos/perfume-oculto')->assertNotFound();
});

it('devuelve 404 al pedir un slug que no existe', function () {
    $this->getJson('/api/store/productos/no-existe')->assertNotFound();
});

it('filtra productos por categoría', function () {
    $categoria = CategoriaEcommerce::factory()->create(['slug' => 'perfumes-hombre']);
    $conCategoria = ProductoEcommerce::factory()->create(['activo' => true]);
    $conCategoria->categorias()->attach($categoria->id);

    ProductoEcommerce::factory()->create(['activo' => true]); // sin categoría

    $response = $this->getJson('/api/store/productos?categoria=perfumes-hombre');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('filtra productos por búsqueda de nombre', function () {
    $match = ProductoEcommerce::factory()->create(['activo' => true]);
    $match->producto->update(['nombre' => 'Perfume Dior Sauvage']);

    $noMatch = ProductoEcommerce::factory()->create(['activo' => true]);
    $noMatch->producto->update(['nombre' => 'Desodorante Axe']);

    $response = $this->getJson('/api/store/productos?busqueda=Dior');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['nombre' => 'Perfume Dior Sauvage']);
});

it('lista categorías raíz con sus subcategorías anidadas', function () {
    $padre = CategoriaEcommerce::factory()->create(['nombre' => 'Perfumes']);
    CategoriaEcommerce::factory()->create([
        'nombre' => 'Perfumes de hombre',
        'categoria_padre_id' => $padre->id,
    ]);
    CategoriaEcommerce::factory()->create(['nombre' => 'Ofertas']); // sin padre, suelta

    $response = $this->getJson('/api/store/categorias');

    $response->assertOk()->assertJsonCount(2); // 2 categorías raíz

    $data = $response->json();
    $perfumes = collect($data)->firstWhere('nombre', 'Perfumes');
    expect($perfumes['subcategorias'])->toHaveCount(1);
});