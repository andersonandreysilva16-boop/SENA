<?php

namespace App\Support;

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Quien puede VER que. Escribir lo decide la base (cada procedimiento revisa el rol del actor);
 * leer no, porque la cuenta de la aplicacion tiene SELECT en todo. Por eso la API filtra las lecturas aqui,
 * usando las mismas funciones de la base (fn_empresa_usuario, fn_atiende).
 */
class Acceso
{
    public static function rol(Usuario $u): RolUsuario
    {
        return RolUsuario::from((int) $u->id_rol);
    }

    public static function empresaDe(Usuario $u): ?string
    {
        return DB::selectOne('SELECT fn_empresa_usuario(?) AS nit', [$u->id_usuario])->nit;
    }

    public static function puedeVerEmpresa(Usuario $u, string $nit): bool
    {
        return match (self::rol($u)) {
            RolUsuario::SuperUsuario => true,
            RolUsuario::Contador => (int) DB::selectOne('SELECT fn_atiende(?, ?) AS ok', [$u->id_usuario, $nit])->ok === 1,
            default => self::empresaDe($u) === $nit,
        };
    }

    /** El Empleado solo ve lo propio; los demas roles ven a las personas de las empresas que pueden ver. */
    public static function puedeVerPersona(Usuario $u, int $idPersona): bool
    {
        if (self::rol($u) === RolUsuario::Empleado) {
            return $u->id_usuario === $idPersona;
        }
        $nit = DB::selectOne('SELECT fn_empresa_usuario(?) AS nit', [$idPersona])->nit;

        return $nit !== null && self::puedeVerEmpresa($u, $nit);
    }

    public static function empresaDePlanilla(int $idPlanilla): ?string
    {
        return DB::selectOne('SELECT fn_empresa_planilla(?) AS nit', [$idPlanilla])->nit;
    }
}
