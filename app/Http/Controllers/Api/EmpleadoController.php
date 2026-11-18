<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Support\Acceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    public function __construct(private UsuarioRepositoryInterface $usuarios) {}

    /** Personas de una empresa (para escoger a quien registrarle una novedad). El Empleado no lista a nadie. */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_if(Acceso::rol($usuario) === RolUsuario::Empleado, 403, 'No tiene permiso para esta operacion.');
        $nit = $request->query('empresa') ?? Acceso::empresaDe($usuario);
        abort_if(! $nit || ! Acceso::puedeVerEmpresa($usuario, $nit), 403, 'No tiene permiso sobre esa empresa.');

        return response()->json($this->usuarios->porEmpresa($nit)->map(fn ($u) => [
            'id_usuario' => $u->id_usuario, 'cedula' => $u->cedula, 'nombre' => $u->nombre,
            'cargo' => $u->cargo?->nombre_cargo, 'rol' => $u->rol->nombre_rol ?? null,
        ])->values());
    }

    /** Personas con afiliaciones incompletas (bloquean la planilla). */
    public function incompletas(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $nit = $request->query('empresa') ?? Acceso::empresaDe($usuario);
        abort_if(Acceso::rol($usuario) === RolUsuario::Empleado || ! $nit || ! Acceso::puedeVerEmpresa($usuario, $nit), 403, 'No tiene permiso sobre esa empresa.');

        return response()->json($this->usuarios->conAfiliacionesIncompletas($nit, $request->query('fecha', now()->toDateString()))
            ->map(fn ($u) => ['id_usuario' => $u->id_usuario, 'cedula' => $u->cedula, 'nombre' => $u->nombre])->values());
    }
}
