<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "tablas_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $tablas = obtenerTablas($conexion);
        echo json_encode($tablas);
        break;
    
    case 'obtener_Modulos':
        $Modulos = obtenerModulos($conexion);
        echo json_encode($Modulos);
        break;
    
    case 'obtener_Tipos_Tabla':
        $tiposTabla = obtenerTiposTabla($conexion);
        echo json_encode($tiposTabla);
        break;
    
    case 'agregar':
        $data = [
            'tabla_nombre' => $_GET['tabla_nombre'] ?? '',
            'tabla_descripcion' => $_GET['tabla_descripcion'] ?? '',
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_tipo_id' => $_GET['tabla_tipo_id'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['tabla_nombre']) || empty($data['modulo_id']) || empty($data['tabla_tipo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de tabla, módulo y tipo son obligatorios']);
            break;
        }
        
        $resultado = agregarTabla($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['tabla_id']);
        $data = [
            'tabla_nombre' => $_GET['tabla_nombre'] ?? '',
            'tabla_descripcion' => $_GET['tabla_descripcion'] ?? '',
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_tipo_id' => $_GET['tabla_tipo_id'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['tabla_nombre']) || empty($data['modulo_id']) || empty($data['tabla_tipo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de tabla, módulo y tipo son obligatorios']);
            break;
        }
        
        $resultado = editarTabla($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['tabla_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoTabla($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['tabla_id']);
        $tabla = obtenerTablaPorId($conexion, $id);
        echo json_encode($tabla);
        break;

    // NUEVOS CASOS PARA GESTIÓN DE ESTADOS
    case 'obtener_estados_patron':
        $tabla_tipo_id = intval($_GET['tabla_tipo_id']);
        $estados = obtenerEstadosPatronPorTipoTabla($conexion, $tabla_tipo_id);
        echo json_encode($estados);
        break;

    case 'verificar_tabla_estados':
        $tabla_id = intval($_GET['tabla_id']);
        $tieneEstados = verificarTablaTieneEstados($conexion, $tabla_id);
        echo json_encode(['tiene_estados' => $tieneEstados]);
        break;

    case 'obtener_tablas_sin_estados':
        $tablas = obtenerTablasSinEstadosConfigurados($conexion);
        echo json_encode($tablas);
        break;
    
    case 'agregar_estados_tabla':
    $tabla_id = intval($_GET['tabla_id']);
    $tabla_tipo_id = intval($_GET['tabla_tipo_id']);
    $agregar_todos = isset($_GET['agregar_todos']) ? intval($_GET['agregar_todos']) : 1;
    
    // Usar la nueva función
    $resultado = agregarEstadosTabla($conexion, $tabla_id, $tabla_tipo_id, $agregar_todos);
    echo json_encode($resultado);
    break;

    case 'obtener_estados_tabla':
    $tabla_id = intval($_GET['tabla_id']);
    $estados = obtenerEstadosTablaActual($conexion, $tabla_id);
    echo json_encode($estados);
    break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>
