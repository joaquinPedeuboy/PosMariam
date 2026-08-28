<?php
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('permite registrar un cliente nuevo', function () {
    $response = $this->postJson('/api/store/register', [
        'nombre' => 'Juan',
        'apellido' => 'Pérez',
        'email' => 'juan@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'telefono' => '3512345678',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['customer' => ['id', 'nombre', 'email'], 'token']);

    $this->assertDatabaseHas('customers', ['email' => 'juan@test.com']);

    $customer = Customer::where('email', 'juan@test.com')->first();
    expect($customer->password)->not->toBe('password123');
});

it('no permite registrar dos clientes con el mismo email', function () {
    Customer::factory()->create(['email' => 'juan@test.com']);

    $response = $this->postJson('/api/store/register', [
        'nombre' => 'Otro',
        'email' => 'juan@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('no permite registrarse sin confirmar la contraseña', function () {
    $response = $this->postJson('/api/store/register', [
        'nombre' => 'Juan',
        'email' => 'nuevo@test.com',
        'password' => 'password123',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('permite loguearse con credenciales correctas', function () {
    Customer::factory()->create([
        'email' => 'juan@test.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/store/login', [
        'email' => 'juan@test.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['customer' => ['id', 'email'], 'token']);
});

it('rechaza el login con contraseña incorrecta', function () {
    Customer::factory()->create([
        'email' => 'juan@test.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/store/login', [
        'email' => 'juan@test.com',
        'password' => 'incorrecta',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('permite a un cliente autenticado ver su perfil', function () {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($customer, 'sanctum')->getJson('/api/store/me');

    $response->assertOk()->assertJsonFragment(['email' => $customer->email]);
});

it('rechaza el acceso a /me sin autenticación', function () {
    $this->getJson('/api/store/me')->assertUnauthorized();
});

it('no permite que un token de empleado (User) acceda a rutas de cliente', function () {
    $user = User::factory()->create(['admin' => false]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/store/me');

    $response->assertForbidden();
});

it('permite cerrar sesión y revoca el token', function () {
    $customer = Customer::factory()->create([
        'email' => 'juan@test.com',
        'password' => 'password123',
    ]);

    $login = $this->postJson('/api/store/login', [
        'email' => 'juan@test.com',
        'password' => 'password123',
    ]);

    $token = $login->json('token');

    $this->assertDatabaseCount('personal_access_tokens', 1);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/store/logout')
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);

    // 👇 Forzamos a Laravel a olvidar el guard cacheado, para que la próxima
    // request vuelva a resolver la autenticación desde cero (como pasaría en producción)
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/store/me')
        ->assertUnauthorized();
});

