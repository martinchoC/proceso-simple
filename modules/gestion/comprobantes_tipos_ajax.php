<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "comprobantes_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 45);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $filters = [
                'grupo' => $_GET['filter_grupo'] ?? '',
                'estado' => $_GET['filter_estado'] ?? '',
                'busqueda' => $_GET['filter_busqueda'] ?? ''
            ];
            
            $comprobantes_tipos = obtenerComprobantesTipos($conexion, $empresa_idx, $pagina_idx, $filters);
            echo json_encode($comprobantes_tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'obtener_grupos_activas':
            $grupos = obtenerGruposActivos($conexion, $empresa_idx);
            echo json_encode($grupos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_comprobantes_fiscales':
            $comprobantes = obtenerComprobantesFiscales($conexion);
            echo json_encode($comprobantes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_estados_registro':
            $estados = obtenerEstadosRegistro($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'comprobante_tipo' => trim($_POST['comprobante_tipo'] ?? ''),
                'comprobante_grupo_id' => intval($_POST['comprobante_grupo_id'] ?? 0),
                'comprobante_fiscal_id' => intval($_POST['comprobante_fiscal_id'] ?? 0),
                'letra' => trim($_POST['letra'] ?? ''),
                'signo' => $_POST['signo'] ?? '+',
                'orden' => intval($_POST['orden'] ?? 1),
                'impacta_stock' => intval($_POST['impacta_stock'] ?? 0),
                'impacta_contabilidad' => intval($_POST['impacta_contabilidad'] ?? 0),
                'impacta_ctacte' => intval($_POST['impacta_ctacte'] ?? 0),
                'comentario' => trim($_POST['comentario'] ?? ''),
                'estado_registro_id' => !empty($_POST['estado_registro_id']) ? intval($_POST['estado_registro_id']) : null,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];
            
            $resultado = agregarComprobanteTipo($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['comprobante_tipo_id'] ?? 0);
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'comprobante_tipo' => trim($_POST['comprobante_tipo'] ?? ''),
                'comprobante_grupo_id' => intval($_POST['comprobante_grupo_id'] ?? 0),
                'comprobante_fiscal_id' => intval($_POST['comprobante_fiscal_id'] ?? 0),
                'letra' => trim($_POST['letra'] ?? ''),
                'signo' => $_POST['signo'] ?? '+',
                'orden' => intval($_POST['orden'] ?? 1),
                'impacta_stock' => intval($_POST['impacta_stock'] ?? 0),
                'impacta_contabilidad' => intval($_POST['impacta_contabilidad'] ?? 0),
                'impacta_ctacte' => intval($_POST['impacta_ctacte'] ?? 0),
                'comentario' => trim($_POST['comentario'] ?? ''),
                'estado_registro_id' => !empty($_POST['estado_registro_id']) ? intval($_POST['estado_registro_id']) : null,
                'empresa_idx' => $empresa_idx
            ];
            
            $resultado = editarComprobanteTipo($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $comprobante_tipo_id = intval($_POST['comprobante_tipo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            
            if (empty($comprobante_tipo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $comprobante_tipo_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['comprobante_tipo_id'] ?? $_GET['comprobante_tipo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $comprobante_tipo = obtenerComprobanteTipoPorId($conexion, $id, $empresa_idx);
            if ($comprobante_tipo) {
                echo json_encode($comprobante_tipo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Tipo de comprobante no encontrado'], JSON_UNESCAPED_UNICODE);
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