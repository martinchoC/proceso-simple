<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/ventas_presentaciones.php'; // venta por presentación (vpre_*): inactiva sin el indicador
require_once __DIR__ . '/../../core/canales_venta.php'; // canales de venta (cvta_*): inactivo sin el indicador
require_once __DIR__ . '/../../core/precios_venta.php'; // reglas de precio de venta (prv_*): inactivo sin el indicador
require_once __DIR__ . '/../../core/vendedores.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
$conexion = $conn;

// ============================================================
// SUPUESTOS A VALIDAR CON EL ESQUEMA REAL (avisame si difieren):
// - (2026-09-12, actualizado) gestion__depositos ya NO existe como tabla: se
//   eliminaron obtenerDepositosEmpresa() y la acción AJAX 'obtener_depositos'.
//   Por el mismo motivo se eliminó también obtenerSucursalesEmpresaRemitos()
//   y la acción 'obtener_sucursales_empresa': el formulario quedó con un único
//   combo "Punto de Venta", restringido a los PV cuya boca asociada
//   (gestion__puntos_venta.boca_id -> gestion__bocas) tiene es_deposito=1.
//   sucursal_id y boca_id se resuelven en el backend a partir del PV elegido,
//   vía resolverBocaYSucursalPorPuntoVentaRemitos() — no vienen del formulario.
// - gestion__ventas_remitos.comprobante_pv guarda directamente el punto_venta_id elegido
//   (igual que ya hace gestion__comprobantes.comprobante_pv en syncComprobante() de
//   ventas_pedidos_model.php), no un número de PV separado.
// - "Pendiente de entrega" = gestion__ventas_pedidos_detalles.cantidad - cantidad_entregada.
//   Solo se ofrecen pedidos vivos: se excluyen los de estado (id genérico) BORRADOR, CANCELADO,
//   ELIMINADO y ANULADO.
// - cantidad_entregada se reserva al agregar/editar el remito, ya en Borrador (con FOR UPDATE, ver
//   insertarDetallesRemito). Editar revierte y vuelve a reservar; eliminar (Borrador -> 6),
//   cancelar o anular revierte (revertirCantidadEntregadaRemito, desde ejecutarTransicionEstadoRemito).
// - El estado del pedido sale de lo ENTREGADO, que son solo los remitos confirmados (pendiente de
//   facturar, facturado o parcial): 11 entrega total, 10 parcial, 5 si ya no queda nada entregado
//   (actualizarEstadoPedidosPorEntregaRemito, al confirmar y al liberar, con el remito ya en su estado
//   nuevo; actualiza también el espejo del pedido en gestion__comprobantes). Prueba en el simulador:
//   casos/19b_pedidos_remitos_eliminacion.py (2026-10-21).
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

    if (empty($funciones) && $pagina_id !== 88 && $pagina_id > 0) {
        return obtenerFuncionesPaginaRemitos($conexion, 88);
    }

    return $funciones;
}

function obtenerBotonesPorEstadoRemito($conexion, $pagina_id, $estado_actual_id)
{
    static $cache = [];
    if (!isset($cache[$pagina_id])) {
        $cache[$pagina_id] = permisos_filtrar_funciones($conexion, obtenerFuncionesPaginaRemitos($conexion, $pagina_id));
    }
    $funciones = $cache[$pagina_id];
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
    $funciones = permisos_filtrar_funciones($conexion, obtenerFuncionesPaginaRemitos($conexion, $pagina_id));
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
                  WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?
                  FOR UPDATE";

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
    $moneda_id = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0);
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
        $importe_pendiente = $importe_total;

        // C5 / D2 / §2.4: No pisar importe_pendiente si el comprobante ya tiene imputaciones vigentes
        $sql_imp = "SELECT 
                        COALESCE(SUM(CASE WHEN ci.comp_destino_id = ? THEN ci.importe_imputado ELSE 0 END), 0) AS imp_destino,
                        COALESCE(SUM(CASE WHEN ci.comp_origen_id = ? THEN ci.importe_origen ELSE 0 END), 0) AS imp_origen,
                        COUNT(*) AS total_imputaciones
                    FROM gestion__comprobantes_imputaciones ci
                    JOIN conf__estados_registros er ON er.estado_registro_id = ci.tabla_estado_registro_id
                    WHERE (ci.comp_destino_id = ? OR ci.comp_origen_id = ?)
                      AND er.codigo_estandar = 'CONFIRMADO'";
        $stmt_imp = mysqli_prepare($conexion, $sql_imp);
        if ($stmt_imp) {
            mysqli_stmt_bind_param($stmt_imp, "iiii", $comprobante_id, $comprobante_id, $comprobante_id, $comprobante_id);
            mysqli_stmt_execute($stmt_imp);
            $res_imp = mysqli_stmt_get_result($stmt_imp);
            if ($row_imp = mysqli_fetch_assoc($res_imp)) {
                if (intval($row_imp['total_imputaciones']) > 0) {
                    $imputado_total = floatval($row_imp['imp_destino']) + floatval($row_imp['imp_origen']);
                    $importe_pendiente = max(0.0, round($importe_total - $imputado_total, 2));
                }
            }
            mysqli_stmt_close($stmt_imp);
        }

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
            $importe_pendiente,
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

