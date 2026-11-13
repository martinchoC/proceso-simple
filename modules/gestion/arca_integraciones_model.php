<?php
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/../../arca/arca_lib.php';
$conexion = $conn;

// ABM de Integraciones ARCA por empresa (gestion__integraciones_empresas) —
// tabla tipo Diccionario (Activo / Inactivo) + pruebas de WSAA / WSFE.
// Mismo motor de botones que productos_tipos_model.php: tabla_estado_registro_id
// guarda el estado_registro_id genérico (1 = Activo, 2 = Inactivo).
//
// Token/Sign NUNCA salen de este archivo: las pruebas devuelven solo
// vencimientos y datos de negocio (ver arca/arca_lib.php).

// Tipos de comprobante habilitados para la prueba de CAE: Facturas A, B y C.
// Notas de crédito/débito exigen comprobantes asociados y quedan fuera de la prueba.
const ARCA_PRUEBA_TIPOS_CAE = [1, 6, 11];

// ✅ Resolver el tabla_id de gestion__integraciones_empresas en conf__tablas (sin hardcodear)
function obtenerTablaIdIntegraciones($conexion)
{
    static $tabla_id = null;
    if ($tabla_id !== null) {
        return $tabla_id;
    }

    $sql = "SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = 'gestion__integraciones_empresas' LIMIT 1";
    $result = mysqli_query($conexion, $sql);
    $fila = $result ? mysqli_fetch_assoc($result) : null;
    $tabla_id = $fila ? intval($fila['tabla_id']) : 0;

    if ($tabla_id === 0) {
        error_log("arca_integraciones: gestion__integraciones_empresas no está registrada en conf__tablas");
    }
    return $tabla_id;
}

// ✅ Listado de integraciones de la empresa, con estado del certificado y del TA
function obtenerIntegracionesEmpresa($conexion, $empresa_idx, $pagina_id, $filtro_estado = '')
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    $tabla_id = obtenerTablaIdIntegraciones($conexion);

    $sql = "SELECT ie.integracion_empresa_id, ie.empresa_id, ie.integracion_ambiente_id, ie.servicio,
                   ie.cuit, ie.certificado_ruta, ie.clave_privada_ruta, ie.observaciones,
                   ie.tabla_estado_registro_id,
                   a.codigo AS ambiente_codigo, a.nombre AS ambiente_nombre, a.activo AS ambiente_activo,
                   a.url_wsaa, a.url_servicio, a.integracion_id,
                   i.nombre AS integracion_nombre,
                   au.fecha_hora_vencimiento AS ta_vencimiento,
                   ter.tabla_estado_registro AS estado_registro
            FROM gestion__integraciones_empresas ie
            INNER JOIN conf__integraciones_ambientes a ON a.integracion_ambiente_id = ie.integracion_ambiente_id
            INNER JOIN conf__integraciones i ON i.integracion_id = a.integracion_id
            LEFT JOIN gestion__integraciones_autenticaciones au
                ON au.empresa_id = ie.empresa_id
               AND au.integracion_ambiente_id = ie.integracion_ambiente_id
               AND au.servicio = ie.servicio
               AND au.activo = 1"
        . (arca_ta_por_integracion($conexion) ? " AND au.integracion_empresa_id = ie.integracion_empresa_id" : "") . "
            LEFT JOIN conf__tablas_estados_registros ter
                ON ter.tabla_id = ? AND ter.estado_registro_id = ie.tabla_estado_registro_id
            WHERE ie.empresa_id = ?";

    $types = "ii";
    $params = [$tabla_id, $empresa_idx];

    if ($filtro_estado !== '' && $filtro_estado !== null) {
        $sql .= " AND ie.tabla_estado_registro_id = ?";
        $types .= "i";
        $params[] = intval($filtro_estado);
    }

    $sql .= " ORDER BY i.nombre, a.codigo, ie.servicio";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando listado de integraciones: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $limite_ta = date('Y-m-d H:i:s', time() + ARCA_TA_MARGEN_SEGUNDOS);
    $en_uso_cae = integracionEnUsoParaCae($conexion, $empresa_idx);
    $delegaciones = contarDelegacionesVigentes($conexion, $empresa_idx);
    $filas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        if (empty($fila['estado_registro'])) {
            $fila['estado_registro'] = $fila['tabla_estado_registro_id'] == 1 ? 'Activo' : 'Inactivo';
        }
        $fila['certificado'] = arca_info_certificado($fila['certificado_ruta']);
        $fila['ta_vigente'] = !empty($fila['ta_vencimiento']) && $fila['ta_vencimiento'] > $limite_ta ? 1 : 0;
        $fila['en_uso_cae'] = intval($fila['integracion_empresa_id']) === $en_uso_cae ? 1 : 0;
        $fila['delegaciones_vigentes'] = $delegaciones[intval($fila['integracion_empresa_id'])] ?? 0;
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $filas[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $filas;
}

