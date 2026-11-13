<?php
/**
 * Controlador AJAX: Padrón de Clientes
 * Módulo 4: Estudio Contable (estudio_clientes_ajax.php)
 * Página ID: 402
 */

declare(strict_types=1);

// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 402]);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/estudio_clientes_model.php';
require_once __DIR__ . '/estudio_clientes_alta_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

$usuario_id = $ctx['usuario_id'];
$empresa_idx = $ctx['empresa_id'];
$pagina_idx = 402;

$accion = $_REQUEST['accion'] ?? '';
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 402, (string)$accion, [
    'guardar' => (intval($_POST['cliente_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
    'alta_cliente' => 'alta_asistente',
    'guardar_plantilla' => 'alta_asistente',
    'crear_empresa' => 'alta_asistente',
    'guardar_jurisdiccion' => 'editar',
    'eliminar_jurisdiccion' => 'editar',
    'cambiar_estado' => (intval($_POST['nuevo_estado_id'] ?? 1) === 2 ? 'inhabilitar' : 'editar'),
]);

switch ($accion) {
    case 'obtener_combos':
        $combos = estudio_clientes_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos recuperados exitosamente.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'listar':
        $filtros = [
            'cliente_tipo_id' => $_GET['cliente_tipo_id'] ?? '',
            'condicion_iva_id' => $_GET['condicion_iva_id'] ?? '',
            'terminacion_cuit' => $_GET['terminacion_cuit'] ?? '',
            'estado_id' => $_GET['estado_id'] ?? '',
            'busqueda' => $_GET['busqueda'] ?? ''
        ];
        $lista = estudio_clientes_listar($conexion, $empresa_idx, $filtros);
        echo json_encode([
            'success' => true,
            'message' => 'Clientes recuperados exitosamente.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'obtener':
        $cliente_id = intval($_GET['cliente_id'] ?? 0);
        $cliente = estudio_cliente_obtener($conexion, $empresa_idx, $cliente_id);
        if (!$cliente) {
            echo json_encode([
                'success' => false,
                'message' => 'Cliente no encontrado.',
                'data' => null,
                'error' => 'NOT_FOUND'
            ]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Cliente obtenido.',
            'data' => $cliente,
            'error' => null
        ]);
        break;

    case 'obtener_empresa_erp':
        $empresa_vinculada_id = intval($_GET['empresa_vinculada_id'] ?? 0);
        if ($empresa_vinculada_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Empresa vinculada inválida o no seleccionada.',
                'data' => null,
                'error' => 'INVALID_ID'
            ]);
            exit;
        }
        if (!estudio_cliente_tiene_vinculacion($conexion, (int)$empresa_idx, $empresa_vinculada_id)) {
            echo json_encode([
                'success' => false,
                'message' => 'La empresa no otorgó al estudio una vinculación vigente. No se pueden consultar sus datos.',
                'data' => null,
                'error' => 'SIN_VINCULACION'
            ]);
            exit;
        }
        $empresa_info = estudio_cliente_obtener_empresa_erp($conexion, $empresa_vinculada_id);
        if (!$empresa_info) {
            echo json_encode([
                'success' => false,
                'message' => 'Empresa ERP no encontrada en el sistema.',
                'data' => null,
                'error' => 'NOT_FOUND'
            ]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Datos de la empresa ERP recuperados.',
            'data' => $empresa_info,
            'error' => null
        ]);
        break;

    case 'leer_constancia':
        // Constancia de inscripción de ARCA (multipart, campo "constancia") leída con la IA del estudio para precargar
        // el alta (Nuevo cliente o Alta con asistente); no graba nada (core/constancia_cuit_ia.php)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido.', 'data' => null, 'error' => 'METHOD']);
            break;
        }
        if (!permisos_puede($conexion, 402, 'agregar') && !permisos_puede($conexion, 402, 'alta_asistente')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Tu perfil no tiene permiso para esta acción.', 'data' => null, 'error' => 'FORBIDDEN']);
            break;
        }
        require_once __DIR__ . '/../../core/constancia_cuit_ia.php';
        $res = cci_leer_constancia_subida($conexion, (int)$empresa_idx, $_FILES['constancia'] ?? null);
        if (!empty($res['success']) && $res['data']['cuit'] !== '') {
            // ¿Ya es cliente del estudio?
            $st = mysqli_prepare($conexion, "SELECT cliente_id, razon_social FROM estudio__clientes
                                             WHERE empresa_id = ? AND REPLACE(REPLACE(cuit, '-', ''), ' ', '') = ? LIMIT 1");
            $cuit = $res['data']['cuit'];
            mysqli_stmt_bind_param($st, "is", $empresa_idx, $cuit);
            mysqli_stmt_execute($st);
            $cli = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            $res['data']['cliente_existente'] = $cli ? ['cliente_id' => (int)$cli['cliente_id'], 'razon_social' => $cli['razon_social']] : null;
        }
        echo json_encode($res);
        break;

    case 'alta_combos':
        echo json_encode(['success' => true, 'message' => 'OK', 'data' => estudio_alta_combos($conexion, (int)$empresa_idx), 'error' => null]);
        break;

    case 'alta_cliente':
        // POST-only: crea empresa, vinculación y cliente
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido.', 'data' => null, 'error' => 'METHOD']);
            break;
        }
        echo json_encode(estudio_alta_cliente($conexion, (int)$empresa_idx, (int)$usuario_id, $_POST));
        break;

    case 'guardar_plantilla':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido.', 'data' => null, 'error' => 'METHOD']);
            break;
        }
        echo json_encode(estudio_plantilla_guardar($conexion, (int)$empresa_idx, intval($_POST['empresa_origen_id'] ?? 0), (string)($_POST['nombre'] ?? '')));
        break;

    case 'guardar':
        $res = estudio_cliente_guardar($conexion, $empresa_idx, $_POST, $usuario_id);
        // Cambió el nivel de un cliente con empresa: se ajusta la empresa (nivel de gestión, perfiles, sucursal).
        // Toca la configuración de otra empresa: exige "Alta con asistente" y vinculación de escritura.
        if (!empty($res['success']) && !empty($res['data']['nivel_cambio'])) {
            $emp = (int)$res['data']['empresa_vinculada_id'];
            if (!permisos_puede($conexion, 402, 'alta_asistente')) {
                $res['message'] .= ' La empresa del cliente no se ajustó al nuevo nivel: su perfil no tiene "Alta con asistente".';
            } elseif (!vinculacionesVerificarPermiso($conexion, $emp, (int)$empresa_idx, 'contabilidad', true)) {
                $res['message'] .= ' La empresa del cliente no se ajustó al nuevo nivel: la vinculación no es de lectura y escritura.';
            } else {
                $pasos = [];
                estudio_alta_aplicar_nivel($conexion, (int)$usuario_id, $emp, (string)$res['data']['nivel_servicio'],
                    function (string $n, bool $ok, string $d = '') use (&$pasos) { $pasos[] = ['paso' => $n, 'ok' => $ok, 'detalle' => $d]; });
                $res['data']['pasos'] = $pasos;
            }
        }
        echo json_encode($res);
        break;

    case 'crear_empresa':
        // Ficha del cliente: crea la empresa de un cliente del padrón que pasa a FISCAL / CONTABLE / GESTION
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido.', 'data' => null, 'error' => 'METHOD']);
            break;
        }
        echo json_encode(estudio_cliente_crear_empresa($conexion, (int)$empresa_idx, (int)$usuario_id, intval($_POST['cliente_id'] ?? 0), $_POST));
        break;

    case 'guardar_jurisdiccion':
        $res = estudio_cliente_guardar_jurisdiccion($conexion, $empresa_idx, $_POST);
        echo json_encode($res);
        break;

    case 'eliminar_jurisdiccion':
        $cliente_jurisdiccion_id = intval($_POST['cliente_jurisdiccion_id'] ?? 0);
        $res = estudio_cliente_eliminar_jurisdiccion($conexion, $empresa_idx, $cliente_jurisdiccion_id);
        echo json_encode($res);
        break;

    case 'cambiar_estado':
        $cliente_id = intval($_POST['cliente_id'] ?? 0);
        $nuevo_estado_id = intval($_POST['nuevo_estado_id'] ?? 1);
        $res = estudio_cliente_cambiar_estado($conexion, $empresa_idx, $cliente_id, $nuevo_estado_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => "Acción '{$accion}' no reconocida.",
            'data' => null,
            'error' => 'UNKNOWN_ACTION'
        ]);
        break;
}
