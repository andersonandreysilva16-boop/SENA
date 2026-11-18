<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CargaMasivaService;
use App\Support\Acceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** El cliente (pagina o app) convierte el Excel/CSV en filas JSON; la base valida y reparte cada fila. */
class CargaMasivaController extends Controller
{
    public function __construct(private CargaMasivaService $carga) {}

    public function novedades(Request $request): JsonResponse
    {
        [$nit, $filas] = $this->leer($request);

        return response()->json($this->carga->novedades($request->user()->id_usuario, $nit, $filas));
    }

    public function empleados(Request $request): JsonResponse
    {
        [$nit, $filas] = $this->leer($request);

        return response()->json($this->carga->empleados($request->user()->id_usuario, $nit, $filas));
    }

    /** @return array{0: string, 1: array<int, array<string, mixed>>} */
    private function leer(Request $request): array
    {
        $d = $request->validate(['empresa' => 'required|string|max:15', 'filas' => 'required|array|min:1|max:2000', 'filas.*' => 'array']);
        abort_unless(Acceso::puedeVerEmpresa($request->user(), $d['empresa']), 403, 'No tiene permiso sobre esa empresa.');

        return [$d['empresa'], $d['filas']];
    }
}
