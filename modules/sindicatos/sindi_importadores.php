<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_importadores.php
 * Parseadores e importadores de nómina para DDJJ:
 * - Archivo F.931 SICOSS de ARCA (ancho fijo oficial)
 * - Archivos CSV / Planillas tabulares
 */

require_once __DIR__ . '/../../db.php';

function sindi_validar_cuil_arg(string $cuil): bool
{
    $cuil = preg_replace('/\D/', '', $cuil);
    if (strlen($cuil) !== 11) return false;

    $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $acum = 0;
    for ($i = 0; $i < 10; $i++) {
        $acum += intval($cuil[$i]) * $mult[$i];
    }
    $resto = $acum % 11;
    $dv = 11 - $resto;
    if ($dv === 11) $dv = 0;
    if ($dv === 10) $dv = 9;
    return intval($cuil[10]) === $dv;
}

/**
 * Parsea archivo F.931 (SICOSS) de ancho fijo de ARCA/AFIP.
 */
function sindi_parsear_f931(string $contenidoTexto, mysqli $conexion, int $empresa_id, ?int $categoria_default_id = null): array
{
    // Obtener categoría por defecto si no viene
    if ($categoria_default_id === null || $categoria_default_id <= 0) {
        $sqlC = "SELECT categoria_id FROM sindi__categorias WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY categoria_id ASC LIMIT 1";
        $stmtC = mysqli_prepare($conexion, $sqlC);
        mysqli_stmt_bind_param($stmtC, "i", $empresa_id);
        mysqli_stmt_execute($stmtC);
        $rC = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
        mysqli_stmt_close($stmtC);
        $categoria_default_id = (int)($rC['categoria_id'] ?? 1);
    }

    $lineas = explode("\n", str_replace("\r", "", $contenidoTexto));
    $empleados = [];
    $errores = [];
    $cuilsVistos = [];
    $nroFila = 0;

    foreach ($lineas as $linea) {
        $nroFila++;
        $len = strlen(rtrim($linea));
        if ($len < 50) {
            // Ignorar líneas vacías o de cabecera
            continue;
        }

        $cuilRaw = substr($linea, 0, 11);
        $cuil = preg_replace('/\D/', '', $cuilRaw);

        if (!sindi_validar_cuil_arg($cuil)) {
            $errores[] = "Fila {$nroFila}: CUIL inválido '{$cuilRaw}'.";
            continue;
        }

        if (isset($cuilsVistos[$cuil])) {
            $errores[] = "Fila {$nroFila}: CUIL {$cuil} duplicado en el mismo archivo (ignorado).";
            continue;
        }
        $cuilsVistos[$cuil] = true;

        $nombreRaw = trim(substr($linea, 11, 30));
        $jubiladoCod = intval(substr($linea, 46, 2));
        $esJubilado = ($jubiladoCod === 2 || $jubiladoCod === 1) ? 1 : 0;

        $rnosRaw = intval(substr($linea, 61, 6));
        $obraSocialId = null;
        if ($rnosRaw > 0) {
            $sqlOS = "SELECT obra_social_id FROM sindi__obras_sociales WHERE codigo_rnos = LPAD(?, 6, '0') LIMIT 1";
            $stmtOS = mysqli_prepare($conexion, $sqlOS);
            mysqli_stmt_bind_param($stmtOS, "i", $rnosRaw);
            mysqli_stmt_execute($stmtOS);
            $rOS = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtOS));
            mysqli_stmt_close($stmtOS);
            if ($rOS) {
                $obraSocialId = (int)$rOS['obra_social_id'];
            }
        }

        // Remuneraciones en posiciones 212 y 405
        $brutoStr = trim(substr($linea, 212, 12));
        $noRemuStr = ($len >= 417) ? trim(substr($linea, 405, 12)) : '0';

        $bruto = floatval(str_replace(',', '.', $brutoStr));
        $noRemu = floatval(str_replace(',', '.', $noRemuStr));

        // En F.931 la remuneración total incluye no remunerativo
        $rem1 = max(0.0, round($bruto - $noRemu, 2));
        $rem2 = max(0.0, round($noRemu, 2));

        // Detectar si la persona ya era afiliada sindical previamente
        $esAfiliado = 0;
        $sqlAfil = "SELECT a.afiliacion_id FROM sindi__afiliaciones a
                    INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
                    WHERE a.empresa_id = ? AND p.cuil = ? AND a.tabla_estado_registro_id = 1 LIMIT 1";
        $stmtA = mysqli_prepare($conexion, $sqlAfil);
        mysqli_stmt_bind_param($stmtA, "is", $empresa_id, $cuil);
        mysqli_stmt_execute($stmtA);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA))) {
            $esAfiliado = 1;
        }
        mysqli_stmt_close($stmtA);

        $empleados[] = [
            'cuil' => $cuil,
            'nombre' => $nombreRaw,
            'categoria_id' => $categoria_default_id,
            'rem_1' => $rem1,
            'rem_2' => $rem2,
            'rem_3' => 0.00,
            'media_jornada' => 0,
            'jubilado' => $esJubilado,
            'afiliado' => $esAfiliado,
            'obra_social_id' => $obraSocialId,
            'novedad_id' => null,
            'baja_motivo_id' => null,
            'f_baja' => null
        ];
    }

    return [
        'success' => count($empleados) > 0,
        'empleados' => $empleados,
        'total_filas' => $nroFila,
        'aceptadas' => count($empleados),
        'errores' => $errores
    ];
}