// ✅ Estados posibles de la tabla (dropdown de filtro rápido)
function obtenerEstadosIntegraciones($conexion)
{
    $tabla_id = obtenerTablaIdIntegraciones($conexion);

    $sql = "SELECT estado_registro_id, tabla_estado_registro AS estado_registro, es_inicial
            FROM conf__tablas_estados_registros
            WHERE tabla_id = ?
            ORDER BY orden ASC, tabla_estado_registro_id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, "i", $tabla_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $estados = [];
    while ($f = mysqli_fetch_assoc($result)) {
        $estados[] = $f;
    }
    mysqli_stmt_close($stmt);
    return $estados;
}

// ✅ Ambientes activos (combo del ABM). Producción queda inactiva hasta habilitarla.
function obtenerAmbientesActivos($conexion)
{
    $sql = "SELECT a.integracion_ambiente_id, a.codigo, a.nombre, i.nombre AS integracion_nombre, i.codigo AS integracion_codigo
            FROM conf__integraciones_ambientes a
            INNER JOIN conf__integraciones i ON i.integracion_id = a.integracion_id
            WHERE a.activo = 1 AND i.activo = 1
            ORDER BY i.nombre, a.integracion_ambiente_id";
    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }
    $ambientes = [];
    while ($f = mysqli_fetch_assoc($result)) {
        $ambientes[] = $f;
    }
    return $ambientes;
}

// ✅ Puntos de venta de la empresa con código fiscal (sugerencias en las pruebas)
function obtenerPuntosVentaFiscales($conexion, $empresa_idx)
{
    $sql = "SELECT punto_venta_id, nombre, codigo_fiscal, sistema_emision
            FROM gestion__puntos_venta
            WHERE empresa_id = ? AND codigo_fiscal IS NOT NULL AND tabla_estado_registro_id = 1
            ORDER BY codigo_fiscal";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $puntos = [];
    while ($f = mysqli_fetch_assoc($result)) {
        $puntos[] = $f;
    }
    mysqli_stmt_close($stmt);
    return $puntos;
}

// ✅ Tipos de comprobante fiscal activos (códigos ARCA)
function obtenerComprobantesFiscales($conexion)
{
    $sql = "SELECT CAST(codigo AS UNSIGNED) AS codigo, comprobante_fiscal
            FROM gestion__comprobantes_fiscales
            WHERE tabla_estado_registro_id = 1
            ORDER BY codigo";
    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }
    $tipos = [];
    while ($f = mysqli_fetch_assoc($result)) {
        $f['permite_cae'] = in_array(intval($f['codigo']), ARCA_PRUEBA_TIPOS_CAE, true) ? 1 : 0;
        $tipos[] = $f;
    }
    return $tipos;
}

// ✅ Obtener funciones configuradas para la página desde conf__paginas_funciones
function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ?
            AND pf.tabla_estado_registro_id = 1 -- Solo funciones activas
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $funciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $funciones[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $funciones;
}

// ✅ Obtener botones disponibles según el estado actual
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    static $cache = [];
    if (!isset($cache[$pagina_id])) {
        $cache[$pagina_id] = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
    }

    $botones = [];
    foreach ($cache[$pagina_id] as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] != $estado_actual_id) {
            continue;
        }

        // Confirmable solo si la función cambia el estado
        $es_confirmable = ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0;

        $botones[] = [
            'nombre_funcion' => $funcion['nombre_funcion'],
            'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
            'icono_clase' => $funcion['icono_clase'],
            'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
            'bg_clase' => $funcion['bg_clase'] ?? '',
            'text_clase' => $funcion['text_clase'] ?? '',
            'descripcion' => $funcion['descripcion'],
            'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
            'es_confirmable' => $es_confirmable
        ];
    }

    return $botones;
}

// ✅ Obtener botón "Agregar" específico para la página (función con origen 0)
function obtenerBotonAgregar($conexion, $pagina_id)
{
    $funciones = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
    if (permisos_sin_funcion_creacion($funciones)) {
        return null;
    }

    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? 'agregar',
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion']
            ];
        }
    }

    return [
        'nombre_funcion' => 'Agregar Integración',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Estado destino de la función "Agregar" (origen 0) de la página; default 1 (Activo)
function obtenerEstadoInicialIntegraciones($conexion, $pagina_id)
{
    foreach (obtenerFuncionesPagina($conexion, $pagina_id) as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0 && $funcion['accion_js'] === 'agregar') {
            return intval($funcion['tabla_estado_registro_destino_id']);
        }
    }
    return 1;
}

// La función accion_js está habilitada en la página para el estado dado
function funcionPermitidaEnEstado($conexion, $pagina_id, $estado_id, $accion_js)
{
    foreach (permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id)) as $funcion) {
        if ($funcion['accion_js'] === $accion_js && intval($funcion['tabla_estado_registro_origen_id']) === intval($estado_id)) {
            return true;
        }
    }
    return false;
}

