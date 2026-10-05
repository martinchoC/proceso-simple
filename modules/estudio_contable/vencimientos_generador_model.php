<?php
/**
 * Modelo de Datos: Generador Masivo de Vencimientos
 * Módulo 4: Estudio Contable (vencimientos_generador_model.php)
 * Tablas: estudio__calendario_vencimientos, estudio__vencimientos_generados, estudio__impuestos_obligaciones
 */

declare(strict_types=1);

/**
 * Obtener combos para el generador
 */
function vencimientos_generador_combos(mysqli $conexion, int $empresa_id): array
{
    $combos = [
        'obligaciones' => [],
        'clientes' => []
    ];

    $res_o = mysqli_query($conexion, "SELECT impuesto_obligacion_id, organismo, codigo_oficial, nombre FROM estudio__impuestos_obligaciones WHERE activo = 1 ORDER BY organismo, nombre");
    while ($r = mysqli_fetch_assoc($res_o)) {
        $combos['obligaciones'][] = $r;
    }

    $sql_c = "SELECT cliente_id, razon_social, cuit, terminacion_cuit FROM estudio__clientes WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY razon_social";
    $stmt_c = mysqli_prepare($conexion, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "i", $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    while ($r = mysqli_fetch_assoc($res_c)) {
        $combos['clientes'][] = $r;
    }
    mysqli_stmt_close($stmt_c);

    return $combos;
}

/**
 * Vista previa de los vencimientos a generar antes del impacto
 */
function vencimientos_generador_preview(mysqli $conexion, int $empresa_id, int $periodo_fiscal, int $obligacion_id = 0, int $cliente_id = 0): array
{
    $anio = (int) substr((string) $periodo_fiscal, 0, 4);
    $mes = (int) substr((string) $periodo_fiscal, 4, 2);

    // Obtener clientes a evaluar
    $sql_cli = "SELECT c.cliente_id, c.razon_social, c.cuit, c.terminacion_cuit, c.condicion_iva_id,
                       c.ingresos_brutos_tipo, c.jurisdiccion_sede_id, c.es_empleador
                FROM estudio__clientes c
                WHERE c.empresa_id = ? AND c.tabla_estado_registro_id = 1";
    $params_c = [$empresa_id];
    $types_c = "i";

    if ($cliente_id > 0) {
        $sql_cli .= " AND c.cliente_id = ?";
        $params_c[] = $cliente_id;
        $types_c .= "i";
    }

    $stmt_c = mysqli_prepare($conexion, $sql_cli);
    mysqli_stmt_bind_param($stmt_c, $types_c, ...$params_c);
    mysqli_stmt_execute($stmt_c);
    $clientes = mysqli_stmt_get_result($stmt_c);

    $items_preview = [];

    while ($cli = mysqli_fetch_assoc($clientes)) {
        // Obligaciones aplicables: asignadas explícitamente o automáticas
        $sql_ob = "SELECT o.impuesto_obligacion_id, o.organismo, o.codigo_oficial, o.nombre AS obligacion_nombre,
                          co.jurisdiccion_id, j.jurisdiccion_nombre
                   FROM estudio__clientes_obligaciones co
                   INNER JOIN estudio__impuestos_obligaciones o ON o.impuesto_obligacion_id = co.impuesto_obligacion_id
                   LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = co.jurisdiccion_id
                   WHERE co.empresa_id = ? AND co.cliente_id = ? AND co.tabla_estado_registro_id = 1";
        
        $params_o = [$empresa_id, $cli['cliente_id']];
        $types_o = "ii";

        if ($obligacion_id > 0) {
            $sql_ob .= " AND co.impuesto_obligacion_id = ?";
            $params_o[] = $obligacion_id;
            $types_o .= "i";
        }

        $stmt_o = mysqli_prepare($conexion, $sql_ob);
        mysqli_stmt_bind_param($stmt_o, $types_o, ...$params_o);
        mysqli_stmt_execute($stmt_o);
        $res_ob = mysqli_stmt_get_result($stmt_o);

        // Si no tiene asignaciones explícitas pero se eligió una obligación o evaluación general
        $obligaciones_cliente = [];
        while ($ro = mysqli_fetch_assoc($res_ob)) {
            $obligaciones_cliente[] = $ro;
        }
        mysqli_stmt_close($stmt_o);

        // Si no tiene obligaciones específicas, agregar las estándar según perfil fiscal
        if (empty($obligaciones_cliente)) {
            $sql_def = "SELECT o.impuesto_obligacion_id, o.organismo, o.codigo_oficial, o.nombre AS obligacion_nombre,
                               NULL AS jurisdiccion_id, NULL AS jurisdiccion_nombre
                        FROM estudio__impuestos_obligaciones o
                        WHERE o.activo = 1";
            if ($obligacion_id > 0) {
                $sql_def .= " AND o.impuesto_obligacion_id = " . intval($obligacion_id);
            } else {
                // Obligaciones base según si es RI o Empleador
                $conds = ["o.codigo_oficial = 'IVA'"];
                if ($cli['es_empleador'] == 1) {
                    $conds[] = "o.codigo_oficial = 'F931'";
                }
                $sql_def .= " AND (" . implode(" OR ", $conds) . ")";
            }
            $res_def = mysqli_query($conexion, $sql_def);
            while ($rd = mysqli_fetch_assoc($res_def)) {
                $obligaciones_cliente[] = $rd;
            }
        }

        foreach ($obligaciones_cliente as $ob) {
            // Buscar fecha límite en calendario
            $sql_cal = "SELECT f_vencimiento 
                        FROM estudio__calendario_vencimientos
                        WHERE impuesto_obligacion_id = ? 
                          AND anio = ? AND mes_periodo = ?
                          AND (terminacion_cuit = ? OR terminacion_cuit = -1)
                        ORDER BY terminacion_cuit DESC LIMIT 1";
            $stmt_cal = mysqli_prepare($conexion, $sql_cal);
            $terminacion = (int) $cli['terminacion_cuit'];
            mysqli_stmt_bind_param($stmt_cal, "iiii", $ob['impuesto_obligacion_id'], $anio, $mes, $terminacion);
            mysqli_stmt_execute($stmt_cal);
            $res_cal = mysqli_stmt_get_result($stmt_cal);
            $row_cal = mysqli_fetch_assoc($res_cal);
            mysqli_stmt_close($stmt_cal);

            // Si no hay calendario oficial cargado, calcular vencimiento por defecto día 20 del mes siguiente
            if ($row_cal && !empty($row_cal['f_vencimiento'])) {
                $f_limite = $row_cal['f_vencimiento'];
            } else {
                $f_limite = date('Y-m-20', strtotime("+1 month", strtotime("{$anio}-{$mes}-01")));
            }

            // Comprobar si ya existe en la base de datos
            $sql_chk = "SELECT vencimiento_id, tabla_estado_registro_id 
                        FROM estudio__vencimientos_generados
                        WHERE empresa_id = ? AND cliente_id = ? 
                          AND impuesto_obligacion_id = ? AND periodo_fiscal = ?
                          AND (jurisdiccion_id <=> ?)";
            $stmt_chk = mysqli_prepare($conexion, $sql_chk);
            mysqli_stmt_bind_param($stmt_chk, "iiiis", $empresa_id, $cli['cliente_id'], $ob['impuesto_obligacion_id'], $periodo_fiscal, $ob['jurisdiccion_id']);
            mysqli_stmt_execute($stmt_chk);
            $res_chk = mysqli_stmt_get_result($stmt_chk);
            $existe = mysqli_fetch_assoc($res_chk);
            mysqli_stmt_close($stmt_chk);

            $items_preview[] = [
                'cliente_id' => $cli['cliente_id'],
                'razon_social' => $cli['razon_social'],
                'cuit' => $cli['cuit'],
                'terminacion_cuit' => $cli['terminacion_cuit'],
                'impuesto_obligacion_id' => $ob['impuesto_obligacion_id'],
                'organismo' => $ob['organismo'],
                'codigo_oficial' => $ob['codigo_oficial'],
                'obligacion_nombre' => $ob['obligacion_nombre'],
                'jurisdiccion_id' => $ob['jurisdiccion_id'],
                'jurisdiccion_nombre' => $ob['jurisdiccion_nombre'] ?? 'Nacional',
                'periodo_fiscal' => $periodo_fiscal,
                'f_limite' => $f_limite,
                'ya_generado' => !empty($existe),
                'vencimiento_id_existente' => $existe['vencimiento_id'] ?? null
            ];
        }
    }
    mysqli_stmt_close($stmt_c);

    return $items_preview;
}

/**
 * Ejecución masiva de generación por lote mensual
 */
function vencimientos_generador_ejecutar(mysqli $conexion, int $empresa_id, int $periodo_fiscal, int $obligacion_id = 0, int $cliente_id = 0, int $usuario_id = 0): array
{
    $preview = vencimientos_generador_preview($conexion, $empresa_id, $periodo_fiscal, $obligacion_id, $cliente_id);

    if (empty($preview)) {
        return [
            'success' => false,
            'message' => 'No se encontraron obligaciones ni clientes aplicables para el período indicado.',
            'data' => null,
            'error' => 'NO_ITEMS'
        ];
    }

    mysqli_begin_transaction($conexion);

    $generados = 0;
    $omitidos = 0;

    try {
        $sql_ins = "INSERT INTO estudio__vencimientos_generados (
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv,
                        comprobante_nro, comprobante_id, entidad_id, f_emision,
                        cliente_id, impuesto_obligacion_id, jurisdiccion_id,
                        periodo_fiscal, f_limite, importe_determinado, importe_saldo_favor,
                        importe_a_pagar, tabla_estado_registro_id
                    ) VALUES (
                        ?, 1, 0, 0, 0, NULL, 0, CURDATE(),
                        ?, ?, ?, ?, ?, 0.00, 0.00, 0.00, 3
                    )";
        $stmt_ins = mysqli_prepare($conexion, $sql_ins);

        foreach ($preview as $item) {
            if ($item['ya_generado']) {
                $omitidos++;
                continue;
            }

            mysqli_stmt_bind_param(
                $stmt_ins,
                "iiiisis",
                $empresa_id,
                $item['cliente_id'],
                $item['impuesto_obligacion_id'],
                $item['jurisdiccion_id'],
                $item['periodo_fiscal'],
                $item['f_limite']
            );
            mysqli_stmt_execute($stmt_ins);
            $generados++;
        }
        mysqli_stmt_close($stmt_ins);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Proceso finalizado con éxito: {$generados} vencimientos generados ({$omitidos} ya existían previamente).",
            'data' => [
                'generados' => $generados,
                'omitidos' => $omitidos,
                'total_evaluado' => count($preview)
            ],
            'error' => null
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'success' => false,
            'message' => 'Error durante la generación por lote: ' . $e->getMessage(),
            'data' => null,
            'error' => $e->getMessage()
        ];
    }
}
