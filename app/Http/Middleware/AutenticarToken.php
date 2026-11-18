<?php

namespace App\Http\Middleware;

use App\Enums\EstadoCuenta;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutenticarToken
{
    public function __construct(private TokenService $tokens, private UsuarioRepositoryInterface $usuarios) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $id = $token ? $this->tokens->validar($token) : null;
        $usuario = $id ? $this->usuarios->find($id, ['rol']) : null;

        if (! $usuario || $usuario->estado_cuenta !== EstadoCuenta::Activa || $usuario->esta_bloqueada) {
            return response()->json(['message' => 'Sesion no valida o vencida. Inicie sesion de nuevo.'], 401);
        }

        Auth::setUser($usuario);
        $request->setUserResolver(fn () => $usuario);

        return $next($request);
    }
}