// ✅ Obtener una integración por ID (filtrada por empresa)
function obtenerIntegracionEmpresaPorId($conexion, $id, $empresa_idx)
{
    $sql = "SELECT ie.integracion_empresa_id, ie.empresa_id, ie.integracion_ambiente_id, ie.servicio,
                   ie.cuit, ie.certificado_ruta, ie.clave_privada_ruta, ie.observaciones,
                   ie.tabla_estado_registro_id,
                   a.codigo AS ambiente_codigo, a.nombre AS ambiente_nombre
            FROM gestion__integraciones_empresas ie
            INNER JOIN conf__integraciones_ambientes a ON a.integracion_ambiente_id = ie.integracion_ambiente_id
            WHERE ie.integracion_empresa_id = ? AND ie.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $fila ?: null;
}

// Dígito verificador de CUIT (módulo 11)
function cuitValido($cuit)
{
    if (!preg_match('/^\d{11}$/', $cuit)) {
        return false;
    }
    $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $suma += intval($cuit[$i]) * $pesos[$i];
    }
    $verificador = 11 - ($suma % 11);
    if ($verificador === 11) {
        $verificador = 0;
    } elseif ($verificador === 10) {
        $verificador = 9;
    }
    return $verificador === intval($cuit[10]);
}

// Normaliza y valida los datos del formulario. Devuelve [datos, error].
function normalizarDatosIntegracion($conexion, $data, $empresa_idx)
{
    $datos = [
        'integracion_ambiente_id' => intval($data['integracion_ambiente_id'] ?? 0),
        'servicio' => strtolower(trim($data['servicio'] ?? 'wsfe')),
        'cuit' => preg_replace('/\D/', '', $data['cuit'] ?? ''),
        'certificado_ruta' => str_replace('\\', '/', trim($data['certificado_ruta'] ?? '')),
        'clave_privada_ruta' => str_replace('\\', '/', trim($data['clave_privada_ruta'] ?? '')),
        'observaciones' => trim($data['observaciones'] ?? '')
    ];
    $datos['observaciones'] = $datos['observaciones'] === '' ? null : mb_substr($datos['observaciones'], 0, 255);

    if ($datos['integracion_ambiente_id'] <= 0) {
        return [null, 'Debe seleccionar un ambiente'];
    }
    $ambiente_valido = false;
    foreach (obtenerAmbientesActivos($conexion) as $ambiente) {
        if (intval($ambiente['integracion_ambiente_id']) === $datos['integracion_ambiente_id']) {
            $ambiente_valido = true;
        }
    }
    if (!$ambiente_valido) {
        return [null, 'El ambiente seleccionado no está activo'];
    }
    if (!preg_match('/^[a-z0-9_]{2,30}$/', $datos['servicio'])) {
        return [null, 'El servicio no es válido (ej.: wsfe)'];
    }
    if (!cuitValido($datos['cuit'])) {
        return [null, 'El CUIT no es válido'];
    }
    // El certificado de ARCA es de cada empresa: el CUIT tiene que ser el de la empresa
    $cuit_empresa = emp_cuit($conexion, $empresa_idx);
    if ($cuit_empresa === '') {
        return [null, 'La empresa no tiene CUIT cargado (Empresas > documento): no se puede configurar ARCA'];
    }
    if ($datos['cuit'] !== $cuit_empresa) {
        return [null, 'El CUIT de la integración tiene que ser el de la empresa (' . $cuit_empresa . ')'];
    }

    $error = arca_validar_par_certificado($datos['certificado_ruta'], $datos['clave_privada_ruta']);
    if ($error !== null) {
        return [null, $error];
    }
    $certificado = arca_info_certificado($datos['certificado_ruta']);
    if ($certificado['cuit'] !== $datos['cuit']) {
        return [null, 'El certificado fue emitido para el CUIT ' . $certificado['cuit'] . ', no para ' . $datos['cuit']];
    }
    if ($certificado['vencido']) {
        return [null, 'El certificado venció el ' . $certificado['valido_hasta']];
    }

    return [$datos, null];
}

