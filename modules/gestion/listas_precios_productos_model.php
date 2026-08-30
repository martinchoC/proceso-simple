<?php
// VERSION: 2026-08-30-fix-bindparam-v2 (null por referencia + conteo de tipos corregido)
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerListasPreciosProductos($conexion, $filtro_lista = '', $filtro_producto = '', $filtro_marca = '', $filtro_modelo = '', $filtro_submodelo = '')
{
    $sql = "SELECT lpp.*, 
                   lp.lista_precio_nombre as lista_nombre,
                   p.producto_codigo, 
                   p.producto_nombre,
                   lpp.precio_final as precio_unitario,
                   lpp.actualizado_en as f_actualizacion
            FROM gestion__listas_precios_productos lpp
            INNER JOIN gestion__listas_precios lp ON lpp.lista_precio_id = lp.lista_precio_id
            INNER JOIN gestion__productos p ON lpp.producto_id = p.producto_id
            WHERE lpp.tabla_estado_registro_id = 1";

    $params = [];
    $types = '';

    if (!empty($filtro_lista)) {
        $sql .= " AND lpp.lista_precio_id = ?";
        $params[] = intval($filtro_lista);
        $types .= 'i';
    }

    if (!empty($filtro_producto)) {
        $sql .= " AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ?)";
        $params[] = '%' . $filtro_producto . '%';
        $params[] = '%' . $filtro_producto . '%';
        $types .= 'ss';
    }

    // Filtros por compatibilidad
    if (!empty($filtro_marca) || !empty($filtro_modelo) || !empty($filtro_submodelo)) {
        $sql .= " AND EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc
                              WHERE pc.producto_id = p.producto_id
                              AND pc.tabla_estado_registro_id = 1";
        if (!empty($filtro_marca)) {
            $sql .= " AND pc.marca_id = ?";
            $params[] = intval($filtro_marca);
            $types .= 'i';
        }
        if (!empty($filtro_modelo)) {
            $sql .= " AND pc.modelo_id = ?";
            $params[] = intval($filtro_modelo);
            $types .= 'i';
        }
        if (!empty($filtro_submodelo)) {
            $sql .= " AND pc.submodelo_id = ?";
            $params[] = intval($filtro_submodelo);
            $types .= 'i';
        }
        $sql .= ")";
    }

    $sql .= " ORDER BY lp.lista_precio_nombre, p.producto_codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if ($stmt && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    } elseif (!$stmt) {
        return [];
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $data[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

function agregarListaPrecioProducto($conexion, $data)
{
    if (empty($data['lista_precio_id']) || empty($data['producto_id']) || empty($data['precio_unitario'])) {
        return false;
    }

    $lista_precio_id = intval($data['lista_precio_id']);
    $producto_id = intval($data['producto_id']);
    $precio_final = floatval($data['precio_unitario']);
    $ajuste_id = !empty($data['ajuste_id']) ? intval($data['ajuste_id']) : null;
    $empresa_id = 2; // o tomarlo de sesión

    // Verificar duplicado activo
    $sql_check = "SELECT COUNT(*) as existe FROM gestion__listas_precios_productos 
                  WHERE lista_precio_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "ii", $lista_precio_id, $producto_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $existe);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($existe > 0) {
        return false;
    }

    // Insertar registro manual (es_manual = 1)
    $sql = "INSERT INTO gestion__listas_precios_productos 
            (empresa_id, lista_precio_id, producto_id, precio_final, es_manual, precio_manual, f_desde, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, 1, ?, CURDATE(), 1)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiidd", $empresa_id, $lista_precio_id, $producto_id, $precio_final, $precio_final);
    return mysqli_stmt_execute($stmt);
}

function editarListaPrecioProducto($conexion, $id, $data)
{
    if (empty($data['lista_precio_id']) || empty($data['producto_id']) || empty($data['precio_unitario'])) {
        return false;
    }

    $id = intval($id);
    $lista_precio_id = intval($data['lista_precio_id']);
    $producto_id = intval($data['producto_id']);
    $precio_final = floatval($data['precio_unitario']);
    $ajuste_id = !empty($data['ajuste_id']) ? intval($data['ajuste_id']) : null;

    // Verificar duplicado excluyendo el actual
    $sql_check = "SELECT COUNT(*) as existe FROM gestion__listas_precios_productos 
                  WHERE lista_precio_id = ? AND producto_id = ? AND lista_precio_producto_id != ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "iii", $lista_precio_id, $producto_id, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $existe);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($existe > 0) {
        return false;
    }

    $sql = "UPDATE gestion__listas_precios_productos 
            SET lista_precio_id = ?, producto_id = ?, precio_final = ?, es_manual = 1, precio_manual = ?
            WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiddi", $lista_precio_id, $producto_id, $precio_final, $precio_final, $id);
    return mysqli_stmt_execute($stmt);
}

