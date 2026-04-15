<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);  // mostrar errores en pantalla

require_once __DIR__ . '/../../db.php';
require_once "jurisdicciones_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 74);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $jurisdicciones = obtenerJurisdicciones($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($jurisdicciones, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_tipos_jurisdiccion':
            $tipos = obtenerTiposJurisdiccion($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_paises':
            $paises = obtenerPaises($conexion);
            echo json_encode($paises, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_provincias':
            $pais_id = intval($_GET['pais_id'] ?? 0);
            $provincias = obtenerProvincias($conexion, $pais_id);
            echo json_encode($provincias, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_localidades':
            $provincia_id = intval($_GET['provincia_id'] ?? 0);
            $localidades = obtenerLocalidades($conexion, $provincia_id);
            echo json_encode($localidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'jurisdiccion_codigo' => trim($_POST['jurisdiccion_codigo'] ?? ''),
                'jurisdiccion_nombre' => trim($_POST['jurisdiccion_nombre'] ?? ''),
                'pais_id' => !empty($_POST['pais_id']) ? intval($_POST['pais_id']) : null,
                'provincia_id' => !empty($_POST['provincia_id']) ? intval($_POST['provincia_id']) : null,
                'localidad_id' => !empty($_POST['localidad_id']) ? intval($_POST['localidad_id']) : null,
                'jurisdiccion_tipo_id' => intval($_POST['jurisdiccion_tipo_id'] ?? 0),
                'organismo_recaudador' => trim($_POST['organismo_recaudador'] ?? ''),
                'requiere_padron' => intval($_POST['requiere_padron'] ?? 0),
                'codigo_externo' => trim($_POST['codigo_externo'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = agregarJurisdiccion($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['jurisdiccion_id'] ?? 0);
            $data = [
                'jurisdiccion_codigo' => trim($_POST['jurisdiccion_codigo'] ?? ''),
                'jurisdiccion_nombre' => trim($_POST['jurisdiccion_nombre'] ?? ''),
                'pais_id' => !empty($_POST['pais_id']) ? intval($_POST['pais_id']) : null,
                'provincia_id' => !empty($_POST['provincia_id']) ? intval($_POST['provincia_id']) : null,
                'localidad_id' => !empty($_POST['localidad_id']) ? intval($_POST['localidad_id']) : null,
                'jurisdiccion_tipo_id' => intval($_POST['jurisdiccion_tipo_id'] ?? 0),
                'organismo_recaudador' => trim($_POST['organismo_recaudador'] ?? ''),
                'requiere_padron' => intval($_POST['requiere_padron'] ?? 0),
                'codigo_externo' => trim($_POST['codigo_externo'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = editarJurisdiccion($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $jurisdiccion_id = intval($_POST['jurisdiccion_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($jurisdiccion_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $jurisdiccion_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['jurisdiccion_id'] ?? $_GET['jurisdiccion_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $jurisdiccion = obtenerJurisdiccionPorId($conexion, $id);
            if ($jurisdiccion) {
                echo json_encode($jurisdiccion, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Jurisdicción no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>