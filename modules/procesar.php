<?php
require_once '../config/db.php';

function listarModulos()
{
    global $conexion;

    $busqueda = $_GET['busqueda'] ?? $_POST['busqueda'] ?? [];

    $query = "SELECT m.*, d.modulo as modulo_depende 
              FROM conf__modulos m
              LEFT JOIN conf__modulos d ON m.depende_id = d.modulo_id 
              WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($busqueda['modulo'])) {
        $query .= " AND m.modulo LIKE ?";
        $params[] = "%" . $busqueda['modulo'] . "%";
        $types .= "s";
    }

    if (!empty($busqueda['base_datos'])) {
        $query .= " AND m.base_datos LIKE ?";
        $params[] = "%" . $busqueda['base_datos'] . "%";
        $types .= "s";
    }

    if (isset($busqueda['estado']) && $busqueda['estado'] !== "") {
        $query .= " AND m.estado_registro_id = ?";
        $params[] = $busqueda['estado'];
        $types .= "i";
    }

    $query .= " ORDER BY m.modulo";

    $stmt = $conexion->prepare($query);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $modulos = [];
    while ($row = $result->fetch_assoc()) {
        $row['estado'] = $row['estado_registro_id'] == 1 ? 'Activo' : 'Inactivo';
        $row['modulo_depende'] = $row['modulo_depende'] ?? 'Ninguno';
        $modulos[] = $row;
    }

    echo json_encode($modulos);
}

function guardarModulo()
{
    global $conexion;

    $modulo_id = $_POST['modulo_id'] ?? 0;
    $accion = $_POST['accion'];

    $datos = [
        'modulo' => $_POST['modulo'],
        'base_datos' => $_POST['base_datos'],
        'modulo_url' => $_POST['modulo_url'],
        'email_envio_modulo' => $_POST['email_envio_modulo'],
        'layout_nombre' => $_POST['layout_nombre'],
        'depende_id' => $_POST['depende_id'],
        'estado_registro_id' => $_POST['estado_registro_id']
    ];

    try {
        if ($accion == 'editar' && $modulo_id > 0) {
            $stmt = $conexion->prepare("UPDATE conf__modulos SET 
                modulo = ?, base_datos = ?, modulo_url = ?, email_envio_modulo = ?,
                layout_nombre = ?, depende_id = ?, estado_registro_id = ?
                WHERE modulo_id = ?");

            $stmt->bind_param(
                "sssssiii",
                $datos['modulo'],
                $datos['base_datos'],
                $datos['modulo_url'],
                $datos['email_envio_modulo'],
                $datos['layout_nombre'],
                $datos['depende_id'],
                $datos['estado_registro_id'],
                $modulo_id
            );
        } else {
            $stmt = $conexion->prepare("INSERT INTO conf__modulos 
                (modulo, base_datos, modulo_url, email_envio_modulo, layout_nombre, depende_id, estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "sssssii",
                $datos['modulo'],
                $datos['base_datos'],
                $datos['modulo_url'],
                $datos['email_envio_modulo'],
                $datos['layout_nombre'],
                $datos['depende_id'],
                $datos['estado_registro_id']
            );
        }

        $stmt->execute();

        if ($stmt->affected_rows > 0 || $stmt->insert_id > 0) {
            echo json_encode([
                'estado' => true,
                'mensaje' => $accion == 'editar' ? 'Módulo actualizado correctamente' : 'Módulo creado correctamente'
            ]);
        } else {
            echo json_encode([
                'estado' => false,
                'mensaje' => 'No se realizaron cambios'
            ]);
        }

        $stmt->close();
    } catch (Exception $e) {
        echo json_encode([
            'estado' => false,
            'mensaje' => 'Error: ' . $e->getMessage()
        ]);
    }
}

function eliminarModulo()
{
    global $conexion;

    $id = $_POST['id'];

    try {
        // Verificar si el módulo es dependencia de otros
        $stmt = $conexion->prepare("SELECT COUNT(*) as dependientes FROM conf__modulos WHERE depende_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['dependientes'] > 0) {
            echo json_encode([
                'estado' => false,
                'mensaje' => 'No se puede eliminar el módulo porque otros módulos dependen de él'
            ]);
            return;
        }

        // Eliminar el módulo
        $stmt = $conexion->prepare("DELETE FROM conf__modulos WHERE modulo_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'estado' => true,
                'mensaje' => 'Módulo eliminado correctamente'
            ]);
        } else {
            echo json_encode([
                'estado' => false,
                'mensaje' => 'No se encontró el módulo a eliminar'
            ]);
        }

        $stmt->close();
    } catch (Exception $e) {
        echo json_encode([
            'estado' => false,
            'mensaje' => 'Error: ' . $e->getMessage()
        ]);
    }
}