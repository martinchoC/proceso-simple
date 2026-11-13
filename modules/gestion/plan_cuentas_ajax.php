<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'modulo_id' => 2, 'paginas_url' => ['plan_cuentas.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "plan_cuentas_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto
$empresa_id = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 68);
// Solo una página de este módulo (si no, la de plan_cuentas.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 2, (int)$pagina_idx, 'plan_cuentas.php');

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            error_log("Ejecutando acción listar para empresa_id: $empresa_id, pagina_idx: $pagina_idx");
            $cuentas = obtenerCuentas($conexion, $empresa_id, $pagina_idx);
            error_log("Cuentas obtenidas: " . count($cuentas));
            if (empty($cuentas)) {
                error_log("No se encontraron cuentas para la empresa $empresa_id");
            }
            echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_cuentas_padre':
            $excluir_id = intval($_GET['excluir_id'] ?? 0);
            $cuentas = obtenerCuentasParaSelect($conexion, $empresa_id, $excluir_id);
            echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_nivel_padre':
            $cuenta_padre_id = intval($_GET['cuenta_padre_id'] ?? 0);
            $nivel = obtenerNivelDesdePadre($conexion, $cuenta_padre_id, $empresa_id);
            echo json_encode(['nivel' => $nivel], JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            $data = [
                'empresa_id' => $empresa_id,
                'codigo' => trim($_POST['codigo'] ?? ''),
                'nombre' => trim($_POST['nombre'] ?? ''),
                'naturaleza' => $_POST['naturaleza'] ?? '',
                'cuenta_padre_id' => !empty($_POST['cuenta_padre_id']) ? intval($_POST['cuenta_padre_id']) : null,
                'nivel' => intval($_POST['nivel'] ?? 1),
                'orden' => intval($_POST['orden'] ?? 0),
                'es_imputable' => intval($_POST['es_imputable'] ?? 1)
            ];

            $resultado = agregarCuenta($conexion, $data, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['cont_cuenta_id'] ?? 0);
            $data = [
                'empresa_id' => $empresa_id,
                'codigo' => trim($_POST['codigo'] ?? ''),
                'nombre' => trim($_POST['nombre'] ?? ''),
                'naturaleza' => $_POST['naturaleza'] ?? '',
                'cuenta_padre_id' => !empty($_POST['cuenta_padre_id']) ? intval($_POST['cuenta_padre_id']) : null,
                'nivel' => intval($_POST['nivel'] ?? 1),
                'orden' => intval($_POST['orden'] ?? 0),
                'es_imputable' => intval($_POST['es_imputable'] ?? 1)
            ];

            $resultado = editarCuenta($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $cont_cuenta_id = intval($_POST['cont_cuenta_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($cont_cuenta_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $cont_cuenta_id, $accion_js, $empresa_id, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['cont_cuenta_id'] ?? $_GET['cont_cuenta_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $cuenta = obtenerCuentaPorId($conexion, $id, $empresa_id);
            if ($cuenta) {
                echo json_encode($cuenta, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Cuenta no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Error en plan_cuentas_ajax.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>