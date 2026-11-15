<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['sucursales.php']]);
require_once __DIR__ . '/../../core/canales_venta.php';

// Canales que vende la sucursal (core/canales_venta.php). null = no se tocan (sin canales de venta, o la pantalla
// no mandó el campo); si no, la lista de ids ([] = vende todos), validada antes de grabar la sucursal.
function sucCanalesDelFormulario($conexion, int $empresa_idx): ?array {
    $crudo = (string)($_POST['canales_venta'] ?? '');
    if ($crudo === '' || !cvta_empresa_usa($conexion, $empresa_idx)) return null;
    $ids = json_decode($crudo, true);
    if (!is_array($ids)) return null;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    foreach ($ids as $c) {
        if (!cvta_canal($conexion, $empresa_idx, $c)) {
            echo json_encode(['resultado' => false, 'error' => 'Uno de los canales no existe o está inactivo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    return $ids;
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once "sucursales_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 36); // ✅ CORREGIDO: Página ID para sucursales
// Solo una página de este módulo (si no, la de sucursales.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conexion, 2, (int)$pagina_idx, 'sucursales.php');

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $sucursales = obtenerSucursales($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_tipos_sucursales_activos':
            $tipos = obtenerTiposSucursalesActivos($conexion, $empresa_idx);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_provincias':
            $provincias = obtenerProvincias($conexion);
            echo json_encode($provincias, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_localidades_por_provincia':
            $provincia_id = intval($_GET['provincia_id'] ?? $_POST['provincia_id'] ?? 0);
            $localidades = obtenerLocalidadesPorProvincia($conexion, $provincia_id);
            echo json_encode($localidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_localidades_activas':
            $provincia_id = intval($_GET['provincia_id'] ?? $_POST['provincia_id'] ?? 0);
            $localidades = obtenerLocalidadesActivas($conexion, $provincia_id);
            echo json_encode($localidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            $canales_form = sucCanalesDelFormulario($conexion, (int)$empresa_idx);
            $data = [
                'sucursal_nombre' => trim($_POST['sucursal_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'sucursal_tipo_id' => intval($_POST['sucursal_tipo_id'] ?? 0),
                'localidad_id' => !empty($_POST['localidad_id']) ? intval($_POST['localidad_id']) : null,
                'direccion' => trim($_POST['direccion'] ?? ''),
                'telefono' => trim($_POST['telefono'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarSucursal($conexion, $data);
            if (!empty($resultado['resultado']) && $canales_form !== null) {
                cvta_guardar_canales_sucursal($conexion, (int)$empresa_idx, (int)($resultado['sucursal_id'] ?? 0), $canales_form, (int)$ctx['usuario_id']);
            }
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['sucursal_id'] ?? 0);
            $canales_form = sucCanalesDelFormulario($conexion, (int)$empresa_idx);
            $data = [
                'sucursal_nombre' => trim($_POST['sucursal_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'sucursal_tipo_id' => intval($_POST['sucursal_tipo_id'] ?? 0),
                'localidad_id' => !empty($_POST['localidad_id']) ? intval($_POST['localidad_id']) : null,
                'direccion' => trim($_POST['direccion'] ?? ''),
                'telefono' => trim($_POST['telefono'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarSucursal($conexion, $id, $data);
            if (!empty($resultado['resultado']) && $canales_form !== null) {
                cvta_guardar_canales_sucursal($conexion, (int)$empresa_idx, $id, $canales_form, (int)$ctx['usuario_id']);
            }
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($sucursal_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $sucursal_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['sucursal_id'] ?? $_GET['sucursal_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $sucursal = obtenerSucursalPorId($conexion, $id, $empresa_idx);
            if ($sucursal) {
                if (cvta_empresa_usa($conexion, (int)$empresa_idx)) {
                    $sucursal['canales_venta'] = cvta_canales_sucursal($conexion, (int)$empresa_idx, $id);
                }
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