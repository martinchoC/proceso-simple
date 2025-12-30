<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "conexion.php";
require_once "comprobantes_fiscales_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// ✅ Obtener información de la página
$pagina_info = obtenerPaginaPorUrl($conexion, 'comprobantes_fiscales.php');
$pagina_id = $pagina_info ? $pagina_info['pagina_id'] : 49;

try {
    switch ($accion) {
        case 'listar':
            $comprobantes_fiscales = obtenerComprobantesFiscales($conexion, $pagina_id);
            echo json_encode($comprobantes_fiscales, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'agregar':
            $data = [
                'codigo' => $_POST['codigo'] ?? 0,
                'comprobante_fiscal' => $_POST['comprobante_fiscal'] ?? ''
            ];
            
            if (empty($data['comprobante_fiscal']) || $data['codigo'] <= 0) {
                echo json_encode(['resultado' => false, 'error' => 'Código y nombre son obligatorios']);
                break;
            }
            
            $resultado = agregarComprobanteFiscal($conexion, $data);
            if (!$resultado) {
                echo json_encode(['resultado' => false, 'error' => 'Ya existe un comprobante fiscal con ese código o nombre']);
                break;
            }
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['comprobante_fiscal_id'] ?? 0);
            $data = [
                'codigo' => $_POST['codigo'] ?? 0,
                'comprobante_fiscal' => $_POST['comprobante_fiscal'] ?? ''
            ];
            
            if (empty($data['comprobante_fiscal']) || $data['codigo'] <= 0) {
                echo json_encode(['resultado' => false, 'error' => 'Código y nombre son obligatorios']);
                break;
            }
            
            $resultado = editarComprobanteFiscal($conexion, $id, $data);
            if (!$resultado) {
                echo json_encode(['resultado' => false, 'error' => 'Ya existe un comprobante fiscal con ese código o nombre']);
                break;
            }
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'cambiar_estado':
            $id = intval($_GET['comprobante_fiscal_id'] ?? $_POST['comprobante_fiscal_id'] ?? 0);
            $nuevo_estado = intval($_GET['nuevo_estado'] ?? $_POST['nuevo_estado'] ?? 0);
            $resultado = cambiarEstadoComprobanteFiscal($conexion, $id, $nuevo_estado);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $id = intval($_GET['comprobante_fiscal_id'] ?? $_POST['comprobante_fiscal_id'] ?? 0);
            $resultado = eliminarComprobanteFiscal($conexion, $id);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['comprobante_fiscal_id'] ?? $_POST['comprobante_fiscal_id'] ?? 0);
            $comprobante_fiscal = obtenerComprobanteFiscalPorId($conexion, $id);
            echo json_encode($comprobante_fiscal ?: [], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_funcion':
            $comprobante_fiscal_id = intval($_POST['comprobante_fiscal_id'] ?? 0);
            $funcion_nombre = $_POST['funcion_nombre'] ?? '';
            
            if (empty($comprobante_fiscal_id) || empty($funcion_nombre)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $comprobante_fiscal_id, $funcion_nombre, $pagina_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conexion);
?>