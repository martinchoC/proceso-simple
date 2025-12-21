<?php
require_once "conexion.php";

// Configuración para la subida de archivos
define('IMAGENES_DIR', $_SERVER['DOCUMENT_ROOT'] . '/uploads/imagenes/');
define('IMAGENES_URL', '/uploads/imagenes/');

// Crear directorio si no existe
if (!file_exists(IMAGENES_DIR)) {
    mkdir(IMAGENES_DIR, 0777, true);
}

function obtenerImagenes($conexion) {
    $sql = "SELECT * FROM conf__imagenes ORDER BY imagen_creacion DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarImagen($conexion, $data, $archivo) {
    if (empty($data['imagen_nombre']) || !$archivo) {
        return false;
    }
    
    // Procesar la imagen subida
    $infoArchivo = pathinfo($archivo['name']);
    $extension = strtolower($infoArchivo['extension']);
    $nombreArchivo = uniqid() . '.' . $extension;
    $rutaCompleta = IMAGENES_DIR . $nombreArchivo;
    
    // Mover el archivo subido
    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        return false;
    }
    
    // Insertar en la base de datos
    $imagen_nombre = mysqli_real_escape_string($conexion, $data['imagen_nombre']);
    $imagen_ruta = IMAGENES_URL . $nombreArchivo;
    $imagen_tipo = $archivo['type'];
    $imagen_tamanio = $archivo['size'];

    $sql = "INSERT INTO conf__imagenes 
            (imagen_nombre, imagen_ruta, imagen_tipo, imagen_tamanio) 
            VALUES 
            ('$imagen_nombre', '$imagen_ruta', '$imagen_tipo', $imagen_tamanio)";
    
    return mysqli_query($conexion, $sql);
}

function editarImagen($conexion, $id, $data, $archivo = null) {
    if (empty($data['imagen_nombre'])) {
        return false;
    }
    
    $id = intval($id);
    $imagen_nombre = mysqli_real_escape_string($conexion, $data['imagen_nombre']);
    
    // Obtener información actual de la imagen
    $imagenActual = obtenerImagenPorId($conexion, $id);
    
    // Si se subió un nuevo archivo, procesarlo
    if ($archivo) {
        // Eliminar el archivo anterior
        if ($imagenActual && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagenActual['imagen_ruta'])) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $imagenActual['imagen_ruta']);
        }
        
        // Procesar la nueva imagen
        $infoArchivo = pathinfo($archivo['name']);
        $extension = strtolower($infoArchivo['extension']);
        $nombreArchivo = uniqid() . '.' . $extension;
        $rutaCompleta = IMAGENES_DIR . $nombreArchivo;
        
        // Mover el archivo subido
        if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            return false;
        }
        
        $imagen_ruta = IMAGENES_URL . $nombreArchivo;
        $imagen_tipo = $archivo['type'];
        $imagen_tamanio = $archivo['size'];
        
        $sql = "UPDATE conf__imagenes SET
                imagen_nombre = '$imagen_nombre',
                imagen_ruta = '$imagen_ruta',
                imagen_tipo = '$imagen_tipo',
                imagen_tamanio = $imagen_tamanio
                WHERE imagen_id = $id";
    } else {
        $sql = "UPDATE conf__imagenes SET
                imagen_nombre = '$imagen_nombre'
                WHERE imagen_id = $id";
    }

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoImagen($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__imagenes SET estado_registro_id = $nuevo_estado WHERE imagen_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarImagen($conexion, $id) {
    $id = intval($id);
    
    // Obtener información de la imagen para eliminar el archivo
    $imagen = obtenerImagenPorId($conexion, $id);
    
    if ($imagen && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagen['imagen_ruta'])) {
        unlink($_SERVER['DOCUMENT_ROOT'] . $imagen['imagen_ruta']);
    }
    
    $sql = "DELETE FROM conf__imagenes WHERE imagen_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerImagenPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__imagenes WHERE imagen_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}