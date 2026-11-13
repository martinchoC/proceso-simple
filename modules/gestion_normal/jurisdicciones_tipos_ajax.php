<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['jurisdicciones_tipos.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);  // mostrar errores en pantalla

require_once __DIR__ . '/../../db.php';
require_once "jurisdicciones_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 75); // ← Cambiado a 73
// Solo una página de este módulo (si no, la de jurisdicciones_tipos.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 5, (int)$pagina_idx, 'jurisdicciones_tipos.php');

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $tipos = obtenerTiposJurisdiccion($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            $data = [
                'empresa_id' => $empresa_idx,
                'jurisdiccion_tipo' => trim($_POST['jurisdiccion_tipo'] ?? ''),
                'codigo' => trim($_POST['codigo'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = agregarTipoJurisdiccion($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['jurisdiccion_tipo_id'] ?? 0);
            $data = [
                'jurisdiccion_tipo' => trim($_POST['jurisdiccion_tipo'] ?? ''),
                'codigo' => trim($_POST['codigo'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = editarTipoJurisdiccion($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $jurisdiccion_tipo_id = intval($_POST['jurisdiccion_tipo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($jurisdiccion_tipo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $jurisdiccion_tipo_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['jurisdiccion_tipo_id'] ?? $_GET['jurisdiccion_tipo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $tipo = obtenerTipoJurisdiccionPorId($conexion, $id);
            if ($tipo) {
                echo json_encode($tipo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Tipo de jurisdicción no encontrado'], JSON_UNESCAPED_UNICODE);
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