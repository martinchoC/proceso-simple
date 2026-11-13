<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
$conexion = $conn;

// ABM de Tipos de Producto (gestion__productos_tipos) — tabla tipo Diccionario
// (Activo / Inactivo). Mismo motor de botones que productos_model.php:
// las acciones salen de conf__paginas_funciones según el estado del registro.
//
// Igual que en gestion__productos, tabla_estado_registro_id guarda el
// estado_registro_id genérico (1 = Activo, 2 = Inactivo), que es contra lo que
// comparan tabla_estado_registro_origen_id / _destino_id de las funciones.

// ✅ Resolver el tabla_id de gestion__productos_tipos en conf__tablas (sin hardcodear)
function obtenerTablaIdProductosTipos($conexion)
{
    static $tabla_id = null;
    if ($tabla_id !== null) {
        return $tabla_id;
    }

    $sql = "SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = 'gestion__productos_tipos' LIMIT 1";
    $result = mysqli_query($conexion, $sql);
    $fila = $result ? mysqli_fetch_assoc($result) : null;
    $tabla_id = $fila ? intval($fila['tabla_id']) : 0;

    if ($tabla_id === 0) {
        error_log("productos_tipos: gestion__productos_tipos no está registrada en conf__tablas");
    }
    return $tabla_id;
}

// ✅ Listado de tipos de producto de la empresa (con cuentas y estado)
function obtenerProductosTipos($conexion, $empresa_idx, $pagina_id, $filtro_estado = '')
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    $tabla_id = obtenerTablaIdProductosTipos($conexion);

    $sql = "SELECT pt.producto_tipo_id, pt.empresa_id, pt.producto_tipo, pt.producto_tipo_codigo,
                   pt.maneja_stock, pt.es_compuesto, pt.afecta_facturacion,
                   pt.tabla_estado_registro_id,
                   pt.cont_cuenta_compra_default_id, pt.cont_cuenta_venta_default_id,
                   pt.cont_cuenta_cmv_default_id,
                   ccc.codigo AS cuenta_compra_codigo, ccc.nombre AS cuenta_compra_nombre,
                   ccv.codigo AS cuenta_venta_codigo, ccv.nombre AS cuenta_venta_nombre,
                   ccm.codigo AS cuenta_cmv_codigo, ccm.nombre AS cuenta_cmv_nombre,
                   ter.tabla_estado_registro AS estado_registro,
                   (SELECT COUNT(*) FROM gestion__productos p
                     WHERE p.producto_tipo_id = pt.producto_tipo_id
                       AND p.empresa_id = pt.empresa_id) AS total_productos
            FROM gestion__productos_tipos pt
            LEFT JOIN gestion__cont_cuentas ccc
                ON ccc.cont_cuenta_id = pt.cont_cuenta_compra_default_id AND ccc.empresa_id = pt.empresa_id
            LEFT JOIN gestion__cont_cuentas ccv
                ON ccv.cont_cuenta_id = pt.cont_cuenta_venta_default_id AND ccv.empresa_id = pt.empresa_id
            LEFT JOIN gestion__cont_cuentas ccm
                ON ccm.cont_cuenta_id = pt.cont_cuenta_cmv_default_id AND ccm.empresa_id = pt.empresa_id
            LEFT JOIN conf__tablas_estados_registros ter
                ON ter.tabla_id = ? AND ter.estado_registro_id = pt.tabla_estado_registro_id
            WHERE pt.empresa_id = ?";

    $types = "ii";
    $params = [$tabla_id, $empresa_idx];

    if ($filtro_estado !== '' && $filtro_estado !== null) {
        $sql .= " AND pt.tabla_estado_registro_id = ?";
        $types .= "i";
        $params[] = intval($filtro_estado);
    }

    $sql .= " ORDER BY pt.producto_tipo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando listado de productos_tipos: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        if (empty($fila['estado_registro'])) {
            $fila['estado_registro'] = $fila['tabla_estado_registro_id'] == 1 ? 'Activo' : 'Inactivo';
        }
        $fila['total_productos'] = intval($fila['total_productos']);
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $tipos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $tipos;
}

// ✅ Estados posibles de la tabla (dropdown de filtro rápido).
// El value es estado_registro_id (1/2), que es lo que guarda la columna.
function obtenerEstadosProductosTipos($conexion)
{
    $tabla_id = obtenerTablaIdProductosTipos($conexion);

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
        'nombre_funcion' => 'Agregar Tipo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Estado destino de la función "Agregar" (origen 0) de la página; default 1 (Activo)
function obtenerEstadoInicialProductosTipos($conexion, $pagina_id)
{
    foreach (obtenerFuncionesPagina($conexion, $pagina_id) as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0 && $funcion['accion_js'] === 'agregar') {
            return intval($funcion['tabla_estado_registro_destino_id']);
        }
    }
    return 1;
}

