<?php
/**
 * Modelo de Datos: Padrón de Clientes y Matriz Territorial
 * Módulo 4: Estudio Contable (estudio_clientes_model.php)
 * Tabla Principal: estudio__clientes (tabla_id = 402)
 */

declare(strict_types=1);

/**
 * Nivel de servicio (qué le hace el estudio) y origen de los datos del cliente. Scripts
 * 20261105_estudio_clientes_origen_datos_01.sql y 20261106_estudio_clientes_nivel_servicio_01.sql.
 *   PADRON   sin empresa: padrón, vencimientos, tareas, honorarios.
 *   FISCAL   empresa SIMPLIFICADA sin perfiles (el estudio opera por la vinculación).
 *   CONTABLE empresa SIMPLIFICADA con perfiles y sucursal.
 *   GESTION  empresa COMPLETA.
 *   Origen LOCAL = empresa de este ERP; REMOTO = servidor propio (URL https); EXTERNO = otro software.
 */
const ESTUDIO_NIVELES_SERVICIO = ['PADRON', 'FISCAL', 'CONTABLE', 'GESTION'];
const ESTUDIO_ORIGENES_DATOS = ['LOCAL', 'REMOTO', 'EXTERNO'];

/**
 * Valida la combinación nivel / origen / URL / empresa vinculada. Devuelve null si es coherente o
 * ['message', 'error'] si no. Normaliza $origen y $url por referencia (PADRON = LOCAL; URL solo en REMOTO).
 */
