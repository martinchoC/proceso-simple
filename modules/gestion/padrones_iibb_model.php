<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
/**
 * Padrones IIBB (página 115): importación y consulta del padrón de alícuotas de percepción por CUIT.
 * La importación es genérica: el formato del archivo lo resuelve el driver de formato_padron
 * (percepciones_iibb_drivers.php; hoy SIRCIP), que entrega filas normalizadas con vigencia y situación.
 * Diseño: _local/docs/gestion/09_percepciones_iibb.md §7 y §12.
 */
require_once __DIR__ . '/percepciones_iibb_core_model.php';
require_once __DIR__ . '/percepciones_iibb_drivers.php';

const PAD_LOTE = 1000;

/** Jurisdicciones de la empresa con padrón configurado (config de percepción IIBB con formato_padron). */
function padJurisdicciones($conexion, $empresa_id) {
    return perIibbFilas($conexion,
        "SELECT cfg.jurisdiccion_id, j.jurisdiccion_nombre, MAX(cfg.formato_padron) AS formato_padron,
                MAX(COALESCE(cfg.codigo_jurisdiccion_cm, s.codigo_comarb)) AS codigo_cm
         FROM gestion__empresas_impuestos_config cfg
         JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
         LEFT JOIN gestion__iibb_jurisdicciones_sedes s ON s.provincia_id = j.provincia_id
         WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ? AND cfg.formato_padron IS NOT NULL AND cfg.formato_padron <> ''
         GROUP BY cfg.jurisdiccion_id, j.jurisdiccion_nombre
         ORDER BY j.jurisdiccion_nombre",
        'ii', intval($empresa_id), PER_IIBB_IMPUESTO_TIPO_ID);
}

function padListarImportaciones($conexion, $empresa_id) {
    return perIibbFilas($conexion,
        "SELECT i.*, j.jurisdiccion_nombre, u.usuario AS usuario_nombre,
                CASE i.tabla_estado_registro_id WHEN 1 THEN 'Vigente' ELSE 'Reemplazada' END AS estado
         FROM gestion__padrones_iibb_importaciones i
         JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = i.jurisdiccion_id
         LEFT JOIN conf__usuarios u ON u.usuario_id = i.usuario_id
         WHERE i.empresa_id = ?
         ORDER BY i.padron_importacion_id DESC LIMIT 200",
        'i', intval($empresa_id));
}

/** Abre el archivo subido (ZIP con un TXT, o TXT) y devuelve un handle de lectura de texto. */
function padAbrirArchivo($ruta, $nombre) {
    $es_zip = strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) === 'zip';
    if (!$es_zip) {
        $fh = fopen($ruta, 'rb');
        $firma = $fh ? fread($fh, 2) : '';
        if ($fh) fclose($fh);
        $es_zip = $firma === "PK";
    }
    if ($es_zip) {
        if (!class_exists('ZipArchive')) throw new Exception('El servidor no tiene la extensión zip de PHP: suba el TXT descomprimido.');
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) throw new Exception('El ZIP no se puede abrir.');
        $entrada = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (substr($n, -1) !== '/') { $entrada = $n; break; }
        }
        $zip->close();
        if ($entrada === null) throw new Exception('El ZIP está vacío.');
        $fh = fopen('zip://' . $ruta . '#' . $entrada, 'rb');
        if (!$fh) throw new Exception('No se pudo leer el archivo dentro del ZIP.');
        return $fh;
    }
    $fh = fopen($ruta, 'rb');
    if (!$fh) throw new Exception('No se pudo leer el archivo.');
    return $fh;
}

