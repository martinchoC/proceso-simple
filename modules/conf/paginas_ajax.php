<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "paginas_model.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$params = array_merge($_GET, $_POST);

switch ($accion) {
    case 'listar':
        $modulo_id = isset($params['modulo_id']) && $params['modulo_id'] !== '' ? intval($params['modulo_id']) : null;
        $paginas = obtenerpaginas($conexion, $modulo_id);
        echo json_encode($paginas, JSON_UNESCAPED_UNICODE);
        break;
    
    case 'obtener_Modulos':
        $Modulos = obtenerModulos($conexion);
        echo json_encode($Modulos, JSON_UNESCAPED_UNICODE);
        break;
    
    case 'obtenerTablas':
        $tablas = obtenerTablas($conexion);
        echo json_encode($tablas, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtenerIconos':
        $iconos = obtenerIconos($conexion);
        echo json_encode($iconos, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtenerPadre':
        $modulo_id = isset($params['modulo_id']) && $params['modulo_id'] !== '' ? intval($params['modulo_id']) : null;
        $padres = obtenerPadre($conexion, $modulo_id);
        echo json_encode($padres, JSON_UNESCAPED_UNICODE);
        break;
    
    case 'obtenerTablaTipo':
        $tabla_id = isset($params['tabla_id']) && is_numeric($params['tabla_id']) ? intval($params['tabla_id']) : null;
        if ($tabla_id) {
            $tabla_tipo_id = obtenerTablaTipoPorTablaId($conexion, $tabla_id);
            echo json_encode(['tabla_tipo_id' => $tabla_tipo_id]);
        } else {
            echo json_encode(['error' => 'Tabla ID no proporcionado']);
        }
        break;
    
    case 'verificarFunciones':
        $pagina_id = isset($params['pagina_id']) && is_numeric($params['pagina_id']) ? intval($params['pagina_id']) : null;
        if ($pagina_id) {
            $tiene_funciones = paginaTieneFunciones($conexion, $pagina_id);
            echo json_encode(['tiene_funciones' => $tiene_funciones]);
        } else {
            echo json_encode(['error' => 'Página ID no proporcionado']);
        }
        break;
    
    case 'obtenerFuncionesPorTipo':
        $tabla_tipo_id = isset($params['tabla_tipo_id']) && is_numeric($params['tabla_tipo_id']) ? intval($params['tabla_tipo_id']) : null;
        if ($tabla_tipo_id) {
            $funciones = obtenerFuncionesPorTipoTabla($conexion, $tabla_tipo_id);
            echo json_encode($funciones, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([]);
        }
        break;
    
    case 'obtenerFuncionesPorPagina':
        $pagina_id = isset($params['pagina_id']) && is_numeric($params['pagina_id']) ? intval($params['pagina_id']) : null;
        if ($pagina_id) {
            $funciones = obtenerFuncionesPorPagina($conexion, $pagina_id);
            echo json_encode($funciones, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'Página ID no proporcionado']);
        }
        break;
    
    case 'copiarFunciones':
        $pagina_id = isset($params['pagina_id']) && is_numeric($params['pagina_id']) ? intval($params['pagina_id']) : null;
        $tabla_tipo_id = isset($params['tabla_tipo_id']) && is_numeric($params['tabla_tipo_id']) ? intval($params['tabla_tipo_id']) : null;
        
        if ($pagina_id && $tabla_tipo_id) {
            $resultado = copiarFuncionesDeTipo($conexion, $pagina_id, $tabla_tipo_id, false);
            echo json_encode([
                'resultado' => $resultado,
                'mensaje' => 'Funciones copiadas correctamente'
            ]);
        } else {
            echo json_encode([
                'resultado' => false, 
                'error' => 'Datos incompletos'
            ]);
        }
        break;
    
    case 'obtenerArbol':
        $modulo_id = isset($params['modulo_id']) && $params['modulo_id'] !== '' ? intval($params['modulo_id']) : null;
        $busqueda = $params['busqueda'] ?? null;
        $arbol = obtenerArbolPaginas($conexion, $modulo_id, $busqueda);
        echo json_encode($arbol, JSON_UNESCAPED_UNICODE);
        break;
    
    case 'actualizarOrden':
        $pagina_id = $params['pagina_id'] ?? null;
        $padre_id = $params['padre_id'] ?? null;
        $posicion = $params['posicion'] ?? null;
        
        if ($pagina_id !== null) {
            $resultado = actualizarOrdenPagina($conexion, $pagina_id, $padre_id, $posicion);
            echo json_encode(['resultado' => $resultado]);
        } else {
            echo json_encode(['resultado' => false, 'error' => 'Datos incompletos']);
        }
        break;
    
    case 'agregar':
        $data = [
            'pagina' => trim($params['pagina'] ?? ''),
            'url' => trim($params['url'] ?? ''),
            'pagina_descripcion' => trim($params['pagina_descripcion'] ?? ''),
            'orden' => isset($params['orden']) && is_numeric($params['orden']) ? intval($params['orden']) : 0,
            'tabla_id' => isset($params['tabla_id']) && is_numeric($params['tabla_id']) && intval($params['tabla_id']) > 0 ? intval($params['tabla_id']) : null,
            'icono_id' => isset($params['icono_id']) && is_numeric($params['icono_id']) ? intval($params['icono_id']) : 0,
            'padre_id' => isset($params['padre_id']) && is_numeric($params['padre_id']) ? intval($params['padre_id']) : 0,
            'modulo_id' => isset($params['modulo_id']) && is_numeric($params['modulo_id']) ? intval($params['modulo_id']) : null,
            'tabla_estado_registro_id' => isset($params['tabla_estado_registro_id']) && is_numeric($params['tabla_estado_registro_id']) ? intval($params['tabla_estado_registro_id']) : 1,
            'es_acceso_directo' => !empty($params['es_acceso_directo']) ? 1 : 0
        ];
        
        if (empty($data['pagina']) || empty($data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de página y módulo son obligatorios']);
            break;
        }
        
        $resultado = agregarpagina($conexion, $data);
        
        if ($resultado) {
            $ultimo_id = mysqli_insert_id($conexion);
            $tabla_tipo_id = !empty($data['tabla_id']) ? obtenerTablaTipoPorTablaId($conexion, $data['tabla_id']) : null;
            
            echo json_encode([
                'resultado' => true,
                'pagina_id' => $ultimo_id,
                'tabla_tipo_id' => $tabla_tipo_id,
                'tiene_funciones' => false,
                'mensaje' => 'Página creada exitosamente'
            ]);
        } else {
            echo json_encode(['resultado' => false, 'error' => 'Error al crear la página']);
        }
        break;

    case 'editar':
        $id = intval($params['pagina_id'] ?? 0);
        $data = [
            'pagina' => trim($params['pagina'] ?? ''),
            'url' => trim($params['url'] ?? ''),
            'pagina_descripcion' => trim($params['pagina_descripcion'] ?? ''),
            'orden' => isset($params['orden']) && is_numeric($params['orden']) ? intval($params['orden']) : 0,
            'tabla_id' => isset($params['tabla_id']) && is_numeric($params['tabla_id']) && intval($params['tabla_id']) > 0 ? intval($params['tabla_id']) : null,
            'icono_id' => isset($params['icono_id']) && is_numeric($params['icono_id']) ? intval($params['icono_id']) : 0,
            'padre_id' => isset($params['padre_id']) && is_numeric($params['padre_id']) ? intval($params['padre_id']) : 0,
            'modulo_id' => isset($params['modulo_id']) && is_numeric($params['modulo_id']) ? intval($params['modulo_id']) : null,
            'tabla_estado_registro_id' => isset($params['tabla_estado_registro_id']) && is_numeric($params['tabla_estado_registro_id']) ? intval($params['tabla_estado_registro_id']) : 1,
            'es_acceso_directo' => !empty($params['es_acceso_directo']) ? 1 : 0
        ];
        
        if ($id <= 0 || empty($data['pagina']) || empty($data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de página y módulo son obligatorios']);
            break;
        }
        
        $resultado = editarpagina($conexion, $id, $data);
        
        if ($resultado) {
            $tiene_funciones = paginaTieneFunciones($conexion, $id);
            $tabla_tipo_id = !empty($data['tabla_id']) ? obtenerTablaTipoPorTablaId($conexion, $data['tabla_id']) : null;
            echo json_encode([
                'resultado' => true,
                'pagina_id' => $id,
                'tabla_tipo_id' => $tabla_tipo_id,
                'tiene_funciones' => $tiene_funciones,
                'mensaje' => 'Página actualizada exitosamente'
            ]);
        } else {
            echo json_encode(['resultado' => false, 'error' => 'Error al actualizar la página']);
        }
        break;

    case 'eliminar':
        $id = intval($params['pagina_id'] ?? 0);
        $resultado = eliminarpagina($conexion, $id);
        echo json_encode(['resultado' => (bool)$resultado]);
        break;

    case 'obtener':
        $id = intval($params['pagina_id'] ?? 0);
        $pagina = obtenerpaginaPorId($conexion, $id);
        if ($pagina) {
            echo json_encode($pagina, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'Página no encontrada']);
        }
        break;
    
    case 'obtenerResultadoCopia':
        $resultado = $_SESSION['resultado_copia_funciones'] ?? null;
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida: ' . htmlspecialchars($accion)]);
}