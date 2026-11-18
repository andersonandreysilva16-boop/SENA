<?php

// Pruebas de la API que NO necesitan MySQL: token, festivos y rutas protegidas.

use App\Services\TokenService;
use App\Support\Festivos;

test('el token se firma, se valida y vence', function () {
    $t = app(TokenService::class);
    $token = $t->emitir(7, 10);

    expect($t->validar($token))->toBe(7)
        ->and($t->validar($token.'x'))->toBeNull()
        ->and($t->validar('basura'))->toBeNull()
        ->and($t->validar($t->emitir(7, -1)))->toBeNull();
});

test('festivos de Colombia 2026: puentes al lunes y Semana Santa', function () {
    $f = Festivos::del(2026);

    // Pascua 2026 = 5 de abril
    expect($f)->toHaveKeys(['2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02', '2026-04-03', '2026-05-18', '2026-06-08', '2026-06-15', '2026-06-29', '2026-07-20', '2026-08-07', '2026-08-17', '2026-10-12', '2026-11-02', '2026-11-16', '2026-12-08', '2026-12-25'])
        ->and(count($f))->toBe(18);
});

test('domingo o festivo: el miercoles no sirve', function () {
    expect(Festivos::esDominicalOFestivo('2026-11-15'))->toBeTrue()    // domingo
        ->and(Festivos::esDominicalOFestivo('2026-11-16'))->toBeTrue() // festivo (Independencia de Cartagena, trasladado)
        ->and(Festivos::esDominicalOFestivo('2026-11-11'))->toBeFalse()
        ->and(Festivos::esDominicalOFestivo('2026-11-18'))->toBeFalse();
});

test('las rutas protegidas responden 401 sin token', function () {
    $this->getJson('/api/novedades')->assertStatus(401);
    $this->getJson('/api/planillas')->assertStatus(401);
    $this->postJson('/api/novedades', [])->assertStatus(401);
});

test('el login valida los datos antes de tocar la base', function () {
    $this->postJson('/api/login', [])->assertStatus(422);
});
