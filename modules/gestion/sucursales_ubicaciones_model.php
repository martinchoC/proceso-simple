<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// Funciones de utilidad - más eficientes
function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funciones = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $funciones;
}

function obtenerEstadosRegistro($conexion)
{
    $sql = "SELECT estado_registro_id, estado_registro, codigo_estandar 
            FROM conf__estados_registros WHERE tabla_estado_registro_id = 1 ORDER BY estado_registro";
    $result = mysqli_query($conexion, $sql);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function obtenerBotonAgregar($conexion, $pagina_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'icono_clase' => $funcion['icono_clase'] ?? 'fas fa-plus',
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary'
            ];
        }
    }
    return ['nombre_funcion' => 'Agregar Ubicación', 'icono_clase' => 'fas fa-plus', 'color_clase' => 'btn-primary'];
}

function obtenerSucursalesActivas($conexion, $empresa_idx)
{
    $sql = "SELECT s.sucursal_id, s.sucursal_nombre, l.localidad
            FROM gestion__sucursales s
            LEFT JOIN conf__localidades l ON s.localidad_id = l.localidad_id
            WHERE s.empresa_id = ? AND s.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $data;
}

function obtenerDepositosPorSucursal($conexion, $sucursal_id)
{
    $sql = "SELECT deposito_id, deposito_nombre, codigo, es_principal
            FROM gestion__depositos
            WHERE sucursal_id = ? AND tabla_estado_registro_id = 1
            ORDER BY es_principal DESC, orden ASC, deposito_nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $sucursal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $data;
}