function padInsertarLote($conexion, $filas, $empresa_id, $jurisdiccion_id, $importacion_id, $usuario_id) {
    if (!$filas) return;
    $marcas = implode(',', array_fill(0, count($filas), "(?, ?, ?, 'ARCHIVO', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"));
    $sql = "INSERT INTO gestion__padrones_iibb
                (empresa_id, jurisdiccion_id, padron_importacion_id, fuente, f_desde, f_hasta, cuit, razon_social,
                 alicuota_percepcion, situacion, codigo_origen, crc, jurisdiccion_sede_cm,
                 periodo, letra_alicuota, situacion_jurisdiccion, usuario_id, tabla_estado_registro_id)
            VALUES $marcas
            ON DUPLICATE KEY UPDATE padron_importacion_id = VALUES(padron_importacion_id), f_hasta = VALUES(f_hasta),
                razon_social = VALUES(razon_social), alicuota_percepcion = VALUES(alicuota_percepcion),
                situacion = VALUES(situacion), codigo_origen = VALUES(codigo_origen), crc = VALUES(crc),
                jurisdiccion_sede_cm = VALUES(jurisdiccion_sede_cm), periodo = VALUES(periodo),
                letra_alicuota = VALUES(letra_alicuota), situacion_jurisdiccion = VALUES(situacion_jurisdiccion),
                usuario_id = VALUES(usuario_id), tabla_estado_registro_id = 1";
    $tipos = '';
    $params = [];
    foreach ($filas as $f) {
        $tipos .= 'iiissssdssisssii';
        array_push($params, $empresa_id, $jurisdiccion_id, $importacion_id, $f['f_desde'], $f['f_hasta'], $f['cuit'],
            $f['razon_social'] ?? null, $f['alicuota'], $f['situacion'], $f['codigo_origen'] ?? null, $f['crc'] ?? null,
            $f['jurisdiccion_sede_cm'] ?? null, $f['periodo'] ?? null, $f['letra'] ?? null, $f['digito'] ?? null, $usuario_id);
    }
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('Error guardando el padrón: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
}

/**
 * Importa el archivo de padrón de una jurisdicción con el driver de su formato_padron.
 * Reemplaza lo importado antes (fuente ARCHIVO) que se superponga con la vigencia del archivo; no toca las filas
 * MANUAL ni WS. Las filas que el driver marca como "no aplica" no se guardan.
 */
function padImportar($conexion, $empresa_id, $usuario_id, $jurisdiccion_id, $archivo) {
    $empresa_id = intval($empresa_id);
    $jurisdiccion_id = intval($jurisdiccion_id);
    error_log("=== INICIO padImportar empresa $empresa_id jur $jurisdiccion_id ===");
    $jur = null;
    foreach (padJurisdicciones($conexion, $empresa_id) as $j) if (intval($j['jurisdiccion_id']) === $jurisdiccion_id) $jur = $j;
    if (!$jur) throw new Exception('La jurisdicción no tiene padrón configurado (Impuestos Adic. - Conf → Agente de Percepción).');
    if (empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
        $err = intval($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        throw new Exception($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
            ? 'El archivo supera el tamaño máximo permitido por el servidor (upload_max_filesize). Súbalo comprimido en ZIP.'
            : 'No se recibió el archivo.');
    }
    return padImportarArchivo($conexion, $empresa_id, $usuario_id, $jur, $archivo['tmp_name'], basename((string)$archivo['name']));
}

/** Importa un archivo ya en disco (separado de padImportar para poder probarlo sin upload HTTP). */
function padImportarArchivo($conexion, $empresa_id, $usuario_id, $jur, $ruta, $nombre) {
    $empresa_id = intval($empresa_id);
    $jurisdiccion_id = intval($jur['jurisdiccion_id']);
    $formato = (string)$jur['formato_padron'];
    $driver = perIibbDriverPadron($formato);
    if ($driver['tipo'] !== 'ARCHIVO') throw new Exception("El formato $formato no se importa desde archivo.");
    $ctx = call_user_func($driver['preparar'], $jur);
    $hash = sha1_file($ruta);
    @set_time_limit(600);

    $fh = padAbrirArchivo($ruta, $nombre);
    $leidas = 0; $cargadas = 0; $rechazadas = 0; $validas = 0; $ejemplos = []; $lote = [];
    $desde = null; $hasta = null; $periodo = null;

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__padrones_iibb_importaciones
            (empresa_id, jurisdiccion_id, formato, periodo, archivo_nombre, archivo_hash, usuario_id, tabla_estado_registro_id)
            VALUES (?, ?, ?, NULL, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, 'iisssi', $empresa_id, $jurisdiccion_id, $formato, $nombre, $hash, $usuario_id);
        mysqli_stmt_execute($stmt);
        $importacion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        while (($linea = fgets($fh)) !== false) {
            $linea = trim($linea);
            if ($linea === '') continue;
            if ($leidas === 0 && !preg_match('/^\d/', $linea)) continue;   // encabezado
            $leidas++;
            $r = call_user_func_array($driver['parsear'], [$linea, &$ctx]);
            if (is_string($r)) {
                $rechazadas++;
                if (count($ejemplos) < 5) $ejemplos[] = "Línea $leidas: $r";
                continue;
            }
            $validas++;
            if ($r === null) continue;   // la fuente dice que no aplica para ese CUIT
            if ($desde === null || $r['f_desde'] < $desde) $desde = $r['f_desde'];
            if ($hasta === null || $r['f_hasta'] > $hasta) $hasta = $r['f_hasta'];
            if (!empty($r['periodo'])) $periodo = $r['periodo'];
            $lote[] = $r;
            $cargadas++;
            if (count($lote) >= PAD_LOTE) { padInsertarLote($conexion, $lote, $empresa_id, $jurisdiccion_id, $importacion_id, $usuario_id); $lote = []; }
        }
        fclose($fh);
        padInsertarLote($conexion, $lote, $empresa_id, $jurisdiccion_id, $importacion_id, $usuario_id);
        if ($validas === 0) throw new Exception('El archivo no tiene registros válidos.' . ($ejemplos ? ' ' . implode(' ', $ejemplos) : ''));
        if ($desde === null && !empty($ctx['periodo'])) {   // ningún CUIT aplica: igual reemplaza el período
            $periodo = $ctx['periodo'];
            $desde = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01';
            $hasta = date('Y-m-t', strtotime($desde));
        }

        if ($desde !== null) {
            // Reemplaza lo importado antes en esa vigencia: filas e importaciones anteriores
            $del = mysqli_prepare($conexion, "DELETE FROM gestion__padrones_iibb
                WHERE empresa_id = ? AND jurisdiccion_id = ? AND fuente = 'ARCHIVO' AND padron_importacion_id <> ?
                  AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)");
            mysqli_stmt_bind_param($del, 'iiiss', $empresa_id, $jurisdiccion_id, $importacion_id, $hasta, $desde);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            $upd = mysqli_prepare($conexion, "UPDATE gestion__padrones_iibb_importaciones SET tabla_estado_registro_id = 2
                WHERE empresa_id = ? AND jurisdiccion_id = ? AND padron_importacion_id <> ?
                  AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)");
            mysqli_stmt_bind_param($upd, 'iiiss', $empresa_id, $jurisdiccion_id, $importacion_id, $hasta, $desde);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }

        $obs = $ejemplos ? mb_substr(implode(' | ', $ejemplos), 0, 1000) : null;
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__padrones_iibb_importaciones
            SET periodo = ?, f_desde = ?, f_hasta = ?, filas_leidas = ?, filas_cargadas = ?, filas_rechazadas = ?, observaciones = ?
            WHERE padron_importacion_id = ?");
        mysqli_stmt_bind_param($stmt, 'sssiiisi', $periodo, $desde, $hasta, $leidas, $cargadas, $rechazadas, $obs, $importacion_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['padron_importacion_id' => $importacion_id, 'periodo' => $periodo, 'f_desde' => $desde, 'f_hasta' => $hasta,
                'leidas' => $leidas, 'cargadas' => $cargadas, 'rechazadas' => $rechazadas, 'ejemplos' => $ejemplos];
    } catch (Exception $e) {
        if (is_resource($fh)) fclose($fh);
        mysqli_rollback($conexion);
        error_log('ERROR padImportar: ' . $e->getMessage());
        throw $e;
    }
}

/** Primer y último día del período aaaamm (o aaaa-mm). */
function padRangoPeriodo($periodo) {
    $p = str_replace('-', '', (string)$periodo);
    if (!preg_match('/^\d{6}$/', $p)) throw new Exception('Período inválido.');
    $desde = substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01';
    return [$desde, date('Y-m-t', strtotime($desde))];
}

/** Situación de un CUIT en el padrón de la jurisdicción para el período (la fila que usaría el motor). */
function padConsultarCuit($conexion, $empresa_id, $jurisdiccion_id, $periodo, $cuit) {
    $cuit = preg_replace('/\D/', '', (string)$cuit);
    if (strlen($cuit) !== 11) throw new Exception('CUIT inválido.');
    list($desde, $hasta) = padRangoPeriodo($periodo);
    return perIibbFila($conexion,
        "SELECT * FROM gestion__padrones_iibb
         WHERE empresa_id = ? AND jurisdiccion_id = ? AND cuit = ? AND tabla_estado_registro_id = 1
           AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
         ORDER BY FIELD(fuente, 'MANUAL', 'WS', 'ARCHIVO'), f_desde DESC LIMIT 1",
        'iisss', intval($empresa_id), intval($jurisdiccion_id), $cuit, $hasta, $desde);
}

/** Clientes de la empresa con su situación en el padrón del período (los que tienen CUIT). */
function padClientes($conexion, $empresa_id, $jurisdiccion_id, $periodo) {
    list($desde, $hasta) = padRangoPeriodo($periodo);
    return perIibbFilas($conexion,
        "SELECT e.entidad_id, COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad_nombre, e.entidad_fantasia, e.cuit, p.alicuota_percepcion, p.letra_alicuota, p.situacion_jurisdiccion,
                p.situacion, p.codigo_origen, p.fuente, p.crc
         FROM gestion__entidades e
         LEFT JOIN gestion__padrones_iibb p ON p.padron_iibb_id = (
                SELECT p2.padron_iibb_id FROM gestion__padrones_iibb p2
                 WHERE p2.empresa_id = e.empresa_id AND p2.jurisdiccion_id = ? AND p2.tabla_estado_registro_id = 1
                   AND p2.cuit = REPLACE(REPLACE(e.cuit, '-', ''), ' ', '')
                   AND p2.f_desde <= ? AND (p2.f_hasta IS NULL OR p2.f_hasta >= ?)
                 ORDER BY FIELD(p2.fuente, 'MANUAL', 'WS', 'ARCHIVO'), p2.f_desde DESC LIMIT 1)
         WHERE e.empresa_id = ? AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI') . " AND e.tabla_estado_registro_id = 1
           AND LENGTH(REPLACE(REPLACE(e.cuit, '-', ''), ' ', '')) = 11
         ORDER BY p.padron_iibb_id IS NULL, e.entidad_nombre",
        'issi', intval($jurisdiccion_id), $hasta, $desde, intval($empresa_id));
}
