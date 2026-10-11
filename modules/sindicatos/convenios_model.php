<?php
declare(strict_types=1);

/**
 * modules/sindicatos/convenios_model.php
 * Modelo para convenios colectivos, categorías y escalas salariales (Página 1002).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_convenios(mysqli $conexion, int $empresa_id, int $pagina_id): array
{
    $sql = "SELECT c.*, er.estado_registro,
                   (SELECT COUNT(*) FROM sindi__categorias cat WHERE cat.convenio_id = c.convenio_id AND cat.empresa_id = c.empresa_id) AS total_categorias
            FROM sindi__convenios c
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            WHERE c.empresa_id = ?
            ORDER BY c.codigo ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $convenioId = (int)$row['convenio_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $convenioId);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_convenio(mysqli $conexion, int $empresa_id, int $convenio_id): ?array
{
    $stmt = mysqli_prepare($conexion, "SELECT * FROM sindi__convenios WHERE convenio_id = ? AND empresa_id = ? LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $convenio_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function sindi_guardar_convenio(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $id = isset($data['convenio_id']) ? (int)$data['convenio_id'] : 0;
    $codigo = strtoupper(trim($data['codigo'] ?? ''));
    $nombre = trim($data['nombre'] ?? '');
    $descripcion = trim($data['descripcion'] ?? '');

    if ($codigo === '' || $nombre === '') {
        return ['success' => false, 'message' => 'Código y nombre son obligatorios.'];
    }

    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__convenios SET codigo = ?, nombre = ?, descripcion = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW() WHERE convenio_id = ? AND empresa_id = ?");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "sssiii", $codigo, $nombre, $descripcion, $usuario_id, $id, $empresa_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Convenio actualizado.' : 'Error al actualizar convenio.'];
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__convenios (empresa_id, codigo, nombre, descripcion, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion) VALUES (?, ?, ?, ?, 1, ?, NOW())");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "isssi", $empresa_id, $codigo, $nombre, $descripcion, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Convenio creado con éxito.' : 'Error al registrar convenio.'];
    }
}

// Sub-entidad: Categorías
function sindi_listar_categorias(mysqli $conexion, int $empresa_id, int $convenio_id): array
{
    $sql = "SELECT cat.*,
                   (SELECT COUNT(*) FROM sindi__categorias_escalas esc WHERE esc.categoria_id = cat.categoria_id AND esc.empresa_id = cat.empresa_id) AS total_escalas
            FROM sindi__categorias cat
            WHERE cat.empresa_id = ? AND cat.convenio_id = ?
            ORDER BY cat.codigo ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $convenio_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

function sindi_guardar_categoria(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $categoria_id = isset($data['categoria_id']) ? (int)$data['categoria_id'] : 0;
    $convenio_id = intval($data['convenio_id'] ?? 0);
    $codigo = strtoupper(trim($data['codigo'] ?? ''));
    $nombre = trim($data['nombre'] ?? '');
    $descripcion = trim($data['descripcion'] ?? '');

    if ($convenio_id <= 0 || $codigo === '' || $nombre === '') {
        return ['success' => false, 'message' => 'Convenio, código y nombre de categoría son obligatorios.'];
    }

    if ($categoria_id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__categorias SET codigo = ?, nombre = ?, descripcion = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW() WHERE categoria_id = ? AND empresa_id = ?");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "sssiii", $codigo, $nombre, $descripcion, $usuario_id, $categoria_id, $empresa_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Categoría actualizada.' : 'Error al actualizar categoría.'];
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__categorias (empresa_id, convenio_id, codigo, nombre, descripcion, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "iisssi", $empresa_id, $convenio_id, $codigo, $nombre, $descripcion, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Categoría registrada.' : 'Error al crear categoría.'];
    }
}

// Sub-entidad: Escalas salariales
function sindi_listar_escalas(mysqli $conexion, int $empresa_id, int $categoria_id): array
{
    $sql = "SELECT * FROM sindi__categorias_escalas WHERE empresa_id = ? AND categoria_id = ? ORDER BY periodo DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $categoria_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

function sindi_guardar_escala(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $escala_id = isset($data['escala_id']) ? (int)$data['escala_id'] : 0;
    $categoria_id = intval($data['categoria_id'] ?? 0);
    $periodo = intval($data['periodo'] ?? 0);
    $basico = floatval($data['basico'] ?? 0);
    $rem_1 = floatval($data['rem_1'] ?? 0);
    $rem_2 = floatval($data['rem_2'] ?? 0);

    if ($categoria_id <= 0 || $periodo < 200001 || $periodo > 209912) {
        return ['success' => false, 'message' => 'Período inválido (formato AAAAMM).'];
    }

    if ($escala_id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__categorias_escalas SET periodo = ?, basico = ?, rem_1 = ?, rem_2 = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW() WHERE escala_id = ? AND empresa_id = ?");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "idddiii", $periodo, $basico, $rem_1, $rem_2, $usuario_id, $escala_id, $empresa_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Escala salarial actualizada.' : 'Error al actualizar escala.'];
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__categorias_escalas (empresa_id, categoria_id, periodo, basico, rem_1, rem_2, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "iiidddi", $empresa_id, $categoria_id, $periodo, $basico, $rem_1, $rem_2, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Escala salarial registrada.' : 'Error al registrar escala.'];
    }
}
