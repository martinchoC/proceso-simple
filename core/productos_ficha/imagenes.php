<?php
/**
 * Solapa Imágenes de la ficha de producto del módulo 5 (doc 26): fotos del producto guardadas en la base
 * (conf__imagenes.imagen_data) y vinculadas en gestion__productos_imagenes, servidas por get_imagen.php del módulo.
 * Mismas reglas que la solapa del módulo 2 (subirImagenProducto / actualizarImagenProducto / eliminarImagenProducto de
 * modules/gestion/productos_model.php): la imagen se valida por contenido y se normaliza con core/imagenes.php
 * (img_normalizar: orientación, lado máximo, recodificación); una sola principal por producto; baja lógica. Además:
 *   - el producto y la imagen tienen que ser de la empresa;
 *   - la primera imagen del producto queda como principal, y al quitar la principal pasa a serlo la siguiente;
 *   - orden con subir / bajar (intercambia con la vecina).
 * Cada escritura va en su transacción.
 */

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/../imagenes.php';

if (!function_exists('pfim_listar')) {
    /** Imágenes activas del producto: la principal primero, después por orden. */
    function pfim_listar(mysqli $conexion, int $empresa_id, int $producto_id): array
    {
        $filas = pfic_filas($conexion,
            "SELECT pi.producto_imagen_id, pi.imagen_id, pi.descripcion, pi.es_principal, pi.orden, ci.imagen_nombre, ci.imagen_tipo, ci.imagen_tamanio
               FROM gestion__productos_imagenes pi
               JOIN conf__imagenes ci ON ci.imagen_id = pi.imagen_id
              WHERE pi.producto_id = ? AND pi.empresa_id = ? AND pi.tabla_estado_registro_id = 1
              ORDER BY pi.es_principal DESC, pi.orden, pi.producto_imagen_id", 'ii', [$producto_id, $empresa_id]);
        foreach ($filas as &$f) {
            $f['es_principal'] = (int) $f['es_principal'];
            $f['imagen_url'] = 'get_imagen.php?id=' . (int) $f['imagen_id'];
            $f['miniatura_url'] = 'get_imagen.php?id=' . (int) $f['imagen_id'] . '&thumb=1&size=120';
        }
        return $filas;
    }
}

if (!function_exists('pfim_imagen')) {
    /** Fila activa de la imagen del producto en la empresa; si no, lanza. */
    function pfim_imagen(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_imagen_id): array
    {
        $f = pfic_filas($conexion,
            "SELECT producto_imagen_id, es_principal, orden FROM gestion__productos_imagenes
              WHERE producto_imagen_id = ? AND producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
            'iii', [$producto_imagen_id, $producto_id, $empresa_id]);
        if (!$f) {
            throw new InvalidArgumentException('La imagen ya no está.');
        }
        return $f[0];
    }
}

if (!function_exists('pfim_marcar_principal')) {
    function pfim_marcar_principal(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_imagen_id): void
    {
        pfic_ejecutar($conexion, "UPDATE gestion__productos_imagenes SET es_principal = IF(producto_imagen_id = ?, 1, 0) WHERE producto_id = ? AND empresa_id = ?",
            'iii', [$producto_imagen_id, $producto_id, $empresa_id]);
    }
}

if (!function_exists('pfim_asegurar_principal')) {
    /** Si el producto tiene imágenes activas y ninguna principal, la primera por orden pasa a serlo. */
    function pfim_asegurar_principal(mysqli $conexion, int $empresa_id, int $producto_id): void
    {
        if (pfic_filas($conexion, "SELECT 1 FROM gestion__productos_imagenes WHERE producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 AND es_principal = 1",
            'ii', [$producto_id, $empresa_id])) {
            return;
        }
        $p = pfic_filas($conexion, "SELECT producto_imagen_id FROM gestion__productos_imagenes WHERE producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                                     ORDER BY orden, producto_imagen_id LIMIT 1", 'ii', [$producto_id, $empresa_id]);
        if ($p) {
            pfim_marcar_principal($conexion, $empresa_id, $producto_id, (int) $p[0]['producto_imagen_id']);
        }
    }
}

if (!function_exists('pfim_subir')) {
    /**
     * Sube una imagen ($archivo = entrada de $_FILES) al producto. Devuelve ['producto_imagen_id', 'imagen_id'].
     * @throws InvalidArgumentException con un mensaje para el usuario
     */
    function pfim_subir(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id, ?array $archivo, string $descripcion, bool $principal): array
    {
        pfic_producto_de_empresa($conexion, $empresa_id, $producto_id);
        if (!$archivo || !isset($archivo['error'])) {
            throw new InvalidArgumentException(img_error_subida(UPLOAD_ERR_NO_FILE));
        }
        if ((int) $archivo['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(img_error_subida((int) $archivo['error']));
        }
        try {
            $img = img_normalizar((string) $archivo['tmp_name'], (string) ($archivo['name'] ?? 'imagen'));
        } catch (RuntimeException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
        $descripcion = mb_substr(trim($descripcion), 0, 255);
        $nombre = mb_substr((string) $img['nombre'], 0, 100);
        mysqli_begin_transaction($conexion);
        try {
            $imagen_id = pfic_ejecutar($conexion,
                "INSERT INTO conf__imagenes (imagen_nombre, imagen_ruta, imagen_tipo, imagen_tamanio, imagen_data, tabla_estado_registro_id) VALUES (?, ?, ?, ?, ?, 1)",
                'sssis', [$nombre, mb_substr('productos/' . $producto_id . '/' . time() . '_' . $nombre, 0, 255), (string) $img['tipo'], (int) $img['tamanio'], $img['data']], true);
            $orden = (int) (pfic_filas($conexion, "SELECT IFNULL(MAX(orden), 0) + 1 AS o FROM gestion__productos_imagenes WHERE producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
                'ii', [$producto_id, $empresa_id])[0]['o'] ?? 1);
            $id = pfic_ejecutar($conexion,
                "INSERT INTO gestion__productos_imagenes (producto_id, empresa_id, imagen_id, descripcion, es_principal, orden, usuario_creacion_id, tabla_estado_registro_id)
                 VALUES (?, ?, ?, ?, 0, ?, ?, 1)", 'iiisii', [$producto_id, $empresa_id, $imagen_id, $descripcion, $orden, $usuario_id ?: null], true);
            if ($principal) {
                pfim_marcar_principal($conexion, $empresa_id, $producto_id, $id);
            } else {
                pfim_asegurar_principal($conexion, $empresa_id, $producto_id);
            }
            mysqli_commit($conexion);
            return ['producto_imagen_id' => $id, 'imagen_id' => $imagen_id];
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            throw $e;
        }
    }
}

if (!function_exists('pfim_editar')) {
    /** Descripción de la imagen. */
    function pfim_editar(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_imagen_id, string $descripcion): void
    {
        pfim_imagen($conexion, $empresa_id, $producto_id, $producto_imagen_id);
        pfic_ejecutar($conexion, "UPDATE gestion__productos_imagenes SET descripcion = ? WHERE producto_imagen_id = ?",
            'si', [mb_substr(trim($descripcion), 0, 255), $producto_imagen_id]);
    }
}

if (!function_exists('pfim_principal')) {
    function pfim_principal(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_imagen_id): void
    {
        pfim_imagen($conexion, $empresa_id, $producto_id, $producto_imagen_id);
        pfim_marcar_principal($conexion, $empresa_id, $producto_id, $producto_imagen_id);
    }
}

if (!function_exists('pfim_mover')) {
    /** Sube (-1) o baja (+1) la imagen en el orden, intercambiando con la vecina. */
    function pfim_mover(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_imagen_id, int $sentido): void
    {
        pfim_imagen($conexion, $empresa_id, $producto_id, $producto_imagen_id);
        $ids = array_map(fn($f) => (int) $f['producto_imagen_id'], pfic_filas($conexion,
            "SELECT producto_imagen_id FROM gestion__productos_imagenes WHERE producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
              ORDER BY orden, producto_imagen_id", 'ii', [$producto_id, $empresa_id]));
        $i = array_search($producto_imagen_id, $ids, true);
        $j = $i + ($sentido < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        mysqli_begin_transaction($conexion);
        try {
            foreach ($ids as $n => $id) {
                pfic_ejecutar($conexion, "UPDATE gestion__productos_imagenes SET orden = ? WHERE producto_imagen_id = ?", 'ii', [$n + 1, $id]);
            }
            mysqli_commit($conexion);
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            throw $e;
        }
    }
}

if (!function_exists('pfim_quitar')) {
    /** Baja lógica; si era la principal, pasa a serlo la siguiente. */
    function pfim_quitar(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_imagen_id): void
    {
        pfim_imagen($conexion, $empresa_id, $producto_id, $producto_imagen_id);
        mysqli_begin_transaction($conexion);
        try {
            pfic_ejecutar($conexion, "UPDATE gestion__productos_imagenes SET tabla_estado_registro_id = 2, es_principal = 0 WHERE producto_imagen_id = ?", 'i', [$producto_imagen_id]);
            pfim_asegurar_principal($conexion, $empresa_id, $producto_id);
            mysqli_commit($conexion);
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            throw $e;
        }
    }
}
