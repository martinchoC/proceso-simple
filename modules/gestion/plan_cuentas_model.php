<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión del Plan de Cuentas Contables
 * Incluye manejo de jerarquías y estados según conf__paginas_funciones
 */

// ✅ Obtener funciones configuradas para la página
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
    if (!$stmt) return [];

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

// ✅ Obtener información de un estado
function obtenerInfoEstado($conexion, $estado_registro_id)
{
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    if (in_array('estado_registro', $columns)) {
        $sql = "SELECT estado_registro, codigo_estandar 
                FROM conf__estados_registros 
                WHERE estado_registro_id = ?";
    } elseif (in_array('nombre_estado', $columns)) {
        $sql = "SELECT nombre_estado as estado_registro, codigo_estandar 
                FROM conf__estados_registros 
                WHERE estado_registro_id = ?";
    } else {
        return ['estado_registro' => 'Estado ' . $estado_registro_id, 'codigo_estandar' => 'ESTADO_' . $estado_registro_id];
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $info;
}

// ✅ Obtener botones según estado actual
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];

    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0
            ];
        }
    }
    return $botones;
}

// ✅ Obtener botón Agregar
function obtenerBotonAgregar($conexion, $pagina_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);

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
        'nombre_funcion' => 'Agregar Cuenta',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial
function obtenerEstadoInicial($conexion)
{
    $sql = "SELECT estado_registro_id 
            FROM conf__estados_registros 
            WHERE valor_estandar IS NOT NULL
            ORDER BY valor_estandar ASC 
            LIMIT 1";

    $result = mysqli_query($conexion, $sql);
    if (!$result) return 1;

    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

// ✅ Ejecutar transición de estado
function ejecutarTransicionEstado($conexion, $cont_cuenta_id, $accion_js, $empresa_id, $pagina_id)
{
    $cont_cuenta_id = intval($cont_cuenta_id);
    $pagina_id = intval($pagina_id);

    // Verificar que la cuenta exista
    $sql_check = "SELECT cont_cuenta_id, tabla_estado_registro_id 
                  FROM gestion__cont_cuentas 
                  WHERE cont_cuenta_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $cont_cuenta_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cuenta = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$cuenta) return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $cuenta['tabla_estado_registro_id'];

    // Buscar función en conf__paginas_funciones
    $sql_funcion = "SELECT pf.* 
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcion) return ['success' => false, 'error' => 'Acción no permitida para este estado'];

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];

    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    // Actualizar estado
    $sql_update = "UPDATE gestion__cont_cuentas 
                   SET tabla_estado_registro_id = ? 
                   WHERE cont_cuenta_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $cont_cuenta_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener nivel desde cuenta padre (para cálculo automático)
function obtenerNivelDesdePadre($conexion, $cuenta_padre_id)
{
    if (empty($cuenta_padre_id)) return 1;

    $sql = "SELECT nivel FROM gestion__cont_cuentas WHERE cont_cuenta_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return 1;

    mysqli_stmt_bind_param($stmt, "i", $cuenta_padre_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ? ($row['nivel'] + 1) : 1;
}

// ✅ Obtener todas las cuentas (listado plano con información de jerarquía)
function obtenerCuentas($conexion, $empresa_id, $pagina_id)
{
    $empresa_id = intval($empresa_id);
    $pagina_id = intval($pagina_id);

    // Consulta simple sin recursividad para evitar problemas de ordenamiento
    $sql = "SELECT 
                c.*,
                er.estado_registro,
                er.codigo_estandar,
                co.color_clase,
                co.bg_clase,
                co.text_clase
            FROM gestion__cont_cuentas c
            LEFT JOIN conf__estados_registros er ON c.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores co ON er.color_id = co.color_id
            WHERE c.empresa_id = ?
            ORDER BY c.codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Estado info
        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => $fila['color_clase'] ?? 'btn-dark',
            'bg_clase' => $fila['bg_clase'] ?? 'bg-dark',
            'text_clase' => $fila['text_clase'] ?? 'text-white'
        ];

        // Botones según estado
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);

        // Asegurar nivel para display
        $fila['nivel'] = $fila['nivel'] ?? 1;

        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Obtener cuentas para dropdown de padre (con formato jerárquico)
function obtenerCuentasParaSelect($conexion, $empresa_id, $excluir_id = 0)
{
    $empresa_id = intval($empresa_id);
    $excluir_id = intval($excluir_id);

    $sql = "WITH RECURSIVE cuenta_tree AS (
                SELECT 
                    c.cont_cuenta_id,
                    c.codigo,
                    c.nombre,
                    c.naturaleza,
                    c.es_imputable,
                    c.cuenta_padre_id,
                    1 as nivel,
                    CAST(c.codigo AS CHAR(1000)) as ruta
                FROM gestion__cont_cuentas c
                WHERE c.empresa_id = ? AND c.cuenta_padre_id IS NULL
                
                UNION ALL
                
                SELECT 
                    c2.cont_cuenta_id,
                    c2.codigo,
                    c2.nombre,
                    c2.naturaleza,
                    c2.es_imputable,
                    c2.cuenta_padre_id,
                    ct.nivel + 1,
                    CONCAT(ct.ruta, '-', c2.codigo)
                FROM gestion__cont_cuentas c2
                INNER JOIN cuenta_tree ct ON c2.cuenta_padre_id = ct.cont_cuenta_id
                WHERE c2.empresa_id = ?
            )
            SELECT * FROM cuenta_tree
            WHERE cont_cuenta_id != ?
            ORDER BY ruta";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $empresa_id, $excluir_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $cuentas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $cuentas[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $cuentas;
}

