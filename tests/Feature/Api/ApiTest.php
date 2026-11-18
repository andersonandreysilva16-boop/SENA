<?php

// La API contra MySQL real (base de pruebas segupyme_v15_test, cuenta segupyme_app).

use App\Models\Usuario;
use App\Services\TokenService;

beforeEach(function () {
    baseNueva(__FILE__);
    // Las contrasenas de los datos de prueba son de relleno: se les pone una real.
    Usuario::whereNotNull('correo_acceso')->get()->each(fn ($u) => $u->update(['contrasena_hash' => password_hash('Clave*2026', PASSWORD_ARGON2ID)]));
});

function tokenDe(string $cedula): array
{
    $id = Usuario::where('cedula', $cedula)->value('id_usuario');

    return ['Authorization' => 'Bearer '.app(TokenService::class)->emitir($id)];
}

test('login correcto devuelve token y perfil; contrasena mala, 401', function () {
    $this->postJson('/api/login', ['correo' => 'ana@textilesandina-prueba.co', 'contrasena' => 'Clave*2026'])
        ->assertOk()->assertJsonPath('usuario.empresa_nit', '900111222')->assertJsonStructure(['token']);
    $this->postJson('/api/login', ['correo' => 'ana@textilesandina-prueba.co', 'contrasena' => 'mala'])->assertStatus(401);
});

test('cinco intentos fallidos bloquean la cuenta', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/login', ['correo' => 'juan@textilesandina-prueba.co', 'contrasena' => 'mala']);
    }
    $this->postJson('/api/login', ['correo' => 'juan@textilesandina-prueba.co', 'contrasena' => 'Clave*2026'])->assertStatus(423);
});

test('el Empleado registra su hora extra y la ve en su lista', function () {
    $h = tokenDe('1012345678');   // Ana
    $this->postJson('/api/novedades', ['concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => '2026-11-06', 'horas' => 2], $h)->assertStatus(201);
    $this->getJson('/api/novedades', $h)->assertOk()->assertJsonPath('total', 3);   // 2 de octubre + la nueva
});

test('no se aceptan horas extra de una fecha futura', function () {
    $this->postJson('/api/novedades', ['concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => now()->addDays(3)->toDateString(), 'horas' => 2], tokenDe('1012345678'))
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'fecha futura'));
});

test('un recargo dominical solo acepta domingos o festivos', function () {
    $h = tokenDe('1012345678');
    $this->postJson('/api/novedades', ['concepto' => 'RECARGO_DOMINICAL_FESTIVO_DIURNO', 'fecha_inicio' => '2026-11-04', 'horas' => 8], $h)->assertStatus(422);
    $this->postJson('/api/novedades', ['concepto' => 'RECARGO_DOMINICAL_FESTIVO_DIURNO', 'fecha_inicio' => '2026-11-01', 'horas' => 8], $h)->assertStatus(201);
});

test('la base rechaza la fecha futura aunque se salte la API (trigger)', function () {
    $ana = Usuario::where('cedula', '1012345678')->value('id_usuario');
    $e = capturar(fn () => app(\App\Repositories\Contracts\NovedadRepositoryInterface::class)->registrar($ana, $ana, 'HORA_EXTRA_DIURNA', now()->addDay()->toDateString(), null, 1));
    expect($e->errorInfo[1])->toBe(1644);
});

test('el Empleado no ve a otras personas ni empresas; el Contador si ve sus clientes', function () {
    $this->getJson('/api/empleados', tokenDe('1012345678'))->assertStatus(403);
    $this->getJson('/api/novedades?empresa=900333444', tokenDe('1078901234'))->assertOk();     // Carlos atiende al cliente
    $this->getJson('/api/novedades?empresa=900111222', tokenDe('52000111'))->assertOk();       // Laura: su empresa
    $this->getJson('/api/novedades?empresa=900333444', tokenDe('52000111'))->assertStatus(403); // Laura: otra empresa
});

test('el Contador genera la planilla; el Empleado no', function () {
    $this->postJson('/api/planillas', ['empresa' => '900111222', 'periodo' => '2026-10-01', 'fecha_limite' => '2026-11-10'], tokenDe('1012345678'))->assertStatus(422);
    $this->postJson('/api/planillas', ['empresa' => '900111222', 'periodo' => '2026-10-01', 'fecha_limite' => '2026-11-10'], tokenDe('1078901234'))
        ->assertStatus(201)->assertJsonPath('total_aportes', '2252105.04');
});

test('la carga masiva de novedades devuelve cuantas entraron y el motivo de las rechazadas', function () {
    $r = $this->postJson('/api/carga/novedades', ['empresa' => '900111222', 'filas' => [
        ['cedula' => '1012345678', 'concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => '2026-11-03', 'horas' => 2],
        ['cedula' => '0000', 'concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => '2026-11-03', 'horas' => 2],
    ]], tokenDe('1078901234'));
    $r->assertOk()->assertJsonPath('ok', 1)->assertJsonPath('errores', 1);
});
