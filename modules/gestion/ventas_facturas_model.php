<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function vfEstadoColumna($conexion) {
    $result = mysqli_query($conexion, "SHOW COLUMNS FROM conf__estados_registros");
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) $columns[] = $row['Field'];
    if (in_array('estado_registro', $columns)) return 'estado_registro';
    if (in_array('nombre_estado', $columns)) return 'nombre_estado';
    return 'descripcion';
}

function listarVentasFacturas($conexion, $empresa_id, $pagina_id) {
    $estado = vfEstadoColumna($conexion);
    $sql = "SELECT f.venta_factura_id, f.comprobante_nro, f.f_emision, f.importe_total,
                   f.entidad_id, f.condicion_pago_id, er.$estado AS estado_registro,
                   er.codigo_estandar, f.tabla_estado_registro_id, ct.comprobante_tipo, e.entidad_nombre,
                   e.entidad_fantasia, cp.condicion_pago
            FROM gestion__ventas_facturas f
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = f.condicion_pago_id
            WHERE f.empresa_id = ?
            ORDER BY f.f_emision DESC, f.venta_factura_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['estado_info'] = ['estado_registro' => $row['estado_registro'] ?? 'Sin estado', 'codigo_estandar' => $row['codigo_estandar'] ?? ''];
        $row['botones'] = vfBotonesPorEstado($conexion, $pagina_id, intval($row['tabla_estado_registro_id'] ?? 0));
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// Todas las funciones (botones) configuradas para esta página, sin filtrar por estado
// origen. Base de vfBotonesPorEstado() y vfObtenerBotonAgregar().
function vfObtenerFuncionesPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfBotonesPorEstado($conexion, $pagina_id, $estado_id) {
        $sql = "SELECT pf.nombre_funcion, pf.accion_js, pf.descripcion,
                       pf.tabla_estado_registro_origen_id,
                       pf.tabla_estado_registro_destino_id,
                       i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
                        FROM conf__paginas_funciones pf
                        LEFT JOIN conf__iconos i ON i.icono_id = pf.icono_id
                        LEFT JOIN conf__colores c ON c.color_id = pf.color_id
                        WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ?
                            AND pf.tabla_estado_registro_id = 1
                        ORDER BY pf.orden";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'ii', $pagina_id, $estado_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['es_confirmable'] = intval($row['tabla_estado_registro_destino_id']) !== intval($estado_id) ? 1 : 0;
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
}

// Función con origen_id = 0 (creación) — mismo patrón que obtenerBotonAgregar() de ventas_pedidos_model.php.
function vfObtenerBotonAgregar($conexion, $pagina_id) {
    foreach (vfObtenerFuncionesPagina($conexion, $pagina_id) as $funcion) {
        if (intval($funcion['tabla_estado_registro_origen_id']) === 0) {
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
        'nombre_funcion' => 'Nueva Factura',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => '',
        'text_clase' => ''
    ];
}

function vfObtenerCondicionCliente($conexion, $entidad_id, $empresa_id) {
    $sql = "SELECT ecc.condicion_pago_id, ecc.lista_precio_id, ecc.cliente_descuento_general,
                   cp.condicion_pago, cp.dias_primer_vencimiento,
                   lp.lista_precio_nombre
            FROM gestion__entidades_condiciones_clientes ecc
            INNER JOIN gestion__entidades e ON e.entidad_id = ecc.entidad_id AND e.empresa_id = ?
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = ecc.condicion_pago_id
            LEFT JOIN gestion__listas_precios lp ON lp.lista_precio_id = ecc.lista_precio_id
            WHERE ecc.entidad_id = ? AND ecc.tabla_estado_registro_id = 1
              AND ecc.f_desde <= CURDATE()
              AND (ecc.f_hasta IS NULL OR ecc.f_hasta >= CURDATE())
            ORDER BY ecc.f_desde DESC LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function vfClientes($conexion, $empresa_id) {
        $sql = "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia,
                                     es.sucursal_id AS entidad_sucursal_id, es.sucursal_nombre
                        FROM gestion__entidades e
                        LEFT JOIN gestion__entidades_sucursales es
                                ON es.entidad_id = e.entidad_id
                             AND es.empresa_id = e.empresa_id
                             AND es.tabla_estado_registro_id = 1
                        WHERE e.empresa_id = ? AND e.es_cliente = 1
                            AND e.tabla_estado_registro_id = 1
                        ORDER BY e.entidad_nombre, es.sucursal_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfPuntosVenta($conexion, $empresa_id) {
    $sql = "SELECT pv.punto_venta_id, pv.sucursal_id, pv.nombre, pv.codigo_fiscal,
                   s.sucursal_nombre
            FROM gestion__puntos_venta pv
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = pv.sucursal_id
            WHERE pv.empresa_id = ? AND pv.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre, pv.nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfTiposPorPuntoVenta($conexion, $empresa_id, $punto_venta_id) {
    $sql = "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.letra, ct.codigo,
                   ct.comprobante_fiscal_id
            FROM gestion__puntos_venta_comprobantes pvc
            INNER JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = pvc.comprobante_tipo_id
            WHERE pvc.empresa_id = ? AND pvc.punto_venta_id = ?
              AND pvc.tabla_estado_registro_id = 1
              AND ct.tabla_estado_registro_id = 1
            ORDER BY ct.comprobante_tipo";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $empresa_id, $punto_venta_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

// $excluir_factura_id: al editar una factura ya guardada, sus propias líneas consumidas
// de remito no deben contar como "ya facturadas" para esta misma pantalla — si no,
// una línea 100% tomada por esta factura desaparecería del listado de pendientes al
// reabrirla. Se descuenta lo que YA consumió esa factura (propia.cantidad_propia) del
// cantidad_facturada global antes de calcular el pendiente. En alta (id=0) no afecta nada.
// $venta_remito_id_filtro: si viene > 0, acota el resultado a las líneas pendientes de ESE
// remito puntual (usado por la solapa "Facturar" del modal de ventas_remitos.php — ahí ya se
// sabe de qué remito se trata, no hace falta traer el resto de los pendientes del cliente).
function vfRemitosPendientes($conexion, $empresa_id, $entidad_id, $entidad_sucursal_id = 0, $excluir_factura_id = 0, $venta_remito_id_filtro = 0) {
    $sql = "SELECT vr.venta_remito_id, vr.comprobante_nro, vr.f_emision,
                   ct.comprobante_tipo, pv.nombre AS punto_venta_nombre,
                   rd.venta_remito_detalle_id, rd.venta_pedido_detalle_id,
                   rd.producto_id, p.producto_codigo, p.producto_nombre,
                   rd.cantidad, rd.precio_unitario_bruto, rd.descuento_general_pct,
                   rd.descuento_general, rd.precio_unitario_neto, rd.iva_alicuota_id,
                   rd.iva_porcentaje, rd.iva_importe, rd.importe_linea,
                   COALESCE(rd.facturado, 0) AS facturado,
                   COALESCE(propia.cantidad_propia, 0) AS cantidad_propia,
                   GREATEST(0, COALESCE(rd.cantidad_facturada, 0) - COALESCE(propia.cantidad_propia, 0)) AS cantidad_facturada,
                   (rd.cantidad - GREATEST(0, COALESCE(rd.cantidad_facturada, 0) - COALESCE(propia.cantidad_propia, 0))) AS cantidad_pendiente_facturar
            FROM gestion__ventas_remitos vr
            INNER JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_id = vr.venta_remito_id
            INNER JOIN gestion__productos p ON p.producto_id = rd.producto_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vr.comprobante_tipo_id
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = vr.comprobante_pv
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = vr.tabla_estado_registro_id
            LEFT JOIN (
                SELECT venta_remito_detalle_id, SUM(cantidad) AS cantidad_propia
                FROM gestion__ventas_facturas_detalles
                WHERE venta_factura_id = ? AND venta_remito_detalle_id IS NOT NULL
                GROUP BY venta_remito_detalle_id
            ) propia ON propia.venta_remito_detalle_id = rd.venta_remito_detalle_id
                        WHERE vr.empresa_id = ? AND vr.entidad_id = ?
                            AND (? = 0 OR vr.entidad_sucursal_id = ?)
                            AND (? = 0 OR vr.venta_remito_id = ?)
              AND er.codigo_estandar IN ('CONFIRMADO', 'PEND_FACT', 'FACT_PARC')
              AND (rd.cantidad - GREATEST(0, COALESCE(rd.cantidad_facturada, 0) - COALESCE(propia.cantidad_propia, 0))) > 0
              AND rd.tabla_estado_registro_id = 1
            ORDER BY vr.f_emision, vr.venta_remito_id, rd.venta_remito_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'iiiiiii', $excluir_factura_id, $empresa_id, $entidad_id, $entidad_sucursal_id, $entidad_sucursal_id, $venta_remito_id_filtro, $venta_remito_id_filtro);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfBuscarProductos($conexion, $empresa_id, $entidad_id, $q) {
    $cond = vfObtenerCondicionCliente($conexion, $entidad_id, $empresa_id);
    if (!$cond || empty($cond['lista_precio_id'])) return [];
    $like = '%' . $q . '%';
    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                   lp.precio_final AS precio_unitario, p.iva_alicuota_id,
                   COALESCE(iva.porcentaje, 0) AS iva_porcentaje
            FROM gestion__listas_precios_productos lp
            INNER JOIN gestion__productos p ON p.producto_id = lp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON iva.iva_alicuota_id = p.iva_alicuota_id
            WHERE lp.lista_precio_id = ? AND lp.empresa_id = ? AND p.empresa_id = ?
              AND lp.tabla_estado_registro_id = 1 AND p.tabla_estado_registro_id = 1
              AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ?)
            ORDER BY p.producto_nombre LIMIT 20";
    $stmt = mysqli_prepare($conexion, $sql);
    $lista = intval($cond['lista_precio_id']);
    mysqli_stmt_bind_param($stmt, 'iiiss', $lista, $empresa_id, $empresa_id, $like, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfEstadoInicial($conexion, $pagina_id) {
    $sql = "SELECT ter.estado_registro_id
            FROM conf__tablas_estados_registros ter
            INNER JOIN conf__paginas pg ON pg.tabla_id = ter.tabla_id
            WHERE pg.pagina_id = ? AND ter.es_inicial = 1 LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['estado_registro_id'] ?? 3);
}

function vfObtenerProximoNumero($conexion, $empresa_id, $punto_venta_id, $comprobante_tipo_id) {
    $sql = "SELECT numerador_id, ultimo_numero
            FROM gestion__comprobantes_numeradores
            WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?
            FOR UPDATE";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('No se pudo preparar el numerador: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $punto_venta_id, $comprobante_tipo_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row) {
        $numero = intval($row['ultimo_numero']) + 1;
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_numeradores SET ultimo_numero = ? WHERE numerador_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $numero, $row['numerador_id']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('No se pudo actualizar el numerador.');
        mysqli_stmt_close($stmt);
        return $numero;
    }

    $numero = 1;
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_numeradores (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iiii', $empresa_id, $punto_venta_id, $comprobante_tipo_id, $numero);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('No se pudo crear el numerador.');
    mysqli_stmt_close($stmt);
    return $numero;
}

// tabla_id de conf__tablas asociada a esta página, usado por syncComprobante() para
// identificar el origen del comprobante. Copia funcional de obtenerTablaOrigenPorPagina()
// tal como está en ventas_pedidos_model.php / facturas_proveedores_model.php.
function obtenerTablaOrigenPorPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT tabla_id FROM conf__paginas WHERE pagina_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta tabla_id: " . mysqli_error($conexion));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ($row && !empty($row['tabla_id'])) ? (int)$row['tabla_id'] : null;
}

// Copia funcional de syncComprobante() tal como está en ventas_pedidos_model.php /
// facturas_proveedores_model.php. Se duplica aquí a propósito para no introducir un
// include cruzado entre módulos; si se resuelve extraer un helper común, esta función
// debería eliminarse de acá.
function syncComprobante($conexion, $data, $tabla_origen_id) {
    if (empty($tabla_origen_id)) {
        error_log("ERROR: tabla_origen_id no proporcionado");
        return null;
    }

    $empresa_id = intval($data['empresa_id'] ?? 0);
    $sucursal_id = !empty($data['sucursal_id']) ? intval($data['sucursal_id']) : null;
    $comprobante_pv = intval($data['comprobante_pv'] ?? 0);
    $comprobante_tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
    $comprobante_nro = intval($data['comprobante_nro'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $entidad_sucursal_id = !empty($data['entidad_sucursal_id']) ? intval($data['entidad_sucursal_id']) : null;
    $f_emision = $data['f_emision'] ?? date('Y-m-d');
    $f_contabilidad = $data['f_contabilidad'] ?? $f_emision;
    $f_vto = $data['f_vto'] ?? null;
    $moneda_id = intval($data['moneda_id'] ?? 1);
    $tipo_cambio = floatval($data['tipo_cambio'] ?? 1.0);
    $registro_origen_id = intval($data['registro_origen_id'] ?? 0);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 3);
    $usuario_id = intval($data['usuario_id'] ?? 0);
    $observaciones = trim($data['observaciones'] ?? '');

    $importe_neto = floatval($data['importe_neto'] ?? 0);
    $descuento_general = floatval($data['descuento_general'] ?? 0);
    $importe_no_gravado = floatval($data['importe_no_gravado'] ?? 0);
    $importe_exento = floatval($data['importe_exento'] ?? 0);
    $importe_iva = floatval($data['importe_iva'] ?? 0);
    $importe_otros_impuestos = floatval($data['importe_otros_impuestos'] ?? 0);
    $importe_total = floatval($data['importe_total'] ?? 0);

    $importe_bruto = $importe_neto + $descuento_general;

    if ($registro_origen_id <= 0) {
        error_log("ERROR: registro_origen_id inválido: $registro_origen_id");
        return null;
    }

    $sql_check = "SELECT comprobante_id FROM gestion__comprobantes
                  WHERE tabla_origen_id = ? AND registro_origen_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        error_log("Error preparando SELECT: " . mysqli_error($conexion));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "ii", $tabla_origen_id, $registro_origen_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existe = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $comprobante_id = null;

    if ($existe) {
        $comprobante_id = $existe['comprobante_id'];
        $sql_update = "UPDATE gestion__comprobantes SET
                            empresa_id = ?,
                            sucursal_id = ?,
                            comprobante_pv = ?,
                            comprobante_tipo_id = ?,
                            comprobante_nro = ?,
                            entidad_id = ?,
                            entidad_sucursal_id = ?,
                            f_emision = ?,
                            f_contabilidad = ?,
                            f_vto = ?,
                            moneda_id = ?,
                            tipo_cambio = ?,
                            importe_bruto = ?,
                            descuento_general = ?,
                            importe_no_gravado = ?,
                            importe_exento = ?,
                            importe_neto = ?,
                            importe_iva = ?,
                            importe_otros_impuestos = ?,
                            importe_total = ?,
                            importe_pendiente = ?,
                            tabla_estado_registro_id = ?,
                            observaciones = ?,
                            usuario_modificacion_id = ?
                        WHERE comprobante_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            error_log("Error preparando UPDATE: " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param($stmt, "iiiiiiisssiddddddddddisii",
            $empresa_id, $sucursal_id, $comprobante_pv,
            $comprobante_tipo_id, $comprobante_nro,
            $entidad_id, $entidad_sucursal_id,
            $f_emision, $f_contabilidad, $f_vto,
            $moneda_id, $tipo_cambio,
            $importe_bruto, $descuento_general,
            $importe_no_gravado, $importe_exento,
            $importe_neto, $importe_iva, $importe_otros_impuestos,
            $importe_total,
            $importe_total,
            $tabla_estado_registro_id, $observaciones,
            $usuario_id,
            $comprobante_id
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error ejecutando UPDATE: " . mysqli_stmt_error($stmt));
            return null;
        }
        mysqli_stmt_close($stmt);
    } else {
        $sql_insert = "INSERT INTO gestion__comprobantes (
                            empresa_id, sucursal_id, comprobante_pv,
                            comprobante_tipo_id, comprobante_nro,
                            entidad_id, entidad_sucursal_id,
                            f_emision, f_contabilidad, f_vto,
                            moneda_id, tipo_cambio,
                            importe_bruto, descuento_general,
                            importe_no_gravado, importe_exento,
                            importe_neto, importe_iva, importe_otros_impuestos,
                            importe_total, importe_pendiente,
                            tabla_origen_id, registro_origen_id,
                            tabla_estado_registro_id,
                            usuario_id,
                            observaciones
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt) {
            error_log("Error preparando INSERT: " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param($stmt, "iiiiiiisssiddddddddddiiiis",
            $empresa_id, $sucursal_id, $comprobante_pv,
            $comprobante_tipo_id, $comprobante_nro,
            $entidad_id, $entidad_sucursal_id,
            $f_emision, $f_contabilidad, $f_vto,
            $moneda_id, $tipo_cambio,
            $importe_bruto, $descuento_general,
            $importe_no_gravado, $importe_exento,
            $importe_neto, $importe_iva, $importe_otros_impuestos,
            $importe_total,
            $importe_total,
            $tabla_origen_id, $registro_origen_id,
            $tabla_estado_registro_id,
            $usuario_id,
            $observaciones
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error ejecutando INSERT: " . mysqli_stmt_error($stmt));
            return null;
        }
        $comprobante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
    }

    return $comprobante_id;
}

// Revierte el consumo que una línea de factura hizo sobre una línea de remito
// (usado al editar o al eliminar una factura, para no dejar remitos "trabados"
// como facturados por una factura descartada/modificada).
function vfLiberarDetalleRemito($conexion, $remito_detalle_id, $cantidad) {
    $remito_detalle_id = intval($remito_detalle_id);
    $cantidad = floatval($cantidad);
    if (!$remito_detalle_id || $cantidad <= 0) return;

    $stmt = mysqli_prepare($conexion, "SELECT cantidad, cantidad_facturada FROM gestion__ventas_remitos_detalles WHERE venta_remito_detalle_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'i', $remito_detalle_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) return;

    $nueva_cantidad_facturada = max(0, floatval($row['cantidad_facturada']) - $cantidad);
    $facturado = ($nueva_cantidad_facturada >= floatval($row['cantidad'])) ? 1 : 0;

    $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_remitos_detalles SET cantidad_facturada = ?, facturado = ? WHERE venta_remito_detalle_id = ?");
    mysqli_stmt_bind_param($stmt, 'dii', $nueva_cantidad_facturada, $facturado, $remito_detalle_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function vfLiberarRemitosDeFactura($conexion, $venta_factura_id) {
    $venta_factura_id = intval($venta_factura_id);
    $sql = "SELECT venta_remito_detalle_id, cantidad FROM gestion__ventas_facturas_detalles
            WHERE venta_factura_id = ? AND venta_remito_detalle_id IS NOT NULL";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $venta_factura_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);

    foreach ($rows as $row) {
        vfLiberarDetalleRemito($conexion, $row['venta_remito_detalle_id'], floatval($row['cantidad']));
    }
}

// Estados de gestion__ventas_remitos (tabla_id 81, estado_registro_id genérico igual que en el
// resto del motor): 3=Borrador, 13=Pend. de Facturación (PEND_FACT), 12=Facturado, 6=Eliminado.
// No hay una función 'auto_facturar' en conf__paginas_funciones para remitos — este cierre de
// ciclo se resuelve acá directamente, igual que vfInsertarDetallesFactura() ya toca
// gestion__ventas_remitos_detalles sin pasar por el motor genérico de botones.
function vfActualizarEstadoRemitosDeFactura($conexion, $venta_factura_id) {
    $venta_factura_id = intval($venta_factura_id);
    $sql = "SELECT DISTINCT rd.venta_remito_id
            FROM gestion__ventas_facturas_detalles d
            INNER JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_detalle_id = d.venta_remito_detalle_id
            WHERE d.venta_factura_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $venta_factura_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $remitoIds = [];
    while ($row = mysqli_fetch_assoc($result)) $remitoIds[] = intval($row['venta_remito_id']);
    mysqli_stmt_close($stmt);

    foreach ($remitoIds as $remitoId) {
        $sqlPend = "SELECT COUNT(*) AS pendientes
                    FROM gestion__ventas_remitos_detalles
                    WHERE venta_remito_id = ? AND tabla_estado_registro_id = 1
                      AND cantidad_facturada < cantidad";
        $stmt = mysqli_prepare($conexion, $sqlPend);
        mysqli_stmt_bind_param($stmt, 'i', $remitoId);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        // Este remito ya tiene al menos una línea facturada por ESTA factura (por eso apareció
        // en $remitoIds), así que si todavía quedan líneas pendientes es forzosamente un caso
        // de facturación parcial, no "todavía nada facturado".
        $nuevoEstado = (intval($fila['pendientes'] ?? 1) === 0) ? 12 : 15; // 12=Facturado, 15=Facturación Parcial

        // Solo pisa Pend. de Facturación (13) o Facturación Parcial (15) — nunca Borrador ni Eliminado.
        $upd = mysqli_prepare($conexion, "UPDATE gestion__ventas_remitos SET tabla_estado_registro_id = ? WHERE venta_remito_id = ? AND tabla_estado_registro_id IN (13, 15)");
        mysqli_stmt_bind_param($upd, 'ii', $nuevoEstado, $remitoId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
}

// Datos mínimos de un remito para precargar el alta de factura desde el deep-link que manda
// ventas_remitos.php (botón "Facturado"/"Facturado Parcial"). Filtra por empresa_id: no alcanza
// con conocer el venta_remito_id de otra empresa para leer a qué cliente pertenece.
function vfObtenerRemitoInfo($conexion, $empresa_id, $venta_remito_id) {
    $venta_remito_id = intval($venta_remito_id);
    $sql = "SELECT venta_remito_id, entidad_id, entidad_sucursal_id, comprobante_nro
            FROM gestion__ventas_remitos
            WHERE venta_remito_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $venta_remito_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// Facturas que ya tienen alguna línea originada en este remito (para la solapa "Facturar" del
// modal de ventas_remitos.php — mostrar el historial y dejar confirmar/ver desde ahí, sin ir a
// buscarlas a Facturación). Trae los mismos botones por estado que usa el listado normal.
function vfObtenerFacturasDeRemito($conexion, $empresa_id, $venta_remito_id, $pagina_id) {
    $venta_remito_id = intval($venta_remito_id);
    $estado = vfEstadoColumna($conexion);
    $sql = "SELECT DISTINCT f.venta_factura_id, f.comprobante_nro, f.f_emision, f.importe_total,
                   f.tabla_estado_registro_id, er.$estado AS estado_registro, er.codigo_estandar,
                   ct.comprobante_tipo
            FROM gestion__ventas_facturas_detalles d
            INNER JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_detalle_id = d.venta_remito_detalle_id
            INNER JOIN gestion__ventas_facturas f ON f.venta_factura_id = d.venta_factura_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
            WHERE rd.venta_remito_id = ? AND f.empresa_id = ?
            ORDER BY f.venta_factura_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $venta_remito_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['botones'] = vfBotonesPorEstado($conexion, $pagina_id, intval($row['tabla_estado_registro_id']));
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfObtenerVentaFacturaPorId($conexion, $id, $empresa_id, $pagina_id = 0) {
    $id = intval($id);
    $estado = vfEstadoColumna($conexion);
    $sql = "SELECT f.*, er.$estado AS estado_registro, er.codigo_estandar,
                   ct.comprobante_tipo, ct.letra, ct.comprobante_fiscal_id,
                   e.entidad_nombre, e.entidad_fantasia,
                   cp.condicion_pago,
                   pv.nombre AS punto_venta_nombre, pv.sucursal_id AS pv_sucursal_id
            FROM gestion__ventas_facturas f
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = f.condicion_pago_id
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = f.punto_venta_id
            WHERE f.venta_factura_id = ? AND f.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$factura) return null;

    $sql_det = "SELECT d.*, p.producto_codigo, p.producto_nombre,
                       vr.comprobante_nro AS remito_comprobante_nro, vr.venta_remito_id AS remito_id_origen
                FROM gestion__ventas_facturas_detalles d
                LEFT JOIN gestion__productos p ON p.producto_id = d.producto_id
                LEFT JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_detalle_id = d.venta_remito_detalle_id
                LEFT JOIN gestion__ventas_remitos vr ON vr.venta_remito_id = rd.venta_remito_id
                WHERE d.venta_factura_id = ?
                ORDER BY d.venta_factura_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql_det);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $detalles = [];
    while ($d = mysqli_fetch_assoc($result)) $detalles[] = $d;
    mysqli_stmt_close($stmt);

    $factura['detalles'] = $detalles;
    $factura['botones'] = vfBotonesPorEstado($conexion, $pagina_id, intval($factura['tabla_estado_registro_id']));
    return $factura;
}

// Calcula bruto/descuento/neto/iva/línea de cada detalle (misma fórmula que guardarVentaFactura())
// y acumula los totales de cabecera. Devuelve [$detalles_calculados, $totales].
function vfCalcularDetallesFactura($detalles, $comprobante_fiscal_id) {
    $subtotal = $descuentos = $iva = $bruto = $total = 0;
    foreach ($detalles as &$d) {
        $cantidad = floatval($d['cantidad']);
        $precio = floatval($d['precio_unitario']);
        $descPct = floatval($d['descuento_general_pct'] ?? 0);
        $brutoLinea = $cantidad * $precio;
        $desc = $brutoLinea * $descPct / 100;
        $neto = $brutoLinea - $desc;
        $ivaPorcentaje = $comprobante_fiscal_id === 0 ? 0 : floatval($d['iva_porcentaje'] ?? 0);
        $ivaImporte = $neto * $ivaPorcentaje / 100;
        $linea = $neto + $ivaImporte;
        $d['iva_porcentaje'] = $ivaPorcentaje;
        $d['_bruto'] = $brutoLinea; $d['_desc'] = $desc; $d['_neto'] = $neto; $d['_iva'] = $ivaImporte; $d['_linea'] = $linea;
        $bruto += $brutoLinea; $descuentos += $desc; $subtotal += $neto; $iva += $ivaImporte; $total += $linea;
    }
    unset($d);
    return [$detalles, ['bruto' => $bruto, 'descuentos' => $descuentos, 'subtotal' => $subtotal, 'iva' => $iva, 'total' => $total]];
}

// Inserta las líneas de detalle ya calculadas y aplica el consumo de remito correspondiente
// (misma lógica que el bloque de detalle de guardarVentaFactura()).
function vfInsertarDetallesFactura($conexion, $factura_id, $detalles) {
    $sqlDet = "INSERT INTO gestion__ventas_facturas_detalles
        (venta_factura_id, producto_id, venta_remito_detalle_id, venta_pedido_detalle_id, cantidad,
         precio_unitario, descuento_general_pct, descuento_general, precio_unitario_neto, importe_neto,
         iva_alicuota_id, porcentaje_iva, importe_iva, importe_no_gravado, importe_exento, importe_linea, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 1)";
    foreach ($detalles as $d) {
        $stmt = mysqli_prepare($conexion, $sqlDet);
        $producto = intval($d['producto_id']);
        $remitoDetalle = intval($d['venta_remito_detalle_id'] ?? 0) ?: null;
        $pedidoDetalle = intval($d['venta_pedido_detalle_id'] ?? 0) ?: null;
        $cantidad = floatval($d['cantidad']); $precio = floatval($d['precio_unitario']); $descPct = floatval($d['descuento_general_pct'] ?? 0);
        $desc = $d['_desc']; $netoUnit = $d['_neto'] / max($cantidad, 0.0001); $neto = $d['_neto'];
        $ivaId = intval($d['iva_alicuota_id'] ?? 1); $ivaPct = floatval($d['iva_porcentaje'] ?? 0); $ivaLinea = $d['_iva']; $linea = $d['_linea'];
        mysqli_stmt_bind_param($stmt, 'iiiiddddddiddd', $factura_id, $producto, $remitoDetalle, $pedidoDetalle, $cantidad, $precio, $descPct, $desc, $netoUnit, $neto, $ivaId, $ivaPct, $ivaLinea, $linea);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        if ($remitoDetalle) {
            // "facturado" es un flag booleano (0/1), no el id de la factura — bindearlo con
            // $factura_id (como estaba antes) dejaba ese id guardado ahí en vez de 1.
            $upd = mysqli_prepare($conexion, "UPDATE gestion__ventas_remitos_detalles SET facturado = IF(cantidad_facturada + ? >= cantidad, 1, 0), cantidad_facturada = cantidad_facturada + ? WHERE venta_remito_detalle_id = ? AND cantidad_facturada + ? <= cantidad");
            mysqli_stmt_bind_param($upd, 'ddid', $cantidad, $cantidad, $remitoDetalle, $cantidad);
            if (!mysqli_stmt_execute($upd) || mysqli_stmt_affected_rows($upd) !== 1) {
                throw new Exception('La cantidad seleccionada supera el pendiente del remito.');
            }
            mysqli_stmt_close($upd);
        }
    }
}

function guardarVentaFactura($conexion, $empresa_id, $pagina_id, $data) {
    $detalles = $data['detalles'] ?? [];
    if (!$data['entidad_id'] || count($detalles) === 0) return ['success' => false, 'error' => 'Seleccione un cliente y agregue al menos un producto.'];
    mysqli_begin_transaction($conexion);
    try {
        $cond = vfObtenerCondicionCliente($conexion, intval($data['entidad_id']), $empresa_id);
        $condicion_pago_id = intval(($data['condicion_pago_id'] ?? null) ?: ($cond['condicion_pago_id'] ?? 0));
        $stmt_tipo = mysqli_prepare($conexion, "SELECT comprobante_fiscal_id FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? AND empresa_id = ? LIMIT 1");
        $tipo_id = intval($data['comprobante_tipo_id']);
        mysqli_stmt_bind_param($stmt_tipo, 'ii', $tipo_id, $empresa_id);
        mysqli_stmt_execute($stmt_tipo);
        $tipo_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tipo));
        mysqli_stmt_close($stmt_tipo);
        $comprobante_fiscal_id = intval($tipo_row['comprobante_fiscal_id'] ?? 0);
        $estado = vfEstadoInicial($conexion, $pagina_id);
        $f_emision = ($data['f_emision'] ?? null) ?: date('Y-m-d');
        $f_vto = ($data['f_vto'] ?? null) ?: null;
        $punto_venta_id = intval($data['punto_venta_id']);
        // La numeración fiscal NO se asigna acá: se asigna recién cuando la factura sale de
        // Borrador (acción 'confirma', ver vfEjecutarTransicionEstado), igual que en
        // ventas_pedidos. Si se numerara al alta, cada borrador descartado dejaría un
        // hueco en la numeración correlativa.
        $comprobante_nro = 0;
        $tipo_cambio = floatval(($data['tipo_cambio'] ?? null) ?: 1);

        list($detalles, $tot) = vfCalcularDetallesFactura($detalles, $comprobante_fiscal_id);
        $bruto = $tot['bruto']; $descuentos = $tot['descuentos']; $subtotal = $tot['subtotal']; $iva = $tot['iva']; $total = $tot['total'];

        $sql = "INSERT INTO gestion__ventas_facturas
                (empresa_id, sucursal_id, comprobante_tipo_id, punto_venta_id, comprobante_nro,
                 comprobante_id, entidad_id, entidad_sucursal_id, condicion_pago_id, f_emision,
                 f_contabilidad, f_vto, moneda_id, tipo_cambio, importe_bruto, descuento_general_pct,
                 descuento_general, importe_neto, importe_exento, importe_no_gravado, importe_iva,
                 importe_otros_impuestos, importe_total, observaciones, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        $sucursal = intval($data['sucursal_id']); $tipo = $tipo_id; $pv = $punto_venta_id;
        $nro = $comprobante_nro; $entidad = intval($data['entidad_id']); $entidadSucursal = intval(($data['entidad_sucursal_id'] ?? null) ?: 0);
        $moneda = intval(($data['moneda_id'] ?? null) ?: 1); $pct = floatval(($data['descuento_general_pct'] ?? null) ?: 0); $obs = trim($data['observaciones'] ?? '');
        mysqli_stmt_bind_param($stmt, 'iiiiiiiisssidddddddsi', $empresa_id, $sucursal, $tipo, $pv, $nro, $entidad, $entidadSucursal, $condicion_pago_id, $f_emision, $f_emision, $f_vto, $moneda, $tipo_cambio, $bruto, $pct, $descuentos, $subtotal, $iva, $total, $obs, $estado);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        $factura_id = mysqli_insert_id($conexion); mysqli_stmt_close($stmt);

        // Sincronizar con gestion__comprobantes (mismo patrón que ventas_pedidos/facturas_proveedores):
        // alimenta cuenta corriente y contabilidad. comprobante_nro va en 0 hasta que se confirme.
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        $comprobante_data = [
            'empresa_id' => $empresa_id, 'sucursal_id' => $sucursal, 'comprobante_pv' => $pv,
            'comprobante_tipo_id' => $tipo, 'comprobante_nro' => $nro,
            'entidad_id' => $entidad, 'entidad_sucursal_id' => $entidadSucursal,
            'f_emision' => $f_emision, 'f_contabilidad' => $f_emision, 'f_vto' => $f_vto,
            'moneda_id' => $moneda, 'tipo_cambio' => $tipo_cambio,
            'registro_origen_id' => $factura_id, 'tabla_estado_registro_id' => $estado,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0, 'observaciones' => $obs,
            'importe_neto' => $subtotal, 'descuento_general' => $descuentos,
            'importe_no_gravado' => 0, 'importe_exento' => 0,
            'importe_iva' => $iva, 'importe_otros_impuestos' => 0, 'importe_total' => $total
        ];
        $comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) throw new Exception('No se pudo sincronizar el comprobante.');
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_id = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $factura_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        vfInsertarDetallesFactura($conexion, $factura_id, $detalles);

        mysqli_commit($conexion);
        return ['success' => true, 'venta_factura_id' => $factura_id, 'message' => 'Factura guardada correctamente.'];
    } catch (Exception $e) { mysqli_rollback($conexion); return ['success' => false, 'error' => $e->getMessage()]; }
}

function vfEditarVentaFactura($conexion, $id, $empresa_id, $pagina_id, $data) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__ventas_facturas WHERE venta_factura_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$factura) return ['success' => false, 'error' => 'Factura no encontrada.'];

    $estado_actual = intval($factura['tabla_estado_registro_id']);
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = ? AND accion_js = 'editar' AND tabla_estado_registro_origen_id = ? AND tabla_estado_registro_id = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $pagina_id, $estado_actual);
    mysqli_stmt_execute($stmt);
    $permitido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$permitido) return ['success' => false, 'error' => 'La factura no se puede editar en su estado actual.'];

    $detalles = $data['detalles'] ?? [];
    if (empty($data['entidad_id']) || count($detalles) === 0) {
        return ['success' => false, 'error' => 'Seleccione un cliente y agregue al menos un producto.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // Libera lo que esta factura tenía consumido de los remitos antes de recalcular,
        // así el detalle nuevo (que puede repetir las mismas líneas con otra cantidad) no
        // choca contra su propio consumo anterior.
        vfLiberarRemitosDeFactura($conexion, $id);

        $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__ventas_facturas_detalles WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $cond = vfObtenerCondicionCliente($conexion, intval($data['entidad_id']), $empresa_id);
        $condicion_pago_id = intval(($data['condicion_pago_id'] ?? null) ?: ($cond['condicion_pago_id'] ?? 0));
        $tipo_id = intval($data['comprobante_tipo_id']);
        $stmt_tipo = mysqli_prepare($conexion, "SELECT comprobante_fiscal_id FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? AND empresa_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_tipo, 'ii', $tipo_id, $empresa_id);
        mysqli_stmt_execute($stmt_tipo);
        $tipo_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tipo));
        mysqli_stmt_close($stmt_tipo);
        $comprobante_fiscal_id = intval($tipo_row['comprobante_fiscal_id'] ?? 0);

        $f_emision = ($data['f_emision'] ?? null) ?: date('Y-m-d');
        $f_vto = ($data['f_vto'] ?? null) ?: null;
        $punto_venta_id = intval($data['punto_venta_id']);
        $tipo_cambio = floatval(($data['tipo_cambio'] ?? null) ?: 1);

        list($detalles, $tot) = vfCalcularDetallesFactura($detalles, $comprobante_fiscal_id);
        $bruto = $tot['bruto']; $descuentos = $tot['descuentos']; $subtotal = $tot['subtotal']; $iva = $tot['iva']; $total = $tot['total'];

        $sucursal = intval($data['sucursal_id']); $entidad = intval($data['entidad_id']); $entidadSucursal = intval(($data['entidad_sucursal_id'] ?? null) ?: 0);
        $moneda = intval(($data['moneda_id'] ?? null) ?: 1); $pct = floatval(($data['descuento_general_pct'] ?? null) ?: 0); $obs = trim($data['observaciones'] ?? '');

        $sql = "UPDATE gestion__ventas_facturas SET
                    sucursal_id = ?, comprobante_tipo_id = ?, punto_venta_id = ?, entidad_id = ?, entidad_sucursal_id = ?,
                    condicion_pago_id = ?, f_emision = ?, f_contabilidad = ?, f_vto = ?, moneda_id = ?, tipo_cambio = ?,
                    importe_bruto = ?, descuento_general_pct = ?, descuento_general = ?, importe_neto = ?, importe_iva = ?,
                    importe_total = ?, observaciones = ?
                WHERE venta_factura_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param(
            $stmt, 'iiiiiisssidddddddsii',
            $sucursal, $tipo_id, $punto_venta_id, $entidad, $entidadSucursal,
            $condicion_pago_id, $f_emision, $f_emision, $f_vto, $moneda, $tipo_cambio,
            $bruto, $pct, $descuentos, $subtotal, $iva,
            $total, $obs, $id, $empresa_id
        );
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        vfInsertarDetallesFactura($conexion, $id, $detalles);

        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        $comprobante_data = [
            'empresa_id' => $empresa_id, 'sucursal_id' => $sucursal, 'comprobante_pv' => $punto_venta_id,
            'comprobante_tipo_id' => $tipo_id, 'comprobante_nro' => intval($factura['comprobante_nro']),
            'entidad_id' => $entidad, 'entidad_sucursal_id' => $entidadSucursal,
            'f_emision' => $f_emision, 'f_contabilidad' => $f_emision, 'f_vto' => $f_vto,
            'moneda_id' => $moneda, 'tipo_cambio' => $tipo_cambio,
            'registro_origen_id' => $id, 'tabla_estado_registro_id' => $estado_actual,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0, 'observaciones' => $obs,
            'importe_neto' => $subtotal, 'descuento_general' => $descuentos,
            'importe_no_gravado' => 0, 'importe_exento' => 0,
            'importe_iva' => $iva, 'importe_otros_impuestos' => 0, 'importe_total' => $total
        ];
        syncComprobante($conexion, $comprobante_data, $tabla_origen_id);

        mysqli_commit($conexion);
        return ['success' => true, 'venta_factura_id' => $id, 'message' => 'Factura actualizada correctamente.'];
    } catch (Exception $e) { mysqli_rollback($conexion); return ['success' => false, 'error' => $e->getMessage()]; }
}

function vfEjecutarTransicionEstado($conexion, $venta_factura_id, $accion_js, $empresa_id, $pagina_id) {
    $venta_factura_id = intval($venta_factura_id);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT * FROM gestion__ventas_facturas WHERE venta_factura_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$factura) return ['success' => false, 'error' => 'Factura no encontrada.'];

    $estado_actual_id = intval($factura['tabla_estado_registro_id']);

    $sql_funcion = "SELECT * FROM conf__paginas_funciones
                    WHERE pagina_id = ? AND tabla_estado_registro_origen_id = ? AND accion_js = ? AND tabla_estado_registro_id = 1
                    LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    mysqli_stmt_bind_param($stmt, 'iis', $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $funcion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$funcion) return ['success' => false, 'error' => 'Acción no permitida para el estado actual.'];

    $estado_destino_id = intval($funcion['tabla_estado_registro_destino_id']);
    if ($estado_destino_id === $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // Al eliminar/anular una factura, devolver al remito la cantidad que tenía tomada,
        // para que vuelva a estar disponible para facturar.
        if ($accion_js === 'eliminar') {
            vfLiberarRemitosDeFactura($conexion, $venta_factura_id);
        }

        $numero_asignado = null;
        // La numeración fiscal se asigna recién acá, al salir de Borrador (acción 'confirma'),
        // no en el alta — ver nota en guardarVentaFactura().
        if ($accion_js === 'confirma') {
            if (!empty($factura['comprobante_nro']) && intval($factura['comprobante_nro']) > 0) {
                $numero_asignado = intval($factura['comprobante_nro']);
            } else {
                if (empty($factura['punto_venta_id']) || empty($factura['comprobante_tipo_id'])) {
                    throw new Exception('La factura no tiene punto de venta o tipo de comprobante asignado.');
                }
                $numero_asignado = vfObtenerProximoNumero($conexion, $empresa_id, intval($factura['punto_venta_id']), intval($factura['comprobante_tipo_id']));
                $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_nro = ? WHERE venta_factura_id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $numero_asignado, $venta_factura_id);
                if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                $factura['comprobante_nro'] = $numero_asignado;
            }
            // Con la factura ya confirmada (documento firme, no un borrador descartable), revisar
            // si a los remitos de los que salieron estas líneas les queda algo pendiente de
            // facturar: si no les queda nada, pasan a Facturado.
            vfActualizarEstadoRemitosDeFactura($conexion, $venta_factura_id);
        }

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET tabla_estado_registro_id = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $estado_destino_id, $venta_factura_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');

        $comprobante_data = [
            'empresa_id' => $empresa_id, 'sucursal_id' => $factura['sucursal_id'], 'comprobante_pv' => $factura['punto_venta_id'],
            'comprobante_tipo_id' => $factura['comprobante_tipo_id'], 'comprobante_nro' => $factura['comprobante_nro'],
            'entidad_id' => $factura['entidad_id'], 'entidad_sucursal_id' => $factura['entidad_sucursal_id'],
            'f_emision' => $factura['f_emision'], 'f_contabilidad' => $factura['f_contabilidad'], 'f_vto' => $factura['f_vto'],
            'moneda_id' => $factura['moneda_id'], 'tipo_cambio' => $factura['tipo_cambio'],
            'registro_origen_id' => $venta_factura_id, 'tabla_estado_registro_id' => $estado_destino_id,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0, 'observaciones' => $factura['observaciones'],
            'importe_neto' => floatval($factura['importe_neto']), 'descuento_general' => floatval($factura['descuento_general']),
            'importe_no_gravado' => floatval($factura['importe_no_gravado']), 'importe_exento' => floatval($factura['importe_exento']),
            'importe_iva' => floatval($factura['importe_iva']), 'importe_otros_impuestos' => floatval($factura['importe_otros_impuestos']),
            'importe_total' => floatval($factura['importe_total'])
        ];
        $comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) throw new Exception('No se pudo sincronizar el comprobante.');
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_id = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $venta_factura_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        $mensaje = 'Estado actualizado correctamente';
        if ($numero_asignado) $mensaje .= ' - Número asignado: ' . $numero_asignado;
        return ['success' => true, 'message' => $mensaje, 'comprobante_nro' => $numero_asignado];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log('ERROR en vfEjecutarTransicionEstado: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