// ✅ Agregar nueva cuenta
function agregarCuenta($conexion, $data, $pagina_idx)
{
    $empresa_id = intval($data['empresa_id']);
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $nombre = mysqli_real_escape_string($conexion, trim($data['nombre'] ?? ''));
    $naturaleza = $data['naturaleza'] ?? '';
    $cuenta_padre_id = !empty($data['cuenta_padre_id']) ? intval($data['cuenta_padre_id']) : null;
    $nivel = intval($data['nivel'] ?? 1);
    $orden = intval($data['orden'] ?? 0);
    $es_imputable = intval($data['es_imputable'] ?? 1);

    // Validaciones
    if (empty($codigo) || empty($nombre) || empty($naturaleza)) {
        return ['resultado' => false, 'error' => 'Código, nombre y naturaleza son obligatorios'];
    }

    if (!in_array($naturaleza, ['DEUDORA', 'ACREEDORA'])) {
        return ['resultado' => false, 'error' => 'Naturaleza inválida'];
    }

    // Verificar código único por empresa
    $sql_check = "SELECT cont_cuenta_id FROM gestion__cont_cuentas 
                  WHERE empresa_id = ? AND codigo = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) > 0) {
        return ['resultado' => false, 'error' => 'Ya existe una cuenta con este código para la empresa'];
    }

    // Obtener estado inicial
    $estado_inicial = obtenerEstadoInicial($conexion);

    // Insertar
    $sql = "INSERT INTO gestion__cont_cuentas 
            (empresa_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "isssiiiii", 
        $empresa_id, $codigo, $nombre, $naturaleza, $cuenta_padre_id, $nivel, $orden, $es_imputable, $estado_inicial);

    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $cont_cuenta_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'cont_cuenta_id' => $cont_cuenta_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la cuenta: ' . mysqli_error($conexion)];
    }
}

// ✅ Editar cuenta existente
function editarCuenta($conexion, $id, $data)
{
    $id = intval($id);
    $empresa_id = intval($data['empresa_id']);
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $nombre = mysqli_real_escape_string($conexion, trim($data['nombre'] ?? ''));
    $naturaleza = $data['naturaleza'] ?? '';
    $cuenta_padre_id = !empty($data['cuenta_padre_id']) ? intval($data['cuenta_padre_id']) : null;
    $nivel = intval($data['nivel'] ?? 1);
    $orden = intval($data['orden'] ?? 0);
    $es_imputable = intval($data['es_imputable'] ?? 1);

    // Validaciones
    if (empty($codigo) || empty($nombre) || empty($naturaleza)) {
        return ['resultado' => false, 'error' => 'Código, nombre y naturaleza son obligatorios'];
    }

    if (!in_array($naturaleza, ['DEUDORA', 'ACREEDORA'])) {
        return ['resultado' => false, 'error' => 'Naturaleza inválida'];
    }

    // Verificar que la cuenta exista
    $sql_check = "SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE cont_cuenta_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Cuenta no encontrada'];
    }

    // Verificar código único (excluyendo esta cuenta)
    $sql_duplicate = "SELECT cont_cuenta_id FROM gestion__cont_cuentas 
                      WHERE empresa_id = ? AND codigo = ? AND cont_cuenta_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "isi", $empresa_id, $codigo, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otra cuenta con este código para la empresa'];
    }

    // Actualizar
    $sql = "UPDATE gestion__cont_cuentas 
            SET codigo = ?, nombre = ?, naturaleza = ?, cuenta_padre_id = ?, 
                nivel = ?, orden = ?, es_imputable = ?
            WHERE cont_cuenta_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "sssiiiii", 
        $codigo, $nombre, $naturaleza, $cuenta_padre_id, $nivel, $orden, $es_imputable, $id);

    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la cuenta: ' . mysqli_error($conexion)];
    }
}

// ✅ Obtener cuenta por ID
function obtenerCuentaPorId($conexion, $id, $empresa_id)
{
    $id = intval($id);

    $sql = "SELECT c.*, er.estado_registro
            FROM gestion__cont_cuentas c
            LEFT JOIN conf__estados_registros er ON c.tabla_estado_registro_id = er.estado_registro_id
            WHERE c.cont_cuenta_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cuenta = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $cuenta;
}
?>