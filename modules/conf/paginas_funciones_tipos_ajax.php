<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "paginas_funciones_tipos_model.php";

// Crear instancia del modelo
$model = new PaginasFuncionesTiposModel($conexion);
$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $filtros = [
            'tabla_tipo_id' => $_GET['tabla_tipo_id'] ?? '',
            'estado_registro' => $_GET['estado_registro'] ?? ''
        ];
        
        $funciones = $model->obtenerFunciones($filtros);
        echo json_encode($funciones);
        break;
    
    case 'obtener':
        $id = intval($_GET['pagina_funcion_id']);
        $funcion = $model->obtenerFuncionPorId($id);
        echo json_encode($funcion);
        break;
    
    case 'agregar':
        $data = [
            'tabla_id' => intval($_POST['tabla_id']),
            'icono_id' => !empty($_POST['icono_id']) ? intval($_POST['icono_id']) : null,
            'color_id' => !empty($_POST['color_id']) ? intval($_POST['color_id']) : null,
            'funcion_estandar_id' => !empty($_POST['funcion_estandar_id']) ? intval($_POST['funcion_estandar_id']) : null,
            'nombre_funcion' => trim($_POST['nombre_funcion']),
            'accion_js' => trim($_POST['accion_js'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'tabla_estado_registro_origen_id' => intval($_POST['tabla_estado_registro_origen_id']),
            'tabla_estado_registro_destino_id' => intval($_POST['tabla_estado_registro_destino_id']),
            'orden' => intval($_POST['orden']),
            'tabla_estado_registro_id' => isset($_POST['estado_registro']) && $_POST['estado_registro'] ? 1 : 0
        ];
        
        // Validaciones
        if (empty($data['tabla_id']) || empty($data['nombre_funcion']) || 
            empty($data['tabla_estado_registro_origen_id']) || empty($data['tabla_estado_registro_destino_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Todos los campos obligatorios deben ser completados']);
            break;
        }
        
        $resultado = $model->agregarFuncion($data);
        echo json_encode(['resultado' => $resultado]);
        break;
    
    case 'editar':
        $id = intval($_POST['pagina_funcion_id']);
        $data = [
            'tabla_id' => intval($_POST['tabla_id']),
            'icono_id' => !empty($_POST['icono_id']) ? intval($_POST['icono_id']) : null,
            'color_id' => !empty($_POST['color_id']) ? intval($_POST['color_id']) : null,
            'funcion_estandar_id' => !empty($_POST['funcion_estandar_id']) ? intval($_POST['funcion_estandar_id']) : null,
            'nombre_funcion' => trim($_POST['nombre_funcion']),
            'accion_js' => trim($_POST['accion_js'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'tabla_estado_registro_origen_id' => intval($_POST['tabla_estado_registro_origen_id']),
            'tabla_estado_registro_destino_id' => intval($_POST['tabla_estado_registro_destino_id']),
            'orden' => intval($_POST['orden']),
            'tabla_estado_registro_id' => isset($_POST['estado_registro']) && $_POST['estado_registro'] ? 1 : 0
        ];
        
        // Validaciones
        if (empty($data['tabla_id']) || empty($data['nombre_funcion']) || 
            empty($data['tabla_estado_registro_origen_id']) || empty($data['tabla_estado_registro_destino_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Todos los campos obligatorios deben ser completados']);
            break;
        }
        
        $resultado = $model->editarFuncion($id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;
    
    case 'cambiar_estado':
        $id = intval($_GET['pagina_funcion_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = $model->cambiarEstadoFuncion($id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;
    
    case 'eliminar':
        $id = intval($_GET['pagina_funcion_id']);
        $resultado = $model->eliminarFuncion($id);
        echo json_encode(['resultado' => $resultado]);
        break;
    
    case 'obtener_tipos_tabla':
        $tipos = $model->obtenerTiposTabla();
        echo json_encode($tipos);
        break;
    
    case 'obtener_estados':
        $estados = $model->obtenerEstadosRegistro();
        echo json_encode($estados);
        break;
    
    case 'obtener_iconos':
        $iconos = $model->obtenerIconos();
        echo json_encode($iconos);
        break;
    
    case 'obtener_colores':
        $colores = $model->obtenerColores();
        echo json_encode($colores);
        break;
    
    case 'obtener_funciones_estandar':
        $tipo_id = isset($_GET['tipo_id']) ? intval($_GET['tipo_id']) : 0;
        
        if ($tipo_id > 0) {
            $funciones = $model->obtenerFuncionesEstandarPorTipo($tipo_id);
        } else {
            $funciones = $model->obtenerFuncionesEstandar();
        }
        
        echo json_encode($funciones);
        break;
    
    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>