// Integración con la que la vigencia abierta de Parámetros de Empresa (página 111 / 611) pide el CAE,
// si ARCA está habilitado; 0 si no hay. Columna de 20261018_arca_certificado_parametros_01.sql.
function integracionEnUsoParaCae($conexion, $empresa_idx)
{
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'arca_integracion_empresa_id'");
    if (!$res || mysqli_num_rows($res) === 0) {
        return 0;
    }
    $stmt = mysqli_prepare($conexion, "SELECT arca_habilitado, arca_integracion_empresa_id FROM gestion__empresas_parametros
                                       WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1
                                       ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1");
    if (!$stmt) {
        return 0;
    }
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ($row && intval($row['arca_habilitado']) === 1) ? intval($row['arca_integracion_empresa_id']) : 0;
}

// Puede haber varias integraciones por empresa + ambiente + servicio (varios certificados; cuál pide
// el CAE se elige en Parámetros de Empresa), pero no el mismo certificado dos veces.
function validarDuplicadoIntegracion($conexion, $datos, $empresa_idx, $excluir_id = 0)
{
    $sql = "SELECT COUNT(*) AS total FROM gestion__integraciones_empresas
            WHERE empresa_id = ? AND integracion_ambiente_id = ? AND servicio = ? AND certificado_ruta = ?
              AND integracion_empresa_id <> ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 'Error en la consulta';
    }
    mysqli_stmt_bind_param($stmt, "iissi", $empresa_idx, $datos['integracion_ambiente_id'], $datos['servicio'], $datos['certificado_ruta'], $excluir_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return intval($row['total']) > 0 ? 'Ese certificado ya está configurado en ese ambiente para el servicio ' . $datos['servicio'] : null;
}

// ✅ Agregar nueva integración
function agregarIntegracionEmpresa($conexion, $data, $empresa_idx, $pagina_id, $usuario_id)
{
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO agregarIntegracionEmpresa empresa: $empresa_idx ===");

    [$datos, $error] = normalizarDatosIntegracion($conexion, $data, $empresa_idx);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $error = validarDuplicadoIntegracion($conexion, $datos, $empresa_idx);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $estado_inicial = obtenerEstadoInicialIntegraciones($conexion, $pagina_id);
    $usuario_id = $usuario_id ? intval($usuario_id) : null;

    mysqli_begin_transaction($conexion);
    try {
        $sql = "INSERT INTO gestion__integraciones_empresas
                    (empresa_id, integracion_ambiente_id, servicio, cuit, certificado_ruta,
                     clave_privada_ruta, observaciones, usuario_creacion_id, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando el alta: ' . mysqli_error($conexion));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "iisssssii",
            $empresa_idx,
            $datos['integracion_ambiente_id'],
            $datos['servicio'],
            $datos['cuit'],
            $datos['certificado_ruta'],
            $datos['clave_privada_ruta'],
            $datos['observaciones'],
            $usuario_id,
            $estado_inicial
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al insertar: ' . mysqli_stmt_error($stmt));
        }
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN agregarIntegracionEmpresa ID: $nuevo_id ===");
        return ['success' => true, 'data' => ['integracion_empresa_id' => $nuevo_id], 'message' => 'Integración creada correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("agregarIntegracionEmpresa: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al guardar la integración'];
    }
}

// ✅ Editar integración existente (solo de la empresa activa).
// Si cambia el CUIT o el certificado, el TA guardado deja de corresponder y se desactiva.
function editarIntegracionEmpresa($conexion, $id, $data, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO editarIntegracionEmpresa ID: $id ===");

    $actual = obtenerIntegracionEmpresaPorId($conexion, $id, $empresa_idx);
    if (!$actual) {
        return ['success' => false, 'message' => 'Integración no encontrada'];
    }

    [$datos, $error] = normalizarDatosIntegracion($conexion, $data, $empresa_idx);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $error = validarDuplicadoIntegracion($conexion, $datos, $empresa_idx, $id);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $invalida_ta = $actual['cuit'] !== $datos['cuit']
        || $actual['certificado_ruta'] !== $datos['certificado_ruta']
        || intval($actual['integracion_ambiente_id']) !== $datos['integracion_ambiente_id']
        || $actual['servicio'] !== $datos['servicio'];

    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE gestion__integraciones_empresas
                SET integracion_ambiente_id = ?, servicio = ?, cuit = ?, certificado_ruta = ?,
                    clave_privada_ruta = ?, observaciones = ?
                WHERE integracion_empresa_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando la edición: ' . mysqli_error($conexion));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssssii",
            $datos['integracion_ambiente_id'],
            $datos['servicio'],
            $datos['cuit'],
            $datos['certificado_ruta'],
            $datos['clave_privada_ruta'],
            $datos['observaciones'],
            $id,
            $empresa_idx
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al actualizar: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        if ($invalida_ta) {
            // El TA es del certificado: solo el de esta integración (sin la columna, el del ambiente)
            $por_integracion = arca_ta_por_integracion($conexion);
            $sql = "UPDATE gestion__integraciones_autenticaciones SET activo = 0
                    WHERE empresa_id = ? AND integracion_ambiente_id = ? AND servicio = ?"
                . ($por_integracion ? " AND integracion_empresa_id = ?" : "");
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                throw new Exception('Error preparando la baja del TA: ' . mysqli_error($conexion));
            }
            $ambiente_anterior = intval($actual['integracion_ambiente_id']);
            if ($por_integracion) {
                mysqli_stmt_bind_param($stmt, "iisi", $empresa_idx, $ambiente_anterior, $actual['servicio'], $id);
            } else {
                mysqli_stmt_bind_param($stmt, "iis", $empresa_idx, $ambiente_anterior, $actual['servicio']);
            }
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error al desactivar el TA: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conexion);
        error_log("=== FIN editarIntegracionEmpresa ID: $id ===");
        return ['success' => true, 'data' => ['integracion_empresa_id' => $id], 'message' => 'Integración actualizada correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("editarIntegracionEmpresa: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar la integración'];
    }
}

// ---------- Delegación del certificado a otras empresas (gestion__integraciones_delegaciones) ----------
// La titular delega; la empresa destino ve la integración en Parámetros de Empresa y pide el CAE con
// este certificado y su propio CUIT (arca_obtener_configuracion_para_empresa). En ARCA, la empresa
// destino tiene que haber delegado el servicio WSFE en el CUIT del certificado.

