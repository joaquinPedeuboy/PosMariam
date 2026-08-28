<?php

use App\Models\CategoriaEcommerce;
use App\Models\ProductoEcommerce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admin(): User
{
    return User::factory()->create(['admin' => true]);
}

it('permite a un admin ver el listado de categorías', function () {
    CategoriaEcommerce::factory()->count(3)->create();

    $response = $this->actingAs(admin())->get(route('admin.categories.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Categories/Index')
            ->has('categorias', 3)
        );
});

it('bloquea el acceso al panel a un empleado sin permiso de admin', function () {
    $empleado = User::factory()->create(['admin' => false]);

    $this->actingAs($empleado)
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

it('redirige a login a un usuario no autenticado', function () {
    $this->get(route('admin.categories.index'))
        ->assertRedirect(route('login'));
});

it('permite crear una categoría generando el slug automáticamente', function () {
    $response = $this->actingAs(admin())->post(route('admin.categories.store'), [
        'nombre' => 'Perfumes de hombre',
        'orden' => 1,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categorias_ecommerce', [
        'nombre' => 'Perfumes de hombre',
        'slug' => 'perfumes-de-hombre',
    ]);
});

it('no permite crear una categoría sin nombre', function () {
    $response = $this->actingAs(admin())->post(route('admin.categories.store'), []);

    $response->assertSessionHasErrors('nombre');
});

it('permite editar una categoría existente', function () {
    $categoria = CategoriaEcommerce::factory()->create(['nombre' => 'Viejo nombre']);

    $response = $this->actingAs(admin())->put(route('admin.categories.update', $categoria), [
        'nombre' => 'Nuevo nombre',
        'slug' => 'nuevo-nombre',
        'orden' => 2,
    ]);

    $response->assertRedirect(route('admin.categories.index'));
    $this->assertDatabaseHas('categorias_ecommerce', ['id' => $categoria->id, 'nombre' => 'Nuevo nombre']);
});

it('no permite eliminar una categoría que tiene productos asignados', function () {
    $categoria = CategoriaEcommerce::factory()->create();
    $productoEcommerce = ProductoEcommerce::factory()->create();
    $productoEcommerce->categorias()->attach($categoria->id);

    $response = $this->actingAs(admin())->delete(route('admin.categories.destroy', $categoria));

    $response->assertSessionHas('error');
    $this->assertDatabaseHas('categorias_ecommerce', ['id' => $categoria->id]);
});

it('permite eliminar una categoría sin productos asignados', function () {
    $categoria = CategoriaEcommerce::factory()->create();

    $this->actingAs(admin())->delete(route('admin.categories.destroy', $categoria));

    $this->assertDatabaseMissing('categorias_ecommerce', ['id' => $categoria->id]);
});