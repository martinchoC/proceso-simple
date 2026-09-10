<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// ============================================================
// SUPUESTOS A VALIDAR CON EL ESQUEMA REAL (avisame si difieren):
// - Existe gestion__depositos(deposito_id, deposito_nombre, empresa_id, tabla_estado_registro_id),
//   mismo patrón que gestion__sucursales.
// - gestion__ventas_remitos.comprobante_pv guarda directamente el punto_venta_id elegido
//   (igual que ya hace gestion__comprobantes.comprobante_pv en syncComprobante() de
//   ventas_pedidos_model.php), no un número de PV separado. Por eso el combo "Punto de
//   Venta" del formulario sigue saliendo de gestion__puntos_venta filtrado por sucursal,
//   pero lo que se persiste en la cabecera del remito es ese mismo punto_venta_id.
// - "Pendiente de entrega" = gestion__ventas_pedidos_detalles.cantidad - cantidad_entregada,
//   tal cual pidió Pablo. Se excluyen pedidos cuyo estado tenga codigo_estandar = 'CANCELADO'.
// - La reversión de cantidad_entregada (al editar o al anular un remito) dispara cuando el
//   estado destino de la transición tiene codigo_estandar 'CANCELADO' o 'ANULADO'. Si en
//   conf__estados_registros usás otro código para "remito anulado", ajustar la comparación
//   en revertirCantidadEntregadaRemito()/ejecutarTransicionEstadoRemito().
// ============================================================


// ============================================================
// MOTOR GENÉRICO DE ESTADOS / BOTONES / NUMERACIÓN
// (copia funcional de ventas_pedidos_model.php: se duplica a propósito para no
//  introducir un include cruzado entre módulos, mismo criterio ya usado ahí)
// ============================================================

function obtenerFuncionesPaginaRemitos($conexion, $pagina_id)
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

function obtenerBotonesPorEstadoRemito($conexion, $pagina_id, $estado_actual_id)
{
    $funciones = obtenerFuncionesPaginaRemitos($conexion, $pagina_id);
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

function obtenerBotonAgregarRemito($conexion, $pagina_id)
{
    $funciones = obtenerFuncionesPaginaRemitos($conexion, $pagina_id);

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
        'nombre_funcion' => 'Nuevo Remito',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicialPaginaRemitos($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT pf.tabla_estado_registro_destino_id
            FROM conf__paginas_funciones pf
            WHERE pf.pagina_id = ?
            AND pf.tabla_estado_registro_origen_id = 0
            AND pf.accion_js = 'agregar'
            AND pf.tabla_estado_registro_id = 1
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta estado inicial (remitos): " . mysqli_error($conexion));
        return 1;
    }

    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row && $row['tabla_estado_registro_destino_id']) {
        return $row['tabla_estado_registro_destino_id'];
    }

    error_log("No se encontró configuración de estado inicial para pagina_id=$pagina_id (remitos), usando fallback: 1");
    return 1;
}

function obtenerTablaOrigenPorPaginaRemitos($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $sql = "SELECT tabla_id FROM conf__paginas WHERE pagina_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta tabla_id (remitos): " . mysqli_error($conexion));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row && !empty($row['tabla_id'])) {
        return (int)$row['tabla_id'];
    }

    error_log("No se encontró tabla_id para la página $pagina_id (remitos)");
    return null;
}

function obtenerCodigoEstandarEstado($conexion, $estado_registro_id)
{
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

    $sql = "SELECT $estado_column as estado_registro, codigo_estandar
            FROM conf__estados_registros
            WHERE estado_registro_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $info;
}

function obtenerProximoNumeroComprobanteRemito($conexion, $empresa_id, $punto_venta_id, $comprobante_tipo_id)
{
    $sql_check = "SELECT numerador_id, ultimo_numero
                  FROM gestion__comprobantes_numeradores
                  WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        error_log("Error preparando consulta numerador (remitos): " . mysqli_error($conexion));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $numerador = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($numerador) {
        $nuevo_numero = $numerador['ultimo_numero'] + 1;

        $sql_update = "UPDATE gestion__comprobantes_numeradores
                       SET ultimo_numero = ?
                       WHERE numerador_id = ?";

        $stmt_update = mysqli_prepare($conexion, $sql_update);
        if (!$stmt_update) {
            error_log("Error preparando update numerador (remitos): " . mysqli_error($conexion));
            return false;
        }

        mysqli_stmt_bind_param($stmt_update, "ii", $nuevo_numero, $numerador['numerador_id']);
        $success = mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        return $success ? $nuevo_numero : false;
    } else {
        $sql_insert = "INSERT INTO gestion__comprobantes_numeradores
                       (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero)
                       VALUES (?, ?, ?, 1)";

        $stmt_insert = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt_insert) {
            error_log("Error preparando insert numerador (remitos): " . mysqli_error($conexion));
            return false;
        }

        mysqli_stmt_bind_param($stmt_insert, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
        $success = mysqli_stmt_execute($stmt_insert);
        mysqli_stmt_close($stmt_insert);

        return $success ? 1 : false;
    }
}