function obtenerSucursalesUbicaciones($conexion, $empresa_idx, $pagina_id, $filters = [])
{
    // Query optimizada con LEFT JOINs y solo campos necesarios
    $sql = "SELECT gu.sucursal_ubicacion_id, gu.sucursal_id, gu.deposito_id, gu.seccion, gu.estanteria, gu.estante, gu.posicion, gu.descripcion, gu.tabla_estado_registro_id,
                   gs.sucursal_nombre, gs.localidad_id,
                   cl.localidad,
                   gd.deposito_nombre, gd.codigo AS deposito_codigo,
                   er.estado_registro, er.codigo_estandar
            FROM gestion__sucursales_ubicaciones gu
            INNER JOIN gestion__sucursales gs ON gu.sucursal_id = gs.sucursal_id
            INNER JOIN gestion__depositos gd ON gu.deposito_id = gd.deposito_id
            LEFT JOIN conf__localidades cl ON gs.localidad_id = cl.localidad_id
            LEFT JOIN conf__estados_registros er ON gu.tabla_estado_registro_id = er.estado_registro_id
            WHERE gs.empresa_id = ?";

    $params = [$empresa_idx];
    $types = "i";

    if (!empty($filters['sucursal'])) {
        $sql .= " AND gu.sucursal_id = ?";
        $params[] = intval($filters['sucursal']);
        $types .= "i";
    }

    if (!empty($filters['busqueda'])) {
        $search = '%' . $filters['busqueda'] . '%';
        $sql .= " AND (gu.seccion LIKE ? OR gu.estanteria LIKE ? OR gu.estante LIKE ? OR gu.posicion LIKE ? OR gu.descripcion LIKE ?)";
        $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
        $types .= "sssss";
    }

    $sql .= " ORDER BY gs.sucursal_nombre, gd.deposito_nombre, gu.seccion, gu.estanteria, gu.estante, gu.posicion";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];

    // Cache de botones por estado para evitar múltiples consultas
    $botonesCache = [];
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    foreach ($funciones as $f) {
        $botonesCache[$f['tabla_estado_registro_origen_id']][] = [
            'nombre_funcion' => $f['nombre_funcion'],
            'accion_js' => $f['accion_js'] ?? strtolower($f['nombre_funcion']),
            'icono_clase' => $f['icono_clase'],
            'color_clase' => $f['color_clase'] ?? 'btn-outline-primary',
            'descripcion' => $f['descripcion']
        ];
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $row['estado_info'] = [
            'estado_registro' => $row['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $row['codigo_estandar'] ?? 'DESCONOCIDO'
        ];
        $row['botones'] = $botonesCache[$row['tabla_estado_registro_id']] ?? [];
        $data[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

function agregarSucursalUbicacion($conexion, $data)
{
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $deposito_id = intval($data['deposito_id'] ?? 0);
    $seccion = trim($data['seccion'] ?? '');
    $estanteria = trim($data['estanteria'] ?? '');
    $estante = trim($data['estante'] ?? '');
    $posicion = trim($data['posicion'] ?? '');
    $descripcion = trim($data['descripcion'] ?? '');
    $estado_registro_id = intval($data['estado_registro_id'] ?? 1);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    // Validaciones básicas
    if ($sucursal_id <= 0 || $deposito_id <= 0 || empty($seccion) || empty($estanteria) || empty($estante) || empty($posicion)) {
        return ['resultado' => false, 'error' => 'Todos los campos obligatorios deben estar completos'];
    }

    // Verificar duplicado
    $sql_check = "SELECT COUNT(*) as total FROM gestion__sucursales_ubicaciones 
                  WHERE sucursal_id = ? AND deposito_id = ? AND seccion = ? AND estanteria = ? AND estante = ? AND posicion = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "iissss", $sucursal_id, $deposito_id, $seccion, $estanteria, $estante, $posicion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe esta ubicación'];
    }

    // Insertar
    $sql = "INSERT INTO gestion__sucursales_ubicaciones 
            (empresa_id, sucursal_id, deposito_id, seccion, estanteria, estante, posicion, descripcion, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iiisssssi", $empresa_idx, $sucursal_id, $deposito_id, $seccion, $estanteria, $estante, $posicion, $descripcion, $estado_registro_id);
    $success = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    return $success ? ['resultado' => true, 'sucursal_ubicacion_id' => $id] : ['resultado' => false, 'error' => 'Error al guardar'];
}

function editarSucursalUbicacion($conexion, $id, $data)
{
    $id = intval($id);
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $deposito_id = intval($data['deposito_id'] ?? 0);
    $seccion = trim($data['seccion'] ?? '');
    $estanteria = trim($data['estanteria'] ?? '');
    $estante = trim($data['estante'] ?? '');
    $posicion = trim($data['posicion'] ?? '');
    $descripcion = trim($data['descripcion'] ?? '');
    $estado_registro_id = intval($data['estado_registro_id'] ?? 1);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if ($id <= 0 || $sucursal_id <= 0 || $deposito_id <= 0 || empty($seccion) || empty($estanteria) || empty($estante) || empty($posicion)) {
        return ['resultado' => false, 'error' => 'Todos los campos obligatorios deben estar completos'];
    }

    // Verificar duplicado (excluyendo el actual)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__sucursales_ubicaciones 
                  WHERE sucursal_id = ? AND deposito_id = ? AND seccion = ? AND estanteria = ? AND estante = ? AND posicion = ?
                  AND sucursal_ubicacion_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "iissssi", $sucursal_id, $deposito_id, $seccion, $estanteria, $estante, $posicion, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otra ubicación con estos datos'];
    }

    $sql = "UPDATE gestion__sucursales_ubicaciones 
            SET sucursal_id = ?, deposito_id = ?, seccion = ?, estanteria = ?, estante = ?, posicion = ?, descripcion = ?, tabla_estado_registro_id = ?
            WHERE sucursal_ubicacion_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "iisssssiii", $sucursal_id, $deposito_id, $seccion, $estanteria, $estante, $posicion, $descripcion, $estado_registro_id, $id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $success ? ['resultado' => true] : ['resultado' => false, 'error' => 'Error al actualizar'];
}

function obtenerSucursalUbicacionPorId($conexion, $id, $empresa_idx)
{
    $sql = "SELECT gu.*, gs.sucursal_nombre, er.estado_registro, er.codigo_estandar,
                   gd.deposito_nombre, gd.codigo AS deposito_codigo
            FROM gestion__sucursales_ubicaciones gu
            INNER JOIN gestion__sucursales gs ON gu.sucursal_id = gs.sucursal_id
            INNER JOIN gestion__depositos gd ON gu.deposito_id = gd.deposito_id
            LEFT JOIN conf__estados_registros er ON gu.tabla_estado_registro_id = er.estado_registro_id
            WHERE gu.sucursal_ubicacion_id = ? AND gs.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $data;
}

function obtenerValoresPorDefecto($conexion, $parent_type, $parent_id, $empresa_idx)
{
    $valores = ['sucursal_id' => 0, 'deposito_id' => 0, 'seccion' => '', 'estanteria' => '', 'estante' => '', 'posicion' => '1A'];
    
    $parts = explode('_', $parent_id);
    
    switch ($parent_type) {
        case 'sucursal':
            $valores['sucursal_id'] = intval($parent_id);
            $depositos = obtenerDepositosPorSucursal($conexion, $valores['sucursal_id']);
            if (!empty($depositos)) {
                foreach ($depositos as $d) {
                    if ($d['es_principal']) { $valores['deposito_id'] = $d['deposito_id']; break; }
                }
                if ($valores['deposito_id'] == 0) $valores['deposito_id'] = $depositos[0]['deposito_id'];
            }
            break;
        case 'deposito':
            $valores['sucursal_id'] = intval($parts[0] ?? 0);
            $valores['deposito_id'] = intval($parts[1] ?? 0);
            break;
        case 'seccion':
            $valores['sucursal_id'] = intval($parts[0] ?? 0);
            $valores['deposito_id'] = intval($parts[1] ?? 0);
            $valores['seccion'] = $parts[2] ?? '';
            break;
        case 'estanteria':
            $valores['sucursal_id'] = intval($parts[0] ?? 0);
            $valores['deposito_id'] = intval($parts[1] ?? 0);
            $valores['seccion'] = $parts[2] ?? '';
            $valores['estanteria'] = $parts[3] ?? '';
            break;
        case 'estante':
            $valores['sucursal_id'] = intval($parts[0] ?? 0);
            $valores['deposito_id'] = intval($parts[1] ?? 0);
            $valores['seccion'] = $parts[2] ?? '';
            $valores['estanteria'] = $parts[3] ?? '';
            $valores['estante'] = $parts[4] ?? '';
            // Calcular próxima posición
            $sql = "SELECT MAX(posicion) as max_pos FROM gestion__sucursales_ubicaciones 
                    WHERE sucursal_id = ? AND deposito_id = ? AND seccion = ? AND estanteria = ? AND estante = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iisss", $valores['sucursal_id'], $valores['deposito_id'], $valores['seccion'], $valores['estanteria'], $valores['estante']);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                if ($row && $row['max_pos']) {
                    preg_match('/(\d+)([A-D])/i', $row['max_pos'], $m);
                    if (count($m) >= 3) {
                        $num = intval($m[1]);
                        $letra = strtoupper($m[2]);
                        $letras = ['A', 'B', 'C', 'D'];
                        $idx = array_search($letra, $letras);
                        if ($idx < 3) $valores['posicion'] = $num . $letras[$idx + 1];
                        else $valores['posicion'] = ($num + 1) . 'A';
                    }
                }
            }
            break;
    }
    return $valores;
}

