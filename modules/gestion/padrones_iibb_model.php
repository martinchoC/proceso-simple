<?php
/**
 * Padrones IIBB (página 115): importación y consulta del padrón de alícuotas de percepción por CUIT.
 * Formato soportado: SIRCIP (RG CA 9/2025) — ZIP con un TXT separado por comas:
 *   periodo(aaaamm), cuit, razón social, jurisdicción sede, CRC, letra de alícuota, campo 7 (25 dígitos)
 * Campo 7: posiciones 924..901 + dígito de control; se guarda el dígito de la jurisdicción de la config
 * (1 adherida con alta, 2 adherida sin alta, 3 adherida sin alta y sin sobre alícuota, 4/5 no adherida).
 * Diseño: docs/gestion/09_percepciones_iibb.md §2.2 y §7.
 */
require_once __DIR__ . '/percepciones_iibb_core_model.php';

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

/**
 * Parsea una línea SIRCIP. Devuelve el registro o un string con el error. La razón social puede tener comas:
 * se toman fijos los 2 primeros y los 4 últimos campos.
 */
function padParsearSircip($linea, $posicion_jur) {
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
    // 924..901 de izquierda a derecha + control al final: la jurisdicción 9NN está en la posición 25-1-NN
    $digito = intval($campo7[25 - 1 - $posicion_jur]);
    return [
        'periodo' => $periodo, 'cuit' => $cuit, 'razon_social' => mb_substr($razon, 0, 70),
        'jurisdiccion_sede_cm' => substr(preg_replace('/\D/', '', $sede), 0, 3) ?: null,
        'crc' => $crc !== '' ? intval($crc) : null, 'letra' => $letra, 'alicuota' => $letras[$letra], 'digito' => $digito,
    ];
}

