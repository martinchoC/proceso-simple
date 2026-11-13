<?php
/**
 * modules/gestion_normal/exportar_productos_offline.php
 * 
 * Genera la página de productos en HTML autocontenido (100% offline) con toda
 * la información disponible (códigos, nombres, compatibilidades, ubicaciones,
 * proveedores, listas de precios, stock, etc.) y la envía por email usando la
 * función nativa mail() pura de PHP a pablo.passoni@gmail.com.
 * 
 * Guarda los archivos en modules/gestion_normal/exports/ y mantiene únicamente los últimos 10
 * archivos generados, eliminando automáticamente los que superen los 10 días de antigüedad.
 * 
 * Estructura segura contra falsos positivos de antivirus:
 * - Los datos se almacenan en un bloque `<script type="application/json">` con saltos de línea por producto.
 * - Sin uso de constructores Blob ni generadores de descargas automáticas que alerten heurísticas.
 * 
 * Puede ejecutarse:
 * 1. Desde el navegador: http://localhost/casalucho/modules/gestion_normal/exportar_productos_offline.php
 * 2. Desde CLI: php modules/gestion_normal/exportar_productos_offline.php [--enviar-email] [--to=email@ejemplo.com]
 * 3. Como opción en el menú Exportar de productos.php y productos_consulta.php
 */

// Aumentar límites para procesar el catálogo completo de productos
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/../../db.php';

// Constantes y configuración por defecto
$empresa_idx_defecto = 2; // Casa Lucho
$destinatario_defecto = 'pablo.passoni@gmail.com';
$directorio_exports = __DIR__ . '/exports';

// Asegurar existencia del directorio exports
if (!file_exists($directorio_exports)) {
    @mkdir($directorio_exports, 0777, true);
}

// Detectar entorno
$es_cli = (php_sapi_name() === 'cli');

// Acción de descarga directa si fue solicitada por HTTP
$accion = $_POST['accion'] ?? $_GET['accion'] ?? 'mostrar';

if (!$es_cli && $accion === 'descargar') {
    $nombre_solicitado = isset($_GET['archivo']) ? basename($_GET['archivo']) : 'productos_offline_latest.html';
    $ruta_descarga = $directorio_exports . '/' . $nombre_solicitado;
    if (!file_exists($ruta_descarga)) {
        $ruta_descarga = $directorio_exports . '/productos_offline_latest.html';
    }

    if (file_exists($ruta_descarga)) {
        header('Content-Description: File Transfer');
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($ruta_descarga) . '"');
        header('Content-Length: ' . filesize($ruta_descarga));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($ruta_descarga);
        exit;
    }
}

// Obtener parámetros
if ($es_cli) {
    $opciones = getopt('', ['enviar-email::', 'to::', 'empresa::']);
    $enviar_email = isset($opciones['enviar-email']) || in_array('--enviar-email', $argv);
    $destinatario = $opciones['to'] ?? $destinatario_defecto;
    $empresa_id = intval($opciones['empresa'] ?? $empresa_idx_defecto);
} else {
    $empresa_id = intval($_GET['empresa_id'] ?? $_POST['empresa_id'] ?? $empresa_idx_defecto);
    $destinatario = trim($_POST['destinatario'] ?? $_GET['destinatario'] ?? $destinatario_defecto);
    $enviar_email = ($accion === 'enviar_email' || isset($_POST['btn_enviar_email']));
}

/**
 * --------------------------------------------------------------------------
 * 1. LIMPIEZA Y ROTACIÓN DE ARCHIVOS EXPORTADOS
 * --------------------------------------------------------------------------
 * Mantiene solo los últimos 10 archivos generados y elimina aquellos
 * que tengan más de 10 días de antigüedad.
 */
function depurarArchivosExports($directorio_exports, $max_archivos = 10, $dias_retencion = 10) {
    if (!is_dir($directorio_exports)) return;

    $archivos = glob($directorio_exports . '/productos_offline_*.html');
    if (!$archivos) return;

    $ahora = time();
    $segundos_retencion = $dias_retencion * 86400;
    $archivos_validos = [];

    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);
        if ($nombre === 'productos_offline_latest.html') {
            continue; // Puntero permanente, no eliminar
        }

        $mtime = filemtime($archivo);

        // Regla 1: eliminar archivos con más de 10 días de antigüedad
        if (($ahora - $mtime) > $segundos_retencion) {
            @unlink($archivo);
            continue;
        }

        $archivos_validos[$archivo] = $mtime;
    }

    // Regla 2: conservar solo los últimos 10 archivos generados
    if (count($archivos_validos) > $max_archivos) {
        arsort($archivos_validos); // Más nuevos primero
        $contador = 0;
        foreach ($archivos_validos as $archivo => $mtime) {
            $contador++;
            if ($contador > $max_archivos) {
                @unlink($archivo);
            }
        }
    }
}

/**
 * --------------------------------------------------------------------------
 * 2. EXTRACCIÓN DE DATOS DE PRODUCTOS Y SUS RELACIONES
 * --------------------------------------------------------------------------
 */
