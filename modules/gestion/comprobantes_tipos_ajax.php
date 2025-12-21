<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "conexion.php";
require_once "comprobantes_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = intval($_REQUEST['empresa_id'] ?? $_POST['empresa_id'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

// ✅ Obtener información de la página
$pagina_info = obtenerPaginaPorUrl($conexion, 'comprobantes_tipos.php');
$pagina_id = $pagina_info ? $pagina_info['pagina_id'] : 45;

try {
    switch ($accion) {
        case 'listar':
            if ($empresa_id <= 0) {
                echo json_encode([]);
                break;
            }
            $comprobantes_tipos = obtenerComprobantesTipos($conexion, $empresa_id, $pagina_id);
            echo json_encode($comprobantes_tipos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'listar_grupos':
            if ($empresa_id <= 0) {
                echo json_encode([]);
                break;
            }
            $grupos = obtenerComprobantesGruposActivos($conexion, $empresa_id);
            echo json_encode($grupos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'listar_fiscales':
            $fiscales = obtenerComprobantesFiscalesActivos($conexion);
            echo json_encode($fiscales, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'agregar':
            $data = [
                'comprobante_grupo_id' => $_POST['comprobante_grupo_id'] ?? 0,
                'comprobante_fiscal_id' => $_POST['comprobante_fiscal_id'] ?? 0,
                'impacta_stock' => $_POST['impacta_stock'] ?? 0,
                'impacta_contabilidad' => $_POST['impacta_contabilidad'] ?? 0,
                'impacta_ctacte' => $_POST['impacta_ctacte'] ?? 0,
                'comprobante_tipo' => $_POST['comprobante_tipo'] ?? '',
                'orden' => $_POST['orden'] ?? 0,
                'codigo' => $_POST['codigo'] ?? '',
                'letra' => $_POST['letra'] ?? '',
                'signo' => $_POST['signo'] ?? '+',
                'comentario' => $_POST['comentario'] ?? ''
            ];
            
            if (empty($data['comprobante_tipo']) || empty($data['codigo']) || 
                $data['comprobante_grupo_id'] <= 0) {
                echo json_encode(['resultado' => false, 'error' => 'Los campos nombre, código y grupo son obligatorios']);
                break;
            }
            
            $resultado = agregarComprobanteTipo($conexion, $data);
            if (!$resultado) {
                echo json_encode(['resultado' => false, 'error' => 'Ya existe un tipo de comprobante con ese nombre en el grupo seleccionado']);
                break;
            }
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['comprobante_tipo_id'] ?? 0);
            $data = [
                'comprobante_grupo_id' => $_POST['comprobante_grupo_id'] ?? 0,
                'comprobante_fiscal_id' => $_POST['comprobante_fiscal_id'] ?? 0,
                'impacta_stock' => $_POST['impacta_stock'] ?? 0,
                'impacta_contabilidad' => $_POST['impacta_contabilidad'] ?? 0,
                'impacta_ctacte' => $_POST['impacta_ctacte'] ?? 0,
                'orden' => $_POST['orden'] ?? 0,
                'comprobante_tipo' => $_POST['comprobante_tipo'] ?? '',
                'codigo' => $_POST['codigo'] ?? '',
                'letra' => $_POST['letra'] ?? '',
                'signo' => $_POST['signo'] ?? '+',
                'comentario' => $_POST['comentario'] ?? ''
            ];
            
            if (empty($data['comprobante_tipo']) || empty($data['codigo']) || 
                $data['comprobante_grupo_id'] <= 0) {
                echo json_encode(['resultado' => false, 'error' => 'Los campos nombre, código y grupo son obligatorios']);
                break;
            }
            
            $resultado = editarComprobanteTipo($conexion, $id, $data);
            if (!$resultado) {
                echo json_encode(['resultado' => false, 'error' => 'Ya existe un tipo de comprobante con ese nombre en el grupo seleccionado']);
                break;
            }
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'cambiar_estado':
            $id = intval($_GET['comprobante_tipo_id'] ?? $_POST['comprobante_tipo_id'] ?? 0);
            $nuevo_estado = intval($_GET['nuevo_estado'] ?? $_POST['nuevo_estado'] ?? 0);
            $resultado = cambiarEstadoComprobanteTipo($conexion, $id, $nuevo_estado);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $id = intval($_GET['comprobante_tipo_id'] ?? $_POST['comprobante_tipo_id'] ?? 0);
            $resultado = eliminarComprobanteTipo($conexion, $id);
            if (!$resultado) {
                echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar el tipo porque tiene comprobantes asociados']);
                break;
            }
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['comprobante_tipo_id'] ?? $_POST['comprobante_tipo_id'] ?? 0);
            $comprobante_tipo = obtenerComprobanteTipoPorId($conexion, $id);
            echo json_encode($comprobante_tipo ?: [], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_funcion':
            $comprobante_tipo_id = intval($_POST['comprobante_tipo_id'] ?? 0);
            $funcion_nombre = $_POST['funcion_nombre'] ?? '';
            
            if (empty($comprobante_tipo_id) || empty($funcion_nombre)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $comprobante_tipo_id, $funcion_nombre, $pagina_id);
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