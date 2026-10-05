<?php
/**
 * Modelo de Datos: Padrón de Clientes y Matriz Territorial
 * Módulo 4: Estudio Contable (estudio_clientes_model.php)
 * Tabla Principal: estudio__clientes (tabla_id = 402)
 */

declare(strict_types=1);

/**
 * Obtener combos para formularios y filtros de clientes
 */
function estudio_clientes_combos(mysqli $conexion, int $empresa_id): array
{
    $combos = [
        'tipos' => [],
        'condiciones_iva' => [],
        'jurisdicciones' => [],
        'empresas_erp' => [],
        'usuarios' => [],
        'estados' => []
    ];

    // Tipos de clientes del estudio
    $sql_t = "SELECT cliente_tipo_id, nombre FROM estudio__clientes_tipos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY nombre";
    $stmt_t = mysqli_prepare($conexion, $sql_t);
    mysqli_stmt_bind_param($stmt_t, "i", $empresa_id);
    mysqli_stmt_execute($stmt_t);
    $res_t = mysqli_stmt_get_result($stmt_t);
    while ($r = mysqli_fetch_assoc($res_t)) {
        $combos['tipos'][] = $r;
    }
    mysqli_stmt_close($stmt_t);

    // Condiciones IVA / Fiscales
    $res_iva = mysqli_query($conexion, "SELECT condicion_fiscal_id, condicion_fiscal, condicion_fiscal_codigo FROM gestion__condiciones_fiscales WHERE tabla_estado_registro_id = 1 ORDER BY condicion_fiscal_id");
    while ($r = mysqli_fetch_assoc($res_iva)) {
        $combos['condiciones_iva'][] = $r;
    }

    // Jurisdicciones
    $res_j = mysqli_query($conexion, "SELECT jurisdiccion_id, jurisdiccion_codigo, jurisdiccion_nombre FROM gestion__jurisdicciones WHERE tabla_estado_registro_id = 1 ORDER BY orden, jurisdiccion_codigo");
    while ($r = mysqli_fetch_assoc($res_j)) {
        $combos['jurisdicciones'][] = $r;
    }

    // Empresas ERP vinculables
    $res_emp = mysqli_query($conexion, "SELECT empresa_id, empresa FROM conf__empresas WHERE tabla_estado_registro_id = 1 AND empresa_id > 1 ORDER BY empresa");
    while ($r = mysqli_fetch_assoc($res_emp)) {
        $combos['empresas_erp'][] = $r;
    }

    // Usuarios del sistema para roles RACI por defecto
    $res_u = mysqli_query($conexion, "SELECT usuario_id, usuario, usuario_nombre FROM conf__usuarios WHERE tabla_estado_registro_id = 1 ORDER BY usuario_nombre");
    while ($r = mysqli_fetch_assoc($res_u)) {
        $combos['usuarios'][] = $r;
    }

    // Estados específicos de estudio__clientes (tabla 402)
    $res_est = mysqli_query($conexion, "SELECT estado_registro_id, tabla_estado_registro FROM conf__tablas_estados_registros WHERE tabla_id = 402 ORDER BY orden");
    while ($r = mysqli_fetch_assoc($res_est)) {
        $combos['estados'][] = $r;
    }

    return $combos;
}

/**
 * Listado de clientes del estudio con filtros y métricas territoriales
 */
