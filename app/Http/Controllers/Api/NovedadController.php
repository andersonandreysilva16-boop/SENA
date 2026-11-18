<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoAprobacion;
use App\Enums\RolUsuario;
use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\ConceptoRepositoryInterface;
use App\Repositories\Contracts\NovedadRepositoryInterface;
use App\Support\Acceso;
use App\Support\Festivos;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NovedadController extends Controller
{
    public function __construct(private NovedadRepositoryInterface $novedades, private ConceptoRepositoryInterface $conceptos) {}

    /** Catalogo de conceptos, con las banderas que necesita el formulario (horas, fecha final, dominical). */
    public function conceptos(): JsonResponse
    {
        return response()->json($this->conceptos->catalogo()->where('codigo', '<>', 'SALARIO')->values()->map(fn ($c) => [
            'id_concepto' => $c->id_concepto,
            'codigo' => $c->codigo,
            'nombre' => $c->nombre,
            'lleva_horas' => $c->porcentaje_fijo !== null,
            'requiere_fecha_fin' => $c->requiere_dias,
            'solo_dominical_festivo' => $c->suma_dominical,
            'requiere_soporte' => $c->requiere_soporte,
        ]));
    }

    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $filtros = $request->validate([
            'empresa' => 'nullable|string|max:15', 'id_empleado' => 'nullable|integer',
            'estado' => ['nullable', Rule::enum(EstadoAprobacion::class)], 'periodo' => 'nullable|date',
        ]);

        if (Acceso::rol($usuario) === RolUsuario::Empleado) {
            $filtros = ['id_empleado' => $usuario->id_usuario] + $filtros;
            $filtros['empresa'] = null;
        } else {
            $filtros['empresa'] ??= Acceso::empresaDe($usuario);
            abort_if(! $filtros['empresa'] || ! Acceso::puedeVerEmpresa($usuario, $filtros['empresa']), 403, 'No tiene permiso sobre esa empresa.');
        }

        $pagina = $this->novedades->buscar($filtros, 30);

        return response()->json([
            'data' => $pagina->getCollection()->map(fn ($n) => $this->fila($n))->values(),
            'total' => $pagina->total(),
            'pagina' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $d = $request->validate([
            'empleado_id' => 'nullable|integer',
            'concepto' => 'required|string|max:40',
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
            'horas' => 'nullable|numeric|gt:0|max:24',
        ]);
        $empleado = (int) ($d['empleado_id'] ?? $usuario->id_usuario);
        abort_unless(Acceso::puedeVerPersona($usuario, $empleado), 403, 'No tiene permiso sobre esa persona.');

        $concepto = $this->conceptos->findByCodigo($d['concepto']);
        if ($concepto && $concepto->porcentaje_fijo !== null) {          // horas extra y recargos
            // Se valida aqui para dar un mensaje claro; la base (trigger) lo vuelve a exigir.
            if ($d['fecha_inicio'] > now()->toDateString()) {
                throw new ReglaDeNegocioException('No puedes registrar horas extra o recargos de una fecha futura: esas horas aun no se han trabajado.');
            }
            if ($concepto->suma_dominical && ! Festivos::esDominicalOFestivo($d['fecha_inicio'])) {
                throw new ReglaDeNegocioException('Este recargo solo aplica a domingos y festivos.');
            }
        }

        try {
            $n = $this->novedades->registrar($usuario->id_usuario, $empleado, $d['concepto'], $d['fecha_inicio'], $d['fecha_fin'] ?? null, isset($d['horas']) ? (float) $d['horas'] : null);
        } catch (QueryException $e) {
            throw ReglaDeNegocioException::desde($e);
        }

        return response()->json($this->fila($n->load(['empleado', 'concepto'])), 201);
    }

    /** El Administrador aprueba o rechaza; la base valida el rol. */
    public function gestionar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['decision' => ['required', Rule::in(['APROBADA', 'RECHAZADA'])]]);
        $novedad = $this->novedades->findOrFail($id);
        abort_unless(Acceso::puedeVerPersona($request->user(), $novedad->empleado_id), 403, 'No tiene permiso sobre esa novedad.');

        try {
            $n = $this->novedades->gestionar($request->user()->id_usuario, $id, EstadoAprobacion::from($d['decision']));

            return response()->json($this->fila($n->load(['empleado', 'concepto'])));
        } catch (QueryException $e) {
            throw ReglaDeNegocioException::desde($e);
        }
    }

    /** Solo lo que necesitan las pantallas: no se envian el salario ni otros datos de la persona. */
    private function fila(\App\Models\Novedad $n): array
    {
        return [
            'id_novedad' => $n->id_novedad,
            'empleado_id' => $n->empleado_id,
            'empleado' => $n->empleado->nombre,
            'cedula' => $n->empleado->cedula,
            'concepto_codigo' => $n->concepto->codigo,
            'concepto' => $n->concepto->nombre,
            'fecha_inicio' => $n->fecha_inicio->toDateString(),
            'fecha_fin' => $n->fecha_fin?->toDateString(),
            'horas' => $n->horas,
            'valor' => $n->valor,
            'estado' => $n->estado->value,
        ];
    }
}
