<?php
declare(strict_types=1);

/**
 * modules/sindicatos/afiliados_model.php
 * Afiliados y grupo familiar (Página 1050). La lógica de negocio está en sindi_afiliaciones_core_model.php.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/sindi_base_model.php';
require_once __DIR__ . '/sindi_afiliaciones_core_model.php';

/** Afiliaciones titulares con su grupo familiar resumido. */
function sindi_listar_afiliados(mysqli $conexion, int $empresa_id, int $pagina_id, array $filtros = []): array
{
    $where = "a.empresa_id = ? AND a.titular_afiliacion_id IS NULL AND a.tabla_estado_registro_id <> 38";
    $tipos = "i";
    $params = [$empresa_id];
    if (!empty($filtros['tipo_codigo'])) {
        $where .= " AND t.codigo = ?";
        $tipos .= "s";
        $params[] = (string)$filtros['tipo_codigo'];
    }
    if (!empty($filtros['estado'])) {
        $where .= " AND a.tabla_estado_registro_id = ?";
        $tipos .= "i";
        $params[] = (int)$filtros['estado'];
    }
    if (!empty($filtros['texto'])) {
        $like = '%' . trim((string)$filtros['texto']) . '%';
        $where .= " AND (p.cuil LIKE ? OR p.apellido LIKE ? OR p.nombre LIKE ? OR a.numero_afiliado LIKE ?)";
        $tipos .= "ssss";
        array_push($params, $like, $like, $like, $like);
    }
    $sql = "SELECT a.afiliacion_id, a.numero_afiliado, a.f_alta, a.f_baja, a.f_vigencia, a.tabla_estado_registro_id, a.origen,
                   p.persona_id, p.cuil, p.apellido, p.nombre, t.codigo AS tipo_codigo, t.nombre AS tipo_nombre,
                   ent.entidad_nombre AS empleador_nombre,
                   COALESCE(ter.tabla_estado_registro, er.estado_registro) AS estado_nombre,
                   (SELECT COUNT(*) FROM sindi__afiliaciones f WHERE f.titular_afiliacion_id = a.afiliacion_id AND f.tabla_estado_registro_id IN (1, 20)) AS familiares
            FROM sindi__afiliaciones a
            INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
            INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
            LEFT JOIN sindi__empleadores emp ON emp.empleador_id = a.empleador_id
            LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 527 AND ter.estado_registro_id = a.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = a.tabla_estado_registro_id
            WHERE {$where}
            ORDER BY p.apellido, p.nombre, a.afiliacion_id
            LIMIT 2000";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, (int)$row['tabla_estado_registro_id'], (int)$row['afiliacion_id']);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/** Ficha: titular, familiares (con documentos) y otras afiliaciones de la persona. */
function sindi_obtener_ficha_afiliado(mysqli $conexion, int $empresa_id, int $afiliacion_id): array
{
    $titular = sindi_afil_obtener($conexion, $empresa_id, $afiliacion_id);
    if (!empty($titular['titular_afiliacion_id'])) {
        $titular = sindi_afil_obtener($conexion, $empresa_id, (int)$titular['titular_afiliacion_id']);
    }
    $tid = (int)$titular['afiliacion_id'];
    $pid = (int)$titular['persona_id'];

    $stmt = mysqli_prepare($conexion, "SELECT persona_id, cuil, apellido, nombre, f_nacimiento, sexo, email, telefono FROM sindi__personas WHERE persona_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $pid);
    mysqli_stmt_execute($stmt);
    $persona = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion, "SELECT a.afiliacion_id, a.numero_afiliado, a.f_alta, a.f_baja, a.f_vigencia, a.tabla_estado_registro_id,
                                              p.cuil, p.apellido, p.nombre, p.f_nacimiento, par.nombre AS parentesco, par.codigo AS parentesco_codigo,
                                              v.documento_excepcion, (SELECT MAX(d.f_vencimiento) FROM sindi__afiliaciones_documentos d
                                                                      WHERE d.afiliacion_id = a.afiliacion_id AND d.tabla_estado_registro_id = 1) AS documento_vto
                                       FROM sindi__afiliaciones a
                                       INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
                                       LEFT JOIN sindi__parentescos par ON par.parentesco_id = a.parentesco_id
                                       LEFT JOIN sindi__afiliaciones_vigencias v ON v.empresa_id = a.empresa_id AND v.parentesco_id = a.parentesco_id
                                       WHERE a.titular_afiliacion_id = ? AND a.empresa_id = ? AND a.tabla_estado_registro_id <> 38
                                       ORDER BY a.numero_afiliado");
    mysqli_stmt_bind_param($stmt, "ii", $tid, $empresa_id);
    mysqli_stmt_execute($stmt);
    $familiares = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    $puedeCarnet = sindi_usuario_puede($conexion, 1050, 'carnet');
    foreach ($familiares as &$f) {
        $f['puede_documento'] = sindi_usuario_puede($conexion, 1050, 'documento') && !empty($f['documento_excepcion'])
            && in_array((int)$f['tabla_estado_registro_id'], [1, 20], true);
        $f['puede_baja'] = sindi_usuario_puede($conexion, 1050, 'baja') && (int)$f['tabla_estado_registro_id'] === 1;
        $f['puede_carnet'] = $puedeCarnet && (int)$f['tabla_estado_registro_id'] === 1 && (int)$titular['tabla_estado_registro_id'] === 1;
    }
    unset($f);

    $stmt = mysqli_prepare($conexion, "SELECT a.afiliacion_id, t.nombre AS tipo_nombre, a.f_alta, a.f_baja, a.tabla_estado_registro_id, ent.entidad_nombre AS empleador_nombre
                                       FROM sindi__afiliaciones a
                                       INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
                                       LEFT JOIN sindi__empleadores emp ON emp.empleador_id = a.empleador_id
                                       LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
                                       WHERE a.empresa_id = ? AND a.persona_id = ? AND a.titular_afiliacion_id IS NULL AND a.tabla_estado_registro_id <> 38
                                       ORDER BY a.f_alta DESC");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $pid);
    mysqli_stmt_execute($stmt);
    $afiliaciones = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return [
        'titular' => $titular,
        'persona' => $persona,
        'familiares' => $familiares,
        'afiliaciones' => $afiliaciones,
        'puede_agregar_familiar' => sindi_usuario_puede($conexion, 1050, 'agregar_familiar') && (int)$titular['tabla_estado_registro_id'] === 1,
        'cuenta' => sindi_afiliado_cuenta($conexion, $empresa_id, $pid),
        'puede_volante' => sindi_usuario_puede($conexion, 1050, 'volante'),
        'puede_carnet' => $puedeCarnet && (int)$titular['tabla_estado_registro_id'] === 1,
    ];
}

/** Ejecuta una operación de escritura en su propia transacción y devuelve la respuesta estándar. */
function sindi_afil_transaccion(mysqli $conexion, callable $operacion, string $mensaje): array
{
    mysqli_begin_transaction($conexion);
    try {
        $data = $operacion();
        mysqli_commit($conexion);
        return ['success' => true, 'message' => $mensaje, 'data' => $data];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("[SINDI_AFILIADOS] " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function sindi_afiliados_alta_titular(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): array
{
    return sindi_afil_transaccion($conexion, function () use ($conexion, $empresa_id, $usuario_id, $d) {
        $persona_id = sindi_afil_persona($conexion, $empresa_id, $usuario_id, $d);
        $empleador_id = !empty($d['empleador_id']) ? (int)$d['empleador_id'] : null;
        if ($empleador_id) {
            AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);
        }
        $id = sindi_afil_alta_titular($conexion, $empresa_id, $usuario_id, $persona_id, (string)($d['tipo_codigo'] ?? ''),
            $empleador_id, (string)($d['f_alta'] ?: date('Y-m-d')), 'SOLICITUD', null);
        return ['afiliacion_id' => $id];
    }, 'Afiliación registrada.');
}

function sindi_afiliados_alta_familiar(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): array
{
    return sindi_afil_transaccion($conexion, function () use ($conexion, $empresa_id, $usuario_id, $d) {
        $persona_id = sindi_afil_persona($conexion, $empresa_id, $usuario_id, $d);
        $doc = !empty($d['documento']) ? ['documento' => (string)$d['documento'], 'f_presentacion' => (string)($d['f_presentacion'] ?: date('Y-m-d'))] : null;
        $id = sindi_afil_alta_familiar($conexion, $empresa_id, $usuario_id, (int)($d['titular_afiliacion_id'] ?? 0), $persona_id,
            (int)($d['parentesco_id'] ?? 0), (string)($d['f_alta'] ?: date('Y-m-d')), 'SOLICITUD', null, $doc);
        return ['afiliacion_id' => $id];
    }, 'Familiar agregado al grupo.');
}

function sindi_afiliados_baja(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): array
{
    return sindi_afil_transaccion($conexion, function () use ($conexion, $empresa_id, $usuario_id, $d) {
        $motivo = !empty($d['baja_motivo_id']) ? (int)$d['baja_motivo_id'] : null;
        sindi_afil_baja($conexion, $empresa_id, $usuario_id, (int)($d['afiliacion_id'] ?? 0), $motivo, (string)($d['f_baja'] ?: date('Y-m-d')));
        return null;
    }, 'Baja registrada (incluye al grupo familiar si era titular).');
}

function sindi_afiliados_documento(mysqli $conexion, int $empresa_id, int $usuario_id, array $d, ?array $archivo): array
{
    return sindi_afil_transaccion($conexion, function () use ($conexion, $empresa_id, $usuario_id, $d, $archivo) {
        $nombre = null;
        $ruta = null;
        if ($archivo && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string)$archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) || (int)$archivo['size'] > 5 * 1024 * 1024) {
                throw new RuntimeException('El documento debe ser PDF, JPG o PNG de hasta 5 MB.');
            }
            $base = dirname(__DIR__, 2) . '/uploads/sindicatos';
            if (!is_dir($base)) mkdir($base, 0755, true);
            if (!file_exists($base . '/.htaccess')) file_put_contents($base . '/.htaccess', "Require all denied\nDeny from all\n");
            $dir = $base . '/' . $empresa_id . '/afiliados';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $guardado = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (file_put_contents($dir . '/' . $guardado, file_get_contents($archivo['tmp_name'])) === false) {
                throw new RuntimeException('No se pudo guardar el documento.');
            }
            $nombre = mb_substr((string)$archivo['name'], 0, 255);
            $ruta = 'uploads/sindicatos/' . $empresa_id . '/afiliados/' . $guardado;
        }
        return sindi_afil_presentar_documento($conexion, $empresa_id, $usuario_id, (int)($d['afiliacion_id'] ?? 0),
            trim((string)($d['documento'] ?? '')) ?: 'Documento de excepción', (string)($d['f_presentacion'] ?: date('Y-m-d')), $nombre, $ruta);
    }, 'Documento registrado y vigencia recalculada.');
}

/** Combos de la página. */
function sindi_afiliados_combos(mysqli $conexion, int $empresa_id, int $usuario_id): array
{
    $q = function (string $sql) use ($conexion, $empresa_id) {
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $r;
    };
    $filtro = AccesosEmpleadoresHelper::filtroSql($conexion, $usuario_id, $empresa_id, 'emp.empleador_id');
    return [
        'tipos' => $q("SELECT codigo, nombre, grupo, requiere_empleador FROM sindi__afiliaciones_tipos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY afiliacion_tipo_id"),
        'parentescos' => $q("SELECT parentesco_id, codigo, nombre FROM sindi__parentescos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY parentesco_id"),
        'bajas_motivos' => $q("SELECT baja_motivo_id, descripcion FROM sindi__bajas_motivos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY descripcion"),
        'empleadores' => $q("SELECT emp.empleador_id, ent.entidad_nombre AS razon_social, ent.cuit FROM sindi__empleadores emp
                             INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
                             WHERE emp.empresa_id = ? AND emp.tabla_estado_registro_id = 1 AND {$filtro} ORDER BY ent.entidad_nombre"),
    ];
}

/**
 * Cuenta corriente del afiliado titular: cuotas directas con saldo (las que no están en un volante vigente) y
 * sus volantes.
 */
function sindi_afiliado_cuenta(mysqli $conexion, int $empresa_id, int $persona_id): array
{
    require_once __DIR__ . '/sindi_volantes_core_model.php';
    $stmt = mysqli_prepare($conexion, "SELECT entidad_id FROM sindi__personas WHERE persona_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $persona_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $entidad_id = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
    mysqli_stmt_close($stmt);

    $deudas = $entidad_id > 0 ? sindi_obtener_deudas_pendientes_entidad($conexion, $empresa_id, $entidad_id) : [];

    $stmt = mysqli_prepare($conexion, "SELECT v.volante_id, v.numero, v.f_emision, v.f_pago_1, v.importe_1, v.f_pago_2, v.importe_2,
                                              v.codigo_barras, v.tabla_estado_registro_id, er.tabla_estado_registro AS estado_nombre
                                       FROM sindi__volantes v
                                       LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 528 AND er.estado_registro_id = v.tabla_estado_registro_id
                                       WHERE v.empresa_id = ? AND v.persona_id = ? AND v.tipo_volante = 'AFILIADO'
                                       ORDER BY v.volante_id DESC LIMIT 20");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $persona_id);
    mysqli_stmt_execute($stmt);
    $volantes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return ['deudas' => $deudas, 'volantes' => $volantes];
}

/** Volante de pago de las cuotas directas seleccionadas del titular (tipo AFILIADO, mismas bocas de pago). */
function sindi_afiliados_emitir_volante(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): array
{
    require_once __DIR__ . '/sindi_volantes_core_model.php';
    $titular = sindi_afil_obtener($conexion, $empresa_id, (int)($d['afiliacion_id'] ?? 0));
    if (!empty($titular['titular_afiliacion_id'])) {
        return ['success' => false, 'message' => 'El volante se emite al titular del grupo familiar.'];
    }
    $ids = array_values(array_filter(array_map('intval', (array)($d['comprobante_ids'] ?? [])), fn($x) => $x > 0));
    return sindi_emitir_volante($conexion, $empresa_id, $usuario_id, [
        'persona_id' => (int)$titular['persona_id'],
        'f_pago_1' => (string)($d['f_pago_1'] ?? date('Y-m-d', strtotime('+10 days'))),
        'f_pago_2' => (string)($d['f_pago_2'] ?? date('Y-m-d', strtotime('+20 days'))),
        'observaciones' => 'Cuotas de afiliado N° ' . $titular['numero_afiliado'],
    ], $ids);
}

// ---------------------------------------------------------------------------------------------
// Carnet (credencial) del afiliado
// ---------------------------------------------------------------------------------------------

/** Datos de una afiliación para su credencial. */
function sindi_carnet_fila(mysqli $conexion, int $empresa_id, int $afiliacion_id): array
{
    $stmt = mysqli_prepare($conexion, "SELECT a.afiliacion_id, a.titular_afiliacion_id, a.numero_afiliado, a.f_alta, a.f_baja, a.f_vigencia,
                                              a.tabla_estado_registro_id, t.nombre AS tipo_nombre, t.grupo,
                                              p.cuil, p.apellido, p.nombre, p.documento_nro, p.f_nacimiento,
                                              par.nombre AS parentesco, ent.entidad_nombre AS empleador_nombre,
                                              tit.numero_afiliado AS titular_numero, CONCAT(pt.apellido, ' ', pt.nombre) AS titular_nombre,
                                              tit.tabla_estado_registro_id AS titular_estado
                                       FROM sindi__afiliaciones a
                                       INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
                                       INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
                                       LEFT JOIN sindi__parentescos par ON par.parentesco_id = a.parentesco_id
                                       LEFT JOIN sindi__empleadores emp ON emp.empleador_id = a.empleador_id AND emp.empresa_id = a.empresa_id
                                       LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
                                       LEFT JOIN sindi__afiliaciones tit ON tit.afiliacion_id = a.titular_afiliacion_id AND tit.empresa_id = a.empresa_id
                                       LEFT JOIN sindi__personas pt ON pt.persona_id = tit.persona_id
                                       WHERE a.afiliacion_id = ? AND a.empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $afiliacion_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        throw new RuntimeException('Afiliación no encontrada en esta empresa.');
    }
    return $fila;
}

/** Firma del código de verificación del carnet (clave del servidor, SINDI_CLAVE_CIFRADO del .env). */
function sindi_carnet_firma(string $datos): string
{
    require_once __DIR__ . '/../../core/secretos.php';
    if (!defined('SINDI_CLAVE_CIFRADO') || SINDI_CLAVE_CIFRADO === '') {
        throw new RuntimeException('Falta SINDI_CLAVE_CIFRADO en el .env: no se pueden firmar las credenciales.');
    }
    return substr(hash_hmac('sha256', 'carnet|' . $datos, (string)SINDI_CLAVE_CIFRADO), 0, 16);
}

/** Código del QR: empresa, afiliación y fecha de emisión, firmados. */
function sindi_carnet_codigo(int $empresa_id, int $afiliacion_id, string $f_emision): string
{
    $datos = $empresa_id . '-' . $afiliacion_id . '-' . str_replace('-', '', $f_emision);
    return $datos . '-' . sindi_carnet_firma($datos);
}

/**
 * Credenciales a imprimir: la afiliación pedida (titular o familiar) y, con $grupo, el titular y sus familiares
 * activos. Solo afiliaciones activas, y las de familiares con el titular activo.
 */
function sindi_afiliados_carnets(mysqli $conexion, int $empresa_id, int $afiliacion_id, bool $grupo): array
{
    $pedida = sindi_carnet_fila($conexion, $empresa_id, $afiliacion_id);
    $ids = [$afiliacion_id];
    if ($grupo) {
        $tid = (int)($pedida['titular_afiliacion_id'] ?: $pedida['afiliacion_id']);
        $ids = [$tid];
        $stmt = mysqli_prepare($conexion, "SELECT afiliacion_id FROM sindi__afiliaciones
                                           WHERE titular_afiliacion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                                           ORDER BY numero_afiliado");
        mysqli_stmt_bind_param($stmt, "ii", $tid, $empresa_id);
        mysqli_stmt_execute($stmt);
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt)) as $r) {
            $ids[] = (int)$r[0];
        }
        mysqli_stmt_close($stmt);
    }
    $hoy = date('Y-m-d');
    $carnets = [];
    foreach ($ids as $id) {
        $c = $id === $afiliacion_id ? $pedida : sindi_carnet_fila($conexion, $empresa_id, $id);
        if ((int)$c['tabla_estado_registro_id'] !== 1) {
            throw new RuntimeException("La afiliación N° {$c['numero_afiliado']} no está activa: no se emite credencial.");
        }
        if (!empty($c['titular_afiliacion_id']) && (int)$c['titular_estado'] !== 1) {
            throw new RuntimeException("El titular de la afiliación N° {$c['numero_afiliado']} no está activo.");
        }
        $c['codigo'] = sindi_carnet_codigo($empresa_id, (int)$c['afiliacion_id'], $hoy);
        $c['f_emision'] = $hoy;
        $carnets[] = $c;
    }
    return $carnets;
}

/** Verificación del código del QR: firma válida y estado actual de la afiliación. */
function sindi_afiliados_verificar_carnet(mysqli $conexion, int $empresa_id, string $codigo): array
{
    if (!preg_match('/^(\d+)-(\d+)-(\d{8})-([0-9a-f]{16})$/', trim($codigo), $m)) {
        return ['valida' => false, 'motivo' => 'El código no tiene el formato de una credencial.'];
    }
    $datos = "{$m[1]}-{$m[2]}-{$m[3]}";
    if (!hash_equals(sindi_carnet_firma($datos), $m[4])) {
        return ['valida' => false, 'motivo' => 'La firma del código no es válida: la credencial no fue emitida por el sistema.'];
    }
    if ((int)$m[1] !== $empresa_id) {
        return ['valida' => false, 'motivo' => 'La credencial es de otra entidad.'];
    }
    $c = sindi_carnet_fila($conexion, $empresa_id, (int)$m[2]);
    $f_emision = substr($m[3], 0, 4) . '-' . substr($m[3], 4, 2) . '-' . substr($m[3], 6, 2);
    $vigente = (int)$c['tabla_estado_registro_id'] === 1
        && (empty($c['titular_afiliacion_id']) || (int)$c['titular_estado'] === 1)
        && (empty($c['f_vigencia']) || $c['f_vigencia'] >= date('Y-m-d'));
    return ['valida' => true, 'vigente' => $vigente, 'f_emision' => $f_emision, 'afiliacion' => $c];
}

// =============================================================================================
// REPORTES DEL PADRÓN Y EXPORTACIÓN A EXCEL
// =============================================================================================

/** Reporte de cumpleañeros en un mes (titulares y familiares con fecha de nacimiento). */
function sindi_reporte_cumpleanios(mysqli $conexion, int $empresa_id, int $mes, ?int $estado = null): array
{
    $where = "a.empresa_id = ? AND a.tabla_estado_registro_id <> 38 AND p.f_nacimiento IS NOT NULL AND p.f_nacimiento <> '0000-00-00' AND MONTH(p.f_nacimiento) = ?";
    $tipos = "ii";
    $params = [$empresa_id, $mes];
    if ($estado) {
        $where .= " AND a.tabla_estado_registro_id = ?";
        $tipos .= "i";
        $params[] = $estado;
    }

    $sql = "SELECT a.afiliacion_id, a.numero_afiliado, a.titular_afiliacion_id, a.tabla_estado_registro_id,
                   p.persona_id, p.cuil, p.apellido, p.nombre, p.sexo, p.f_nacimiento, p.email, p.telefono,
                   DAY(p.f_nacimiento) AS dia_cumple,
                   MONTH(p.f_nacimiento) AS mes_cumple,
                   (YEAR(CURDATE()) - YEAR(p.f_nacimiento) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(p.f_nacimiento, '%m%d'))) AS edad_actual,
                   (YEAR(CURDATE()) - YEAR(p.f_nacimiento)) AS edad_a_cumplir,
                   (YEAR(CURDATE()) - YEAR(p.f_nacimiento)) AS edad_cumplir,
                   t.codigo AS tipo_codigo, t.nombre AS tipo_nombre, t.grupo,
                   COALESCE(par.nombre, 'TITULAR') AS parentesco,
                   tit.numero_afiliado AS titular_numero,
                   CONCAT(pt.apellido, ' ', pt.nombre) AS titular_nombre,
                   pt.cuil AS titular_cuil,
                   CASE WHEN a.titular_afiliacion_id IS NOT NULL THEN CONCAT(pt.apellido, ' ', pt.nombre) ELSE ent.entidad_nombre END AS referencia,
                   ent.entidad_nombre AS empleador_nombre,
                   COALESCE(ter.tabla_estado_registro, er.estado_registro) AS estado_nombre
            FROM sindi__afiliaciones a
            INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
            INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
            LEFT JOIN sindi__parentescos par ON par.parentesco_id = a.parentesco_id
            LEFT JOIN sindi__empleadores emp ON emp.empleador_id = a.empleador_id
            LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN sindi__afiliaciones tit ON tit.afiliacion_id = a.titular_afiliacion_id
            LEFT JOIN sindi__personas pt ON pt.persona_id = tit.persona_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 527 AND ter.estado_registro_id = a.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = a.tabla_estado_registro_id
            WHERE {$where}
            ORDER BY DAY(p.f_nacimiento) ASC, p.apellido ASC, p.nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $res;
}

/** Reporte de Activos / Inactivos (resumen estadístico y desglose de bajas). */
function sindi_reporte_activos_inactivos(mysqli $conexion, int $empresa_id): array
{
    // 1. Resumen por estado y tipo de afiliación
    $sqlResumen = "SELECT t.codigo AS tipo_codigo, t.nombre AS tipo_nombre, t.grupo,
                          SUM(CASE WHEN a.tabla_estado_registro_id = 1 THEN 1 ELSE 0 END) AS activos,
                          SUM(CASE WHEN a.tabla_estado_registro_id = 20 THEN 1 ELSE 0 END) AS vencidos,
                          SUM(CASE WHEN a.tabla_estado_registro_id = 2 THEN 1 ELSE 0 END) AS bajas,
                          COUNT(*) AS total
                   FROM sindi__afiliaciones a
                   INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
                   WHERE a.empresa_id = ? AND a.tabla_estado_registro_id <> 38
                   GROUP BY t.afiliacion_tipo_id
                   ORDER BY t.grupo DESC, t.afiliacion_tipo_id ASC";
    $stmt = mysqli_prepare($conexion, $sqlResumen);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $desglose = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // 2. Totales generales
    $totActivos = 0;
    $totVencidos = 0;
    $totBajas = 0;
    $totGeneral = 0;
    foreach ($desglose as $d) {
        $totActivos += (int)$d['activos'];
        $totVencidos += (int)$d['vencidos'];
        $totBajas += (int)$d['bajas'];
        $totGeneral += (int)$d['total'];
    }

    // 3. Detalle de bajas registradas
    $sqlBajas = "SELECT a.afiliacion_id, a.numero_afiliado, a.f_alta, a.f_baja,
                        p.cuil, p.apellido, p.nombre, p.email, p.telefono,
                        t.nombre AS tipo_nombre,
                        ent.entidad_nombre AS empleador_nombre,
                        COALESCE(bm.descripcion, 'Baja registrada') AS motivo_baja
                 FROM sindi__afiliaciones a
                 INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
                 INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
                 LEFT JOIN sindi__bajas_motivos bm ON bm.baja_motivo_id = a.baja_motivo_id
                 LEFT JOIN sindi__empleadores emp ON emp.empleador_id = a.empleador_id
                 LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
                 WHERE a.empresa_id = ? AND a.tabla_estado_registro_id = 2
                 ORDER BY a.f_baja DESC, p.apellido ASC LIMIT 1000";
    $stmt = mysqli_prepare($conexion, $sqlBajas);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $bajas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return [
        'totales' => [
            'total' => $totGeneral,
            'activos' => $totActivos,
            'vencidos' => $totVencidos,
            'bajas' => $totBajas,
        ],
        'desglose' => $desglose,
        'bajas' => $bajas,
    ];
}

/** Reporte de grupos familiares: titulares y sus familiares a cargo. */
function sindi_reporte_grupos_familiares(mysqli $conexion, int $empresa_id, string $texto = ''): array
{
    $where = "a.empresa_id = ? AND a.tabla_estado_registro_id <> 38";
    $tipos = "i";
    $params = [$empresa_id];

    if ($texto !== '') {
        $like = '%' . trim($texto) . '%';
        $where .= " AND (p.cuil LIKE ? OR p.apellido LIKE ? OR p.nombre LIKE ? OR a.numero_afiliado LIKE ? OR pt.apellido LIKE ? OR pt.cuil LIKE ?)";
        $tipos .= "ssssss";
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    $sql = "SELECT a.afiliacion_id, a.numero_afiliado, a.titular_afiliacion_id, a.f_alta, a.f_baja, a.f_vigencia, a.tabla_estado_registro_id,
                   p.cuil, p.apellido, p.nombre, p.sexo, p.f_nacimiento, p.telefono, p.email,
                   CASE WHEN p.f_nacimiento IS NOT NULL AND p.f_nacimiento <> '0000-00-00'
                        THEN (YEAR(CURDATE()) - YEAR(p.f_nacimiento) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(p.f_nacimiento, '%m%d')))
                        ELSE NULL END AS edad,
                   t.codigo AS tipo_codigo, t.nombre AS tipo_nombre, t.grupo,
                   COALESCE(par.nombre, 'TITULAR') AS parentesco,
                   ent.entidad_nombre AS empleador_nombre,
                   tit.numero_afiliado AS titular_numero,
                   CONCAT(pt.apellido, ' ', pt.nombre) AS titular_nombre,
                   pt.cuil AS titular_cuil,
                   COALESCE(ter.tabla_estado_registro, er.estado_registro) AS estado_nombre,
                   (SELECT MAX(d.f_vencimiento) FROM sindi__afiliaciones_documentos d WHERE d.afiliacion_id = a.afiliacion_id AND d.tabla_estado_registro_id = 1) AS doc_vencimiento
            FROM sindi__afiliaciones a
            INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
            INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
            LEFT JOIN sindi__parentescos par ON par.parentesco_id = a.parentesco_id
            LEFT JOIN sindi__empleadores emp ON emp.empleador_id = a.empleador_id
            LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN sindi__afiliaciones tit ON tit.afiliacion_id = a.titular_afiliacion_id
            LEFT JOIN sindi__personas pt ON pt.persona_id = tit.persona_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 527 AND ter.estado_registro_id = a.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = a.tabla_estado_registro_id
            WHERE {$where}
            ORDER BY COALESCE(tit.numero_afiliado, a.numero_afiliado) ASC, a.titular_afiliacion_id IS NOT NULL, a.numero_afiliado ASC
            LIMIT 3000";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $res;
}

/** Genera y descarga un archivo Excel (.xls con formato HTML y estilos nativos). */
function sindi_generar_excel_tabla(string $nombreArchivo, string $titulo, array $columnas, array $filas): void
{
    // Limpiar buffers previos para evitar corrupción
    while (ob_get_level()) {
        ob_end_clean();
    }

    $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nombreArchivo);
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $sanitized . '_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
    header('Pragma: public');

    // UTF-8 BOM
    echo "\xEF\xBB\xBF";
    echo <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
  body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
  .titulo { font-size: 15pt; font-weight: bold; color: #1a4d2e; padding: 10px 0; }
  .fecha-gen { font-size: 9pt; color: #666666; font-style: italic; margin-bottom: 12px; }
  table { border-collapse: collapse; width: 100%; }
  th { background-color: #0d6efd; color: #ffffff; font-weight: bold; text-align: center; border: 0.5pt solid #cccccc; padding: 6px; }
  td { border: 0.5pt solid #e0e0e0; padding: 5px; vertical-align: middle; }
  tr:nth-child(even) { background-color: #f8f9fa; }
  .texto { mso-number-format: "\@"; text-align: left; }
  .codigo { mso-number-format: "\@"; font-family: Consolas, monospace; text-align: center; font-weight: bold; }
  .fecha { mso-number-format: "dd\/mm\/yyyy"; text-align: center; }
  .numero { mso-number-format: "\#\,\#\#0"; text-align: right; }
  .estado-activo { background-color: #d1e7dd; color: #0f5132; text-align: center; font-weight: bold; }
  .estado-baja { background-color: #f8d7da; color: #842029; text-align: center; font-weight: bold; }
  .estado-vencido { background-color: #fff3cd; color: #664d03; text-align: center; font-weight: bold; }
</style>
</head>
<body>
  <div class="titulo">{$titulo}</div>
  <div class="fecha-gen">Generado el: {$nombreArchivo} - Fecha: 

HTML;
    echo date('d/m/Y H:i') . "</div>\n";
    echo "<table>\n  <thead>\n    <tr>\n";
    foreach ($columnas as $col) {
        echo "      <th>" . htmlspecialchars((string)$col, ENT_QUOTES, 'UTF-8') . "</th>\n";
    }
    echo "    </tr>\n  </thead>\n  <tbody>\n";

    foreach ($filas as $fila) {
        echo "    <tr>\n";
        foreach ($fila as $celda) {
            $val = $celda['valor'] ?? '';
            $tipo = $celda['tipo'] ?? 'texto';
            $cls = 'texto';
            if ($tipo === 'codigo') $cls = 'codigo';
            elseif ($tipo === 'fecha') $cls = 'fecha';
            elseif ($tipo === 'numero') $cls = 'numero';
            elseif ($tipo === 'estado') {
                $e = (int)($celda['estado_id'] ?? 1);
                $cls = ($e === 1) ? 'estado-activo' : (($e === 2) ? 'estado-baja' : 'estado-vencido');
            }
            echo "      <td class=\"{$cls}\">" . htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') . "</td>\n";
        }
        echo "    </tr>\n";
    }

    echo "  </tbody>\n</table>\n</body>\n</html>";
    exit(0);
}

/** Exporta el padrón filtrado a Excel. */
function sindi_exportar_padron_excel(mysqli $conexion, int $empresa_id, array $filtros): void
{
    $afiliados = sindi_listar_afiliados($conexion, $empresa_id, 1050, $filtros);

    $columnas = [
        'N° Afiliado', 'Apellido', 'Nombre', 'CUIL', 'Tipo Afiliación', 'Empleador',
        'Fecha Alta', 'Familiares a Cargo', 'Estado'
    ];

    $filas = [];
    foreach ($afiliados as $a) {
        $filas[] = [
            ['valor' => $a['numero_afiliado'] ?: '-', 'tipo' => 'codigo'],
            ['valor' => $a['apellido'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $a['nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $a['cuil'] ?: '-', 'tipo' => 'codigo'],
            ['valor' => $a['tipo_nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $a['empleador_nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => !empty($a['f_alta']) ? date('d/m/Y', strtotime($a['f_alta'])) : '-', 'tipo' => 'fecha'],
            ['valor' => $a['familiares'] ?? 0, 'tipo' => 'numero'],
            ['valor' => $a['estado_nombre'] ?: '-', 'tipo' => 'estado', 'estado_id' => $a['tabla_estado_registro_id']],
        ];
    }

    sindi_generar_excel_tabla('Padron_Afiliados', 'Padrón de Afiliados Titulares', $columnas, $filas);
}

/** Exporta el reporte de cumpleaños a Excel. */
function sindi_exportar_cumpleanios_excel(mysqli $conexion, int $empresa_id, int $mes, ?int $estado = null): void
{
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    $nombreMes = $meses[$mes] ?? (string)$mes;

    $cumples = sindi_reporte_cumpleanios($conexion, $empresa_id, $mes, $estado);

    $columnas = [
        'Día', 'N° Afiliado', 'Apellido', 'Nombre', 'CUIL', 'Fecha Nacimiento', 'Edad a Cumplir',
        'Parentesco / Tipo', 'Titular / Empleador', 'Teléfono', 'Correo Electrónico', 'Estado'
    ];

    $filas = [];
    foreach ($cumples as $c) {
        $filas[] = [
            ['valor' => $c['dia_cumple'], 'tipo' => 'numero'],
            ['valor' => $c['numero_afiliado'] ?: '-', 'tipo' => 'codigo'],
            ['valor' => $c['apellido'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $c['nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $c['cuil'] ?: '-', 'tipo' => 'codigo'],
            ['valor' => !empty($c['f_nacimiento']) ? date('d/m/Y', strtotime($c['f_nacimiento'])) : '-', 'tipo' => 'fecha'],
            ['valor' => $c['edad_cumplir'], 'tipo' => 'numero'],
            ['valor' => $c['parentesco'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $c['referencia'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $c['telefono'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $c['email'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $c['estado_nombre'] ?: '-', 'tipo' => 'estado', 'estado_id' => $c['tabla_estado_registro_id']],
        ];
    }

    sindi_generar_excel_tabla('Cumpleanios_' . $nombreMes, "Cumpleañeros del Mes de {$nombreMes}", $columnas, $filas);
}

/** Exporta el padrón con grupo familiar a Excel. */
function sindi_exportar_grupos_excel(mysqli $conexion, int $empresa_id): void
{
    $grupos = sindi_reporte_grupos_familiares($conexion, $empresa_id);

    $columnas = [
        'N° Afiliado', 'Parentesco / Rol', 'Apellido', 'Nombre', 'CUIL', 'Edad', 'Fecha Nacimiento',
        'Fecha Alta', 'Vigencia Cobertura', 'Titular a Cargo', 'Empleador', 'Estado', 'Vence Documento'
    ];

    $filas = [];
    foreach ($grupos as $g) {
        $esTitular = empty($g['titular_afiliacion_id']);
        $filas[] = [
            ['valor' => $g['numero_afiliado'] ?: '-', 'tipo' => 'codigo'],
            ['valor' => $esTitular ? 'TITULAR' : ($g['parentesco'] ?: 'FAMILIAR'), 'tipo' => 'texto'],
            ['valor' => $g['apellido'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $g['nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $g['cuil'] ?: '-', 'tipo' => 'codigo'],
            ['valor' => $g['edad'] ?? '-', 'tipo' => 'numero'],
            ['valor' => !empty($g['f_nacimiento']) ? date('d/m/Y', strtotime($g['f_nacimiento'])) : '-', 'tipo' => 'fecha'],
            ['valor' => !empty($g['f_alta']) ? date('d/m/Y', strtotime($g['f_alta'])) : '-', 'tipo' => 'fecha'],
            ['valor' => !empty($g['f_vigencia']) ? date('d/m/Y', strtotime($g['f_vigencia'])) : 'Sin límite', 'tipo' => 'fecha'],
            ['valor' => !$esTitular ? ($g['titular_nombre'] . ' (N° ' . $g['titular_numero'] . ')') : '-', 'tipo' => 'texto'],
            ['valor' => $g['empleador_nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $g['estado_nombre'] ?: '-', 'tipo' => 'estado', 'estado_id' => $g['tabla_estado_registro_id']],
            ['valor' => !empty($g['doc_vencimiento']) ? date('d/m/Y', strtotime($g['doc_vencimiento'])) : '-', 'tipo' => 'fecha'],
        ];
    }

    sindi_generar_excel_tabla('Padron_Grupo_Familiar', 'Padrón de Afiliados y Grupo Familiar', $columnas, $filas);
}

/** Exporta las bajas registradas a Excel. */
function sindi_exportar_bajas_excel(mysqli $conexion, int $empresa_id): void
{
    $reporte = sindi_reporte_activos_inactivos($conexion, $empresa_id);
    $bajas = $reporte['bajas'];

    $columnas = [
        'N° Afiliado', 'Apellido', 'Nombre', 'CUIL', 'Tipo Afiliación', 'Empleador',
        'Fecha Alta', 'Fecha Baja', 'Motivo de Baja'
    ];

    $filas = [];
    foreach ($bajas as $b) {
        $filas[] = [
            ['valor' => $b['numero_afiliado'] ?: '-', 'tipo' => 'codigo'],
            ['valor' => $b['apellido'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $b['nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $b['cuil'] ?: '-', 'tipo' => 'codigo'],
            ['valor' => $b['tipo_nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => $b['empleador_nombre'] ?: '-', 'tipo' => 'texto'],
            ['valor' => !empty($b['f_alta']) ? date('d/m/Y', strtotime($b['f_alta'])) : '-', 'tipo' => 'fecha'],
            ['valor' => !empty($b['f_baja']) ? date('d/m/Y', strtotime($b['f_baja'])) : '-', 'tipo' => 'fecha'],
            ['valor' => $b['motivo_baja'] ?: '-', 'tipo' => 'texto'],
        ];
    }

    sindi_generar_excel_tabla('Afiliados_Bajas', 'Listado de Bajas de Afiliados', $columnas, $filas);
}
