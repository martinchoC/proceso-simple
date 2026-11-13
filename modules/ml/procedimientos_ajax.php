<?php
/**
 * Controlador AJAX para Procedimientos y Soporte (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/procedimientos_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/procedimientos_model.php';
require_once __DIR__ . '/normas_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 315; // Procedimientos y Soporte

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 315
$funciones_permitidas = [];
if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
    $sql_f = "SELECT pagina_funcion_id, accion_js, nombre_funcion 
              FROM conf__paginas_funciones 
              WHERE pagina_id = ? AND tabla_estado_registro_id = 1";
    $stmt_f = mysqli_prepare($conexion, $sql_f);
    mysqli_stmt_bind_param($stmt_f, "i", $pagina_idx);
    mysqli_stmt_execute($stmt_f);
    $res_f = mysqli_stmt_get_result($stmt_f);
    while ($rf = mysqli_fetch_assoc($res_f)) {
        $funciones_permitidas[$rf['accion_js']] = $rf;
    }
    mysqli_stmt_close($stmt_f);
} else {
    $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_idx);
    if (!empty($perfiles)) {
        $perfil_ids = array_column($perfiles, 'empresa_perfil_id');
        $placeholders = implode(',', array_fill(0, count($perfil_ids), '?'));
        $sql_f = "SELECT DISTINCT pf.pagina_funcion_id, pf.accion_js, pf.nombre_funcion
                  FROM conf__paginas_funciones pf
                  INNER JOIN conf__empresas_perfiles_funciones epf 
                      ON epf.pagina_funcion_id = pf.pagina_funcion_id 
                      AND epf.empresa_perfil_id IN ($placeholders)
                  WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_id = 1 
                    AND epf.asignado = 1";
        $stmt_f = mysqli_prepare($conexion, $sql_f);
        $types = str_repeat('i', count($perfil_ids)) . 'i';
        $params_f = array_merge($perfil_ids, [$pagina_idx]);
        mysqli_stmt_bind_param($stmt_f, $types, ...$params_f);
        mysqli_stmt_execute($stmt_f);
        $res_f = mysqli_stmt_get_result($stmt_f);
        while ($rf = mysqli_fetch_assoc($res_f)) {
            $funciones_permitidas[$rf['accion_js']] = $rf;
        }
        mysqli_stmt_close($stmt_f);
    }
}

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    case 'combos':
        $normas = normas_listar($conexion, ['solo_vigentes' => true]);
        $q_e = mysqli_query($conexion, "SELECT empresa_id, empresa FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
        $empresas = [];
        while ($r = mysqli_fetch_assoc($q_e)) $empresas[] = $r;

        $tipos_documento = procedimientos_tipos_listar($conexion);

        // Usuarios activos para asignar como responsables del procedimiento
        $q_u = mysqli_query($conexion, "SELECT DISTINCT u.usuario_id, u.usuario, u.usuario_nombre 
                                        FROM conf__usuarios u
                                        INNER JOIN conf__usuarios_perfiles up ON up.usuario_id = u.usuario_id
                                        INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
                                        WHERE (ep.empresa_id = {$empresa_idx} OR {$empresa_idx} = 0)
                                          AND u.tabla_estado_registro_id = 1
                                        ORDER BY u.usuario_nombre ASC");
        $usuarios = [];
        while ($ru = mysqli_fetch_assoc($q_u)) {
            $usuarios[] = [
                'usuario_id' => (int)$ru['usuario_id'],
                'nombre' => $ru['usuario_nombre'] ?: $ru['usuario']
            ];
        }

        $areas_sugeridas = [
            'Impuestos y Finanzas',
            'Operaciones y Almacenaje',
            'Logística y Despacho',
            'Seguridad, Higiene y MA (HSE)',
            'Mantenimiento e Integridad Mecánica',
            'Calidad y Procesos',
            'Recursos Humanos y Nómina',
            'Legales y Compliance Corporativo'
        ];

        echo json_encode([
            'success' => true,
            'data' => [
                'normas' => $normas,
                'empresas' => $empresas,
                'tipos_documento' => $tipos_documento,
                'usuarios' => $usuarios,
                'areas_sugeridas' => $areas_sugeridas,
                'permite_guardar' => isset($funciones_permitidas['guardar_procedimiento']),
                'permite_aprobar' => isset($funciones_permitidas['aprobar_procedimiento'])
            ]
        ]);
        break;

    case 'listar':
        $estado = intval($_GET['filtro_estado'] ?? 0);
        $tipo_id = intval($_GET['filtro_tipo'] ?? 0);
        $lista = procedimientos_listar($conexion, $empresa_idx, $estado, $tipo_id);

        $permite_guardar = isset($funciones_permitidas['guardar_procedimiento']);
        $permite_aprobar = isset($funciones_permitidas['aprobar_procedimiento']);

        foreach ($lista as &$p) {
            $est = intval($p['tabla_estado_registro_id']);
            $p['permite_editar'] = $permite_guardar && $est === 2053;
            $p['permite_aprobar'] = $permite_aprobar && $est === 2054;
            $p['permite_enviar'] = $permite_guardar && $est === 2053;
        }
        unset($p);

        echo json_encode([
            'success' => true,
            'data' => $lista,
            'permisos' => [
                'permite_guardar' => $permite_guardar,
                'permite_aprobar' => $permite_aprobar
            ]
        ]);
        break;

    case 'obtener':
        $proc_id = intval($_GET['procedimiento_id'] ?? 0);
        $item = procedimiento_obtener($conexion, $proc_id);
        if ($item) {
            echo json_encode(['success' => true, 'data' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Procedimiento no encontrado.']);
        }
        break;

    case 'guardar':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para guardar procedimientos.']);
            exit;
        }
        $archivo = isset($_FILES['archivo_adjunto']) && $_FILES['archivo_adjunto']['error'] === UPLOAD_ERR_OK ? $_FILES['archivo_adjunto'] : null;
        $res = procedimiento_guardar($conexion, $_POST, $usuario_id, $archivo);
        echo json_encode($res);
        break;

    case 'cambiar_estado':
        $procedimiento_id = intval($_POST['procedimiento_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);

        if ($nuevo_estado === 2055 && !isset($funciones_permitidas['aprobar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para aprobar procedimientos.']);
            exit;
        }

        $res = procedimiento_cambiar_estado($conexion, $procedimiento_id, $nuevo_estado, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_versiones':
        $procedimiento_id = intval($_GET['procedimiento_id'] ?? 0);
        $versiones = procedimiento_versiones_listar($conexion, $procedimiento_id);
        echo json_encode(['success' => true, 'data' => $versiones]);
        break;

    case 'guardar_version':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para agregar versiones.']);
            exit;
        }
        $procedimiento_id = intval($_POST['procedimiento_id'] ?? 0);
        $archivo = isset($_FILES['archivo_version']) && $_FILES['archivo_version']['error'] === UPLOAD_ERR_OK ? $_FILES['archivo_version'] : null;
        $res = procedimiento_version_crear($conexion, $procedimiento_id, $_POST, $usuario_id, $archivo);
        echo json_encode($res);
        break;

    case 'listar_instrucciones':
        $version_id = intval($_GET['procedimiento_version_id'] ?? 0);
        $instrucciones = procedimiento_instrucciones_listar($conexion, $version_id);
        echo json_encode(['success' => true, 'data' => $instrucciones]);
        break;

    case 'guardar_instruccion':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para agregar instrucciones.']);
            exit;
        }
        $res = procedimiento_instruccion_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar_instruccion':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar instrucciones.']);
            exit;
        }
        $ins_id = intval($_POST['instruccion_id'] ?? 0);
        $res = procedimiento_instruccion_eliminar($conexion, $ins_id);
        echo json_encode($res);
        break;

    // --- Puntos 1 y 2: Documentos Asociados / Jerarquía ---
    case 'listar_documentos_asociados':
        $proc_id = intval($_GET['procedimiento_id'] ?? 0);
        $docs = procedimiento_documentos_listar($conexion, $proc_id);
        echo json_encode(['success' => true, 'data' => $docs]);
        break;

    case 'candidatos_documentos_asociados':
        $proc_id = intval($_GET['procedimiento_id'] ?? 0);
        $cands = procedimiento_documentos_candidatos($conexion, $proc_id, $empresa_idx);
        echo json_encode(['success' => true, 'data' => $cands]);
        break;

    case 'vincular_documento':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para vincular documentos.']);
            exit;
        }
        $padre_id = intval($_POST['procedimiento_padre_id'] ?? 0);
        $doc_id = intval($_POST['documento_asociado_id'] ?? 0);
        $tipo_rel = trim($_POST['tipo_relacion'] ?? 'anexo');
        $oblig = !empty($_POST['obligatorio']) ? 1 : 0;
        $notas = trim($_POST['notas'] ?? '');
        $res = procedimiento_documento_vincular($conexion, $padre_id, $doc_id, $tipo_rel, $oblig, $notas, $usuario_id);
        echo json_encode($res);
        break;

    case 'desvincular_documento':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para desvincular documentos.']);
            exit;
        }
        $proc_doc_id = intval($_POST['procedimiento_documento_id'] ?? 0);
        $res = procedimiento_documento_desvincular($conexion, $proc_doc_id, $usuario_id);
        echo json_encode($res);
        break;

    // --- Punto 3: Workflow de Aprobación y Firmas Electrónicas ---
    case 'solicitar_aprobacion':
        $proc_id = intval($_POST['procedimiento_id'] ?? 0);
        $nota = trim($_POST['nota'] ?? '');
        $res = procedimiento_solicitar_aprobacion($conexion, $proc_id, $usuario_id, $nota);
        echo json_encode($res);
        break;

    case 'aprobar_formal':
        if (!isset($funciones_permitidas['aprobar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para aprobar procedimientos.']);
            exit;
        }
        $proc_id = intval($_POST['procedimiento_id'] ?? 0);
        $ver_id = intval($_POST['procedimiento_version_id'] ?? 0);
        $rol_firmante = trim($_POST['rol_firmante'] ?? 'Aprobador de Compliance');
        $obs = trim($_POST['observaciones'] ?? '');
        $res = procedimiento_aprobar_formal($conexion, $proc_id, $ver_id, $usuario_id, $rol_firmante, $obs);
        echo json_encode($res);
        break;

    case 'rechazar_formal':
        $proc_id = intval($_POST['procedimiento_id'] ?? 0);
        $ver_id = intval($_POST['procedimiento_version_id'] ?? 0);
        $motivo = trim($_POST['motivo_rechazo'] ?? '');
        $res = procedimiento_rechazar_formal($conexion, $proc_id, $ver_id, $usuario_id, $motivo);
        echo json_encode($res);
        break;

    case 'listar_aprobaciones':
        $proc_id = intval($_GET['procedimiento_id'] ?? 0);
        $aprs = procedimiento_aprobaciones_listar($conexion, $proc_id);
        echo json_encode(['success' => true, 'data' => $aprs]);
        break;

    // --- Punto 4: Publicación, Difusión y Toma de Conocimiento ---
    case 'registrar_lectura':
        $ver_id = intval($_POST['procedimiento_version_id'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');
        $res = procedimiento_lectura_registrar($conexion, $ver_id, $usuario_id, $comentario);
        echo json_encode($res);
        break;

    case 'listar_lecturas':
        $ver_id = intval($_GET['procedimiento_version_id'] ?? 0);
        $res = procedimiento_lecturas_listar($conexion, $ver_id, $empresa_idx);
        echo json_encode(['success' => true, 'data' => $res]);
        break;

    // --- Punto 6: Obsolescencia y Baja Controlada ---
    case 'marcar_obsoleto':
        if (!isset($funciones_permitidas['aprobar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para dar de baja procedimientos.']);
            exit;
        }
        $proc_id = intval($_POST['procedimiento_id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $res = procedimiento_marcar_obsoleto($conexion, $proc_id, $usuario_id, $motivo);
        echo json_encode($res);
        break;

    // --- Punto 7: Pista de Auditoría ---
    case 'listar_logs':
        $proc_id = intval($_GET['procedimiento_id'] ?? 0);
        $logs = procedimiento_logs_listar($conexion, $proc_id);
        echo json_encode(['success' => true, 'data' => $logs]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción '{$accion}' no reconocida."]);
        break;
}
