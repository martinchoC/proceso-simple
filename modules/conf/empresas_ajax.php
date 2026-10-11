<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 1, 'paginas_url' => ['empresas.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "empresas_model.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = $_SESSION['usuario_id'] ?? null;

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $empresas = obtenerempresas($conexion);
        echo json_encode($empresas, JSON_UNESCAPED_UNICODE);
        break;
    
    case 'obtener_localidades':
        $localidades = obtenerLocalidades($conexion);
        echo json_encode($localidades, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_documentos_tipos':
        $docs = obtenerDocumentosTipos($conexion);
        echo json_encode($docs, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_condiciones_fiscales':
        $condiciones = obtenerCondicionesFiscales($conexion);
        echo json_encode($condiciones, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_iibb_regimenes':
        $regimenes = obtenerIibbRegimenes($conexion);
        echo json_encode($regimenes, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_jurisdicciones_sedes':
        $sedes = obtenerJurisdiccionesSedes($conexion);
        echo json_encode($sedes, JSON_UNESCAPED_UNICODE);
        break;
    
    case 'agregar':
        $data = [
            'empresa' => $_REQUEST['empresa'] ?? '',
            'documento_tipo_id' => $_REQUEST['documento_tipo_id'] ?? 1,
            'documento_numero' => $_REQUEST['documento_numero'] ?? '',
            'telefono' => $_REQUEST['telefono'] ?? '',
            'domicilio' => $_REQUEST['domicilio'] ?? '',
            'localidad_id' => $_REQUEST['localidad_id'] ?? null,
            'condicion_fiscal_id' => $_REQUEST['condicion_fiscal_id'] ?? null,
            'iibb_regimen_id' => $_REQUEST['iibb_regimen_id'] ?? 1,
            'jurisdiccion_sede_id' => $_REQUEST['jurisdiccion_sede_id'] ?? 2,
            'ingresos_brutos_nro' => $_REQUEST['ingresos_brutos_nro'] ?? null,
            'inicio_actividades' => $_REQUEST['inicio_actividades'] ?? null,
            'leyenda_fiscal' => $_REQUEST['leyenda_fiscal'] ?? null,
            'email' => $_REQUEST['email'] ?? '',
            'base_conf' => $_REQUEST['base_conf'] ?? null,
            'tabla_estado_registro_id' => $_REQUEST['tabla_estado_registro_id'] ?? ($_REQUEST['estado_registro_id'] ?? 1)
        ];
        // Validación adicional
        if (empty($data['empresa']) || empty($data['documento_numero']) || empty($data['localidad_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Campos obligatorios faltantes (Razón social, CUIT o Localidad)'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $resultado = agregarempresa($conexion, $data, $usuario_id);
        echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
        break;

    case 'editar':
        $id = intval($_REQUEST['empresa_id'] ?? 0);
        $data = [
            'empresa' => $_REQUEST['empresa'] ?? '',
            'documento_tipo_id' => $_REQUEST['documento_tipo_id'] ?? 1,
            'documento_numero' => $_REQUEST['documento_numero'] ?? '',
            'telefono' => $_REQUEST['telefono'] ?? '',
            'domicilio' => $_REQUEST['domicilio'] ?? '',
            'localidad_id' => $_REQUEST['localidad_id'] ?? null,
            'condicion_fiscal_id' => $_REQUEST['condicion_fiscal_id'] ?? null,
            'iibb_regimen_id' => $_REQUEST['iibb_regimen_id'] ?? 1,
            'jurisdiccion_sede_id' => $_REQUEST['jurisdiccion_sede_id'] ?? 2,
            'ingresos_brutos_nro' => $_REQUEST['ingresos_brutos_nro'] ?? null,
            'inicio_actividades' => $_REQUEST['inicio_actividades'] ?? null,
            'leyenda_fiscal' => $_REQUEST['leyenda_fiscal'] ?? null,
            'email' => $_REQUEST['email'] ?? '',
            'base_conf' => $_REQUEST['base_conf'] ?? null,
            'tabla_estado_registro_id' => $_REQUEST['tabla_estado_registro_id'] ?? ($_REQUEST['estado_registro_id'] ?? 1)
        ];
        $resultado = editarempresa($conexion, $id, $data, $usuario_id);
        echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
        break;

    case 'eliminar':
        $id = intval($_REQUEST['empresa_id'] ?? 0);
        $resultado = eliminarempresa($conexion, $id);
        echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener':
        $id = intval($_REQUEST['empresa_id'] ?? 0);
        $empresa = obtenerempresaPorId($conexion, $id);
        echo json_encode($empresa, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida'], JSON_UNESCAPED_UNICODE);
}
