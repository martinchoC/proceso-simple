<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Validador de identificadores SQL (tablas y columnas) que vienen de
 * configuración.
 *
 * Los nombres de tabla y columna no pueden ir como parámetros de un prepared
 * statement: se interpolan. Este validador es la única puerta por la que se
 * permite hacerlo, y sólo deja pasar la forma [a-z0-9_]. Además, quien lo usa
 * verifica contra information_schema que el identificador exista antes de
 * armar la consulta, así un nombre mal configurado da una denegación con
 * diagnóstico y no un error de SQL.
 */
final class Identificador
{
    private const PATRON = '/^[a-z][a-z0-9_]{0,63}$/';

    /** Devuelve el identificador normalizado o lanza si no tiene forma válida. */
    public static function validar(string $nombre, string $paraQue): string
    {
        $nombre = strtolower(trim($nombre));

        if (preg_match(self::PATRON, $nombre) !== 1) {
            throw new InvalidArgumentException(
                "Identificador inválido para {$paraQue}: sólo se admiten letras, números y guión bajo."
            );
        }

        return $nombre;
    }

    /** Igual que validar(), pero devuelve '' en vez de lanzar. */
    public static function oVacio(string $nombre): string
    {
        try {
            return self::validar($nombre, 'configuración');
        } catch (InvalidArgumentException) {
            return '';
        }
    }
}
