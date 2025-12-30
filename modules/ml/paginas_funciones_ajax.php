<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Para desarrollo, puedes mostrar errores como JSON
function json_error($message) {
    echo json_encode(['error' => $message]);
    exit;
}

require_once "conexion.php";
require_once "paginas_funciones_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($accion) {
        case 'listar':
            $funciones = obtenerPaginasFunciones($conexion);
            echo json_encode($funciones);
            break;
        
        case 'agregar':
            $data = [
                'pagina_id' => $_GET['pagina_id'] ?? '',
                'tabla_id' => $_GET['tabla_id'] ?? null,
                'icono_id' => $_GET['icono_id'] ?? null,
                'color_id' => $_GET['color_id'] ?? null,
                'nombre_funcion' => $_GET['nombre_funcion'] ?? '',
                'accion_js' => $_GET['accion_js'] ?? null,
                'descripcion' => $_GET['descripcion'] ?? '',
                'tabla_estado_registro_origen_id' => $_GET['tabla_estado_registro_origen_id'] ?? '',
                'tabla_estado_registro_destino_id' => $_GET['tabla_estado_registro_destino_id'] ?? '',
                'orden' => $_GET['orden'] ?? 0
            ];
            
            if (empty($data['pagina_id']) || empty($data['nombre_funcion'])) {
                echo json_encode(['resultado' => false, 'error' => 'La página y el nombre de función son obligatorios']);
                break;
            }
            
            $resultado = agregarPaginaFuncion($conexion, $data);
            echo json_encode(['resultado' => $resultado]);
            break;

        case 'editar':
            $id = intval($_GET['pagina_funcion_id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['resultado' => false, 'error' => 'ID inválido']);
                break;
            }
            
            $data = [
                'pagina_id' => $_GET['pagina_id'] ?? '',
                'tabla_id' => $_GET['tabla_id'] ?? null,
                'icono_id' => $_GET['icono_id'] ?? null,
                'color_id' => $_GET['color_id'] ?? null,
                'nombre_funcion' => $_GET['nombre_funcion'] ?? '',
                'accion_js' => $_GET['accion_js'] ?? null,
                'descripcion' => $_GET['descripcion'] ?? '',
                'tabla_estado_registro_origen_id' => $_GET['tabla_estado_registro_origen_id'] ?? '',
                'tabla_estado_registro_destino_id' => $_GET['tabla_estado_registro_destino_id'] ?? '',
                'orden' => $_GET['orden'] ?? 0
            ];
            
            if (empty($data['pagina_id']) || empty($data['nombre_funcion'])) {
                echo json_encode(['resultado' => false, 'error' => 'La página y el nombre de función son obligatorios']);
                break;
            }
            
            $resultado = editarPaginaFuncion($conexion, $id, $data);
            echo json_encode(['resultado' => $resultado]);
            break;

        case 'eliminar':
            $id = intval($_GET['pagina_funcion_id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['resultado' => false, 'error' => 'ID inválido']);
                break;
            }
            
            $resultado = eliminarPaginaFuncion($conexion, $id);
            echo json_encode(['resultado' => $resultado]);
            break;

        case 'obtener':
            $id = intval($_GET['pagina_funcion_id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['error' => 'ID inválido']);
                break;
            }
            
            $funcion = obtenerPaginaFuncionPorId($conexion, $id);
            if ($funcion === null) {
                echo json_encode(['error' => 'No se encontró la función']);
            } else {
                echo json_encode($funcion);
            }
            break;
            
        case 'obtener_paginas':
            $paginas = obtenerPaginas($conexion);
            echo json_encode($paginas);
            break;
        
        case 'obtener_iconos':
            $iconos = obtenerIconos($conexion);
            echo json_encode($iconos);
            break;
        
        case 'obtener_colores':
            $colores = obtenerColores($conexion);
            echo json_encode($colores);
            break;
        
        case 'obtener_tabla_por_pagina':
            $pagina_id = intval($_GET['pagina_id'] ?? 0);
            if ($pagina_id <= 0) {
                echo json_encode(['tabla_id' => null]);
                break;
            }
            
            $tabla_id = obtenerTablaPorPagina($conexion, $pagina_id);
            echo json_encode(['tabla_id' => $tabla_id]);
            break;
                
        case 'obtener_tablas':
            $tablas = obtenerTablas($conexion);
            echo json_encode($tablas);
            break;
            
        case 'obtener_estados_por_tabla':
            $tabla_id = intval($_GET['tabla_id'] ?? 0);
            if ($tabla_id <= 0) {
                echo json_encode([]);
                break;
            }
            
            $estados = obtenerEstadosPorTabla($conexion, $tabla_id);
            if (!$estados) {
                $estados = [];
            }
            echo json_encode($estados);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida']);
    }
} catch (Exception $e) {
    // En desarrollo, mostrar el error
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}

mysqli_close($conexion);
?>