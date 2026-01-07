<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerListasPrecios($conexion)
{
    $sql = "SELECT * FROM `gestion__listas_precios` 
            ORDER BY es_principal DESC, nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarListaPrecios($conexion, $data)
{
    if (empty($data['nombre'])) {
        return false;
    }

    $nombre = mysqli_real_escape_string($conexion, $data['nombre']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $empresa_id = intval($data['empresa_id']);
    $es_principal = intval($data['es_principal']);
    $metodo_calculo = mysqli_real_escape_string($conexion, $data['metodo_calculo']);
    $margen_ganancia = floatval($data['margen_ganancia']);
    $tipo = mysqli_real_escape_string($conexion, $data['tipo']);
    $estado = mysqli_real_escape_string($conexion, $data['estado']);
    $f_vigencia_desde = $data['f_vigencia_desde'] ? "'" . mysqli_real_escape_string($conexion, $data['f_vigencia_desde']) . "'" : 'NULL';
    $f_vigencia_hasta = $data['f_vigencia_hasta'] ? "'" . mysqli_real_escape_string($conexion, $data['f_vigencia_hasta']) . "'" : 'NULL';
    $usuario_id_alta = intval($data['usuario_id_alta']);
    $ip_origen = mysqli_real_escape_string($conexion, $data['ip_origen']);

    // Verificar si ya existe el nombre
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__listas_precios` 
                  WHERE nombre = '$nombre' AND empresa_id = $empresa_id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe_nombre = mysqli_fetch_assoc($res_check)['existe'];

    if ($existe_nombre > 0) {
        return false; // Ya existe este nombre
    }

    // Si se marca como principal, quitar principal de otras listas
    if ($es_principal == 1) {
        $sql_update_principal = "UPDATE `gestion__listas_precios` 
                                SET es_principal = 0 
                                WHERE empresa_id = $empresa_id AND tipo = '$tipo'";
        mysqli_query($conexion, $sql_update_principal);
    }

    $sql = "INSERT INTO `gestion__listas_precios` 
            (nombre, descripcion, empresa_id, es_principal, metodo_calculo, 
             margen_ganancia, tipo, estado, f_vigencia_desde, f_vigencia_hasta,
             usuario_id_alta, ip_origen) 
            VALUES 
            ('$nombre', '$descripcion', $empresa_id, $es_principal, '$metodo_calculo',
             $margen_ganancia, '$tipo', '$estado', $f_vigencia_desde, $f_vigencia_hasta,
             $usuario_id_alta, '$ip_origen')";

    return mysqli_query($conexion, $sql);
}

function editarListaPrecios($conexion, $id, $data)
{
    if (empty($data['nombre'])) {
        return false;
    }

    $id = intval($id);
    $nombre = mysqli_real_escape_string($conexion, $data['nombre']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $es_principal = intval($data['es_principal']);
    $metodo_calculo = mysqli_real_escape_string($conexion, $data['metodo_calculo']);
    $margen_ganancia = floatval($data['margen_ganancia']);
    $tipo = mysqli_real_escape_string($conexion, $data['tipo']);
    $estado = mysqli_real_escape_string($conexion, $data['estado']);
    $f_vigencia_desde = $data['f_vigencia_desde'] ? "'" . mysqli_real_escape_string($conexion, $data['f_vigencia_desde']) . "'" : 'NULL';
    $f_vigencia_hasta = $data['f_vigencia_hasta'] ? "'" . mysqli_real_escape_string($conexion, $data['f_vigencia_hasta']) . "'" : 'NULL';
    $usuario_id_modificacion = intval($data['usuario_id_modificacion']);
    $ip_origen = mysqli_real_escape_string($conexion, $data['ip_origen']);

    // Obtener empresa_id y tipo actual para las verificaciones
    $sql_current = "SELECT empresa_id, tipo FROM `gestion__listas_precios` WHERE lista_id = $id";
    $res_current = mysqli_query($conexion, $sql_current);
    $current_data = mysqli_fetch_assoc($res_current);
    $empresa_id = $current_data['empresa_id'];
    $current_tipo = $current_data['tipo'];

    // Verificar si ya existe el nombre (excluyendo el registro actual)
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__listas_precios` 
                  WHERE nombre = '$nombre' 
                  AND empresa_id = $empresa_id 
                  AND lista_id != $id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe_nombre = mysqli_fetch_assoc($res_check)['existe'];

    if ($existe_nombre > 0) {
        return false; // Ya existe este nombre
    }

    // Si se marca como principal, quitar principal de otras listas del mismo tipo
    if ($es_principal == 1) {
        $sql_update_principal = "UPDATE `gestion__listas_precios` 
                                SET es_principal = 0 
                                WHERE empresa_id = $empresa_id AND tipo = '$tipo' AND lista_id != $id";
        mysqli_query($conexion, $sql_update_principal);
    }

    $sql = "UPDATE `gestion__listas_precios` SET
            nombre = '$nombre',
            descripcion = '$descripcion',
            es_principal = $es_principal,
            metodo_calculo = '$metodo_calculo',
            margen_ganancia = $margen_ganancia,
            tipo = '$tipo',
            estado = '$estado',
            f_vigencia_desde = $f_vigencia_desde,
            f_vigencia_hasta = $f_vigencia_hasta,
            usuario_id_modificacion = $usuario_id_modificacion,
            ip_origen = '$ip_origen',
            f_actualizacion = CURRENT_TIMESTAMP
            WHERE lista_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarListaPrecios($conexion, $id)
{
    $id = intval($id);

    // Verificar si está siendo usado en otras tablas
    // Aquí puedes agregar verificaciones según tu estructura de base de datos

    $sql = "DELETE FROM `gestion__listas_precios` 
            WHERE lista_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerListaPreciosPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM `gestion__listas_precios` 
            WHERE lista_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
?>