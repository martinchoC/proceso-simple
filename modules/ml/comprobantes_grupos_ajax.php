<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "conexion.php";
require_once "comprobantes_grupos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// ✅ Obtener información de la página
$pagina_info = obtenerPaginaPorUrl($conexion, 'comprobantes_grupos.php');
$pagina_id = $pagina_info ? $pagina_info['pagina_id'] : 46;

try {
    switch ($accion) {
        case 'listar':
            $empresa_id = intval($_GET['empresa_id'] ?? 0);
            if ($empresa_id <= 0) {
                echo json_encode([]);
                break;
            }
            $comprobantes_grupos = obtenerComprobantesGrupos($conexion, $empresa_id, $pagina_id);
            echo json_encode($comprobantes_grupos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'agregar':
            $data = [
                'empresa_id' => $_POST['empresa_id'] ?? 0,
                'comprobante_grupo' => $_POST['comprobante_grupo'] ?? ''
            ];
            
            if (empty($data['comprobante_grupo']) || $data['empresa_id'] <= 0) {
                echo json_encode(['resultado' => false, 'error' => 'El nombre del grupo es obligatorio']);
                break;
            }
            
            $resultado = agregarComprobanteGrupo($conexion, $data);
            if (!$resultado) {
                echo json_encode(['resultado' => false, 'error' => 'Ya existe un grupo de comprobantes con ese nombre']);
                break;
            }
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['comprobante_grupo_id'] ?? 0);
            $data = [
                'empresa_id' => $_POST['empresa_id'] ?? 0,
                'comprobante_grupo' => $_POST['comprobante_grupo'] ?? ''
            ];
            
            if (empty($data['comprobante_grupo']) || $data['empresa_id'] <= 0) {
                echo json_encode(['resultado' => false, 'error' => 'El nombre del grupo es obligatorio']);
                break;
            }
            
            $resultado = editarComprobanteGrupo($conexion, $id, $data);
            if (!$resultado) {
                echo json_encode(['resultado' => false, 'error' => 'Ya existe un grupo de comprobantes con ese nombre']);
                break;
            }
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'cambiar_estado':
            $id = intval($_GET['comprobante_grupo_id'] ?? $_POST['comprobante_grupo_id'] ?? 0);
            $nuevo_estado = intval($_GET['nuevo_estado'] ?? $_POST['nuevo_estado'] ?? 0);
            $empresa_id = intval($_GET['empresa_id'] ?? $_POST['empresa_id'] ?? 0);
            $resultado = cambiarEstadoComprobanteGrupo($conexion, $id, $nuevo_estado, $empresa_id);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $id = intval($_GET['comprobante_grupo_id'] ?? $_POST['comprobante_grupo_id'] ?? 0);
            $empresa_id = intval($_GET['empresa_id'] ?? $_POST['empresa_id'] ?? 0);
            $resultado = eliminarComprobanteGrupo($conexion, $id, $empresa_id);
            if (!$resultado) {
                echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar el grupo porque tiene tipos de comprobantes asociados']);
                break;
            }
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['comprobante_grupo_id'] ?? $_POST['comprobante_grupo_id'] ?? 0);
            $empresa_id = intval($_GET['empresa_id'] ?? $_POST['empresa_id'] ?? 0);
            $comprobante_grupo = obtenerComprobanteGrupoPorId($conexion, $id, $empresa_id);
            echo json_encode($comprobante_grupo ?: [], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_funcion':
            $comprobante_grupo_id = intval($_POST['comprobante_grupo_id'] ?? 0);
            $funcion_nombre = $_POST['funcion_nombre'] ?? '';
            $empresa_id = intval($_POST['empresa_id'] ?? 0);
            
            if (empty($comprobante_grupo_id) || empty($funcion_nombre) || empty($empresa_id)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $comprobante_grupo_id, $funcion_nombre, $pagina_id);
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