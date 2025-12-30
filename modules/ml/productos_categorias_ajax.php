<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "productos_categorias_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $categorias = obtenerCategorias($conexion);
        echo json_encode($categorias);
        break;
    
    case 'listar_arbol':
        $arbol = obtenerEstructuraJerarquica($conexion);
        echo json_encode($arbol);
        break;
    
    case 'listar_categorias_padre':
        $excluir_id = $_GET['excluir_id'] ?? null;
        $categorias = obtenerCategoriasPadre($conexion, $excluir_id);
        echo json_encode($categorias);
        break;
    
    case 'listar_todas_categorias':
        $excluir_id = $_GET['excluir_id'] ?? null;
        $categorias = obtenerTodasCategorias($conexion, $excluir_id);
        echo json_encode($categorias);
        break;
    
    case 'agregar':
        $data = [
            'producto_categoria_nombre' => $_POST['producto_categoria_nombre'] ?? '',
            'producto_categoria_padre_id' => $_POST['producto_categoria_padre_id'] ?? null
        ];
        
        if (empty($data['producto_categoria_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre de la categoría es obligatorio']);
            break;
        }
        
        $resultado = agregarCategoria($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['producto_categoria_id']);
        $data = [
            'producto_categoria_nombre' => $_POST['producto_categoria_nombre'] ?? '',
            'producto_categoria_padre_id' => $_POST['producto_categoria_padre_id'] ?? null
        ];
        
        if (empty($data['producto_categoria_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre de la categoría es obligatorio']);
            break;
        }
        
        $resultado = editarCategoria($conexion, $id, $data);
        if ($resultado === false) {
            echo json_encode(['resultado' => false, 'error' => 'No se puede asignar una categoría como su propio padre']);
        } else {
            echo json_encode(['resultado' => $resultado]);
        }
        break;

    case 'eliminar':
        $id = intval($_GET['producto_categoria_id']);
        $resultado = eliminarCategoria($conexion, $id);
        
        if ($resultado === false) {
            echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar la categoría porque tiene subcategorías asociadas']);
        } else {
            echo json_encode(['resultado' => $resultado]);
        }
        break;

    case 'obtener':
        $id = intval($_GET['producto_categoria_id']);
        $categoria = obtenerCategoriaPorId($conexion, $id);
        echo json_encode($categoria);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}