<?php
// Reutiliza funciones ya existentes en vez de duplicarlas: obtenerPedidoVentaPorId,
// obtenerTablaOrigenPorPagina, obtenerBotonesPorEstado, ejecutarTransicionEstado
// (de pedidos) y obtenerPedidosPendientesCliente (de remitos).
require_once __DIR__ . '/ventas_pedidos_model.php';
require_once __DIR__ . '/ventas_remitos_model.php';

// Lista fija de pedidos en los estados operativos de esta página (5, 9, 10) —
// NO es un filtro configurable: es la consulta fija decidida para esta página
// separada de uso operativo (preparación de pedidos), distinta del ABM
// genérico de ventas_pedidos.php que muestra todos los estados.
function obtenerPedidosGestion($conexion, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $tabla_id = obtenerTablaOrigenPorPagina($conexion, intval($pagina_id));

    $sql = "SELECT vp.venta_pedido_id, vp.comprobante_nro, vp.f_emision, vp.f_entrega_estimada,
                   vp.total, vp.tabla_estado_registro_id, vp.entidad_id, vp.sucursal_id, vp.punto_venta_id,
                   ter.tabla_estado_registro as estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia,
                   s.sucursal_nombre,
                   pv.nombre as punto_venta_nombre
            FROM gestion__ventas_pedidos vp
            LEFT JOIN conf__tablas_estados_registros ter
                ON vp.tabla_estado_registro_id = ter.estado_registro_id AND ter.tabla_id = ?
            LEFT JOIN conf__estados_registros er ON ter.estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON ter.color_id = c.color_id
            LEFT JOIN gestion__comprobantes_tipos ct ON vp.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON vp.entidad_id = e.entidad_id
            LEFT JOIN gestion__sucursales s ON vp.sucursal_id = s.sucursal_id AND s.empresa_id = vp.empresa_id
            LEFT JOIN gestion__puntos_venta pv ON vp.punto_venta_id = pv.punto_venta_id AND pv.empresa_id = vp.empresa_id
            WHERE vp.empresa_id = ? AND vp.tabla_estado_registro_id IN (5, 9, 10)
            ORDER BY vp.f_emision ASC, vp.venta_pedido_id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerPedidosGestion: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $tabla_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $pedidos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => $fila['color_clase'] ?? 'btn-dark',
            'bg_clase' => $fila['bg_clase'] ?? 'bg-dark',
            'text_clase' => $fila['text_clase'] ?? 'text-white'
        ];
        $pedidos[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $pedidos;
}

// Depósitos activos de UNA sucursal puntual (mismo criterio que
// obtenerPuntosVentaDepositoRemitos en ventas_remitos_model.php, acotado por
// sucursal_id) — el remito de esta página siempre usa la sucursal del
// pedido, nunca se elige de una lista general.
function obtenerPuntosVentaDepositoPorSucursal($conexion, $empresa_idx, $sucursal_id)
{
    $empresa_idx = intval($empresa_idx);
    $sucursal_id = intval($sucursal_id);

    $sql = "SELECT pv.punto_venta_id, pv.nombre AS punto_venta_nombre,
                   pv.codigo_fiscal AS punto_venta_codigo, pv.boca_id
            FROM gestion__puntos_venta pv
            INNER JOIN gestion__bocas b
                ON b.boca_id = pv.boca_id
                AND b.tabla_estado_registro_id = 1
                AND b.es_deposito = 1
                AND b.sucursal_id = ?
            WHERE pv.empresa_id = ?
              AND pv.tabla_estado_registro_id = 1
            ORDER BY pv.nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerPuntosVentaDepositoPorSucursal: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $sucursal_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $puntos_venta = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $puntos_venta[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $puntos_venta;
}

// Busca, entre los botones habilitados para el estado de origen en ESTA
// página (pagina_id propio, dado de alta en conf__paginas/conf__paginas_funciones),
// el que transiciona específicamente al estado destino indicado. Nunca se
// hardcodea el accion_js como string fijo — ya nos pasó el bug de
// 'confirmar' vs 'confirmar_pedido' por asumir el nombre de la acción en vez
// de leerlo de la configuración real.
function resolverAccionTransicion($conexion, $pagina_id, $estado_origen_id, $estado_destino_id)
{
    $botones = obtenerBotonesPorEstado($conexion, $pagina_id, $estado_origen_id);
    foreach ($botones as $boton) {
        if ($boton['estado_destino_id'] == $estado_destino_id) {
            return $boton['accion_js'];
        }
    }
    return null;
}
