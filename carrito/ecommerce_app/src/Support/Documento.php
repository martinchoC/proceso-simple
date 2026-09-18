<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalización del documento (CUIL/CUIT) que se usa como nombre de usuario.
 *
 * No valida el dígito verificador a propósito: la autorización la decide la
 * coincidencia contra gestion__entidades, no una regla de formato. Validar el
 * módulo 11 acá sólo agregaría una forma de dejar afuera a un cliente cuyo
 * documento está mal cargado en el ERP, sin ganar nada en seguridad.
 *
 * Sí actúa como filtro previo: si el usuario no tiene forma de documento, no
 * se consulta la base. Eso evita el caso peligroso de comparar un texto contra
 * una columna numérica, donde MySQL convierte 'martin' a 0 y podría hacer
 * match con una entidad que tenga el documento en 0.
 */
final class Documento
{
    /**
     * Largos admitidos: 11 = CUIL/CUIT, 7 y 8 = DNI, por si alguna entidad del
     * ERP tiene cargado el documento sin prefijo. Cualquier otro largo no es un
     * documento y se descarta sin consultar la base.
     */
    private const LARGOS_VALIDOS = [7, 8, 11];

    /**
     * Devuelve el documento como cadena de dígitos, o '' si el texto recibido
     * no puede ser un documento.
     */
    public static function normalizar(string $valor): string
    {
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';

        // Se quitan ceros a la izquierda para que '020304050607' y
        // '20304050607' resuelvan la misma entidad, tanto si la columna del
        // ERP es BIGINT como si es VARCHAR. Todo ceros queda en cadena vacía:
        // sería el comodín que hace match con filas incompletas del ERP.
        $digitos = ltrim($digitos, '0');

        return in_array(strlen($digitos), self::LARGOS_VALIDOS, true) ? $digitos : '';
    }
}
