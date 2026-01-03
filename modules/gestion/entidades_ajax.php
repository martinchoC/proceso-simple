<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "entidades_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $entidades = obtenerEntidades($conexion);
        echo json_encode($entidades);
        break;
    
    case 'agregar':
        $data = [
            'nombre_fiscal' => $_POST['nombre_fiscal'] ?? '',
            'nombre_fantasia' => $_POST['nombre_fantasia'] ?? '',
            'entidad_tipo_id' => $_POST['entidad_tipo_id'] ?? 0,
            'cuit' => $_POST['cuit'] ?? '',
            'sitio_web' => $_POST['sitio_web'] ?? '',
            'domicilio_legal' => $_POST['domicilio_legal'] ?? '',
            'localidad_id' => $_POST['localidad_id'] ?? 0,
            'observaciones' => $_POST['observaciones'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1
        ];
        
        if (empty($data['nombre_fiscal']) || empty($data['cuit'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre fiscal y CUIT son obligatorios']);
            break;
        }
        
        $resultado = agregarEntidad($conexion, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe una entidad con ese CUIT']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['entidad_id']);
        $data = [
            'nombre_fiscal' => $_POST['nombre_fiscal'] ?? '',
            'nombre_fantasia' => $_POST['nombre_fantasia'] ?? '',
            'entidad_tipo_id' => $_POST['entidad_tipo_id'] ?? 0,
            'cuit' => $_POST['cuit'] ?? '',
            'sitio_web' => $_POST['sitio_web'] ?? '',
            'domicilio_legal' => $_POST['domicilio_legal'] ?? '',
            'localidad_id' => $_POST['localidad_id'] ?? 0,
            'observaciones' => $_POST['observaciones'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1
        ];
        
        if (empty($data['nombre_fiscal']) || empty($data['cuit'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre fiscal y CUIT son obligatorios']);
            break;
        }
        
        $resultado = editarEntidad($conexion, $id, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe una entidad con ese CUIT']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['entidad_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoEntidad($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['entidad_id']);
        $resultado = eliminarEntidad($conexion, $id);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar la entidad porque tiene sucursales, condiciones fiscales o roles asociados']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['entidad_id']);
        $entidad = obtenerEntidadPorId($conexion, $id);
        echo json_encode($entidad);
        break;

    case 'obtener_datos_auxiliares':
        $id = intval($_GET['entidad_id']);
        $sucursales = obtenerSucursalesPorEntidad($conexion, $id);
        $condiciones = obtenerCondicionesFiscalesPorEntidad($conexion, $id);
        $roles = obtenerRolesPorEntidad($conexion, $id);
        
        echo json_encode([
            'sucursales' => $sucursales,
            'condiciones_fiscales' => $condiciones,
            'roles' => $roles
        ]);
        break;

    case 'obtener_maestras':
        $tipos = obtenerTiposEntidad($conexion);
        $condiciones = obtenerCondicionesFiscales($conexion);
        $roles = obtenerRolesEntidades($conexion);
        $localidades = obtenerLocalidades($conexion);
        
        echo json_encode([
            'tipos_entidad' => $tipos,
            'condiciones_fiscales' => $condiciones,
            'roles_entidades' => $roles,
            'localidades' => $localidades
        ]);
        break;

    // Nuevas acciones para gestionar sucursales
    case 'agregar_sucursal':
        $data = [
            'entidad_id' => $_POST['entidad_id'] ?? 0,
            'sucursal_nombre' => $_POST['sucursal_nombre'] ?? '',
            'sucursal_direccion' => $_POST['sucursal_direccion'] ?? '',
            'sucursal_telefono' => $_POST['sucursal_telefono'] ?? '',
            'sucursal_email' => $_POST['sucursal_email'] ?? '',
            'sucursal_contacto' => $_POST['sucursal_contacto'] ?? '',
            'localidad_id' => $_POST['localidad_id'] ?? 0
        ];
        
        if (empty($data['sucursal_nombre']) || empty($data['entidad_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de sucursal y entidad son obligatorios']);
            break;
        }
        
        $resultado = agregarSucursal($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_sucursal':
        $sucursal_id = intval($_POST['sucursal_id']);
        $data = [
            'sucursal_nombre' => $_POST['sucursal_nombre'] ?? '',
            'sucursal_direccion' => $_POST['sucursal_direccion'] ?? '',
            'sucursal_telefono' => $_POST['sucursal_telefono'] ?? '',
            'sucursal_email' => $_POST['sucursal_email'] ?? '',
            'sucursal_contacto' => $_POST['sucursal_contacto'] ?? '',
            'localidad_id' => $_POST['localidad_id'] ?? 0
        ];
        
        if (empty($data['sucursal_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de sucursal es obligatorio']);
            break;
        }
        
        $resultado = editarSucursal($conexion, $sucursal_id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar_sucursal':
        $sucursal_id = intval($_GET['sucursal_id']);
        $resultado = eliminarSucursal($conexion, $sucursal_id);
        echo json_encode(['resultado' => $resultado]);
        break;

    // Nuevas acciones para gestionar condiciones fiscales
    case 'agregar_condicion':
        $data = [
            'entidad_id' => $_POST['entidad_id'] ?? 0,
            'condicion_fiscal_id' => $_POST['condicion_fiscal_id'] ?? 0,
            'f_desde' => $_POST['f_desde'] ?? '',
            'f_hasta' => $_POST['f_hasta'] ?? null
        ];
        
        if (empty($data['condicion_fiscal_id']) || empty($data['f_desde']) || empty($data['entidad_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Condición fiscal, fecha desde y entidad son obligatorios']);
            break;
        }
        
        $resultado = agregarCondicionFiscal($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_condicion':
        $entidad_condicion_fiscal_id = intval($_POST['entidad_condicion_fiscal_id']);
        $data = [
            'condicion_fiscal_id' => $_POST['condicion_fiscal_id'] ?? 0,
            'f_desde' => $_POST['f_desde'] ?? '',
            'f_hasta' => $_POST['f_hasta'] ?? null
        ];
        
        if (empty($data['condicion_fiscal_id']) || empty($data['f_desde'])) {
            echo json_encode(['resultado' => false, 'error' => 'Condición fiscal y fecha desde son obligatorios']);
            break;
        }
        
        $resultado = editarCondicionFiscal($conexion, $entidad_condicion_fiscal_id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar_condicion':
        $entidad_condicion_fiscal_id = intval($_GET['entidad_condicion_fiscal_id']);
        $resultado = eliminarCondicionFiscal($conexion, $entidad_condicion_fiscal_id);
        echo json_encode(['resultado' => $resultado]);
        break;

    // Nuevas acciones para gestionar roles
    case 'agregar_rol':
        $data = [
            'entidad_id' => $_POST['entidad_id'] ?? 0,
            'rol_entidad_id' => $_POST['rol_entidad_id'] ?? 0,
            'f_alta' => $_POST['f_alta'] ?? '',
            'f_baja' => $_POST['f_baja'] ?? null
        ];
        
        if (empty($data['rol_entidad_id']) || empty($data['f_alta']) || empty($data['entidad_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Rol, fecha alta y entidad son obligatorios']);
            break;
        }
        
        $resultado = agregarRol($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_rol':
        $entidad_rol_id = intval($_POST['entidad_rol_id']);
        $data = [
            'rol_entidad_id' => $_POST['rol_entidad_id'] ?? 0,
            'f_alta' => $_POST['f_alta'] ?? '',
            'f_baja' => $_POST['f_baja'] ?? null
        ];
        
        if (empty($data['rol_entidad_id']) || empty($data['f_alta'])) {
            echo json_encode(['resultado' => false, 'error' => 'Rol y fecha alta son obligatorios']);
            break;
        }
        
        $resultado = editarRol($conexion, $entidad_rol_id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar_rol':
        $entidad_rol_id = intval($_GET['entidad_rol_id']);
        $resultado = eliminarRol($conexion, $entidad_rol_id);
        echo json_encode(['resultado' => $resultado]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>