<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "marcas_model.php";

$accion = $_GET['accion'] ?? '';
$pagina_idx = 43; // ID fijo de la página de marcas

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 1;
        $marcas = obtenerMarcas($conexion, $empresa_id);
        echo json_encode($marcas);
        break;
    
    case 'agregar':
        $data = [
            'empresa_id' => isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 1,
            'marca_nombre' => trim($_GET['marca_nombre'] ?? ''),
            'tabla_estado_registro_id' => isset($_GET['tabla_estado_registro_id']) ? intval($_GET['tabla_estado_registro_id']) : 1
        ];
        
        // Validaciones
        if (empty($data['marca_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre de la marca es obligatorio']);
            break;
        }
        
        // Verificar si ya existe la marca para esta empresa
        if (existeMarca($conexion, $data['empresa_id'], $data['marca_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'Esta marca ya existe para la empresa']);
            break;
        }
        
        $resultado = agregarMarca($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['marca_id'] ?? 0);
        $data = [
            'empresa_id' => isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 1,
            'marca_nombre' => trim($_GET['marca_nombre'] ?? ''),
            'tabla_estado_registro_id' => isset($_GET['tabla_estado_registro_id']) ? intval($_GET['tabla_estado_registro_id']) : 1
        ];
        
        // Validaciones
        if (empty($data['marca_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre de la marca es obligatorio']);
            break;
        }
        
        if ($id == 0) {
            echo json_encode(['resultado' => false, 'error' => 'ID de marca inválido']);
            break;
        }
        
        // Verificar si ya existe otra marca con el mismo nombre (excluyendo la actual)
        if (existeMarcaEditando($conexion, $data['empresa_id'], $data['marca_nombre'], $id)) {
            echo json_encode(['resultado' => false, 'error' => 'Esta marca ya existe para la empresa']);
            break;
        }
        
        $resultado = editarMarca($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['marca_id'] ?? 0);
        $nuevo_estado = intval($_GET['nuevo_estado'] ?? 0);
        
        if ($id == 0) {
            echo json_encode(['resultado' => false, 'error' => 'ID de marca inválido']);
            break;
        }
        
        $resultado = cambiarEstadoMarca($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['marca_id'] ?? 0);
        
        if ($id == 0) {
            echo json_encode(null);
            break;
        }
        
        $marca = obtenerMarcaPorId($conexion, $id);
        echo json_encode($marca);
        break;
        
    case 'obtener_empresa':
        $empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 1;
        $empresa = obtenerEmpresaPorId($conexion, $empresa_id);
        echo json_encode($empresa);
        break;
        
    // NUEVAS ACCIONES PARA FUNCIONES DINÁMICAS
    case 'obtener_funciones_estado':
        $estado_registro_id = intval($_GET['estado_registro_id'] ?? 1);
        $funciones = obtenerFuncionesPagina($conexion, $pagina_idx, $estado_registro_id);
        echo json_encode($funciones);
        break;
        
    case 'obtener_todos_estados':
        $estados = obtenerEstadosRegistros($conexion);
        echo json_encode($estados);
        break;
        
    case 'ejecutar_accion':
        $marca_id = intval($_GET['marca_id'] ?? 0);
        $funcion_estandar_id = intval($_GET['funcion_estandar_id'] ?? 0);
        $nuevo_estado = isset($_GET['nuevo_estado']) ? intval($_GET['nuevo_estado']) : null;
        
        if ($marca_id == 0 || $funcion_estandar_id == 0) {
            echo json_encode(['resultado' => false, 'error' => 'Parámetros inválidos']);
            break;
        }
        
        $resultado = ejecutarAccionMarca($conexion, $marca_id, $funcion_estandar_id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
        break;
}
?>