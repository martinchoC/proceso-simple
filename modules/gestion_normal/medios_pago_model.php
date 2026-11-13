<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
$conexion = $conn;

/**
 * Obtiene las funciones de página configuradas en conf__paginas_funciones
 */
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

/**
 * Obtiene la información del estado de un registro
 */
function obtenerInfoEstado($conexion, $estado_registro_id) {
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row['Field'];
        }
    }

    if (in_array('estado_registro', $columns)) {
        $sql = "SELECT estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
    } elseif (in_array('nombre_estado', $columns)) {
        $sql = "SELECT nombre_estado as estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
    } elseif (in_array('descripcion', $columns)) {
        $sql = "SELECT descripcion as estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
    } else {
        return [
            'estado_registro' => 'Estado ' . $estado_registro_id,
            'codigo_estandar' => 'ESTADO_' . $estado_registro_id
        ];
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

/**
 * Obtiene los botones habilitados para un estado de registro
 */
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id) {
    $funciones = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
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

/**
 * Obtiene el botón "Agregar" para la página
 */
function obtenerBotonAgregar($conexion, $pagina_id) {
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
        'nombre_funcion' => 'Nuevo Medio de Pago',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

/**
 * Ejecuta transición de estado (inactivar, habilitar, etc.)
 */
function ejecutarTransicionEstadoMedioPago($conexion, $medio_pago_id, $accion_js, $empresa_idx, $pagina_id) {
    $medio_pago_id = intval($medio_pago_id);
    $pagina_id = intval($pagina_id);
    $empresa_idx = intval($empresa_idx);

    $sql_check = "SELECT medio_pago_id, tabla_estado_registro_id FROM gestion__medios_pago WHERE medio_pago_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la preparación de consulta'];
    mysqli_stmt_bind_param($stmt, "ii", $medio_pago_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $mp = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$mp) return ['success' => false, 'error' => 'Medio de pago no encontrado'];

    $estado_actual_id = intval($mp['tabla_estado_registro_id']);

    $sql_funcion = "SELECT pf.* FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta de permisos'];
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$funcion) return ['success' => false, 'error' => 'Acción no permitida para el estado actual'];

    $estado_destino_id = intval($funcion['tabla_estado_registro_destino_id']);
    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'El registro ya se encuentra en el estado solicitado'];
    }

    $usuario_mod_id = intval($_SESSION['usuario_id'] ?? 0);
    $sql_update = "UPDATE gestion__medios_pago 
                   SET tabla_estado_registro_id = ?, 
                       usuario_modificacion_id = ?, 
                       fecha_modificacion = NOW() 
                   WHERE medio_pago_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error al preparar actualización'];
    mysqli_stmt_bind_param($stmt, "iiii", $estado_destino_id, $usuario_mod_id, $medio_pago_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    }
    return ['success' => false, 'error' => 'Error al actualizar el estado en base de datos'];
}

/**
 * Obtiene todos los medios de pago de la empresa activa
 */
function obtenerMediosPago($conexion, $empresa_idx, $pagina_id) {
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row['Field'];
        }
    }
    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) $estado_column = 'nombre_estado';
        elseif (in_array('descripcion', $columns)) $estado_column = 'descripcion';
    }

    $sql = "SELECT mp.*,
                   tc.tesoreria_cuenta,
                   cc.codigo AS cont_cuenta_codigo,
                   cc.nombre AS cont_cuenta_nombre,
                   it.impuesto_tipo,
                   it.codigo_afip AS impuesto_codigo_afip,
                   er.$estado_column AS estado_registro,
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__medios_pago mp
            LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = mp.tesoreria_cuenta_id
            LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = mp.cont_cuenta_id
            LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = mp.impuesto_tipo_id
            LEFT JOIN conf__estados_registros er ON mp.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE mp.empresa_id = ?
            ORDER BY mp.orden ASC, mp.medio_pago ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];

    while ($fila = mysqli_fetch_assoc($result)) {
        $color_clase = $fila['color_clase'] ?? 'btn-dark';
        $bg_clase = $fila['bg_clase'] ?? 'bg-secondary';
        $text_clase = $fila['text_clase'] ?? 'text-white';

        $estado_actual_id = intval($fila['tabla_estado_registro_id'] ?? 1);
        $botones = obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id);

        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Desconocido',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => $color_clase,
            'bg_clase' => $bg_clase,
            'text_clase' => $text_clase
        ];
        $fila['botones'] = $botones;

        $data[] = $fila;
    }
    mysqli_stmt_close($stmt);

    return $data;
}