function eliminarListaPrecioProducto($conexion, $id)
{
    $id = intval($id);
    // Desactivación lógica
    $sql = "UPDATE gestion__listas_precios_productos SET tabla_estado_registro_id = 2 WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    return mysqli_stmt_execute($stmt);
}

function obtenerListaPrecioProductoPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT *, precio_final as precio_unitario FROM gestion__listas_precios_productos WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function obtenerListasPrecios($conexion)
{
    $sql = "SELECT lista_precio_id, lista_precio_nombre as nombre
            FROM gestion__listas_precios 
            WHERE tabla_estado_registro_id = 1
            ORDER BY lista_precio_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerProductos($conexion)
{
    $sql = "SELECT producto_id, producto_codigo, producto_nombre 
            FROM gestion__productos 
            WHERE tabla_estado_registro_id = 1
            ORDER BY producto_codigo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
// listas_precios_productos_model.php
// ... (código existente) ...

function importarPreciosDesdeExcel($conexion, $lista_precio_id, $productos_precios, $empresa_id = 2)
{
    // Validar que la lista de precios exista y esté activa
    $sql_check_lista = "SELECT lista_precio_id FROM gestion__listas_precios WHERE lista_precio_id = ? AND tabla_estado_registro_id = 1";
    $stmt_check = mysqli_prepare($conexion, $sql_check_lista);
    mysqli_stmt_bind_param($stmt_check, "i", $lista_precio_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    if (mysqli_num_rows($result_check) == 0) {
        return ['success' => false, 'message' => 'La lista de precios seleccionada no existe o está inactiva.'];
    }
    mysqli_stmt_close($stmt_check);

    // Iterar sobre los productos del archivo Excel.
    // "detalle" acumula UNA fila por cada producto del Excel, con su estado
    // final, para que el front arme un Excel de respuesta en vez de un
    // mensaje de texto gigante.
    $detalle = [];
    $procesados = 0;
    $sin_cambios = 0;
    $no_encontrados_count = 0;
    $errores_count = 0;

    foreach ($productos_precios as $item) {
        $codigo = trim($item['codigo']);
        $precio_nuevo = floatval($item['precio']);

        // Buscar el producto por su código en la base de datos
        $sql_producto = "SELECT producto_id, producto_nombre FROM gestion__productos WHERE producto_codigo = ? AND tabla_estado_registro_id = 1";
        $stmt_prod = mysqli_prepare($conexion, $sql_producto);
        mysqli_stmt_bind_param($stmt_prod, "s", $codigo);
        mysqli_stmt_execute($stmt_prod);
        $result_prod = mysqli_stmt_get_result($stmt_prod);
        $producto = mysqli_fetch_assoc($result_prod);
        mysqli_stmt_close($stmt_prod);

        if (!$producto) {
            $no_encontrados_count++;
            $detalle[] = [
                'codigo' => $codigo,
                'producto_nombre' => '',
                'estado' => 'No encontrado',
                'precio_anterior' => null,
                'precio_nuevo' => $precio_nuevo,
                'detalle' => 'El código no existe en la base de datos. Falta dar de alta el producto.'
            ];
            continue; // Si no existe el producto, lo saltamos
        }

        $producto_id = $producto['producto_id'];
        $fecha_hoy = date('Y-m-d');

        // Buscar el registro activo actual para esta lista y producto
        $sql_get_current = "SELECT lista_precio_producto_id, precio_final, f_desde, f_hasta, 
                                   precio_origen, porcentaje_general_aplicado, importe_general_aplicado,
                                   lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                                   es_manual, precio_manual, observaciones
                            FROM gestion__listas_precios_productos
                            WHERE lista_precio_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1";
        $stmt_get = mysqli_prepare($conexion, $sql_get_current);
        mysqli_stmt_bind_param($stmt_get, "ii", $lista_precio_id, $producto_id);
        mysqli_stmt_execute($stmt_get);
        $result_get = mysqli_stmt_get_result($stmt_get);
        $registro_actual = mysqli_fetch_assoc($result_get);
        mysqli_stmt_close($stmt_get);

        // Si el producto ya tiene un precio activo en esta lista y el precio
        // del Excel es el mismo (con tolerancia de centavos), no hacemos nada.
        if ($registro_actual && abs((float)$registro_actual['precio_final'] - $precio_nuevo) < 0.005) {
            $sin_cambios++;
            $detalle[] = [
                'codigo' => $codigo,
                'producto_nombre' => $producto['producto_nombre'],
                'estado' => 'Sin cambios',
                'precio_anterior' => (float)$registro_actual['precio_final'],
                'precio_nuevo' => $precio_nuevo,
                'detalle' => 'El precio ya estaba actualizado.'
            ];
            continue;
        }

        // Todo lo que puede fallar por fila va en su propio try/catch:
        // así un error real de MySQL (constraint, tipo, NOT NULL) no tira
        // un fatal que corta el resto del lote y rompe el JSON de salida.
        try {
            // Si existe un registro activo con precio distinto, lo pasamos
            // al historial y lo desactivamos antes de cargar el nuevo.
            if ($registro_actual) {
                // Insertar en el historial
                $sql_historial = "INSERT INTO gestion__listas_precios_productos_historial 
                                  (empresa_id, lista_precio_id, lista_precio_producto_id, producto_id, producto_costo_id,
                                   precio_origen, porcentaje_general_aplicado, importe_general_aplicado,
                                   lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                                   es_manual, precio_manual, precio_final, f_desde, f_hasta, observaciones, tabla_estado_registro_id)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt_hist = mysqli_prepare($conexion, $sql_historial);
                if (!$stmt_hist) {
                    throw new Exception('Error al preparar insert de historial: ' . mysqli_error($conexion));
                }
                // mysqli_stmt_bind_param exige variables (pasa todo por
                // referencia): no se puede pasar el literal `null` directo.
                $producto_costo_id = null;
                mysqli_stmt_bind_param($stmt_hist, "iiiiidddiddiddsss", 
                    $empresa_id, 
                    $lista_precio_id, 
                    $registro_actual['lista_precio_producto_id'],
                    $producto_id,
                    $producto_costo_id,
                    $registro_actual['precio_origen'],
                    $registro_actual['porcentaje_general_aplicado'],
                    $registro_actual['importe_general_aplicado'],
                    $registro_actual['lista_precio_regla_id'],
                    $registro_actual['porcentaje_regla_aplicado'],
                    $registro_actual['importe_regla_aplicado'],
                    $registro_actual['es_manual'],
                    $registro_actual['precio_manual'],
                    $registro_actual['precio_final'],
                    $registro_actual['f_desde'],
                    $registro_actual['f_hasta'],
                    $registro_actual['observaciones']
                );
                $result_hist = mysqli_stmt_execute($stmt_hist);
                mysqli_stmt_close($stmt_hist);

                if (!$result_hist) {
                    throw new Exception('Error al guardar historial: ' . mysqli_error($conexion));
                }

                // Desactivar el registro actual
                $sql_update = "UPDATE gestion__listas_precios_productos SET tabla_estado_registro_id = 2 WHERE lista_precio_producto_id = ?";
                $stmt_update = mysqli_prepare($conexion, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "i", $registro_actual['lista_precio_producto_id']);
                $result_update = mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);

                if (!$result_update) {
                    throw new Exception('Error al desactivar el registro anterior: ' . mysqli_error($conexion));
                }
            }

            // --- 2. Insertar el nuevo registro con el precio del Excel ---
            // Creamos un nuevo registro (precio manual)
            $sql_insert = "INSERT INTO gestion__listas_precios_productos 
                           (empresa_id, lista_precio_id, producto_id, precio_final, es_manual, precio_manual, f_desde, tabla_estado_registro_id) 
                           VALUES (?, ?, ?, ?, 1, ?, ?, 1)";
            $stmt_insert = mysqli_prepare($conexion, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "iiidds", $empresa_id, $lista_precio_id, $producto_id, $precio_nuevo, $precio_nuevo, $fecha_hoy);
            $result_insert = mysqli_stmt_execute($stmt_insert);
            mysqli_stmt_close($stmt_insert);

            if (!$result_insert) {
                throw new Exception('Error al insertar nuevo precio: ' . mysqli_error($conexion));
            }

            $procesados++;
            $detalle[] = [
                'codigo' => $codigo,
                'producto_nombre' => $producto['producto_nombre'],
                'estado' => $registro_actual ? 'Actualizado' : 'Cargado (nuevo)',
                'precio_anterior' => $registro_actual ? (float)$registro_actual['precio_final'] : null,
                'precio_nuevo' => $precio_nuevo,
                'detalle' => $registro_actual ? 'Precio anterior pasado a historial.' : 'No tenía precio previo en esta lista.'
            ];

        } catch (Throwable $e) {
            // Captura cualquier error real de MySQL (constraint, tipo de dato,
            // columna NOT NULL, etc.) que en PHP 8.1+ con mysqli_report en modo
            // estricto se lanza como excepción no capturada. Sin este catch,
            // ese error corta TODO el loop y rompe el JSON de salida (por eso
            // el "Unexpected token '<'" en el front).
            $errores_count++;
            $detalle[] = [
                'codigo' => $codigo,
                'producto_nombre' => $producto['producto_nombre'],
                'estado' => 'Error',
                'precio_anterior' => $registro_actual ? (float)$registro_actual['precio_final'] : null,
                'precio_nuevo' => $precio_nuevo,
                'detalle' => $e->getMessage()
            ];
            continue;
        }
    }

    $mensaje = "Importación completada: $procesados actualizados, $sin_cambios sin cambios, $no_encontrados_count no encontrados, $errores_count con error. Ver detalle en el Excel descargado.";

    return [
        'success' => true,
        'message' => $mensaje,
        'procesados' => $procesados,
        'sin_cambios' => $sin_cambios,
        'no_encontrados_count' => $no_encontrados_count,
        'errores_count' => $errores_count,
        'detalle' => $detalle
    ];
}
?>