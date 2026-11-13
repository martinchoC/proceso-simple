<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['productos_consulta.php']]);

// Endpoint del "remito rápido" que se arma desde productos_consulta.php.
//
// No duplica lógica de remitos: delega en ventas_remitos_model.php (alta, numeración
// y stock pasan por el mismo circuito que ventas_remitos.php).
// Reglas de seguridad:
//  - El precio NUNCA se toma del navegador: se recalcula acá con la lista del cliente
//    (o Mostrador para consumidor final) y su descuento general vigente.
//  - Para crear se exige además acceso a la página ventas_remitos.php.
//  - La empresa sale de la sesión validada, nunca de lo que mande el cliente.

header('Content-Type: application/json; charset=utf-8');

function rr_responder($data, int $http = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function rr_error(string $msg, int $http = 400): void
{
    rr_responder(['success' => false, 'error' => $msg], $http);
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/ventas_remitos_model.php';

if (!$conexion) rr_error('Error de conexión a la base de datos', 500);

$usuario_id = intval($ctx['usuario_id']);
$empresa_id = intval($ctx['empresa_id']);
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

/**
 * Página ventas_remitos.php (módulo 2) a la que el usuario tiene acceso. Define el
 * flujo de estados (alta y confirmación) en conf__paginas_funciones.
 */
function rr_pagina_remitos(mysqli $conexion, int $usuario_id, int $empresa_id): int
{
    $stmt = mysqli_prepare($conexion, "SELECT pagina_id FROM conf__paginas
                                       WHERE modulo_id = 2 AND url = 'ventas_remitos.php'
                                         AND tabla_estado_registro_id = 1
                                       ORDER BY pagina_id");
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $paginas = [];
    while ($f = mysqli_fetch_assoc($res)) $paginas[] = intval($f['pagina_id']);
    mysqli_stmt_close($stmt);

    if (!$paginas) return 0;
    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) return $paginas[0];

    $permitidas = usuario_obtener_paginas_permitidas($conexion, $usuario_id, $empresa_id);
    foreach ($paginas as $p) {
        if (isset($permitidas[$p])) return $p;
    }
    return 0;
}

function rr_exigir_pagina_remitos(mysqli $conexion, int $usuario_id, int $empresa_id): int
{
    $pagina = rr_pagina_remitos($conexion, $usuario_id, $empresa_id);
    if ($pagina <= 0) {
        rr_error('No tenés permisos para emitir remitos. Pedile a un administrador acceso a la página Remitos de venta.', 403);
    }
    return $pagina;
}

/**
 * Precio de lista vigente por producto, para el cliente (o Mostrador si $entidad_id = 0).
 * Misma regla que buscarProductosClienteRemitos(): precio_final de la lista menos el
 * descuento general del cliente. Devuelve [producto_id => fila] solo de lo encontrado.
 */
function rr_precios(mysqli $conexion, int $empresa_id, int $entidad_id, array $producto_ids): array
{
    $producto_ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
    if (!$producto_ids) return [];

    $condicion = obtenerCondicionPrecioRemito($conexion, $empresa_id, $entidad_id);
    if (!$condicion) return ['__error' => 'sin_lista_precios'];

    $lista_id = intval($condicion['lista_precio_id']);
    $desc_pct = floatval($condicion['cliente_descuento_general'] ?? 0);
    $marcas = implode(',', array_fill(0, count($producto_ids), '?'));

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, p.iva_alicuota_id,
                   COALESCE(iva.porcentaje, 0) AS iva_porcentaje, lp.precio_final
            FROM gestion__listas_precios_productos lp
            INNER JOIN gestion__productos p ON p.producto_id = lp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON iva.iva_alicuota_id = p.iva_alicuota_id
            WHERE lp.lista_precio_id = ? AND lp.empresa_id = ? AND p.empresa_id = ?
              AND p.tabla_estado_registro_id = 1 AND lp.tabla_estado_registro_id = 1
              AND lp.f_desde <= CURDATE() AND (lp.f_hasta IS NULL OR lp.f_hasta >= CURDATE())
              AND p.producto_id IN ($marcas)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['__error' => 'consulta'];

    $tipos = 'iii' . str_repeat('i', count($producto_ids));
    $params = array_merge([$lista_id, $empresa_id, $empresa_id], $producto_ids);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $out = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $bruto = floatval($f['precio_final']);
        $out[intval($f['producto_id'])] = [
            'producto_id' => intval($f['producto_id']),
            'producto_codigo' => $f['producto_codigo'],
            'producto_nombre' => $f['producto_nombre'],
            'precio_unitario_bruto' => $bruto,
            'descuento_general_pct' => $desc_pct,
            'precio_neto' => round($bruto * (1 - $desc_pct / 100), 4),
            'iva_alicuota_id' => $f['iva_alicuota_id'] !== null ? intval($f['iva_alicuota_id']) : null,
            'iva_porcentaje' => floatval($f['iva_porcentaje']),
        ];
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/** Tipo de comprobante por defecto del PV: el primero habilitado para remitos. */
function rr_tipo_defecto(mysqli $conexion, int $empresa_id, int $pagina_remitos, int $punto_venta_id): int
{
    $tipos = obtenerComprobantesTiposRemitos($conexion, $empresa_id, $pagina_remitos, $punto_venta_id);
    return $tipos ? intval($tipos[0]['comprobante_tipo_id']) : 0;
}

try {
    switch ($accion) {

        // Defaults del formulario: puntos de venta con depósito y su tipo de remito.
        case 'contexto':
            $pagina = rr_exigir_pagina_remitos($conexion, $usuario_id, $empresa_id);
            $pvs = obtenerPuntosVentaDepositoRemitos($conexion, $empresa_id);
            foreach ($pvs as &$pv) {
                $pv['comprobante_tipo_id'] = rr_tipo_defecto($conexion, $empresa_id, $pagina, intval($pv['punto_venta_id']));
            }
            unset($pv);
            rr_responder([
                'success' => true,
                'puntos_venta' => $pvs,
                'fecha' => date('Y-m-d'),
            ]);

        // Búsqueda de clientes por texto (no se baja la lista completa al navegador).
        case 'buscar_clientes':
            rr_exigir_pagina_remitos($conexion, $usuario_id, $empresa_id);
            $q = mb_strtolower(trim($_GET['q'] ?? $_POST['q'] ?? ''));
            if (mb_strlen($q) < 2) rr_responder(['success' => true, 'clientes' => []]);
            $palabras = preg_split('/\s+/', $q);
            $clientes = [];
            foreach (obtenerClientesRemitos($conexion, $empresa_id) as $c) {
                $texto = mb_strtolower(($c['entidad_nombre'] ?? '') . ' ' . ($c['entidad_fantasia'] ?? ''));
                $ok = true;
                foreach ($palabras as $p) {
                    if ($p !== '' && mb_strpos($texto, $p) === false) { $ok = false; break; }
                }
                if ($ok) {
                    $clientes[] = [
                        'entidad_id' => intval($c['entidad_id']),
                        'entidad_nombre' => $c['entidad_nombre'],
                        'entidad_fantasia' => $c['entidad_fantasia'],
                        'condicion_fiscal' => $c['condicion_fiscal'],
                    ];
                    if (count($clientes) >= 20) break;
                }
            }
            rr_responder(['success' => true, 'clientes' => $clientes]);

        // Precios del servidor para lo que hay en el carrito (cambia al cambiar de cliente).
        case 'precios':
            rr_exigir_pagina_remitos($conexion, $usuario_id, $empresa_id);
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            $ids = json_decode($_POST['producto_ids'] ?? '[]', true);
            if (!is_array($ids)) rr_error('Productos inválidos');
            $precios = rr_precios($conexion, $empresa_id, $entidad_id, $ids);
            if (isset($precios['__error'])) {
                rr_responder(['success' => false, 'error' => $entidad_id > 0
                    ? 'El cliente no tiene una lista de precios vigente asignada.'
                    : 'No hay lista de precios Mostrador vigente para consumidor final.']);
            }
            rr_responder(['success' => true, 'precios' => array_values($precios)]);

        // Alta + confirmación en una sola operación: queda pendiente de facturar.
        case 'crear':
            $pagina = rr_exigir_pagina_remitos($conexion, $usuario_id, $empresa_id);

            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            $cf_nombre = trim($_POST['consumidor_final_nombre'] ?? '');
            $punto_venta_id = intval($_POST['punto_venta_id'] ?? 0);
            $observaciones = mb_substr(trim($_POST['observaciones'] ?? ''), 0, 500);
            $items = json_decode($_POST['items'] ?? '[]', true);

            if (!is_array($items) || !$items) rr_error('El carrito está vacío');
            if (count($items) > 100) rr_error('Demasiadas líneas para un remito rápido (máximo 100)');
            if ($entidad_id <= 0 && $cf_nombre === '') rr_error('Elegí un cliente o indicá el nombre del consumidor final');

            // Cantidades agrupadas por producto (si repiten el mismo producto se suman).
            $cantidades = [];
            foreach ($items as $it) {
                $pid = intval($it['producto_id'] ?? 0);
                $cant = floatval($it['cantidad'] ?? 0);
                if ($pid <= 0 || $cant <= 0 || $cant > 1000000) rr_error('Hay una línea con producto o cantidad inválida');
                $cantidades[$pid] = ($cantidades[$pid] ?? 0) + $cant;
            }

            $precios = rr_precios($conexion, $empresa_id, $entidad_id, array_keys($cantidades));
            if (isset($precios['__error'])) {
                rr_error($entidad_id > 0
                    ? 'El cliente no tiene una lista de precios vigente asignada.'
                    : 'No hay lista de precios Mostrador vigente para consumidor final.');
            }

            $detalles = [];
            foreach ($cantidades as $pid => $cant) {
                if (!isset($precios[$pid])) {
                    rr_error('El producto ID ' . $pid . ' no tiene precio vigente en la lista del cliente.');
                }
                $p = $precios[$pid];
                $detalles[] = [
                    'producto_id' => $pid,
                    'cantidad' => $cant,
                    'precio_unitario_bruto' => $p['precio_unitario_bruto'],
                    'descuento_general_pct' => $p['descuento_general_pct'],
                    'iva_alicuota_id' => $p['iva_alicuota_id'],
                    'iva_porcentaje' => $p['iva_porcentaje'],
                ];
            }

            $tipo_id = rr_tipo_defecto($conexion, $empresa_id, $pagina, $punto_venta_id);
            if ($tipo_id <= 0) rr_error('El punto de venta elegido no tiene un tipo de remito habilitado.');

            $alta = agregarRemitoVenta($conexion, [
                'punto_venta_id' => $punto_venta_id,
                'comprobante_tipo_id' => $tipo_id,
                'entidad_id' => $entidad_id,
                'entidad_sucursal_id' => 0,
                'consumidor_final_nombre' => $entidad_id > 0 ? '' : $cf_nombre,
                'f_emision' => date('Y-m-d'),
                'observaciones' => $observaciones,
                'detalles' => $detalles,
                'empresa_idx' => $empresa_id,
                'pagina_idx' => $pagina,
            ]);
            if (empty($alta['resultado'])) {
                rr_error($alta['error'] ?? 'No se pudo crear el remito');
            }

            // Confirmar = numerar + descontar stock + pasar a pendiente de facturar.
            $remito_id = intval($alta['venta_remito_id']);
            $conf = ejecutarTransicionEstadoRemito($conexion, $remito_id, 'confirmar', $empresa_id, $pagina);
            if (empty($conf['success'])) {
                rr_responder([
                    'success' => true,
                    'confirmado' => false,
                    'venta_remito_id' => $remito_id,
                    'message' => 'El remito quedó en borrador (no se pudo confirmar: ' . ($conf['error'] ?? 'error') . '). Revisalo en Remitos de venta.',
                ]);
            }
            rr_responder([
                'success' => true,
                'confirmado' => true,
                'venta_remito_id' => $remito_id,
                'comprobante_nro' => $conf['comprobante_nro'] ?? null,
                'message' => 'Remito emitido' . (!empty($conf['comprobante_nro']) ? ' N° ' . $conf['comprobante_nro'] : '') . ' y pendiente de facturar.',
            ]);

        default:
            rr_error('Acción no permitida: ' . $accion, 403);
    }
} catch (Throwable $e) {
    error_log('productos_consulta_remito_ajax.php: ' . $e->getMessage());
    rr_error('Error del servidor', 500);
}
