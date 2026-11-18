<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoCuenta;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private const MAX_INTENTOS = 5;
    private const MINUTOS_BLOQUEO = 15;

    public function __construct(private UsuarioRepositoryInterface $usuarios, private TokenService $tokens) {}

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate(['correo' => 'required|email', 'contrasena' => 'required|string']);
        $mensaje = 'Correo o contraseña incorrectos.';

        $usuario = $this->usuarios->findByCorreo($datos['correo']);
        if (! $usuario) {
            return response()->json(['message' => $mensaje], 401);
        }
        if ($usuario->esta_bloqueada) {
            return response()->json(['message' => 'Cuenta bloqueada temporalmente por intentos fallidos. Intente mas tarde.'], 423);
        }
        if (! password_verify($datos['contrasena'], (string) $usuario->contrasena_hash)) {
            $this->registrarFallo($usuario);

            return response()->json(['message' => $mensaje], 401);
        }
        if ($usuario->estado_cuenta !== EstadoCuenta::Activa) {
            return response()->json(['message' => 'La cuenta no esta activa.'], 403);
        }
        if ($usuario->intentos_fallidos > 0 || $usuario->bloqueada_hasta) {
            $this->usuarios->update($usuario->id_usuario, ['intentos_fallidos' => 0, 'bloqueada_hasta' => null]);
        }

        return response()->json([
            'token' => $this->tokens->emitir($usuario->id_usuario),
            'usuario' => $this->perfil($usuario->id_usuario),
        ]);
    }

    public function yo(Request $request): JsonResponse
    {
        return response()->json($this->perfil($request->user()->id_usuario));
    }

    private function registrarFallo(Usuario $usuario): void
    {
        $intentos = $usuario->intentos_fallidos + 1;
        $cambios = ['intentos_fallidos' => $intentos];
        if ($intentos >= self::MAX_INTENTOS) {
            $cambios = ['intentos_fallidos' => 0, 'bloqueada_hasta' => now()->addMinutes(self::MINUTOS_BLOQUEO)];
        }
        $this->usuarios->update($usuario->id_usuario, $cambios);
    }

    /** @return array<string, mixed> */
    private function perfil(int $id): array
    {
        $u = $this->usuarios->find($id, ['rol', 'cargo.empresa']);

        return [
            'id_usuario' => $u->id_usuario,
            'nombre' => $u->nombre,
            'cedula' => $u->cedula,
            'id_rol' => $u->id_rol,
            'rol' => $u->rol->nombre_rol ?? null,
            'cargo' => $u->cargo?->nombre_cargo,
            'empresa_nit' => $u->cargo?->empresa_id,
            'empresa' => $u->cargo?->empresa?->razon_social,
        ];
    }
}