// Delegaciones vigentes por integración de la empresa titular: [integracion_empresa_id => cantidad]
function contarDelegacionesVigentes($conexion, $empresa_idx)
{
    if (!arca_delegaciones_existen($conexion)) {
        return [];
    }
    $sql = "SELECT d.integracion_empresa_id, COUNT(*) AS total
            FROM gestion__integraciones_delegaciones d
            WHERE d.empresa_id = ? AND " . arca_sql_delegacion_vigente() . "
            GROUP BY d.integracion_empresa_id";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $totales = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $totales[intval($f['integracion_empresa_id'])] = intval($f['total']);
    }
    mysqli_stmt_close($stmt);
    return $totales;
}

// Valida que la integración sea de la empresa y que "Delegar" esté habilitado en su estado.
// Devuelve [registro, error].
function integracionParaDelegar($conexion, $integracion_empresa_id, $empresa_idx, $pagina_id)
{
    if (!arca_delegaciones_existen($conexion)) {
        return [null, 'Falta correr el script 20261019_arca_certificado_parametros_02_delegaciones.sql'];
    }
    $registro = obtenerIntegracionEmpresaPorId($conexion, $integracion_empresa_id, $empresa_idx);
    if (!$registro) {
        return [null, 'Integración no encontrada'];
    }
    if (!funcionPermitidaEnEstado($conexion, $pagina_id, $registro['tabla_estado_registro_id'], 'delegar')) {
        return [null, 'La delegación no está habilitada para el estado de esta integración'];
    }
    return [$registro, null];
}

// Empresas a las que se puede delegar: las otras empresas activas a las que el usuario tiene acceso
// (no se listan empresas ajenas al usuario), con CUIT cargado.
function obtenerEmpresasDelegables($conexion, $usuario_id, $empresa_idx)
{
    $sql = "SELECT empresa_id, empresa, documento_numero FROM conf__empresas
            WHERE tabla_estado_registro_id = 1 AND empresa_id <> ? AND empresa_id > 1
            ORDER BY empresa";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $acceso_total = defined('USUARIOS_ACCESO_TOTAL') && in_array(intval($usuario_id), USUARIOS_ACCESO_TOTAL, true);
    $empresas = [];
    while ($f = mysqli_fetch_assoc($res)) {
        if (preg_replace('/\D/', '', (string)$f['documento_numero']) === '') {
            continue;
        }
        if ($acceso_total || !empty(usuario_obtener_perfiles_empresa($conexion, intval($usuario_id), intval($f['empresa_id'])))) {
            $empresas[] = $f;
        }
    }
    mysqli_stmt_close($stmt);
    return $empresas;
}

// Delegaciones de una integración (vigentes, futuras, vencidas y revocadas), con si la empresa destino
// la tiene elegida hoy para pedir el CAE.
function obtenerDelegacionesIntegracion($conexion, $integracion_empresa_id, $empresa_idx, $pagina_id)
{
    [$registro, $error] = integracionParaDelegar($conexion, $integracion_empresa_id, $empresa_idx, $pagina_id);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }
    $sql = "SELECT d.integracion_delegacion_id, d.empresa_destino_id, e.empresa AS empresa_destino,
                   e.documento_numero AS cuit_destino, d.fecha_desde, d.fecha_hasta, d.observaciones,
                   d.tabla_estado_registro_id,
                   (" . arca_sql_delegacion_vigente() . ") AS vigente,
                   EXISTS (SELECT 1 FROM gestion__empresas_parametros ep
                           WHERE ep.empresa_id = d.empresa_destino_id AND ep.f_hasta IS NULL
                             AND ep.tabla_estado_registro_id = 1 AND ep.arca_habilitado = 1
                             AND ep.arca_integracion_empresa_id = d.integracion_empresa_id) AS en_uso_cae
            FROM gestion__integraciones_delegaciones d
            INNER JOIN conf__empresas e ON e.empresa_id = d.empresa_destino_id
            WHERE d.integracion_empresa_id = ? AND d.empresa_id = ?
            ORDER BY d.tabla_estado_registro_id, e.empresa";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la consulta'];
    }
    $integracion_empresa_id = intval($integracion_empresa_id);
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "ii", $integracion_empresa_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $filas[] = $f;
    }
    mysqli_stmt_close($stmt);
    return ['success' => true, 'data' => $filas];
}

