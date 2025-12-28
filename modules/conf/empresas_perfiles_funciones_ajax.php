<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir el modelo
require_once "empresas_perfiles_funciones_model.php";

// Crear conexión si no existe
if (!isset($conexion) || !$conexion) {
    $conexion = conectarBD();
}

// Determinar la acción (GET o POST)
$accion = $_GET['accion'] ?? ($_POST['accion'] ?? '');

// Establecer cabeceras para JSON
header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'obtener_modulos':
        $modulos = obtenerModulos($conexion);
        echo json_encode($modulos);
        break;
        
    case 'obtener_empresas':
        $empresas = obtenerEmpresas($conexion);
        echo json_encode($empresas);
        break;
        
    case 'obtener_empresas_perfiles_por_modulo':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        $empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : null;
        
        if ($empresa_id) {
            $empresasPerfiles = obtenerEmpresasPerfilesPorModuloYEmpresa($conexion, $modulo_id, $empresa_id);
        } else {
            $empresasPerfiles = obtenerEmpresasPerfilesPorModulo($conexion, $modulo_id);
        }
        
        echo json_encode($empresasPerfiles);
        break;
        
    case 'obtener_paginas_funciones_por_modulo':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        if ($modulo_id) {
            $paginasFunciones = obtenerPaginasFuncionesPorModulo($conexion, $modulo_id);
            echo json_encode($paginasFunciones);
        } else {
            echo json_encode([]);
        }
        break;
        
    case 'obtener_paginas_funciones_por_empresa_perfil':
        $empresa_perfil_id = isset($_GET['empresa_perfil_id']) ? intval($_GET['empresa_perfil_id']) : null;
        if ($empresa_perfil_id) {
            $paginasFunciones = obtenerPaginasFuncionesPorEmpresaPerfil($conexion, $empresa_perfil_id);
            echo json_encode($paginasFunciones);
        } else {
            echo json_encode([]);
        }
        break;
        
    case 'obtener_paginas_funciones_disponibles':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        if ($modulo_id) {
            $paginasFunciones = obtenerPaginasFuncionesDisponiblesPorModulo($conexion, $modulo_id);
            echo json_encode($paginasFunciones);
        } else {
            echo json_encode([]);
        }
        break;
        
    // NUEVAS ACCIONES PARA ESTRUCTURA JERÁRQUICA
    case 'obtener_estructura_paginas_por_modulo':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        if ($modulo_id) {
            $estructura = obtenerEstructuraPaginasPorModulo($conexion, $modulo_id);
            echo json_encode($estructura);
        } else {
            echo json_encode([]);
        }
        break;
        
    case 'obtener_estructura_completa_por_empresa_perfil':
        $empresa_perfil_id = isset($_GET['empresa_perfil_id']) ? intval($_GET['empresa_perfil_id']) : null;
        if ($empresa_perfil_id) {
            $estructura = obtenerEstructuraCompletaPorEmpresaPerfil($conexion, $empresa_perfil_id);
            echo json_encode($estructura);
        } else {
            echo json_encode([]);
        }
        break;
        
    case 'obtener_detalle_empresa_perfil':
        $empresa_perfil_id = isset($_GET['empresa_perfil_id']) ? intval($_GET['empresa_perfil_id']) : null;
        if ($empresa_perfil_id) {
            $detalle = obtenerDetalleEmpresaPerfil($conexion, $empresa_perfil_id);
            if ($detalle) {
                echo json_encode(['resultado' => true, 'datos' => $detalle]);
            } else {
                echo json_encode(['resultado' => false, 'mensaje' => 'Perfil de empresa no encontrado']);
            }
        } else {
            echo json_encode(['resultado' => false, 'mensaje' => 'ID de perfil no válido']);
        }
        break;
        
    case 'obtener_detalle_empresa_perfil_completo':
        $empresa_perfil_id = isset($_GET['empresa_perfil_id']) ? intval($_GET['empresa_perfil_id']) : null;
        if ($empresa_perfil_id) {
            $detalle = obtenerDetalleEmpresaPerfilCompleto($conexion, $empresa_perfil_id);
            if ($detalle) {
                echo json_encode(['resultado' => true, 'datos' => $detalle]);
            } else {
                echo json_encode(['resultado' => false, 'mensaje' => 'Perfil de empresa no encontrado']);
            }
        } else {
            echo json_encode(['resultado' => false, 'mensaje' => 'ID de perfil no válido']);
        }
        break;
        
    case 'obtener_empresa_id_por_perfil':
        $empresa_perfil_id = isset($_GET['empresa_perfil_id']) ? intval($_GET['empresa_perfil_id']) : null;
        if ($empresa_perfil_id) {
            $empresa_id = obtenerEmpresaIdPorPerfil($conexion, $empresa_perfil_id);
            echo json_encode(['resultado' => true, 'empresa_id' => $empresa_id]);
        } else {
            echo json_encode(['resultado' => false, 'empresa_id' => 0]);
        }
        break;
        
    case 'asignar_pagina_funcion_empresa_perfil':
        $empresa_id = intval($_POST['empresa_id']);
        $empresa_perfil_id = intval($_POST['empresa_perfil_id']);
        $pagina_funcion_id = intval($_POST['pagina_funcion_id']);
        $asignado = intval($_POST['asignado']);
        
        $resultado = asignarPaginaFuncionAEmpresaPerfil($conexion, $empresa_id, $empresa_perfil_id, $pagina_funcion_id, $asignado, 1);
        echo json_encode(['resultado' => $resultado, 'mensaje' => $resultado ? 'Función asignada correctamente' : 'Error al asignar la función']);
        break;
        
    case 'eliminar_pagina_funcion_empresa_perfil':
        $empresa_perfil_funcion_id = intval($_POST['empresa_perfil_funcion_id']);
        
        $resultado = eliminarPaginaFuncionDeEmpresaPerfil($conexion, $empresa_perfil_funcion_id);
        echo json_encode(['resultado' => $resultado, 'mensaje' => $resultado ? 'Función eliminada correctamente' : 'Error al eliminar la función']);
        break;
        
    case 'actualizar_asignacion_pagina_funcion':
        $empresa_perfil_funcion_id = intval($_POST['empresa_perfil_funcion_id']);
        $asignado = intval($_POST['asignado']);
        
        $resultado = actualizarAsignacionPaginaFuncion($conexion, $empresa_perfil_funcion_id, $asignado);
        echo json_encode(['resultado' => $resultado, 'mensaje' => $resultado ? 'Asignación actualizada correctamente' : 'Error al actualizar la asignación']);
        break;
        
    case 'heredar_paginas_funciones_desde_perfil_base':
        $empresa_perfil_id = intval($_POST['empresa_perfil_id']);
        $perfil_id_base = intval($_POST['perfil_id_base']);
        $empresa_id = intval($_POST['empresa_id']);
        
        $contador = heredarPaginasFuncionesDesdePerfilBase($conexion, $empresa_perfil_id, $perfil_id_base, $empresa_id);
        echo json_encode(['resultado' => true, 'mensaje' => "Se heredaron $contador funciones del módulo", 'contador' => $contador]);
        break;
        
    case 'asignar_multiple_paginas_funciones':
        $empresa_id = intval($_POST['empresa_id']);
        $empresa_perfil_id = intval($_POST['empresa_perfil_id']);
        $paginas_funciones_ids = isset($_POST['paginas_funciones_ids']) ? json_decode($_POST['paginas_funciones_ids']) : [];
        $asignado = intval($_POST['asignado']);
        
        $contador = 0;
        $errores = [];
        
        foreach ($paginas_funciones_ids as $pagina_funcion_id) {
            $resultado = asignarPaginaFuncionAEmpresaPerfil($conexion, $empresa_id, $empresa_perfil_id, $pagina_funcion_id, $asignado, 1);
            if ($resultado) {
                $contador++;
            } else {
                $errores[] = $pagina_funcion_id;
            }
        }
        
        echo json_encode([
            'resultado' => count($errores) == 0,
            'mensaje' => "Se asignaron $contador funciones correctamente",
            'contador' => $contador,
            'errores' => $errores
        ]);
        break;
        
    case 'eliminar_multiple_paginas_funciones':
        $empresa_perfil_id = intval($_POST['empresa_perfil_id']);
        $paginas_funciones_ids = isset($_POST['paginas_funciones_ids']) ? json_decode($_POST['paginas_funciones_ids']) : [];
        
        $contador = 0;
        $errores = [];
        
        foreach ($paginas_funciones_ids as $pagina_funcion_id) {
            // Primero obtener el ID de la asignación
            $sql_get = "SELECT empresa_perfil_funcion_id FROM conf__empresas_perfiles_funciones 
                       WHERE empresa_perfil_id = $empresa_perfil_id AND pagina_funcion_id = $pagina_funcion_id";
            $res_get = mysqli_query($conexion, $sql_get);
            
            if ($row = mysqli_fetch_assoc($res_get)) {
                $empresa_perfil_funcion_id = $row['empresa_perfil_funcion_id'];
                $resultado = eliminarPaginaFuncionDeEmpresaPerfil($conexion, $empresa_perfil_funcion_id);
                
                if ($resultado) {
                    $contador++;
                } else {
                    $errores[] = $pagina_funcion_id;
                }
            } else {
                $errores[] = $pagina_funcion_id;
            }
        }
        
        echo json_encode([
            'resultado' => count($errores) == 0,
            'mensaje' => "Se eliminaron $contador funciones correctamente",
            'contador' => $contador,
            'errores' => $errores
        ]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}

// Cerrar conexión si es necesario
if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>