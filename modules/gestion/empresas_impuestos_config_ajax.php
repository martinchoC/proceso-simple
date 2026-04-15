<?php
// Limpiar cualquier salida previa
ob_clean();

// Configurar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

// Forzar header JSON antes de cualquier posible error
header('Content-Type: application/json; charset=utf-8');

// Función para capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode(['error' => 'Error fatal: ' . $error['message'] . ' en ' . $error['file'] . ' línea ' . $error['line']]);
    }
});

require_once __DIR__ . '/../../db.php';
require_once "empresas_impuestos_config_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 73);

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $configuraciones = obtenerConfiguracionesImpuestos($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($configuraciones, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_tipos_impuesto':
            $tipos = obtenerTiposImpuesto($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_jurisdicciones':
            $jurisdicciones = obtenerJurisdiccionesParaSelect($conexion);
            echo json_encode($jurisdicciones, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_condiciones_fiscales':
            $condiciones = obtenerCondicionesFiscales($conexion);
            echo json_encode($condiciones, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_cuentas_contables':
            $cuentas = obtenerCuentasContables($conexion, $empresa_idx);
            echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'empresa_id' => intval($_POST['empresa_id'] ?? $empresa_idx),
                'impuesto_tipo_id' => intval($_POST['impuesto_tipo_id'] ?? 0),
                'jurisdiccion_id' => !empty($_POST['jurisdiccion_id']) ? intval($_POST['jurisdiccion_id']) : null,
                'condicion_fiscal_id' => !empty($_POST['condicion_fiscal_id']) ? intval($_POST['condicion_fiscal_id']) : null,
                'cont_cuenta_id' => !empty($_POST['cont_cuenta_id']) ? intval($_POST['cont_cuenta_id']) : null,
                'tipo_calculo' => trim($_POST['tipo_calculo'] ?? 'manual'),
                'base_calculo' => !empty($_POST['base_calculo']) ? trim($_POST['base_calculo']) : null,
                'alicuota' => floatval($_POST['alicuota'] ?? 0),
                'minimo_imponible' => floatval($_POST['minimo_imponible'] ?? 0),
                'monto_fijo' => floatval($_POST['monto_fijo'] ?? 0),
                'prioridad' => intval($_POST['prioridad'] ?? 1),
                'f_desde' => trim($_POST['f_desde'] ?? ''),
                'f_hasta' => !empty($_POST['f_hasta']) ? trim($_POST['f_hasta']) : null,
                'aplica_siempre' => intval($_POST['aplica_siempre'] ?? 1)
            ];

            $resultado = agregarConfiguracionImpuesto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['empresa_impuesto_config_id'] ?? 0);
            $data = [
                'empresa_id' => intval($_POST['empresa_id'] ?? $empresa_idx),
                'impuesto_tipo_id' => intval($_POST['impuesto_tipo_id'] ?? 0),
                'jurisdiccion_id' => !empty($_POST['jurisdiccion_id']) ? intval($_POST['jurisdiccion_id']) : null,
                'condicion_fiscal_id' => !empty($_POST['condicion_fiscal_id']) ? intval($_POST['condicion_fiscal_id']) : null,
                'cont_cuenta_id' => !empty($_POST['cont_cuenta_id']) ? intval($_POST['cont_cuenta_id']) : null,
                'tipo_calculo' => trim($_POST['tipo_calculo'] ?? 'manual'),
                'base_calculo' => !empty($_POST['base_calculo']) ? trim($_POST['base_calculo']) : null,
                'alicuota' => floatval($_POST['alicuota'] ?? 0),
                'minimo_imponible' => floatval($_POST['minimo_imponible'] ?? 0),
                'monto_fijo' => floatval($_POST['monto_fijo'] ?? 0),
                'prioridad' => intval($_POST['prioridad'] ?? 1),
                'f_desde' => trim($_POST['f_desde'] ?? ''),
                'f_hasta' => !empty($_POST['f_hasta']) ? trim($_POST['f_hasta']) : null,
                'aplica_siempre' => intval($_POST['aplica_siempre'] ?? 1)
            ];

            $resultado = editarConfiguracionImpuesto($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $empresa_impuesto_config_id = intval($_POST['empresa_impuesto_config_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($empresa_impuesto_config_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $empresa_impuesto_config_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['empresa_impuesto_config_id'] ?? $_GET['empresa_impuesto_config_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $configuracion = obtenerConfiguracionPorId($conexion, $id, $empresa_idx);
            if ($configuracion) {
                echo json_encode($configuracion, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Configuración no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>