/**
 * Parsea archivo delimitado CSV o TSV con nómina de empleados.
 * Formato esperado de cabecera: CUIL, Nombre, Categoria, Remunerativo, NoRemunerativo, MediaJornada, Afiliado, Jubilado
 */
function sindi_parsear_csv(string $contenidoTexto, mysqli $conexion, int $empresa_id, ?int $categoria_default_id = null): array
{
    $lineas = explode("\n", str_replace("\r", "", $contenidoTexto));
    $empleados = [];
    $errores = [];
    $cuilsVistos = [];
    $nroFila = 0;

    // Detectar separador (, o ;)
    $primeraLinea = $lineas[0] ?? '';
    $sep = (substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',')) ? ';' : ',';

    $esPrimera = true;
    foreach ($lineas as $linea) {
        $nroFila++;
        $linea = trim($linea);
        if ($linea === '') continue;

        $campos = str_getcsv($linea, $sep);
        if (empty($campos) || count($campos) < 2) continue;

        if ($esPrimera) {
            $esPrimera = false;
            // Saltear encabezado si la primera columna contiene 'CUIL'
            if (stripos($campos[0], 'cuil') !== false) {
                continue;
            }
        }

        $cuil = preg_replace('/\D/', '', (string)($campos[0] ?? ''));
        if (!sindi_validar_cuil_arg($cuil)) {
            $errores[] = "Fila {$nroFila}: CUIL inválido '{$campos[0]}'.";
            continue;
        }

        if (isset($cuilsVistos[$cuil])) {
            $errores[] = "Fila {$nroFila}: CUIL {$cuil} repetido en la misma planilla (ignorado).";
            continue;
        }
        $cuilsVistos[$cuil] = true;

        $nombre = trim((string)($campos[1] ?? 'Empleado'));
        $catInput = trim((string)($campos[2] ?? ''));
        $categoriaId = $categoria_default_id ?? 1;

        if (!empty($catInput)) {
            if (is_numeric($catInput)) {
                $categoriaId = intval($catInput);
            } else {
                $sqlCat = "SELECT categoria_id FROM sindi__categorias WHERE empresa_id = ? AND (codigo LIKE ? OR nombre LIKE ?) LIMIT 1";
                $stmtCat = mysqli_prepare($conexion, $sqlCat);
                $busq = "%{$catInput}%";
                mysqli_stmt_bind_param($stmtCat, "iss", $empresa_id, $busq, $busq);
                mysqli_stmt_execute($stmtCat);
                $rCat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCat));
                mysqli_stmt_close($stmtCat);
                if ($rCat) {
                    $categoriaId = (int)$rCat['categoria_id'];
                }
            }
        }

        $rem1 = max(0.0, floatval(str_replace(',', '.', (string)($campos[3] ?? '0'))));
        $rem2 = max(0.0, floatval(str_replace(',', '.', (string)($campos[4] ?? '0'))));
        $mediaJornada = (!empty($campos[5]) && in_array(strtolower((string)$campos[5]), ['1', 's', 'si', 'true', 'on'], true)) ? 1 : 0;
        $afiliado = (!empty($campos[6]) && in_array(strtolower((string)$campos[6]), ['1', 's', 'si', 'true', 'on'], true)) ? 1 : 0;
        $jubilado = (!empty($campos[7]) && in_array(strtolower((string)$campos[7]), ['1', 's', 'si', 'true', 'on'], true)) ? 1 : 0;

        $empleados[] = [
            'cuil' => $cuil,
            'nombre' => $nombre,
            'categoria_id' => $categoriaId,
            'rem_1' => $rem1,
            'rem_2' => $rem2,
            'rem_3' => 0.00,
            'media_jornada' => $mediaJornada,
            'jubilado' => $jubilado,
            'afiliado' => $afiliado,
            'obra_social_id' => null,
            'novedad_id' => null,
            'baja_motivo_id' => null,
            'f_baja' => null
        ];
    }

    return [
        'success' => count($empleados) > 0,
        'empleados' => $empleados,
        'total_filas' => $nroFila,
        'aceptadas' => count($empleados),
        'errores' => $errores
    ];
}
