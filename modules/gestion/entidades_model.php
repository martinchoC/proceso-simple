<?php
require_once "conexion.php";

function obtenerEntidades($conexion) {
    $sql = "SELECT 
                gestion__entidades.*,
                gestion__entidades_tipos.entidad_tipo,
                conf__localidades.localidad,
                gestion__roles_entidades.rol_entidad_nombre
            FROM `gestion__entidades`
            LEFT JOIN gestion__entidades_tipos ON gestion__entidades.entidad_tipo_id = gestion__entidades_tipos.entidad_tipo_id
            LEFT JOIN conf__localidades ON gestion__entidades.localidad_id = conf__localidades.localidad_id
            LEFT JOIN gestion__entidades_roles ON gestion__entidades.entidad_id = gestion__entidades_roles.entidad_id AND gestion__entidades_roles.f_baja IS NULL
            LEFT JOIN gestion__roles_entidades ON gestion__entidades_roles.rol_entidad_id = gestion__roles_entidades.rol_entidad_id
            WHERE gestion__entidades.estado_registro_id = 1
            ORDER BY gestion__entidades.nombre_fiscal";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEntidadPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM `gestion__entidades` WHERE entidad_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function agregarEntidad($conexion, $data) {
    // Validaciones básicas
    if (empty($data['nombre_fiscal']) || empty($data['cuit'])) {
        return false;
    }

    // Escapar datos
    $nombre_fiscal = mysqli_real_escape_string($conexion, $data['nombre_fiscal']);
    $nombre_fantasia = mysqli_real_escape_string($conexion, $data['nombre_fantasia'] ?? '');
    $cuit = mysqli_real_escape_string($conexion, $data['cuit']);
    $sitio_web = mysqli_real_escape_string($conexion, $data['sitio_web'] ?? '');
    $domicilio_legal = mysqli_real_escape_string($conexion, $data['domicilio_legal'] ?? '');
    $observaciones = mysqli_real_escape_string($conexion, $data['observaciones'] ?? '');
    
    $entidad_tipo_id = intval($data['entidad_tipo_id'] ?? 0);
    $localidad_id = intval($data['localidad_id'] ?? 0);
    $estado_registro_id = intval($data['estado_registro_id'] ?? 1);

    // Verificar si ya existe el CUIT
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__entidades` WHERE cuit = '$cuit'";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe_cuit = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe_cuit > 0) {
        return false;
    }

    $sql = "INSERT INTO `gestion__entidades` 
            (empresa_id, nombre_fiscal, nombre_fantasia, entidad_tipo_id, cuit, sitio_web, domicilio_legal, localidad_id, observaciones, estado_registro_id) 
            VALUES 
            (2, '$nombre_fiscal', '$nombre_fantasia', $entidad_tipo_id, '$cuit', '$sitio_web', '$domicilio_legal', $localidad_id, '$observaciones', $estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarEntidad($conexion, $id, $data) {
    if (empty($data['nombre_fiscal']) || empty($data['cuit'])) {
        return false;
    }

    $id = intval($id);
    $nombre_fiscal = mysqli_real_escape_string($conexion, $data['nombre_fiscal']);
    $nombre_fantasia = mysqli_real_escape_string($conexion, $data['nombre_fantasia'] ?? '');
    $cuit = mysqli_real_escape_string($conexion, $data['cuit']);
    $sitio_web = mysqli_real_escape_string($conexion, $data['sitio_web'] ?? '');
    $domicilio_legal = mysqli_real_escape_string($conexion, $data['domicilio_legal'] ?? '');
    $observaciones = mysqli_real_escape_string($conexion, $data['observaciones'] ?? '');
    
    $entidad_tipo_id = intval($data['entidad_tipo_id'] ?? 0);
    $localidad_id = intval($data['localidad_id'] ?? 0);
    $estado_registro_id = intval($data['estado_registro_id'] ?? 1);

    // Verificar si ya existe el CUIT (excluyendo el actual)
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__entidades` 
                  WHERE cuit = '$cuit' AND entidad_id != $id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe_cuit = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe_cuit > 0) {
        return false;
    }

    $sql = "UPDATE `gestion__entidades` SET
            nombre_fiscal = '$nombre_fiscal',
            nombre_fantasia = '$nombre_fantasia',
            entidad_tipo_id = $entidad_tipo_id,
            cuit = '$cuit',
            sitio_web = '$sitio_web',
            domicilio_legal = '$domicilio_legal',
            localidad_id = $localidad_id,
            observaciones = '$observaciones',
            estado_registro_id = $estado_registro_id
            WHERE entidad_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoEntidad($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE `gestion__entidades` 
            SET estado_registro_id = $nuevo_estado 
            WHERE entidad_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarEntidad($conexion, $id) {
    $id = intval($id);
    
    // Verificar relaciones antes de eliminar
    $sql_check = "SELECT 
        (SELECT COUNT(*) FROM gestion__entidades_sucursales WHERE entidad_id = $id) as sucursales,
        (SELECT COUNT(*) FROM gestion__entidades_condiciones_fiscales WHERE entidad_id = $id) as condiciones,
        (SELECT COUNT(*) FROM gestion__entidades_roles WHERE entidad_id = $id) as roles";
    
    $res_check = mysqli_query($conexion, $sql_check);
    $relaciones = mysqli_fetch_assoc($res_check);
    
    if ($relaciones['sucursales'] > 0 || $relaciones['condiciones'] > 0 || $relaciones['roles'] > 0) {
        return false; // Tiene relaciones, no se puede eliminar
    }
    
    $sql = "DELETE FROM `gestion__entidades` WHERE entidad_id = $id";
    return mysqli_query($conexion, $sql);
}

// Funciones para datos auxiliares (sucursales, condiciones fiscales, roles)
function obtenerSucursalesPorEntidad($conexion, $entidad_id) {
    $entidad_id = intval($entidad_id);
    $sql = "SELECT gestion__entidades_sucursales.*, conf__localidades.localidad 
            FROM gestion__entidades_sucursales
            LEFT JOIN conf__localidades ON gestion__entidades_sucursales.localidad_id = conf__localidades.localidad_id
            WHERE gestion__entidades_sucursales.entidad_id = $entidad_id AND gestion__entidades_sucursales.estado_registro_id = 1";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerCondicionesFiscalesPorEntidad($conexion, $entidad_id) {
    $entidad_id = intval($entidad_id);
    $sql = "SELECT gestion__entidades_condiciones_fiscales.*, gestion__condiciones_fiscales.condicion_fiscal, gestion__condiciones_fiscales.condicion_fiscal_codigo
            FROM gestion__entidades_condiciones_fiscales
            JOIN gestion__condiciones_fiscales ON gestion__entidades_condiciones_fiscales.condicion_fiscal_id = gestion__condiciones_fiscales.condicion_fiscal_id
            WHERE gestion__entidades_condiciones_fiscales.entidad_id = $entidad_id AND gestion__entidades_condiciones_fiscales.estado_registro_id = 1
            ORDER BY gestion__entidades_condiciones_fiscales.f_desde DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerRolesPorEntidad($conexion, $entidad_id) {
    $entidad_id = intval($entidad_id);
    $sql = "SELECT gestion__entidades_roles.*, gestion__roles_entidades.rol_entidad_nombre
            FROM gestion__entidades_roles
            JOIN gestion__roles_entidades ON gestion__entidades_roles.rol_entidad_id = gestion__roles_entidades.rol_entidad_id
            WHERE gestion__entidades_roles.entidad_id = $entidad_id
            ORDER BY gestion__entidades_roles.f_alta DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Funciones para obtener datos de tablas maestras
function obtenerTiposEntidad($conexion) {
    $sql = "SELECT * FROM gestion__entidades_tipos WHERE estado_registro_id = 1 ORDER BY entidad_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerCondicionesFiscales($conexion) {
    $sql = "SELECT * FROM gestion__condiciones_fiscales WHERE estado_registro_id = 1 ORDER BY condicion_fiscal";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerRolesEntidades($conexion) {
    $sql = "SELECT * FROM gestion__roles_entidades ORDER BY rol_entidad_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerLocalidades($conexion) {
    $sql = "SELECT * FROM conf__localidades WHERE estado_registro_id = 1 ORDER BY localidad";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Funciones para gestionar sucursales
function agregarSucursal($conexion, $data) {
    $entidad_id = intval($data['entidad_id']);
    $sucursal_nombre = mysqli_real_escape_string($conexion, $data['sucursal_nombre']);
    $sucursal_direccion = mysqli_real_escape_string($conexion, $data['sucursal_direccion'] ?? '');
    $sucursal_telefono = mysqli_real_escape_string($conexion, $data['sucursal_telefono'] ?? '');
    $sucursal_email = mysqli_real_escape_string($conexion, $data['sucursal_email'] ?? '');
    $sucursal_contacto = mysqli_real_escape_string($conexion, $data['sucursal_contacto'] ?? '');
    $localidad_id = intval($data['localidad_id'] ?? 0);
    
    $sql = "INSERT INTO gestion__entidades_sucursales 
            (entidad_id, sucursal_nombre, sucursal_direccion, localidad_id, sucursal_telefono, sucursal_email, sucursal_contacto, estado_registro_id) 
            VALUES 
            ($entidad_id, '$sucursal_nombre', '$sucursal_direccion', $localidad_id, '$sucursal_telefono', '$sucursal_email', '$sucursal_contacto', 1)";
    
    return mysqli_query($conexion, $sql);
}

function editarSucursal($conexion, $sucursal_id, $data) {
    $sucursal_id = intval($sucursal_id);
    $sucursal_nombre = mysqli_real_escape_string($conexion, $data['sucursal_nombre']);
    $sucursal_direccion = mysqli_real_escape_string($conexion, $data['sucursal_direccion'] ?? '');
    $sucursal_telefono = mysqli_real_escape_string($conexion, $data['sucursal_telefono'] ?? '');
    $sucursal_email = mysqli_real_escape_string($conexion, $data['sucursal_email'] ?? '');
    $sucursal_contacto = mysqli_real_escape_string($conexion, $data['sucursal_contacto'] ?? '');
    $localidad_id = intval($data['localidad_id'] ?? 0);
    
    $sql = "UPDATE gestion__entidades_sucursales SET
            sucursal_nombre = '$sucursal_nombre',
            sucursal_direccion = '$sucursal_direccion',
            localidad_id = $localidad_id,
            sucursal_telefono = '$sucursal_telefono',
            sucursal_email = '$sucursal_email',
            sucursal_contacto = '$sucursal_contacto'
            WHERE sucursal_id = $sucursal_id";
    
    return mysqli_query($conexion, $sql);
}

function eliminarSucursal($conexion, $sucursal_id) {
    $sucursal_id = intval($sucursal_id);
    
    $sql = "UPDATE gestion__entidades_sucursales 
            SET estado_registro_id = 0 
            WHERE sucursal_id = $sucursal_id";
    return mysqli_query($conexion, $sql);
}

// Funciones para gestionar condiciones fiscales
function agregarCondicionFiscal($conexion, $data) {
    $entidad_id = intval($data['entidad_id']);
    $condicion_fiscal_id = intval($data['condicion_fiscal_id']);
    $f_desde = mysqli_real_escape_string($conexion, $data['f_desde']);
    $f_hasta = mysqli_real_escape_string($conexion, $data['f_hasta'] ?? null);
    
    $sql = "INSERT INTO gestion__entidades_condiciones_fiscales 
            (entidad_id, condicion_fiscal_id, f_desde, f_hasta, estado_registro_id) 
            VALUES 
            ($entidad_id, $condicion_fiscal_id, '$f_desde', " . ($f_hasta ? "'$f_hasta'" : "NULL") . ", 1)";
    
    return mysqli_query($conexion, $sql);
}

function editarCondicionFiscal($conexion, $entidad_condicion_fiscal_id, $data) {
    $entidad_condicion_fiscal_id = intval($entidad_condicion_fiscal_id);
    $condicion_fiscal_id = intval($data['condicion_fiscal_id']);
    $f_desde = mysqli_real_escape_string($conexion, $data['f_desde']);
    $f_hasta = mysqli_real_escape_string($conexion, $data['f_hasta'] ?? null);
    
    $sql = "UPDATE gestion__entidades_condiciones_fiscales SET
            condicion_fiscal_id = $condicion_fiscal_id,
            f_desde = '$f_desde',
            f_hasta = " . ($f_hasta ? "'$f_hasta'" : "NULL") . "
            WHERE entidad_condicion_fiscal_id = $entidad_condicion_fiscal_id";
    
    return mysqli_query($conexion, $sql);
}

function eliminarCondicionFiscal($conexion, $entidad_condicion_fiscal_id) {
    $entidad_condicion_fiscal_id = intval($entidad_condicion_fiscal_id);
    
    $sql = "UPDATE gestion__entidades_condiciones_fiscales 
            SET estado_registro_id = 0 
            WHERE entidad_condicion_fiscal_id = $entidad_condicion_fiscal_id";
    return mysqli_query($conexion, $sql);
}

// Funciones para gestionar roles
function agregarRol($conexion, $data) {
    $entidad_id = intval($data['entidad_id']);
    $rol_entidad_id = intval($data['rol_entidad_id']);
    $f_alta = mysqli_real_escape_string($conexion, $data['f_alta']);
    $f_baja = mysqli_real_escape_string($conexion, $data['f_baja'] ?? null);
    
    $sql = "INSERT INTO gestion__entidades_roles 
            (entidad_id, rol_entidad_id, f_alta, f_baja) 
            VALUES 
            ($entidad_id, $rol_entidad_id, '$f_alta', " . ($f_baja ? "'$f_baja'" : "NULL") . ")";
    
    return mysqli_query($conexion, $sql);
}

function editarRol($conexion, $entidad_rol_id, $data) {
    $entidad_rol_id = intval($entidad_rol_id);
    $rol_entidad_id = intval($data['rol_entidad_id']);
    $f_alta = mysqli_real_escape_string($conexion, $data['f_alta']);
    $f_baja = mysqli_real_escape_string($conexion, $data['f_baja'] ?? null);
    
    $sql = "UPDATE gestion__entidades_roles SET
            rol_entidad_id = $rol_entidad_id,
            f_alta = '$f_alta',
            f_baja = " . ($f_baja ? "'$f_baja'" : "NULL") . "
            WHERE entidad_rol_id = $entidad_rol_id";
    
    return mysqli_query($conexion, $sql);
}

function eliminarRol($conexion, $entidad_rol_id) {
    $entidad_rol_id = intval($entidad_rol_id);
    
    $sql = "DELETE FROM gestion__entidades_roles WHERE entidad_rol_id = $entidad_rol_id";
    return mysqli_query($conexion, $sql);
}
?>