<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "conexion.php";
require_once "paginas_funciones_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $funciones = obtenerPaginasFunciones($conexion);
        echo json_encode($funciones);
        break;
    
    case 'agregar':
        $data = [
            'pagina_id' => $_GET['pagina_id'] ?? '',
            'tabla_id' => $_GET['tabla_id'] ?? null,
            'nombre_funcion' => $_GET['nombre_funcion'] ?? '',
            'descripcion' => $_GET['descripcion'] ?? '',
            'tabla_estado_registro_origen_id' => $_GET['tabla_estado_registro_origen_id'] ?? '',
            'tabla_estado_registro_destino_id' => $_GET['tabla_estado_registro_destino_id'] ?? '',
            'es_confirmable' => $_GET['es_confirmable'] ?? 1,
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
        $id = intval($_GET['pagina_funcion_id']);
        $data = [
            'pagina_id' => $_GET['pagina_id'] ?? '',
            'tabla_id' => $_GET['tabla_id'] ?? null,
            'nombre_funcion' => $_GET['nombre_funcion'] ?? '',
            'descripcion' => $_GET['descripcion'] ?? '',
            'tabla_estado_registro_origen_id' => $_GET['tabla_estado_registro_origen_id'] ?? '',
            'tabla_estado_registro_destino_id' => $_GET['tabla_estado_registro_destino_id'] ?? '',
            'es_confirmable' => $_GET['es_confirmable'] ?? 1,
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
        $id = intval($_GET['pagina_funcion_id']);
        $resultado = eliminarPaginaFuncion($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['pagina_funcion_id']);
        $funcion = obtenerPaginaFuncionPorId($conexion, $id);
        echo json_encode($funcion);
        break;
        
    case 'obtener_paginas':
        $paginas = obtenerPaginas($conexion);
        echo json_encode($paginas);
        break;
    
    case 'obtener_tabla_por_pagina':
        $pagina_id = intval($_GET['pagina_id']);
        $tabla_id = obtenerTablaPorPagina($conexion, $pagina_id);
        echo json_encode(['tabla_id' => $tabla_id]);
        break;
            
    case 'obtener_tablas':
        $tablas = obtenerTablas($conexion);
        echo json_encode($tablas);
        break;
        
    case 'obtener_estados_por_tabla':
        $tabla_id = intval($_GET['tabla_id']);
        $estados = obtenerEstadosPorTabla($conexion, $tabla_id);
        if (!$estados) {
            $estados = []; // Asegurar que siempre sea un array
        }
        echo json_encode($estados);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}

mysqli_close($conexion);