<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerIconos($conexion)
{
    $sql = "SELECT i.*, e.estado_nombre 
            FROM conf__iconos i
            LEFT JOIN conf__estados_registros e ON i.tabla_estados_registro_id = e.estado_id
            ORDER BY i.icono_nombre";
    $res = mysqli_query($conexion, $sql);

    if (!$res) {
        return ['error' => mysqli_error($conexion)];
    }

    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarIcono($conexion, $data)
{
    if (empty($data['icono_nombre']) || empty($data['icono_clase'])) {
        return false;
    }

    $icono_nombre = mysqli_real_escape_string($conexion, trim($data['icono_nombre']));
    $icono_clase = mysqli_real_escape_string($conexion, trim($data['icono_clase']));
    $tabla_estados_registro_id = intval($data['tabla_estados_registro_id']);

    // Verificar si el estado existe en conf__estados_registros
    $sql_verificar = "SELECT estado_id FROM conf__estados_registros WHERE estado_id = $tabla_estados_registro_id";
    $res_verificar = mysqli_query($conexion, $sql_verificar);

    if (!$res_verificar || mysqli_num_rows($res_verificar) == 0) {
        // Estado por defecto si no existe
        $tabla_estados_registro_id = 1; // Activo
    }

    $sql = "INSERT INTO conf__iconos (icono_nombre, icono_clase, tabla_estados_registro_id, fecha_creacion) 
            VALUES ('$icono_nombre', '$icono_clase', $tabla_estados_registro_id, NOW())";

    return mysqli_query($conexion, $sql);
}

function editarIcono($conexion, $id, $data)
{
    if (empty($data['icono_nombre']) || empty($data['icono_clase'])) {
        return false;
    }

    $id = intval($id);
    $icono_nombre = mysqli_real_escape_string($conexion, trim($data['icono_nombre']));
    $icono_clase = mysqli_real_escape_string($conexion, trim($data['icono_clase']));
    $tabla_estados_registro_id = intval($data['tabla_estados_registro_id']);

    $sql = "UPDATE conf__iconos SET
            icono_nombre = '$icono_nombre',
            icono_clase = '$icono_clase',
            tabla_estados_registro_id = $tabla_estados_registro_id,
            fecha_actualizacion = NOW()
            WHERE icono_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoIcono($conexion, $id, $nuevo_estado)
{
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);

    $sql = "UPDATE conf__iconos SET 
            tabla_estados_registro_id = $nuevo_estado,
            fecha_actualizacion = NOW()
            WHERE icono_id = $id";

    return mysqli_query($conexion, $sql);
}

function obtenerIconoPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT i.*, e.estado_nombre 
            FROM conf__iconos i
            LEFT JOIN conf__estados_registros e ON i.tabla_estados_registro_id = e.estado_id
            WHERE i.icono_id = $id";
    $res = mysqli_query($conexion, $sql);

    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }
    return false;
}

function obtenerEstadoIcono($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT tabla_estados_registro_id FROM conf__iconos WHERE icono_id = $id";
    $res = mysqli_query($conexion, $sql);

    if ($res && mysqli_num_rows($res) > 0) {
        $fila = mysqli_fetch_assoc($res);
        return $fila['tabla_estados_registro_id'];
    }
    return false;
}

// Función para registrar cambios de estado (si la necesitas)
function registrarCambioEstado($conexion, $tabla, $registro_id, $estado_anterior, $estado_nuevo)
{
    // Implementación según tu estructura de auditoría
    // Ejemplo:
    $sql = "INSERT INTO auditoria_cambios_estado 
            (tabla, registro_id, estado_anterior, estado_nuevo, fecha_cambio, usuario_id) 
            VALUES ('$tabla', $registro_id, $estado_anterior, $estado_nuevo, NOW(), " . $_SESSION['usuario_id'] . ")";

    return mysqli_query($conexion, $sql);
}
?>