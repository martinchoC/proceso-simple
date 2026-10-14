<?php
/**
 * AJAX Controller para ABM de Roles de Entidades y Cuentas Contables por Empresa
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['empresas_roles_cuentas.php']]);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/empresas_roles_cuentas_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? $ctx['empresa_id']);

header('Content-Type: application/json; charset=utf-8');

if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar_roles_empresa':
            $roles = obtenerRolesCuentasPorEmpresa($conexion, $empresa_idx);
            echo json_encode(['data' => $roles, 'resultado' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_roles_disponibles':
            $roles = obtenerRolesDisponiblesParaEmpresa($conexion, $empresa_idx);
            echo json_encode($roles, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_cuentas_contables':
            $cuentas = obtenerCuentasContablesEmpresaSelect($conexion, $empresa_idx);
            echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_rol_empresa':
            $id = intval($_GET['empresa_rol_cuenta_id'] ?? $_POST['empresa_rol_cuenta_id'] ?? 0);
            $rol = obtenerRolEmpresaPorId($conexion, $id, $empresa_idx);
            if ($rol) {
                echo json_encode(['resultado' => true, 'data' => $rol], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['resultado' => false, 'error' => 'Registro no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'guardar_rol_empresa':
            $data = [
                'empresa_id' => $empresa_idx,
                'rol_entidad_id' => intval($_POST['rol_entidad_id'] ?? 0),
                'cont_cuenta_id' => intval($_POST['cont_cuenta_id'] ?? 0),
                'crear_nuevo_rol' => !empty($_POST['crear_nuevo_rol']),
                'nuevo_rol_codigo' => trim($_POST['nuevo_rol_codigo'] ?? ''),
                'nuevo_rol_nombre' => trim($_POST['nuevo_rol_nombre'] ?? ''),
                'nuevo_rol_descripcion' => trim($_POST['nuevo_rol_descripcion'] ?? '')
            ];
            $res = guardarRolEmpresa($conexion, $data);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_rol_empresa':
            $id = intval($_POST['empresa_rol_cuenta_id'] ?? 0);
            $data = [
                'empresa_id' => $empresa_idx,
                'cont_cuenta_id' => intval($_POST['cont_cuenta_id'] ?? 0),
                'tabla_estado_registro_id' => intval($_POST['tabla_estado_registro_id'] ?? 1)
            ];
            $res = editarRolEmpresa($conexion, $id, $data);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'cambiar_estado_rol_empresa':
            $id = intval($_POST['empresa_rol_cuenta_id'] ?? 0);
            $nuevoEstado = intval($_POST['tabla_estado_registro_id'] ?? 1);
            $res = cambiarEstadoRolEmpresa($conexion, $id, $empresa_idx, $nuevoEstado);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['resultado' => false, 'error' => 'Acción no válida o no especificada: ' . htmlspecialchars($accion)], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['resultado' => false, 'error' => 'Error en el servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