// ✅ Cuentas contables imputables y activas de la empresa (combos de cuentas default)
function obtenerCuentasContables($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT cont_cuenta_id, codigo, nombre
            FROM gestion__cont_cuentas
            WHERE empresa_id = ?
            AND es_imputable = 1
            AND tabla_estado_registro_id = 1
            ORDER BY codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $cuentas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $cuentas[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $cuentas;
}

// Valida que una cuenta (si viene) exista y sea de la empresa. Devuelve null si es válida,
// o el mensaje de error.
function validarCuentaEmpresa($conexion, $cont_cuenta_id, $empresa_idx, $etiqueta)
{
    if ($cont_cuenta_id === null) {
        return null;
    }

    $sql = "SELECT COUNT(*) AS total FROM gestion__cont_cuentas
            WHERE cont_cuenta_id = ? AND empresa_id = ? AND es_imputable = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 'Error en la consulta';
    }
    mysqli_stmt_bind_param($stmt, "ii", $cont_cuenta_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return intval($row['total']) > 0 ? null : "La cuenta de $etiqueta no es válida para esta empresa";
}

// Normaliza y valida los datos del formulario. Devuelve [datos, error].
function normalizarDatosProductoTipo($conexion, $data, $empresa_idx)
{
    $datos = [
        'producto_tipo' => trim($data['producto_tipo'] ?? ''),
        'producto_tipo_codigo' => strtoupper(trim($data['producto_tipo_codigo'] ?? '')),
        'maneja_stock' => !empty($data['maneja_stock']) ? 1 : 0,
        'es_compuesto' => !empty($data['es_compuesto']) ? 1 : 0,
        'afecta_facturacion' => !empty($data['afecta_facturacion']) ? 1 : 0,
        'cont_cuenta_compra_default_id' => !empty($data['cont_cuenta_compra_default_id']) ? intval($data['cont_cuenta_compra_default_id']) : null,
        'cont_cuenta_venta_default_id' => !empty($data['cont_cuenta_venta_default_id']) ? intval($data['cont_cuenta_venta_default_id']) : null,
        'cont_cuenta_cmv_default_id' => !empty($data['cont_cuenta_cmv_default_id']) ? intval($data['cont_cuenta_cmv_default_id']) : null,
    ];

    if ($datos['producto_tipo_codigo'] === '') {
        return [null, 'El código es obligatorio'];
    }
    if ($datos['producto_tipo'] === '') {
        return [null, 'El nombre del tipo es obligatorio'];
    }
    if (mb_strlen($datos['producto_tipo_codigo']) > 20) {
        return [null, 'El código no puede exceder los 20 caracteres'];
    }
    if (mb_strlen($datos['producto_tipo']) > 100) {
        return [null, 'El nombre no puede exceder los 100 caracteres'];
    }

    $cuentas = [
        'cont_cuenta_compra_default_id' => 'compra',
        'cont_cuenta_venta_default_id' => 'venta',
        'cont_cuenta_cmv_default_id' => 'CMV',
    ];
    foreach ($cuentas as $campo => $etiqueta) {
        $error = validarCuentaEmpresa($conexion, $datos[$campo], $empresa_idx, $etiqueta);
        if ($error !== null) {
            return [null, $error];
        }
    }

    return [$datos, null];
}

// Duplicados: el índice UNIQUE de producto_tipo_codigo es GLOBAL (no por empresa),
// así que el código se valida contra todas las empresas; el nombre, dentro de la empresa.
function validarDuplicadoProductoTipo($conexion, $datos, $empresa_idx, $excluir_id = 0)
{
    // Código único por empresa (uk_productos_tipos_empresa_codigo)
    $sql = "SELECT COUNT(*) AS total FROM gestion__productos_tipos
            WHERE producto_tipo_codigo = ? AND empresa_id = ? AND producto_tipo_id <> ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 'Error en la consulta';
    }
    mysqli_stmt_bind_param($stmt, "sii", $datos['producto_tipo_codigo'], $empresa_idx, $excluir_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (intval($row['total']) > 0) {
        return 'Ya existe un tipo de producto con el código "' . $datos['producto_tipo_codigo'] . '"';
    }

    $sql = "SELECT COUNT(*) AS total FROM gestion__productos_tipos
            WHERE producto_tipo = ? AND empresa_id = ? AND producto_tipo_id <> ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 'Error en la consulta';
    }
    mysqli_stmt_bind_param($stmt, "sii", $datos['producto_tipo'], $empresa_idx, $excluir_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (intval($row['total']) > 0) {
        return 'Ya existe un tipo de producto con el nombre "' . $datos['producto_tipo'] . '"';
    }

    return null;
}

// ✅ Agregar nuevo tipo de producto
function agregarProductoTipo($conexion, $data, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO agregarProductoTipo empresa: $empresa_idx ===");

    [$datos, $error] = normalizarDatosProductoTipo($conexion, $data, $empresa_idx);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $error = validarDuplicadoProductoTipo($conexion, $datos, $empresa_idx);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $estado_inicial = obtenerEstadoInicialProductosTipos($conexion, $pagina_id);

    mysqli_begin_transaction($conexion);
    try {
        $sql = "INSERT INTO gestion__productos_tipos
                    (empresa_id, producto_tipo, producto_tipo_codigo, maneja_stock, es_compuesto,
                     afecta_facturacion, tabla_estado_registro_id,
                     cont_cuenta_compra_default_id, cont_cuenta_venta_default_id, cont_cuenta_cmv_default_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando el alta: ' . mysqli_error($conexion));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "issiiiiiii",
            $empresa_idx,
            $datos['producto_tipo'],
            $datos['producto_tipo_codigo'],
            $datos['maneja_stock'],
            $datos['es_compuesto'],
            $datos['afecta_facturacion'],
            $estado_inicial,
            $datos['cont_cuenta_compra_default_id'],
            $datos['cont_cuenta_venta_default_id'],
            $datos['cont_cuenta_cmv_default_id']
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al insertar: ' . mysqli_stmt_error($stmt));
        }
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN agregarProductoTipo ID: $nuevo_id ===");
        return ['success' => true, 'data' => ['producto_tipo_id' => $nuevo_id], 'message' => 'Tipo de producto creado correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("agregarProductoTipo: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al guardar el tipo de producto'];
    }
}

// ✅ Editar tipo de producto existente (solo de la empresa activa)
function editarProductoTipo($conexion, $id, $data, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO editarProductoTipo ID: $id ===");

    $actual = obtenerProductoTipoPorId($conexion, $id, $empresa_idx);
    if (!$actual) {
        return ['success' => false, 'message' => 'Tipo de producto no encontrado'];
    }

    [$datos, $error] = normalizarDatosProductoTipo($conexion, $data, $empresa_idx);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $error = validarDuplicadoProductoTipo($conexion, $datos, $empresa_idx, $id);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE gestion__productos_tipos
                SET producto_tipo = ?, producto_tipo_codigo = ?, maneja_stock = ?, es_compuesto = ?,
                    afecta_facturacion = ?, cont_cuenta_compra_default_id = ?,
                    cont_cuenta_venta_default_id = ?, cont_cuenta_cmv_default_id = ?
                WHERE producto_tipo_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando la edición: ' . mysqli_error($conexion));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssiiiiiiii",
            $datos['producto_tipo'],
            $datos['producto_tipo_codigo'],
            $datos['maneja_stock'],
            $datos['es_compuesto'],
            $datos['afecta_facturacion'],
            $datos['cont_cuenta_compra_default_id'],
            $datos['cont_cuenta_venta_default_id'],
            $datos['cont_cuenta_cmv_default_id'],
            $id,
            $empresa_idx
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al actualizar: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN editarProductoTipo ID: $id ===");
        return ['success' => true, 'data' => ['producto_tipo_id' => $id], 'message' => 'Tipo de producto actualizado correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("editarProductoTipo: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar el tipo de producto'];
    }
}