function extraerDatosCatalogoProductos($conn, $empresa_id) {
    $inicio = microtime(true);

    // 1. Datos de la empresa
    $stmt_emp = mysqli_prepare($conn, "SELECT empresa_id, empresa, email, telefono, domicilio FROM conf__empresas WHERE empresa_id = ?");
    mysqli_stmt_bind_param($stmt_emp, "i", $empresa_id);
    mysqli_stmt_execute($stmt_emp);
    $empresa_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_emp)) ?: [
        'empresa' => 'Casa Lucho',
        'email' => '',
        'telefono' => '',
        'domicilio' => ''
    ];
    mysqli_stmt_close($stmt_emp);

    // 2. Listas de precios activas
    $listas_precios = [];
    $stmt_lp = mysqli_prepare($conn, "SELECT lista_precio_id, lista_precio_nombre FROM gestion__listas_precios WHERE tabla_estado_registro_id = 1 AND (empresa_id = 0 OR empresa_id = ?) ORDER BY lista_precio_id ASC");
    mysqli_stmt_bind_param($stmt_lp, "i", $empresa_id);
    mysqli_stmt_execute($stmt_lp);
    $res_lp = mysqli_stmt_get_result($stmt_lp);
    while ($r = mysqli_fetch_assoc($res_lp)) {
        $listas_precios[$r['lista_precio_id']] = $r['lista_precio_nombre'];
    }
    mysqli_stmt_close($stmt_lp);

    // 3. Precios por producto y lista
    $precios_por_prod = [];
    $sql_prec = "SELECT lpp.producto_id, lpp.lista_precio_id, lpp.precio_final 
                 FROM gestion__listas_precios_productos lpp
                 INNER JOIN gestion__listas_precios lp ON lpp.lista_precio_id = lp.lista_precio_id
                 WHERE lpp.tabla_estado_registro_id = 1 
                   AND lp.tabla_estado_registro_id = 1
                   AND (lp.empresa_id = 0 OR lp.empresa_id = ?)";
    $stmt_pr = mysqli_prepare($conn, $sql_prec);
    mysqli_stmt_bind_param($stmt_pr, "i", $empresa_id);
    mysqli_stmt_execute($stmt_pr);
    $res_prec = mysqli_stmt_get_result($stmt_pr);
    while ($r = mysqli_fetch_assoc($res_prec)) {
        $precios_por_prod[$r['producto_id']][$r['lista_precio_id']] = (float)$r['precio_final'];
    }
    mysqli_stmt_close($stmt_pr);

    // 4. Compatibilidades completas
    $compat_por_prod = [];
    $marcas_unicas = [];
    $modelos_por_marca = [];
    $submodelos_por_modelo = [];

    $sql_c = "SELECT pc.producto_id, ma.marca_nombre, mo.modelo_nombre, sm.submodelo_nombre, pc.anio_desde, pc.anio_hasta 
              FROM gestion__productos_compatibilidad pc
              INNER JOIN gestion__marcas ma ON pc.marca_id = ma.marca_id
              INNER JOIN gestion__modelos mo ON pc.modelo_id = mo.modelo_id
              LEFT JOIN gestion__submodelos sm ON pc.submodelo_id = sm.submodelo_id
              WHERE pc.empresa_id = ? AND pc.tabla_estado_registro_id = 1
              ORDER BY pc.producto_id, ma.marca_nombre, mo.modelo_nombre, sm.submodelo_nombre, pc.anio_desde";
    $stmt_c = mysqli_prepare($conn, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "i", $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    while ($r = mysqli_fetch_assoc($res_c)) {
        $marca = trim($r['marca_nombre'] ?? '');
        $modelo = trim($r['modelo_nombre'] ?? '');
        $submodelo = trim($r['submodelo_nombre'] ?? '');
        
        $anios = '';
        if (!empty($r['anio_desde']) || !empty($r['anio_hasta'])) {
            $d = $r['anio_desde'] ?: '?';
            $h = $r['anio_hasta'] ?: '?';
            $anios = ($d === $h) ? (string)$d : "{$d}-{$h}";
        }

        $compat_por_prod[$r['producto_id']][] = [
            'marca' => $marca,
            'modelo' => $modelo,
            'submodelo' => $submodelo,
            'anios' => $anios
        ];

        if ($marca !== '') {
            $marcas_unicas[$marca] = true;
            if ($modelo !== '') {
                $modelos_por_marca[$marca][$modelo] = true;
                if ($submodelo !== '') {
                    $submodelos_por_modelo[$modelo][$submodelo] = true;
                }
            }
        }
    }
    mysqli_stmt_close($stmt_c);

    // 5. Ubicaciones físicas
    $ubic_por_prod = [];
    $sql_u = "SELECT pu.producto_id, s.sucursal_nombre, b.boca_nombre, su.seccion, su.estanteria, su.estante, su.posicion, su.descripcion
              FROM gestion__productos_ubicaciones pu
              INNER JOIN gestion__sucursales_ubicaciones su ON pu.sucursal_ubicacion_id = su.sucursal_ubicacion_id
              LEFT JOIN gestion__sucursales s ON su.sucursal_id = s.sucursal_id
              LEFT JOIN gestion__bocas b ON su.boca_id = b.boca_id
              WHERE pu.tabla_estado_registro_id = 1
              ORDER BY pu.producto_id, s.sucursal_nombre, su.seccion, su.estanteria, su.estante, su.posicion";
    $res_u = mysqli_query($conn, $sql_u);
    if ($res_u) {
        while ($r = mysqli_fetch_assoc($res_u)) {
            $ubic_por_prod[$r['producto_id']][] = [
                'sucursal' => $r['sucursal_nombre'] ?: '',
                'boca' => $r['boca_nombre'] ?: '',
                'seccion' => $r['seccion'] ?: '',
                'estanteria' => $r['estanteria'] ?: '',
                'estante' => $r['estante'] ?: '',
                'posicion' => $r['posicion'] ?: '',
                'descripcion' => $r['descripcion'] ?: ''
            ];
        }
    }

    // 6. Proveedores
    $prov_por_prod = [];
    $sql_p = "SELECT pp.producto_id, e.entidad_nombre, pp.codigo_proveedor
              FROM gestion__productos_proveedores pp
              LEFT JOIN gestion__entidades e ON pp.entidad_id = e.entidad_id
              WHERE pp.empresa_id = ? AND pp.tabla_estado_registro_id = 1
              ORDER BY pp.producto_id, e.entidad_nombre";
    $stmt_p = mysqli_prepare($conn, $sql_p);
    mysqli_stmt_bind_param($stmt_p, "i", $empresa_id);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);
    while ($r = mysqli_fetch_assoc($res_p)) {
        $prov_por_prod[$r['producto_id']][] = [
            'proveedor' => $r['entidad_nombre'] ?: '',
            'codigo' => $r['codigo_proveedor'] ?: ''
        ];
    }
    mysqli_stmt_close($stmt_p);

    // 7. Costos actuales
    $costos_por_prod = [];
    $sql_cos = "SELECT producto_id, costo_actual FROM gestion__productos_costos WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    $stmt_cos = mysqli_prepare($conn, $sql_cos);
    mysqli_stmt_bind_param($stmt_cos, "i", $empresa_id);
    mysqli_stmt_execute($stmt_cos);
    $res_cos = mysqli_stmt_get_result($stmt_cos);
    while ($r = mysqli_fetch_assoc($res_cos)) {
        $costos_por_prod[$r['producto_id']] = (float)$r['costo_actual'];
    }
    mysqli_stmt_close($stmt_cos);

    // 8. Stock por producto
    $stock_por_prod = [];
    $sql_s = "SELECT s.producto_id, SUM(s.cantidad) as stock_total 
              FROM gestion__stock s 
              WHERE s.empresa_id = ? 
              GROUP BY s.producto_id";
    $stmt_s = mysqli_prepare($conn, $sql_s);
    mysqli_stmt_bind_param($stmt_s, "i", $empresa_id);
    mysqli_stmt_execute($stmt_s);
    $res_s = mysqli_stmt_get_result($stmt_s);
    while ($r = mysqli_fetch_assoc($res_s)) {
        $stock_por_prod[$r['producto_id']] = (float)$r['stock_total'];
    }
    mysqli_stmt_close($stmt_s);

    // 9. Productos base con todos sus atributos
    $sql_prod = "SELECT 
                    p.producto_id,
                    p.producto_codigo,
                    p.producto_nombre,
                    p.producto_descripcion,
                    p.codigo_barras,
                    p.lado,
                    p.material,
                    p.color,
                    p.peso,
                    p.dimensiones,
                    p.garantia,
                    p.controla_stock,
                    um.unidad_nombre,
                    um.unidad_abreviatura,
                    pt.producto_tipo,
                    cat.producto_categoria_nombre,
                    er.estado_registro,
                    IFNULL(iva.porcentaje, 21.0) as iva_porcentaje
                 FROM gestion__productos p
                 LEFT JOIN conf__estados_registros er ON p.tabla_estado_registro_id = er.estado_registro_id
                 LEFT JOIN gestion__unidades_medida um ON p.unidad_medida_id = um.unidad_medida_id
                 LEFT JOIN gestion__productos_tipos pt ON p.producto_tipo_id = pt.producto_tipo_id
                 LEFT JOIN gestion__productos_categorias cat ON p.producto_categoria_id = cat.producto_categoria_id
                 LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id AND iva.tabla_estado_registro_id = 1
                 WHERE p.empresa_id = ? AND p.controla_stock = 1
                 ORDER BY p.producto_codigo ASC";

    $stmt_prod = mysqli_prepare($conn, $sql_prod);
    mysqli_stmt_bind_param($stmt_prod, "i", $empresa_id);
    mysqli_stmt_execute($stmt_prod);
    $res_prod = mysqli_stmt_get_result($stmt_prod);

    $productos = [];
    while ($row = mysqli_fetch_assoc($res_prod)) {
        $pid = (int)$row['producto_id'];
        $iva = (float)$row['iva_porcentaje'];

        // Armar listas de precios con neto y final con IVA
        $precios = [];
        if (isset($precios_por_prod[$pid])) {
            foreach ($precios_por_prod[$pid] as $lid => $neto) {
                $con_iva = round($neto * (1 + $iva / 100), 2);
                $precios[$lid] = [
                    'neto' => $neto,
                    'con_iva' => $con_iva
                ];
            }
        }

        // Construir string de búsqueda precalculado para rendimiento ultrarrápido offline
        $tokens_busqueda = [
            $row['producto_codigo'],
            $row['producto_nombre'],
            $row['codigo_barras'],
            $row['producto_descripcion']
        ];
        if (isset($prov_por_prod[$pid])) {
            foreach ($prov_por_prod[$pid] as $pr) {
                $tokens_busqueda[] = $pr['codigo'];
                $tokens_busqueda[] = $pr['proveedor'];
            }
        }
        if (isset($compat_por_prod[$pid])) {
            foreach ($compat_por_prod[$pid] as $cp) {
                $tokens_busqueda[] = $cp['marca'];
                $tokens_busqueda[] = $cp['modelo'];
                $tokens_busqueda[] = $cp['submodelo'];
                $tokens_busqueda[] = $cp['anios'];
            }
        }
        if (isset($ubic_por_prod[$pid])) {
            foreach ($ubic_por_prod[$pid] as $ub) {
                $tokens_busqueda[] = $ub['seccion'];
                $tokens_busqueda[] = $ub['estanteria'];
                $tokens_busqueda[] = $ub['estante'];
                $tokens_busqueda[] = $ub['posicion'];
            }
        }

        $productos[] = [
            'id' => $pid,
            'c' => $row['producto_codigo'],
            'n' => $row['producto_nombre'],
            'd' => $row['producto_descripcion'] ?: '',
            'b' => $row['codigo_barras'] ?: '',
            'u' => $row['unidad_abreviatura'] ?: 'u',
            'un' => $row['unidad_nombre'] ?: 'Unidad',
            't' => $row['producto_tipo'] ?: 'Producto',
            'cat' => $row['producto_categoria_nombre'] ?: '',
            'est' => $row['estado_registro'] ?: 'Activo',
            'iva' => $iva,
            'lado' => $row['lado'] ?: '',
            'mat' => $row['material'] ?: '',
            'col' => $row['color'] ?: '',
            'peso' => $row['peso'] ? (float)$row['peso'] : null,
            'dim' => $row['dimensiones'] ?: '',
            'gar' => $row['garantia'] ?: '',
            'costo' => $costos_por_prod[$pid] ?? null,
            'stock' => $stock_por_prod[$pid] ?? null,
            'precios' => $precios,
            'compat' => $compat_por_prod[$pid] ?? [],
            'ubic' => $ubic_por_prod[$pid] ?? [],
            'prov' => $prov_por_prod[$pid] ?? [],
            'q' => mb_strtolower(implode(' ', array_filter($tokens_busqueda)))
        ];
    }
    mysqli_stmt_close($stmt_prod);

    $tiempo_procesamiento = round((microtime(true) - $inicio), 2);

    // Formatear marcas y modelos para filtros dinámicos
    ksort($marcas_unicas);
    $marcas_lista = array_keys($marcas_unicas);

    $modelos_filtro = [];
    foreach ($modelos_por_marca as $m => $mods) {
        $keys = array_keys($mods);
        sort($keys);
        $modelos_filtro[$m] = $keys;
    }

    $submodelos_filtro = [];
    foreach ($submodelos_por_modelo as $mod => $subms) {
        $keys = array_keys($subms);
        sort($keys);
        $submodelos_filtro[$mod] = $keys;
    }

    return [
        'empresa' => $empresa_info,
        'fecha_generacion' => date('d/m/Y H:i:s'),
        'total_productos' => count($productos),
        'tiempo_segundos' => $tiempo_procesamiento,
        'listas_precios' => $listas_precios,
        'marcas' => $marcas_lista,
        'modelos_por_marca' => $modelos_filtro,
        'submodelos_por_modelo' => $submodelos_filtro,
        'productos' => $productos
    ];
}