function estudio_cliente_validar_servicio(string $nivel, string &$origen, ?string &$url, ?int $empresa_vinculada_id): ?array
{
    if (!in_array($nivel, ESTUDIO_NIVELES_SERVICIO, true) || !in_array($origen, ESTUDIO_ORIGENES_DATOS, true)) {
        return ['message' => 'Nivel de servicio u origen de datos inválido.', 'error' => 'VALIDATION_ERROR'];
    }
    if ($nivel === 'PADRON') {
        $origen = 'LOCAL';
    }
    $url = $origen === 'REMOTO' ? trim((string)$url) : null;
    if ($origen === 'REMOTO' && !preg_match('#^https://[^\s]+$#i', (string)$url)) {
        return ['message' => 'Para un cliente con servidor propio indique la URL https:// de su ERP.', 'error' => 'INVALID_URL'];
    }
    $con_empresa = $empresa_vinculada_id !== null && $empresa_vinculada_id > 0;
    if ($origen !== 'LOCAL' && $con_empresa) {
        return ['message' => 'Un cliente con servidor propio o software externo no puede tener una empresa vinculada de este ERP. Quite la empresa vinculada o cambie el origen.', 'error' => 'ORIGEN_CON_EMPRESA'];
    }
    if ($origen === 'LOCAL' && $nivel !== 'PADRON' && !$con_empresa) {
        return ['message' => "El nivel $nivel necesita la empresa del cliente en el ERP.", 'error' => 'REQUIERE_EMPRESA'];
    }
    return null;
}

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

    // Estudio sin tipos (ni activos ni inactivos): se siembran los tres básicos. Ids por autoincremento: el catálogo es
    // por empresa y con ids fijos solo el primer estudio que abría la página los recibía (los demás quedaban sin tipos).
    $st_n = mysqli_prepare($conexion, "SELECT COUNT(*) FROM estudio__clientes_tipos WHERE empresa_id = ?");
    mysqli_stmt_bind_param($st_n, "i", $empresa_id);
    mysqli_stmt_execute($st_n);
    $tiene_tipos = (int)(mysqli_fetch_row(mysqli_stmt_get_result($st_n))[0] ?? 0) > 0;
    mysqli_stmt_close($st_n);
    if (empty($combos['tipos']) && !$tiene_tipos) {
        $st_i = mysqli_prepare($conexion, "INSERT INTO estudio__clientes_tipos (empresa_id, nombre, descripcion, tabla_estado_registro_id) VALUES (?, ?, ?, 1)");
        foreach ([['Empresa / Sociedad', 'Sociedades comerciales, SRL, SA, SAS'],
                  ['Profesional / Autónomo', 'Profesionales independientes y personas humanas'],
                  ['Comercio / Pyme', 'Comercios minoristas y pequeñas empresas']] as [$nombre, $descripcion]) {
            mysqli_stmt_bind_param($st_i, "iss", $empresa_id, $nombre, $descripcion);
            mysqli_stmt_execute($st_i);
        }
        mysqli_stmt_close($st_i);
        error_log("=== estudio_clientes_combos: tipos de cliente sembrados para la empresa $empresa_id ===");

        $stmt_t = mysqli_prepare($conexion, $sql_t);
        mysqli_stmt_bind_param($stmt_t, "i", $empresa_id);
        mysqli_stmt_execute($stmt_t);
        $res_t = mysqli_stmt_get_result($stmt_t);
        while ($r = mysqli_fetch_assoc($res_t)) {
            $combos['tipos'][] = $r;
        }
        mysqli_stmt_close($stmt_t);
    }

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
    $res_emp = mysqli_query($conexion, "SELECT DISTINCT e.empresa_id, e.empresa
        FROM conf__empresas e
        INNER JOIN conf__empresas_vinculaciones v ON v.empresa_origen_id = e.empresa_id
             AND v.empresa_destino_id = " . intval($empresa_id) . "
             AND v.tabla_estado_registro_id = 1 AND v.fecha_desde <= CURDATE()
             AND (v.fecha_hasta IS NULL OR v.fecha_hasta >= CURDATE())
        WHERE e.tabla_estado_registro_id = 1 AND e.empresa_id <> " . intval($empresa_id) . " ORDER BY e.empresa");
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
                   COALESCE(ct.nombre, 'General') AS tipo_cliente_nombre,
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   js.jurisdiccion_nombre AS jurisdiccion_sede_nombre,
                   ur.usuario_nombre AS usuario_r_nombre,
                   ua.usuario_nombre AS usuario_a_nombre,
                   ter.tabla_estado_registro,
                   COALESCE(jur_count.total_jurisdicciones, 0) AS total_jurisdicciones
            FROM estudio__clientes c
            LEFT JOIN estudio__clientes_tipos ct ON ct.cliente_tipo_id = c.cliente_tipo_id
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
    $sql = "SELECT c.*, COALESCE(ct.nombre, 'General') AS tipo_cliente_nombre, cf.condicion_fiscal
            FROM estudio__clientes c
            LEFT JOIN estudio__clientes_tipos ct ON ct.cliente_tipo_id = c.cliente_tipo_id
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
 * Seguridad (F0): el estudio solo puede vincular/leer la empresa ERP de un cliente si ésta le otorgó una
 * vinculación vigente (conf__empresas_vinculaciones: origen = empresa cliente, destino = estudio) con
 * permiso en al menos un área operativa. $area vacío = cualquiera de ellas.
 */
function estudio_cliente_tiene_vinculacion(mysqli $conexion, int $estudio_id, int $empresa_erp_id, string $area = ''): bool
{
    if ($empresa_erp_id <= 0 || $estudio_id <= 0 || $empresa_erp_id === $estudio_id) {
        return false;
    }
    require_once __DIR__ . '/../../core/empresas_vinculaciones_model.php';
    $areas = $area !== '' ? [$area] : ['ventas', 'compras', 'contabilidad', 'impuestos', 'tesoreria'];
    foreach ($areas as $a) {
        if (vinculacionesVerificarPermiso($conexion, $empresa_erp_id, $estudio_id, $a, false)) {
            return true;
        }
    }
    return false;
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

    // Lo que tenía guardado (edición): la vinculación se exige solo si cambia la empresa, y el nivel se compara
    // para saber si hay que ajustar la empresa del cliente.
    $previo = null;
    if ($cliente_id > 0) {
        $st = mysqli_prepare($conexion, "SELECT empresa_vinculada_id, nivel_servicio, origen_datos, remoto_url
                                         FROM estudio__clientes WHERE empresa_id = ? AND cliente_id = ?");
        mysqli_stmt_bind_param($st, "ii", $empresa_id, $cliente_id);
        mysqli_stmt_execute($st);
        $previo = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
        mysqli_stmt_close($st);
        if (!$previo) {
            return ['success' => false, 'message' => 'El cliente no existe.', 'data' => null, 'error' => 'NOT_FOUND'];
        }
    }
    $empresa_previa = $previo && $previo['empresa_vinculada_id'] !== null ? (int)$previo['empresa_vinculada_id'] : null;

    // Nivel de servicio y origen: solo si vienen (la ficha los manda; el asistente los graba aparte).
    $con_servicio = array_key_exists('nivel_servicio', $datos);
    $nivel_servicio = $con_servicio ? strtoupper(trim((string)$datos['nivel_servicio'])) : null;
    $origen_datos = $con_servicio ? strtoupper(trim((string)($datos['origen_datos'] ?? 'LOCAL'))) : '';
    $remoto_url = $con_servicio ? (string)($datos['remoto_url'] ?? '') : null;
    if ($con_servicio) {
        $err = estudio_cliente_validar_servicio($nivel_servicio, $origen_datos, $remoto_url, $empresa_vinculada_id);
        if ($err) {
            return ['success' => false, 'message' => $err['message'], 'data' => null, 'error' => $err['error']];
        }
    }

    if ($empresa_vinculada_id !== null && $empresa_vinculada_id !== $empresa_previa) {
        if (!estudio_cliente_tiene_vinculacion($conexion, $empresa_id, $empresa_vinculada_id)) {
            return ['success' => false, 'message' => 'La empresa seleccionada no otorgó al estudio una vinculación vigente con permisos. Registrela primero en Vinculaciones entre empresas.', 'data' => null, 'error' => 'SIN_VINCULACION'];
        }
        $st = mysqli_prepare($conexion, "SELECT cliente_id FROM estudio__clientes WHERE empresa_id = ? AND empresa_vinculada_id = ? AND cliente_id <> ? AND tabla_estado_registro_id = 1 LIMIT 1");
        mysqli_stmt_bind_param($st, "iii", $empresa_id, $empresa_vinculada_id, $cliente_id);
        mysqli_stmt_execute($st);
        $dup = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if ($dup) {
            return ['success' => false, 'message' => 'Esa empresa ya está vinculada a otro cliente del estudio.', 'data' => null, 'error' => 'EMPRESA_DUPLICADA'];
        }
    }

    if ($cliente_tipo_id <= 0 || empty($razon_social) || empty($cuit) || $condicion_iva_id <= 0) {
        return ['success' => false, 'message' => 'Complete todos los campos obligatorios (*).', 'data' => null, 'error' => 'VALIDATION_ERROR'];
    }

    // Limpieza y cálculo de terminación de CUIT
    $cuit_limpio = preg_replace('/[^0-9]/', '', $cuit);
    if (strlen($cuit_limpio) !== 11) {
        return ['success' => false, 'message' => 'El CUIT ingresado debe poseer 11 dígitos numéricos.', 'data' => null, 'error' => 'INVALID_CUIT'];
    }
    $terminacion_cuit = (int) substr($cuit_limpio, -1);
    $cuit = $cuit_limpio; // el padrón guarda el CUIT sin guiones (como los clientes existentes y conf__empresas)

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
                "issiiiisiiiiisiii",
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
                "iissiiiisiiiiisi",
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

        if ($con_servicio) {
            $st = mysqli_prepare($conexion, "UPDATE estudio__clientes SET nivel_servicio = ?, origen_datos = ?, remoto_url = ?
                                             WHERE empresa_id = ? AND cliente_id = ?");
            mysqli_stmt_bind_param($st, "sssii", $nivel_servicio, $origen_datos, $remoto_url, $empresa_id, $cliente_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => 'Cliente guardado correctamente.',
            'data' => [
                'cliente_id' => $cliente_id,
                'empresa_vinculada_id' => $empresa_vinculada_id,
                'nivel_servicio' => $nivel_servicio,
                // El nivel cambió (o se vinculó otra empresa): el _ajax.php ajusta la empresa del cliente
                'nivel_cambio' => $con_servicio && $empresa_vinculada_id !== null
                    && ($previo === null || $previo['nivel_servicio'] !== $nivel_servicio || $empresa_vinculada_id !== $empresa_previa),
            ],
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

/**
 * Obtener toda la información precargada de una Empresa ERP vinculable
 */
function estudio_cliente_obtener_empresa_erp(mysqli $conexion, int $empresa_erp_id): ?array
{
    $sql = "SELECT e.empresa_id, e.empresa, e.documento_tipo_id, e.documento_numero,
                   e.telefono, e.domicilio, e.email, e.localidad_id,
                   COALESCE(ep.condicion_fiscal_id, e.condicion_fiscal_id) AS condicion_fiscal_id,
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   COALESCE(ep.iibb_regimen_id, e.iibb_regimen_id, 1) AS iibb_regimen_id,
                   reg.iibb_regimen_codigo, reg.iibb_regimen_nombre,
                   COALESCE(ep.jurisdiccion_sede_id, e.jurisdiccion_sede_id, 2) AS jurisdiccion_sede_id,
                   sed.codigo_comarb, sed.jurisdiccion_sede,
                   j.jurisdiccion_id AS jurisdiccion_id_estudio,
                   ep.ingresos_brutos_nro, ep.inicio_actividades, ep.leyenda_fiscal
            FROM conf__empresas e
            LEFT JOIN gestion__empresas_parametros ep ON ep.empresa_parametro_id = (
                SELECT ep2.empresa_parametro_id 
                FROM gestion__empresas_parametros ep2 
                WHERE ep2.empresa_id = e.empresa_id AND ep2.f_hasta IS NULL AND ep2.tabla_estado_registro_id = 1 
                ORDER BY ep2.f_desde DESC, ep2.empresa_parametro_id DESC LIMIT 1
            )
            LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = COALESCE(ep.condicion_fiscal_id, e.condicion_fiscal_id)
            LEFT JOIN gestion__iibb_regimenes reg ON reg.iibb_regimen_id = COALESCE(ep.iibb_regimen_id, e.iibb_regimen_id, 1)
            LEFT JOIN gestion__iibb_jurisdicciones_sedes sed ON sed.jurisdiccion_sede_id = COALESCE(ep.jurisdiccion_sede_id, e.jurisdiccion_sede_id, 2)
            LEFT JOIN gestion__jurisdicciones j ON (j.codigo_externo = sed.codigo_comarb OR (j.provincia_id = sed.provincia_id AND j.jurisdiccion_tipo_id = 2))
            WHERE e.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $empresa_erp_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $empresa = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$empresa) {
        return null;
    }

    // Formatear CUIT si tiene 11 dígitos
    $doc_num = preg_replace('/[^0-9]/', '', (string)($empresa['documento_numero'] ?? ''));
    if (strlen($doc_num) === 11) {
        $empresa['cuit'] = substr($doc_num, 0, 2) . '-' . substr($doc_num, 2, 8) . '-' . substr($doc_num, 10, 1);
    } else {
        $empresa['cuit'] = $doc_num;
    }
    $empresa['cuit_limpio'] = $doc_num;

    // Normalizar ingresos_brutos_tipo ('LOCAL', 'CONVENIO_MULTILATERAL', 'EXENTO')
    $reg_cod = strtoupper(trim((string)($empresa['iibb_regimen_codigo'] ?? '')));
    if ($reg_cod === 'CONVENIO_MULTILATERAL' || intval($empresa['iibb_regimen_id']) === 2) {
        $empresa['ingresos_brutos_tipo'] = 'CONVENIO_MULTILATERAL';
    } elseif ($reg_cod === 'EXENTO' || intval($empresa['iibb_regimen_id']) === 3) {
        $empresa['ingresos_brutos_tipo'] = 'EXENTO';
    } else {
        $empresa['ingresos_brutos_tipo'] = 'LOCAL';
    }

    // Cantidad de empleados en nómina/DDJJ
    $empresa['cantidad_empleados'] = 0;
    $empresa['es_empleador'] = 0;
    $sql_emp = "SELECT COUNT(DISTINCT persona_id) as total FROM sindi__ddjj_empleados WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    $stmt_e = mysqli_prepare($conexion, $sql_emp);
    if ($stmt_e) {
        mysqli_stmt_bind_param($stmt_e, "i", $empresa_erp_id);
        mysqli_stmt_execute($stmt_e);
        $res_e = mysqli_stmt_get_result($stmt_e);
        if ($row_e = mysqli_fetch_assoc($res_e)) {
            $cant = intval($row_e['total'] ?? 0);
            $empresa['cantidad_empleados'] = $cant;
            $empresa['es_empleador'] = ($cant > 0) ? 1 : 0;
        }
        mysqli_stmt_close($stmt_e);
    }

    return $empresa;
}

