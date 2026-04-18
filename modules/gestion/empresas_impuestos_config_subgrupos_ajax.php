<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

require_once __DIR__ . '/../../db.php';
require_once "empresas_impuestos_config_subgrupos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

$empresa_id = intval($_GET['empresa_id'] ?? $_POST['empresa_id'] ?? 2);
$empresa_impuesto_config_id = intval($_GET['empresa_impuesto_config_id'] ?? $_POST['empresa_impuesto_config_id'] ?? 0);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 76);

header('Content-Type: application/json; charset=utf-8');

if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $data = obtenerConfiguracionesSubgrupo($conexion, $empresa_impuesto_config_id, $empresa_id, $pagina_idx);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_subgrupos_disponibles':
            $subgrupos = obtenerSubgruposDisponibles($conexion, $empresa_id, $empresa_impuesto_config_id);
            echo json_encode($subgrupos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_config_info':
            $info = obtenerConfiguracionInfo($conexion, $empresa_impuesto_config_id, $empresa_id);
            echo json_encode($info, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $resultado = agregarConfiguracionSubgrupo($conexion, [
                'empresa_impuesto_config_id' => intval($_POST['empresa_impuesto_config_id'] ?? 0),
                'comprobante_subgrupo_id' => intval($_POST['comprobante_subgrupo_id'] ?? 0)
            ]);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $resultado = editarConfiguracionSubgrupo($conexion, 
                intval($_POST['empresa_impuesto_config_subgrupo_id'] ?? 0),
                ['comprobante_subgrupo_id' => intval($_POST['comprobante_subgrupo_id'] ?? 0)]
            );
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['empresa_impuesto_config_subgrupo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            if (empty($id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }
            $resultado = ejecutarTransicionEstado($conexion, $id, $accion_js, $empresa_id, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['empresa_impuesto_config_subgrupo_id'] ?? $_GET['empresa_impuesto_config_subgrupo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado']);
                break;
            }
            $config = obtenerConfiguracionSubgrupoPorId($conexion, $id, $empresa_id);
            echo json_encode($config ?: ['error' => 'Configuración no encontrada'], JSON_UNESCAPED_UNICODE);
            break;
       
           case 'eliminar_operacion':
            $id = intval($_POST['empresa_impuesto_config_operacion_id'] ?? 0);
            
            // Agregar log para depuración
            error_log("Eliminar operación - ID recibido: " . $id);
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $resultado = eliminarOperacion($conexion, $id);
            
            // Agregar log del resultado
            error_log("Resultado eliminación: " . print_r($resultado, true));
            
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}

if (isset($conexion) && $conexion) mysqli_close($conexion);
?>