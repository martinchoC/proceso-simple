<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "listas_precios_productos_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// Red de seguridad: con mysqli_report en modo estricto (default desde PHP 8.1),
// cualquier error de MySQL no capturado se propaga como excepción fatal, que
// PHP imprime como HTML (con display_errors=1). Eso rompe el JSON que espera
// el front y produce el "Unexpected token '<'" en el navegador. Este try/catch
// asegura que, pase lo que pase, la respuesta siempre sea JSON válido.
try {

switch ($accion) {
    case 'listar':
        $filtro_lista = $_GET['filtro_lista'] ?? '';
        $filtro_producto = $_GET['filtro_producto'] ?? '';
        $filtro_marca = $_GET['filtro_marca'] ?? '';
        $filtro_modelo = $_GET['filtro_modelo'] ?? '';
        $filtro_submodelo = $_GET['filtro_submodelo'] ?? '';
        $precios = obtenerListasPreciosProductos($conexion, $filtro_lista, $filtro_producto, $filtro_marca, $filtro_modelo, $filtro_submodelo);
        echo json_encode($precios);
        break;
    
    case 'agregar':
        $data = [
            'lista_precio_id' => $_POST['lista_precio_id'] ?? 0,
            'producto_id' => $_POST['producto_id'] ?? 0,
            'precio_unitario' => $_POST['precio_unitario'] ?? 0,
            'ajuste_id' => $_POST['ajuste_id'] ?? null
        ];
        
        if (empty($data['lista_precio_id']) || empty($data['producto_id']) || $data['precio_unitario'] <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Lista, producto y precio son obligatorios']);
            break;
        }
        
        $resultado = agregarListaPrecioProducto($conexion, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe un precio para este producto en la lista seleccionada']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['lista_precio_producto_id']);
        $data = [
            'lista_precio_id' => $_POST['lista_precio_id'] ?? 0,
            'producto_id' => $_POST['producto_id'] ?? 0,
            'precio_unitario' => $_POST['precio_unitario'] ?? 0,
            'ajuste_id' => $_POST['ajuste_id'] ?? null
        ];
        
        if (empty($data['lista_precio_id']) || empty($data['producto_id']) || $data['precio_unitario'] <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Lista, producto y precio son obligatorios']);
            break;
        }
        
        $resultado = editarListaPrecioProducto($conexion, $id, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe un precio para este producto en la lista seleccionada']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['lista_precio_producto_id']);
        $resultado = eliminarListaPrecioProducto($conexion, $id);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar el precio del producto']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['lista_precio_producto_id']);
        $precio_producto = obtenerListaPrecioProductoPorId($conexion, $id);
        echo json_encode($precio_producto);
        break;

    case 'obtener_listas':
        $listas = obtenerListasPrecios($conexion);
        echo json_encode($listas);
        break;

    case 'obtener_productos':
        $productos = obtenerProductos($conexion);
        echo json_encode($productos);
        break;
        // listas_precios_productos_ajax.php
    // ... (código existente) ...

    // Necesitamos incluir una librería para leer archivos Excel. 
    // En este ejemplo, usaremos la extensión nativa de PHP para leer archivos Excel (si está disponible) o, mejor, 
    // asumiremos que el archivo se procesa del lado del cliente y se envía la data como JSON.
    // Para simplificar y mantener la coherencia con el resto del sistema, procesaremos el archivo del lado del servidor con PHPSpreadsheet o similar.
    // Sin embargo, como no se especificó, y para no agregar dependencias adicionales, 
    // asumiremos que el frontend leerá el archivo Excel y enviará los datos en formato JSON.
    // Por lo tanto, la acción 'importar' recibirá un array de productos (código, precio) en $_POST.

    case 'importar':
        // Verificar que se recibieron los datos
        $lista_precio_id = intval($_POST['lista_precio_id'] ?? 0);
        $productos_json = $_POST['productos'] ?? '';
        
        // Validar datos
        if (empty($lista_precio_id)) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar una lista de precios']);
            break;
        }
        
        if (empty($productos_json)) {
            echo json_encode(['success' => false, 'message' => 'No se recibieron productos para importar']);
            break;
        }
        
        // Decodificar JSON
        $productos = json_decode($productos_json, true);
        if (!is_array($productos) || count($productos) == 0) {
            echo json_encode(['success' => false, 'message' => 'El listado de productos está vacío o no es válido']);
            break;
        }
        
        // Llamar a la función del modelo
        $resultado = importarPreciosDesdeExcel($conexion, $lista_precio_id, $productos);
        echo json_encode($resultado);
        break;
        
    

    default:
        echo json_encode(['error' => 'Acción no definida']);
}

} catch (Throwable $e) {
    // Cualquier fatal de MySQL/PHP no capturado en el switch cae acá,
    // en vez de romper el JSON con una página de error HTML.
    http_response_code(500);
    echo json_encode([
        'resultado' => false,
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage(),
        'message' => 'Error del servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>