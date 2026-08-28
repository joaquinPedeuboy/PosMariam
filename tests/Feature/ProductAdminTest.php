<?php

use App\Models\CategoriaEcommerce;
use App\Models\Imagen;
use App\Models\Producto;
use App\Models\ProductoEcommerce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->create(['admin' => true]);
}

it('el listado solo muestra productos que ya tienen ficha ecommerce', function () {
    $publicado = ProductoEcommerce::factory()->create();
    Producto::factory()->create(); // producto del POS sin publicar, no debe aparecer

    $response = $this->actingAs(adminUser())->get(route('admin.products.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->has('productos.data', 1)
        );
});

it('la búsqueda de productos del POS excluye los que ya están publicados', function () {
    $sinPublicar = Producto::factory()->create(['nombre' => 'Perfume Dior Sauvage']);
    $yaPublicado = ProductoEcommerce::factory()->create();
    $yaPublicado->producto->update(['nombre' => 'Perfume Dior Homme']);

    $response = $this->actingAs(adminUser())
        ->getJson(route('admin.products.buscar-pos', ['q' => 'Dior']));

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['nombre' => 'Perfume Dior Sauvage']);
});

it('permite publicar un producto del POS en la web', function () {
    $producto = Producto::factory()->create([
        'nombre' => 'Perfume Original',
        'codigo_barras' => '1111111111111',
    ]);
    $categoria = CategoriaEcommerce::factory()->create();

    $response = $this->actingAs(adminUser())->post(route('admin.products.store'), [
        'producto_id' => $producto->id,
        'nombre' => 'Perfume Editado',
        'codigo_barras' => '2222222222222',
        'descripcion' => 'Una fragancia increíble',
        'activo' => true,
        'destacado' => false,
        'categorias' => [$categoria->id],
    ]);

    $response->assertRedirect(route('admin.products.index'));

    // El nombre/código se actualizan en el producto del POS (misma entidad)
    $this->assertDatabaseHas('productos', [
        'id' => $producto->id,
        'nombre' => 'Perfume Editado',
        'codigo_barras' => '2222222222222',
    ]);

    $this->assertDatabaseHas('producto_ecommerce', [
        'producto_id' => $producto->id,
        'slug' => 'perfume-editado',
        'activo' => true,
    ]);

    $this->assertDatabaseHas('categoria_producto_ecommerce', [
        'categoria_ecommerce_id' => $categoria->id,
    ]);
});

it('no permite publicar dos veces el mismo producto', function () {
    $productoEcommerce = ProductoEcommerce::factory()->create();

    $response = $this->actingAs(adminUser())->post(route('admin.products.store'), [
        'producto_id' => $productoEcommerce->producto_id,
        'nombre' => 'Cualquiera',
    ]);

    $response->assertSessionHasErrors('producto_id');
});

it('genera slugs únicos si el nombre se repite', function () {
    $categoria = CategoriaEcommerce::factory()->create();

    foreach (range(1, 2) as $i) {
        $producto = Producto::factory()->create();
        $this->actingAs(adminUser())->post(route('admin.products.store'), [
            'producto_id' => $producto->id,
            'nombre' => 'Perfume Repetido',
        ]);
    }

    $this->assertDatabaseHas('producto_ecommerce', ['slug' => 'perfume-repetido']);
    $this->assertDatabaseHas('producto_ecommerce', ['slug' => 'perfume-repetido-2']);
});

it('permite editar un producto publicado, incluyendo subir una imagen', function () {
    Storage::fake('public');

    $productoEcommerce = ProductoEcommerce::factory()->create();
    $categoria = CategoriaEcommerce::factory()->create();

    $response = $this->actingAs(adminUser())->put(route('admin.products.update', $productoEcommerce), [
        'nombre' => 'Nombre actualizado',
        'codigo_barras' => $productoEcommerce->producto->codigo_barras,
        'descripcion' => 'Nueva descripción',
        'activo' => true,
        'destacado' => true,
        'categorias' => [$categoria->id],
        'imagenes' => [UploadedFile::fake()->image('perfume.jpg')],
    ]);

    $response->assertRedirect(route('admin.products.index'));

    $this->assertDatabaseHas('productos', [
        'id' => $productoEcommerce->producto_id,
        'nombre' => 'Nombre actualizado',
    ]);

    $this->assertDatabaseHas('producto_ecommerce', [
        'id' => $productoEcommerce->id,
        'destacado' => true,
    ]);

    expect(Imagen::where('producto_id', $productoEcommerce->producto_id)->count())->toBe(1);
});

it('al eliminar la ficha ecommerce, el producto del POS no se borra', function () {
    $productoEcommerce = ProductoEcommerce::factory()->create();
    $productoId = $productoEcommerce->producto_id;

    $this->actingAs(adminUser())->delete(route('admin.products.destroy', $productoEcommerce));

    $this->assertDatabaseMissing('producto_ecommerce', ['id' => $productoEcommerce->id]);
    $this->assertDatabaseHas('productos', ['id' => $productoId]); // sigue existiendo
});

it('bloquea el acceso a un empleado sin permiso de admin', function () {
    $empleado = User::factory()->create(['admin' => false]);

    $this->actingAs($empleado)
        ->get(route('admin.products.index'))
        ->assertForbidden();
});