<?php
/* =========================================================================
 * Historial de importaciones de listas de precios de proveedores
 * (proveedor_precios.php de gestion y gestion_normal: mismo código).
 *
 * Una importación = el archivo que subió el usuario (guardado tal cual en
 * uploads/listas_proveedores/<empresa>/) + una fila de resultado por cada
 * fila del archivo y producto vinculado. El navegador lee el Excel y lo
 * manda en lotes (importar_lote); el flujo es:
 *   ppi_iniciar (sube el archivo, EN_CURSO) → importarListaProveedorDesdeExcel
 *   por lote (registra sus filas con ppi_registrar_detalles + ppi_sumar, en la
 *   transacción del lote) → ppi_finalizar (faltantes y estado final).
 * Tablas: gestion__proveedores_precios_importaciones / _detalles
 * (script 20261104_proveedor_precios_importaciones_01.sql).
 * ========================================================================= */

const PPI_DIR_REL = 'uploads/listas_proveedores';
const PPI_EXTENSIONES = ['xlsx', 'xls', 'csv'];
const PPI_MAX_BYTES = 20 * 1024 * 1024;
const PPI_CONTADORES = ['filas_archivo', 'nuevos', 'actualizados', 'sin_cambios', 'no_vinculados', 'filas_error', 'sin_fecha', 'faltantes'];

/** Cabecera de una importación de la empresa, o null. */
function ppi_obtener($conexion, $empresa_id, $importacion_id)
{
    $e = intval($empresa_id);
    $id = intval($importacion_id);
    $stmt = mysqli_prepare($conexion, "SELECT * FROM gestion__proveedores_precios_importaciones WHERE importacion_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $e);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
    return $fila;
}

/**
 * Abre una importación: guarda el archivo original (si vino) y la cabecera EN_CURSO.
 * $archivo = elemento de $_FILES o null. Lanza RuntimeException.
 */
function ppi_iniciar($conexion, $empresa_id, $entidad_id, $usuario_id, $archivo, $archivo_nombre, $filas_archivo)
{
    $e = intval($empresa_id);
    $ent = intval($entidad_id);
    $uid = intval($usuario_id);
    $filas = max(0, intval($filas_archivo));

    $stmt = mysqli_prepare($conexion, "SELECT entidad_id FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $ent, $e);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$existe) {
        throw new RuntimeException('El proveedor no es de la empresa.');
    }

    $nombre = mb_substr(basename(str_replace('\\', '/', trim((string)$archivo_nombre))), 0, 255);
    $ruta = null;
    $bytes = 0;
    $sha = null;

    if ($archivo && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            throw new RuntimeException('No se pudo recibir el archivo (código ' . intval($archivo['error']) . ').');
        }
        $bytes = intval($archivo['size']);
        if ($bytes <= 0 || $bytes > PPI_MAX_BYTES) {
            throw new RuntimeException('El archivo está vacío o supera los 20 MB.');
        }
        if ($nombre === '') {
            $nombre = mb_substr(basename(str_replace('\\', '/', (string)$archivo['name'])), 0, 255);
        }
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, PPI_EXTENSIONES, true)) {
            throw new RuntimeException('El archivo tiene que ser Excel (.xlsx / .xls) o CSV.');
        }

        $base = __DIR__ . '/../' . PPI_DIR_REL;
        $dir = $base . '/' . $e;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('No se pudo crear la carpeta de archivos.');
        }
        if (!file_exists($base . '/.htaccess')) {
            @file_put_contents($base . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        $guardado = date('Ymd') . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($archivo['tmp_name'], $dir . '/' . $guardado)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }
        $ruta = PPI_DIR_REL . '/' . $e . '/' . $guardado;
        $sha = hash_file('sha256', $dir . '/' . $guardado);
    }

    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__proveedores_precios_importaciones
            (empresa_id, entidad_id, archivo_nombre, archivo_ruta, archivo_bytes, archivo_sha256, filas_archivo, estado, usuario_id, fecha_inicio)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'EN_CURSO', ?, NOW())");
    mysqli_stmt_bind_param($stmt, "iissisii", $e, $ent, $nombre, $ruta, $bytes, $sha, $filas, $uid);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('No se pudo registrar la importación: ' . $err);
    }
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    // ¿El mismo archivo ya se importó para este proveedor? Solo aviso.
    $previa = null;
    if ($sha) {
        $stmt = mysqli_prepare($conexion, "SELECT importacion_id, fecha_inicio FROM gestion__proveedores_precios_importaciones
                WHERE empresa_id = ? AND entidad_id = ? AND archivo_sha256 = ? AND importacion_id <> ?
                ORDER BY importacion_id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iisi", $e, $ent, $sha, $id);
        mysqli_stmt_execute($stmt);
        $previa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
    }

    error_log("=== ppi_iniciar importación $id empresa $e proveedor $ent archivo '$nombre' ($bytes bytes, $filas filas) ===");
    return ['importacion_id' => $id, 'previa' => $previa];
}