// Recalcula, para cada pedido con al menos una línea vinculada a este remito, si
// quedó completo (todas sus líneas con cantidad == cantidad_entregada, sin importar
// si ese cantidad_entregada viene de este remito o de otros) o si todavía tiene
// alguna línea con diferencia, y deja gestion__ventas_pedidos.tabla_estado_registro_id
// en 11 (completo) o 10 (con diferencia) según corresponda.
// Se llama al confirmar un remito (aplicar) y al cancelar/anular uno ya confirmado
// (revertir): en ambos casos puede haber cambiado si el pedido está completo o no.
function actualizarEstadoPedidosPorEntregaRemito($conexion, $venta_remito_id)
{
    $venta_remito_id = intval($venta_remito_id);

    $sql_pedidos = "SELECT DISTINCT vpd.venta_pedido_id
                    FROM gestion__ventas_remitos_detalles vrd
                    INNER JOIN gestion__ventas_pedidos_detalles vpd
                        ON vpd.venta_pedido_detalle_id = vrd.venta_pedido_detalle_id
                    WHERE vrd.venta_remito_id = ? AND vrd.venta_pedido_detalle_id IS NOT NULL";
    $stmt = mysqli_prepare($conexion, $sql_pedidos);
    if (!$stmt) {
        throw new Exception("Error preparando búsqueda de pedidos afectados: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt, "i", $venta_remito_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pedido_ids = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $pedido_ids[] = intval($fila['venta_pedido_id']);
    }
    mysqli_stmt_close($stmt);

    foreach ($pedido_ids as $pedido_id) {
        // Entregado = remitos confirmados (pendientes de facturar, facturados o parciales); los Borradores
        // descuentan cantidad_entregada (reservan el pendiente) pero no cuentan como entrega.
        $sql_check = "SELECT vp.tabla_estado_registro_id AS estado,
                             SUM(COALESCE(ent.cant, 0) >= vpd.cantidad) AS completas,
                             SUM(COALESCE(ent.cant, 0) > 0) AS con_entrega,
                             COUNT(*) AS lineas
                      FROM gestion__ventas_pedidos vp
                      JOIN gestion__ventas_pedidos_detalles vpd ON vpd.venta_pedido_id = vp.venta_pedido_id
                      LEFT JOIN (SELECT rd.venta_pedido_detalle_id, SUM(rd.cantidad) cant
                                 FROM gestion__ventas_remitos_detalles rd
                                 JOIN gestion__ventas_remitos vr ON vr.venta_remito_id = rd.venta_remito_id
                                 JOIN conf__estados_registros er ON er.estado_registro_id = vr.tabla_estado_registro_id
                                 WHERE rd.tabla_estado_registro_id = 1
                                   AND er.codigo_estandar IN ('CONFIRMADO', 'PEND_FACT', 'FACT_PARC', 'FACTURADO')
                                 GROUP BY rd.venta_pedido_detalle_id) ent ON ent.venta_pedido_detalle_id = vpd.venta_pedido_detalle_id
                      WHERE vp.venta_pedido_id = ?
                      GROUP BY vp.venta_pedido_id, vp.tabla_estado_registro_id";
        $stmt_check = mysqli_prepare($conexion, $sql_check);
        if (!$stmt_check) {
            throw new Exception("Error preparando verificación de pedido completo: " . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt_check, "i", $pedido_id);
        mysqli_stmt_execute($stmt_check);
        $fila_check = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
        mysqli_stmt_close($stmt_check);
        if (!$fila_check) {
            continue;
        }

        $estado_actual = intval($fila_check['estado']);
        // Solo pedidos vivos: confirmado (5), en preparación (9), entrega parcial (10) o total (11)
        if (!in_array($estado_actual, [5, 9, 10, 11], true)) {
            continue;
        }
        if (intval($fila_check['completas']) === intval($fila_check['lineas'])) {
            $estado_pedido = 11;
        } elseif (intval($fila_check['con_entrega']) > 0) {
            $estado_pedido = 10;
        } else {
            // Sin nada entregado: vuelve a confirmado si estaba marcado como entregado; si no, queda como está
            $estado_pedido = in_array($estado_actual, [10, 11], true) ? 5 : $estado_actual;
        }
        if ($estado_pedido === $estado_actual) {
            continue;
        }

        $sql_upd_pedido = "UPDATE gestion__ventas_pedidos SET tabla_estado_registro_id = ? WHERE venta_pedido_id = ?";
        $stmt_upd_pedido = mysqli_prepare($conexion, $sql_upd_pedido);
        if (!$stmt_upd_pedido) {
            throw new Exception("Error preparando actualización de estado del pedido: " . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt_upd_pedido, "ii", $estado_pedido, $pedido_id);
        if (!mysqli_stmt_execute($stmt_upd_pedido)) {
            throw new Exception("Error actualizando estado del pedido: " . mysqli_stmt_error($stmt_upd_pedido));
        }
        mysqli_stmt_close($stmt_upd_pedido);

        // Espejo del pedido en gestion__comprobantes
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes SET tabla_estado_registro_id = ?
                                           WHERE registro_origen_id = ?
                                             AND tabla_origen_id = (SELECT t.tabla_id FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__ventas_pedidos')");
        mysqli_stmt_bind_param($stmt, "ii", $estado_pedido, $pedido_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error sincronizando el comprobante del pedido: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }
}


// de entrega (cantidad > cantidad_entregada). Excluye pedidos cancelados. No filtra por
// entidad_sucursal_id: se listan todos los pendientes del cliente y el usuario elige a
// mano qué líneas remitir (un pedido puede haberse cargado sin sucursal específica).
//
// (2026-09-12) Devuelve una lista PLANA de líneas (antes venía agrupada por pedido) para
// poder ordenar de punta a punta por ubicación física del producto — mezclando líneas de
// distintos pedidos si están en el mismo estante — en vez de por pedido/fecha. Cada línea
// trae igual el numero/fecha/tipo de su pedido, así el front no perdió información.
//
// Ubicación: mismo esquema que el ABM de productos (gestion__productos_ubicaciones ->
// gestion__sucursales_ubicaciones). Un producto puede tener ubicación en varias bocas; si
// se pasa $boca_id (la boca del punto de venta elegido en el remito) se filtra y ordena
// solo por esa boca, que es la relevante para quien arma el remito. Sin $boca_id (todavía
// no se eligió punto de venta) se muestran/ordenan las ubicaciones de todas las bocas.
function obtenerPedidosPendientesCliente($conexion, $empresa_idx, $entidad_id, $boca_id = null, $pedido_id = null, $incluir_lineas_completas = false, $incluir_en_borrador = false)
{
    $empresa_idx = intval($empresa_idx);
    $entidad_id = intval($entidad_id);
    $boca_id = !empty($boca_id) ? intval($boca_id) : null;
    // Filtro opcional: cuando se llama desde la solapa "Remitos" del módulo de
    // pedidos, acota los pendientes a ESE pedido únicamente (no a todos los
    // pendientes del cliente, que es el comportamiento de la pantalla de remitos
    // standalone). Mismo patrón null-safe "= ? OR ? IS NULL" que ya usa boca_id acá.
    $pedido_id = !empty($pedido_id) ? intval($pedido_id) : null;
    $incluir_lineas_completas = $incluir_lineas_completas ? 1 : 0;
    $incluir_en_borrador = $incluir_en_borrador ? 1 : 0;

    $sql = "SELECT vp.venta_pedido_id, vp.comprobante_nro, vp.f_emision,
                   ct.comprobante_tipo,
                   vpd.venta_pedido_detalle_id, vpd.producto_id,
                   p.producto_codigo, p.producto_nombre,
                   vpd.cantidad, vpd.cantidad_entregada,
                   COALESCE((
                       SELECT SUM(rd_confirmado.cantidad)
                       FROM gestion__ventas_remitos_detalles rd_confirmado
                       INNER JOIN gestion__ventas_remitos vr_confirmado
                           ON vr_confirmado.venta_remito_id = rd_confirmado.venta_remito_id
                       INNER JOIN conf__estados_registros er_confirmado
                           ON er_confirmado.estado_registro_id = vr_confirmado.tabla_estado_registro_id
                       WHERE rd_confirmado.venta_pedido_detalle_id = vpd.venta_pedido_detalle_id
                         AND rd_confirmado.tabla_estado_registro_id = 1
                         AND vr_confirmado.empresa_id = vp.empresa_id
                         AND er_confirmado.codigo_estandar IN ('CONFIRMADO', 'PEND_FACT', 'FACT_PARC', 'FACTURADO')
                   ), 0) as cantidad_entregada_confirmada,
                   (vpd.cantidad - vpd.cantidad_entregada) as pendiente,
                   vpd.precio_unitario_bruto, vpd.descuento_general_pct, vpd.precio_unitario_neto,
                   vpd.iva_alicuota_id, vpd.iva_porcentaje,
                   COALESCE(
                       (SELECT CONCAT('[', GROUP_CONCAT(
                           JSON_OBJECT(
                               'sucursal', COALESCE(su2.sucursal_nombre, ''),
                               'boca', COALESCE(b.boca_nombre, ''),
                               'seccion', COALESCE(su2_ubic.seccion, ''),
                               'estanteria', COALESCE(su2_ubic.estanteria, ''),
                               'estante', COALESCE(su2_ubic.estante, ''),
                               'posicion', COALESCE(su2_ubic.posicion, ''),
                               'descripcion', COALESCE(su2_ubic.descripcion, '')
                           )
                           ORDER BY su2.sucursal_nombre, su2_ubic.seccion, su2_ubic.estanteria, su2_ubic.estante, su2_ubic.posicion
                           SEPARATOR ','
                       ), ']')
                       FROM gestion__productos_ubicaciones pu2
                       INNER JOIN gestion__sucursales_ubicaciones su2_ubic ON pu2.sucursal_ubicacion_id = su2_ubic.sucursal_ubicacion_id
                       LEFT JOIN gestion__sucursales su2 ON su2_ubic.sucursal_id = su2.sucursal_id
                       LEFT JOIN gestion__bocas b ON su2_ubic.boca_id = b.boca_id
                       WHERE pu2.producto_id = vpd.producto_id
                       AND pu2.tabla_estado_registro_id = 1
                       AND (su2_ubic.boca_id = ? OR ? IS NULL)
                       ), '[]'
                   ) as ubicaciones_detalle,
                   (SELECT su3_ubic.seccion
                    FROM gestion__productos_ubicaciones pu3
                    INNER JOIN gestion__sucursales_ubicaciones su3_ubic ON pu3.sucursal_ubicacion_id = su3_ubic.sucursal_ubicacion_id
                    WHERE pu3.producto_id = vpd.producto_id
                    AND pu3.tabla_estado_registro_id = 1
                    AND (su3_ubic.boca_id = ? OR ? IS NULL)
                    ORDER BY su3_ubic.seccion, su3_ubic.estanteria, su3_ubic.estante, su3_ubic.posicion
                    LIMIT 1) as orden_seccion,
                   (SELECT su4_ubic.estanteria
                    FROM gestion__productos_ubicaciones pu4
                    INNER JOIN gestion__sucursales_ubicaciones su4_ubic ON pu4.sucursal_ubicacion_id = su4_ubic.sucursal_ubicacion_id
                    WHERE pu4.producto_id = vpd.producto_id
                    AND pu4.tabla_estado_registro_id = 1
                    AND (su4_ubic.boca_id = ? OR ? IS NULL)
                    ORDER BY su4_ubic.seccion, su4_ubic.estanteria, su4_ubic.estante, su4_ubic.posicion
                    LIMIT 1) as orden_estanteria,
                   (SELECT su5_ubic.estante
                    FROM gestion__productos_ubicaciones pu5
                    INNER JOIN gestion__sucursales_ubicaciones su5_ubic ON pu5.sucursal_ubicacion_id = su5_ubic.sucursal_ubicacion_id
                    WHERE pu5.producto_id = vpd.producto_id
                    AND pu5.tabla_estado_registro_id = 1
                    AND (su5_ubic.boca_id = ? OR ? IS NULL)
                    ORDER BY su5_ubic.seccion, su5_ubic.estanteria, su5_ubic.estante, su5_ubic.posicion
                    LIMIT 1) as orden_estante,
                   (SELECT su6_ubic.posicion
                    FROM gestion__productos_ubicaciones pu6
                    INNER JOIN gestion__sucursales_ubicaciones su6_ubic ON pu6.sucursal_ubicacion_id = su6_ubic.sucursal_ubicacion_id
                    WHERE pu6.producto_id = vpd.producto_id
                    AND pu6.tabla_estado_registro_id = 1
                    AND (su6_ubic.boca_id = ? OR ? IS NULL)
                    ORDER BY su6_ubic.seccion, su6_ubic.estanteria, su6_ubic.estante, su6_ubic.posicion
                    LIMIT 1) as orden_posicion
            FROM gestion__ventas_pedidos_detalles vpd
            INNER JOIN gestion__ventas_pedidos vp ON vpd.venta_pedido_id = vp.venta_pedido_id
            INNER JOIN gestion__productos p ON vpd.producto_id = p.producto_id
            LEFT JOIN gestion__comprobantes_tipos ct ON vp.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = vp.tabla_estado_registro_id
            WHERE vp.entidad_id = ?
            AND vp.empresa_id = ?
            AND (
                ? = 1
                OR vpd.cantidad > vpd.cantidad_entregada
                OR (
                    ? = 1
                    AND EXISTS (
                        SELECT 1
                        FROM gestion__ventas_remitos_detalles rd_b
                        INNER JOIN gestion__ventas_remitos vr_b ON rd_b.venta_remito_id = vr_b.venta_remito_id
                        WHERE rd_b.venta_pedido_detalle_id = vpd.venta_pedido_detalle_id
                          AND rd_b.tabla_estado_registro_id = 1
                          AND vr_b.empresa_id = vp.empresa_id
                          AND vr_b.tabla_estado_registro_id = 3
                    )
                )
            )
            AND (er.codigo_estandar IS NULL OR er.codigo_estandar NOT IN ('BORRADOR', 'CANCELADO', 'ELIMINADO', 'ANULADO'))
            AND (vp.venta_pedido_id = ? OR ? IS NULL)
            ORDER BY (orden_seccion IS NULL), orden_seccion, orden_estanteria, orden_estante, orden_posicion,
                     vp.f_emision, vp.venta_pedido_id, vpd.venta_pedido_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta pedidos pendientes: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiiiiiiiiiiiiiii",
        $boca_id, $boca_id, $boca_id, $boca_id, $boca_id, $boca_id, $boca_id, $boca_id, $boca_id, $boca_id,
        $entidad_id, $empresa_idx, $incluir_lineas_completas, $incluir_en_borrador, $pedido_id, $pedido_id
    );
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $lineas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $ubicaciones_detalle = [];
        if (!empty($fila['ubicaciones_detalle']) && $fila['ubicaciones_detalle'] !== '[]') {
            $decoded = json_decode($fila['ubicaciones_detalle'], true);
            if (is_array($decoded)) {
                $ubicaciones_detalle = $decoded;
            }
        }

        $lineas[] = [
            'venta_pedido_id' => $fila['venta_pedido_id'],
            'comprobante_nro' => $fila['comprobante_nro'],
            'comprobante_tipo' => $fila['comprobante_tipo'],
            'f_emision' => $fila['f_emision'],
            'venta_pedido_detalle_id' => $fila['venta_pedido_detalle_id'],
            'producto_id' => $fila['producto_id'],
            'producto_codigo' => $fila['producto_codigo'],
            'producto_nombre' => $fila['producto_nombre'],
            'cantidad' => floatval($fila['cantidad']),
            'cantidad_entregada' => floatval($fila['cantidad_entregada']),
            'cantidad_entregada_confirmada' => floatval($fila['cantidad_entregada_confirmada']),
            'pendiente' => floatval($fila['pendiente']),
            'precio_unitario_bruto' => floatval($fila['precio_unitario_bruto']),
            'descuento_general_pct' => floatval($fila['descuento_general_pct']),
            'precio_unitario_neto' => floatval($fila['precio_unitario_neto']),
            'iva_alicuota_id' => $fila['iva_alicuota_id'],
            'iva_porcentaje' => floatval($fila['iva_porcentaje'] ?? 0),
            'ubicaciones_detalle' => $ubicaciones_detalle
        ];
    }

    mysqli_stmt_close($stmt);
    // Venta por presentación: la presentación de cada línea pendiente del pedido (solo con el indicador)
    if (vpre_activo($conexion, (int) $empresa_idx, 'pedido')) {
        $lineas = vpre_agregar_fotos($conexion, 'pedido', $lineas);
    }
    return $lineas;
}


// ============================================================
// CRUD DE REMITOS
// ============================================================

function obtenerRemitosVenta($conexion, $empresa_idx, $pagina_id, $solo_pendientes_facturacion = false)
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

    $sql_where_estado = $solo_pendientes_facturacion ? " AND vr.tabla_estado_registro_id IN (13, 15) " : "";

    $sql = "SELECT vr.*,
                   er.$estado_column as estado_registro,
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia, e.cuit,
                   s.sucursal_nombre,
                   b.boca_nombre,
                   pv.nombre as punto_venta_nombre, pv.codigo_fiscal as punto_venta_codigo,
                   COALESCE(td.total, 0) as total
            FROM gestion__ventas_remitos vr
            LEFT JOIN conf__estados_registros er ON vr.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__comprobantes_tipos ct ON vr.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON vr.entidad_id = e.entidad_id
            LEFT JOIN gestion__sucursales s ON vr.sucursal_id = s.sucursal_id AND s.empresa_id = vr.empresa_id
            LEFT JOIN gestion__bocas b ON vr.boca_id = b.boca_id AND b.empresa_id = vr.empresa_id
            LEFT JOIN gestion__puntos_venta pv ON vr.comprobante_pv = pv.punto_venta_id AND pv.empresa_id = vr.empresa_id
            LEFT JOIN (
                SELECT venta_remito_id, SUM(importe_linea + iva_importe) as total
                FROM gestion__ventas_remitos_detalles
                WHERE tabla_estado_registro_id = 1
                GROUP BY venta_remito_id
            ) td ON td.venta_remito_id = vr.venta_remito_id
            WHERE vr.empresa_id = ? $sql_where_estado";

    $uid = intval($_SESSION['usuario_id'] ?? 0);
    if ($uid > 0) {
        if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
            require_once __DIR__ . '/../../core/accesos_sucursales.php';
            $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $uid, $empresa_idx, 'vr.sucursal_id');
        }
        $sql .= vcar_sql_filtro_cliente($conexion, $empresa_idx, $uid, 'vr.entidad_id');
    }

    $sql .= " ORDER BY vr.f_emision DESC, vr.venta_remito_id DESC";

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
                   b.boca_nombre,
                   pv.nombre as punto_venta_nombre,
                   cf.condicion_fiscal,
                   cf.condicion_fiscal_codigo
            FROM gestion__ventas_remitos vr
            LEFT JOIN gestion__comprobantes_tipos ct ON vr.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON vr.entidad_id = e.entidad_id
            LEFT JOIN gestion__bocas b ON vr.boca_id = b.boca_id
            LEFT JOIN gestion__puntos_venta pv ON vr.comprobante_pv = pv.punto_venta_id AND pv.empresa_id = vr.empresa_id
            LEFT JOIN gestion__entidades_condiciones_fiscales ecf
                   ON ecf.entidad_condicion_fiscal_id = (
                        SELECT ecf2.entidad_condicion_fiscal_id
                        FROM gestion__entidades_condiciones_fiscales ecf2
                        WHERE ecf2.entidad_id = vr.entidad_id
                          AND ecf2.tabla_estado_registro_id = 1
                          AND (ecf2.f_desde IS NULL OR ecf2.f_desde <= CURDATE())
                          AND (ecf2.f_hasta IS NULL OR ecf2.f_hasta >= CURDATE())
                        ORDER BY ecf2.f_desde DESC, ecf2.entidad_condicion_fiscal_id DESC
                        LIMIT 1
                   )
            LEFT JOIN gestion__condiciones_fiscales cf ON ecf.condicion_fiscal_id = cf.condicion_fiscal_id
            WHERE vr.venta_remito_id = ? AND vr.empresa_id = ?"
        . vcar_sql_filtro_cliente($conexion, $empresa_idx, intval($_SESSION['usuario_id'] ?? 0), 'vr.entidad_id');

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $remito = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$remito) return null;

    // Si por algún motivo el remito no tiene entidad_id o entidad_sucursal_id en su cabecera
    // pero proviene de un pedido, lo recuperamos del pedido origen
    if (empty($remito['entidad_id']) || empty($remito['entidad_sucursal_id'])) {
        $sql_pedido = "SELECT vp.entidad_id, vp.entidad_sucursal_id, e.entidad_nombre, e.entidad_fantasia
                       FROM gestion__ventas_remitos_detalles vrd
                       JOIN gestion__ventas_pedidos_detalles vpd ON vrd.venta_pedido_detalle_id = vpd.venta_pedido_detalle_id
                       JOIN gestion__ventas_pedidos vp ON vpd.venta_pedido_id = vp.venta_pedido_id
                       LEFT JOIN gestion__entidades e ON vp.entidad_id = e.entidad_id
                       WHERE vrd.venta_remito_id = ?
                       LIMIT 1";
        $stmt_ped = mysqli_prepare($conexion, $sql_pedido);
        if ($stmt_ped) {
            mysqli_stmt_bind_param($stmt_ped, "i", $id);
            mysqli_stmt_execute($stmt_ped);
            $res_ped = mysqli_stmt_get_result($stmt_ped);
            if ($ped_data = mysqli_fetch_assoc($res_ped)) {
                if (empty($remito['entidad_id']) && !empty($ped_data['entidad_id'])) {
                    $remito['entidad_id'] = $ped_data['entidad_id'];
                    $remito['entidad_nombre'] = $ped_data['entidad_nombre'];
                    $remito['entidad_fantasia'] = $ped_data['entidad_fantasia'];
                }
                if (empty($remito['entidad_sucursal_id']) && !empty($ped_data['entidad_sucursal_id'])) {
                    $remito['entidad_sucursal_id'] = $ped_data['entidad_sucursal_id'];
                }
            }
            mysqli_stmt_close($stmt_ped);
        }
    }

    if (empty($remito['condicion_fiscal'])) {
        $remito['condicion_fiscal'] = 'Consumidor Final';
        $remito['condicion_fiscal_codigo'] = 'CF';
    }

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

    // Venta por presentación: la foto de cada línea (solo con el indicador)
    if (vpre_activo($conexion, (int) $empresa_idx, 'remito')) {
        $detalles = vpre_agregar_fotos($conexion, 'remito', $detalles);
    }
    $remito['detalles'] = $detalles;
    return $remito;
}