// ✅ Obtener un tipo de producto por ID (filtrado por empresa)
function obtenerProductoTipoPorId($conexion, $id, $empresa_idx)
{
    $sql = "SELECT producto_tipo_id, empresa_id, producto_tipo, producto_tipo_codigo,
                   maneja_stock, es_compuesto, afecta_facturacion, tabla_estado_registro_id,
                   cont_cuenta_compra_default_id, cont_cuenta_venta_default_id, cont_cuenta_cmv_default_id
            FROM gestion__productos_tipos
            WHERE producto_tipo_id = ? AND empresa_id = ?";

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

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones
function ejecutarTransicionEstado($conexion, $producto_tipo_id, $accion_js, $empresa_idx, $pagina_id)
{
    $producto_tipo_id = intval($producto_tipo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    error_log("=== INICIO ejecutarTransicionEstado productos_tipos ID: $producto_tipo_id acción: $accion_js ===");

    $tipo = obtenerProductoTipoPorId($conexion, $producto_tipo_id, $empresa_idx);
    if (!$tipo) {
        return ['success' => false, 'message' => 'Registro no encontrado'];
    }

    $estado_actual_id = intval($tipo['tabla_estado_registro_id']);

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

    mysqli_begin_transaction($conexion);
    try {
        $sql_update = "UPDATE gestion__productos_tipos
                       SET tabla_estado_registro_id = ?
                       WHERE producto_tipo_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            throw new Exception('Error preparando el cambio de estado: ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $producto_tipo_id, $empresa_idx);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al cambiar estado: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("ejecutarTransicionEstado productos_tipos: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar el estado'];
    }
}
