<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 1, 'paginas_url' => ['empresas.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "empresas_model.php";
require_once __DIR__ . '/../../core/empresa_logo.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = $_SESSION['usuario_id'] ?? null;

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($accion) {
    case 'listar':
        $empresas = obtenerempresas($conexion);
        echo json_encode($empresas, JSON_UNESCAPED_UNICODE);
        break;
    
    case 'obtener_provincias':
        echo json_encode(obtenerProvincias($conexion), JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_localidades':
        $localidades = obtenerLocalidades($conexion, (int)($_REQUEST['provincia_id'] ?? 0));
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
    
    case 'leer_constancia':
        // Constancia de inscripción de ARCA (multipart, campo "constancia") leída con la IA de la empresa activa:
        // devuelve los datos para precargar el alta, no graba nada (core/constancia_cuit_ia.php)
        require_once __DIR__ . '/../../core/constancia_cuit_ia.php';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
            break;
        }
        echo json_encode(cci_leer_constancia_subida($conexion, (int)$ctx['empresa_id'], $_FILES['constancia'] ?? null), JSON_UNESCAPED_UNICODE);
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
            'tipo_operacion' => $_REQUEST['tipo_operacion'] ?? null,
            'nivel_gestion' => $_REQUEST['nivel_gestion'] ?? null,
            'formato_fecha' => $_REQUEST['formato_fecha'] ?? null,
            'separador_miles' => $_REQUEST['separador_miles'] ?? null,
            'separador_decimal' => $_REQUEST['separador_decimal'] ?? null,
            'decimales_stock' => $_REQUEST['decimales_stock'] ?? null,
            'usa_presentaciones' => $_REQUEST['usa_presentaciones'] ?? null,
            'es_principal' => !empty($_REQUEST['es_principal']) ? 1 : 0,
            // Uso de la IA en todos los módulos (core/ia_empresa.php); sin el campo, habilitada
            'ia_habilitada' => intval($_REQUEST['ia_habilitada'] ?? 1) === 1 ? 1 : 0,
            'ia_api_key' => trim((string)($_POST['ia_api_key'] ?? '')),
            'ia_api_key_quitar' => intval($_POST['ia_api_key_quitar'] ?? 0),
            'tabla_estado_registro_id' => $_REQUEST['tabla_estado_registro_id'] ?? ($_REQUEST['estado_registro_id'] ?? 1)
        ];
        // Validación adicional
        if (empty($data['empresa']) || empty($data['documento_numero']) || empty($data['localidad_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Campos obligatorios faltantes (Razón social, CUIT o Localidad)'], JSON_UNESCAPED_UNICODE);
            break;
        }
        if (!conf_ia_clave_valida($data['ia_api_key'])) {
            echo json_encode(['resultado' => false, 'error' => 'La clave de la API de la IA no es válida (sin espacios, hasta 300 caracteres).'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $nueva_empresa_id = 0;
        $resultado = agregarempresa($conexion, $data, $usuario_id, $nueva_empresa_id);
        // Logo (multipart, campo "logo"): el nombre del archivo lleva la empresa, por eso va después del alta
        if ($resultado && $nueva_empresa_id > 0 && !empty($_FILES['logo'])) {
            $logo = emp_logo_guardar_archivo($_FILES['logo'], $nueva_empresa_id);
            if (!empty($logo['error'])) {
                echo json_encode(['resultado' => false, 'error' => 'La empresa se creó, pero el logo no: ' . $logo['error']], JSON_UNESCAPED_UNICODE);
                break;
            }
            guardarLogoEmpresa($conexion, $nueva_empresa_id, $logo['ruta'], false);
        }
        echo json_encode(['resultado' => $resultado] + ($resultado && $nueva_empresa_id > 0 ? ['empresa_id' => $nueva_empresa_id] : []), JSON_UNESCAPED_UNICODE);
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
            'tipo_operacion' => $_REQUEST['tipo_operacion'] ?? null,
            'nivel_gestion' => $_REQUEST['nivel_gestion'] ?? null,
            'formato_fecha' => $_REQUEST['formato_fecha'] ?? null,
            'separador_miles' => $_REQUEST['separador_miles'] ?? null,
            'separador_decimal' => $_REQUEST['separador_decimal'] ?? null,
            'decimales_stock' => $_REQUEST['decimales_stock'] ?? null,
            'usa_presentaciones' => $_REQUEST['usa_presentaciones'] ?? null,
            'es_principal' => !empty($_REQUEST['es_principal']) ? 1 : 0,
            // Uso de la IA en todos los módulos (core/ia_empresa.php); sin el campo, habilitada
            'ia_habilitada' => intval($_REQUEST['ia_habilitada'] ?? 1) === 1 ? 1 : 0,
            'ia_api_key' => trim((string)($_POST['ia_api_key'] ?? '')),
            'ia_api_key_quitar' => intval($_POST['ia_api_key_quitar'] ?? 0),
            'tabla_estado_registro_id' => $_REQUEST['tabla_estado_registro_id'] ?? ($_REQUEST['estado_registro_id'] ?? 1)
        ];
        if (!conf_ia_clave_valida($data['ia_api_key'])) {
            echo json_encode(['resultado' => false, 'error' => 'La clave de la API de la IA no es válida (sin espacios, hasta 300 caracteres).'], JSON_UNESCAPED_UNICODE);
            break;
        }
        // Logo (multipart, campo "logo"): se valida y guarda antes de tocar la empresa
        $logo = emp_logo_guardar_archivo($_FILES['logo'] ?? null, $id);
        if (!empty($logo['error'])) {
            echo json_encode(['resultado' => false, 'error' => $logo['error']], JSON_UNESCAPED_UNICODE);
            break;
        }
        $resultado = editarempresa($conexion, $id, $data, $usuario_id);
        $respuesta = ['resultado' => $resultado];
        if ($resultado) {
            $quitar_logo = intval($_REQUEST['quitar_logo'] ?? 0) === 1;
            // Después de editarempresa: si creó una vigencia nueva de parámetros, también lleva el logo
            guardarLogoEmpresa($conexion, $id, $logo['ruta'], $quitar_logo);
            // Colores ajustados a mano: solo con el mismo logo (un logo nuevo los vuelve a calcular)
            if (intval($_REQUEST['colores_manual'] ?? 0) === 1 && empty($logo['ruta']) && !$quitar_logo) {
                emp_logo_fijar_manual($conexion, $id, $_REQUEST['color_logo_oscuro'] ?? '', $_REQUEST['color_logo_claro'] ?? '');
            }
            $respuesta['tema'] = emp_logo_tema($conexion, $id);
        }
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
        break;

    case 'recalcular_colores':
        // Vuelve a pedir los colores del logo actual (IA de la empresa → cálculo del servidor) y los guarda
        $id = intval($_REQUEST['empresa_id'] ?? 0);
        $colores = emp_logo_recalcular($conexion, $id);
        if ($colores === null) {
            echo json_encode(['success' => false, 'message' => 'La empresa no tiene logo o no se le pudieron sacar colores.'], JSON_UNESCAPED_UNICODE);
            break;
        }
        echo json_encode(['success' => true, 'data' => $colores,
            'message' => $colores['origen'] === 'IA' ? 'Colores elegidos por la IA.' : 'Colores calculados del logo (sin IA).'], JSON_UNESCAPED_UNICODE);
        break;

    case 'inhabilitar':
    case 'activar':
        $id = intval($_REQUEST['empresa_id'] ?? 0);
        $resultado = cambiarEstadoEmpresa($conexion, $id, $accion === 'activar' ? 1 : 2);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'marcar_principal':
        $id = intval($_REQUEST['empresa_id'] ?? 0);
        $resultado = marcarEmpresaPrincipal($conexion, $id, $usuario_id);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener':
        $id = intval($_REQUEST['empresa_id'] ?? 0);
        $empresa = obtenerempresaPorId($conexion, $id);
        echo json_encode($empresa, JSON_UNESCAPED_UNICODE);
        break;

        default:
            echo json_encode(['error' => 'Acción no definida'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    error_log("Error en empresas_ajax.php [accion={$accion}]: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(200);
    echo json_encode([
        'resultado' => false,
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage(),
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
