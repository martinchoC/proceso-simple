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
    
    case 'agregar':
        $data = [
            'pagina' => $_GET['pagina'] ?? '',
            'url' => $_GET['url'] ?? '',
            'pagina_descripcion' => $_GET['pagina_descripcion'] ?? '',
            'orden' => $_GET['orden'] ?? '',
            'tabla_id' => $_GET['tabla_id'] ?? '',
            'icono_id' => $_GET['icono_id'] ?? '',
            'padre_id' => $_GET['padre_id'] ?? null, // Puede ser null
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_tabla_estado_registro_id' => $_GET['tabla_tabla_estado_registro_id'] ?? 1
        ];
        // Validación solo para campos obligatorios
        if (empty($data['pagina']) || empty($data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de página y módulo son obligatorios']);
            break;
        }
        $resultado = agregarpagina($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
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
            'padre_id' => $_GET['padre_id'] ?? null, // Puede ser null
            'tabla_tabla_estado_registro_id' => $_GET['tabla_tabla_estado_registro_id'] ?? 1
        ];
        $resultado = editarpagina($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
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
