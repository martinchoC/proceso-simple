<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerFuncionesPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
            AND pf.tabla_estado_registro_id = 1
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

function obtenerInfoEstado($conexion, $estado_registro_id) {
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    if (in_array('estado_registro', $columns)) {
        $sql = "SELECT estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
    } elseif (in_array('nombre_estado', $columns)) {
        $sql = "SELECT nombre_estado as estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
    } elseif (in_array('descripcion', $columns)) {
        $sql = "SELECT descripcion as estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
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

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id) {
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];
    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            $esConfirmable = 0;
            if ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) {
                $esConfirmable = 1;
            }
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => $esConfirmable
            ];
        }
    }
    return $botones;
}

function obtenerBotonAgregar($conexion, $pagina_id) {
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
        'nombre_funcion' => 'Nuevo Ajuste',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicial($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT ter.estado_registro_id
            FROM conf__paginas p
            JOIN conf__tablas_estados_registros ter ON ter.tabla_id = p.tabla_id
            WHERE p.pagina_id = ?
            AND ter.es_inicial = 1
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return 1;
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($fila) return $fila['estado_registro_id'];
    return 1;
}

function ejecutarTransicionEstado($conexion, $stock_ajuste_id, $accion_js, $empresa_idx, $pagina_id) {
    $stock_ajuste_id = intval($stock_ajuste_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT sa.tabla_estado_registro_id
                  FROM gestion__stock_ajustes sa
                  WHERE sa.stock_ajuste_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "i", $stock_ajuste_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ajuste = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$ajuste) return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $ajuste['tabla_estado_registro_id'];

    $sql_funcion = "SELECT pf.* FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ? AND pf.accion_js = ?
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

    $sql_update = "UPDATE gestion__stock_ajustes SET tabla_estado_registro_id = ? WHERE stock_ajuste_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error preparando update: ' . mysqli_error($conexion)];
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $stock_ajuste_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Error actualizando estado: ' . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
    return ['success' => true, 'message' => 'Estado actualizado correctamente'];
}

function obtenerStockAjustes($conexion, $empresa_idx, $pagina_id) {
    $pagina_id = intval($pagina_id);

    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) {
            $estado_column = 'nombre_estado';
        } elseif (in_array('descripcion', $columns)) {
            $estado_column = 'descripcion';
        }
    }

    $sql = "SELECT sa.*, 
                   er.$estado_column as estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   ct.comprobante_tipo,
                   s.sucursal_nombre,
                   d.deposito_nombre
            FROM gestion__stock_ajustes sa
            LEFT JOIN conf__estados_registros er ON sa.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__comprobantes_tipos ct ON sa.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__sucursales s ON sa.sucursal_id = s.sucursal_id AND s.empresa_id = sa.empresa_id
            LEFT JOIN gestion__depositos d ON sa.deposito_id = d.deposito_id AND d.empresa_id = sa.empresa_id
            WHERE sa.empresa_id = ?
            ORDER BY sa.fecha DESC, sa.stock_ajuste_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $color_clase = $fila['color_clase'] ?? 'btn-dark';
        $bg_clase = $fila['bg_clase'] ?? 'bg-dark';
        $text_clase = $fila['text_clase'] ?? 'text-white';

        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => $color_clase,
            'bg_clase' => $bg_clase,
            'text_clase' => $text_clase
        ];

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