// Copia funcional de syncComprobante() de ventas_pedidos_model.php (ver nota ahí sobre
// por qué se duplica en vez de compartir un helper).
function syncComprobanteRemito($conexion, $data, $tabla_origen_id)
{
    if (empty($tabla_origen_id)) {
        error_log("ERROR: tabla_origen_id no proporcionado (remitos)");
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
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 1);
    $usuario_id = intval($data['usuario_id'] ?? 0);
    $observaciones = trim($data['observaciones'] ?? '');

    // Un remito no factura por sí mismo, pero desde que persiste IVA y descuento general
    // por línea (igual que los pedidos), el comprobante sincronizado ahora también lleva
    // el neto y el IVA reales de esas líneas; siguen sin desglosarse acá descuento_general,
    // no_gravado ni exento a nivel comprobante (el remito no tiene esa apertura propia).
    $importe_neto = floatval($data['importe_neto'] ?? 0);
    $importe_iva = floatval($data['importe_iva'] ?? 0);
    $importe_total = $importe_neto + $importe_iva;

    if ($registro_origen_id <= 0) {
        error_log("ERROR: registro_origen_id inválido (remitos): $registro_origen_id");
        return null;
    }

    $sql_check = "SELECT comprobante_id FROM gestion__comprobantes
                  WHERE tabla_origen_id = ? AND registro_origen_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        error_log("Error preparando SELECT (remitos): " . mysqli_error($conexion));
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
                            empresa_id = ?, sucursal_id = ?, comprobante_pv = ?,
                            comprobante_tipo_id = ?, comprobante_nro = ?,
                            entidad_id = ?, entidad_sucursal_id = ?,
                            f_emision = ?, f_contabilidad = ?, f_vto = ?,
                            moneda_id = ?, tipo_cambio = ?,
                            importe_bruto = ?, descuento_general = 0,
                            importe_no_gravado = 0, importe_exento = 0,
                            importe_neto = ?, importe_iva = ?, importe_otros_impuestos = 0,
                            importe_total = ?, importe_pendiente = ?,
                            tabla_estado_registro_id = ?, observaciones = ?,
                            usuario_modificacion_id = ?
                        WHERE comprobante_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            error_log("Error preparando UPDATE (remitos): " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param(
            $stmt,
            "iiiiiiisssiddddddisii",
            $empresa_id,
            $sucursal_id,
            $comprobante_pv,
            $comprobante_tipo_id,
            $comprobante_nro,
            $entidad_id,
            $entidad_sucursal_id,
            $f_emision,
            $f_contabilidad,
            $f_vto,
            $moneda_id,
            $tipo_cambio,
            $importe_neto,
            $importe_neto,
            $importe_iva,
            $importe_total,
            $importe_total,
            $tabla_estado_registro_id,
            $observaciones,
            $usuario_id,
            $comprobante_id
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error ejecutando UPDATE (remitos): " . mysqli_stmt_error($stmt));
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
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,0,?,?,0,?,?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt) {
            error_log("Error preparando INSERT (remitos): " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param(
            $stmt,
            "iiiiiiisssiddddddiiiis",
            $empresa_id,
            $sucursal_id,
            $comprobante_pv,
            $comprobante_tipo_id,
            $comprobante_nro,
            $entidad_id,
            $entidad_sucursal_id,
            $f_emision,
            $f_contabilidad,
            $f_vto,
            $moneda_id,
            $tipo_cambio,
            $importe_neto,
            $importe_neto,
            $importe_iva,
            $importe_total,
            $importe_total,
            $tabla_origen_id,
            $registro_origen_id,
            $tabla_estado_registro_id,
            $usuario_id,
            $observaciones
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error ejecutando INSERT (remitos): " . mysqli_stmt_error($stmt));
            return null;
        }
        $comprobante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
    }

    return $comprobante_id;
}


// ============================================================
// LÓGICA ESPECÍFICA DE REMITOS: PENDIENTE DE PEDIDOS Y REVERSIÓN
// ============================================================

// Revierte (resta) la cantidad_entregada que un remito había aplicado sobre las líneas
// de pedido que tenía vinculadas. Se usa tanto al editar (revertir lo viejo antes de
// aplicar lo nuevo) como al anular un remito ya confirmado.
function revertirCantidadEntregadaRemito($conexion, $venta_remito_id)
{
    $venta_remito_id = intval($venta_remito_id);

    $sql = "SELECT venta_pedido_detalle_id, cantidad
            FROM gestion__ventas_remitos_detalles
            WHERE venta_remito_id = ? AND venta_pedido_detalle_id IS NOT NULL";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception("Error preparando consulta de reversión: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt, "i", $venta_remito_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $lineas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $lineas[] = $fila;
    }
    mysqli_stmt_close($stmt);

    foreach ($lineas as $linea) {
        $sql_upd = "UPDATE gestion__ventas_pedidos_detalles
                    SET cantidad_entregada = GREATEST(0, cantidad_entregada - ?)
                    WHERE venta_pedido_detalle_id = ?";
        $stmt_upd = mysqli_prepare($conexion, $sql_upd);
        if (!$stmt_upd) {
            throw new Exception("Error preparando reversión de cantidad_entregada: " . mysqli_error($conexion));
        }
        $cantidad = floatval($linea['cantidad']);
        $vpd_id = intval($linea['venta_pedido_detalle_id']);
        mysqli_stmt_bind_param($stmt_upd, "di", $cantidad, $vpd_id);
        if (!mysqli_stmt_execute($stmt_upd)) {
            throw new Exception("Error revirtiendo cantidad_entregada: " . mysqli_stmt_error($stmt_upd));
        }
        mysqli_stmt_close($stmt_upd);
    }
}

// Devuelve, agrupados por pedido, los pedidos de venta del cliente con líneas pendientes
// de entrega (cantidad > cantidad_entregada). Excluye pedidos cancelados. No filtra por
// entidad_sucursal_id: se listan todos los pendientes del cliente y el usuario elige a
// mano qué líneas remitir (un pedido puede haberse cargado sin sucursal específica).
function obtenerPedidosPendientesCliente($conexion, $empresa_idx, $entidad_id)
{
    $empresa_idx = intval($empresa_idx);
    $entidad_id = intval($entidad_id);

    $sql = "SELECT vp.venta_pedido_id, vp.comprobante_nro, vp.f_emision,
                   ct.comprobante_tipo,
                   vpd.venta_pedido_detalle_id, vpd.producto_id,
                   p.producto_codigo, p.producto_nombre,
                   vpd.cantidad, vpd.cantidad_entregada,
                   (vpd.cantidad - vpd.cantidad_entregada) as pendiente,
                   vpd.precio_unitario_bruto, vpd.descuento_general_pct, vpd.precio_unitario_neto,
                   vpd.iva_alicuota_id, vpd.iva_porcentaje
            FROM gestion__ventas_pedidos_detalles vpd
            INNER JOIN gestion__ventas_pedidos vp ON vpd.venta_pedido_id = vp.venta_pedido_id
            INNER JOIN gestion__productos p ON vpd.producto_id = p.producto_id
            LEFT JOIN gestion__comprobantes_tipos ct ON vp.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN conf__estados_registros er ON vp.tabla_estado_registro_id = er.estado_registro_id
            WHERE vp.entidad_id = ?
            AND vp.empresa_id = ?
            AND vpd.cantidad > vpd.cantidad_entregada
            AND (er.codigo_estandar IS NULL OR er.codigo_estandar != 'CANCELADO')
            ORDER BY vp.f_emision, vp.venta_pedido_id, vpd.venta_pedido_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta pedidos pendientes: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $pedidos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $pid = $fila['venta_pedido_id'];
        if (!isset($pedidos[$pid])) {
            $pedidos[$pid] = [
                'venta_pedido_id' => $pid,
                'comprobante_nro' => $fila['comprobante_nro'],
                'comprobante_tipo' => $fila['comprobante_tipo'],
                'f_emision' => $fila['f_emision'],
                'detalles' => []
            ];
        }
        $pedidos[$pid]['detalles'][] = [
            'venta_pedido_detalle_id' => $fila['venta_pedido_detalle_id'],
            'producto_id' => $fila['producto_id'],
            'producto_codigo' => $fila['producto_codigo'],
            'producto_nombre' => $fila['producto_nombre'],
            'cantidad' => floatval($fila['cantidad']),
            'cantidad_entregada' => floatval($fila['cantidad_entregada']),
            'pendiente' => floatval($fila['pendiente']),
            'precio_unitario_bruto' => floatval($fila['precio_unitario_bruto']),
            'descuento_general_pct' => floatval($fila['descuento_general_pct']),
            'precio_unitario_neto' => floatval($fila['precio_unitario_neto']),
            'iva_alicuota_id' => $fila['iva_alicuota_id'],
            'iva_porcentaje' => floatval($fila['iva_porcentaje'] ?? 0)
        ];
    }

    mysqli_stmt_close($stmt);
    return array_values($pedidos);
}


