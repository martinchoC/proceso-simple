<?php
/**
 * stock_consulta_model.php — Stock y Movimientos (página 638).
 * Las grillas, bocas, categorías y tipos son las del Dashboard de stock (stock_dashboard_model.php, con los filtros
 * opcionales sucursal_id / producto_id / categoria_id / compatibilidad y con_totales). Acá: la búsqueda de productos
 * del filtro, los catálogos de compatibilidad (solo Gestión, módulo 2), la planilla de conteo y el blanqueo de
 * stock negativo, que genera Ajustes de Stock (stock_ajustes_model.php) con lo filtrado.
 */
require_once __DIR__ . '/stock_dashboard_model.php';
require_once __DIR__ . '/../../core/formato.php';

/** Productos que controlan stock, para el filtro (Select2): [{id, text}], hasta 30. */
function scBuscarProductos(mysqli $conexion, int $empresa_id, string $q): array
{
    $q = trim($q);
    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre
            FROM gestion__productos p
            WHERE p.empresa_id = ? AND p.controla_stock = 1";
    $types = 'i';
    $params = [$empresa_id];
    // Cada palabra tiene que estar en el código, el nombre o el código de barras
    foreach (array_slice(preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $palabra) {
        $like = '%' . $palabra . '%';
        $sql .= " AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.codigo_barras LIKE ?)";
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }
    $sql .= " ORDER BY (p.producto_codigo = ?) DESC, p.tabla_estado_registro_id = 1 DESC, p.producto_nombre LIMIT 30";
    $types .= 's';
    $params[] = $q;

    return array_map(function ($p) {
        return ['id' => intval($p['producto_id']), 'text' => $p['producto_codigo'] . ' — ' . $p['producto_nombre']];
    }, sdbConsultar($conexion, $sql, $types, $params));
}

// ---------------------------------------------------------------------------------------------- compatibilidad
// Catálogos de marcas / modelos / submodelos (globales, como en productos.php), solo los que tienen
// compatibilidades cargadas en productos con stock de la empresa: así el filtro no ofrece opciones vacías.

function scCompatMarcas(mysqli $conexion, int $empresa_id): array
{
    return sdbConsultar($conexion, "SELECT DISTINCT ma.marca_id, ma.marca_nombre
                                    FROM gestion__productos_compatibilidad pc
                                    JOIN gestion__productos p ON p.producto_id = pc.producto_id AND p.empresa_id = pc.empresa_id AND p.controla_stock = 1
                                    JOIN gestion__marcas ma ON ma.marca_id = pc.marca_id AND ma.tabla_estado_registro_id = 1
                                    WHERE pc.empresa_id = ? AND pc.tabla_estado_registro_id = 1
                                    ORDER BY ma.marca_nombre", 'i', [$empresa_id]);
}

function scCompatModelos(mysqli $conexion, int $empresa_id, int $marca_id): array
{
    if ($marca_id <= 0) return [];
    return sdbConsultar($conexion, "SELECT DISTINCT mo.modelo_id, mo.modelo_nombre
                                    FROM gestion__productos_compatibilidad pc
                                    JOIN gestion__productos p ON p.producto_id = pc.producto_id AND p.empresa_id = pc.empresa_id AND p.controla_stock = 1
                                    JOIN gestion__modelos mo ON mo.modelo_id = pc.modelo_id AND mo.tabla_estado_registro_id = 1
                                    WHERE pc.empresa_id = ? AND pc.marca_id = ? AND pc.tabla_estado_registro_id = 1
                                    ORDER BY mo.modelo_nombre", 'ii', [$empresa_id, $marca_id]);
}

function scCompatSubmodelos(mysqli $conexion, int $empresa_id, int $modelo_id): array
{
    if ($modelo_id <= 0) return [];
    return sdbConsultar($conexion, "SELECT DISTINCT sm.submodelo_id, sm.submodelo_nombre
                                    FROM gestion__productos_compatibilidad pc
                                    JOIN gestion__productos p ON p.producto_id = pc.producto_id AND p.empresa_id = pc.empresa_id AND p.controla_stock = 1
                                    JOIN gestion__submodelos sm ON sm.submodelo_id = pc.submodelo_id AND sm.tabla_estado_registro_id = 1
                                    WHERE pc.empresa_id = ? AND pc.modelo_id = ? AND pc.tabla_estado_registro_id = 1
                                    ORDER BY sm.submodelo_nombre", 'ii', [$empresa_id, $modelo_id]);
}

// ---------------------------------------------------------------------------------------------- planilla de conteo

/**
 * Filas de la planilla de conteo: lo mismo que muestra la grilla (situación, sucursal, boca y filtros de producto),
 * hasta 5000 productos, con el stock de cada boca. El armado por boca lo hace el navegador.
 */
function scConteoListado(mysqli $conexion, int $empresa_id, int $usuario_id, array $req): array
{
    $req['reporte'] = 1;
    $req['start'] = 0;
    $req['length'] = 5000;
    $req['order'] = [['column' => 0, 'dir' => 'asc']];
    $req['columns'] = [['name' => 'codigo']];
    unset($req['con_totales']);
    $r = sdbListarStock($conexion, $empresa_id, $usuario_id, $req);
    return ['filas' => $r['data'], 'total' => $r['recordsFiltered'], 'truncado' => $r['recordsFiltered'] > count($r['data'])];
}

// ---------------------------------------------------------------------------------------------- blanqueo de negativos

/**
 * Pares boca × producto con stock negativo dentro de los filtros (sucursal, boca, sucursales visibles del usuario y
 * filtros de producto). El blanqueo trabaja por boca: un producto en −5 en una boca y +3 en otra se lleva a 0 solo
 * en la primera (la situación "Con stock negativo" de la grilla mira el total y acá no se usa).
 */
function scNegativosPorBoca(mysqli $conexion, int $empresa_id, int $usuario_id, array $req): array
{
    $boca_id = intval($req['boca_id'] ?? 0);
    $sucursal_id = intval($req['sucursal_id'] ?? 0);
    $sql = "SELECT s.boca_id, s.sucursal_id, b.boca_nombre, su.sucursal_nombre,
                   s.producto_id, p.producto_codigo, p.producto_nombre, s.cantidad
            FROM gestion__stock s
            JOIN gestion__productos p ON p.producto_id = s.producto_id AND p.empresa_id = s.empresa_id AND p.controla_stock = 1
            JOIN gestion__bocas b ON b.boca_id = s.boca_id AND b.empresa_id = s.empresa_id
            LEFT JOIN gestion__sucursales su ON su.sucursal_id = s.sucursal_id AND su.empresa_id = s.empresa_id
            WHERE s.empresa_id = ? AND s.cantidad < 0";
    $types = 'i';
    $params = [$empresa_id];
    if ($boca_id > 0) {
        $sql .= " AND s.boca_id = ?";
        $types .= 'i';
        $params[] = $boca_id;
    }
    if ($sucursal_id > 0) {
        $sql .= " AND s.sucursal_id = ?";
        $types .= 'i';
        $params[] = $sucursal_id;
    }
    $sql .= sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 's.sucursal_id');
    $sql .= sdbFiltroProductos($req, $types, $params);
    $sql .= " ORDER BY b.boca_nombre, s.boca_id, p.producto_codigo";
    return sdbConsultar($conexion, $sql, $types, $params);
}

/** Resumen para confirmar: por boca, cuántos productos y cuántas unidades se suman. */
function scBlanqueoPrevia(mysqli $conexion, int $empresa_id, int $usuario_id, array $req): array
{
    $bocas = [];
    foreach (scNegativosPorBoca($conexion, $empresa_id, $usuario_id, $req) as $f) {
        $b = intval($f['boca_id']);
        if (!isset($bocas[$b])) {
            $bocas[$b] = ['boca_id' => $b, 'boca_nombre' => $f['boca_nombre'], 'sucursal_nombre' => $f['sucursal_nombre'],
                          'productos' => 0, 'unidades' => 0.0];
        }
        $bocas[$b]['productos']++;
        $bocas[$b]['unidades'] += -(float)$f['cantidad'];
    }
    return array_values($bocas);
}

/** Tipo de ajuste para el blanqueo: el que solo suma (Sobrante, S_AP) o, si no hay, el de inventario (+/-). */
function scBlanqueoTipo(mysqli $conexion, int $empresa_id): int
{
    $subgrupo = emp_subgrupo_id($conexion, $empresa_id, 'CEF');
    if ($subgrupo <= 0) return 0;
    $f = sdbConsultar($conexion, "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos
                                  WHERE empresa_id = ? AND comprobante_subgrupo_id = ? AND tabla_estado_registro_id = 1
                                    AND impacta_stock = 1 AND stock_signo IN ('+', '+/-')
                                  ORDER BY stock_signo = '+' DESC, codigo = 'S_AP' DESC, comprobante_tipo_id LIMIT 1",
                      'ii', [$empresa_id, $subgrupo]);
    return intval($f[0]['comprobante_tipo_id'] ?? 0);
}

/**
 * Genera un Ajuste de Stock por boca que lleva a 0 cada producto negativo de lo filtrado y, si $confirmar, lo
 * confirma (mueve el stock). Cada boca es una transacción propia (agregarStockAjuste / ejecutarTransicionEstado de
 * stock_ajustes_model.php, que el _ajax.php incluye): si una falla, las demás siguen y se informa.
 * La cantidad del ajuste es la del saldo al momento de generarlo, sin redondear (deja el saldo exactamente en 0).
 */
function scBlanqueoGenerar(mysqli $conexion, int $empresa_id, int $usuario_id, array $req, int $pagina_ajustes, bool $confirmar): array
{
    $tipo_id = scBlanqueoTipo($conexion, $empresa_id);
    if ($tipo_id <= 0) {
        throw new InvalidArgumentException('La empresa no tiene un tipo de Ajuste de Stock que sume (Sobrante físico o Ajuste por inventario).');
    }
    $por_boca = [];
    foreach (scNegativosPorBoca($conexion, $empresa_id, $usuario_id, $req) as $f) {
        $por_boca[intval($f['boca_id'])][] = $f;
    }
    if (!$por_boca) {
        throw new InvalidArgumentException('No hay productos con stock negativo en lo filtrado.');
    }

    $fecha = date('Y-m-d H:i:s');
    $resultado = [];
    foreach ($por_boca as $boca_id => $filas) {
        $detalles = [];
        foreach ($filas as $f) {
            $cant = round(-(float)$f['cantidad'], 4);
            $detalles[] = [
                'producto_id' => intval($f['producto_id']), 'deposito_id' => $boca_id,
                'stock_sistema' => (float)$f['cantidad'], 'stock_fisico' => 0, 'diferencia' => $cant, 'cantidad_ajuste' => $cant,
                'costo_unitario' => 0, 'observacion' => 'Blanqueo de stock negativo',
            ];
        }
        $item = ['boca_id' => $boca_id, 'boca_nombre' => $filas[0]['boca_nombre'], 'productos' => count($filas),
                 'stock_ajuste_id' => null, 'confirmado' => false, 'error' => null];
        $alta = agregarStockAjuste($conexion, [
            'sucursal_id' => intval($filas[0]['sucursal_id']), 'deposito_id' => $boca_id, 'comprobante_tipo_id' => $tipo_id,
            'fecha' => $fecha, 'descripcion' => 'Blanqueo de stock negativo (Stock y Movimientos): ' . count($filas) . ' productos',
            'detalles' => $detalles, 'empresa_idx' => $empresa_id, 'pagina_idx' => $pagina_ajustes, 'redondear' => false,
        ]);
        if (empty($alta['resultado'])) {
            $item['error'] = $alta['error'] ?? 'No se pudo crear el ajuste';
            $resultado[] = $item;
            continue;
        }
        $item['stock_ajuste_id'] = intval($alta['stock_ajuste_id']);
        if ($confirmar) {
            $t = ejecutarTransicionEstado($conexion, $item['stock_ajuste_id'], 'confirmar', $empresa_id, $pagina_ajustes);
            if (!empty($t['success'])) {
                $item['confirmado'] = true;
            } else {
                $item['error'] = 'Quedó en Borrador: ' . ($t['error'] ?? 'no se pudo confirmar');
            }
        }
        error_log("=== scBlanqueoGenerar empresa $empresa_id usuario $usuario_id boca $boca_id: ajuste {$item['stock_ajuste_id']}, "
            . count($filas) . ' productos' . ($item['confirmado'] ? ', confirmado' : '') . ' ===');
        $resultado[] = $item;
    }
    return $resultado;
}