// Destinatario del remito: un cliente (entidad_id > 0, con o sin sucursal) o un
// consumidor final (entidad_id = 0/vacío + consumidor_final_nombre obligatorio). En el
// consumidor final entidad_id se persiste NULL y no lleva sucursal; en el cliente el
// nombre libre se descarta (NULL) para que no queden los dos datos cargados a la vez.
function resolverDestinatarioRemito($data)
{
    $entidad_id = intval($data['entidad_id'] ?? 0);

    if ($entidad_id > 0) {
        $entidad_sucursal_id = intval($data['entidad_sucursal_id'] ?? 0);
        return [
            'entidad_id' => $entidad_id,
            'entidad_sucursal_id' => $entidad_sucursal_id > 0 ? $entidad_sucursal_id : null,
            'consumidor_final_nombre' => null
        ];
    }

    $nombre = trim($data['consumidor_final_nombre'] ?? '');
    if ($nombre === '') {
        return ['error' => 'Debe seleccionar un cliente o indicar el nombre del consumidor final'];
    }
    if (mb_strlen($nombre) > 150) {
        return ['error' => 'El nombre del consumidor final no puede superar los 150 caracteres'];
    }

    return [
        'entidad_id' => null,
        'entidad_sucursal_id' => null,
        'consumidor_final_nombre' => $nombre
    ];
}

