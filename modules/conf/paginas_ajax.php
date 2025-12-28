<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "paginas_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $paginas = obtenerpaginas($conexion);
        echo json_encode($paginas);
        break;
    
    case 'obtener_Modulos':
        $Modulos = obtenerModulos($conexion);
        echo json_encode($Modulos);
        break;
    
    case 'obtenerTablas':
        $tablas = obtenerTablas($conexion);
        echo json_encode($tablas);
        break;

    case 'obtenerIconos':
        $iconos = obtenerIconos($conexion);
        echo json_encode($iconos);
        break;

    case 'obtenerPadre':
        $padres = obtenerPadre($conexion);
        echo json_encode($padres);
        break;
    
    // Nueva acción: Obtener tabla tipo por tabla_id
    case 'obtenerTablaTipo':
        $tabla_id = $_GET['tabla_id'] ?? null;
        if ($tabla_id) {
            $tabla_tipo_id = obtenerTablaTipoPorTablaId($conexion, $tabla_id);
            echo json_encode(['tabla_tipo_id' => $tabla_tipo_id]);
        } else {
            echo json_encode(['error' => 'Tabla ID no proporcionado']);
        }
        break;
    
    // Nueva acción: Verificar si página tiene funciones
    case 'verificarFunciones':
        $pagina_id = $_GET['pagina_id'] ?? null;
        if ($pagina_id) {
            $tiene_funciones = paginaTieneFunciones($conexion, $pagina_id);
            echo json_encode(['tiene_funciones' => $tiene_funciones]);
        } else {
            echo json_encode(['error' => 'Página ID no proporcionado']);
        }
        break;
    
    // Nueva acción: Obtener funciones por tipo (para mostrar en modal de copia)
    case 'obtenerFuncionesPorTipo':
        $tabla_tipo_id = $_GET['tabla_tipo_id'] ?? null;
        if ($tabla_tipo_id) {
            $funciones = obtenerFuncionesPorTipoTabla($conexion, $tabla_tipo_id);
            echo json_encode($funciones);
        } else {
            echo json_encode([]);
        }
        break;
    
    // Nueva acción: Obtener funciones de una página específica
    case 'obtenerFuncionesPorPagina':
        $pagina_id = $_GET['pagina_id'] ?? null;
        if ($pagina_id) {
            $funciones = obtenerFuncionesPorPagina($conexion, $pagina_id);
            echo json_encode($funciones);
        } else {
            echo json_encode(['error' => 'Página ID no proporcionado']);
        }
        break;
    
    // Nueva acción: Copiar funciones de tipo
    case 'copiarFunciones':
        $pagina_id = $_GET['pagina_id'] ?? null;
        $tabla_tipo_id = $_GET['tabla_tipo_id'] ?? null;
        
        if ($pagina_id && $tabla_tipo_id) {
            // Primero verificar si ya tiene funciones
            if (paginaTieneFunciones($conexion, $pagina_id)) {
                echo json_encode(['resultado' => false, 'error' => 'La página ya tiene funciones asignadas']);
            } else {
                $resultado = copiarFuncionesDeTipo($conexion, $pagina_id, $tabla_tipo_id);
                echo json_encode(['resultado' => $resultado]);
            }
        } else {
            echo json_encode(['resultado' => false, 'error' => 'Datos incompletos']);
        }
        break;
    
    case 'agregar':
        $data = [
            'pagina' => $_GET['pagina'] ?? '',
            'url' => $_GET['url'] ?? '',
            'pagina_descripcion' => $_GET['pagina_descripcion'] ?? '',
            'orden' => $_GET['orden'] ?? '',
            'tabla_id' => $_GET['tabla_id'] ?? '',
            'icono_id' => $_GET['icono_id'] ?? '',
            'padre_id' => $_GET['padre_id'] ?? null,
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        // Validación solo para campos obligatorios
        if (empty($data['pagina']) || empty($data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de página y módulo son obligatorios']);
            break;
        }
        
        $resultado = agregarpagina($conexion, $data);
        
        // Si se agregó exitosamente y tiene tabla_id, preguntar por copiar funciones
        if ($resultado && !empty($data['tabla_id'])) {
            $ultimo_id = mysqli_insert_id($conexion);
            $tabla_tipo_id = obtenerTablaTipoPorTablaId($conexion, $data['tabla_id']);
            
            echo json_encode([
                'resultado' => true,
                'pagina_id' => $ultimo_id,
                'tabla_tipo_id' => $tabla_tipo_id,
                'mensaje' => 'Página creada exitosamente'
            ]);
        } else {
            echo json_encode(['resultado' => $resultado]);
        }
        break;

    case 'editar':
        $id = intval($_GET['pagina_id']);
        $data = [
            'pagina' => $_GET['pagina'] ?? '',
            'url' => $_GET['url'] ?? '',
            'pagina_descripcion' => $_GET['pagina_descripcion'] ?? '',
            'orden' => $_GET['orden'] ?? '',
            'tabla_id' => $_GET['tabla_id'] ?? 'default',
            'icono_id' => $_GET['icono_id'] ?? 'default',
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'padre_id' => $_GET['padre_id'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        $resultado = editarpagina($conexion, $id, $data);
        
        // Si se editó exitosamente y cambió la tabla, verificar si se deben copiar funciones
        if ($resultado && !empty($data['tabla_id'])) {
            // Verificar si la página ya tiene funciones
            $tiene_funciones = paginaTieneFunciones($conexion, $id);
            if (!$tiene_funciones) {
                $tabla_tipo_id = obtenerTablaTipoPorTablaId($conexion, $data['tabla_id']);
                echo json_encode([
                    'resultado' => true,
                    'pagina_id' => $id,
                    'tabla_tipo_id' => $tabla_tipo_id,
                    'tiene_funciones' => false,
                    'mensaje' => 'Página actualizada exitosamente'
                ]);
            } else {
                echo json_encode(['resultado' => true]);
            }
        } else {
            echo json_encode(['resultado' => $resultado]);
        }
        break;

    case 'eliminar':
        $id = intval($_GET['pagina_id']);
        $resultado = eliminarpagina($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['pagina_id']);
        $pagina = obtenerpaginaPorId($conexion, $id);
        echo json_encode($pagina);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