/** Importación EN_CURSO de la empresa y del proveedor (para aceptar lotes), o RuntimeException. */
function ppi_exigir_en_curso($conexion, $empresa_id, $entidad_id, $importacion_id)
{
    $imp = ppi_obtener($conexion, $empresa_id, $importacion_id);
    if (!$imp || intval($imp['entidad_id']) !== intval($entidad_id)) {
        throw new RuntimeException('La importación no existe o es de otro proveedor.');
    }
    if ($imp['estado'] !== 'EN_CURSO') {
        throw new RuntimeException('La importación ya está cerrada.');
    }
    return $imp;
}

/**
 * Inserta filas de resultado. Cada fila: fila_nro, codigo_proveedor, descripcion, precio_texto,
 * precio, precio_anterior, f_vigencia_desde, producto_proveedor_id, producto_id, resultado, mensaje.
 * No abre transacción (va dentro de la del lote).
 */
function ppi_registrar_detalles($conexion, $empresa_id, $importacion_id, array $filas)
{
    if (!$filas) return;
    $e = intval($empresa_id);
    $id = intval($importacion_id);
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__proveedores_precios_importaciones_detalles
            (importacion_id, empresa_id, fila_nro, codigo_proveedor, descripcion, precio_texto, precio, precio_anterior,
             f_vigencia_desde, producto_proveedor_id, producto_id, resultado, mensaje)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $fila_nro = null; $codigo = ''; $desc = ''; $ptexto = ''; $precio = null; $panterior = null;
    $fecha = null; $pp_id = null; $prod_id = null; $resultado = ''; $mensaje = '';
    mysqli_stmt_bind_param($stmt, "iiisssddsiiss", $id, $e, $fila_nro, $codigo, $desc, $ptexto, $precio, $panterior,
        $fecha, $pp_id, $prod_id, $resultado, $mensaje);
    foreach ($filas as $f) {
        $fila_nro = isset($f['fila_nro']) && $f['fila_nro'] !== null ? intval($f['fila_nro']) : null;
        $codigo = mb_substr((string)($f['codigo_proveedor'] ?? ''), 0, 100);
        $desc = mb_substr((string)($f['descripcion'] ?? ''), 0, 255);
        $ptexto = mb_substr((string)($f['precio_texto'] ?? ''), 0, 60);
        $precio = isset($f['precio']) && $f['precio'] !== null ? (float)$f['precio'] : null;
        $panterior = isset($f['precio_anterior']) && $f['precio_anterior'] !== null ? (float)$f['precio_anterior'] : null;
        $fecha = !empty($f['f_vigencia_desde']) ? $f['f_vigencia_desde'] : null;
        $pp_id = !empty($f['producto_proveedor_id']) ? intval($f['producto_proveedor_id']) : null;
        $prod_id = !empty($f['producto_id']) ? intval($f['producto_id']) : null;
        $resultado = (string)$f['resultado'];
        $mensaje = mb_substr((string)($f['mensaje'] ?? ''), 0, 255);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException('No se pudo registrar el resultado de la fila: ' . $err);
        }
    }
    mysqli_stmt_close($stmt);
}

