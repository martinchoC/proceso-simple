<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once "comprobantes_grupos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 46); // Página ID para grupos de comprobantes
$pagina_subgrupos_idx = 47; // Página para subgrupos

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar_jerarquia':
            $grupos = obtenerComprobantesGrupos($conexion, $empresa_idx, $pagina_idx);
            $subgrupos = obtenerSubgruposAgrupadosPorGrupo($conexion, $empresa_idx, $pagina_subgrupos_idx);
            
            echo json_encode([
                'grupos' => $grupos,
                'subgrupos' => $subgrupos
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_grupos_select':
            $grupos = obtenerGruposParaSelect($conexion, $empresa_idx);
            echo json_encode($grupos, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_subgrupos':
            $comprobante_grupo_id = intval($_GET['comprobante_grupo_id'] ?? 0);
            if (empty($comprobante_grupo_id)) {
                echo json_encode(['error' => 'ID de grupo no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $subgrupos = obtenerComprobantesSubgrupos($conexion, $comprobante_grupo_id, $empresa_idx, $pagina_subgrupos_idx);
            echo json_encode($subgrupos, JSON_UNESCAPED_UNICODE);
            break;

       case 'listar_subgrupos_todos':
            $subgrupos = obtenerComprobantesSubgruposTodos(
                $conexion,
                $empresa_idx,
                $pagina_subgrupos_idx
            );
            echo json_encode($subgrupos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_grupo':
            $data = [
                'comprobante_grupo' => trim($_POST['comprobante_grupo'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 0),
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarComprobanteGrupo($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_grupo':
            $id = intval($_POST['comprobante_grupo_id'] ?? 0);
            $data = [
                'comprobante_grupo' => trim($_POST['comprobante_grupo'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 0),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarComprobanteGrupo($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_subgrupo':
            $data = [
                'comprobante_subgrupo' => trim($_POST['comprobante_subgrupo'] ?? ''),
                'comprobante_grupo_id' => intval($_POST['comprobante_grupo_id'] ?? 0),
                'orden' => intval($_POST['orden'] ?? 0),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = agregarComprobanteSubgrupo($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_subgrupo':
            $id = intval($_POST['comprobante_subgrupo_id'] ?? 0);
            $data = [
                'comprobante_subgrupo' => trim($_POST['comprobante_subgrupo'] ?? ''),
                'comprobante_grupo_id' => intval($_POST['comprobante_grupo_id'] ?? 0),
                'orden' => intval($_POST['orden'] ?? 0),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarComprobanteSubgrupo($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion_grupo':
            $comprobante_grupo_id = intval($_POST['comprobante_grupo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($comprobante_grupo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstadoGrupo($conexion, $comprobante_grupo_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion_subgrupo':
            $comprobante_subgrupo_id = intval($_POST['comprobante_subgrupo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($comprobante_subgrupo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstadoSubgrupo($conexion, $comprobante_subgrupo_id, $accion_js, $empresa_idx, $pagina_subgrupos_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_grupo':
            $id = intval($_POST['comprobante_grupo_id'] ?? $_GET['comprobante_grupo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $grupo = obtenerComprobanteGrupoPorId($conexion, $id, $empresa_idx);
            if ($grupo) {
                echo json_encode($grupo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Grupo de comprobante no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_subgrupo':
            $id = intval($_POST['comprobante_subgrupo_id'] ?? $_GET['comprobante_subgrupo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $subgrupo = obtenerComprobanteSubgrupoPorId($conexion, $id, $empresa_idx);
            if ($subgrupo) {
                echo json_encode($subgrupo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Subgrupo de comprobante no encontrado'], JSON_UNESCAPED_UNICODE);
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