// Alta o renovación de la delegación a una empresa (una fila por integración + empresa destino).
function guardarDelegacionIntegracion($conexion, $integracion_empresa_id, $data, $empresa_idx, $pagina_id, $usuario_id)
{
    $integracion_empresa_id = intval($integracion_empresa_id);
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO guardarDelegacionIntegracion ID: $integracion_empresa_id empresa: $empresa_idx ===");

    [$registro, $error] = integracionParaDelegar($conexion, $integracion_empresa_id, $empresa_idx, $pagina_id);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }
    if ($registro['servicio'] !== 'wsfe') {
        return ['success' => false, 'message' => 'Solo se delegan integraciones de facturación (WSFE)'];
    }

    $destino = intval($data['empresa_destino_id'] ?? 0);
    $fecha_desde = trim($data['fecha_desde'] ?? '') ?: date('Y-m-d');
    $fecha_hasta = trim($data['fecha_hasta'] ?? '') ?: null;
    $observaciones = trim($data['observaciones'] ?? '');
    $observaciones = $observaciones === '' ? null : mb_substr($observaciones, 0, 255);

    $permitida = false;
    foreach (obtenerEmpresasDelegables($conexion, $usuario_id, $empresa_idx) as $e) {
        if (intval($e['empresa_id']) === $destino) {
            $permitida = true;
        }
    }
    if (!$permitida) {
        return ['success' => false, 'message' => 'Elija una empresa a la que tenga acceso y con CUIT cargado'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde) || ($fecha_hasta !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta))) {
        return ['success' => false, 'message' => 'Fechas inválidas'];
    }
    if ($fecha_hasta !== null && $fecha_hasta < $fecha_desde) {
        return ['success' => false, 'message' => 'La fecha hasta no puede ser anterior a la fecha desde'];
    }

    $usuario_id = $usuario_id ? intval($usuario_id) : null;
    mysqli_begin_transaction($conexion);
    try {
        $sql = "INSERT INTO gestion__integraciones_delegaciones
                    (empresa_id, integracion_empresa_id, empresa_destino_id, fecha_desde, fecha_hasta,
                     observaciones, usuario_creacion_id, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE fecha_desde = VALUES(fecha_desde), fecha_hasta = VALUES(fecha_hasta),
                    observaciones = VALUES(observaciones), tabla_estado_registro_id = 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando la delegación: ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, "iiisssi", $empresa_idx, $integracion_empresa_id, $destino, $fecha_desde, $fecha_hasta, $observaciones, $usuario_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al guardar la delegación: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
        error_log("=== FIN guardarDelegacionIntegracion ID: $integracion_empresa_id destino: $destino ===");
        return ['success' => true, 'message' => 'Certificado delegado. La empresa ya puede elegirlo en Parámetros de Empresa.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("guardarDelegacionIntegracion: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al guardar la delegación'];
    }
}

