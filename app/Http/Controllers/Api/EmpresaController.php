<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EmpresaRepositoryInterface;
use App\Support\Acceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function __construct(private EmpresaRepositoryInterface $empresas) {}

    /** Empresas que la persona puede ver: la suya y, si es Contador, las de sus clientes; el Super Usuario ve todos los clientes. */
    public function index(Request $request): JsonResponse
    {
        $u = $request->user();
        $propia = Acceso::empresaDe($u);

        $lista = match (Acceso::rol($u)) {
            RolUsuario::SuperUsuario => $this->empresas->clientes(),
            RolUsuario::Contador => $propia ? $this->empresas->clientesDeContadora($propia) : collect(),
            default => collect(),
        };
        $lista = $lista->map(fn ($e) => ['nit' => $e->nit, 'razon_social' => $e->razon_social]);

        if ($propia && ! $lista->contains('nit', $propia)) {
            $e = $this->empresas->find($propia);
            $lista->prepend(['nit' => $e->nit, 'razon_social' => $e->razon_social]);
        }

        return response()->json($lista->values());
    }
}
