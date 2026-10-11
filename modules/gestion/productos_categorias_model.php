<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/empresa_config.php';
$conexion = $conn;

// Categorías de productos de una empresa (gestion__productos_categorias.empresa_id).
// Todas las funciones reciben la empresa validada por el guard: nunca se lee ni se modifica
// una categoría de otra empresa.

function categoriasConsultar($conexion, $sql, $tipos, array $params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("productos_categorias: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $filas;
}

function obtenerCategorias($conexion, $empresa_idx)
{
    return categoriasConsultar($conexion,
        "SELECT c.*, p.producto_categoria_nombre as padre_nombre
           FROM gestion__productos_categorias c
           LEFT JOIN gestion__productos_categorias p
                  ON c.producto_categoria_padre_id = p.producto_categoria_id AND p.empresa_id = c.empresa_id
          WHERE c.empresa_id = ?
          ORDER BY c.producto_categoria_padre_id, c.producto_categoria_nombre",
        "i", [intval($empresa_idx)]);
}

function obtenerEstructuraJerarquica($conexion, $empresa_idx)
{
    $todasCategorias = [];
    foreach (categoriasConsultar($conexion,
        "SELECT * FROM gestion__productos_categorias WHERE empresa_id = ?
          ORDER BY producto_categoria_padre_id, producto_categoria_nombre",
        "i", [intval($empresa_idx)]) as $categoria) {
        $todasCategorias[$categoria['producto_categoria_id']] = $categoria;
    }

    // Construimos la estructura jerárquica
    $arbol = [];
    foreach ($todasCategorias as $id => $categoria) {
        if (empty($categoria['producto_categoria_padre_id'])) {
            $arbol[$id] = $categoria;
            $arbol[$id]['hijos'] = obtenerHijos($todasCategorias, $id);
        }
    }
    return $arbol;
}

function obtenerHijos($categorias, $padre_id)
{
    $hijos = [];
    foreach ($categorias as $id => $categoria) {
        if ($categoria['producto_categoria_padre_id'] == $padre_id) {
            $hijos[$id] = $categoria;
            $hijos[$id]['hijos'] = obtenerHijos($categorias, $id);
        }
    }
    return $hijos;
}

function obtenerCategoriasPadre($conexion, $empresa_idx, $excluir_id = null)
{
    return categoriasConsultar($conexion,
        "SELECT producto_categoria_id, producto_categoria_nombre
           FROM gestion__productos_categorias
          WHERE empresa_id = ?
            AND (producto_categoria_padre_id IS NULL OR producto_categoria_padre_id = 0)
            AND producto_categoria_id <> ?
          ORDER BY producto_categoria_nombre",
        "ii", [intval($empresa_idx), intval($excluir_id)]);
}

function obtenerTodasCategorias($conexion, $empresa_idx, $excluir_id = null)
{
    return categoriasConsultar($conexion,
        "SELECT producto_categoria_id, producto_categoria_nombre, producto_categoria_padre_id
           FROM gestion__productos_categorias
          WHERE empresa_id = ? AND producto_categoria_id <> ?
          ORDER BY producto_categoria_nombre",
        "ii", [intval($empresa_idx), intval($excluir_id)]);
}

/** Padre válido de la empresa (0 = sin padre); null si el padre no es de la empresa. */
function categoriaPadreValido($conexion, $empresa_idx, $padre_id)
{
    $padre_id = intval($padre_id);
    if ($padre_id <= 0) {
        return ['producto_categoria_id' => 0, 'producto_tipo_id' => null];
    }
    $filas = categoriasConsultar($conexion,
        "SELECT producto_categoria_id, producto_tipo_id FROM gestion__productos_categorias
          WHERE producto_categoria_id = ? AND empresa_id = ?",
        "ii", [$padre_id, intval($empresa_idx)]);
    return $filas[0] ?? null;
}

function agregarCategoria($conexion, $data)
{
    $empresa_idx = intval($data['empresa_id'] ?? 0);
    $nombre = trim($data['producto_categoria_nombre'] ?? '');
    if ($empresa_idx <= 0 || $nombre === '') {
        return false;
    }
    $padre = categoriaPadreValido($conexion, $empresa_idx, $data['producto_categoria_padre_id'] ?? 0);
    if ($padre === null) {
        return false;
    }
    $padre_id = $padre['producto_categoria_id'] > 0 ? intval($padre['producto_categoria_id']) : null;
    // Tipo de producto: el del padre o, en una categoría raíz, el primer tipo con stock de la empresa
    $producto_tipo_id = intval($padre['producto_tipo_id'] ?? 0) ?: emp_producto_tipo_id($conexion, $empresa_idx, true);

    $stmt = mysqli_prepare($conexion,
        "INSERT INTO gestion__productos_categorias (empresa_id, producto_categoria_nombre, producto_categoria_padre_id, producto_tipo_id)
         VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isii", $empresa_idx, $nombre, $padre_id, $producto_tipo_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function editarCategoria($conexion, $id, $data)
{
    $id = intval($id);
    $empresa_idx = intval($data['empresa_id'] ?? 0);
    $nombre = trim($data['producto_categoria_nombre'] ?? '');
    if ($empresa_idx <= 0 || $nombre === '') {
        return false;
    }
    $padre = categoriaPadreValido($conexion, $empresa_idx, $data['producto_categoria_padre_id'] ?? 0);
    // Evitar que una categoría sea su propio padre (o que el padre sea de otra empresa)
    if ($padre === null || intval($padre['producto_categoria_id']) === $id) {
        return false;
    }
    $padre_id = $padre['producto_categoria_id'] > 0 ? intval($padre['producto_categoria_id']) : null;

    $stmt = mysqli_prepare($conexion,
        "UPDATE gestion__productos_categorias
            SET producto_categoria_nombre = ?, producto_categoria_padre_id = ?
          WHERE producto_categoria_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "siii", $nombre, $padre_id, $id, $empresa_idx);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function eliminarCategoria($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    // No se puede eliminar si tiene subcategorías
    $hijas = categoriasConsultar($conexion,
        "SELECT COUNT(*) AS count FROM gestion__productos_categorias WHERE producto_categoria_padre_id = ? AND empresa_id = ?",
        "ii", [$id, $empresa_idx]);
    if (intval($hijas[0]['count'] ?? 0) > 0) {
        return false;
    }

    $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__productos_categorias WHERE producto_categoria_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function obtenerCategoriaPorId($conexion, $id, $empresa_idx)
{
    $filas = categoriasConsultar($conexion,
        "SELECT * FROM gestion__productos_categorias WHERE producto_categoria_id = ? AND empresa_id = ?",
        "ii", [intval($id), intval($empresa_idx)]);
    return $filas[0] ?? null;
}