// ============================================================
// CRUD DE REMITOS
// ============================================================

function obtenerRemitosVenta($conexion, $empresa_idx, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $empresa_idx = intval($empresa_idx);

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

    $sql = "SELECT vr.*,
                   er.$estado_column as estado_registro,
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia,
                   s.sucursal_nombre,
                   dep.deposito_nombre,
                   pv.nombre as punto_venta_nombre, pv.codigo_fiscal as punto_venta_codigo,
                   COALESCE(td.total, 0) as total
            FROM gestion__ventas_remitos vr
            LEFT JOIN conf__estados_registros er ON vr.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__comprobantes_tipos ct ON vr.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON vr.entidad_id = e.entidad_id
            LEFT JOIN gestion__sucursales s ON vr.sucursal_id = s.sucursal_id AND s.empresa_id = vr.empresa_id
            LEFT JOIN gestion__depositos dep ON vr.deposito_id = dep.deposito_id AND dep.empresa_id = vr.empresa_id
            LEFT JOIN gestion__puntos_venta pv ON vr.comprobante_pv = pv.punto_venta_id AND pv.empresa_id = vr.empresa_id
            LEFT JOIN (
                SELECT venta_remito_id, SUM(importe_linea + iva_importe) as total
                FROM gestion__ventas_remitos_detalles
                WHERE tabla_estado_registro_id = 1
                GROUP BY venta_remito_id
            ) td ON td.venta_remito_id = vr.venta_remito_id
            WHERE vr.empresa_id = ?
            ORDER BY vr.f_emision DESC, vr.venta_remito_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando listado de remitos: " . mysqli_error($conexion));
        return [];
    }

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

        $fila['botones'] = obtenerBotonesPorEstadoRemito($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

function obtenerRemitoVentaPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT vr.*,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia,
                   dep.deposito_nombre,
                   pv.nombre as punto_venta_nombre
            FROM gestion__ventas_remitos vr
            LEFT JOIN gestion__comprobantes_tipos ct ON vr.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON vr.entidad_id = e.entidad_id
            LEFT JOIN gestion__depositos dep ON vr.deposito_id = dep.deposito_id
            LEFT JOIN gestion__puntos_venta pv ON vr.comprobante_pv = pv.punto_venta_id AND pv.empresa_id = vr.empresa_id
            WHERE vr.venta_remito_id = ? AND vr.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $remito = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$remito) return null;

    // pendiente_disponible: la pendiente "de verdad" sumando de nuevo lo que esta misma
    // línea ya restó de cantidad_entregada, para poder subir la cantidad al editar sin
    // que el propio remito se cuente a sí mismo como "ya entregado".
    // El IVA y el descuento general SÍ se persisten en gestion__ventas_remitos_detalles
    // (precio_unitario_bruto, descuento_general_pct, descuento_general, precio_unitario_neto,
    // iva_alicuota_id, iva_porcentaje, iva_importe), reflejando lo que se aplicó realmente
    // al remitir. Por eso se leen directo de rd.* y NO se vuelven a traer del producto/lista
    // de precios actuales, que pueden haber cambiado desde entonces.
    $sql_detalles = "SELECT rd.*, p.producto_codigo, p.producto_nombre,
                            vp.venta_pedido_id, vp.comprobante_nro as pedido_comprobante_nro,
                            vpd.cantidad as pedido_cantidad, vpd.cantidad_entregada as pedido_cantidad_entregada,
                            (vpd.cantidad - vpd.cantidad_entregada + rd.cantidad) as pendiente_disponible
                     FROM gestion__ventas_remitos_detalles rd
                     LEFT JOIN gestion__productos p ON rd.producto_id = p.producto_id
                     LEFT JOIN gestion__ventas_pedidos_detalles vpd ON rd.venta_pedido_detalle_id = vpd.venta_pedido_detalle_id
                     LEFT JOIN gestion__ventas_pedidos vp ON vpd.venta_pedido_id = vp.venta_pedido_id
                     WHERE rd.venta_remito_id = ?
                     ORDER BY rd.venta_remito_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt) return $remito;

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $detalles = [];
    while ($detalle = mysqli_fetch_assoc($result)) {
        $detalles[] = [
            'venta_remito_detalle_id' => $detalle['venta_remito_detalle_id'],
            'producto_id' => $detalle['producto_id'],
            'producto_codigo' => $detalle['producto_codigo'],
            'producto_nombre' => $detalle['producto_nombre'],
            'venta_pedido_detalle_id' => $detalle['venta_pedido_detalle_id'],
            'venta_pedido_id' => $detalle['venta_pedido_id'],
            'pedido_comprobante_nro' => $detalle['pedido_comprobante_nro'],
            'cantidad' => floatval($detalle['cantidad']),
            'precio_unitario_bruto' => floatval($detalle['precio_unitario_bruto']),
            'descuento_general_pct' => floatval($detalle['descuento_general_pct']),
            'descuento_general' => floatval($detalle['descuento_general']),
            'precio_unitario_neto' => floatval($detalle['precio_unitario_neto']),
            'importe_linea' => floatval($detalle['importe_linea']),
            'iva_alicuota_id' => $detalle['iva_alicuota_id'],
            'iva_porcentaje' => floatval($detalle['iva_porcentaje'] ?? 0),
            'iva_importe' => floatval($detalle['iva_importe'] ?? 0),
            'pendiente_disponible' => $detalle['venta_pedido_detalle_id']
                ? floatval($detalle['pendiente_disponible'])
                : null
        ];
    }
    mysqli_stmt_close($stmt);

    $remito['detalles'] = $detalles;
    return $remito;
}

