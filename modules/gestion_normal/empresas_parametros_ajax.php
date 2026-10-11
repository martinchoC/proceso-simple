<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['empresas_parametros.php']]);
/**
 * Controlador AJAX para Parámetros Generales de Empresa
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/empresas_parametros_model.php';

$conexion = $conn;
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$accion = $_REQUEST['accion'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? null;

try {
    switch ($accion) {
        case 'obtener_catalogos':
            $empresa_id = intval($_GET['empresa_id'] ?? $ctx['empresa_id']);
            $catalogos = [
                'empresas' => obtenerEmpresasCatalogo($conexion),
                'condiciones_fiscales' => obtenerCondicionesFiscalesCatalogo($conexion),
                'iibb_regimenes' => obtenerIibbRegimenesCatalogo($conexion),
                'jurisdicciones_sedes' => obtenerJurisdiccionesSedesCatalogo($conexion),
                'puntos_venta' => obtenerPuntosVentaCatalogo($conexion, $empresa_id),
                'listas_precios' => obtenerListasPreciosCatalogo($conexion, $empresa_id),
                'condiciones_pago' => obtenerCondicionesPagoCatalogo($conexion, $empresa_id)
            ];
            echo json_encode($catalogos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_vigentes':
            $empresa_id = intval($_GET['empresa_id'] ?? 0);
            if (empty($empresa_id)) {
                echo json_encode(['error' => 'Empresa no especificada'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $vigente = obtenerParametrosVigentesEmpresa($conexion, $empresa_id);
            echo json_encode($vigente ?: ['sin_parametros' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_historial':
            $empresa_id = intval($_GET['empresa_id'] ?? 0);
            if (empty($empresa_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }
            $historial = obtenerHistorialParametrosEmpresa($conexion, $empresa_id);
            echo json_encode($historial, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_parametro':
            $id = intval($_GET['empresa_parametro_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no especificado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $param = obtenerParametroEmpresaPorId($conexion, $id);
            echo json_encode($param, JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $data = [
                'empresa_parametro_id' => intval($_POST['empresa_parametro_id'] ?? 0),
                'empresa_id' => intval($_POST['empresa_id'] ?? 0),
                'condicion_fiscal_id' => intval($_POST['condicion_fiscal_id'] ?? 0),
                'punto_venta_defecto_id' => !empty($_POST['punto_venta_defecto_id']) ? intval($_POST['punto_venta_defecto_id']) : null,
                'lista_precio_defecto_id' => !empty($_POST['lista_precio_defecto_id']) ? intval($_POST['lista_precio_defecto_id']) : null,
                'condicion_pago_defecto_id' => !empty($_POST['condicion_pago_defecto_id']) ? intval($_POST['condicion_pago_defecto_id']) : null,
                'ingresos_brutos_nro' => $_POST['ingresos_brutos_nro'] ?? null,
                'iibb_regimen_id' => !empty($_POST['iibb_regimen_id']) ? intval($_POST['iibb_regimen_id']) : 1,
                'jurisdiccion_sede_id' => !empty($_POST['jurisdiccion_sede_id']) ? intval($_POST['jurisdiccion_sede_id']) : 2,
                'inicio_actividades' => $_POST['inicio_actividades'] ?? null,
                'leyenda_fiscal' => $_POST['leyenda_fiscal'] ?? null,
                'arca_habilitado' => intval($_POST['arca_habilitado'] ?? 0),
                'compra_actualiza_lista_proveedor' => intval($_POST['compra_actualiza_lista_proveedor'] ?? 0),
                'compra_actualiza_listas_venta' => intval($_POST['compra_actualiza_listas_venta'] ?? 0),
                'f_desde' => $_POST['f_desde'] ?? '',
                'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null,
                'observaciones' => $_POST['observaciones'] ?? null,
                'cerrar_anterior' => isset($_POST['cerrar_anterior']) ? intval($_POST['cerrar_anterior']) : 0,
                'quitar_logo' => intval($_POST['quitar_logo'] ?? 0)
            ];
            // Logo para comprobantes impresos (multipart, campo "logo"): la empresa del registro
            // tiene que ser una del usuario, no solo la de la sesión.
            if (!empty($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                ajax_guard_exigir_empresa($conexion, intval($ctx['usuario_id']), $data['empresa_id']);
                $logo = guardarArchivoLogoEmpresa($_FILES['logo'], $data['empresa_id']);
                if (!empty($logo['error'])) {
                    echo json_encode(['resultado' => false, 'error' => $logo['error']], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $data['logo_archivo'] = $logo['ruta'];
            }
            $resultado = guardarParametrosEmpresa($conexion, $data, $usuario_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'cambiar_estado':
            $id = intval($_POST['empresa_parametro_id'] ?? 0);
            $nuevo_estado = intval($_POST['nuevo_estado'] ?? 1);
            $resultado = cambiarEstadoParametroEmpresa($conexion, $id, $nuevo_estado);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        // ---- Configuración de impresión (gestion__empresas_impresion) ----
        // La página permite elegir la empresa: se valida que sea una del usuario.
        case 'impresion_listar':
        case 'impresion_subgrupos':
        case 'impresion_guardar':
        case 'impresion_cambiar_estado':
            $empresa_imp = intval($_REQUEST['empresa_id'] ?? $ctx['empresa_id']);
            ajax_guard_exigir_empresa($conexion, intval($ctx['usuario_id']), $empresa_imp);
            if ($accion === 'impresion_listar') {
                echo json_encode(listarConfiguracionesImpresion($conexion, $empresa_imp), JSON_UNESCAPED_UNICODE);
            } elseif ($accion === 'impresion_subgrupos') {
                echo json_encode(obtenerSubgruposImpresion($conexion, $empresa_imp), JSON_UNESCAPED_UNICODE);
            } elseif ($accion === 'impresion_guardar') {
                echo json_encode(guardarConfiguracionImpresion($conexion, $empresa_imp, $_POST, $ctx['usuario_id']), JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(cambiarEstadoConfiguracionImpresion($conexion, $empresa_imp, intval($_POST['empresa_impresion_id'] ?? 0), intval($_POST['nuevo_estado'] ?? 1)), JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no reconocida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