/**
 * Serializa los datos en JSON con saltos de línea por producto para evitar líneas excesivamente
 * largas que activan alertas heurísticas en antivirus (como McAfee).
 */
function serializarJsonSeguroAntivirus($datos) {
    // Para que los datos no superen los límites de longitud de línea de los antivirus
    // y se mantenga una estructura JSON 100% válida:
    // Codificamos los metadatos con saltos de línea y cada producto en una sola línea.
    $productos = $datos['productos'];
    $datos['productos'] = '__PLACEHOLDER_PRODUCTOS__';

    $json_base = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $lineas_productos = [];
    foreach ($productos as $p) {
        $lineas_productos[] = '    ' . json_encode($p, JSON_UNESCAPED_UNICODE);
    }
    $bloque_productos = "[\n" . implode(",\n", $lineas_productos) . "\n  ]";

    return str_replace('"__PLACEHOLDER_PRODUCTOS__"', $bloque_productos, $json_base);
}

/**
 * --------------------------------------------------------------------------
 * 3. GENERADOR DE HTML AUTOCONTENIDO (OFFLINE TOTAL - SEGURO ANTIVIRUS)
 * --------------------------------------------------------------------------
 */
function generarHtmlOffline($datos) {
    $empresa_nombre = htmlspecialchars($datos['empresa']['empresa'] ?? 'Casa Lucho', ENT_QUOTES, 'UTF-8');
    $fecha_generacion = $datos['fecha_generacion'];
    $total_productos = number_format($datos['total_productos'], 0, ',', '.');

    // Listas de precios para thead
    $ths_listas = '';
    $paleta = ['#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#d63384', '#0dcaf0'];
    $idx_c = 0;
    foreach ($datos['listas_precios'] as $lp_id => $lp_nom) {
        $color = $paleta[$idx_c % count($paleta)];
        $ths_listas .= '<th class="text-end" style="background-color: ' . $color . '; color: #fff; min-width: 140px;">' . htmlspecialchars($lp_nom, ENT_QUOTES, 'UTF-8') . '</th>';
        $idx_c++;
    }

    // Datos serializados de forma limpia y segura con saltos de línea
    $json_catalogo_seguro = serializarJsonSeguroAntivirus($datos);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo Offline - {$empresa_nombre}</title>
    <style>
        :root {
            --primary: #0d6efd;
            --primary-dark: #0b5ed7;
            --secondary: #6c757d;
            --success: #198754;
            --info: #0dcaf0;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
            --border-color: #dee2e6;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --header-bg: #1e293b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            line-height: 1.5;
            font-size: 14px;
        }
        /* Top Navigation Header */
        .app-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            padding: 14px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .app-brand { display: flex; align-items: center; gap: 12px; }
        .app-brand h1 { font-size: 20px; font-weight: 700; margin: 0; letter-spacing: -0.3px; }
        .app-badge-offline {
            background-color: #10b981;
            color: white;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .header-meta { font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 16px; }
        .header-actions { display: flex; gap: 8px; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
            background: #fff;
            color: #334155;
        }
        .btn:hover { background: #f8fafc; }
        .btn-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #16a34a; color: #fff; border-color: #16a34a; }
        .btn-success:hover { background: #15803d; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .btn-outline { background: transparent; color: #fff; border-color: rgba(255,255,255,0.3); }
        .btn-outline:hover { background: rgba(255,255,255,0.1); }

        /* Container & Cards */
        .container { max-width: 1600px; margin: 20px auto; padding: 0 16px; }
        .card {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-body { padding: 16px; }

        /* Filters section */
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            align-items: end;
        }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #475569; }
        .form-control, .form-select {
            padding: 7px 10px;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            outline: none;
            background: #ffffff;
            color: #1e293b;
            transition: border-color 0.15s;
        }
        .form-control:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }

        /* Stats Row */
        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            padding: 10px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
            gap: 12px;
        }
        .stats-badge {
            font-weight: 600;
            color: #0f172a;
            background: #e2e8f0;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .pagination-container {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .page-btn {
            padding: 4px 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .page-btn.active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Table styles */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table.catalogo-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            white-space: normal;
        }
        table.catalogo-table thead th {
            padding: 10px 12px;
            background: #f1f5f9;
            color: #334155;
            font-weight: 600;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
            position: sticky;
            top: 60px;
            z-index: 10;
            cursor: pointer;
            user-select: none;
        }
        table.catalogo-table thead th:hover { background: #e2e8f0; }
        table.catalogo-table tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        table.catalogo-table tbody tr:hover { background-color: #f8fafc; }
        .row-even { background-color: #ffffff; }
        .row-odd { background-color: #fcfdfe; }

        /* Badges & Chips */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin: 1px;
        }
        .badge-brand { background: #e0e7ff; color: #3730a3; }
        .badge-model { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .badge-years { background: #fef3c7; color: #92400e; font-family: monospace; font-size: 11px; }
        .badge-ubic { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-family: monospace; }
        .badge-prov { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
        .badge-stock { background: #dcfce7; color: #15803d; font-weight: 700; }
        .badge-no-stock { background: #fee2e2; color: #991b1b; }
        .price-box { text-align: right; }
        .price-neto { font-size: 13px; font-weight: 600; color: #0f172a; }
        .price-iva { font-size: 11px; color: #64748b; }

        /* Modal Details */
        .modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 16px;
        }
        .modal-content {
            background: #ffffff;
            border-radius: 12px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.2s ease-out;
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-header {
            padding: 16px 20px;
            background: #1e293b;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .modal-title { font-size: 18px; font-weight: 700; }
        .modal-close {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close:hover { color: #ffffff; }
        .modal-body { padding: 20px; }
        .modal-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 16px;
            overflow-x: auto;
        }
        .tab-btn {
            padding: 8px 16px;
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            margin-bottom: -2px;
        }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .detail-item {
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .detail-label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 2px; }
        .detail-val { font-size: 14px; font-weight: 600; color: #0f172a; word-break: break-word; }

        /* Print styles */
        @media print {
            .app-header, .filters-card, .pagination-container, .btn-action { display: none !important; }
            body { background: white; font-size: 10pt; }
            .container { max-width: 100%; margin: 0; padding: 0; }
            .card { border: none; box-shadow: none; }
            table.catalogo-table thead th { position: static; background: #e2e8f0 !important; color: black !important; }
            .print-only-header { display: block !important; margin-bottom: 15px; }
        }
        .print-only-header { display: none; }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="app-header">
        <div class="app-brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            <h1>{$empresa_nombre}</h1>
            <span class="app-badge-offline">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Consulta Offline
            </span>
        </div>
        <div class="header-meta">
            <span><strong id="hdrTotal">{$total_productos}</strong> productos disponibles</span>
            <span>Generado: <strong>{$fecha_generacion}</strong></span>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline btn-sm" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Imprimir Catálogo
            </button>
        </div>
    </header>

    <div class="container">
        <!-- Print Header -->
        <div class="print-only-header">
            <h2>{$empresa_nombre} - Catálogo de Productos</h2>
            <p>Fecha de emisión: {$fecha_generacion} | Total productos: {$total_productos}</p>
            <hr style="margin: 10px 0;">
        </div>

        <!-- Filtros Card -->
        <div class="card filters-card">
            <div class="card-body">
                <div class="filters-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="txtBuscar">Búsqueda rápida (Código, Nombre, Proveedor, Detalle...)</label>
                        <input type="text" id="txtBuscar" class="form-control" placeholder="Escribí una palabra, código o 'marca modelo'..." autofocus>
                    </div>
                    <div class="form-group">
                        <label for="selMarca">Marca Compatible</label>
                        <select id="selMarca" class="form-select">
                            <option value="">Todas las marcas</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="selModelo">Modelo Compatible</label>
                        <select id="selModelo" class="form-select" disabled>
                            <option value="">Todos los modelos</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="selSubmodelo">Submodelo</label>
                        <select id="selSubmodelo" class="form-select" disabled>
                            <option value="">Todos los submodelos</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="selStock">Stock</label>
                        <select id="selStock" class="form-select">
                            <option value="todos">Todos los productos</option>
                            <option value="con_stock">Solo con stock</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-secondary" onclick="limpiarFiltros()" style="width: 100%;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Productos Card -->
        <div class="card">
            <!-- Barra de estado y paginación superior -->
            <div class="stats-bar">
                <div>
                    Mostrando <span id="lblInicio">0</span> a <span id="lblFin">0</span> de <span class="stats-badge" id="lblFiltrados">0</span> productos encontrados (de {$total_productos} totales)
                </div>
                <div class="pagination-container">
                    <label for="selPorPagina" style="font-size: 12px; color: #64748b;">Por página:</label>
                    <select id="selPorPagina" class="form-select" style="width: auto; padding: 2px 6px; font-size: 12px;" onchange="cambiarPorPagina()">
                        <option value="15">15</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                        <option value="todos">Todos</option>
                    </select>
                    <button class="page-btn" id="btnPagAnt" onclick="paginaAnterior()" title="Página anterior">&laquo;</button>
                    <span id="lblPaginaActual" style="font-size: 12px; font-weight: 600;">1 / 1</span>
                    <button class="page-btn" id="btnPagSig" onclick="paginaSiguiente()" title="Página siguiente">&raquo;</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="catalogo-table" id="tablaCatalogo">
                    <thead>
                        <tr>
                            <th onclick="ordenarPor('c')" width="100">Código <span id="sort_c"></span></th>
                            <th onclick="ordenarPor('n')">Nombre del Producto <span id="sort_n"></span></th>
                            <th width="200">Compatibilidad (Marca/Modelo/Año)</th>
                            <th width="160">Ubicaciones</th>
                            <th width="120">Cód. Proveedor</th>
                            <th width="80" class="text-center">Stock</th>
                            {$ths_listas}
                            <th width="90" class="text-center btn-action">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTabla">
                        <!-- Generado dinámicamente -->
                    </tbody>
                </table>
            </div>

            <!-- Barra inferior -->
            <div class="stats-bar" style="border-top: 1px solid #e2e8f0; border-bottom: none;">
                <div style="color: #64748b; font-size: 11px;">
                    * Precios expresados en moneda de curso legal. Precio neto / Con IVA incluido.
                </div>
                <div class="pagination-container">
                    <button class="page-btn" onclick="paginaAnterior()">&laquo; Anterior</button>
                    <span id="lblPaginaActualBajo" style="font-size: 12px; font-weight: 600;">1 / 1</span>
                    <button class="page-btn" onclick="paginaSiguiente()">Siguiente &raquo;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalle del Producto -->
    <div class="modal-backdrop" id="modalDetalle" onclick="if(event.target===this) cerrarModal()">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="modal-title" id="mCodigo">-</div>
                    <div style="font-size: 12px; color: #94a3b8;" id="mNombre">-</div>
                </div>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="tab-btn active" onclick="verPestana('tabInfo', this)">Información General</button>
                    <button class="tab-btn" onclick="verPestana('tabCompat', this)">Compatibilidad Vehicular (<span id="mCountCompat">0</span>)</button>
                    <button class="tab-btn" onclick="verPestana('tabUbic', this)">Ubicaciones en Depósito (<span id="mCountUbic">0</span>)</button>
                    <button class="tab-btn" onclick="verPestana('tabProv', this)">Proveedores (<span id="mCountProv">0</span>)</button>
                    <button class="tab-btn" onclick="verPestana('tabPrecios', this)">Listas de Precios</button>
                </div>

                <!-- Tab 1: Info General -->
                <div id="tabInfo" class="tab-content active">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Código Producto</div>
                            <div class="detail-val" id="mdCodigo">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Código de Barras</div>
                            <div class="detail-val" id="mdBarras">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tipo</div>
                            <div class="detail-val" id="mdTipo">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Categoría</div>
                            <div class="detail-val" id="mdCategoria">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Unidad de Medida</div>
                            <div class="detail-val" id="mdUnidad">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Alícuota IVA</div>
                            <div class="detail-val" id="mdIva">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Stock Actual</div>
                            <div class="detail-val" id="mdStock">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Estado Registro</div>
                            <div class="detail-val" id="mdEstado">-</div>
                        </div>
                    </div>

                    <!-- Ficha Técnica -->
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Lado</div>
                            <div class="detail-val" id="mdLado">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Material</div>
                            <div class="detail-val" id="mdMaterial">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Color</div>
                            <div class="detail-val" id="mdColor">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Dimensiones / Peso</div>
                            <div class="detail-val" id="mdDimensiones">-</div>
                        </div>
                    </div>

                    <div class="detail-item" style="margin-top: 10px;">
                        <div class="detail-label">Descripción Extendida</div>
                        <div class="detail-val" id="mdDescripcion" style="white-space: pre-wrap; font-weight: normal; font-size: 13px;">-</div>
                    </div>
                </div>

                <!-- Tab 2: Compatibilidad -->
                <div id="tabCompat" class="tab-content">
                    <table class="catalogo-table" style="width: 100%;">
                        <thead>
                            <tr style="background: #e2e8f0;">
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Submodelo</th>
                                <th class="text-center">Años</th>
                            </tr>
                        </thead>
                        <tbody id="mTablaCompat"></tbody>
                    </table>
                </div>

                <!-- Tab 3: Ubicaciones -->
                <div id="tabUbic" class="tab-content">
                    <table class="catalogo-table" style="width: 100%;">
                        <thead>
                            <tr style="background: #e2e8f0;">
                                <th>Sucursal / Boca</th>
                                <th>Sección</th>
                                <th>Estantería</th>
                                <th>Estante</th>
                                <th>Posición</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody id="mTablaUbic"></tbody>
                    </table>
                </div>

                <!-- Tab 4: Proveedores -->
                <div id="tabProv" class="tab-content">
                    <table class="catalogo-table" style="width: 100%;">
                        <thead>
                            <tr style="background: #e2e8f0;">
                                <th>Razón Social / Proveedor</th>
                                <th>Código Proveedor</th>
                            </tr>
                        </thead>
                        <tbody id="mTablaProv"></tbody>
                    </table>
                </div>

                <!-- Tab 5: Precios -->
                <div id="tabPrecios" class="tab-content">
                    <table class="catalogo-table" style="width: 100%;">
                        <thead>
                            <tr style="background: #e2e8f0;">
                                <th>Lista de Precios</th>
                                <th class="text-end">Precio Neto</th>
                                <th class="text-center">IVA</th>
                                <th class="text-end">Precio Final (c/IVA)</th>
                            </tr>
                        </thead>
                        <tbody id="mTablaPrecios"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor de datos JSON estático (no ejecutable por antivirus) -->
    <script type="application/json" id="catalogo_datos">
{$json_catalogo_seguro}
    </script>

    <!-- Lógica de búsqueda y renderizado -->
    <script>
        const CAT = JSON.parse(document.getElementById('catalogo_datos').textContent);
        let itemsFiltrados = CAT.productos.slice();
        let paginaActual = 1;
        let porPagina = 25;
        let columnaOrden = 'c';
        let ordenAsc = true;

        // Formateador de moneda es-AR
        function fmt(n) {
            if (n === null || n === undefined || isNaN(n)) return '-';
            return parseFloat(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Inicialización al cargar la página
        window.addEventListener('DOMContentLoaded', function() {
            cargarSelectMarcas();
            aplicarFiltros();

            document.getElementById('txtBuscar').addEventListener('input', debounce(aplicarFiltros, 200));
            document.getElementById('selMarca').addEventListener('change', function() {
                actualizarModelos();
                aplicarFiltros();
            });
            document.getElementById('selModelo').addEventListener('change', function() {
                actualizarSubmodelos();
                aplicarFiltros();
            });
            document.getElementById('selSubmodelo').addEventListener('change', aplicarFiltros);
            document.getElementById('selStock').addEventListener('change', aplicarFiltros);
        });

        function debounce(func, wait) {
            let timeout;
            return function() {
                const context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        }

        function cargarSelectMarcas() {
            const selMarca = document.getElementById('selMarca');
            for (let i = 0; i < CAT.marcas.length; i++) {
                const m = CAT.marcas[i];
                const opt = document.createElement('option');
                opt.value = m;
                opt.textContent = m;
                selMarca.appendChild(opt);
            }
        }

        function actualizarModelos() {
            const marca = document.getElementById('selMarca').value;
            const selModelo = document.getElementById('selModelo');
            const selSubmodelo = document.getElementById('selSubmodelo');
            selModelo.innerHTML = '<option value="">Todos los modelos</option>';
            selSubmodelo.innerHTML = '<option value="">Todos los submodelos</option>';
            selSubmodelo.disabled = true;

            if (marca && CAT.modelos_por_marca[marca]) {
                const mods = CAT.modelos_por_marca[marca];
                for (let i = 0; i < mods.length; i++) {
                    const opt = document.createElement('option');
                    opt.value = mods[i];
                    opt.textContent = mods[i];
                    selModelo.appendChild(opt);
                }
                selModelo.disabled = false;
            } else {
                selModelo.disabled = true;
            }
        }

        function actualizarSubmodelos() {
            const modelo = document.getElementById('selModelo').value;
            const selSubmodelo = document.getElementById('selSubmodelo');
            selSubmodelo.innerHTML = '<option value="">Todos los submodelos</option>';

            if (modelo && CAT.submodelos_por_modelo[modelo]) {
                const subms = CAT.submodelos_por_modelo[modelo];
                for (let i = 0; i < subms.length; i++) {
                    const opt = document.createElement('option');
                    opt.value = subms[i];
                    opt.textContent = subms[i];
                    selSubmodelo.appendChild(opt);
                }
                selSubmodelo.disabled = false;
            } else {
                selSubmodelo.disabled = true;
            }
        }

        function limpiarFiltros() {
            document.getElementById('txtBuscar').value = '';
            document.getElementById('selMarca').value = '';
            actualizarModelos();
            document.getElementById('selStock').value = 'todos';
            aplicarFiltros();
        }

        function aplicarFiltros() {
            const q = document.getElementById('txtBuscar').value.trim().toLowerCase();
            const marca = document.getElementById('selMarca').value;
            const modelo = document.getElementById('selModelo').value;
            const submodelo = document.getElementById('selSubmodelo').value;
            const stockFiltro = document.getElementById('selStock').value;

            const palabras = q ? q.split(/\s+/).filter(function(w) { return w.length > 0; }) : [];

            itemsFiltrados = CAT.productos.filter(function(p) {
                // Filtro búsqueda libre
                if (palabras.length > 0) {
                    for (let i = 0; i < palabras.length; i++) {
                        if (p.q.indexOf(palabras[i]) === -1) return false;
                    }
                }

                // Filtro de marca, modelo, submodelo
                if (marca || modelo || submodelo) {
                    let tieneCompat = false;
                    for (let i = 0; i < p.compat.length; i++) {
                        const c = p.compat[i];
                        if (marca && c.marca !== marca) continue;
                        if (modelo && c.modelo !== modelo) continue;
                        if (submodelo && c.submodelo !== submodelo) continue;
                        tieneCompat = true;
                        break;
                    }
                    if (!tieneCompat) return false;
                }

                // Filtro de stock
                if (stockFiltro === 'con_stock' && (!p.stock || p.stock <= 0)) {
                    return false;
                }

                return true;
            });

            ejecutarOrden();
            paginaActual = 1;
            renderizarTabla();
        }

        function ordenarPor(col) {
            if (columnaOrden === col) {
                ordenAsc = !ordenAsc;
            } else {
                columnaOrden = col;
                ordenAsc = true;
            }
            ejecutarOrden();
            renderizarTabla();
        }

        function ejecutarOrden() {
            itemsFiltrados.sort(function(a, b) {
                const vA = a[columnaOrden] || '';
                const vB = b[columnaOrden] || '';
                if (typeof vA === 'string') {
                    const cmp = vA.localeCompare(vB, 'es', { numeric: true });
                    return ordenAsc ? cmp : -cmp;
                }
                return ordenAsc ? (vA - vB) : (vB - vA);
            });
        }

        function renderizarTabla() {
            const tbody = document.getElementById('cuerpoTabla');
            tbody.innerHTML = '';

            const total = itemsFiltrados.length;
            const porPag = (porPagina === 'todos') ? total : parseInt(porPagina);
            const totalPaginas = Math.ceil(total / (porPag || 1)) || 1;

            if (paginaActual > totalPaginas) paginaActual = totalPaginas;
            if (paginaActual < 1) paginaActual = 1;

            const inicio = (paginaActual - 1) * porPag;
            const fin = Math.min(inicio + porPag, total);

            document.getElementById('lblFiltrados').textContent = total.toLocaleString('es-AR');
            document.getElementById('lblInicio').textContent = total > 0 ? (inicio + 1).toLocaleString('es-AR') : 0;
            document.getElementById('lblFin').textContent = fin.toLocaleString('es-AR');
            document.getElementById('lblPaginaActual').textContent = paginaActual + ' / ' + totalPaginas;
            document.getElementById('lblPaginaActualBajo').textContent = paginaActual + ' / ' + totalPaginas;
            document.getElementById('btnPagAnt').disabled = (paginaActual <= 1);
            document.getElementById('btnPagSig').disabled = (paginaActual >= totalPaginas);

            // Iconos de orden
            document.querySelectorAll('[id^="sort_"]').forEach(function(el) { el.textContent = ''; });
            const sortIcon = ordenAsc ? '▲' : '▼';
            const sortEl = document.getElementById('sort_' + columnaOrden);
            if (sortEl) sortEl.textContent = sortIcon;

            if (total === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 30px; color: #64748b;">No se encontraron productos que coincidan con la búsqueda o filtros aplicados.</td></tr>';
                return;
            }

            const itemsMostrar = itemsFiltrados.slice(inicio, fin);
            const fragment = document.createDocumentFragment();

            for (let idx = 0; idx < itemsMostrar.length; idx++) {
                const p = itemsMostrar[idx];
                const tr = document.createElement('tr');
                tr.className = (idx % 2 === 0) ? 'row-even' : 'row-odd';

                let html = '<td class="fw-bold" style="font-family: monospace; font-weight: 700; color: #1e293b;">' + p.c + '</td>';
                html += '<td><div style="font-weight: 600; color: #0f172a;">' + p.n + '</div>' +
                        (p.b ? '<div style="font-size: 11px; color: #64748b;">EAN: ' + p.b + '</div>' : '') + '</td>';

                // Compatibilidad
                let compatHtml = '';
                if (p.compat && p.compat.length > 0) {
                    const maxComp = p.compat.slice(0, 3);
                    for (let c = 0; c < maxComp.length; c++) {
                        const cp = maxComp[c];
                        compatHtml += '<div style="margin-bottom: 2px;">' +
                            '<span class="badge badge-brand">' + cp.marca + '</span> ' +
                            '<span class="badge badge-model">' + cp.modelo + ' ' + (cp.submodelo || '') + '</span>' +
                            (cp.anios ? ' <span class="badge badge-years">' + cp.anios + '</span>' : '') +
                        '</div>';
                    }
                    if (p.compat.length > 3) {
                        compatHtml += '<div style="font-size: 11px; color: #2563eb; font-weight: 600;">+' + (p.compat.length - 3) + ' compatibilidades más...</div>';
                    }
                } else {
                    compatHtml = '<span style="color: #94a3b8; font-size: 11px;">Universal / Sin esp.</span>';
                }
                html += '<td>' + compatHtml + '</td>';

                // Ubicaciones
                let ubicHtml = '';
                if (p.ubic && p.ubic.length > 0) {
                    const maxU = p.ubic.slice(0, 2);
                    for (let u = 0; u < maxU.length; u++) {
                        const ub = maxU[u];
                        let partes = [];
                        if (ub.seccion) partes.push(ub.seccion);
                        if (ub.estanteria) partes.push('Est.' + ub.estanteria);
                        if (ub.estante) partes.push('Nro.' + ub.estante);
                        if (ub.posicion) partes.push('Pos.' + ub.posicion);
                        ubicHtml += '<div class="badge badge-ubic" title="' + ub.sucursal + '">' + partes.join(' ') + '</div>';
                    }
                    if (p.ubic.length > 2) {
                        ubicHtml += '<div style="font-size: 10px; color: #166534;">+' + (p.ubic.length - 2) + ' ubicaciones</div>';
                    }
                } else {
                    ubicHtml = '<span style="color: #94a3b8; font-size: 11px;">-</span>';
                }
                html += '<td>' + ubicHtml + '</td>';

                // Proveedor
                let provHtml = '';
                if (p.prov && p.prov.length > 0) {
                    const maxP = p.prov.slice(0, 2);
                    for (let pr = 0; pr < maxP.length; pr++) {
                        provHtml += '<div class="badge badge-prov" title="' + maxP[pr].proveedor + '">' + maxP[pr].codigo + '</div>';
                    }
                    if (p.prov.length > 2) provHtml += '<div style="font-size: 10px; color: #475569;">+' + (p.prov.length - 2) + ' más</div>';
                } else {
                    provHtml = '<span style="color: #94a3b8;">-</span>';
                }
                html += '<td>' + provHtml + '</td>';

                // Stock
                let stockHtml = '';
                if (p.stock !== null && p.stock !== undefined) {
                    stockHtml = (p.stock > 0)
                        ? '<span class="badge badge-stock">' + p.stock + ' ' + p.u + '</span>'
                        : '<span class="badge badge-no-stock">0 ' + p.u + '</span>';
                } else {
                    stockHtml = '<span style="color: #94a3b8; font-size: 11px;">-</span>';
                }
                html += '<td class="text-center">' + stockHtml + '</td>';

                // Listas de precios
                for (let lp_id in CAT.listas_precios) {
                    const pr = p.precios[lp_id];
                    if (pr) {
                        html += '<td class="price-box">' +
                            '<div class="price-neto">$ ' + fmt(pr.neto) + '</div>' +
                            '<div class="price-iva">c/IVA: $ ' + fmt(pr.con_iva) + '</div>' +
                        '</td>';
                    } else {
                        html += '<td class="price-box" style="color: #94a3b8;">-</td>';
                    }
                }

                // Acción
                html += '<td class="text-center btn-action">' +
                    '<button class="btn btn-sm btn-primary" onclick="abrirModalDetalle(' + p.id + ')" title="Ver toda la información">Ver</button>' +
                '</td>';

                tr.innerHTML = html;
                fragment.appendChild(tr);
            }

            tbody.appendChild(fragment);
        }

        function cambiarPorPagina() {
            porPagina = document.getElementById('selPorPagina').value;
            paginaActual = 1;
            renderizarTabla();
        }

        function paginaAnterior() {
            if (paginaActual > 1) {
                paginaActual--;
                renderizarTabla();
            }
        }

        function paginaSiguiente() {
            const porPag = (porPagina === 'todos') ? itemsFiltrados.length : parseInt(porPagina);
            const totalPaginas = Math.ceil(itemsFiltrados.length / (porPag || 1)) || 1;
            if (paginaActual < totalPaginas) {
                paginaActual++;
                renderizarTabla();
            }
        }

        function abrirModalDetalle(id) {
            let p = null;
            for (let i = 0; i < CAT.productos.length; i++) {
                if (CAT.productos[i].id === id) { p = CAT.productos[i]; break; }
            }
            if (!p) return;

            document.getElementById('mCodigo').textContent = p.c;
            document.getElementById('mNombre').textContent = p.n;
            document.getElementById('mdCodigo').textContent = p.c;
            document.getElementById('mdBarras').textContent = p.b || 'Sin código de barras';
            document.getElementById('mdTipo').textContent = p.t || 'Producto';
            document.getElementById('mdCategoria').textContent = p.cat || 'General';
            document.getElementById('mdUnidad').textContent = p.un + ' (' + p.u + ')';
            document.getElementById('mdIva').textContent = p.iva + '%';
            document.getElementById('mdStock').textContent = (p.stock !== null) ? (p.stock + ' ' + p.u) : 'No informado';
            document.getElementById('mdEstado').textContent = p.est;
            document.getElementById('mdLado').textContent = p.lado || '-';
            document.getElementById('mdMaterial').textContent = p.mat || '-';
            document.getElementById('mdColor').textContent = p.col || '-';
            document.getElementById('mdDimensiones').textContent = (p.dim ? p.dim : '') + (p.peso ? (' / ' + p.peso + ' kg') : '-');
            document.getElementById('mdDescripcion').textContent = p.d || p.n;

            // Compatibilidades
            document.getElementById('mCountCompat').textContent = p.compat.length;
            const tbCompat = document.getElementById('mTablaCompat');
            tbCompat.innerHTML = '';
            if (p.compat.length === 0) {
                tbCompat.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 16px;">Sin compatibilidades cargadas.</td></tr>';
            } else {
                for (let i = 0; i < p.compat.length; i++) {
                    const c = p.compat[i];
                    tbCompat.innerHTML += '<tr><td><strong>' + c.marca + '</strong></td><td>' + c.modelo + '</td><td>' + (c.submodelo || '-') + '</td><td class="text-center">' + (c.anios || 'Todos') + '</td></tr>';
                }
            }

            // Ubicaciones
            document.getElementById('mCountUbic').textContent = p.ubic.length;
            const tbUbic = document.getElementById('mTablaUbic');
            tbUbic.innerHTML = '';
            if (p.ubic.length === 0) {
                tbUbic.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 16px;">Sin ubicaciones registradas.</td></tr>';
            } else {
                for (let i = 0; i < p.ubic.length; i++) {
                    const u = p.ubic[i];
                    tbUbic.innerHTML += '<tr><td>' + u.sucursal + (u.boca ? ' (' + u.boca + ')' : '') + '</td><td>' + (u.seccion || '-') + '</td><td>' + (u.estanteria || '-') + '</td><td>' + (u.estante || '-') + '</td><td>' + (u.posicion || '-') + '</td><td>' + (u.descripcion || '-') + '</td></tr>';
                }
            }

            // Proveedores
            document.getElementById('mCountProv').textContent = p.prov.length;
            const tbProv = document.getElementById('mTablaProv');
            tbProv.innerHTML = '';
            if (p.prov.length === 0) {
                tbProv.innerHTML = '<tr><td colspan="2" style="text-align: center; color: #94a3b8; padding: 16px;">Sin proveedores asignados.</td></tr>';
            } else {
                for (let i = 0; i < p.prov.length; i++) {
                    tbProv.innerHTML += '<tr><td><strong>' + p.prov[i].proveedor + '</strong></td><td><span class="badge badge-prov">' + p.prov[i].codigo + '</span></td></tr>';
                }
            }

            // Precios
            const tbPrecios = document.getElementById('mTablaPrecios');
            tbPrecios.innerHTML = '';
            for (let lp_id in CAT.listas_precios) {
                const nombreLista = CAT.listas_precios[lp_id];
                const pr = p.precios[lp_id];
                tbPrecios.innerHTML += '<tr><td><strong>' + nombreLista + '</strong></td><td class="text-end">' + (pr ? ('$ ' + fmt(pr.neto)) : '-') + '</td><td class="text-center">' + p.iva + '%</td><td class="text-end" style="font-weight: 700; color: #16a34a;">' + (pr ? ('$ ' + fmt(pr.con_iva)) : '-') + '</td></tr>';
            }

            verPestana('tabInfo', document.querySelector('.modal-tabs .tab-btn'));
            document.getElementById('modalDetalle').style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('modalDetalle').style.display = 'none';
        }

        function verPestana(idTab, btn) {
            document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
            document.querySelectorAll('.modal-tabs .tab-btn').forEach(function(el) { el.classList.remove('active'); });
            document.getElementById(idTab).classList.add('active');
            if (btn) btn.classList.add('active');
        }
    </script>
</body>
</html>
HTML;

    return $html;
}

/**
 * --------------------------------------------------------------------------
 * 4. SERVICIO DE ENVÍO DE CORREO (SMTP + FALLBACK mail() + COPIA LOCAL)
 * Siguiendo el estándar de MailerService de Casa Lucho (Martin Castro)
 * --------------------------------------------------------------------------
 */
function enviarCorreoCatalogo($destinatario, $asunto, $cuerpo_html, $ruta_archivo_adjunto) {
    // Configuración desde .env (según especificación oficial de Martin Castro)
    $driver      = defined('MAIL_DRIVER') ? MAIL_DRIVER : 'smtp';
    $host        = defined('MAIL_HOST') ? MAIL_HOST : 'localhost';
    $port        = defined('MAIL_PORT') ? (int)MAIL_PORT : 587;
    $user        = defined('MAIL_USERNAME') ? MAIL_USERNAME : 'no-reply';
    $pass        = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
    $encryption  = defined('MAIL_ENCRYPTION') ? strtolower(MAIL_ENCRYPTION) : 'tls';
    $fromEmail   = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'no-reply@procesosimple.com';
    $fromName    = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Casa Lucho Autoherrajes';

    $nombre_adjunto = basename($ruta_archivo_adjunto);
    $contenido_adjunto = file_exists($ruta_archivo_adjunto) ? file_get_contents($ruta_archivo_adjunto) : '';

    $asuntoCodificado = sprintf('=?UTF-8?B?%s?=', base64_encode($asunto));
    $fromCodificado = sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($fromName), $fromEmail);

    // Construcción del mensaje MIME Multipart/Mixed (HTML + Adjunto .html)
    $boundary = "==Multipart_Boundary_x" . md5(uniqid((string)microtime(true), true)) . "x";

    $headers = [
        'MIME-Version' => '1.0',
        'From'         => $fromCodificado,
        'Reply-To'     => $fromEmail,
        'X-Mailer'     => 'CasaLucho/1.0 PHP/' . PHP_VERSION,
        'Content-Type' => 'multipart/mixed; boundary="' . $boundary . '"'
    ];

    $headersStr = '';
    foreach ($headers as $k => $v) {
        $headersStr .= "{$k}: {$v}\r\n";
    }

    $cuerpoMultipart = "--{$boundary}\r\n";
    $cuerpoMultipart .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cuerpoMultipart .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $cuerpoMultipart .= $cuerpo_html . "\r\n\r\n";

    if (!empty($contenido_adjunto)) {
        $cuerpoMultipart .= "--{$boundary}\r\n";
        $cuerpoMultipart .= "Content-Type: text/html; name=\"{$nombre_adjunto}\"\r\n";
        $cuerpoMultipart .= "Content-Disposition: attachment; filename=\"{$nombre_adjunto}\"\r\n";
        $cuerpoMultipart .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $cuerpoMultipart .= chunk_split(base64_encode($contenido_adjunto)) . "\r\n\r\n";
    }
    $cuerpoMultipart .= "--{$boundary}--";

    $logs = [];
    $enviado = false;
    $metodo_usado = '';

    // Intento 1: Conexión SMTP por socket con TLS y autenticación (según MailerService)
    if ($driver === 'smtp' && $host !== '') {
        try {
            $remoteSocket = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $socket = @stream_socket_client($remoteSocket, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
            if ($socket) {
                $read = static fn () => fgets($socket, 512);
                $send = static function (string $cmd) use ($socket) {
                    fputs($socket, $cmd . "\r\n");
                };

                $read();
                $send('EHLO ' . gethostname());
                $ehloResp = '';
                while ($line = $read()) {
                    $ehloResp .= $line;
                    if (substr($line, 3, 1) === ' ') break;
                }

                if ($encryption === 'tls' && str_contains($ehloResp, 'STARTTLS')) {
                    $send('STARTTLS');
                    $read();
                    @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    $send('EHLO ' . gethostname());
                    while ($line = $read()) {
                        if (substr($line, 3, 1) === ' ') break;
                    }
                }

                if ($user !== '' && $pass !== '') {
                    $send('AUTH LOGIN');
                    $read();
                    $send(base64_encode($user));
                    $read();
                    $send(base64_encode($pass));
                    $authRes = $read();
                    if (!str_starts_with((string)$authRes, '235')) {
                        fclose($socket);
                        throw new \RuntimeException('Autenticación SMTP fallida: ' . trim($authRes));
                    }
                }

                $send("MAIL FROM:<$fromEmail>");
                $read();
                $send("RCPT TO:<$destinatario>");
                $read();
                $send('DATA');
                $read();

                $mensajeCompleto  = $headersStr;
                $mensajeCompleto .= "To: <$destinatario>\r\n";
                $mensajeCompleto .= "Subject: $asuntoCodificado\r\n\r\n";
                $mensajeCompleto .= $cuerpoMultipart . "\r\n.\r\n";

                $send($mensajeCompleto);
                $dataRes = $read();
                $send('QUIT');
                fclose($socket);

                if (str_starts_with((string)$dataRes, '250')) {
                    $enviado = true;
                    $metodo_usado = "SMTP ({$host}:{$port})";
                }
            } else {
                $logs[] = "No se pudo conectar a SMTP ($remoteSocket): $errstr ($errno)";
            }
        } catch (\Throwable $e) {
            $logs[] = "Excepción SMTP: " . $e->getMessage();
        }
    }

    // Intento 2: Función nativa mail() pura de PHP
    if (!$enviado) {
        if ($host !== '') ini_set('SMTP', $host);
        if ($port > 0) ini_set('smtp_port', (string)$port);
        ini_set('sendmail_from', $fromEmail);

        try {
            $ok = @mail($destinatario, $asuntoCodificado, $cuerpoMultipart, $headersStr);
            if ($ok) {
                $enviado = true;
                $metodo_usado = "mail() nativo de PHP";
            } else {
                $err = error_get_last();
                $logs[] = "mail() nativo retornó false: " . ($err['message'] ?? 'sin detalle');
            }
        } catch (\Throwable $e) {
            $logs[] = "Excepción en mail(): " . $e->getMessage();
        }
    }

    // Intento 3: Copia local de respaldo en storage/logs (idéntico a guardarCopiaLocal de MailerService)
    $dirLogs = dirname(__DIR__, 2) . '/storage/logs';
    if (!is_dir($dirLogs)) {
        @mkdir($dirLogs, 0755, true);
    }
    $infoCopia = sprintf(
        "<!-- PARA: %s | ASUNTO: %s | FECHA: %s | METODO: %s -->\n%s",
        htmlspecialchars($destinatario),
        htmlspecialchars($asunto),
        date('Y-m-d H:i:s'),
        $metodo_usado ?: 'Copia local de respaldo',
        $cuerpo_html
    );
    @file_put_contents($dirLogs . '/last_email_catalogo.html', $infoCopia);

    return [
        'success' => $enviado,
        'metodo' => $metodo_usado,
        'logs' => $logs
    ];
}

/**
 * --------------------------------------------------------------------------
 * 5. PROCESAMIENTO PRINCIPAL Y EJECUCIÓN
 * --------------------------------------------------------------------------
 */

// Extraer catálogo
$datos = extraerDatosCatalogoProductos($conn, $empresa_id);

// Generar HTML seguro (sin alertar a McAfee ni antivirus)
$html_catalogo = generarHtmlOffline($datos);

// Guardar archivo HTML en el servidor (en modules/gestion_normal/exports/)
$fecha_archivo = date('Ymd_His');
$nombre_archivo_export = "productos_offline_{$fecha_archivo}.html";
$ruta_archivo_export = $directorio_exports . '/' . $nombre_archivo_export;
$ruta_archivo_latest = $directorio_exports . '/productos_offline_latest.html';

file_put_contents($ruta_archivo_export, $html_catalogo);
file_put_contents($ruta_archivo_latest, $html_catalogo);

// Limpiar archivos antiguos: conservar últimos 10 archivos y borrar los mayores a 10 días
depurarArchivosExports($directorio_exports, 10, 10);

$tamanio_mb = round(filesize($ruta_archivo_export) / (1024 * 1024), 2);

// Enviar correo si fue solicitado
$resultado_envio = null;
$error_envio = null;
$metodo_envio = null;

if ($enviar_email) {
    $asunto = "Catálogo de Productos Offline - " . ($datos['empresa']['empresa'] ?? 'Casa Lucho') . " [" . date('d/m/Y H:i') . "]";

    $cuerpo_email = "
    <div style='font-family: Arial, sans-serif; max-width: 650px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
        <div style='background: #009aa0; color: #ffffff; padding: 15px; border-radius: 6px; text-align: center;'>
            <h2 style='margin: 0;'>Catálogo de Productos para Consulta Offline</h2>
            <p style='margin: 5px 0 0 0; color: #e6fffa;'>Casa Lucho Autoherrajes - Sistema de Gestión</p>
        </div>
        <div style='padding: 20px 10px; color: #334155; line-height: 1.6;'>
            <p>Hola <strong>Pablo</strong>,</p>
            <p>Se ha generado exitosamente el archivo HTML autocontenido con el catálogo completo de productos para consulta <strong>100% offline</strong>.</p>
            <div style='background: #f8fafc; padding: 15px; border-left: 4px solid #009aa0; border-radius: 4px; margin: 15px 0;'>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li><strong>Total de productos:</strong> " . number_format($datos['total_productos'], 0, ',', '.') . "</li>
                    <li><strong>Listas de precios incluidas:</strong> " . implode(', ', $datos['listas_precios']) . " (Precio Neto y con IVA)</li>
                    <li><strong>Información incluida:</strong> Códigos, nombres, descripciones, compatibilidades vehiculares, ubicaciones en depósito, proveedores, costos y stock disponible.</li>
                    <li><strong>Tamaño del archivo adjunto:</strong> {$tamanio_mb} MB</li>
                    <li><strong>Fecha y hora de generación:</strong> {$datos['fecha_generacion']}</li>
                </ul>
            </div>
            <h3 style='color: #0f172a; margin-top: 20px;'>¿Cómo usar el archivo adjunto?</h3>
            <ol style='padding-left: 20px;'>
                <li>Descargue en su computadora, tablet o teléfono el archivo adjunto <code>{$nombre_archivo_export}</code>.</li>
                <li>Haga doble clic para abrirlo con cualquier navegador web (Google Chrome, Edge, Safari, Firefox).</li>
                <li><strong>No requiere internet ni conexión al servidor:</strong> el archivo contiene el motor de búsqueda instantáneo, filtros de marcas y modelos, y visualizador de detalles.</li>
            </ol>
        </div>
        <div style='text-align: center; color: #94a3b8; font-size: 12px; border-top: 1px solid #e2e8f0; padding-top: 15px;'>
            Casa Lucho Autoherrajes &middot; Venta mayorista y minorista &middot; Sistema Declarativo Multiempresa
        </div>
    </div>";

    $res_mail = enviarCorreoCatalogo($destinatario, $asunto, $cuerpo_email, $ruta_archivo_export);
    if ($res_mail['success']) {
        $resultado_envio = true;
        $metodo_envio = $res_mail['metodo'];
    } else {
        $resultado_envio = false;
        $error_envio = implode(' | ', $res_mail['logs']);
    }
}

/**
 * --------------------------------------------------------------------------
 * 6. SALIDA / RESPUESTA (CLI O INTERFAZ WEB)
 * --------------------------------------------------------------------------
 */
if ($es_cli) {
    echo "========================================================\n";
    echo "  EXPORTADOR DE PRODUCTOS OFFLINE - CASA LUCHO\n";
    echo "  Ubicación: modules/gestion_normal/exportar_productos_offline.php\n";
    echo "========================================================\n";
    echo "Total productos exportados: " . $datos['total_productos'] . "\n";
    echo "Archivo generado: $ruta_archivo_export ($tamanio_mb MB)\n";
    echo "Acceso directo: $ruta_archivo_latest\n";
    echo "Tiempo de proceso: {$datos['tiempo_segundos']} s\n";
    echo "Depuración exports: Últimos 10 archivos conservados (retención 10 días)\n";
    if ($enviar_email) {
        if ($resultado_envio) {
            echo "\n✓ CORREO ENVIADO CON ÉXITO vía {$metodo_envio} a {$destinatario}\n";
        } else {
            echo "\n✗ ERROR AL ENVIAR CORREO: {$error_envio}\n";
        }
    } else {
        echo "\nPara enviar por email, ejecute:\n";
        echo "  php modules/gestion_normal/exportar_productos_offline.php --enviar-email --to=$destinatario\n";
    }
    echo "========================================================\n";
    exit;
}

// URLs para Navegador Web
$url_descarga = "exportar_productos_offline.php?accion=descargar&archivo=" . urlencode($nombre_archivo_export);
$url_ver_online = "exports/productos_offline_latest.html";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Productos Offline - <?php echo htmlspecialchars($datos['empresa']['empresa'] ?? 'Casa Lucho'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .hero-card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-box { background: #f8fafc; border-radius: 8px; padding: 15px; border: 1px solid #e2e8f0; }
        .btn-action-lg { padding: 12px 20px; font-weight: 600; border-radius: 8px; }
    </style>
</head>
<body class="py-5">
    <div class="container" style="max-width: 850px;">
        <div class="card hero-card overflow-hidden">
            <div class="card-header bg-dark text-white p-4 text-center">
                <h3 class="mb-1"><i class="fas fa-boxes me-2 text-primary"></i>Generador de Catálogo Offline</h3>
                <p class="text-secondary mb-0"><?php echo htmlspecialchars($datos['empresa']['empresa'] ?? 'Casa Lucho'); ?> &bull; Módulo Gestión</p>
            </div>
            <div class="card-body p-4">
                
                <?php if ($enviar_email && $resultado_envio): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">¡Correo enviado con éxito!</h5>
                            <div>El catálogo de productos en formato HTML se envió correctamente vía <strong><?php echo htmlspecialchars($metodo_envio ?? 'SMTP / Mail'); ?></strong> a <strong><?php echo htmlspecialchars($destinatario); ?></strong> como archivo adjunto.</div>
                        </div>
                    </div>
                <?php elseif ($enviar_email && $error_envio): ?>
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Resultado del envío</h5>
                            <div><?php echo htmlspecialchars($error_envio); ?></div>
                            <div class="small mt-1 text-muted">Se guardó una copia de respaldo en <code>storage/logs/last_email_catalogo.html</code>. En el servidor de producción (con servicio SMTP o sendmail activo) el envío se completa automáticamente.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fas fa-info-circle fa-2x me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Catálogo HTML Generado Exitosamente</h5>
                            <div>El archivo HTML autocontenido está listo en <code>modules/gestion_normal/exports/</code> para ser consultado offline o enviado por correo.</div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Métricas -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="stat-box text-center">
                            <div class="text-muted small text-uppercase fw-bold">Productos Procesados</div>
                            <div class="fs-3 fw-bold text-primary"><?php echo number_format($datos['total_productos'], 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box text-center">
                            <div class="text-muted small text-uppercase fw-bold">Tamaño del Archivo</div>
                            <div class="fs-3 fw-bold text-success"><?php echo $tamanio_mb; ?> MB</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box text-center">
                            <div class="text-muted small text-uppercase fw-bold">Tiempo de Generación</div>
                            <div class="fs-3 fw-bold text-secondary"><?php echo $datos['tiempo_segundos']; ?> s</div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción Directa -->
                <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
                    <a href="<?php echo $url_ver_online; ?>" target="_blank" class="btn btn-primary btn-action-lg">
                        <i class="fas fa-external-link-alt me-2"></i>Abrir Catálogo Offline Ahora
                    </a>
                    <a href="<?php echo $url_descarga; ?>" class="btn btn-outline-success btn-action-lg">
                        <i class="fas fa-download me-2"></i>Descargar Archivo HTML Limpio
                    </a>
                    <a href="productos.php" class="btn btn-outline-secondary btn-action-lg">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Productos
                    </a>
                </div>

                <!-- Formulario de Envío por Email Puro -->
                <div class="card border bg-light">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2 text-primary"></i>Enviar Catálogo por Correo Electrónico (PHP mail() puro)</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="exportar_productos_offline.php">
                            <input type="hidden" name="accion" value="enviar_email">
                            <input type="hidden" name="empresa_id" value="<?php echo $empresa_id; ?>">

                            <div class="mb-3">
                                <label for="destinatario" class="form-label fw-bold">Destinatario:</label>
                                <input type="email" class="form-control" id="destinatario" name="destinatario" value="<?php echo htmlspecialchars($destinatario); ?>" required>
                                <div class="form-text">El archivo HTML será enviado como adjunto usando la función <code>mail()</code> nativa de PHP.</div>
                            </div>

                            <button type="submit" name="btn_enviar_email" class="btn btn-success w-100 py-2 fw-bold">
                                <i class="fas fa-paper-plane me-2"></i>Enviar Catálogo a <?php echo htmlspecialchars($destinatario); ?>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
            <div class="card-footer bg-light text-center text-muted small py-3">
                Archivo guardado en: <code><?php echo htmlspecialchars($ruta_archivo_export); ?></code>
                <div class="mt-1 text-muted" style="font-size: 11px;">
                    Estructura segura contra falsos positivos de antivirus &bull; Retención: Máximo 10 archivos generados / 10 días.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