function agregarRemitoVenta($conexion, $data)
{
    if (!$conexion) {
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }
    if (empty($data['f_emision'])) {
        return ['resultado' => false, 'error' => 'La fecha de emisión es obligatoria'];
    }
    if (empty($data['entidad_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un cliente'];
    }
    if (empty($data['comprobante_tipo_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el tipo de comprobante'];
    }
    if (empty($data['sucursal_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la sucursal'];
    }
    if (empty($data['deposito_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el depósito'];
    }
    if (empty($data['punto_venta_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el punto de venta'];
    }
    if (!isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
        return ['resultado' => false, 'error' => 'Debe agregar al menos un producto al remito'];
    }

    mysqli_begin_transaction($conexion);

    try {
        $empresa_id_val = intval($data['empresa_idx']);
        $estado_inicial_val = intval(obtenerEstadoInicialPaginaRemitos($conexion, $data['pagina_idx']) ?: 1);

        $sucursal_id_val = intval($data['sucursal_id']);
        $deposito_id_val = intval($data['deposito_id']);
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_pv_val = intval($data['punto_venta_id']); // ver nota de supuestos arriba
        $comprobante_nro_val = 0;
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0)
            ? intval($data['entidad_sucursal_id']) : null;
        $f_emision_val = $data['f_emision'];
        $observaciones_val = isset($data['observaciones']) ? trim($data['observaciones']) : '';

        $sql = "INSERT INTO gestion__ventas_remitos
                (empresa_id, sucursal_id, deposito_id, comprobante_tipo_id, comprobante_pv,
                 comprobante_nro, comprobante_id, f_emision, entidad_id, entidad_sucursal_id,
                 observaciones, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert de remito: " . mysqli_error($conexion));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "iiiiiisiisi",
            $empresa_id_val,
            $sucursal_id_val,
            $deposito_id_val,
            $comprobante_tipo_id_val,
            $comprobante_pv_val,
            $comprobante_nro_val,
            $f_emision_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $observaciones_val,
            $estado_inicial_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert de remito: " . mysqli_stmt_error($stmt));
        }
        $venta_remito_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        $importes = insertarDetallesRemito($conexion, $venta_remito_id, $entidad_id_val, $empresa_id_val, $data['detalles']);

        $tabla_origen_id = obtenerTablaOrigenPorPaginaRemitos($conexion, $data['pagina_idx']);
        if (!$tabla_origen_id) {
            throw new Exception("No se pudo determinar la tabla de origen del remito");
        }

        $comprobante_data = [
            'empresa_id' => $empresa_id_val,
            'sucursal_id' => $sucursal_id_val,
            'comprobante_pv' => $comprobante_pv_val,
            'comprobante_tipo_id' => $comprobante_tipo_id_val,
            'comprobante_nro' => $comprobante_nro_val,
            'entidad_id' => $entidad_id_val,
            'entidad_sucursal_id' => $entidad_sucursal_id_val,
            'f_emision' => $f_emision_val,
            'f_contabilidad' => $f_emision_val,
            'moneda_id' => 1,
            'tipo_cambio' => 1,
            'registro_origen_id' => $venta_remito_id,
            'tabla_estado_registro_id' => $estado_inicial_val,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0,
            'observaciones' => $observaciones_val,
            'importe_neto' => $importes['importe_neto'],
            'importe_iva' => $importes['importe_iva']
        ];

        $comprobante_id = syncComprobanteRemito($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) {
            throw new Exception("Error al sincronizar el comprobante del remito");
        }

        $sql_upd = "UPDATE gestion__ventas_remitos SET comprobante_id = ? WHERE venta_remito_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $venta_remito_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['resultado' => true, 'venta_remito_id' => $venta_remito_id, 'comprobante_id' => $comprobante_id];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarRemitoVenta: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarRemitoVenta($conexion, $id, $data)
{
    $id = intval($id);

    mysqli_begin_transaction($conexion);

    try {
        $sql_check = "SELECT comprobante_nro, tabla_estado_registro_id, comprobante_id
                      FROM gestion__ventas_remitos WHERE venta_remito_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $actual = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$actual) {
            throw new Exception("Remito no encontrado");
        }
        if (!empty($actual['comprobante_nro']) && $actual['comprobante_nro'] > 0) {
            throw new Exception("El remito ya fue confirmado/numerado. Solo se puede anular, no editar.");
        }

        // Revertir lo que este remito ya había descontado de los pedidos, antes de
        // volver a aplicar (y validar) los detalles nuevos.
        revertirCantidadEntregadaRemito($conexion, $id);

        $sql_delete = "DELETE FROM gestion__ventas_remitos_detalles WHERE venta_remito_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_delete);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error eliminando detalles existentes: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        $empresa_idx_val = intval($data['empresa_idx']);
        $sucursal_id_val = intval($data['sucursal_id']);
        $deposito_id_val = intval($data['deposito_id']);
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_pv_val = intval($data['punto_venta_id']);
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0)
            ? intval($data['entidad_sucursal_id']) : null;
        $f_emision_val = $data['f_emision'];
        $observaciones_val = isset($data['observaciones']) ? trim($data['observaciones']) : '';

        $sql = "UPDATE gestion__ventas_remitos
                SET sucursal_id = ?, deposito_id = ?, comprobante_tipo_id = ?, comprobante_pv = ?,
                    f_emision = ?, entidad_id = ?, entidad_sucursal_id = ?, observaciones = ?
                WHERE venta_remito_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update de remito: " . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param(
            $stmt,
            "iiiisiisii",
            $sucursal_id_val,
            $deposito_id_val,
            $comprobante_tipo_id_val,
            $comprobante_pv_val,
            $f_emision_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $observaciones_val,
            $id,
            $empresa_idx_val
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update de remito: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        if (!isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            throw new Exception("Debe haber al menos un producto en el remito");
        }
        $importes = insertarDetallesRemito($conexion, $id, $entidad_id_val, $empresa_idx_val, $data['detalles']);

        $tabla_origen_id = obtenerTablaOrigenPorPaginaRemitos($conexion, $data['pagina_idx'] ?? 0);
        if ($tabla_origen_id) {
            $comprobante_data = [
                'empresa_id' => $empresa_idx_val,
                'sucursal_id' => $sucursal_id_val,
                'comprobante_pv' => $comprobante_pv_val,
                'comprobante_tipo_id' => $comprobante_tipo_id_val,
                'comprobante_nro' => 0,
                'entidad_id' => $entidad_id_val,
                'entidad_sucursal_id' => $entidad_sucursal_id_val,
                'f_emision' => $f_emision_val,
                'f_contabilidad' => $f_emision_val,
                'moneda_id' => 1,
                'tipo_cambio' => 1,
                'registro_origen_id' => $id,
                'tabla_estado_registro_id' => $actual['tabla_estado_registro_id'],
                'usuario_id' => $_SESSION['usuario_id'] ?? 0,
                'observaciones' => $observaciones_val,
                'importe_neto' => $importes['importe_neto'],
                'importe_iva' => $importes['importe_iva']
            ];
            syncComprobanteRemito($conexion, $comprobante_data, $tabla_origen_id);
        }

        mysqli_commit($conexion);
        return ['resultado' => true, 'message' => 'Remito actualizado correctamente'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarRemitoVenta: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

// Inserta los detalles de un remito. Por cada línea con venta_pedido_detalle_id
// (viene de un pedido pendiente) toma un lock (FOR UPDATE), valida que pertenezca al
// mismo cliente/empresa y que la cantidad a remitir no supere el pendiente real, y
// actualiza cantidad_entregada en la misma transacción. Las líneas sin
// venta_pedido_detalle_id son productos sacados sin pedido y no tocan pedidos.
//
// IVA y descuento general (agregado a pedido de Pablo, mismo criterio que
// gestion__ventas_pedidos_detalles): para una línea CON pedido, precio_unitario_bruto,
// descuento_general_pct, iva_alicuota_id e iva_porcentaje se toman siempre de la línea
// de pedido (autoridad, tomada en el mismo FOR UPDATE que ya valida el pendiente) y NO
// de lo que mande el navegador. Para una línea SIN pedido se toma lo que vino de la
// búsqueda de productos (lista de precios + condición comercial vigente del cliente en
// ese momento). Con eso se recalculan siempre acá: descuento_general (por unidad),
// precio_unitario_neto, importe_linea (neto gravado, cantidad*precio_unitario_neto) e
// iva_importe (importe_linea*iva_porcentaje/100).
// Devuelve los totales netos e IVA acumulados, usados como base informativa del comprobante.
function insertarDetallesRemito($conexion, $venta_remito_id, $entidad_id, $empresa_id, $detalles)
{
    if (!is_array($detalles) || count($detalles) === 0) {
        throw new Exception("Debe agregar al menos un producto al remito");
    }

    $importe_neto_total = 0;
    $importe_iva_total = 0;

    foreach ($detalles as $detalle) {
        if (empty($detalle['producto_id'])) {
            throw new Exception("Hay una línea sin producto seleccionado");
        }
        $cantidad = floatval($detalle['cantidad'] ?? 0);
        if ($cantidad <= 0) {
            throw new Exception("La cantidad de cada línea debe ser mayor a 0");
        }

        $venta_pedido_detalle_id = !empty($detalle['venta_pedido_detalle_id'])
            ? intval($detalle['venta_pedido_detalle_id']) : null;

        // Valores por defecto: lo que mandó el navegador (línea "sin pedido").
        $precio_unitario_bruto = floatval($detalle['precio_unitario_bruto'] ?? 0);
        $descuento_general_pct = floatval($detalle['descuento_general_pct'] ?? 0);
        $iva_alicuota_id = !empty($detalle['iva_alicuota_id']) ? intval($detalle['iva_alicuota_id']) : null;
        $iva_porcentaje = floatval($detalle['iva_porcentaje'] ?? 0);

        if ($venta_pedido_detalle_id) {
            $sql_lock = "SELECT vpd.cantidad, vpd.cantidad_entregada, vp.entidad_id, vp.empresa_id,
                                p.producto_nombre,
                                vpd.precio_unitario_bruto, vpd.descuento_general_pct,
                                vpd.iva_alicuota_id, vpd.iva_porcentaje
                         FROM gestion__ventas_pedidos_detalles vpd
                         INNER JOIN gestion__ventas_pedidos vp ON vpd.venta_pedido_id = vp.venta_pedido_id
                         INNER JOIN gestion__productos p ON vpd.producto_id = p.producto_id
                         WHERE vpd.venta_pedido_detalle_id = ?
                         FOR UPDATE";
            $stmt_lock = mysqli_prepare($conexion, $sql_lock);
            if (!$stmt_lock) {
                throw new Exception("Error preparando validación de pedido: " . mysqli_error($conexion));
            }
            mysqli_stmt_bind_param($stmt_lock, "i", $venta_pedido_detalle_id);
            mysqli_stmt_execute($stmt_lock);
            $result_lock = mysqli_stmt_get_result($stmt_lock);
            $linea_pedido = mysqli_fetch_assoc($result_lock);
            mysqli_stmt_close($stmt_lock);

            if (!$linea_pedido) {
                throw new Exception("La línea de pedido seleccionada ya no existe");
            }
            if (intval($linea_pedido['empresa_id']) !== intval($empresa_id) || intval($linea_pedido['entidad_id']) !== intval($entidad_id)) {
                throw new Exception("La línea de pedido seleccionada no corresponde al cliente del remito");
            }

            $pendiente = floatval($linea_pedido['cantidad']) - floatval($linea_pedido['cantidad_entregada']);
            if ($cantidad > $pendiente + 0.0001) {
                throw new Exception(sprintf(
                    "La cantidad a remitir de \"%s\" (%s) supera el pendiente del pedido (%s)",
                    $linea_pedido['producto_nombre'],
                    $cantidad,
                    $pendiente
                ));
            }

            // Autoridad: se pisa lo que mandó el navegador con lo que dice el pedido.
            $precio_unitario_bruto = floatval($linea_pedido['precio_unitario_bruto']);
            $descuento_general_pct = floatval($linea_pedido['descuento_general_pct']);
            $iva_alicuota_id = $linea_pedido['iva_alicuota_id'] !== null ? intval($linea_pedido['iva_alicuota_id']) : null;
            $iva_porcentaje = floatval($linea_pedido['iva_porcentaje']);

            $sql_upd = "UPDATE gestion__ventas_pedidos_detalles
                        SET cantidad_entregada = cantidad_entregada + ?
                        WHERE venta_pedido_detalle_id = ?";
            $stmt_upd = mysqli_prepare($conexion, $sql_upd);
            mysqli_stmt_bind_param($stmt_upd, "di", $cantidad, $venta_pedido_detalle_id);
            if (!mysqli_stmt_execute($stmt_upd)) {
                throw new Exception("Error actualizando cantidad_entregada: " . mysqli_stmt_error($stmt_upd));
            }
            mysqli_stmt_close($stmt_upd);
        }

        $descuento_general = $precio_unitario_bruto * ($descuento_general_pct / 100); // por unidad
        $precio_unitario_neto = $precio_unitario_bruto - $descuento_general;
        $importe_linea = round($cantidad * $precio_unitario_neto, 6); // neto gravado, sin IVA
        $iva_importe = round($importe_linea * ($iva_porcentaje / 100), 2);

        $sql_insert = "INSERT INTO gestion__ventas_remitos_detalles
                       (venta_remito_id, producto_id, venta_pedido_detalle_id, cantidad,
                        precio_unitario_bruto, descuento_general_pct, descuento_general, precio_unitario_neto,
                        importe_linea, iva_alicuota_id, iva_porcentaje, iva_importe,
                        tabla_estado_registro_id)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt_insert = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt_insert) {
            throw new Exception("Error preparando insert de detalle: " . mysqli_error($conexion));
        }
        $producto_id = intval($detalle['producto_id']);
        mysqli_stmt_bind_param(
            $stmt_insert,
            "iiiddddddidd",
            $venta_remito_id,
            $producto_id,
            $venta_pedido_detalle_id,
            $cantidad,
            $precio_unitario_bruto,
            $descuento_general_pct,
            $descuento_general,
            $precio_unitario_neto,
            $importe_linea,
            $iva_alicuota_id,
            $iva_porcentaje,
            $iva_importe
        );
        if (!mysqli_stmt_execute($stmt_insert)) {
            throw new Exception("Error insertando detalle del remito: " . mysqli_stmt_error($stmt_insert));
        }
        mysqli_stmt_close($stmt_insert);

        $importe_neto_total += $importe_linea;
        $importe_iva_total += $iva_importe;
    }

    return ['importe_neto' => $importe_neto_total, 'importe_iva' => $importe_iva_total];
}

function ejecutarTransicionEstadoRemito($conexion, $venta_remito_id, $accion_js, $empresa_idx, $pagina_id)
{
    $venta_remito_id = intval($venta_remito_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT venta_remito_id, tabla_estado_registro_id, comprobante_nro,
                         comprobante_tipo_id, comprobante_pv, empresa_id
                  FROM gestion__ventas_remitos
                  WHERE venta_remito_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta'];
    }
    mysqli_stmt_bind_param($stmt, "i", $venta_remito_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $remito = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$remito) {
        return ['success' => false, 'error' => 'Registro no encontrado'];
    }

    $estado_actual_id = $remito['tabla_estado_registro_id'];

    $sql_funcion = "SELECT pf.*
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ?
                    AND pf.tabla_estado_registro_origen_id = ?
                    AND pf.accion_js = ?
                    LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta'];
    }
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcion) {
        error_log("Acción no permitida (remitos) - página: $pagina_id, estado origen: $estado_actual_id, acción: $accion_js");
        return ['success' => false, 'error' => 'Acción no permitida para este estado'];
    }

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];

    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    mysqli_begin_transaction($conexion);

    try {
        $numero_asignado = null;

        if ($accion_js === 'confirmar') {
            if (empty($remito['comprobante_pv'])) {
                throw new Exception('El remito no tiene punto de venta asignado. No se puede numerar.');
            }

            if (!empty($remito['comprobante_nro']) && $remito['comprobante_nro'] > 0) {
                $numero_asignado = $remito['comprobante_nro'];
            } else {
                $proximo_numero = obtenerProximoNumeroComprobanteRemito(
                    $conexion,
                    $remito['empresa_id'],
                    $remito['comprobante_pv'],
                    $remito['comprobante_tipo_id']
                );
                if ($proximo_numero === false) {
                    throw new Exception('Error al obtener el próximo número de comprobante');
                }

                $sql_update_numero = "UPDATE gestion__ventas_remitos SET comprobante_nro = ? WHERE venta_remito_id = ?";
                $stmt_numero = mysqli_prepare($conexion, $sql_update_numero);
                mysqli_stmt_bind_param($stmt_numero, "ii", $proximo_numero, $venta_remito_id);
                if (!mysqli_stmt_execute($stmt_numero)) {
                    throw new Exception('Error actualizando número: ' . mysqli_stmt_error($stmt_numero));
                }
                mysqli_stmt_close($stmt_numero);

                $numero_asignado = $proximo_numero;
            }
        }

        // Si el estado destino es una cancelación/anulación, liberar el pendiente que
        // este remito le había descontado a los pedidos de origen.
        $info_destino = obtenerCodigoEstandarEstado($conexion, $estado_destino_id);
        $codigo_destino = $info_destino['codigo_estandar'] ?? '';
        if (in_array($codigo_destino, ['CANCELADO', 'ANULADO'])) {
            revertirCantidadEntregadaRemito($conexion, $venta_remito_id);
        }

        $sql_update = "UPDATE gestion__ventas_remitos SET tabla_estado_registro_id = ? WHERE venta_remito_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $venta_remito_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error actualizando estado: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);

        $mensaje = 'Estado actualizado correctamente';
        if ($numero_asignado) {
            $mensaje .= ' - Número asignado: ' . $numero_asignado;
        }

        return ['success' => true, 'message' => $mensaje, 'comprobante_nro' => $numero_asignado];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en ejecutarTransicionEstadoRemito: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


// ============================================================
// COMBOS Y CATÁLOGOS (reutilizan la misma lógica de ventas_pedidos_model.php,
// duplicados acá por el mismo criterio de no cruzar includes entre módulos)
// ============================================================

function obtenerComprobantesTiposRemitos($conexion)
{
    // A pedido de Pablo: sin filtrar por grupo/subgrupo, se muestran todos los tipos
    // activos y él elige a mano cuál corresponde a "Remito".
    $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra
            FROM gestion__comprobantes_tipos
            WHERE tabla_estado_registro_id = 1
            ORDER BY comprobante_tipo";

    $result = mysqli_query($conexion, $sql);
    if (!$result) return [];

    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    return $tipos;
}

function obtenerDepositosEmpresa($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT deposito_id, deposito_nombre
            FROM gestion__depositos
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1
            ORDER BY deposito_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta de depósitos: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $depositos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $depositos[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $depositos;
}

function obtenerSucursalesEmpresaRemitos($conexion, $empresa_idx)
{
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

function obtenerClientesRemitos($conexion, $empresa_idx)
{
    $sql = "SELECT entidad_id, entidad_nombre, entidad_fantasia
            FROM gestion__entidades
            WHERE empresa_id = ?
            AND es_cliente = 1
            AND tabla_estado_registro_id = 1
            ORDER BY entidad_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $clientes = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $clientes[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $clientes;
}

function obtenerSucursalesClienteRemitos($conexion, $entidad_id, $empresa_idx)
{
    $entidad_id = intval($entidad_id);

    $sql = "SELECT sucursal_id, sucursal_nombre
            FROM gestion__entidades_sucursales
            WHERE entidad_id = ?
            AND empresa_id = ?
            AND tabla_estado_registro_id = 1
            ORDER BY sucursal_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $sucursales = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $sucursales[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $sucursales;
}

// Misma fuente que ventas_pedidos: lista de precios vigente del cliente
// (gestion__entidades_condiciones_clientes), necesaria para poder buscar y agregar
// productos "sin pedido" con un precio de referencia coherente.
function obtenerListaPrecioVigenteClienteRemitos($conexion, $entidad_id)
{
    $entidad_id = intval($entidad_id);

    $sql = "SELECT ecc.lista_precio_id, ecc.cliente_descuento_general
            FROM gestion__entidades_condiciones_clientes ecc
            WHERE ecc.entidad_id = ?
            AND ecc.tabla_estado_registro_id = 1
            AND ecc.f_desde <= CURDATE()
            AND (ecc.f_hasta IS NULL OR ecc.f_hasta >= CURDATE())
            ORDER BY ecc.f_desde DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

// El parámetro $q llega como una o más palabras separadas por espacio (los "tags" que
// arma el buscador en ventas_remitos.js, mismo patrón que el buscador con tags del ABM
// de productos: cada palabra es una condición AND independiente, y dentro de cada
// palabra se busca con OR en código, nombre y compatibilidad). Así "poza corolla"
// encuentra productos que mencionen ambas palabras sin importar en qué columna esté cada una.
function buscarProductosClienteRemitos($conexion, $empresa_idx, $entidad_id, $q)
{
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);

    $condicion = obtenerListaPrecioVigenteClienteRemitos($conexion, $entidad_id);
    if (!$condicion || empty($condicion['lista_precio_id'])) {
        return [];
    }
    $lista_precio_id = intval($condicion['lista_precio_id']);
    $descuento_pct = floatval($condicion['cliente_descuento_general'] ?? 0);

    $where_conditions = [
        "lp.lista_precio_id = ?",
        "lp.empresa_id = ?",
        "p.empresa_id = ?",
        "p.tabla_estado_registro_id = 1",
        "lp.tabla_estado_registro_id = 1",
        "lp.f_desde <= CURDATE()",
        "(lp.f_hasta IS NULL OR lp.f_hasta >= CURDATE())"
    ];
    $where_params = [$lista_precio_id, $empresa_idx, $empresa_idx];
    $where_types = "iii";

    $palabras = preg_split('/\s+/', trim($q));
    $palabras = array_filter($palabras, function ($p) { return strlen($p) > 0; });

    foreach ($palabras as $palabra) {
        $palabra_like = '%' . $palabra . '%';
        $where_conditions[] = "(p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.compatibilidad_busqueda LIKE ?)";
        $where_params[] = $palabra_like;
        $where_params[] = $palabra_like;
        $where_params[] = $palabra_like;
        $where_types .= "sss";
    }

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                   p.compatibilidad_texto,
                   p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje,
                   lp.precio_final
            FROM gestion__listas_precios_productos lp
            INNER JOIN gestion__productos p ON p.producto_id = lp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
            WHERE " . implode(" AND ", $where_conditions) . "
            ORDER BY p.producto_nombre
            LIMIT 20";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, $where_types, ...$where_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Precio neto (bruto - descuento general del cliente), coherente con el
        // criterio de precio_unitario_neto ya usado en pedidos.
        $fila['descuento_general_pct'] = $descuento_pct;
        $fila['precio_neto'] = floatval($fila['precio_final']) * (1 - $descuento_pct / 100);
        $productos[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $productos;
}