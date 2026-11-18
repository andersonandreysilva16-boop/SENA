<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Domingos y festivos de Colombia (Ley 51 de 1983: los festivos de "puente" pasan al lunes siguiente;
 * Semana Santa y las fiestas que dependen de ella se calculan desde el Domingo de Pascua).
 * Es la misma regla que usa la pagina (js/utils/festivos.js) y la app movil (fechas.dart).
 */
class Festivos
{
    /** @return array<string, true> fechas ISO (YYYY-MM-DD) festivas del año */
    public static function del(int $anio): array
    {
        $fechas = [];
        $fijo = fn (int $m, int $d) => CarbonImmutable::create($anio, $m, $d);
        $lunes = function (CarbonImmutable $f): CarbonImmutable {
            // Si no cae lunes, pasa al lunes siguiente.
            return $f->dayOfWeekIso === 1 ? $f : $f->next(CarbonImmutable::MONDAY);
        };

        foreach ([[1, 1], [5, 1], [7, 20], [8, 7], [12, 8], [12, 25]] as [$m, $d]) {
            $fechas[$fijo($m, $d)->toDateString()] = true;
        }
        foreach ([[1, 6], [3, 19], [6, 29], [8, 15], [10, 12], [11, 1], [11, 11]] as [$m, $d]) {
            $fechas[$lunes($fijo($m, $d))->toDateString()] = true;
        }

        $pascua = self::pascua($anio);
        $fechas[$pascua->subDays(3)->toDateString()] = true;   // Jueves Santo
        $fechas[$pascua->subDays(2)->toDateString()] = true;   // Viernes Santo
        $fechas[$pascua->addDays(43)->toDateString()] = true;  // Ascension (lunes)
        $fechas[$pascua->addDays(64)->toDateString()] = true;  // Corpus Christi (lunes)
        $fechas[$pascua->addDays(71)->toDateString()] = true;  // Sagrado Corazon (lunes)

        return $fechas;
    }

    public static function esDominicalOFestivo(string $iso): bool
    {
        $f = CarbonImmutable::parse($iso);

        return $f->isSunday() || isset(self::del($f->year)[$f->toDateString()]);
    }

    /** Domingo de Pascua (algoritmo de Meeus/Jones/Butcher). */
    private static function pascua(int $y): CarbonImmutable
    {
        $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100;
        $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($y, $mes, $dia);
    }
}