function ejecutarTransicionEstado($conexion, $sucursal_ubicacion_id, $accion_js, $empresa_idx, $pagina_id)
{
    // Verificar permiso y obtener estado actual
    $sql = "SELECT gu.tabla_estado_registro_id FROM gestion__sucursales_ubicaciones gu
            INNER JOIN gestion__sucursales gs ON gu.sucursal_id = gs.sucursal_id
            WHERE gu.sucursal_ubicacion_id = ? AND gs.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "ii", $sucursal_ubicacion_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$row) return ['success' => false, 'error' => 'Acceso denegado'];

    $estado_actual = $row['tabla_estado_registro_id'];

    // Buscar la función
    $sql = "SELECT tabla_estado_registro_destino_id FROM conf__paginas_funciones
            WHERE pagina_id = ? AND tabla_estado_registro_origen_id = ? AND accion_js = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$funcion) return ['success' => false, 'error' => 'Acción no permitida'];

    $estado_destino = $funcion['tabla_estado_registro_destino_id'];
    if ($estado_destino == $estado_actual) return ['success' => true, 'message' => 'Acción ejecutada'];

    // Actualizar estado
    $sql = "UPDATE gestion__sucursales_ubicaciones SET tabla_estado_registro_id = ? WHERE sucursal_ubicacion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino, $sucursal_ubicacion_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $success ? ['success' => true, 'message' => 'Estado actualizado'] : ['success' => false, 'error' => 'Error al actualizar'];
}
?>