/** Suma contadores a la cabecera (claves de PPI_CONTADORES). */
function ppi_sumar($conexion, $empresa_id, $importacion_id, array $contadores)
{
    $sets = [];
    $valores = [];
    foreach (PPI_CONTADORES as $c) {
        if (!empty($contadores[$c])) {
            $sets[] = "$c = $c + ?";
            $valores[] = intval($contadores[$c]);
        }
    }
    if (!$sets) return;
    $valores[] = intval($importacion_id);
    $valores[] = intval($empresa_id);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__proveedores_precios_importaciones SET " . implode(', ', $sets) .
        " WHERE importacion_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($valores)), ...$valores);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Cierra la importación: registra los vinculados que no vinieron en el archivo
 * ($faltantes = filas de obtenerVinculosActivosProveedor) y fija el estado.
 */
function ppi_finalizar($conexion, $empresa_id, $importacion_id, array $faltantes, $lotes_fallidos)
{
    $e = intval($empresa_id);
    $id = intval($importacion_id);
    $lf = max(0, intval($lotes_fallidos));

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "SELECT importacion_id FROM gestion__proveedores_precios_importaciones
                WHERE importacion_id = ? AND empresa_id = ? AND estado = 'EN_CURSO' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "ii", $id, $e);
        mysqli_stmt_execute($stmt);
        $abierta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$abierta) {
            mysqli_rollback($conexion);
            return;
        }

        $filas = [];
        foreach ($faltantes as $v) {
            $filas[] = [
                'fila_nro' => null,
                'codigo_proveedor' => (string)($v['codigo_proveedor'] ?? ''),
                'descripcion' => (string)($v['producto_nombre'] ?? ''),
                'precio_anterior' => $v['precio_lista'] ?? null,
                'producto_proveedor_id' => $v['producto_proveedor_id'] ?? null,
                'producto_id' => $v['producto_id'] ?? null,
                'resultado' => 'FALTANTE',
                'mensaje' => trim((string)($v['codigo_proveedor'] ?? '')) === ''
                    ? 'El vínculo no tiene código del proveedor'
                    : 'Vinculado al proveedor y no vino en el archivo: su precio no cambió',
            ];
        }
        ppi_registrar_detalles($conexion, $e, $id, $filas);

        $nf = count($faltantes);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__proveedores_precios_importaciones
                SET faltantes = ?, lotes_fallidos = ?, fecha_fin = NOW(),
                    estado = IF(filas_error > 0 OR ? > 0, 'CON_ERRORES', 'FINALIZADA')
                WHERE importacion_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "iiiii", $nf, $lf, $lf, $id, $e);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
    } catch (Throwable $ex) {
        mysqli_rollback($conexion);
        error_log("ERROR ppi_finalizar importación $id: " . $ex->getMessage());
        throw $ex;
    }
}

/** Importaciones de la empresa (de un proveedor o de todos), las más nuevas primero. */
function ppi_listar($conexion, $empresa_id, $entidad_id, $limite = 500)
{
    $e = intval($empresa_id);
    $ent = intval($entidad_id);
    $lim = max(1, min(2000, intval($limite)));
    $sql = "SELECT i.importacion_id, i.entidad_id, i.archivo_nombre, (i.archivo_ruta IS NOT NULL) AS tiene_archivo,
                   i.archivo_bytes, i.filas_archivo, i.nuevos, i.actualizados, i.sin_cambios, i.no_vinculados,
                   i.filas_error, i.sin_fecha, i.faltantes, i.lotes_fallidos, i.estado, i.fecha_inicio, i.fecha_fin,
                   (i.estado = 'EN_CURSO' AND i.fecha_inicio < NOW() - INTERVAL 1 HOUR) AS interrumpida,
                   COALESCE(NULLIF(TRIM(en.entidad_fantasia), ''), en.entidad_nombre) AS entidad_nombre,
                   u.usuario_nombre
              FROM gestion__proveedores_precios_importaciones i
              LEFT JOIN gestion__entidades en ON en.entidad_id = i.entidad_id AND en.empresa_id = i.empresa_id
              LEFT JOIN conf__usuarios u ON u.usuario_id = i.usuario_id
             WHERE i.empresa_id = ? AND (? = 0 OR i.entidad_id = ?)
             ORDER BY i.importacion_id DESC
             LIMIT $lim";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $e, $ent, $ent);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $filas[] = $f;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

/** Filas de resultado de una importación de la empresa, en el orden del archivo (faltantes al final). */
function ppi_detalle($conexion, $empresa_id, $importacion_id)
{
    $e = intval($empresa_id);
    $id = intval($importacion_id);
    $sql = "SELECT d.fila_nro, d.codigo_proveedor, d.descripcion, d.precio_texto, d.precio, d.precio_anterior,
                   d.f_vigencia_desde, d.resultado, d.mensaje, d.producto_id,
                   p.producto_codigo, p.producto_nombre
              FROM gestion__proveedores_precios_importaciones_detalles d
              LEFT JOIN gestion__productos p ON p.producto_id = d.producto_id AND p.empresa_id = d.empresa_id
             WHERE d.importacion_id = ? AND d.empresa_id = ?
             ORDER BY d.fila_nro IS NULL, d.fila_nro, d.detalle_id";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $e);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $filas[] = $f;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

/** Archivo original de una importación de la empresa: ['ruta_abs', 'nombre'] o null. */
function ppi_archivo($conexion, $empresa_id, $importacion_id)
{
    $imp = ppi_obtener($conexion, $empresa_id, $importacion_id);
    if (!$imp || empty($imp['archivo_ruta'])) return null;
    $base = realpath(__DIR__ . '/../' . PPI_DIR_REL . '/' . intval($empresa_id));
    $ruta = realpath(__DIR__ . '/../' . $imp['archivo_ruta']);
    if (!$base || !$ruta || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0) return null;
    return ['ruta_abs' => $ruta, 'nombre' => $imp['archivo_nombre'] !== '' ? $imp['archivo_nombre'] : basename($ruta)];
}
