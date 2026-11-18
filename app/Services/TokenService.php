<?php

namespace App\Services;

/**
 * Token firmado (HS256, formato JWT) sin guardar nada en la base: el modelo de datos no tiene tabla de sesiones.
 * Lleva el id de la persona y la fecha de vencimiento; se firma con la APP_KEY. En cada peticion se vuelve a
 * leer la persona y se comprueba que su cuenta siga ACTIVA, asi bloquear o eliminar una cuenta corta el acceso de inmediato.
 */
class TokenService
{
    public function emitir(int $idUsuario, int $minutos = 480): string
    {
        $cuerpo = $this->b64(json_encode(['sub' => $idUsuario, 'iat' => time(), 'exp' => time() + $minutos * 60]));
        $cabecera = $this->b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        return "$cabecera.$cuerpo.".$this->firma("$cabecera.$cuerpo");
    }

    /** @return int|null id del usuario, o null si el token es invalido o vencio. */
    public function validar(string $token): ?int
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3 || ! hash_equals($this->firma("$partes[0].$partes[1]"), $partes[2])) {
            return null;
        }
        $datos = json_decode($this->deb64($partes[1]), true);
        if (! is_array($datos) || ($datos['exp'] ?? 0) < time() || ! isset($datos['sub'])) {
            return null;
        }

        return (int) $datos['sub'];
    }

    private function firma(string $texto): string
    {
        $clave = (string) config('app.key');
        $clave = str_starts_with($clave, 'base64:') ? base64_decode(substr($clave, 7)) : $clave;

        return $this->b64(hash_hmac('sha256', $texto, $clave, true));
    }

    private function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private function deb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