// Revoca una delegación (queda registrada en estado 2). La empresa destino deja de poder pedir
// el CAE con este certificado desde ese momento.
function revocarDelegacionIntegracion($conexion, $integracion_empresa_id, $delegacion_id, $empresa_idx, $pagina_id)
{
    [$registro, $error] = integracionParaDelegar($conexion, $integracion_empresa_id, $empresa_idx, $pagina_id);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }
    $integracion_empresa_id = intval($integracion_empresa_id);
    $delegacion_id = intval($delegacion_id);
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO revocarDelegacionIntegracion ID: $delegacion_id ===");

    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE gestion__integraciones_delegaciones SET tabla_estado_registro_id = 2
                WHERE integracion_delegacion_id = ? AND integracion_empresa_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando la revocación: ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, "iii", $delegacion_id, $integracion_empresa_id, $empresa_idx);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al revocar: ' . mysqli_stmt_error($stmt));
        }
        $afectadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
        return $afectadas > 0
            ? ['success' => true, 'message' => 'Delegación revocada']
            : ['success' => false, 'message' => 'Delegación no encontrada o ya revocada'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("revocarDelegacionIntegracion: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al revocar la delegación'];
    }
}

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones
function ejecutarTransicionEstado($conexion, $integracion_empresa_id, $accion_js, $empresa_idx, $pagina_id)
{
    $integracion_empresa_id = intval($integracion_empresa_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    error_log("=== INICIO ejecutarTransicionEstado arca_integraciones ID: $integracion_empresa_id acción: $accion_js ===");

    $registro = obtenerIntegracionEmpresaPorId($conexion, $integracion_empresa_id, $empresa_idx);
    if (!$registro) {
        return ['success' => false, 'message' => 'Registro no encontrado'];
    }

    $estado_actual_id = intval($registro['tabla_estado_registro_id']);

    $sql_funcion = "SELECT pf.tabla_estado_registro_destino_id
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ?
                    AND pf.tabla_estado_registro_origen_id = ?
                    AND pf.accion_js = ?
                    AND pf.tabla_estado_registro_id = 1
                    LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt)
        return ['success' => false, 'message' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $funcion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$funcion) {
        return ['success' => false, 'message' => 'Acción no permitida para este estado'];
    }

    $estado_destino_id = intval($funcion['tabla_estado_registro_destino_id']);
    if ($estado_destino_id === $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }
    if ($estado_destino_id !== 1 && integracionEnUsoParaCae($conexion, $empresa_idx) === $integracion_empresa_id) {
        return ['success' => false, 'message' => 'Esta integración es la que pide el CAE en Parámetros de Empresa. Elija otra allí (o desactive ARCA) antes de inactivarla.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        $sql_update = "UPDATE gestion__integraciones_empresas
                       SET tabla_estado_registro_id = ?
                       WHERE integracion_empresa_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            throw new Exception('Error preparando el cambio de estado: ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $integracion_empresa_id, $empresa_idx);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al cambiar estado: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("ejecutarTransicionEstado arca_integraciones: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar el estado'];
    }
}

// ✅ Historial de llamadas de la empresa (sin solicitud/respuesta, que se piden aparte)
function obtenerResultadosIntegraciones($conexion, $empresa_idx, $integracion_ambiente_id = 0, $limite = 500)
{
    $sql = "SELECT r.integracion_resultado_id, r.fecha_hora_solicitud, r.fecha_hora_respuesta,
                   r.resultado, r.codigo, r.descripcion, r.referencia_tipo, r.referencia_id, r.intento,
                   o.codigo AS operacion_codigo, o.nombre AS operacion_nombre,
                   a.nombre AS ambiente_nombre, a.codigo AS ambiente_codigo
            FROM gestion__integraciones_resultados r
            LEFT JOIN conf__integraciones_operaciones o ON o.integracion_operacion_id = r.integracion_operacion_id
            LEFT JOIN conf__integraciones_ambientes a ON a.integracion_ambiente_id = r.integracion_ambiente_id
            WHERE r.empresa_id = ?";
    $types = "i";
    $empresa_idx = intval($empresa_idx);
    $params = [$empresa_idx];

    if (intval($integracion_ambiente_id) > 0) {
        $sql .= " AND r.integracion_ambiente_id = ?";
        $types .= "i";
        $params[] = intval($integracion_ambiente_id);
    }
    $sql .= " ORDER BY r.integracion_resultado_id DESC LIMIT ?";
    $types .= "i";
    $params[] = intval($limite);

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("obtenerResultadosIntegraciones: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($f = mysqli_fetch_assoc($result)) {
        $filas[] = $f;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

// ✅ Detalle técnico de una llamada (solicitud/respuesta ya enmascaradas al guardar;
// se vuelve a enmascarar por si hubiera filas cargadas por otro camino)
function obtenerResultadoDetalle($conexion, $id, $empresa_idx)
{
    $sql = "SELECT r.*, o.nombre AS operacion_nombre, a.nombre AS ambiente_nombre
            FROM gestion__integraciones_resultados r
            LEFT JOIN conf__integraciones_operaciones o ON o.integracion_operacion_id = r.integracion_operacion_id
            LEFT JOIN conf__integraciones_ambientes a ON a.integracion_ambiente_id = r.integracion_ambiente_id
            WHERE r.integracion_resultado_id = ? AND r.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        return null;
    }
    $fila['solicitud'] = arca_ocultar_credenciales($fila['solicitud']);
    $fila['respuesta'] = arca_ocultar_credenciales($fila['respuesta']);
    return $fila;
}

// Datos de la prueba de CAE: Factura A/B al 21% (neto + IVA) o Factura C (sin IVA).
// Devuelve [datos, error].
function normalizarDatosPruebaCae($data)
{
    $cbte_tipo = intval($data['cbte_tipo'] ?? 0);
    $imp_total = round((float)str_replace(',', '.', $data['imp_total'] ?? '0'), 2);
    $doc_tipo = intval($data['doc_tipo'] ?? 99);
    $doc_nro = preg_replace('/\D/', '', $data['doc_nro'] ?? '');

    if (!in_array($cbte_tipo, ARCA_PRUEBA_TIPOS_CAE, true)) {
        return [null, 'La prueba de CAE admite solo Factura A (1), B (6) o C (11)'];
    }
    if (intval($data['pto_vta'] ?? 0) <= 0) {
        return [null, 'El punto de venta es obligatorio'];
    }
    if ($imp_total <= 0) {
        return [null, 'El importe total debe ser mayor a cero'];
    }
    if (!in_array($doc_tipo, [80, 86, 96, 99], true)) {
        return [null, 'Tipo de documento no válido'];
    }
    if ($doc_tipo === 99) {
        $doc_nro = '0';
    } elseif ($doc_nro === '') {
        return [null, 'El número de documento es obligatorio'];
    }
    if ($doc_tipo === 80 && !cuitValido($doc_nro)) {
        return [null, 'El CUIT del receptor no es válido'];
    }

    if ($cbte_tipo === 11) {
        $imp_neto = $imp_total;
        $imp_iva = 0;
    } else {
        $imp_neto = round($imp_total / 1.21, 2);
        $imp_iva = round($imp_total - $imp_neto, 2);
    }

    return [[
        'pto_vta' => intval($data['pto_vta']),
        'cbte_tipo' => $cbte_tipo,
        'concepto' => 1,
        'doc_tipo' => $doc_tipo,
        'doc_nro' => $doc_nro,
        'condicion_iva_receptor_id' => intval($data['condicion_iva_receptor_id'] ?? 5),
        'imp_total' => $imp_total,
        'imp_neto' => $imp_neto,
        'imp_iva' => $imp_iva,
        'alicuota_iva_id' => 5,
        'fecha' => date('Ymd')
    ], null];
}

// ✅ Ejecuta una prueba contra ARCA para la integración indicada.
// Exige que la función 'pruebas' esté habilitada para el estado del registro.
function ejecutarPruebaArca($conexion, $id, $operacion, $data, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO ejecutarPruebaArca ID: $id operación: $operacion ===");

    $cfg = arca_obtener_configuracion($conexion, $id, $empresa_idx);
    if (!$cfg) {
        return ['success' => false, 'message' => 'Integración no encontrada'];
    }
    if (!funcionPermitidaEnEstado($conexion, $pagina_id, $cfg['tabla_estado_registro_id'], 'pruebas')) {
        return ['success' => false, 'message' => 'Las pruebas no están habilitadas para el estado de esta integración'];
    }
    if (intval($cfg['ambiente_activo']) !== 1) {
        return ['success' => false, 'message' => 'El ambiente ' . $cfg['ambiente_nombre'] . ' está inactivo'];
    }

    // Padrón (ws_sr_constancia_inscripcion, arca/arca_padron.php): WSAA y el estado del servidor; el resto es de WSFE
    if ($cfg['servicio'] !== 'wsfe' && !in_array($operacion, ['estado_ta', 'wsaa_login', 'wsaa_importar_ta', 'fe_dummy'], true)) {
        return ['success' => false, 'message' => 'Esa prueba es de facturación electrónica (WSFE); esta integración es del servicio ' . $cfg['servicio']];
    }
    if ($cfg['servicio'] === 'ws_sr_constancia_inscripcion' && $operacion === 'fe_dummy') {
        require_once __DIR__ . '/../../arca/arca_padron.php';
        $r = arca_padron_dummy($conexion, $cfg);
        $operacion = '';
    }

    switch ($operacion) {
        case '':
            break;

        case 'estado_ta':
            return ['success' => true, 'data' => arca_estado_ta($conexion, $cfg)];

        case 'wsaa_login':
            $r = arca_obtener_ta($conexion, $cfg);
            if (!$r['ok']) {
                return ['success' => false, 'message' => $r['error']];
            }
            // Solo vigencia: Token/Sign no salen al navegador.
            return [
                'success' => true,
                'data' => ['reutilizado' => $r['reutilizado'], 'generacion' => $r['generacion'], 'vencimiento' => $r['vencimiento']],
                'message' => $r['reutilizado'] ? 'Se reutilizó el TA vigente de la base' : 'TA nuevo obtenido de WSAA'
            ];

        case 'wsaa_importar_ta':
            $r = arca_importar_ta_respuestas($conexion, $cfg);
            return $r['ok']
                ? ['success' => true, 'data' => $r, 'message' => 'TA importado desde ' . $r['archivo']]
                : ['success' => false, 'message' => $r['error']];

        case 'fe_dummy':
            $r = arca_wsfe_dummy($conexion, $cfg);
            break;

        case 'ptos_venta':
            $r = arca_wsfe_ptos_venta($conexion, $cfg);
            break;

        case 'tipos_tributos':
            $r = arca_wsfe_tipos_tributos($conexion, $cfg);
            break;

        case 'ultimo_autorizado':
            $pto_vta = intval($data['pto_vta'] ?? 0);
            $cbte_tipo = intval($data['cbte_tipo'] ?? 0);
            if ($pto_vta <= 0 || $cbte_tipo <= 0) {
                return ['success' => false, 'message' => 'Indique punto de venta y tipo de comprobante'];
            }
            $r = arca_wsfe_ultimo_autorizado($conexion, $cfg, $pto_vta, $cbte_tipo);
            break;

        case 'comp_consultar':
            $pto_vta = intval($data['pto_vta'] ?? 0);
            $cbte_tipo = intval($data['cbte_tipo'] ?? 0);
            $cbte_nro = intval($data['cbte_nro'] ?? 0);
            if ($pto_vta <= 0 || $cbte_tipo <= 0 || $cbte_nro <= 0) {
                return ['success' => false, 'message' => 'Indique punto de venta, tipo y número de comprobante'];
            }
            $r = arca_wsfe_comp_consultar($conexion, $cfg, $pto_vta, $cbte_tipo, $cbte_nro);
            break;

        case 'cae_solicitar':
            // Un CAE en producción es un comprobante fiscal real: desde esta pantalla, nunca.
            if ($cfg['ambiente_codigo'] !== 'homologacion') {
                return ['success' => false, 'message' => 'La prueba de CAE solo se permite en Homologación'];
            }
            [$datos, $error] = normalizarDatosPruebaCae($data);
            if ($error !== null) {
                return ['success' => false, 'message' => $error];
            }
            $r = arca_wsfe_solicitar_cae($conexion, $cfg, $datos);
            if (!empty($r['timeout'])) {
                $r['error'] .= '. No reintentar a ciegas: consulte el comprobante '
                    . $r['data']['CbteNro'] . ' con "Consultar comprobante" para conciliar.';
            }
            break;

        default:
            return ['success' => false, 'message' => 'Operación de prueba no válida'];
    }

    return [
        'success' => !empty($r['ok']),
        'data' => [
            'resultado' => $r['data'] ?? null,
            'errores' => $r['errores'] ?? [],
            'eventos' => $r['eventos'] ?? [],
            'timeout' => !empty($r['timeout'])
        ],
        'message' => !empty($r['ok']) ? 'Operación ejecutada correctamente' : ($r['error'] ?? 'Error en la operación')
    ];
}
