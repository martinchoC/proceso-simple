<?php
declare(strict_types=1);
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 138]);

/**
 * stock_consulta_ajax.php — Stock y Movimientos (página 138).
 * Consulta, planilla de conteo y blanqueo de stock negativo. El blanqueo escribe Ajustes de Stock: exige las funciones
 * agregar (y confirmar, si se confirma) de la página stock_ajustes.php de este módulo.
 * Respuesta: {success: bool, data: ..., message: string}. Las grillas server-side devuelven además
 * draw/recordsTotal/recordsFiltered para DataTables y los totales de todo lo filtrado.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/stock_consulta_model.php';
require_once __DIR__ . '/stock_ajustes_model.php';   // agregarStockAjuste / ejecutarTransicionEstado (blanqueo)

const SC_MODULO_ID = 2;

header('Content-Type: application/json; charset=utf-8');
ob_start();

function scResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200, array $extra = []): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    echo json_encode(array_merge(['success' => $ok, 'data' => $data, 'message' => $mensaje], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

try {
    switch ($accion) {
        case 'obtener_filtros':
            scResponder(true, [
                'bocas' => sdbBocas($conexion, $empresa_id, $usuario_id),
                'categorias' => sdbCategorias($conexion, $empresa_id),
                'tipos' => sdbTiposMovimiento($conexion, $empresa_id),
            ]);
            break;

        case 'buscar_productos':
            scResponder(true, scBuscarProductos($conexion, $empresa_id, (string)($_GET['q'] ?? '')));
            break;

        case 'listar_stock':
            $r = sdbListarStock($conexion, $empresa_id, $usuario_id, array_merge($_GET, ['con_totales' => 1]));
            scResponder(true, $r['data'], '', 200, array_diff_key($r, ['data' => 1]));
            break;

        case 'compat_marcas':
            scResponder(true, scCompatMarcas($conexion, $empresa_id));
            break;

        case 'compat_modelos':
            scResponder(true, scCompatModelos($conexion, $empresa_id, (int)($_GET['marca_id'] ?? 0)));
            break;

        case 'compat_submodelos':
            scResponder(true, scCompatSubmodelos($conexion, $empresa_id, (int)($_GET['modelo_id'] ?? 0)));
            break;

        case 'conteo_listado':
            scResponder(true, scConteoListado($conexion, $empresa_id, $usuario_id, $_GET));
            break;

        case 'blanqueo_previa':
            $pag_aj = permisos_pagina_modulo($conn, SC_MODULO_ID, 0, 'stock_ajustes.php');
            scResponder(true, [
                'bocas' => scBlanqueoPrevia($conexion, $empresa_id, $usuario_id, $_GET),
                'puede_agregar' => permisos_es_superadmin() || permisos_puede($conexion, $pag_aj, 'agregar'),
                'puede_confirmar' => permisos_es_superadmin() || permisos_puede($conexion, $pag_aj, 'confirmar'),
                'decimales' => fmt_decimales_stock(),
            ]);
            break;

        case 'blanqueo_generar':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') scResponder(false, null, 'Método no permitido', 405);
            $pag_aj = permisos_pagina_modulo($conn, SC_MODULO_ID, 0, 'stock_ajustes.php');
            $confirmar = (string)($_POST['confirmar'] ?? '1') === '1';
            permisos_exigir_funcion($conexion, $pag_aj, 'agregar');
            if ($confirmar) permisos_exigir_funcion($conexion, $pag_aj, 'confirmar');
            $res = scBlanqueoGenerar($conexion, $empresa_id, $usuario_id, $_POST, $pag_aj, $confirmar);
            $con_error = count(array_filter($res, function ($r) { return !empty($r['error']); }));
            scResponder($con_error < count($res), $res, $con_error > 0 ? 'Algunas bocas no se pudieron ajustar' : 'Ajustes generados');
            break;

        case 'listar_movimientos':
            $r = sdbListarMovimientos($conexion, $empresa_id, $usuario_id, array_merge($_GET, ['con_totales' => 1]));
            scResponder(true, $r['data'], '', 200, array_diff_key($r, ['data' => 1]));
            break;

        default:
            scResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[stock_consulta_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    scResponder(false, null, 'Error de base de datos', 500);
} catch (InvalidArgumentException $e) {
    scResponder(false, null, $e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('[stock_consulta_ajax] ' . $accion . ': ' . $e->getMessage());
    scResponder(false, null, 'Error interno del servidor', 500);
}