function agregarRemitoVenta($conexion, $data)
{
    if (!$conexion) {
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }
    if (empty($data['f_emision'])) {
        return ['resultado' => false, 'error' => 'La fecha de emisión es obligatoria'];
    }
    $destinatario = resolverDestinatarioRemito($data);
    if (isset($destinatario['error'])) {
        return ['resultado' => false, 'error' => $destinatario['error']];
    }
    if (empty($data['comprobante_tipo_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el tipo de comprobante'];
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
        vcar_exigir_cliente($conexion, $empresa_id_val, intval($_SESSION['usuario_id'] ?? 0), intval($destinatario['entidad_id'] ?? 0), $data['f_emision'] ?? null);
        $estado_inicial_val = intval(obtenerEstadoInicialPaginaRemitos($conexion, $data['pagina_idx']) ?: 1);

        $comprobante_pv_val = intval($data['punto_venta_id']); // ver nota de supuestos arriba

        // boca_id (ex deposito_id) y sucursal_id ya NO vienen del formulario: se
        // resuelven acá revalidando contra la base que el PV tenga una boca de
        // depósito activa. No confiar en lo que mande el cliente.
        $ubicacion = resolverBocaYSucursalPorPuntoVentaRemitos($conexion, $empresa_id_val, $comprobante_pv_val);
        if (!$ubicacion) {
            throw new Exception("El punto de venta elegido no tiene un depósito válido asociado");
        }
        $boca_id_val = intval($ubicacion['boca_id']);
        $sucursal_id_val = intval($ubicacion['sucursal_id']);

        if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
            require_once __DIR__ . '/../../core/accesos_sucursales.php';
            AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_id_val, $sucursal_id_val);
            AccesosSucursalesHelper::exigirAccesoPuntoVenta($conexion, $empresa_id_val, $comprobante_pv_val);
        }
        // Canales de venta: la sucursal tiene que vender al canal del cliente (sin el indicador, no hace nada)
        cvta_exigir_venta_sucursal($conexion, $empresa_id_val, $sucursal_id_val, $destinatario['entidad_id'] ?? null);
        // Reglas de precio de venta: regla y descuento manual de las líneas sin pedido ni remito de origen; el manual
        // no puede superar el tope del usuario (sin el indicador, nada)
        if (isset($data['detalles']) && is_array($data['detalles'])) {
            $data['detalles'] = prv_preparar_lineas($conexion, intval($empresa_id_val), [
                'entidad_id' => intval($destinatario['entidad_id'] ?? null), 'sucursal_id' => intval($sucursal_id_val),
                'fecha' => $data['f_emision'] ?? null, 'usuario_id' => intval($_SESSION['usuario_id'] ?? 0),
            ], $data['detalles'], 'remito', true);
        }

        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_nro_val = 0;
        $entidad_id_val = $destinatario['entidad_id'];
        $entidad_sucursal_id_val = $destinatario['entidad_sucursal_id'];
        $consumidor_final_nombre_val = $destinatario['consumidor_final_nombre'];
        $f_emision_val = $data['f_emision'];
        $observaciones_val = isset($data['observaciones']) ? trim($data['observaciones']) : '';

        $sql = "INSERT INTO gestion__ventas_remitos
                (empresa_id, sucursal_id, boca_id, comprobante_tipo_id, comprobante_pv,
                 comprobante_nro, comprobante_id, f_emision, entidad_id, entidad_sucursal_id,
                 consumidor_final_nombre, observaciones, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert de remito: " . mysqli_error($conexion));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "iiiiiisiissi",
            $empresa_id_val,
            $sucursal_id_val,
            $boca_id_val,
            $comprobante_tipo_id_val,
            $comprobante_pv_val,
            $comprobante_nro_val,
            $f_emision_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $consumidor_final_nombre_val,
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
            'moneda_id' => emp_moneda_base_id($conexion, $empresa_id_val),
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
        if (empty($data['punto_venta_id'])) {
            throw new Exception("Debe seleccionar el punto de venta");
        }
        $comprobante_pv_val = intval($data['punto_venta_id']);

        $ubicacion = resolverBocaYSucursalPorPuntoVentaRemitos($conexion, $empresa_idx_val, $comprobante_pv_val);
        if (!$ubicacion) {
            throw new Exception("El punto de venta elegido no tiene un depósito válido asociado");
        }
        $boca_id_val = intval($ubicacion['boca_id']);
        $sucursal_id_val = intval($ubicacion['sucursal_id']);

        $destinatario = resolverDestinatarioRemito($data);
        if (isset($destinatario['error'])) {
            throw new Exception($destinatario['error']);
        }
        // Canales de venta: la sucursal tiene que vender al canal del cliente (sin el indicador, no hace nada)
        cvta_exigir_venta_sucursal($conexion, intval($data['empresa_idx'] ?? 0), $sucursal_id_val, $destinatario['entidad_id'] ?? null);
        // Reglas de precio de venta: regla y descuento manual de las líneas sin pedido ni remito de origen; el manual
        // no puede superar el tope del usuario (sin el indicador, nada)
        if (isset($data['detalles']) && is_array($data['detalles'])) {
            $data['detalles'] = prv_preparar_lineas($conexion, intval($data['empresa_idx'] ?? 0), [
                'entidad_id' => intval($destinatario['entidad_id'] ?? null), 'sucursal_id' => intval($sucursal_id_val),
                'fecha' => $data['f_emision'] ?? null, 'usuario_id' => intval($_SESSION['usuario_id'] ?? 0),
            ], $data['detalles'], 'remito', true);
        }

        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $entidad_id_val = $destinatario['entidad_id'];
        $entidad_sucursal_id_val = $destinatario['entidad_sucursal_id'];
        $consumidor_final_nombre_val = $destinatario['consumidor_final_nombre'];
        $f_emision_val = $data['f_emision'];
        $observaciones_val = isset($data['observaciones']) ? trim($data['observaciones']) : '';

        $sql = "UPDATE gestion__ventas_remitos
                SET sucursal_id = ?, boca_id = ?, comprobante_tipo_id = ?, comprobante_pv = ?,
                    f_emision = ?, entidad_id = ?, entidad_sucursal_id = ?,
                    consumidor_final_nombre = ?, observaciones = ?
                WHERE venta_remito_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update de remito: " . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param(
            $stmt,
            "iiiisiissii",
            $sucursal_id_val,
            $boca_id_val,
            $comprobante_tipo_id_val,
            $comprobante_pv_val,
            $f_emision_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $consumidor_final_nombre_val,
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
                'moneda_id' => emp_moneda_base_id($conexion, $empresa_idx_val),
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
    // Venta por presentación: las líneas sin pedido en cajas pasan a unidad base (sin el indicador, no hace nada)
    $detalles = vpre_preparar_lineas($conexion, intval($empresa_id),
        (!empty($entidad_id) && vpre_activo($conexion, intval($empresa_id), 'remito')) ? lpre_lista_cliente($conexion, intval($empresa_id), intval($entidad_id)) : 0,
        $detalles, 'remito');

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

        if ($venta_pedido_detalle_id && empty($entidad_id)) {
            throw new Exception("Un remito a consumidor final no puede incluir líneas de pedidos de clientes");
        }

        if ($venta_pedido_detalle_id) {
            $sql_lock ="SELECT vpd.cantidad, vpd.cantidad_entregada, vp.entidad_id, vp.empresa_id,
                                p.producto_nombre,
                                vpd.precio_unitario_bruto, vpd.descuento_general_pct,
                                vpd.iva_alicuota_id, vpd.iva_porcentaje,
                                vp.tabla_estado_registro_id, vp.comprobante_nro,
                                er.codigo_estandar
                         FROM gestion__ventas_pedidos_detalles vpd
                         INNER JOIN gestion__ventas_pedidos vp ON vpd.venta_pedido_id = vp.venta_pedido_id
                         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = vp.tabla_estado_registro_id
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
            $codigo_pedido = $linea_pedido['codigo_estandar'] ?? '';
            if ($codigo_pedido === 'BORRADOR') {
                $nro = !empty($linea_pedido['comprobante_nro']) ? '#' . $linea_pedido['comprobante_nro'] : '';
                throw new Exception("No se puede cargar un remito para el pedido $nro porque se encuentra en estado Borrador. Debe confirmarlo primero.");
            }
            if (in_array($codigo_pedido, ['ELIMINADO', 'CANCELADO', 'ANULADO'], true)) {
                $nro = !empty($linea_pedido['comprobante_nro']) ? '#' . $linea_pedido['comprobante_nro'] : '';
                throw new Exception("No se puede cargar un remito para el pedido $nro porque está " . strtolower($codigo_pedido) . ".");
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

            // Venta por presentación: una línea del pedido en cajas se entrega de a cajas enteras
            $error_presentacion = vpre_validar_heredada($conexion, 'pedido', $venta_pedido_detalle_id, $cantidad);
            if ($error_presentacion !== null) {
                throw new Exception($error_presentacion);
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
        // Venta por presentación: hereda la presentación de la línea del pedido, o guarda la de la línea directa
        $remito_detalle_id_vpre = (int) mysqli_insert_id($conexion);
        if ($venta_pedido_detalle_id) {
            vpre_heredar($conexion, 'remito', $remito_detalle_id_vpre, 'pedido', $venta_pedido_detalle_id, $cantidad);
        } elseif (!empty($detalle['_vpre'])) {
            vpre_guardar_foto($conexion, 'remito', $remito_detalle_id_vpre, $detalle['_vpre']);
        }
        // Reglas de precio de venta: hereda la regla de la línea del pedido, o graba la de la línea directa
        if ($venta_pedido_detalle_id) {
            prv_heredar($conexion, 'remito', $remito_detalle_id_vpre, 'pedido', (int) $venta_pedido_detalle_id);
        } elseif (!empty($detalle['_prv'])) {
            prv_guardar_linea($conexion, 'remito', $remito_detalle_id_vpre, $detalle['_prv'], $detalle['_prv']['_aprobado_por'] ?? null);
        }

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

        // Si el remito se elimina (Borrador -> Eliminado), se cancela o se anula, liberar el pendiente
        // que le había descontado a los pedidos de origen (lo descuenta desde el alta en Borrador).
        $info_destino = obtenerCodigoEstandarEstado($conexion, $estado_destino_id);
        $codigo_destino = $info_destino['codigo_estandar'] ?? '';
        $libera_pedido = in_array($codigo_destino, ['CANCELADO', 'ANULADO', 'ELIMINADO'], true);
        // Anular un remito confirmado: solo si ninguna factura (tampoco un borrador) usa sus líneas;
        // si no, primero se anula o se quita la línea de la factura.
        if ($codigo_destino === 'ANULADO') {
            $stmt = mysqli_prepare($conexion, "SELECT f.venta_factura_id, f.comprobante_nro
                                               FROM gestion__ventas_remitos_detalles rd
                                               JOIN gestion__ventas_facturas_detalles fd ON fd.venta_remito_detalle_id = rd.venta_remito_detalle_id
                                                    AND fd.tabla_estado_registro_id <> 6
                                               JOIN gestion__ventas_facturas f ON f.venta_factura_id = fd.venta_factura_id
                                                    AND f.tabla_estado_registro_id NOT IN (6, 38)
                                               WHERE rd.venta_remito_id = ?
                                               LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $venta_remito_id);
            mysqli_stmt_execute($stmt);
            $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($factura) {
                throw new Exception('El remito tiene líneas en una factura (' . (intval($factura['comprobante_nro']) > 0 ? 'N° ' . intval($factura['comprobante_nro']) : 'borrador ID ' . intval($factura['venta_factura_id']))
                    . '). Para anularlo, primero anulá la factura o quitá esas líneas.');
            }
        }
        if ($libera_pedido) {
            revertirCantidadEntregadaRemito($conexion, $venta_remito_id);
        }

        $sql_update = "UPDATE gestion__ventas_remitos SET tabla_estado_registro_id = ? WHERE venta_remito_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $venta_remito_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error actualizando estado: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // Con el remito ya en su estado nuevo: entrega total (11), parcial (10) o ninguna (5) de cada pedido afectado
        if ($accion_js === 'confirmar' || $libera_pedido) {
            actualizarEstadoPedidosPorEntregaRemito($conexion, $venta_remito_id);
        }

        // Espejo en gestion__comprobantes: estado y número del remito (antes quedaba en Borrador y sin número)
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes c
                 JOIN gestion__ventas_remitos r ON r.venta_remito_id = c.registro_origen_id
                 SET c.tabla_estado_registro_id = r.tabla_estado_registro_id, c.comprobante_nro = r.comprobante_nro
                 WHERE r.venta_remito_id = ?
                   AND c.tabla_origen_id = (SELECT t.tabla_id FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__ventas_remitos')");
        mysqli_stmt_bind_param($stmt, "i", $venta_remito_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error sincronizando el comprobante del remito: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // Stock: lo decide el tipo (stock_signo) y el estado destino (aplica_stock); aprobar descuenta,
        // eliminar un remito aprobado lo devuelve. Idempotente: va en toda transición.
        sincronizarStockRemitoVenta($conexion, $venta_remito_id, intval($estado_destino_id), $pagina_id);

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

// Lleva el libro de stock al estado del remito (ver stock_core_model.php). Corre dentro de la
// transacción del llamador; la boca sale del propio remito.
function sincronizarStockRemitoVenta($conexion, $venta_remito_id, $estado_id, $pagina_id)
{
    require_once __DIR__ . '/stock_core_model.php';

    $tabla_origen_id = obtenerTablaOrigenPorPaginaRemitos($conexion, $pagina_id);
    if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen del remito.');

    $stmt = mysqli_prepare($conexion, "SELECT r.empresa_id, r.boca_id, r.comprobante_tipo_id, r.comprobante_pv, r.comprobante_nro,
                                              COALESCE(NULLIF(r.comprobante_id, 0), c.comprobante_id, 0) AS comprobante_id
                                       FROM gestion__ventas_remitos r
                                       LEFT JOIN gestion__comprobantes c ON c.tabla_origen_id = ? AND c.registro_origen_id = r.venta_remito_id
                                       WHERE r.venta_remito_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $tabla_origen_id, $venta_remito_id);
    mysqli_stmt_execute($stmt);
    $remito = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$remito) throw new Exception('Remito no encontrado.');

    $stmt = mysqli_prepare($conexion, "SELECT venta_remito_detalle_id, producto_id, cantidad
                                       FROM gestion__ventas_remitos_detalles
                                       WHERE venta_remito_id = ? AND tabla_estado_registro_id <> 6");
    mysqli_stmt_bind_param($stmt, "i", $venta_remito_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lineas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lineas[] = [
            'registro_detalle_id' => intval($row['venta_remito_detalle_id']),
            'producto_id' => intval($row['producto_id']),
            'cantidad' => floatval($row['cantidad']),
        ];
    }
    mysqli_stmt_close($stmt);

    return stockSincronizarComprobante($conexion, [
        'empresa_id' => intval($remito['empresa_id']),
        'comprobante_id' => intval($remito['comprobante_id']),
        'comprobante_tipo_id' => intval($remito['comprobante_tipo_id']),
        'tabla_origen_id' => $tabla_origen_id,
        'registro_origen_id' => intval($venta_remito_id),
        'estado_registro_id' => intval($estado_id),
        'boca_id' => intval($remito['boca_id']),
        'usuario_id' => $_SESSION['usuario_id'] ?? null,
        'observaciones' => 'Remito N° ' . $remito['comprobante_pv'] . '-' . $remito['comprobante_nro'],
    ], $lineas);
}


// ============================================================
// COMBOS Y CATÁLOGOS (reutilizan la misma lógica de ventas_pedidos_model.php,
// duplicados acá por el mismo criterio de no cruzar includes entre módulos)
// ============================================================

// Mismo criterio que obtenerComprobantesTipos() en ventas_pedidos_model.php:
// pagina_id -> tabla_id -> subgrupo con esa tabla_id, intersectado con lo
// habilitado para el punto_venta_id en gestion__puntos_venta_comprobantes.
// Filtro duro por intersección: sin PV, o sin habilitación, no aparece.
function obtenerComprobantesTiposRemitos($conexion, $empresa_idx, $pagina_id, $punto_venta_id)
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    $punto_venta_id = intval($punto_venta_id);

    if (empty($punto_venta_id)) {
        return [];
    }

    $tabla_id = obtenerTablaOrigenPorPaginaRemitos($conexion, $pagina_id);
    if (empty($tabla_id)) {
        error_log("obtenerComprobantesTiposRemitos: sin tabla_id configurado para pagina_id=$pagina_id");
        return [];
    }

    $sql = "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.letra, ct.codigo
            FROM gestion__comprobantes_tipos ct
            INNER JOIN gestion__comprobantes_subgrupos cs
                ON cs.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
                AND cs.tabla_estado_registro_id = 1
                AND cs.tabla_id = ?
                AND cs.empresa_id IN (0, ?)
            INNER JOIN gestion__puntos_venta_comprobantes pvc
                ON pvc.comprobante_tipo_id = ct.comprobante_tipo_id
                AND pvc.punto_venta_id = ?
                AND pvc.empresa_id = ?
                AND pvc.tabla_estado_registro_id = 1
            WHERE ct.tabla_estado_registro_id = 1
              AND ct.empresa_id IN (0, ?)
            ORDER BY ct.orden, ct.comprobante_tipo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerComprobantesTiposRemitos: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "iiiii", $tabla_id, $empresa_idx, $punto_venta_id, $empresa_idx, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    mysqli_stmt_close($stmt);

    return $tipos;
}

// Único combo del formulario: puntos de venta de la empresa cuya boca asociada
// es un depósito (es_deposito=1) y está activa. boca_id es nullable en
// gestion__puntos_venta, así que el INNER JOIN ya excluye los PV sin boca asignada.
function obtenerPuntosVentaDepositoRemitos($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT pv.punto_venta_id, pv.nombre AS punto_venta_nombre,
                   pv.codigo_fiscal AS punto_venta_codigo, pv.boca_id
            FROM gestion__puntos_venta pv
            INNER JOIN gestion__bocas b
                ON b.boca_id = pv.boca_id
                AND b.tabla_estado_registro_id = 1
                AND b.es_deposito = 1
            WHERE pv.empresa_id = ?
              AND pv.tabla_estado_registro_id = 1
            ORDER BY pv.nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerPuntosVentaDepositoRemitos: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $puntos_venta = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $puntos_venta[] = $fila;
    }
    mysqli_stmt_close($stmt);

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $puntos_venta = AccesosSucursalesHelper::filtrarPuntosVenta($conexion, $empresa_idx, $puntos_venta);
    }

    return $puntos_venta;
}

// Resuelve boca_id + sucursal_id (para la RLS transversal por sucursal) a partir
// del punto_venta_id elegido, revalidando en el propio backend que sea un PV con
// boca de depósito activa — no confiar en lo que mandó el formulario.
function resolverBocaYSucursalPorPuntoVentaRemitos($conexion, $empresa_idx, $punto_venta_id)
{
    $empresa_idx = intval($empresa_idx);
    $punto_venta_id = intval($punto_venta_id);

    $sql = "SELECT pv.boca_id, b.sucursal_id
            FROM gestion__puntos_venta pv
            INNER JOIN gestion__bocas b
                ON b.boca_id = pv.boca_id
                AND b.tabla_estado_registro_id = 1
                AND b.es_deposito = 1
            WHERE pv.punto_venta_id = ?
              AND pv.empresa_id = ?
              AND pv.tabla_estado_registro_id = 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando resolverBocaYSucursalPorPuntoVentaRemitos: " . mysqli_error($conexion));
        return null;
    }

    mysqli_stmt_bind_param($stmt, "ii", $punto_venta_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $fila ?: null;
}


function obtenerClientesRemitos($conexion, $empresa_idx)
{
    $sql = "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia,
                   COALESCE(cf.condicion_fiscal, 'Consumidor Final') AS condicion_fiscal,
                   COALESCE(cf.condicion_fiscal_codigo, 'CF') AS condicion_fiscal_codigo
            FROM gestion__entidades e
            LEFT JOIN gestion__entidades_condiciones_fiscales ecf
                   ON ecf.entidad_condicion_fiscal_id = (
                        SELECT ecf2.entidad_condicion_fiscal_id
                        FROM gestion__entidades_condiciones_fiscales ecf2
                        WHERE ecf2.entidad_id = e.entidad_id
                          AND ecf2.tabla_estado_registro_id = 1
                          AND (ecf2.f_desde IS NULL OR ecf2.f_desde <= CURDATE())
                          AND (ecf2.f_hasta IS NULL OR ecf2.f_hasta >= CURDATE())
                        ORDER BY ecf2.f_desde DESC, ecf2.entidad_condicion_fiscal_id DESC
                        LIMIT 1
                   )
            LEFT JOIN gestion__condiciones_fiscales cf ON ecf.condicion_fiscal_id = cf.condicion_fiscal_id
            WHERE e.empresa_id = ?
            AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI') . "
            AND e.tabla_estado_registro_id = 1"
        . vcar_sql_filtro_cliente($conexion, intval($empresa_idx), intval($_SESSION['usuario_id'] ?? 0), 'e.entidad_id') . "
            ORDER BY e.entidad_nombre";

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

    $sql = "SELECT ecc.lista_precio_id, ecc.cliente_descuento_general,
                   COALESCE(cf.condicion_fiscal, 'Consumidor Final') AS condicion_fiscal,
                   COALESCE(cf.condicion_fiscal_codigo, 'CF') AS condicion_fiscal_codigo
            FROM gestion__entidades e
            LEFT JOIN gestion__entidades_condiciones_clientes ecc
                   ON ecc.entidad_condicion_cliente_id = (
                        SELECT ecc2.entidad_condicion_cliente_id
                        FROM gestion__entidades_condiciones_clientes ecc2
                        WHERE ecc2.entidad_id = e.entidad_id
                          AND ecc2.tabla_estado_registro_id = 1
                          AND ecc2.f_desde <= CURDATE()
                          AND (ecc2.f_hasta IS NULL OR ecc2.f_hasta >= CURDATE())
                        ORDER BY ecc2.f_desde DESC, ecc2.entidad_condicion_cliente_id DESC
                        LIMIT 1
                   )
            LEFT JOIN gestion__entidades_condiciones_fiscales ecf
                   ON ecf.entidad_condicion_fiscal_id = (
                        SELECT ecf2.entidad_condicion_fiscal_id
                        FROM gestion__entidades_condiciones_fiscales ecf2
                        WHERE ecf2.entidad_id = e.entidad_id
                          AND ecf2.tabla_estado_registro_id = 1
                          AND (ecf2.f_desde IS NULL OR ecf2.f_desde <= CURDATE())
                          AND (ecf2.f_hasta IS NULL OR ecf2.f_hasta >= CURDATE())
                        ORDER BY ecf2.f_desde DESC, ecf2.entidad_condicion_fiscal_id DESC
                        LIMIT 1
                   )
            LEFT JOIN gestion__condiciones_fiscales cf ON ecf.condicion_fiscal_id = cf.condicion_fiscal_id
            WHERE e.entidad_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return cvta_completar_condicion($conexion, $entidad_id, $row ?: null); // canales de venta: lista unificada (sin el indicador, igual que antes)
}

// Lista de precios que se aplica a los consumidores finales: la "Mostrador" de la
// empresa, identificada por código (no por id, que cambia entre empresas).
const LISTA_PRECIO_CODIGO_MOSTRADOR = 'MOST';

function obtenerListaPrecioMostradorRemitos($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    $codigo = LISTA_PRECIO_CODIGO_MOSTRADOR;

    $sql = "SELECT lista_precio_id
            FROM gestion__listas_precios
            WHERE empresa_id = ?
            AND lista_precio_codigo = ?
            AND tabla_estado_registro_id = 1
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "is", $empresa_idx, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ? intval($row['lista_precio_id']) : null;
}

// Condición de precio para buscar productos "sin pedido": con cliente, su lista y
// descuento general vigentes; sin cliente ($entidad_id = 0, consumidor final), la
// lista Mostrador sin descuento. Devuelve null si no hay lista aplicable.
function obtenerCondicionPrecioRemito($conexion, $empresa_idx, $entidad_id)
{
    $entidad_id = intval($entidad_id);

    if ($entidad_id > 0) {
        $condicion = obtenerListaPrecioVigenteClienteRemitos($conexion, $entidad_id);
        return (!$condicion || empty($condicion['lista_precio_id'])) ? null : $condicion;
    }

    $lista_mostrador_id = obtenerListaPrecioMostradorRemitos($conexion, $empresa_idx);
    if (!$lista_mostrador_id) return null;

    return ['lista_precio_id' => $lista_mostrador_id, 'cliente_descuento_general' => 0];
}

// El parámetro $q llega como una o más palabras separadas por espacio (los "tags" que
// arma el buscador en ventas_remitos.js, mismo patrón que el buscador con tags del ABM
// de productos: cada palabra es una condición AND independiente, y dentro de cada
// palabra se busca con OR en código, nombre y compatibilidad). Así "poza corolla"
// encuentra productos que mencionen ambas palabras sin importar en qué columna esté cada una.
// $entidad_id = 0 busca para consumidor final (precio de mostrador, ver obtenerCondicionPrecioRemito).
function buscarProductosClienteRemitos($conexion, $empresa_idx, $entidad_id, $q)
{
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);

    $condicion = obtenerCondicionPrecioRemito($conexion, $empresa_idx, $entidad_id);
    if (!$condicion) {
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
    // Venta por presentación: ofertas por producto (sin el indicador, las filas tal cual)
    // en_lista: canales de venta, aviso "sin precio en la lista del cliente" (sin el indicador no se agrega)
    return cvta_marcar_en_lista($conexion, intval($empresa_idx), intval($lista_precio_id), vpre_agregar_ofertas($conexion, intval($empresa_idx), intval($lista_precio_id), $productos, 0, 'remito'));
}