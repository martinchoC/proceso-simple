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
    foreach ($familiares as &$f) {
        $f['puede_documento'] = sindi_usuario_puede($conexion, 1050, 'documento') && !empty($f['documento_excepcion'])
            && in_array((int)$f['tabla_estado_registro_id'], [1, 20], true);
        $f['puede_baja'] = sindi_usuario_puede($conexion, 1050, 'baja') && (int)$f['tabla_estado_registro_id'] === 1;
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
