<?php
/**
 * AJAX Handler: Asistente de Inicio de Empresa y Clonación Autorizada
 * Módulo: Gestión (mod 2) / Gestión Normal (mod 5)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 621, 'params' => ['empresa_id', 'empresa_idx']]);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

require_once __DIR__ . '/empresa_inicio_model.php';
// Alta de empresa desde la constancia de CUIT: agregarempresa y los combos de la ABM de empresas (a nivel de archivo:
// el modelo de conf toma $conn global).
require_once __DIR__ . '/../conf/empresas_model.php';

// "accion" lo manda assets/js/constancia-cuit-ia.js (leer_constancia); el resto del asistente usa "action".
$action = $_POST['action'] ?? $_GET['action'] ?? $_POST['accion'] ?? $_GET['accion'] ?? '';
$empresa_id = (int) $ctx['empresa_id'];
$usuario_id = (int) $ctx['usuario_id'];
// Perfiles del módulo de esta página (Gestión = 2; en gestion_normal, 5).
$modulo_id = 5;

// Alta de una empresa nueva desde la constancia (lectura con IA incluida): función "alta_empresa" de la página.
permisos_exigir_accion($conexion, 621, (string) $action, [
    'leer_constancia' => 'alta_empresa',
    'alta_empresa_constancia' => 'alta_empresa',
]);

try {
    switch ($action) {
        case 'leer_constancia':
            // Constancia de inscripción de ARCA (multipart, campo "constancia") leída con la IA de la empresa activa
            // (quien paga la llamada). No graba nada: precarga el modal de alta (core/constancia_cuit_ia.php).
            require_once __DIR__ . '/../../core/constancia_cuit_ia.php';
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'data' => null, 'message' => 'Método no permitido.']);
                break;
            }
            echo json_encode(cci_leer_constancia_subida($conexion, $empresa_id, $_FILES['constancia'] ?? null), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_combos_alta':
            echo json_encode(['success' => true, 'data' => [
                'condiciones' => obtenerCondicionesFiscales($conexion),
                'provincias' => obtenerProvincias($conexion),
                'iibb_regimenes' => obtenerIibbRegimenes($conexion),
                'jurisdicciones_sedes' => obtenerJurisdiccionesSedes($conexion),
            ]], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_localidades':
            echo json_encode(['success' => true, 'data' => obtenerLocalidades($conexion, (int) ($_GET['provincia_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
            break;

        case 'alta_empresa_constancia':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
                break;
            }
            echo json_encode(inicioAltaEmpresaConstancia($conexion, $empresa_id, $usuario_id, $modulo_id, [
                'razon_social' => $_POST['razon_social'] ?? '',
                'cuit' => $_POST['cuit'] ?? '',
                'condicion_fiscal_id' => $_POST['condicion_fiscal_id'] ?? 0,
                'domicilio' => $_POST['domicilio'] ?? '',
                'localidad_id' => $_POST['localidad_id'] ?? 0,
                'iibb_regimen_id' => $_POST['iibb_regimen_id'] ?? 1,
                'jurisdiccion_sede_id' => $_POST['jurisdiccion_sede_id'] ?? 0,
                'inicio_actividades' => $_POST['inicio_actividades'] ?? '',
                'mes_cierre_ejercicio' => $_POST['mes_cierre_ejercicio'] ?? 12,
                'tipo_operacion' => $_POST['tipo_operacion'] ?? '',
                'copiar_desde_actual' => intval($_POST['copiar_desde_actual'] ?? 0),
            ]), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_diagnostico':
            $diag = inicioObtenerDiagnostico($conexion, $empresa_id, $modulo_id);
            echo json_encode(['success' => true, 'data' => $diag]);
            break;

        case 'obtener_empresas_autorizadas':
            $empresas = inicioObtenerEmpresasAutorizadas($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $empresas]);
            break;

        case 'previsualizar_copia':
            $origen_id = intval($_POST['empresa_origen_id'] ?? $_GET['empresa_origen_id'] ?? 0);
            $prev = inicioPrevisualizarCopia($conexion, $origen_id, $empresa_id, $modulo_id);
            echo json_encode($prev);
            break;

        case 'ejecutar_copia':
            $origen_id = intval($_POST['empresa_origen_id'] ?? 0);
            $componentesRaw = $_POST['componentes'] ?? [];
            if (is_string($componentesRaw)) {
                $componentesRaw = json_decode($componentesRaw, true) ?: [];
            }
            $res = inicioEjecutarCopia($conexion, $origen_id, $empresa_id, $componentesRaw, $usuario_id, $modulo_id);
            echo json_encode($res);
            break;

        case 'crear_sucursal_rapida':
            $res = inicioCrearSucursalRapida($conexion, $empresa_id, $usuario_id);
            echo json_encode($res);
            break;

        case 'asignar_accesos_globales':
            $res = inicioAsignarAccesosGlobales($conexion, $empresa_id);
            echo json_encode($res);
            break;

        case 'guardar_tipo_operacion':
            echo json_encode(inicioGuardarTipoOperacion($conexion, $empresa_id, (string) ($_POST['tipo_operacion'] ?? '')));
            break;

        case 'crear_perfiles_predefinidos':
            $perfil_ids = $_POST['perfil_ids'] ?? [];
            echo json_encode(inicioCrearPerfilesPredefinidos($conexion, $empresa_id, $modulo_id, is_array($perfil_ids) ? $perfil_ids : []));
            break;

        case 'quitar_funciones_bienes':
            $ep_ids = $_POST['empresa_perfil_ids'] ?? [];
            echo json_encode(inicioQuitarFuncionesBienes($conexion, $empresa_id, $modulo_id, is_array($ep_ids) ? $ep_ids : []));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Excepción en el servidor: ' . $e->getMessage()]);
}