function agregarStockAjuste($conexion, $data) {
    error_log("=== INICIO agregarStockAjuste ===");

    if (empty($data['fecha'])) return ['resultado' => false, 'error' => 'La fecha es obligatoria'];
    if (empty($data['deposito_id'])) return ['resultado' => false, 'error' => 'Debe seleccionar un depósito'];
    if (empty($data['comprobante_tipo_id'])) return ['resultado' => false, 'error' => 'Debe seleccionar el tipo de comprobante'];
    if (empty($data['sucursal_id'])) return ['resultado' => false, 'error' => 'Debe seleccionar la sucursal'];
    if (!isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
        return ['resultado' => false, 'error' => 'Debe agregar al menos un producto al detalle'];
    }

    mysqli_begin_transaction($conexion);
    try {
        $estado_inicial = obtenerEstadoInicial($conexion, $data['pagina_idx']);
        if (!$estado_inicial) $estado_inicial = 1;

        $sql = "INSERT INTO gestion__stock_ajustes 
                (empresa_id, sucursal_id, deposito_id, comprobante_tipo_id, fecha, descripcion, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) throw new Exception("Error preparando insert: " . mysqli_error($conexion));

        $empresa_id_val = intval($data['empresa_idx']);
        $sucursal_id_val = intval($data['sucursal_id']);
        $deposito_id_val = intval($data['deposito_id']);
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $fecha_val = $data['fecha'];
        $descripcion_val = $data['descripcion'] ?? '';
        $estado_inicial_val = intval($estado_inicial);

        mysqli_stmt_bind_param($stmt, "iiiissi",
            $empresa_id_val,
            $sucursal_id_val,
            $deposito_id_val,
            $comprobante_tipo_id_val,
            $fecha_val,
            $descripcion_val,
            $estado_inicial_val
        );

        if (!mysqli_stmt_execute($stmt)) throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        $stock_ajuste_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        $detalles_success = insertarDetallesAjuste($conexion, $stock_ajuste_id, $data['empresa_idx'], $data['detalles']);
        if (!$detalles_success) throw new Exception("Error al insertar los detalles");

        mysqli_commit($conexion);
        return ['resultado' => true, 'stock_ajuste_id' => $stock_ajuste_id];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarStockAjuste: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarStockAjuste($conexion, $id, $data) {
    $id = intval($id);
    error_log("=== INICIO editarStockAjuste ID: $id ===");

    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE gestion__stock_ajustes 
                SET sucursal_id = ?, deposito_id = ?, comprobante_tipo_id = ?, fecha = ?, descripcion = ?
                WHERE stock_ajuste_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) throw new Exception("Error preparando update: " . mysqli_error($conexion));

        $sucursal_id_val = intval($data['sucursal_id']);
        $deposito_id_val = intval($data['deposito_id']);
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $fecha_val = $data['fecha'];
        $descripcion_val = $data['descripcion'] ?? '';
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        mysqli_stmt_bind_param($stmt, "iiissii",
            $sucursal_id_val,
            $deposito_id_val,
            $comprobante_tipo_id_val,
            $fecha_val,
            $descripcion_val,
            $id_val,
            $empresa_idx_val
        );

        if (!mysqli_stmt_execute($stmt)) throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $sql_delete = "DELETE FROM gestion__stock_ajustes_detalles WHERE stock_ajuste_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        if (!$stmt_delete) throw new Exception("Error preparando delete de detalles: " . mysqli_error($conexion));
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        if (!mysqli_stmt_execute($stmt_delete)) throw new Exception("Error eliminando detalles existentes: " . mysqli_stmt_error($stmt_delete));
        mysqli_stmt_close($stmt_delete);

        if (isset($data['detalles']) && is_array($data['detalles']) && count($data['detalles']) > 0) {
            $detalles_success = insertarDetallesAjuste($conexion, $id, $empresa_idx_val, $data['detalles']);
            if (!$detalles_success) throw new Exception("Error al insertar los nuevos detalles");
        } else {
            throw new Exception("Debe haber al menos un detalle en el ajuste");
        }

        mysqli_commit($conexion);
        return ['resultado' => true, 'message' => 'Ajuste actualizado correctamente'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarStockAjuste: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function obtenerStockAjustePorId($conexion, $id, $empresa_idx) {
    $id = intval($id);
    $sql = "SELECT sa.*, ct.comprobante_tipo, s.sucursal_nombre, d.deposito_nombre
            FROM gestion__stock_ajustes sa
            LEFT JOIN gestion__comprobantes_tipos ct ON sa.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__sucursales s ON sa.sucursal_id = s.sucursal_id AND s.empresa_id = sa.empresa_id
            LEFT JOIN gestion__depositos d ON sa.deposito_id = d.deposito_id AND d.empresa_id = sa.empresa_id
            WHERE sa.stock_ajuste_id = ? AND sa.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ajuste = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$ajuste) return null;

    $sql_detalles = "SELECT sad.*, p.producto_codigo, p.producto_nombre
                     FROM gestion__stock_ajustes_detalles sad
                     LEFT JOIN gestion__productos p ON sad.producto_id = p.producto_id
                     WHERE sad.stock_ajuste_id = ?
                     ORDER BY sad.stock_ajuste_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt) return $ajuste;
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $detalles = [];
    while ($detalle = mysqli_fetch_assoc($result)) {
        $detalles[] = [
            'stock_ajuste_detalle_id' => $detalle['stock_ajuste_detalle_id'],
            'producto_id' => $detalle['producto_id'],
            'producto_nombre' => ($detalle['producto_codigo'] ?? '') . ' - ' . ($detalle['producto_nombre'] ?? ''),
            'deposito_id' => $detalle['deposito_id'],
            'stock_sistema' => floatval($detalle['stock_sistema']),
            'stock_fisico' => floatval($detalle['stock_fisico']),
            'diferencia' => floatval($detalle['diferencia']),
            'cantidad_ajuste' => floatval($detalle['cantidad_ajuste']),
            'costo_unitario' => floatval($detalle['costo_unitario'] ?? 0),
            'costo_total' => floatval($detalle['costo_total'] ?? 0),
            'observacion' => $detalle['observacion'] ?? ''
        ];
    }
    mysqli_stmt_close($stmt);
    $ajuste['detalles'] = $detalles;
    return $ajuste;
}

function obtenerComprobantesTipos($conexion) {
    $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra 
            FROM gestion__comprobantes_tipos 
            WHERE comprobante_subgrupo_id = 15
            AND tabla_estado_registro_id = 1 
            ORDER BY comprobante_tipo";
    $result = mysqli_query($conexion, $sql);
    if (!$result) return [];
    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    return $tipos;
}

function obtenerSucursalesEmpresa($conexion, $empresa_idx) {
    $sql = "SELECT sucursal_id, sucursal_nombre 
            FROM gestion__sucursales 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1 
            ORDER BY sucursal_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sucursales = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $sucursales[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $sucursales;
}

function obtenerDepositos($conexion, $sucursal_id, $empresa_idx) {
    $sucursal_id = intval($sucursal_id);
    $sql = "SELECT deposito_id, deposito_nombre 
            FROM gestion__depositos 
            WHERE sucursal_id = ? 
            AND empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY deposito_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "ii", $sucursal_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $depositos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $depositos[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $depositos;
}

function buscarProductosConStock($conexion, $empresa_idx, $deposito_id, $q) {
    $deposito_id = intval($deposito_id);
    $q = mysqli_real_escape_string($conexion, $q);
    $search = "%$q%";

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, 
                   COALESCE(ss.stock_actual, 0) as stock_sistema
            FROM gestion__productos p
            LEFT JOIN gestion__stocks ss ON p.producto_id = ss.producto_id AND ss.deposito_id = ?
            WHERE p.empresa_id = ? 
            AND p.tabla_estado_registro_id = 1
            AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ?)
            ORDER BY p.producto_nombre
            LIMIT 20";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "iiss", $deposito_id, $empresa_idx, $search, $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $productos;
}

function insertarDetallesAjuste($conexion, $stock_ajuste_id, $empresa_id, $detalles) {
    error_log("Insertando " . count($detalles) . " detalles para ajuste $stock_ajuste_id");

    if (!is_array($detalles) || count($detalles) === 0) return false;

    foreach ($detalles as $index => $detalle) {
        if (empty($detalle['producto_id'])) {
            error_log("Error: producto_id vacío en detalle $index");
            return false;
        }

        $producto_id = intval($detalle['producto_id']);
        $deposito_id = intval($detalle['deposito_id'] ?? 0);
        $stock_sistema = floatval($detalle['stock_sistema'] ?? 0);
        $stock_fisico = floatval($detalle['stock_fisico'] ?? 0);
        $diferencia = floatval($detalle['diferencia'] ?? 0);
        $cantidad_ajuste = floatval($detalle['cantidad_ajuste'] ?? $diferencia);
        $costo_unitario = floatval($detalle['costo_unitario'] ?? 0);
        $costo_total = floatval($detalle['costo_total'] ?? (abs($cantidad_ajuste) * $costo_unitario));
        $observacion = $detalle['observacion'] ?? '';

        $sql = "INSERT INTO gestion__stock_ajustes_detalles 
                (stock_ajuste_id, producto_id, deposito_id, stock_sistema, stock_fisico, diferencia, 
                 cantidad_ajuste, costo_unitario, costo_total, observacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando insert detalle: " . mysqli_error($conexion));
            return false;
        }

        mysqli_stmt_bind_param($stmt, "iiiddddddd",
            $stock_ajuste_id,
            $producto_id,
            $deposito_id,
            $stock_sistema,
            $stock_fisico,
            $diferencia,
            $cantidad_ajuste,
            $costo_unitario,
            $costo_total,
            $observacion
        );

        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error ejecutando insert detalle $index: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
        $detalle_id = mysqli_insert_id($conexion);
        error_log("Detalle $index insertado con ID: " . $detalle_id);
        mysqli_stmt_close($stmt);
    }
    return true;
}
?>