function estudio_clientes_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
{
    $sql = "SELECT c.cliente_id, c.empresa_id, c.cliente_tipo_id, c.razon_social, c.cuit,
                   c.terminacion_cuit, c.condicion_iva_id, c.empresa_vinculada_id,
                   c.mes_cierre_ejercicio, c.ingresos_brutos_tipo, c.jurisdiccion_sede_id,
                   c.es_empleador, c.cantidad_empleados, c.usuario_r_defecto_id, c.usuario_a_defecto_id,
                   c.observaciones, c.tabla_estado_registro_id, c.created_at,
                   ct.nombre AS tipo_cliente_nombre,
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   js.jurisdiccion_nombre AS jurisdiccion_sede_nombre,
                   ur.usuario_nombre AS usuario_r_nombre,
                   ua.usuario_nombre AS usuario_a_nombre,
                   ter.tabla_estado_registro,
                   COALESCE(jur_count.total_jurisdicciones, 0) AS total_jurisdicciones
            FROM estudio__clientes c
            INNER JOIN estudio__clientes_tipos ct ON ct.cliente_tipo_id = c.cliente_tipo_id
            INNER JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = c.condicion_iva_id
            LEFT JOIN gestion__jurisdicciones js ON js.jurisdiccion_id = c.jurisdiccion_sede_id
            LEFT JOIN conf__usuarios ur ON ur.usuario_id = c.usuario_r_defecto_id
            LEFT JOIN conf__usuarios ua ON ua.usuario_id = c.usuario_a_defecto_id
            LEFT JOIN conf__tablas_estados_registros ter 
                ON ter.tabla_id = 402 AND ter.estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN (
                SELECT cliente_id, COUNT(*) AS total_jurisdicciones 
                FROM estudio__clientes_jurisdicciones 
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                GROUP BY cliente_id
            ) jur_count ON jur_count.cliente_id = c.cliente_id
            WHERE c.empresa_id = ?";

    $params = [$empresa_id, $empresa_id];
    $types = "ii";

    if (!empty($filtros['cliente_tipo_id'])) {
        $sql .= " AND c.cliente_tipo_id = ?";
        $params[] = intval($filtros['cliente_tipo_id']);
        $types .= "i";
    }

    if (!empty($filtros['condicion_iva_id'])) {
        $sql .= " AND c.condicion_iva_id = ?";
        $params[] = intval($filtros['condicion_iva_id']);
        $types .= "i";
    }

    if (!empty($filtros['terminacion_cuit']) && is_numeric($filtros['terminacion_cuit'])) {
        $sql .= " AND c.terminacion_cuit = ?";
        $params[] = intval($filtros['terminacion_cuit']);
        $types .= "i";
    }

    if (!empty($filtros['estado_id'])) {
        $sql .= " AND c.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['estado_id']);
        $types .= "i";
    }

    if (!empty($filtros['busqueda'])) {
        $term = "%" . trim($filtros['busqueda']) . "%";
        $sql .= " AND (c.razon_social LIKE ? OR c.cuit LIKE ?)";
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    $sql .= " ORDER BY c.razon_social ASC";

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
 * Obtener un cliente específico con sus jurisdicciones y tasas municipales
 */
function estudio_cliente_obtener(mysqli $conexion, int $empresa_id, int $cliente_id): ?array
{
    $sql = "SELECT c.*, ct.nombre AS tipo_cliente_nombre, cf.condicion_fiscal
            FROM estudio__clientes c
            INNER JOIN estudio__clientes_tipos ct ON ct.cliente_tipo_id = c.cliente_tipo_id
            INNER JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = c.condicion_iva_id
            WHERE c.empresa_id = ? AND c.cliente_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $cliente_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cliente = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$cliente) {
        return null;
    }

    // Jurisdicciones del cliente
    $sql_j = "SELECT cj.*, j.jurisdiccion_codigo, j.jurisdiccion_nombre 
              FROM estudio__clientes_jurisdicciones cj
              INNER JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cj.jurisdiccion_id
              WHERE cj.empresa_id = ? AND cj.cliente_id = ? AND cj.tabla_estado_registro_id = 1
              ORDER BY cj.tipo_inscripcion, j.jurisdiccion_codigo";
    $stmt_j = mysqli_prepare($conexion, $sql_j);
    mysqli_stmt_bind_param($stmt_j, "ii", $empresa_id, $cliente_id);
    mysqli_stmt_execute($stmt_j);
    $res_j = mysqli_stmt_get_result($stmt_j);
    $cliente['jurisdicciones'] = [];
    while ($rj = mysqli_fetch_assoc($res_j)) {
        $cliente['jurisdicciones'][] = $rj;
    }
    mysqli_stmt_close($stmt_j);

    // Tasas Municipales
    $sql_m = "SELECT * FROM estudio__tasas_municipales 
              WHERE empresa_id = ? AND cliente_id = ? AND tabla_estado_registro_id = 1
              ORDER BY municipio_nombre";
    $stmt_m = mysqli_prepare($conexion, $sql_m);
    mysqli_stmt_bind_param($stmt_m, "ii", $empresa_id, $cliente_id);
    mysqli_stmt_execute($stmt_m);
    $res_m = mysqli_stmt_get_result($stmt_m);
    $cliente['tasas_municipales'] = [];
    while ($rm = mysqli_fetch_assoc($res_m)) {
        $cliente['tasas_municipales'][] = $rm;
    }
    mysqli_stmt_close($stmt_m);

    return $cliente;
}

/**
 * Guardar o actualizar un cliente con transacción y validación segura
 */
function estudio_cliente_guardar(mysqli $conexion, int $empresa_id, array $datos, int $usuario_id): array
{
    $cliente_id = intval($datos['cliente_id'] ?? 0);
    $cliente_tipo_id = intval($datos['cliente_tipo_id'] ?? 0);
    $razon_social = trim($datos['razon_social'] ?? '');
    $cuit = trim($datos['cuit'] ?? '');
    $condicion_iva_id = intval($datos['condicion_iva_id'] ?? 0);
    $empresa_vinculada_id = !empty($datos['empresa_vinculada_id']) ? intval($datos['empresa_vinculada_id']) : null;
    $mes_cierre_ejercicio = intval($datos['mes_cierre_ejercicio'] ?? 12);
    $ingresos_brutos_tipo = in_array($datos['ingresos_brutos_tipo'] ?? '', ['LOCAL', 'CONVENIO_MULTILATERAL', 'EXENTO'], true) ? $datos['ingresos_brutos_tipo'] : 'LOCAL';
    $jurisdiccion_sede_id = !empty($datos['jurisdiccion_sede_id']) ? intval($datos['jurisdiccion_sede_id']) : null;
    $es_empleador = !empty($datos['es_empleador']) ? 1 : 0;
    $cantidad_empleados = intval($datos['cantidad_empleados'] ?? 0);
    $usuario_r_defecto_id = !empty($datos['usuario_r_defecto_id']) ? intval($datos['usuario_r_defecto_id']) : null;
    $usuario_a_defecto_id = !empty($datos['usuario_a_defecto_id']) ? intval($datos['usuario_a_defecto_id']) : null;
    $observaciones = trim($datos['observaciones'] ?? '');
    $tabla_estado_registro_id = intval($datos['tabla_estado_registro_id'] ?? 1);

    if ($cliente_tipo_id <= 0 || empty($razon_social) || empty($cuit) || $condicion_iva_id <= 0) {
        return ['success' => false, 'message' => 'Complete todos los campos obligatorios (*).', 'data' => null, 'error' => 'VALIDATION_ERROR'];
    }

    // Limpieza y cálculo de terminación de CUIT
    $cuit_limpio = preg_replace('/[^0-9]/', '', $cuit);
    if (strlen($cuit_limpio) !== 11) {
        return ['success' => false, 'message' => 'El CUIT ingresado debe poseer 11 dígitos numéricos.', 'data' => null, 'error' => 'INVALID_CUIT'];
    }
    $terminacion_cuit = (int) substr($cuit_limpio, -1);

    mysqli_begin_transaction($conexion);

    try {
        if ($cliente_id > 0) {
            // Update
            $sql = "UPDATE estudio__clientes SET
                        cliente_tipo_id = ?, razon_social = ?, cuit = ?, terminacion_cuit = ?,
                        condicion_iva_id = ?, empresa_vinculada_id = ?, mes_cierre_ejercicio = ?,
                        ingresos_brutos_tipo = ?, jurisdiccion_sede_id = ?, es_empleador = ?,
                        cantidad_empleados = ?, usuario_r_defecto_id = ?, usuario_a_defecto_id = ?,
                        observaciones = ?, tabla_estado_registro_id = ?
                    WHERE empresa_id = ? AND cliente_id = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "issiiiiisiiiiiiii",
                $cliente_tipo_id,
                $razon_social,
                $cuit,
                $terminacion_cuit,
                $condicion_iva_id,
                $empresa_vinculada_id,
                $mes_cierre_ejercicio,
                $ingresos_brutos_tipo,
                $jurisdiccion_sede_id,
                $es_empleador,
                $cantidad_empleados,
                $usuario_r_defecto_id,
                $usuario_a_defecto_id,
                $observaciones,
                $tabla_estado_registro_id,
                $empresa_id,
                $cliente_id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            // Insert
            $sql = "INSERT INTO estudio__clientes (
                        empresa_id, cliente_tipo_id, razon_social, cuit, terminacion_cuit,
                        condicion_iva_id, empresa_vinculada_id, mes_cierre_ejercicio,
                        ingresos_brutos_tipo, jurisdiccion_sede_id, es_empleador,
                        cantidad_empleados, usuario_r_defecto_id, usuario_a_defecto_id,
                        observaciones, tabla_estado_registro_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "iissiiiiisiiiiis",
                $empresa_id,
                $cliente_tipo_id,
                $razon_social,
                $cuit,
                $terminacion_cuit,
                $condicion_iva_id,
                $empresa_vinculada_id,
                $mes_cierre_ejercicio,
                $ingresos_brutos_tipo,
                $jurisdiccion_sede_id,
                $es_empleador,
                $cantidad_empleados,
                $usuario_r_defecto_id,
                $usuario_a_defecto_id,
                $observaciones,
                $tabla_estado_registro_id
            );
            mysqli_stmt_execute($stmt);
            $cliente_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }

        // Si se indicó jurisdicción sede y no está en la matriz, registrarla automáticamente
        if ($jurisdiccion_sede_id > 0) {
            $sql_sede = "INSERT INTO estudio__clientes_jurisdicciones (
                            empresa_id, cliente_id, jurisdiccion_id, tipo_inscripcion, 
                            coeficiente_cm05, f_alta, tabla_estado_registro_id
                         ) VALUES (?, ?, ?, 'SEDE_PRINCIPAL', 0.0000, CURDATE(), 1)
                         ON DUPLICATE KEY UPDATE 
                            tipo_inscripcion = 'SEDE_PRINCIPAL', 
                            tabla_estado_registro_id = 1";
            $stmt_s = mysqli_prepare($conexion, $sql_sede);
            mysqli_stmt_bind_param($stmt_s, "iii", $empresa_id, $cliente_id, $jurisdiccion_sede_id);
            mysqli_stmt_execute($stmt_s);
            mysqli_stmt_close($stmt_s);
        }

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => 'Cliente guardado correctamente.',
            'data' => ['cliente_id' => $cliente_id],
            'error' => null
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'success' => false,
            'message' => 'Error al guardar cliente: ' . $e->getMessage(),
            'data' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Guardar o actualizar jurisdicción de convenio multilateral / local para un cliente
 */
function estudio_cliente_guardar_jurisdiccion(mysqli $conexion, int $empresa_id, array $datos): array
{
    $cliente_id = intval($datos['cliente_id'] ?? 0);
    $jurisdiccion_id = intval($datos['jurisdiccion_id'] ?? 0);
    $tipo_inscripcion = in_array($datos['tipo_inscripcion'] ?? '', ['SEDE_PRINCIPAL', 'EXTRAÑA_JURISDICCION', 'REGIMEN_SIMPLIFICADO'], true) ? $datos['tipo_inscripcion'] : 'EXTRAÑA_JURISDICCION';
    $numero_inscripcion = trim($datos['numero_inscripcion'] ?? '');
    $coeficiente_cm05 = floatval($datos['coeficiente_cm05'] ?? 0.0);
    $es_agente_retencion = !empty($datos['es_agente_retencion']) ? 1 : 0;
    $es_agente_percepcion = !empty($datos['es_agente_percepcion']) ? 1 : 0;
    $f_alta = !empty($datos['f_alta']) ? $datos['f_alta'] : date('Y-m-d');

    if ($cliente_id <= 0 || $jurisdiccion_id <= 0) {
        return ['success' => false, 'message' => 'Cliente y jurisdicción son requeridos.', 'data' => null, 'error' => 'PARAM_ERROR'];
    }

    mysqli_begin_transaction($conexion);
    try {
        $sql = "INSERT INTO estudio__clientes_jurisdicciones (
                    empresa_id, cliente_id, jurisdiccion_id, tipo_inscripcion,
                    numero_inscripcion, coeficiente_cm05, es_agente_retencion,
                    es_agente_percepcion, f_alta, tabla_estado_registro_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    tipo_inscripcion = VALUES(tipo_inscripcion),
                    numero_inscripcion = VALUES(numero_inscripcion),
                    coeficiente_cm05 = VALUES(coeficiente_cm05),
                    es_agente_retencion = VALUES(es_agente_retencion),
                    es_agente_percepcion = VALUES(es_agente_percepcion),
                    f_alta = VALUES(f_alta),
                    tabla_estado_registro_id = 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "iiissdiis",
            $empresa_id,
            $cliente_id,
            $jurisdiccion_id,
            $tipo_inscripcion,
            $numero_inscripcion,
            $coeficiente_cm05,
            $es_agente_retencion,
            $es_agente_percepcion,
            $f_alta
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Jurisdicción registrada en matriz territorial.', 'data' => null, 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al guardar jurisdicción: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Eliminar / dar de baja jurisdicción de un cliente
 */
function estudio_cliente_eliminar_jurisdiccion(mysqli $conexion, int $empresa_id, int $cliente_jurisdiccion_id): array
{
    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE estudio__clientes_jurisdicciones 
                SET tabla_estado_registro_id = 2, f_baja = CURDATE()
                WHERE empresa_id = ? AND cliente_jurisdiccion_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $cliente_jurisdiccion_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Jurisdicción dada de baja.', 'data' => null, 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Cambiar estado de un cliente (Activo / Inactivo / Suspendido)
 */
function estudio_cliente_cambiar_estado(mysqli $conexion, int $empresa_id, int $cliente_id, int $nuevo_estado_id): array
{
    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE estudio__clientes SET tabla_estado_registro_id = ? WHERE empresa_id = ? AND cliente_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado_id, $empresa_id, $cliente_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado actualizado exitosamente.', 'data' => null, 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al actualizar estado: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}
