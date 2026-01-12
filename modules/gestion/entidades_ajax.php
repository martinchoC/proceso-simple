<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "entidades_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 50);
$pagina_idx_sucursales = intval($_GET['pagina_idx_sucursales'] ?? $_POST['pagina_idx_sucursales'] ?? 51);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar_entidades':
            $entidades = obtenerEntidades($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($entidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_sucursales':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $sucursales = obtenerSucursalesEntidad($conexion, $empresa_idx, $entidad_id, $pagina_idx_sucursales);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_tipos_entidad':
            $tipos = obtenerTiposEntidad($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_localidades':
            $localidades = obtenerLocalidades($conexion);
            echo json_encode($localidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_entidad':
            $data = [
                'empresa_id' => $empresa_idx,
                'entidad_nombre' => trim($_POST['entidad_nombre'] ?? ''),
                'entidad_fantasia' => trim($_POST['entidad_fantasia'] ?? ''),
                'entidad_tipo_id' => intval($_POST['entidad_tipo_id'] ?? 0),
                'cuit' => $_POST['cuit'] ? intval($_POST['cuit']) : null,
                'sitio_web' => trim($_POST['sitio_web'] ?? ''),
                'domicilio_legal' => trim($_POST['domicilio_legal'] ?? ''),
                'localidad_id' => $_POST['localidad_id'] ? intval($_POST['localidad_id']) : null,
                'es_proveedor' => isset($_POST['es_proveedor']) ? 1 : 0,
                'es_cliente' => isset($_POST['es_cliente']) ? 1 : 0,
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarEntidad($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_entidad':
            $id = intval($_POST['entidad_id'] ?? 0);
            $data = [
                'entidad_nombre' => trim($_POST['entidad_nombre'] ?? ''),
                'entidad_fantasia' => trim($_POST['entidad_fantasia'] ?? ''),
                'entidad_tipo_id' => intval($_POST['entidad_tipo_id'] ?? 0),
                'cuit' => $_POST['cuit'] ? intval($_POST['cuit']) : null,
                'sitio_web' => trim($_POST['sitio_web'] ?? ''),
                'domicilio_legal' => trim($_POST['domicilio_legal'] ?? ''),
                'localidad_id' => $_POST['localidad_id'] ? intval($_POST['localidad_id']) : null,
                'es_proveedor' => isset($_POST['es_proveedor']) ? 1 : 0,
                'es_cliente' => isset($_POST['es_cliente']) ? 1 : 0,
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarEntidad($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_sucursal':
            $data = [
                'empresa_id' => $empresa_idx,
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'sucursal_nombre' => trim($_POST['sucursal_nombre'] ?? ''),
                'sucursal_direccion' => trim($_POST['sucursal_direccion'] ?? ''),
                'localidad_id' => $_POST['localidad_id'] ? intval($_POST['localidad_id']) : null,
                'sucursal_telefono' => trim($_POST['sucursal_telefono'] ?? ''),
                'sucursal_email' => trim($_POST['sucursal_email'] ?? ''),
                'sucursal_contacto' => trim($_POST['sucursal_contacto'] ?? ''),
                'pagina_idx' => $pagina_idx_sucursales
            ];

            $resultado = agregarSucursal($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_sucursal':
            $id = intval($_POST['sucursal_id'] ?? 0);
            $data = [
                'sucursal_nombre' => trim($_POST['sucursal_nombre'] ?? ''),
                'sucursal_direccion' => trim($_POST['sucursal_direccion'] ?? ''),
                'localidad_id' => $_POST['localidad_id'] ? intval($_POST['localidad_id']) : null,
                'sucursal_telefono' => trim($_POST['sucursal_telefono'] ?? ''),
                'sucursal_email' => trim($_POST['sucursal_email'] ?? ''),
                'sucursal_contacto' => trim($_POST['sucursal_contacto'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarSucursal($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion_entidad':
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($entidad_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstadoEntidad($conexion, $entidad_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion_sucursal':
            $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($sucursal_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstadoSucursal($conexion, $sucursal_id, $accion_js, $empresa_idx, $pagina_idx_sucursales);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_entidad':
            $id = intval($_POST['entidad_id'] ?? $_GET['entidad_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $entidad = obtenerEntidadPorId($conexion, $id, $empresa_idx);
            if ($entidad) {
                echo json_encode($entidad, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Entidad no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_sucursal':
            $id = intval($_POST['sucursal_id'] ?? $_GET['sucursal_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $sucursal = obtenerSucursalPorId($conexion, $id, $empresa_idx);
            if ($sucursal) {
                echo json_encode($sucursal, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Sucursal no encontrada'], JSON_UNESCAPED_UNICODE);
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