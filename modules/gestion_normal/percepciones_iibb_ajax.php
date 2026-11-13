<?php
declare(strict_types=1);
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 616]);

/**
 * percepciones_iibb_ajax.php — Percepciones IIBB practicadas (página 116).
 * Respuesta: {success: bool, data: ..., message: string}; 'exportar' devuelve el archivo.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/percepciones_iibb_model.php';

ob_start();

function perResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

// Función de la página (motor de botones) activa con ese accion_js y asignada al perfil del usuario
function perFuncionHabilitada(mysqli $conexion, int $pagina_id, string $accion_js): bool
{
    return permisos_puede($conexion, $pagina_id, $accion_js);
}

$pagina_idx = 616;
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

try {
    switch ($accion) {
        case 'maestros':
            perResponder(true, [
                'jurisdicciones' => perJurisdicciones($conexion, $empresa_id),
                'organismos' => perEntidadesOrganismo($conexion, $empresa_id),
                'sucursales' => perSucursales($conexion, $empresa_id),
                'puede_exportar' => perFuncionHabilitada($conexion, $pagina_idx, 'exportar'),
                'puede_liquidar' => perFuncionHabilitada($conexion, $pagina_idx, 'liquidar'),
            ]);
            break;

        case 'listar':
            $jur = (int)($_GET['jurisdiccion_id'] ?? $_POST['jurisdiccion_id'] ?? 0);
            $periodo = (string)($_GET['periodo'] ?? $_POST['periodo'] ?? '');
            $filas = perListado($conexion, $empresa_id, $jur, $periodo);
            $cfg = perConfigPeriodo($conexion, $empresa_id, $jur, $periodo);
            perResponder(true, ['filas' => $filas, 'resumen' => perResumen($filas),
                'config' => $cfg ? ['formato_ddjj' => $cfg['formato_ddjj'], 'numero_agente' => $cfg['numero_agente'],
                                    'codigo_jurisdiccion_cm' => $cfg['codigo_jurisdiccion_cm']] : null]);
            break;

        case 'exportar':
            if (!perFuncionHabilitada($conexion, $pagina_idx, 'exportar')) perResponder(false, null, 'Acción no habilitada', 403);
            $archivo = perExportar($conexion, $empresa_id, (int)($_GET['jurisdiccion_id'] ?? 0), (string)($_GET['periodo'] ?? ''));
            if (ob_get_length()) ob_clean();
            header('Content-Type: ' . $archivo['mime']);
            header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
            header('Content-Length: ' . strlen($archivo['contenido']));
            echo $archivo['contenido'];
            exit;

        case 'liquidar':
            if (!perFuncionHabilitada($conexion, $pagina_idx, 'liquidar')) perResponder(false, null, 'Acción no habilitada', 403);
            $r = perLiquidar($conexion, $empresa_id, $usuario_id, $_POST);
            perResponder(true, $r, $r['message']);
            break;

        default:
            perResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[percepciones_iibb_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    perResponder(false, null, 'Error de base de datos', 500);
} catch (Throwable $e) {
    error_log('[percepciones_iibb_ajax] ' . $accion . ': ' . $e->getMessage());
    if ($accion === 'exportar') {
        // Descarga directa: el error vuelve como texto para que el navegador lo muestre
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo $e->getMessage();
        exit;
    }
    perResponder(false, null, $e->getMessage(), 400);
}