/**
 * Obtiene un medio de pago por ID
 */
function obtenerMedioPagoPorId($conexion, $medio_pago_id, $empresa_idx) {
    $medio_pago_id = intval($medio_pago_id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT mp.*,
                   tc.tesoreria_cuenta,
                   cc.codigo AS cont_cuenta_codigo,
                   cc.nombre AS cont_cuenta_nombre,
                   it.impuesto_tipo
            FROM gestion__medios_pago mp
            LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = mp.tesoreria_cuenta_id
            LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = mp.cont_cuenta_id
            LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = mp.impuesto_tipo_id
            WHERE mp.medio_pago_id = ? AND mp.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $medio_pago_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row;
}

/**
 * Obtiene los maestros relacionados para combos de selección
 */
function obtenerMaestrosMediosPago($conexion, $empresa_idx) {
    $empresa_idx = intval($empresa_idx);

    // Cuentas de Tesorería de la empresa activa
    $tesoreria_cuentas = [];
    $sql_tc = "SELECT tesoreria_cuenta_id, tesoreria_cuenta, cuenta_tipo, moneda_id 
               FROM gestion__tesoreria_cuentas 
               WHERE empresa_id = ? AND tabla_estado_registro_id = 1 
               ORDER BY cuenta_tipo ASC, tesoreria_cuenta ASC";
    $stmt_tc = mysqli_prepare($conexion, $sql_tc);
    if ($stmt_tc) {
        mysqli_stmt_bind_param($stmt_tc, "i", $empresa_idx);
        mysqli_stmt_execute($stmt_tc);
        $res = mysqli_stmt_get_result($stmt_tc);
        while ($r = mysqli_fetch_assoc($res)) {
            $tesoreria_cuentas[] = $r;
        }
        mysqli_stmt_close($stmt_tc);
    }

    // Cuentas Contables imputables de la empresa activa
    $cont_cuentas = [];
    $sql_cc = "SELECT cont_cuenta_id, codigo, nombre 
               FROM gestion__cont_cuentas 
               WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1 
               ORDER BY codigo ASC";
    $stmt_cc = mysqli_prepare($conexion, $sql_cc);
    if ($stmt_cc) {
        mysqli_stmt_bind_param($stmt_cc, "i", $empresa_idx);
        mysqli_stmt_execute($stmt_cc);
        $res = mysqli_stmt_get_result($stmt_cc);
        while ($r = mysqli_fetch_assoc($res)) {
            $cont_cuentas[] = $r;
        }
        mysqli_stmt_close($stmt_cc);
    }

    // Tipos de Impuestos (enfocado en retenciones)
    $impuestos_tipos = [];
    $sql_it = "SELECT impuesto_tipo_id, impuesto_tipo, codigo_afip, es_retencion 
               FROM gestion__impuestos_tipos 
               WHERE tabla_estado_registro_id = 1 
               ORDER BY es_retencion DESC, impuesto_tipo ASC";
    $res_it = mysqli_query($conexion, $sql_it);
    if ($res_it) {
        while ($r = mysqli_fetch_assoc($res_it)) {
            $impuestos_tipos[] = $r;
        }
    }

    // Tipos de medio permitidos por el ENUM
    $tipos_medio = [
        ['codigo' => 'EFECTIVO', 'nombre' => 'Efectivo', 'cuenta_defecto' => 'CAJA'],
        ['codigo' => 'CHEQUE_TERCERO', 'nombre' => 'Cheque de Tercero', 'cuenta_defecto' => 'VALORES'],
        ['codigo' => 'CHEQUE_PROPIO', 'nombre' => 'Cheque Propio', 'cuenta_defecto' => 'BANCO'],
        ['codigo' => 'TRANSFERENCIA', 'nombre' => 'Transferencia Bancaria', 'cuenta_defecto' => 'BANCO'],
        ['codigo' => 'DEPOSITO', 'nombre' => 'Depósito Bancario', 'cuenta_defecto' => 'BANCO'],
        ['codigo' => 'TARJETA_DEBITO', 'nombre' => 'Tarjeta de Débito', 'cuenta_defecto' => 'BANCO'],
        ['codigo' => 'TARJETA_CREDITO', 'nombre' => 'Tarjeta de Crédito', 'cuenta_defecto' => 'CUPONES'],
        ['codigo' => 'RETENCION', 'nombre' => 'Retención Impositiva', 'cuenta_defecto' => null],
        ['codigo' => 'OTRO', 'nombre' => 'Otro Medio de Pago', 'cuenta_defecto' => null]
    ];

    $cuentas_tipo = [
        ['codigo' => 'CAJA', 'nombre' => 'Caja (Efectivo)'],
        ['codigo' => 'BANCO', 'nombre' => 'Banco (Cuentas bancarias)'],
        ['codigo' => 'VALORES', 'nombre' => 'Valores al Cobro (Cartera)'],
        ['codigo' => 'CUPONES', 'nombre' => 'Cupones de Tarjeta']
    ];

    return [
        'tesoreria_cuentas' => $tesoreria_cuentas,
        'cont_cuentas' => $cont_cuentas,
        'impuestos_tipos' => $impuestos_tipos,
        'tipos_medio' => $tipos_medio,
        'cuentas_tipo' => $cuentas_tipo
    ];
}

/**
 * Guarda (crea o actualiza) un medio de pago
 */
function guardarMedioPago($conexion, $data) {
    $empresa_id = intval($data['empresa_idx'] ?? 0);
    $medio_pago_id = intval($data['medio_pago_id'] ?? 0);
    $usuario_id = intval($_SESSION['usuario_id'] ?? 1);

    if ($empresa_id <= 0) {
        return ['success' => false, 'error' => 'Empresa no identificada'];
    }

    $codigo = strtoupper(trim($data['codigo'] ?? ''));
    $medio_pago = trim($data['medio_pago'] ?? '');
    $medio_pago_tipo = trim($data['medio_pago_tipo'] ?? '');

    if (empty($codigo)) {
        return ['success' => false, 'error' => 'El código del medio de pago es obligatorio'];
    }
    if (strlen($codigo) > 30) {
        return ['success' => false, 'error' => 'El código no puede superar los 30 caracteres'];
    }
    if (empty($medio_pago)) {
        return ['success' => false, 'error' => 'El nombre del medio de pago es obligatorio'];
    }
    if (strlen($medio_pago) > 100) {
        return ['success' => false, 'error' => 'El nombre no puede superar los 100 caracteres'];
    }

    $tipos_permitidos = ['EFECTIVO','CHEQUE_PROPIO','CHEQUE_TERCERO','TRANSFERENCIA','DEPOSITO','TARJETA_DEBITO','TARJETA_CREDITO','RETENCION','OTRO'];
    if (!in_array($medio_pago_tipo, $tipos_permitidos, true)) {
        return ['success' => false, 'error' => 'Tipo de medio de pago inválido'];
    }

    // Verificar código duplicado en la misma empresa
    $sql_dup = "SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id = ? AND codigo = ? AND medio_pago_id != ?";
    $stmt_dup = mysqli_prepare($conexion, $sql_dup);
    mysqli_stmt_bind_param($stmt_dup, "isi", $empresa_id, $codigo, $medio_pago_id);
    mysqli_stmt_execute($stmt_dup);
    mysqli_stmt_store_result($stmt_dup);
    if (mysqli_stmt_num_rows($stmt_dup) > 0) {
        mysqli_stmt_close($stmt_dup);
        return ['success' => false, 'error' => "Ya existe un medio de pago con el código '$codigo' en esta empresa"];
    }
    mysqli_stmt_close($stmt_dup);

    $tesoreria_cuenta_tipo = !empty($data['tesoreria_cuenta_tipo']) ? trim($data['tesoreria_cuenta_tipo']) : null;
    $cuentas_tipo_validas = ['CAJA', 'BANCO', 'VALORES', 'CUPONES'];
    if ($tesoreria_cuenta_tipo !== null && !in_array($tesoreria_cuenta_tipo, $cuentas_tipo_validas, true)) {
        $tesoreria_cuenta_tipo = null;
    }

    $tesoreria_cuenta_id = !empty($data['tesoreria_cuenta_id']) ? intval($data['tesoreria_cuenta_id']) : null;
    $cont_cuenta_id = !empty($data['cont_cuenta_id']) ? intval($data['cont_cuenta_id']) : null;
    $impuesto_tipo_id = !empty($data['impuesto_tipo_id']) ? intval($data['impuesto_tipo_id']) : null;

    $requiere_detalle = !empty($data['requiere_detalle']) ? 1 : 0;
    $aplica_cobro = !empty($data['aplica_cobro']) ? 1 : 0;
    $aplica_pago = !empty($data['aplica_pago']) ? 1 : 0;
    $orden = isset($data['orden']) ? intval($data['orden']) : 1;

    if ($medio_pago_id > 0) {
        // UPDATE
        $sql = "UPDATE gestion__medios_pago SET
                    codigo = ?,
                    medio_pago = ?,
                    medio_pago_tipo = ?,
                    tesoreria_cuenta_tipo = ?,
                    tesoreria_cuenta_id = ?,
                    cont_cuenta_id = ?,
                    impuesto_tipo_id = ?,
                    requiere_detalle = ?,
                    aplica_cobro = ?,
                    aplica_pago = ?,
                    orden = ?,
                    usuario_modificacion_id = ?,
                    fecha_modificacion = NOW()
                WHERE medio_pago_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return ['success' => false, 'error' => 'Error preparando actualización: ' . mysqli_error($conexion)];

        mysqli_stmt_bind_param($stmt, "ssssiiiiiiiiii",
            $codigo,
            $medio_pago,
            $medio_pago_tipo,
            $tesoreria_cuenta_tipo,
            $tesoreria_cuenta_id,
            $cont_cuenta_id,
            $impuesto_tipo_id,
            $requiere_detalle,
            $aplica_cobro,
            $aplica_pago,
            $orden,
            $usuario_id,
            $medio_pago_id,
            $empresa_id
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'message' => 'Medio de pago actualizado correctamente', 'id' => $medio_pago_id];
        } else {
            return ['success' => false, 'error' => 'Error al actualizar medio de pago: ' . mysqli_error($conexion)];
        }
    } else {
        // INSERT
        $sql = "INSERT INTO gestion__medios_pago (
                    empresa_id,
                    codigo,
                    medio_pago,
                    medio_pago_tipo,
                    tesoreria_cuenta_tipo,
                    tesoreria_cuenta_id,
                    cont_cuenta_id,
                    impuesto_tipo_id,
                    requiere_detalle,
                    aplica_cobro,
                    aplica_pago,
                    orden,
                    tabla_estado_registro_id,
                    usuario_id,
                    fecha_creacion,
                    fecha_modificacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return ['success' => false, 'error' => 'Error preparando inserción: ' . mysqli_error($conexion)];

        mysqli_stmt_bind_param($stmt, "issssiiiiiiii",
            $empresa_id,
            $codigo,
            $medio_pago,
            $medio_pago_tipo,
            $tesoreria_cuenta_tipo,
            $tesoreria_cuenta_id,
            $cont_cuenta_id,
            $impuesto_tipo_id,
            $requiere_detalle,
            $aplica_cobro,
            $aplica_pago,
            $orden,
            $usuario_id
        );

        $ok = mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'message' => 'Medio de pago creado correctamente', 'id' => $new_id];
        } else {
            return ['success' => false, 'error' => 'Error al crear medio de pago: ' . mysqli_error($conexion)];
        }
    }
}
