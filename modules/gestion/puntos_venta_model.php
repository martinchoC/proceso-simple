<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ?
            AND pf.tabla_estado_registro_id = 1
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
    } elseif (in_array('descripcion', $columns)) {
        $sql = "SELECT descripcion as estado_registro, codigo_estandar
                FROM conf__estados_registros
                WHERE estado_registro_id = ?";
    } else {
        return [
            'estado_registro' => 'Estado ' . $estado_registro_id,
            'codigo_estandar' => 'ESTADO_' . $estado_registro_id
        ];
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "i", $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $info = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $info;
}

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];

    foreach ($funciones as $funcion) {
        // Incluir botones donde el origen coincide con el estado actual
        // O donde el origen es 0 (botón agregar, pero eso se maneja aparte)
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {

            // Determinar si es confirmable (cambia de estado)
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
        'nombre_funcion' => 'Nuevo Punto',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicial($conexion)
{
    $sql = "SELECT estado_registro_id
            FROM conf__estados_registros
            WHERE valor_estandar IS NOT NULL
            ORDER BY valor_estandar ASC
            LIMIT 1";

    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return 1;
    }

    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

function ejecutarTransicionEstado($conexion, $punto_venta_id, $accion_js, $empresa_idx, $pagina_id)
{
    $punto_venta_id = intval($punto_venta_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT punto_venta_id, tabla_estado_registro_id
                  FROM gestion__puntos_venta
                  WHERE punto_venta_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $punto_venta_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $punto = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$punto)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $punto['tabla_estado_registro_id'];

    $sql_funcion = "SELECT pf.*
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ?
                    AND pf.tabla_estado_registro_origen_id = ?
                    AND pf.accion_js = ?
                    LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcion)
        return ['success' => false, 'error' => 'Acción no permitida para este estado'];

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];

    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    $sql_update = "UPDATE gestion__puntos_venta
                   SET tabla_estado_registro_id = ?
                   WHERE punto_venta_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $punto_venta_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

function obtenerPuntosVenta($conexion, $empresa_idx, $pagina_id)
{
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

    $sql = "SELECT pv.*,
                   e.empresa,
                   s.sucursal_nombre,
                   gb.boca_nombre,
                   er.$estado_column as estado_registro,
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__puntos_venta pv
            LEFT JOIN conf__empresas e ON pv.empresa_id = e.empresa_id
            LEFT JOIN gestion__sucursales s ON pv.sucursal_id = s.sucursal_id
            INNER JOIN gestion__bocas gb ON pv.boca_id = gb.boca_id
            LEFT JOIN conf__estados_registros er ON pv.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE pv.empresa_id = ?
            ORDER BY s.sucursal_nombre, pv.nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $color_clase = $fila['color_clase'] ?? 'btn-dark';
        $bg_clase = $fila['bg_clase'] ?? 'bg-dark';
        $text_clase = $fila['text_clase'] ?? 'text-white';

        $fila['empresa_nombre'] = $fila['empresa'] ?? 'Sin empresa';
        $fila['sucursal_nombre'] = $fila['sucursal_nombre'] ?? 'Sin sucursal';
        $fila['es_web'] = intval($fila['es_web'] ?? 0);

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

function agregarPuntoVenta($conexion, $data)
{
    error_log("=== INICIO agregarPuntoVenta ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (!$conexion) {
        error_log("Error: Conexión a BD no disponible");
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }

    // Validaciones básicas
    if (empty($data['sucursal_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una sucursal'];
    }
    if (empty($data['boca_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una boca'];
    }
    if (empty($data['nombre'])) {
        return ['resultado' => false, 'error' => 'El nombre es obligatorio'];
    }

    mysqli_begin_transaction($conexion);

    try {
        // Verificar duplicados por sucursal
        $sql_check = "SELECT COUNT(*) as total FROM gestion__puntos_venta
                      WHERE sucursal_id = ? AND nombre = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) {
            throw new Exception("Error preparando consulta duplicados: " . mysqli_error($conexion));
        }

        $sucursal_id_check = intval($data['sucursal_id'] ?? 0);
        $nombre_check = trim($data['nombre']);
        $empresa_idx_check = intval($data['empresa_idx'] ?? 0);

        mysqli_stmt_bind_param($stmt, "isi", $sucursal_id_check, $nombre_check, $empresa_idx_check);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['total'] > 0) {
            throw new Exception('Ya existe un punto de venta con este nombre en la sucursal seleccionada');
        }

        // Verificar código fiscal duplicado (si se proporcionó)
        if (!empty($data['codigo_fiscal'])) {
            $sql_check_fiscal = "SELECT COUNT(*) as total FROM gestion__puntos_venta
                                 WHERE codigo_fiscal = ? AND empresa_id = ?";
            $stmt_fiscal = mysqli_prepare($conexion, $sql_check_fiscal);
            if ($stmt_fiscal) {
                $codigo_fiscal_check = intval($data['codigo_fiscal']);
                mysqli_stmt_bind_param($stmt_fiscal, "ii", $codigo_fiscal_check, $empresa_idx_check);
                mysqli_stmt_execute($stmt_fiscal);
                $result_fiscal = mysqli_stmt_get_result($stmt_fiscal);
                $row_fiscal = mysqli_fetch_assoc($result_fiscal);
                mysqli_stmt_close($stmt_fiscal);

                if ($row_fiscal['total'] > 0) {
                    throw new Exception('Ya existe un punto de venta con este código fiscal');
                }
            }
        }

        // Validar que la boca pertenezca a la sucursal elegida (siempre, ya es obligatoria)
        $boca_id_val = intval($data['boca_id']);
        validarBocaPerteneceASucursal($conexion, $boca_id_val, $sucursal_id_check, $empresa_idx_check);

        // Obtener estado inicial
        $estado_inicial = obtenerEstadoInicial($conexion);
        if (!$estado_inicial) {
            $estado_inicial = 1;
        }

        // Insertar punto de venta
        $sql = "INSERT INTO gestion__puntos_venta
                (empresa_id, sucursal_id, boca_id, nombre, descripcion, codigo_fiscal, es_web, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert: " . mysqli_error($conexion));
        }

        $empresa_id_val = intval($data['empresa_idx']);
        $sucursal_id_val = intval($data['sucursal_id']);
        $nombre_val = trim($data['nombre']);
        $descripcion_val = isset($data['descripcion']) ? trim($data['descripcion']) : null;
        $codigo_fiscal_val = isset($data['codigo_fiscal']) && $data['codigo_fiscal'] !== '' ? intval($data['codigo_fiscal']) : null;
        $es_web_val = !empty($data['es_web']) ? 1 : 0;
        $estado_val = $estado_inicial;

        mysqli_stmt_bind_param($stmt, "iiissiii",
            $empresa_id_val,
            $sucursal_id_val,
            $boca_id_val,
            $nombre_val,
            $descripcion_val,
            $codigo_fiscal_val,
            $es_web_val,
            $estado_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        }

        $punto_venta_id = mysqli_insert_id($conexion);
        error_log("Punto de venta creado con ID: " . $punto_venta_id);
        mysqli_stmt_close($stmt);

        // Guardar tipos de comprobante habilitados para este PV
        $comprobantes = $data['comprobantes'] ?? [];
        guardarComprobantesPuntoVenta($conexion, $punto_venta_id, $empresa_id_val, $comprobantes);

        mysqli_commit($conexion);
        error_log("=== FIN agregarPuntoVenta - ÉXITO ===");
        return ['resultado' => true, 'punto_venta_id' => $punto_venta_id];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarPuntoVenta: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarPuntoVenta($conexion, $id, $data)
{
    $id = intval($id);

    error_log("=== INICIO editarPuntoVenta ID: $id ===");
    error_log("Datos recibidos en función: " . print_r($data, true));

    mysqli_begin_transaction($conexion);

    try {
        // Verificar duplicados (excluyendo el registro actual)
        $sql_check = "SELECT COUNT(*) as total FROM gestion__puntos_venta
                      WHERE sucursal_id = ? AND nombre = ? AND empresa_id = ? AND punto_venta_id != ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) {
            throw new Exception("Error preparando consulta duplicados: " . mysqli_error($conexion));
        }

        $sucursal_id_check = intval($data['sucursal_id'] ?? 0);
        $nombre_check = trim($data['nombre']);
        $empresa_idx_check = intval($data['empresa_idx'] ?? 0);

        mysqli_stmt_bind_param($stmt, "isii", $sucursal_id_check, $nombre_check, $empresa_idx_check, $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['total'] > 0) {
            throw new Exception('Ya existe otro punto de venta con este nombre en la sucursal seleccionada');
        }

        // Verificar código fiscal duplicado (si se proporcionó, excluyendo el actual)
        if (!empty($data['codigo_fiscal'])) {
            $sql_check_fiscal = "SELECT COUNT(*) as total FROM gestion__puntos_venta
                                 WHERE codigo_fiscal = ? AND empresa_id = ? AND punto_venta_id != ?";
            $stmt_fiscal = mysqli_prepare($conexion, $sql_check_fiscal);
            if ($stmt_fiscal) {
                $codigo_fiscal_check = intval($data['codigo_fiscal']);
                mysqli_stmt_bind_param($stmt_fiscal, "iii", $codigo_fiscal_check, $empresa_idx_check, $id);
                mysqli_stmt_execute($stmt_fiscal);
                $result_fiscal = mysqli_stmt_get_result($stmt_fiscal);
                $row_fiscal = mysqli_fetch_assoc($result_fiscal);
                mysqli_stmt_close($stmt_fiscal);

                if ($row_fiscal['total'] > 0) {
                    throw new Exception('Ya existe otro punto de venta con este código fiscal');
                }
            }
        }

        // Validar que la boca pertenezca a la sucursal elegida (siempre, ya es obligatoria)
        if (empty($data['boca_id'])) {
            throw new Exception('Debe seleccionar una boca');
        }
        $boca_id_val = intval($data['boca_id']);
        validarBocaPerteneceASucursal($conexion, $boca_id_val, $sucursal_id_check, $empresa_idx_check);

        // Actualizar punto de venta (NO se actualiza tabla_estado_registro_id porque eso se maneja con acciones)
        $sql = "UPDATE gestion__puntos_venta
                SET sucursal_id = ?,
                    boca_id = ?,
                    nombre = ?,
                    descripcion = ?,
                    codigo_fiscal = ?,
                    es_web = ?
                WHERE punto_venta_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        $sucursal_id_val = intval($data['sucursal_id']);
        $nombre_val = trim($data['nombre']);
        $descripcion_val = isset($data['descripcion']) ? trim($data['descripcion']) : null;
        $codigo_fiscal_val = isset($data['codigo_fiscal']) && $data['codigo_fiscal'] !== '' ? intval($data['codigo_fiscal']) : null;
        $es_web_val = !empty($data['es_web']) ? 1 : 0;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        mysqli_stmt_bind_param($stmt, "iissiiii",
            $sucursal_id_val,
            $boca_id_val,
            $nombre_val,
            $descripcion_val,
            $codigo_fiscal_val,
            $es_web_val,
            $id_val,
            $empresa_idx_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }

        $affected_rows = mysqli_stmt_affected_rows($stmt);
        error_log("Filas afectadas en update: " . $affected_rows);
        mysqli_stmt_close($stmt);

        // Guardar tipos de comprobante habilitados para este PV
        $comprobantes = $data['comprobantes'] ?? [];
        guardarComprobantesPuntoVenta($conexion, $id, $empresa_idx_val, $comprobantes);

        mysqli_commit($conexion);
        error_log("=== FIN editarPuntoVenta - ÉXITO ===");
        return ['resultado' => true, 'message' => 'Punto de venta actualizado correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarPuntoVenta: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

// Valida que la boca elegida pertenezca a la misma sucursal del punto de
// venta (regla de negocio que la FK simple no puede expresar). Lanza
// Exception si no se cumple, para que agregar/editar hagan rollback.
function validarBocaPerteneceASucursal($conexion, $boca_id, $sucursal_id, $empresa_idx)
{
    $sql = "SELECT sucursal_id FROM gestion__bocas WHERE boca_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception("Error validando boca: " . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "ii", $boca_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $boca = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$boca) {
        throw new Exception('La boca seleccionada no existe');
    }
    if (intval($boca['sucursal_id']) !== intval($sucursal_id)) {
        throw new Exception('La boca seleccionada no pertenece a la sucursal elegida');
    }
}

// Bocas de una sucursal para el selector del PV (obligatorio elegir una):
// a diferencia del combo de ubicaciones físicas de stock, acá NO se
// filtra por es_deposito, porque un punto de venta puede colgar tanto
// de una boca comercial (ej. "Caja mostrador") como de una boca de
// depósito (ej. "Remitos").
function obtenerBocasPorSucursal($conexion, $sucursal_id, $empresa_idx)
{
    $sql = "SELECT boca_id, boca_nombre, codigo, es_deposito, es_principal
            FROM gestion__bocas
            WHERE sucursal_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
            ORDER BY es_principal DESC, orden ASC, boca_nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $sucursal_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $bocas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $bocas[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $bocas;
}

// Catálogo de tipos de comprobante habilitables para un PV. empresa_id=0
// es catálogo global (compartido entre empresas) y se incluye siempre
// junto con los específicos de la empresa.
function obtenerComprobantesTipos($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    // Asume que gestion__comprobantes_tipos.comprobante_subgrupo_id es la FK hacia
    // gestion__comprobantes_subgrupos, siguiendo la convención de nombres del resto
    // del esquema. Si el campo real tiene otro nombre, ajustar el JOIN.
    $sql = "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.codigo, ct.letra, ct.signo,
                   ct.impacta_stock, ct.impacta_contabilidad, ct.impacta_ctacte,
                   ct.comprobante_subgrupo_id, cs.comprobante_subgrupo, cs.codigo AS subgrupo_codigo
            FROM gestion__comprobantes_tipos ct
            LEFT JOIN gestion__comprobantes_subgrupos cs
                ON cs.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
                AND cs.tabla_estado_registro_id = 1
            WHERE (ct.empresa_id = ? OR ct.empresa_id = 0) AND ct.tabla_estado_registro_id = 1
            ORDER BY cs.orden ASC, ct.orden ASC, ct.comprobante_tipo ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $tipos;
}

// Tipos de comprobante ya habilitados para un PV puntual (para precargar
// el formulario de edición). Devuelve comprobante_tipo_id => requiere_afip.
function obtenerComprobantesPorPuntoVenta($conexion, $punto_venta_id, $empresa_idx)
{
    $punto_venta_id = intval($punto_venta_id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT comprobante_tipo_id, requiere_afip
            FROM gestion__puntos_venta_comprobantes
            WHERE punto_venta_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $punto_venta_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $habilitados = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $habilitados[intval($fila['comprobante_tipo_id'])] = intval($fila['requiere_afip']);
    }

    mysqli_stmt_close($stmt);
    return $habilitados;
}

// Sincroniza gestion__puntos_venta_comprobantes con la selección actual:
// borra todo lo previo y vuelve a insertar lo elegido. Se llama SIEMPRE
// dentro de la transacción de agregarPuntoVenta/editarPuntoVenta — no
// abre ni cierra transacción propia, y propaga la excepción para que el
// caller haga rollback.
function guardarComprobantesPuntoVenta($conexion, $punto_venta_id, $empresa_idx, $comprobantes)
{
    $punto_venta_id = intval($punto_venta_id);
    $empresa_idx = intval($empresa_idx);

    $sql_delete = "DELETE FROM gestion__puntos_venta_comprobantes WHERE punto_venta_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_delete);
    if (!$stmt) {
        throw new Exception("Error preparando limpieza de comprobantes: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt, "ii", $punto_venta_id, $empresa_idx);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error limpiando comprobantes habilitados: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    if (empty($comprobantes)) {
        return; // ningún tipo habilitado es una selección válida
    }

    $sql_insert = "INSERT INTO gestion__puntos_venta_comprobantes
                    (empresa_id, punto_venta_id, comprobante_tipo_id, requiere_afip, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, 1)";
    $stmt = mysqli_prepare($conexion, $sql_insert);
    if (!$stmt) {
        throw new Exception("Error preparando insert de comprobantes: " . mysqli_error($conexion));
    }

    foreach ($comprobantes as $item) {
        $comprobante_tipo_id_val = intval($item['comprobante_tipo_id'] ?? 0);
        if ($comprobante_tipo_id_val <= 0) {
            continue;
        }
        $requiere_afip_val = !empty($item['requiere_afip']) ? 1 : 0;

        mysqli_stmt_bind_param($stmt, "iiii",
            $empresa_idx, $punto_venta_id, $comprobante_tipo_id_val, $requiere_afip_val
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error guardando comprobante habilitado: " . mysqli_stmt_error($stmt));
        }
    }

    mysqli_stmt_close($stmt);
}

function obtenerPuntoVentaPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);

    $sql = "SELECT pv.*, s.sucursal_nombre, e.empresa, gb.boca_nombre
            FROM gestion__puntos_venta pv
            LEFT JOIN gestion__sucursales s ON pv.sucursal_id = s.sucursal_id
            LEFT JOIN conf__empresas e ON pv.empresa_id = e.empresa_id
            INNER JOIN gestion__bocas gb ON pv.boca_id = gb.boca_id
            WHERE pv.punto_venta_id = ? AND pv.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $punto = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($punto) {
        $punto['comprobantes_habilitados'] = obtenerComprobantesPorPuntoVenta($conexion, $id, $empresa_idx);
    }

    return $punto;
}

function obtenerSucursalesEmpresa($conexion, $empresa_idx)
{
    $sql = "SELECT sucursal_id, sucursal_nombre
            FROM gestion__sucursales
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1
            ORDER BY sucursal_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta sucursales: " . mysqli_error($conexion));
        return [];
    }

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

function obtenerEstadosRegistro($conexion)
{
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    $nombre_columna = 'estado_registro';
    if (in_array('nombre_estado', $columns)) {
        $nombre_columna = 'nombre_estado';
    } elseif (in_array('descripcion', $columns)) {
        $nombre_columna = 'descripcion';
    }

    $sql = "SELECT estado_registro_id, $nombre_columna as estado_nombre, codigo_estandar
            FROM conf__estados_registros
            ORDER BY orden, $nombre_columna";

    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        error_log("Error obteniendo estados: " . mysqli_error($conexion));
        return [];
    }

    $estados = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $estados[] = [
            'estado_registro_id' => $fila['estado_registro_id'],
            'estado_registro' => $fila['estado_nombre'],
            'codigo_estandar' => $fila['codigo_estandar']
        ];
    }

    return $estados;
}

?>