function padInsertarLote($conexion, $filas, $empresa_id, $jurisdiccion_id, $importacion_id) {
    if (!$filas) return;
    $marcas = implode(',', array_fill(0, count($filas), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
    $sql = "INSERT INTO gestion__padrones_iibb
                (empresa_id, jurisdiccion_id, padron_importacion_id, periodo, cuit, razon_social, alicuota_percepcion,
                 letra_alicuota, crc, situacion_jurisdiccion, jurisdiccion_sede_cm)
            VALUES $marcas
            ON DUPLICATE KEY UPDATE padron_importacion_id = VALUES(padron_importacion_id), razon_social = VALUES(razon_social),
                alicuota_percepcion = VALUES(alicuota_percepcion), letra_alicuota = VALUES(letra_alicuota), crc = VALUES(crc),
                situacion_jurisdiccion = VALUES(situacion_jurisdiccion), jurisdiccion_sede_cm = VALUES(jurisdiccion_sede_cm)";
    $tipos = '';
    $params = [];
    foreach ($filas as $f) {
        $tipos .= 'iiisssdsiis';
        array_push($params, $empresa_id, $jurisdiccion_id, $importacion_id, $f['periodo'], $f['cuit'], $f['razon_social'],
            $f['alicuota'], $f['letra'], $f['crc'], $f['digito'], $f['jurisdiccion_sede_cm']);
    }
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('Error guardando el padrón: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
}

/**
 * Importa el archivo de padrón de una jurisdicción. Reemplaza el mismo período (filas e importación anterior).
 * Solo se guardan los CUIT con la jurisdicción adherida (dígitos 1, 2 y 3 del campo 7).
 */
function padImportar($conexion, $empresa_id, $usuario_id, $jurisdiccion_id, $archivo) {
    $empresa_id = intval($empresa_id);
    $jurisdiccion_id = intval($jurisdiccion_id);
    error_log("=== INICIO padImportar empresa $empresa_id jur $jurisdiccion_id ===");
    $jur = null;
    foreach (padJurisdicciones($conexion, $empresa_id) as $j) if (intval($j['jurisdiccion_id']) === $jurisdiccion_id) $jur = $j;
    if (!$jur) throw new Exception('La jurisdicción no tiene padrón configurado (Impuestos Adic. - Conf → Agente de Percepción).');
    if ($jur['formato_padron'] !== 'SIRCIP') throw new Exception('Formato de padrón no soportado: ' . $jur['formato_padron']);
    $codigo_cm = intval($jur['codigo_cm']);
    if ($codigo_cm < 901 || $codigo_cm > 924) throw new Exception('Falta el código de jurisdicción CM (901-924) en la configuración.');
    $posicion = $codigo_cm - 900;

    if (empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
        $err = intval($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        throw new Exception($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
            ? 'El archivo supera el tamaño máximo permitido por el servidor (upload_max_filesize). Súbalo comprimido en ZIP.'
            : 'No se recibió el archivo.');
    }
    $nombre = basename((string)$archivo['name']);
    $hash = sha1_file($archivo['tmp_name']);
    @set_time_limit(600);

    $fh = padAbrirArchivo($archivo['tmp_name'], $nombre);
    $leidas = 0; $cargadas = 0; $rechazadas = 0; $ejemplos = []; $periodo = null; $lote = [];

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__padrones_iibb_importaciones
            (empresa_id, jurisdiccion_id, formato, periodo, archivo_nombre, archivo_hash, usuario_id, tabla_estado_registro_id)
            VALUES (?, ?, 'SIRCIP', '000000', ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, 'iissi', $empresa_id, $jurisdiccion_id, $nombre, $hash, $usuario_id);
        mysqli_stmt_execute($stmt);
        $importacion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        while (($linea = fgets($fh)) !== false) {
            $linea = trim($linea);
            if ($linea === '') continue;
            if ($leidas === 0 && $cargadas === 0 && $rechazadas === 0 && !preg_match('/^\d/', $linea)) continue;   // encabezado
            $leidas++;
            $r = padParsearSircip($linea, $posicion);
            if (is_string($r)) {
                $rechazadas++;
                if (count($ejemplos) < 5) $ejemplos[] = "Línea $leidas: $r";
                continue;
            }
            if ($periodo === null) {
                $periodo = $r['periodo'];
                // Reemplaza el período: filas y importaciones anteriores
                $del = mysqli_prepare($conexion, "DELETE FROM gestion__padrones_iibb WHERE empresa_id = ? AND jurisdiccion_id = ? AND periodo = ?");
                mysqli_stmt_bind_param($del, 'iis', $empresa_id, $jurisdiccion_id, $periodo);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);
                $upd = mysqli_prepare($conexion, "UPDATE gestion__padrones_iibb_importaciones SET tabla_estado_registro_id = 2
                                                  WHERE empresa_id = ? AND jurisdiccion_id = ? AND periodo = ? AND padron_importacion_id <> ?");
                mysqli_stmt_bind_param($upd, 'iisi', $empresa_id, $jurisdiccion_id, $periodo, $importacion_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            } elseif ($r['periodo'] !== $periodo) {
                throw new Exception("El archivo mezcla períodos ($periodo y {$r['periodo']}).");
            }
            if ($r['digito'] < 1 || $r['digito'] > 3) continue;   // jurisdicción no adherida para ese CUIT: no aplica
            $lote[] = $r;
            $cargadas++;
            if (count($lote) >= PAD_LOTE) { padInsertarLote($conexion, $lote, $empresa_id, $jurisdiccion_id, $importacion_id); $lote = []; }
        }
        fclose($fh);
        padInsertarLote($conexion, $lote, $empresa_id, $jurisdiccion_id, $importacion_id);
        if ($periodo === null) throw new Exception('El archivo no tiene registros válidos.' . ($ejemplos ? ' ' . implode(' ', $ejemplos) : ''));

        $obs = $ejemplos ? mb_substr(implode(' | ', $ejemplos), 0, 1000) : null;
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__padrones_iibb_importaciones
            SET periodo = ?, filas_leidas = ?, filas_cargadas = ?, filas_rechazadas = ?, observaciones = ?
            WHERE padron_importacion_id = ?");
        mysqli_stmt_bind_param($stmt, 'siiisi', $periodo, $leidas, $cargadas, $rechazadas, $obs, $importacion_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['padron_importacion_id' => $importacion_id, 'periodo' => $periodo, 'leidas' => $leidas,
                'cargadas' => $cargadas, 'rechazadas' => $rechazadas, 'ejemplos' => $ejemplos];
    } catch (Exception $e) {
        if (is_resource($fh)) fclose($fh);
        mysqli_rollback($conexion);
        error_log('ERROR padImportar: ' . $e->getMessage());
        throw $e;
    }
}

/** Situación de un CUIT en el padrón de la jurisdicción (período). */
function padConsultarCuit($conexion, $empresa_id, $jurisdiccion_id, $periodo, $cuit) {
    $cuit = preg_replace('/\D/', '', (string)$cuit);
    if (strlen($cuit) !== 11) throw new Exception('CUIT inválido.');
    return perIibbFila($conexion,
        "SELECT * FROM gestion__padrones_iibb WHERE empresa_id = ? AND jurisdiccion_id = ? AND periodo = ? AND cuit = ?",
        'iiss', intval($empresa_id), intval($jurisdiccion_id), str_replace('-', '', (string)$periodo), $cuit);
}

/** Clientes de la empresa con su situación en el padrón del período (los que tienen CUIT). */
function padClientes($conexion, $empresa_id, $jurisdiccion_id, $periodo) {
    return perIibbFilas($conexion,
        "SELECT e.entidad_id, e.entidad_nombre, e.cuit, p.alicuota_percepcion, p.letra_alicuota, p.situacion_jurisdiccion, p.crc
         FROM gestion__entidades e
         LEFT JOIN gestion__padrones_iibb p
                ON p.empresa_id = e.empresa_id AND p.jurisdiccion_id = ? AND p.periodo = ?
               AND p.cuit = REPLACE(REPLACE(e.cuit, '-', ''), ' ', '')
         WHERE e.empresa_id = ? AND e.es_cliente = 1 AND e.tabla_estado_registro_id = 1
           AND LENGTH(REPLACE(REPLACE(e.cuit, '-', ''), ' ', '')) = 11
         ORDER BY p.padron_iibb_id IS NULL, e.entidad_nombre",
        'isi', intval($jurisdiccion_id), str_replace('-', '', (string)$periodo), intval($empresa_id));
}
