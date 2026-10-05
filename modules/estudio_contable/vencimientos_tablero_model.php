<?php
/**
 * Modelo de Datos: Tablero Operativo de Vencimientos
 * Módulo 4: Estudio Contable (vencimientos_tablero_model.php)
 * Tabla Principal: estudio__vencimientos_generados (tabla_id = 414, Proceso Largo Tipo 3)
 */

declare(strict_types=1);

/**
 * Combos para filtros del tablero
 */
function vencimientos_combos(mysqli $conexion, int $empresa_id): array
{
    $combos = [
        'obligaciones' => [],
        'jurisdicciones' => [],
        'clientes' => [],
        'estados' => []
    ];

    // Obligaciones
    $res_o = mysqli_query($conexion, "SELECT impuesto_obligacion_id, organismo, codigo_oficial, nombre FROM estudio__impuestos_obligaciones WHERE activo = 1 ORDER BY organismo, nombre");
    while ($r = mysqli_fetch_assoc($res_o)) {
        $combos['obligaciones'][] = $r;
    }

    // Jurisdicciones
    $res_j = mysqli_query($conexion, "SELECT jurisdiccion_id, jurisdiccion_codigo, jurisdiccion_nombre FROM gestion__jurisdicciones WHERE tabla_estado_registro_id = 1 ORDER BY orden");
    while ($r = mysqli_fetch_assoc($res_j)) {
        $combos['jurisdicciones'][] = $r;
    }

    // Clientes del estudio
    $sql_c = "SELECT cliente_id, razon_social, cuit FROM estudio__clientes WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY razon_social";
    $stmt_c = mysqli_prepare($conexion, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "i", $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    while ($r = mysqli_fetch_assoc($res_c)) {
        $combos['clientes'][] = $r;
    }
    mysqli_stmt_close($stmt_c);

    // Estados de la tabla 414
    $res_e = mysqli_query($conexion, "SELECT estado_registro_id, tabla_estado_registro, color_id FROM conf__tablas_estados_registros WHERE tabla_id = 414 ORDER BY orden");
    while ($r = mysqli_fetch_assoc($res_e)) {
        $combos['estados'][] = $r;
    }

    return $combos;
}

/**
 * Obtener métricas / KPIs del tablero para un periodo o rango
 */
function vencimientos_tablero_kpis(mysqli $conexion, int $empresa_id, int $periodo_fiscal = 0): array
{
    $sql = "SELECT 
                COUNT(*) AS total_vencimientos,
                SUM(CASE WHEN tabla_estado_registro_id = 3 THEN 1 ELSE 0 END) AS sin_iniciar,
                SUM(CASE WHEN tabla_estado_registro_id = 4 THEN 1 ELSE 0 END) AS en_liquidacion,
                SUM(CASE WHEN tabla_estado_registro_id = 5 THEN 1 ELSE 0 END) AS listos_presentar,
                SUM(CASE WHEN tabla_estado_registro_id = 6 THEN 1 ELSE 0 END) AS presentados,
                SUM(CASE WHEN tabla_estado_registro_id = 7 THEN 1 ELSE 0 END) AS pagados,
                SUM(CASE WHEN tabla_estado_registro_id NOT IN (7, 8) AND f_limite < CURDATE() THEN 1 ELSE 0 END) AS vencidos_urgentes,
                COALESCE(SUM(importe_a_pagar), 0) AS total_determinado
            FROM estudio__vencimientos_generados
            WHERE empresa_id = ?";

    $params = [$empresa_id];
    $types = "i";

    if ($periodo_fiscal > 0) {
        $sql .= " AND periodo_fiscal = ?";
        $params[] = $periodo_fiscal;
        $types .= "i";
    }

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $kpi = mysqli_fetch_assoc($res) ?: [];
    mysqli_stmt_close($stmt);

    return $kpi;
}

/**
 * Listado del tablero operativo con cálculo dinámico de semáforo de urgencia
 */
function vencimientos_tablero_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
{
    $sql = "SELECT v.vencimiento_id, v.empresa_id, v.sucursal_id, v.cliente_id,
                   v.impuesto_obligacion_id, v.jurisdiccion_id, v.periodo_fiscal,
                   v.f_limite, v.importe_determinado, v.importe_saldo_favor, v.importe_a_pagar,
                   v.vep_nro, v.f_presentacion, v.f_pago, v.comprobante_pago_adjunto,
                   v.tabla_estado_registro_id, v.created_at,
                   c.razon_social AS cliente_nombre, c.cuit AS cliente_cuit, c.terminacion_cuit,
                   o.organismo, o.codigo_oficial AS obligacion_codigo, o.nombre AS obligacion_nombre,
                   j.jurisdiccion_codigo, j.jurisdiccion_nombre,
                   ter.tabla_estado_registro,
                   DATEDIFF(v.f_limite, CURDATE()) AS dias_restantes,
                   CASE 
                       WHEN v.tabla_estado_registro_id IN (7, 8) THEN 'FINALIZADO'
                       WHEN DATEDIFF(v.f_limite, CURDATE()) < 0 THEN 'VENCIDO'
                       WHEN DATEDIFF(v.f_limite, CURDATE()) BETWEEN 0 AND 3 THEN 'URGENTE'
                       WHEN DATEDIFF(v.f_limite, CURDATE()) BETWEEN 4 AND 7 THEN 'PROXIMO'
                       ELSE 'EN_PLAZO'
                   END AS semaforo_urgencia
            FROM estudio__vencimientos_generados v
            INNER JOIN estudio__clientes c ON c.cliente_id = v.cliente_id
            INNER JOIN estudio__impuestos_obligaciones o ON o.impuesto_obligacion_id = v.impuesto_obligacion_id
            LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = v.jurisdiccion_id
            LEFT JOIN conf__tablas_estados_registros ter 
                ON ter.tabla_id = 414 AND ter.estado_registro_id = v.tabla_estado_registro_id
            WHERE v.empresa_id = ?";

    $params = [$empresa_id];
    $types = "i";

    if (!empty($filtros['periodo_fiscal'])) {
        $sql .= " AND v.periodo_fiscal = ?";
        $params[] = intval($filtros['periodo_fiscal']);
        $types .= "i";
    }

    if (!empty($filtros['cliente_id'])) {
        $sql .= " AND v.cliente_id = ?";
        $params[] = intval($filtros['cliente_id']);
        $types .= "i";
    }

    if (!empty($filtros['impuesto_obligacion_id'])) {
        $sql .= " AND v.impuesto_obligacion_id = ?";
        $params[] = intval($filtros['impuesto_obligacion_id']);
        $types .= "i";
    }

    if (!empty($filtros['tabla_estado_registro_id'])) {
        $sql .= " AND v.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['tabla_estado_registro_id']);
        $types .= "i";
    }

    if (!empty($filtros['urgencia'])) {
        if ($filtros['urgencia'] === 'VENCIDO') {
            $sql .= " AND v.tabla_estado_registro_id NOT IN (7, 8) AND DATEDIFF(v.f_limite, CURDATE()) < 0";
        } elseif ($filtros['urgencia'] === 'URGENTE') {
            $sql .= " AND v.tabla_estado_registro_id NOT IN (7, 8) AND DATEDIFF(v.f_limite, CURDATE()) BETWEEN 0 AND 3";
        }
    }

    $sql .= " ORDER BY v.f_limite ASC, c.razon_social ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Obtener detalle de un vencimiento
 */
function vencimiento_obtener(mysqli $conexion, int $empresa_id, int $vencimiento_id): ?array
{
    $sql = "SELECT v.*, c.razon_social AS cliente_nombre, c.cuit AS cliente_cuit,
                   o.codigo_oficial, o.nombre AS obligacion_nombre, o.organismo,
                   j.jurisdiccion_nombre
            FROM estudio__vencimientos_generados v
            INNER JOIN estudio__clientes c ON c.cliente_id = v.cliente_id
            INNER JOIN estudio__impuestos_obligaciones o ON o.impuesto_obligacion_id = v.impuesto_obligacion_id
            LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = v.jurisdiccion_id
            WHERE v.empresa_id = ? AND v.vencimiento_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $vencimiento_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Transición de estados de un vencimiento generado con datos de liquidación y pago
 */
function vencimiento_cambiar_estado(mysqli $conexion, int $empresa_id, int $vencimiento_id, int $nuevo_estado_id, array $datos = [], int $usuario_id = 0): array
{
    $importe_determinado = floatval($datos['importe_determinado'] ?? 0.0);
    $importe_saldo_favor = floatval($datos['importe_saldo_favor'] ?? 0.0);
    $importe_a_pagar = floatval($datos['importe_a_pagar'] ?? max(0.0, $importe_determinado - $importe_saldo_favor));
    $vep_nro = trim($datos['vep_nro'] ?? '');

    mysqli_begin_transaction($conexion);

    try {
        $sql = "UPDATE estudio__vencimientos_generados SET
                    tabla_estado_registro_id = ?,
                    importe_determinado = CASE WHEN ? > 0 THEN ? ELSE importe_determinado END,
                    importe_saldo_favor = CASE WHEN ? > 0 THEN ? ELSE importe_saldo_favor END,
                    importe_a_pagar = CASE WHEN ? > 0 THEN ? ELSE importe_a_pagar END,
                    vep_nro = CASE WHEN ? != '' THEN ? ELSE vep_nro END,
                    f_presentacion = CASE WHEN ? = 6 AND f_presentacion IS NULL THEN NOW() ELSE f_presentacion END,
                    f_pago = CASE WHEN ? = 7 AND f_pago IS NULL THEN NOW() ELSE f_pago END
                WHERE empresa_id = ? AND vencimiento_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "idddddssiiii",
            $nuevo_estado_id,
            $importe_determinado,
            $importe_determinado,
            $importe_saldo_favor,
            $importe_saldo_favor,
            $importe_a_pagar,
            $importe_a_pagar,
            $vep_nro,
            $vep_nro,
            $nuevo_estado_id,
            $nuevo_estado_id,
            $empresa_id,
            $vencimiento_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => 'Estado de vencimiento actualizado.',
            'data' => ['vencimiento_id' => $vencimiento_id, 'nuevo_estado_id' => $nuevo_estado_id],
            'error' => null
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'success' => false,
            'message' => 'Error al actualizar vencimiento: ' . $e->getMessage(),
            'data' => null,
            'error' => $e->getMessage()
        ];
    }
}
