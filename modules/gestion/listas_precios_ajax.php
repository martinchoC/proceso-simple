<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "listas_precios_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $listas_precios = obtenerListasPrecios($conexion);
        echo json_encode($listas_precios);
        break;
    
    case 'agregar':
        $data = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'empresa_id' => 2, // Usando el mismo empresa_id de la configuración
            'es_principal' => $_POST['es_principal'] ?? 0,
            'metodo_calculo' => $_POST['metodo_calculo'] ?? 'manual',
            'margen_ganancia' => $_POST['margen_ganancia'] ?? 0.00,
            'tipo' => $_POST['tipo'] ?? 'venta',
            'estado' => $_POST['estado'] ?? 'activa',
            'f_vigencia_desde' => $_POST['f_vigencia_desde'] ?? null,
            'f_vigencia_hasta' => $_POST['f_vigencia_hasta'] ?? null,
            'usuario_id_alta' => 1, // Usuario por defecto, puedes obtenerlo de la sesión
            'ip_origen' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];
        
        if (empty($data['nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        $resultado = agregarListaPrecios($conexion, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe una lista de precios con ese nombre']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['lista_id']);
        $data = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'es_principal' => $_POST['es_principal'] ?? 0,
            'metodo_calculo' => $_POST['metodo_calculo'] ?? 'manual',
            'margen_ganancia' => $_POST['margen_ganancia'] ?? 0.00,
            'tipo' => $_POST['tipo'] ?? 'venta',
            'estado' => $_POST['estado'] ?? 'activa',
            'f_vigencia_desde' => $_POST['f_vigencia_desde'] ?? null,
            'f_vigencia_hasta' => $_POST['f_vigencia_hasta'] ?? null,
            'usuario_id_modificacion' => 1, // Usuario por defecto
            'ip_origen' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];
        
        if (empty($data['nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        $resultado = editarListaPrecios($conexion, $id, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe una lista de precios con ese nombre']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['lista_id']);
        $resultado = eliminarListaPrecios($conexion, $id);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar la lista de precios porque está siendo usada']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['lista_id']);
        $lista_precios = obtenerListaPreciosPorId($conexion, $id);
        echo json_encode($lista_precios);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>