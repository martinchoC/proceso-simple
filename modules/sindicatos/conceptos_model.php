<?php
declare(strict_types=1);

/**
 * modules/sindicatos/conceptos_model.php
 * Modelo para conceptos de aporte sindical (Página 1003).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_conceptos(mysqli $conexion, int $empresa_id, int $pagina_id): array
{
    $sql = "SELECT c.*, conv.codigo AS convenio_codigo, conv.nombre AS convenio_nombre, er.estado_registro
            FROM sindi__conceptos c
            LEFT JOIN sindi__convenios conv ON conv.convenio_id = c.convenio_id
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
        $id = (int)$row['concepto_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_concepto(mysqli $conexion, int $empresa_id, int $concepto_id): ?array
{
    $stmt = mysqli_prepare($conexion, "SELECT * FROM sindi__conceptos WHERE concepto_id = ? AND empresa_id = ? LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $concepto_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function sindi_guardar_concepto(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $id = isset($data['concepto_id']) ? (int)$data['concepto_id'] : 0;
    $convenio_id = intval($data['convenio_id'] ?? 0);
    $codigo = strtoupper(trim($data['codigo'] ?? ''));
    $descripcion = trim($data['descripcion'] ?? '');
    $base_calculo = in_array($data['base_calculo'] ?? '', ['REM_TOTAL','REMUNERATIVO','FIJO']) ? $data['base_calculo'] : 'REM_TOTAL';
    $porcentaje = floatval($data['porcentaje'] ?? 0);
    $valor_fijo = floatval($data['valor_fijo'] ?? 0);
    $solo_afiliados = !empty($data['solo_afiliados']) ? 1 : 0;
    $aplica_piso_escala = !empty($data['aplica_piso_escala']) ? 1 : 0;
    $incluye_sac = !empty($data['incluye_sac']) ? 1 : 0;
    $cuenta_concepto_codigo = trim($data['cuenta_concepto_codigo'] ?? 'SINDI_DEUDORES_APORTES');
    $f_desde = !empty($data['f_desde']) ? $data['f_desde'] : date('Y-m-d');
    $f_hasta = !empty($data['f_hasta']) ? $data['f_hasta'] : null;

    if ($convenio_id <= 0 || $codigo === '' || $descripcion === '') {
        return ['success' => false, 'message' => 'Convenio, código y descripción son obligatorios.'];
    }

    if ($id > 0) {
        $sql = "UPDATE sindi__conceptos SET
                convenio_id = ?, codigo = ?, descripcion = ?, base_calculo = ?, porcentaje = ?,
                valor_fijo = ?, solo_afiliados = ?, aplica_piso_escala = ?, incluye_sac = ?,
                cuenta_concepto_codigo = ?, f_desde = ?, f_hasta = ?,
                usuario_actualizacion_id = ?, fecha_actualizacion = NOW()
                WHERE concepto_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];

        mysqli_stmt_bind_param(
            $stmt,
            "isssddiiisssiii",
            $convenio_id, $codigo, $descripcion, $base_calculo, $porcentaje,
            $valor_fijo, $solo_afiliados, $aplica_piso_escala, $incluye_sac,
            $cuenta_concepto_codigo, $f_desde, $f_hasta,
            $usuario_id, $id, $empresa_id
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Concepto de aporte actualizado.' : 'Error al actualizar concepto.'];
    } else {
        $sql = "INSERT INTO sindi__conceptos (
                empresa_id, convenio_id, codigo, descripcion, base_calculo, porcentaje,
                valor_fijo, solo_afiliados, aplica_piso_escala, incluye_sac,
                cuenta_concepto_codigo, f_desde, f_hasta,
                tabla_estado_registro_id, usuario_creacion_id, fecha_creacion
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW()
            )";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];

        mysqli_stmt_bind_param(
            $stmt,
            "iisssddiiisssi",
            $empresa_id, $convenio_id, $codigo, $descripcion, $base_calculo, $porcentaje,
            $valor_fijo, $solo_afiliados, $aplica_piso_escala, $incluye_sac,
            $cuenta_concepto_codigo, $f_desde, $f_hasta, $usuario_id
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Concepto de aporte registrado.' : 'Error al registrar concepto.'];
    }
}
