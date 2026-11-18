<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CargaMasivaController;
use App\Http\Controllers\Api\EmpleadoController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\NovedadController;
use App\Http\Controllers\Api\PlanillaController;
use Illuminate\Support\Facades\Route;

// API REST de SeguPYME (prefijo /api). La usan la pagina web y la app movil Flutter.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware('token')->group(function () {
    Route::get('/yo', [AuthController::class, 'yo']);

    Route::get('/empresas', [EmpresaController::class, 'index']);
    Route::get('/conceptos', [NovedadController::class, 'conceptos']);
    Route::get('/novedades', [NovedadController::class, 'index']);
    Route::post('/novedades', [NovedadController::class, 'store']);
    Route::patch('/novedades/{id}', [NovedadController::class, 'gestionar'])->whereNumber('id');

    Route::get('/empleados', [EmpleadoController::class, 'index']);
    Route::get('/empleados/afiliaciones-incompletas', [EmpleadoController::class, 'incompletas']);

    Route::get('/planillas', [PlanillaController::class, 'index']);
    Route::post('/planillas', [PlanillaController::class, 'generar']);
    Route::get('/planillas/{id}', [PlanillaController::class, 'show'])->whereNumber('id');
    Route::post('/planillas/{id}/aprobar', [PlanillaController::class, 'aprobar'])->whereNumber('id');
    Route::post('/planillas/{id}/refrescar', [PlanillaController::class, 'refrescar'])->whereNumber('id');
    Route::post('/planillas/{id}/generada', [PlanillaController::class, 'marcarGenerada'])->whereNumber('id');
    Route::post('/planillas/{id}/pago', [PlanillaController::class, 'pago'])->whereNumber('id');
    Route::post('/planillas/{id}/correccion', [PlanillaController::class, 'corregir'])->whereNumber('id');
    Route::delete('/planillas/{id}', [PlanillaController::class, 'descartar'])->whereNumber('id');

    Route::post('/carga/novedades', [CargaMasivaController::class, 'novedades']);
    Route::post('/carga/empleados', [CargaMasivaController::class, 'empleados']);
});
