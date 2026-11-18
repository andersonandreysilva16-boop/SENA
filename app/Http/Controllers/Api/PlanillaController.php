<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\PlanillaRepositoryInterface;
use App\Services\PlanillaService;
use App\Support\Acceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanillaController extends Controller
{
    public function __construct(private PlanillaService $servicio, private PlanillaRepositoryInterface $planillas) {}

    public function index(Request $request): JsonResponse
    {
        $nit = $request->query('empresa') ?? Acceso::empresaDe($request->user());
        $this->exigirEmpresa($request, $nit);

        return response()->json($this->planillas->listarPorEmpresa($nit));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirEmpresa($request, Acceso::empresaDePlanilla($id));

        return response()->json(['resumen' => $this->planillas->resumen($id), 'planilla' => $this->planillas->conDetalle($id)]);
    }

    public function generar(Request $request): JsonResponse
    {
        $d = $request->validate(['empresa' => 'required|string|max:15', 'periodo' => 'required|date_format:Y-m-d', 'fecha_limite' => 'required|date_format:Y-m-d']);

        return response()->json($this->servicio->generar($request->user()->id_usuario, $d['empresa'], $d['periodo'], $d['fecha_limite']), 201);
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        return response()->json($this->servicio->aprobar($request->user()->id_usuario, $id));
    }

    public function refrescar(Request $request, int $id): JsonResponse
    {
        return response()->json($this->servicio->refrescar($request->user()->id_usuario, $id));
    }

    public function descartar(Request $request, int $id): JsonResponse
    {
        $this->servicio->descartar($request->user()->id_usuario, $id);

        return response()->json(null, 204);
    }

    public function corregir(Request $request, int $id): JsonResponse
    {
        return response()->json($this->servicio->corregir($request->user()->id_usuario, $id), 201);
    }

    public function marcarGenerada(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['clave_archivo' => 'required|string|max:64']);

        return response()->json($this->servicio->marcarGenerada($request->user()->id_usuario, $id, $d['clave_archivo']));
    }

    public function pago(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'fecha_pago' => 'required|date_format:Y-m-d', 'monto' => 'required|numeric|gt:0',
            'clave_comprobante' => 'required|string|max:64', 'sha256' => 'required|string|size:64',
        ]);

        return response()->json($this->servicio->registrarPago($request->user()->id_usuario, $id, $d['fecha_pago'], (string) $d['monto'], $d['clave_comprobante'], $d['sha256']));
    }

    private function exigirEmpresa(Request $request, ?string $nit): void
    {
        abort_if(Acceso::rol($request->user()) === RolUsuario::Empleado || ! $nit || ! Acceso::puedeVerEmpresa($request->user(), $nit), 403, 'No tiene permiso sobre esa empresa.');
    }
}
