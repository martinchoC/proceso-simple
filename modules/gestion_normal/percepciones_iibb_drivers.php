<?php
/**
 * Percepciones IIBB: registro de drivers de fuentes de alícuota (padrones) y de formatos de DDJJ.
 * Diseño: _local/docs/gestion/09_percepciones_iibb.md §12.
 *
 * Un driver de padrón convierte lo propio de una fuente en FILAS NORMALIZADAS de gestion__padrones_iibb:
 *   cuit, razon_social, f_desde, f_hasta, alicuota, situacion (PADRON | INSCRIPTO_OTRA_JURISDICCION | NO_PERCIBIR),
 *   codigo_origen, crc, jurisdiccion_sede_cm
 *   (+ periodo, letra, digito: columnas de compatibilidad que hoy solo llena SIRCIP).
 * El motor (perIibbCalcular) lee solo esas filas: no conoce ningún formato.
 *
 * Driver de archivo:
 *   'tipo'     => 'ARCHIVO'
 *   'preparar' => fn(array $jur) : array $ctx        valida la config de la jurisdicción y arma el contexto
 *   'parsear'  => fn(string $linea, array &$ctx)     devuelve fila normalizada | string (error de la línea)
 *                                                    | null (la fuente dice que no aplica: no se guarda);
 *                                                    lanza Exception para abortar la importación
 *
 * Formato de DDJJ: fn($conexion, $empresa_id, array $cfg, array $filas) devuelve
 *   ['lineas' => [...], 'zip' => bool]   (perExportar arma el nombre y el archivo)
 */
require_once __DIR__ . '/percepciones_iibb_core_model.php';

function perIibbDriversPadron() {
    return [
        'SIRCIP' => [
            'tipo'        => 'ARCHIVO',
            'descripcion' => 'Padrón SIRCIP de la Comisión Arbitral (RG CA 9/2025)',
            'preparar'    => 'padSircipPreparar',
            'parsear'     => 'padSircipParsear',
        ],
    ];
}

function perIibbDriverPadron($formato) {
    $drivers = perIibbDriversPadron();
    if (!isset($drivers[$formato])) throw new Exception('Formato de padrón no soportado: ' . ($formato ?: '(vacío)'));
    return $drivers[$formato];
}

function perIibbDriversDdjj() {
    return [
        'SIRCAR_D1' => 'perDdjjSircarD1',
        'SIRCIP'    => 'perDdjjSircip',
    ];
}

function perIibbDriverDdjj($formato) {
    $drivers = perIibbDriversDdjj();
    if (!isset($drivers[$formato])) throw new Exception('Formato de DDJJ no soportado: ' . ($formato ?: '(vacío)'));
    return $drivers[$formato];
}

// ------------------------------------------------------------------------------------------------ SIRCIP (padrón)

/** Necesita el código CM de la jurisdicción (901-924): define la posición del dígito en el campo 7. */
function padSircipPreparar($jur) {
    $codigo_cm = intval($jur['codigo_cm'] ?? 0);
    if ($codigo_cm < 901 || $codigo_cm > 924) throw new Exception('Falta el código de jurisdicción CM (901-924) en la configuración.');
    return ['posicion' => $codigo_cm - 900, 'periodo' => null];
}

/**
 * Línea SIRCIP: periodo(aaaamm), cuit, razón social, jurisdicción sede, CRC, letra de alícuota, campo 7 (25 dígitos).
 * La razón social puede tener comas: se toman fijos los 2 primeros y los 4 últimos campos.
 * Campo 7 de la jurisdicción: 1 adherida con alta -> PADRON (alícuota de la letra); 2 adherida sin alta ->
 * INSCRIPTO_OTRA_JURISDICCION (percepción por falta de alta, con el CRC); 3 sin alta y sin sobre alícuota ->
 * NO_PERCIBIR; 4/5 jurisdicción no adherida -> no aplica (null: el motor sigue por la condición del cliente).
 */
function padSircipParsear($linea, &$ctx) {
    $c = str_getcsv($linea);
    if (count($c) < 7) return 'faltan campos';
    $periodo = trim($c[0]);
    $cuit = preg_replace('/\D/', '', $c[1]);
    $campo7 = trim($c[count($c) - 1]);
    $letra = strtoupper(trim($c[count($c) - 2]));
    $crc = trim($c[count($c) - 3]);
    $sede = trim($c[count($c) - 4]);
    $razon = trim(implode(',', array_slice($c, 2, count($c) - 6)));
    if (!preg_match('/^\d{6}$/', $periodo)) return 'período inválido';
    if (strlen($cuit) !== 11) return 'CUIT inválido';
    $letras = perIibbLetrasSircip();
    if (!isset($letras[$letra])) return "letra de alícuota '$letra' desconocida";
    if (!preg_match('/^\d{25}$/', $campo7)) return 'campo 7 inválido';
    if ($ctx['periodo'] === null) $ctx['periodo'] = $periodo;
    elseif ($periodo !== $ctx['periodo']) throw new Exception("El archivo mezcla períodos ({$ctx['periodo']} y $periodo).");

    // 924..901 de izquierda a derecha + control al final: la jurisdicción 9NN está en la posición 25-1-NN
    $digito = intval($campo7[25 - 1 - $ctx['posicion']]);
    $situaciones = [1 => 'PADRON', 2 => 'INSCRIPTO_OTRA_JURISDICCION', 3 => 'NO_PERCIBIR'];
    if (!isset($situaciones[$digito])) return null;

    $desde = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01';
    return [
        'cuit' => $cuit, 'razon_social' => mb_substr($razon, 0, 70),
        'f_desde' => $desde, 'f_hasta' => date('Y-m-t', strtotime($desde)),
        'alicuota' => $letras[$letra], 'situacion' => $situaciones[$digito], 'codigo_origen' => $letra,
        'crc' => $crc !== '' ? intval($crc) : null,
        'jurisdiccion_sede_cm' => substr(preg_replace('/\D/', '', $sede), 0, 3) ?: null,
        'periodo' => $periodo, 'letra' => $letra, 'digito' => $digito,
    ];
}
