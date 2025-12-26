<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "paginas_funciones_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $funciones = obtenerPaginasFunciones($conexion);
        echo json_encode($funciones);
        break;
    
    case 'obtenerPaginas':
        $paginas = obtenerPaginas($conexion);
        echo json_encode($paginas);
        break;

    case 'obtenerIconos':
        $iconos = obtenerIconos($conexion);
        echo json_encode($iconos);
        break;

    case 'obtenerColores':
        $colores = obtenerColores($conexion);
        echo json_encode($colores);
        break;

    case 'obtenerFuncionesEstandar':
        $funcionesEstandar = obtenerFuncionesEstandar($conexion);
        echo json_encode($funcionesEstandar);
        break;

    case 'obtenerEstadosRegistro':
        $estados = obtenerEstadosRegistro($conexion);
        echo json_encode($estados);
        break;
    
    case 'agregar':
        $data = [
            'nombre_funcion' => $_GET['nombre_funcion'] ?? '',
            'pagina_id' => $_GET['pagina_id'] ?? '',
            'accion_js' => $_GET['accion_js'] ?? '',
            'descripcion' => $_GET['descripcion'] ?? '',
            'orden' => $_GET['orden'] ?? 0,
            'icono_id' => $_GET['icono_id'] ?? null,
            'color_id' => $_GET['color_id'] ?? 1,
            'funcion_estandar_id' => $_GET['funcion_estandar_id'] ?? null,
            'tabla_estado_registro_origen_id' => $_GET['tabla_estado_registro_origen_id'] ?? 0,
            'tabla_estado_registro_destino_id' => $_GET['tabla_estado_registro_destino_id'] ?? '',
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        // Validación de campos obligatorios
        if (empty($data['nombre_funcion']) || 
            empty($data['pagina_id']) ||
            empty($data['tabla_estado_registro_destino_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de función, página y estado destino son obligatorios']);
            break;
        }
        
        $resultado = agregarPaginaFuncion($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['pagina_funcion_id']);
        $data = [
            'nombre_funcion' => $_GET['nombre_funcion'] ?? '',
            'pagina_id' => $_GET['pagina_id'] ?? '',
            'accion_js' => $_GET['accion_js'] ?? '',
            'descripcion' => $_GET['descripcion'] ?? '',
            'orden' => $_GET['orden'] ?? 0,
            'icono_id' => $_GET['icono_id'] ?? null,
            'color_id' => $_GET['color_id'] ?? 1,
            'funcion_estandar_id' => $_GET['funcion_estandar_id'] ?? null,
            'tabla_estado_registro_origen_id' => $_GET['tabla_estado_registro_origen_id'] ?? 0,
            'tabla_estado_registro_destino_id' => $_GET['tabla_estado_registro_destino_id'] ?? '',
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        // Validación de campos obligatorios
        if (empty($data['nombre_funcion']) || 
            empty($data['pagina_id']) ||
            empty($data['tabla_estado_registro_destino_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de función, página y estado destino son obligatorios']);
            break;
        }
        
        $resultado = editarPaginaFuncion($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['pagina_funcion_id']);
        $resultado = eliminarPaginaFuncion($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['pagina_funcion_id']);
        $funcion = obtenerPaginaFuncionPorId($conexion, $id);
        echo json_encode($funcion);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>