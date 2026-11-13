<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['comprobantes_tipos.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "comprobantes_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 45);
// Solo una página de este módulo (si no, la de comprobantes_tipos.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 5, (int)$pagina_idx, 'comprobantes_tipos.php');

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            // Obtener parámetros de filtro
            $filtro_grupo = intval($_GET['filtro_grupo'] ?? 0);
            $filtro_subgrupo = intval($_GET['filtro_subgrupo'] ?? 0);
            $filtro_signo = $_GET['filtro_signo'] ?? '';
            $filtro_estado = intval($_GET['filtro_estado'] ?? 0);
            
            $tipos = obtenerComprobantesTipos($conexion, $empresa_idx, $pagina_idx, [
                'grupo_id' => $filtro_grupo,
                'subgrupo_id' => $filtro_subgrupo,
                'signo' => $filtro_signo,
                'estado_id' => $filtro_estado
            ]);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_grupos':
            $grupos = obtenerGruposComprobantes($conexion, $empresa_idx);
            echo json_encode($grupos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_subgrupos':
            $grupo_id = intval($_GET['comprobante_grupo_id'] ?? 0);
            $subgrupos = obtenerSubgruposComprobantes($conexion, $empresa_idx, $grupo_id);
            echo json_encode($subgrupos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_comprobantes_fiscales':
            $comprobantes = obtenerComprobantesFiscales($conexion, $empresa_idx);
            echo json_encode($comprobantes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_estados':
            $estados = obtenerEstadosDisponibles($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'comprobante_tipo' => trim($_POST['comprobante_tipo'] ?? ''),
                'comprobante_grupo_id' => intval($_POST['comprobante_grupo_id'] ?? 0),
                'comprobante_subgrupo_id' => intval($_POST['comprobante_subgrupo_id'] ?? 0),
                'comprobante_fiscal_id' => intval($_POST['comprobante_fiscal_id'] ?? 0),
                'letra' => trim($_POST['letra'] ?? ''),
                'signo' => $_POST['signo'] ?? '+',
                'orden' => intval($_POST['orden'] ?? 1),
                'comentario' => trim($_POST['comentario'] ?? ''),
                'impacta_stock' => intval($_POST['impacta_stock'] ?? 0),
                'stock_signo' => $_POST['stock_signo'] ?? null,
                'impacta_contabilidad' => intval($_POST['impacta_contabilidad'] ?? 0),
                'impacta_ctacte' => intval($_POST['impacta_ctacte'] ?? 0),
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarComprobanteTipo($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['comprobante_tipo_id'] ?? 0);
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'comprobante_tipo' => trim($_POST['comprobante_tipo'] ?? ''),
                'comprobante_grupo_id' => intval($_POST['comprobante_grupo_id'] ?? 0),
                'comprobante_subgrupo_id' => intval($_POST['comprobante_subgrupo_id'] ?? 0),
                'comprobante_fiscal_id' => intval($_POST['comprobante_fiscal_id'] ?? 0),
                'letra' => trim($_POST['letra'] ?? ''),
                'signo' => $_POST['signo'] ?? '+',
                'orden' => intval($_POST['orden'] ?? 1),
                'comentario' => trim($_POST['comentario'] ?? ''),
                'impacta_stock' => intval($_POST['impacta_stock'] ?? 0),
                'stock_signo' => $_POST['stock_signo'] ?? null,
                'impacta_contabilidad' => intval($_POST['impacta_contabilidad'] ?? 0),
                'impacta_ctacte' => intval($_POST['impacta_ctacte'] ?? 0),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarComprobanteTipo($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $comprobante_tipo_id = intval($_POST['comprobante_tipo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($comprobante_tipo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $comprobante_tipo_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['comprobante_tipo_id'] ?? $_GET['comprobante_tipo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $tipo = obtenerComprobanteTipoPorId($conexion, $id, $empresa_idx);
            if ($tipo) {
                echo json_encode($tipo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Tipo de comprobante no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;
        
        // Función "Copiar": el formulario llega precargado con el original y editado por el usuario
        case 'copiar':
            $pagina_copia = pagina_id_por_url($conexion, 5, 'comprobantes_tipos.php');
            if (!usuario_puede_funcion($conexion, intval($ctx['usuario_id']), $empresa_idx, $pagina_copia, 'copiar')) {
                http_response_code(403);
                echo json_encode(['resultado' => false, 'error' => 'No tiene permiso para copiar tipos de comprobante.'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $id = intval($_POST['copia_de_id'] ?? 0);
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'comprobante_tipo' => trim($_POST['comprobante_tipo'] ?? ''),
                'comprobante_grupo_id' => intval($_POST['comprobante_grupo_id'] ?? 0),
                'comprobante_subgrupo_id' => intval($_POST['comprobante_subgrupo_id'] ?? 0),
                'comprobante_fiscal_id' => intval($_POST['comprobante_fiscal_id'] ?? 0),
                'letra' => trim($_POST['letra'] ?? ''),
                'signo' => $_POST['signo'] ?? '+',
                'orden' => intval($_POST['orden'] ?? 1),
                'comentario' => trim($_POST['comentario'] ?? ''),
                'impacta_stock' => intval($_POST['impacta_stock'] ?? 0),
                'stock_signo' => $_POST['stock_signo'] ?? null,
                'impacta_contabilidad' => intval($_POST['impacta_contabilidad'] ?? 0),
                'impacta_ctacte' => intval($_POST['impacta_ctacte'] ?? 0),
                'copiar_relaciones' => intval($_POST['copiar_relaciones'] ?? 0),
                'copiar_puntos_venta' => intval($_POST['copiar_puntos_venta'] ?? 0),
                'empresa_idx' => $empresa_idx
            ];

            if (empty($id)) {
                echo json_encode(['resultado' => false, 'error' => 'ID del tipo original no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = copiarComprobanteTipo($conexion, $id, $data, intval($ctx['usuario_id']));
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        // Función "Relaciones": qué tipos cancela y de qué comprobante padre depende (comprobantes_tipos_relaciones_model.php)
        case 'relaciones_obtener':
        case 'relaciones_guardar':
            require_once __DIR__ . '/comprobantes_tipos_relaciones_model.php';
            $pagina_rel = pagina_id_por_url($conexion, 5, 'comprobantes_tipos.php');
            if (!usuario_puede_funcion($conexion, intval($ctx['usuario_id']), $empresa_idx, $pagina_rel, 'relaciones')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para las relaciones entre tipos.'], JSON_UNESCAPED_UNICODE);
                break;
            }
            try {
                $tipo_id = intval($_GET['comprobante_tipo_id'] ?? $_POST['comprobante_tipo_id'] ?? 0);
                if ($accion === 'relaciones_obtener') {
                    echo json_encode(['success' => true, 'data' => ctrObtener($conexion, $empresa_idx, $tipo_id)], JSON_UNESCAPED_UNICODE);
                } else {
                    $cancela = json_decode($_POST['cancela'] ?? '[]', true);
                    $padres = json_decode($_POST['padres'] ?? '[]', true);
                    [$altas, $bajas] = ctrGuardar($conexion, $empresa_idx, intval($ctx['usuario_id']), $tipo_id,
                                                  is_array($cancela) ? $cancela : [], is_array($padres) ? $padres : []);
                    echo json_encode(['success' => true, 'data' => ['altas' => $altas, 'bajas' => $bajas],
                                      'message' => "Relaciones guardadas ($altas cambios, $bajas quitadas)."], JSON_UNESCAPED_UNICODE);
                }
            } catch (Exception $ex) {
                echo json_encode(['success' => false, 'message' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
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