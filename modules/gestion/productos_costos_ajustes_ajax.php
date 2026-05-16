<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

require_once __DIR__ . '/../../db.php';
require_once "productos_costos_ajustes_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 82);

header('Content-Type: application/json; charset=utf-8');

if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $ajustes = obtenerAjustes($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($ajustes, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_tipos_ajuste':
            $tipos = obtenerTiposAjuste($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_tipos_valor':
            $tipos = obtenerTiposValor($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_entidades':
            $entidades = obtenerEntidades($conexion, $empresa_idx);
            echo json_encode($entidades, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_listas_costo_proveedor':
            $listas = obtenerListasCostoProveedor($conexion, $empresa_idx);
            echo json_encode($listas, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'buscar_productos':
            $busqueda = $_GET['busqueda'] ?? '';
            $productos = buscarProductos($conexion, $empresa_idx, $busqueda);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'empresa_idx' => $empresa_idx,
                'ajuste_descripcion' => trim($_POST['ajuste_descripcion'] ?? ''),
                'producto_costo_ajuste_tipo_id' => intval($_POST['producto_costo_ajuste_tipo_id'] ?? 0),
                'producto_costo_ajuste_valor_tipo_id' => !empty($_POST['producto_costo_ajuste_valor_tipo_id']) ? intval($_POST['producto_costo_ajuste_valor_tipo_id']) : null,
                'valor_ajuste' => !empty($_POST['valor_ajuste']) ? floatval($_POST['valor_ajuste']) : null,
                'entidad_id' => !empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : null,
                'producto_id' => !empty($_POST['producto_id']) ? intval($_POST['producto_id']) : null,
                'proveedor_lista_costo_id' => !empty($_POST['proveedor_lista_costo_id']) ? intval($_POST['proveedor_lista_costo_id']) : null,
                'f_informado' => $_POST['f_informado'] ?? '',
                'f_vigencia_desde' => $_POST['f_vigencia_desde'] ?? '',
                'f_vigencia_hasta' => $_POST['f_vigencia_hasta'] ?? '',
                'requiere_aprobacion' => intval($_POST['requiere_aprobacion'] ?? 1),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'detalles' => $_POST['detalles'] ?? '[]'
            ];
            $resultado = agregarAjuste($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['producto_costo_ajuste_id'] ?? 0);
            $data = [
                'empresa_idx' => $empresa_idx,
                'ajuste_descripcion' => trim($_POST['ajuste_descripcion'] ?? ''),
                'producto_costo_ajuste_tipo_id' => intval($_POST['producto_costo_ajuste_tipo_id'] ?? 0),
                'producto_costo_ajuste_valor_tipo_id' => !empty($_POST['producto_costo_ajuste_valor_tipo_id']) ? intval($_POST['producto_costo_ajuste_valor_tipo_id']) : null,
                'valor_ajuste' => !empty($_POST['valor_ajuste']) ? floatval($_POST['valor_ajuste']) : null,
                'entidad_id' => !empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : null,
                'producto_id' => !empty($_POST['producto_id']) ? intval($_POST['producto_id']) : null,
                'proveedor_lista_costo_id' => !empty($_POST['proveedor_lista_costo_id']) ? intval($_POST['proveedor_lista_costo_id']) : null,
                'f_informado' => $_POST['f_informado'] ?? '',
                'f_vigencia_desde' => $_POST['f_vigencia_desde'] ?? '',
                'f_vigencia_hasta' => $_POST['f_vigencia_hasta'] ?? '',
                'requiere_aprobacion' => intval($_POST['requiere_aprobacion'] ?? 1),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'detalles' => $_POST['detalles'] ?? '[]'
            ];
            $resultado = editarAjuste($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $ajuste_id = intval($_POST['producto_costo_ajuste_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            if (empty($ajuste_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $resultado = ejecutarTransicionEstado($conexion, $ajuste_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['producto_costo_ajuste_id'] ?? $_GET['producto_costo_ajuste_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $ajuste = obtenerAjustePorId($conexion, $id);
            if ($ajuste) {
                echo json_encode($ajuste, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Ajuste no encontrado'], JSON_UNESCAPED_UNICODE);
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