<?php
/**
 * Modelo de Datos: Tarifarios y Conceptos de Honorarios
 * Módulo 4: Estudio Contable (honorarios_conceptos_model.php)
 * Tablas: estudio__honorarios_conceptos (404), estudio__honorarios_tarifarios (405), estudio__clientes_abonos (407)
 */

declare(strict_types=1);

/**
 * Combos para tarifas y abonos
 */
function honorarios_conceptos_combos(mysqli $conexion, int $empresa_id): array
{
    $combos = [
        'productos' => [],
        'clientes' => [],
        'conceptos' => [],
        'puntos_venta' => [],
        'condiciones_pago' => []
    ];

    // Productos que sean servicios de venta
    $res_p = mysqli_query($conexion, "SELECT producto_id, producto_codigo, producto_nombre FROM gestion__productos WHERE (empresa_id = {$empresa_id} OR empresa_id = 0) AND tabla_estado_registro_id = 1 ORDER BY producto_nombre");
    while ($r = mysqli_fetch_assoc($res_p)) {
        $combos['productos'][] = $r;
    }

    // Clientes
    $sql_c = "SELECT cliente_id, razon_social, cuit FROM estudio__clientes WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY razon_social";
    $stmt_c = mysqli_prepare($conexion, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "i", $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    while ($r = mysqli_fetch_assoc($res_c)) {
        $combos['clientes'][] = $r;
    }
    mysqli_stmt_close($stmt_c);

    // Conceptos ya creados
    $sql_co = "SELECT concepto_id, nombre, tipo_cargo FROM estudio__honorarios_conceptos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY nombre";
    $stmt_co = mysqli_prepare($conexion, $sql_co);
    mysqli_stmt_bind_param($stmt_co, "i", $empresa_id);
    mysqli_stmt_execute($stmt_co);
    $res_co = mysqli_stmt_get_result($stmt_co);
    while ($r = mysqli_fetch_assoc($res_co)) {
        $combos['conceptos'][] = $r;
    }
    mysqli_stmt_close($stmt_co);

    // Puntos de venta activos
    $res_pv = mysqli_query($conexion, "SELECT punto_venta_id, nombre, codigo_fiscal FROM gestion__puntos_venta WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id = 1 ORDER BY codigo_fiscal");
    while ($r = mysqli_fetch_assoc($res_pv)) {
        $combos['puntos_venta'][] = $r;
    }

    // Condiciones de pago
    $res_cp = mysqli_query($conexion, "SELECT condicion_pago_id, condicion_pago FROM gestion__condiciones_pago WHERE tabla_estado_registro_id = 1 ORDER BY condicion_pago_id");
    while ($r = mysqli_fetch_assoc($res_cp)) {
        $combos['condiciones_pago'][] = $r;
    }

    return $combos;
}

/**
 * Listar conceptos de honorarios
 */
function honorarios_conceptos_listar(mysqli $conexion, int $empresa_id): array
{
    $sql = "SELECT hc.concepto_id, hc.empresa_id, hc.producto_id, hc.nombre, hc.descripcion,
                   hc.tipo_cargo, hc.tabla_estado_registro_id, hc.created_at,
                   p.producto_codigo, p.producto_nombre
            FROM estudio__honorarios_conceptos hc
            INNER JOIN gestion__productos p ON p.producto_id = hc.producto_id
            WHERE hc.empresa_id = ? AND hc.tabla_estado_registro_id = 1
            ORDER BY hc.nombre ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/**
 * Guardar concepto de honorarios
 */
function honorarios_concepto_guardar(mysqli $conexion, int $empresa_id, array $datos): array
{
    $concepto_id = intval($datos['concepto_id'] ?? 0);
    $producto_id = intval($datos['producto_id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $tipo_cargo = in_array($datos['tipo_cargo'] ?? '', ['FIJO_MENSUAL', 'POR_COMPROBANTE', 'POR_LEGAJO', 'POR_HORA', 'EVENTUAL'], true) ? $datos['tipo_cargo'] : 'FIJO_MENSUAL';

    if ($producto_id <= 0 || empty($nombre)) {
        return ['success' => false, 'message' => 'Producto base y nombre de concepto son obligatorios.', 'data' => null, 'error' => 'VALIDATION'];
    }

    mysqli_begin_transaction($conexion);
    try {
        if ($concepto_id > 0) {
            $sql = "UPDATE estudio__honorarios_conceptos 
                    SET producto_id = ?, nombre = ?, descripcion = ?, tipo_cargo = ?
                    WHERE empresa_id = ? AND concepto_id = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "isssii", $producto_id, $nombre, $descripcion, $tipo_cargo, $empresa_id, $concepto_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $sql = "INSERT INTO estudio__honorarios_conceptos (empresa_id, producto_id, nombre, descripcion, tipo_cargo, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "iisss", $empresa_id, $producto_id, $nombre, $descripcion, $tipo_cargo);
            mysqli_stmt_execute($stmt);
            $concepto_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Concepto de honorarios guardado.', 'data' => ['concepto_id' => $concepto_id], 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Listar abonos recurrentes configurados por cliente
 */
function clientes_abonos_listar(mysqli $conexion, int $empresa_id, int $cliente_id = 0): array
{
    $sql = "SELECT a.abono_id, a.empresa_id, a.cliente_id, a.concepto_id, a.punto_venta_id,
                   a.condicion_venta_id, a.importe_base, a.dia_facturacion, a.aplica_adicionales_automaticos,
                   a.tope_comprobantes_incluidos, a.precio_excedente_comprobante,
                   a.tope_legajos_incluidos, a.precio_excedente_legajo, a.ultimo_periodo_facturado,
                   a.tabla_estado_registro_id,
                   c.razon_social AS cliente_nombre, c.cuit AS cliente_cuit,
                   hc.nombre AS concepto_nombre, hc.tipo_cargo,
                   pv.codigo_fiscal AS punto_venta_codigo,
                   cp.condicion_pago
            FROM estudio__clientes_abonos a
            INNER JOIN estudio__clientes c ON c.cliente_id = a.cliente_id
            INNER JOIN estudio__honorarios_conceptos hc ON hc.concepto_id = a.concepto_id
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = a.punto_venta_id
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = a.condicion_venta_id
            WHERE a.empresa_id = ? AND a.tabla_estado_registro_id = 1";

    $params = [$empresa_id];
    $types = "i";

    if ($cliente_id > 0) {
        $sql .= " AND a.cliente_id = ?";
        $params[] = $cliente_id;
        $types .= "i";
    }

    $sql .= " ORDER BY c.razon_social ASC, a.abono_id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Guardar o actualizar regla de abono recurrente de un cliente
 */
function cliente_abono_guardar(mysqli $conexion, int $empresa_id, array $datos): array
{
    $abono_id = intval($datos['abono_id'] ?? 0);
    $cliente_id = intval($datos['cliente_id'] ?? 0);
    $concepto_id = intval($datos['concepto_id'] ?? 0);
    $punto_venta_id = intval($datos['punto_venta_id'] ?? 1);
    $condicion_venta_id = intval($datos['condicion_venta_id'] ?? 1);
    $importe_base = floatval($datos['importe_base'] ?? 0.0);
    $dia_facturacion = intval($datos['dia_facturacion'] ?? 1);
    $aplica_adicionales_automaticos = !empty($datos['aplica_adicionales_automaticos']) ? 1 : 0;
    $tope_comprobantes_incluidos = intval($datos['tope_comprobantes_incluidos'] ?? 0);
    $precio_excedente_comprobante = floatval($datos['precio_excedente_comprobante'] ?? 0.0);
    $tope_legajos_incluidos = intval($datos['tope_legajos_incluidos'] ?? 0);
    $precio_excedente_legajo = floatval($datos['precio_excedente_legajo'] ?? 0.0);

    if ($cliente_id <= 0 || $concepto_id <= 0 || $importe_base <= 0) {
        return ['success' => false, 'message' => 'Cliente, concepto e importe base válido son obligatorios.', 'data' => null, 'error' => 'VALIDATION'];
    }

    mysqli_begin_transaction($conexion);
    try {
        if ($abono_id > 0) {
            $sql = "UPDATE estudio__clientes_abonos SET
                        cliente_id = ?, concepto_id = ?, punto_venta_id = ?, condicion_venta_id = ?,
                        importe_base = ?, dia_facturacion = ?, aplica_adicionales_automaticos = ?,
                        tope_comprobantes_incluidos = ?, precio_excedente_comprobante = ?,
                        tope_legajos_incluidos = ?, precio_excedente_legajo = ?
                    WHERE empresa_id = ? AND abono_id = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "iiiidiiididii",
                $cliente_id,
                $concepto_id,
                $punto_venta_id,
                $condicion_venta_id,
                $importe_base,
                $dia_facturacion,
                $aplica_adicionales_automaticos,
                $tope_comprobantes_incluidos,
                $precio_excedente_comprobante,
                $tope_legajos_incluidos,
                $precio_excedente_legajo,
                $empresa_id,
                $abono_id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $sql = "INSERT INTO estudio__clientes_abonos (
                        empresa_id, cliente_id, concepto_id, punto_venta_id, condicion_venta_id,
                        importe_base, dia_facturacion, aplica_adicionales_automaticos,
                        tope_comprobantes_incluidos, precio_excedente_comprobante,
                        tope_legajos_incluidos, precio_excedente_legajo, tabla_estado_registro_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "iiiiidiiidid",
                $empresa_id,
                $cliente_id,
                $concepto_id,
                $punto_venta_id,
                $condicion_venta_id,
                $importe_base,
                $dia_facturacion,
                $aplica_adicionales_automaticos,
                $tope_comprobantes_incluidos,
                $precio_excedente_comprobante,
                $tope_legajos_incluidos,
                $precio_excedente_legajo
            );
            mysqli_stmt_execute($stmt);
            $abono_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Abono recurrente guardado.', 'data' => ['abono_id' => $abono_id], 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Dar de baja abono
 */
function cliente_abono_eliminar(mysqli $conexion, int $empresa_id, int $abono_id): array
{
    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE estudio__clientes_abonos SET tabla_estado_registro_id = 2 WHERE empresa_id = ? AND abono_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $abono_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Abono dado de baja.', 'data' => null, 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}
