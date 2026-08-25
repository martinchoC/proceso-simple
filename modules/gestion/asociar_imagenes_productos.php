<?php
/**
 * Script para asociar imágenes existentes en conf__imagenes a productos
 * 
 * El nombre del archivo (sin extensión) debe coincidir con producto_codigo
 * Ejemplo: imagen_nombre = "PROD-001.jpg" → producto_codigo = "PROD-001"
 * 
 * También muestra un listado de imágenes que no tienen producto asociado
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

// ============================================================
// BUSCAR db.php
// ============================================================
$posibles_rutas = [
    __DIR__ . '/../../db.php',
    __DIR__ . '/../db.php',
    __DIR__ . '/db.php',
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../../config/db.php',
];

$db_encontrado = false;
$db_path = '';
foreach ($posibles_rutas as $ruta) {
    if (file_exists($ruta)) {
        $db_path = $ruta;
        require_once $ruta;
        $db_encontrado = true;
        break;
    }
}

if (!$db_encontrado || !isset($conn) || !$conn) {
    die("<h1>❌ Error de conexión</h1><p>No se pudo conectar a la base de datos.</p>");
}

$conexion = $conn;
$empresa_id = 2; // Cambia según tu empresa

// ============================================================
// FUNCIÓN PARA FORMATEAR TAMAÑO
// ============================================================
function formatSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

// ============================================================
// INICIO DEL HTML
// ============================================================
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Asociar Imágenes a Productos</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .warning { color: #ffc107; }
        .table { font-size: 0.9rem; }
        .table td, .table th { padding: 0.5rem; vertical-align: middle; }
        .badge-conexion { position: fixed; top: 10px; right: 10px; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; z-index: 1000; background: #28a745; color: white; }
        .progress-bar-container { width: 100%; height: 20px; background-color: #e9ecef; border-radius: 10px; overflow: hidden; margin: 15px 0; }
        .progress-bar-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s ease; border-radius: 10px; }
        .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin: 15px 0; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-card .number { font-size: 1.8rem; font-weight: bold; }
        .stat-card .label { font-size: 0.8rem; color: #6c757d; }
        .stat-card.total .number { color: #0d6efd; }
        .stat-card.success .number { color: #28a745; }
        .stat-card.error .number { color: #dc3545; }
        .stat-card.skip .number { color: #ffc107; }
        .stat-card.orphan .number { color: #6f42c1; }
        .log-container { max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.85rem; }
        .log-item { padding: 3px 0; border-bottom: 1px solid #eee; }
        .log-item.success { color: #28a745; }
        .log-item.error { color: #dc3545; }
        .log-item.skip { color: #ffc107; }
        .log-item.info { color: #17a2b8; }
        .log-item.orphan { color: #6f42c1; }
        .img-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; cursor: pointer; }
        .img-thumb:hover { transform: scale(2); transition: transform 0.3s; z-index: 100; position: relative; }
        .table-orphans { font-size: 0.85rem; }
        .table-orphans td, .table-orphans th { padding: 0.3rem 0.5rem; }
        .accordion-button:not(.collapsed) { background-color: #e9ecef; color: #0d6efd; }
        .accordion-button { font-weight: 500; }
        .badge-extension { font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; }
        .badge-extension.jpg { background: #28a745; color: white; }
        .badge-extension.png { background: #0d6efd; color: white; }
        .badge-extension.gif { background: #ffc107; color: black; }
        .badge-extension.webp { background: #6f42c1; color: white; }
        .badge-extension.jfif { background: #dc3545; color: white; }
        .badge-extension.other { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class='badge-conexion'><i class='fas fa-check-circle me-1'></i> Conectado</div>
    <div class='container'>";

echo "<h1>📸 Asociar Imágenes a Productos</h1>";
echo "<p><span class='badge bg-secondary'>db.php: " . htmlspecialchars($db_path) . "</span></p>";
echo "<p>Empresa ID: <strong>" . $empresa_id . "</strong></p>";
echo "<hr>";

// ============================================================
// CONTADORES Y ESTADÍSTICAS
// ============================================================
$total_productos = 0;
$total_imagenes = 0;
$asociaciones_creadas = 0;
$asociaciones_existentes = 0;
$imagenes_huerfanas = [];
$errores = [];
$logs = [];

// ============================================================
// OBTENER TODOS LOS PRODUCTOS
// ============================================================
echo "<h3>🔍 Paso 1: Obteniendo productos...</h3>";

$sql_productos = "SELECT producto_id, producto_codigo, producto_nombre 
                  FROM gestion__productos 
                  WHERE empresa_id = ? 
                  AND tabla_estado_registro_id = 1
                  ORDER BY producto_codigo";

$stmt_productos = mysqli_prepare($conexion, $sql_productos);
if (!$stmt_productos) {
    die("<div class='alert alert-danger'>Error al preparar consulta de productos: " . mysqli_error($conexion) . "</div>");
}

mysqli_stmt_bind_param($stmt_productos, "i", $empresa_id);
mysqli_stmt_execute($stmt_productos);
$result_productos = mysqli_stmt_get_result($stmt_productos);

$productos = [];
while ($row = mysqli_fetch_assoc($result_productos)) {
    $productos[$row['producto_codigo']] = $row;
}
mysqli_stmt_close($stmt_productos);

$total_productos = count($productos);
echo "<div class='alert alert-info'>📊 Total de productos activos: <strong>" . $total_productos . "</strong></div>";

// ============================================================
// OBTENER TODAS LAS IMÁGENES Y SUS ASOCIACIONES
// ============================================================
echo "<h3>🔍 Paso 2: Obteniendo imágenes y asociaciones...</h3>";

// Obtener todas las imágenes
$sql_imagenes = "SELECT imagen_id, imagen_nombre, imagen_tipo, imagen_tamanio, imagen_creacion 
                  FROM conf__imagenes 
                  WHERE tabla_estado_registro_id = 1
                  ORDER BY imagen_nombre";

$result_imagenes = mysqli_query($conexion, $sql_imagenes);
if (!$result_imagenes) {
    die("<div class='alert alert-danger'>Error al consultar imágenes: " . mysqli_error($conexion) . "</div>");
}

$imagenes = [];
while ($row = mysqli_fetch_assoc($result_imagenes)) {
    $nombre_sin_extension = pathinfo($row['imagen_nombre'], PATHINFO_FILENAME);
    $imagenes[] = [
        'imagen_id' => $row['imagen_id'],
        'imagen_nombre' => $row['imagen_nombre'],
        'nombre_sin_extension' => $nombre_sin_extension,
        'imagen_tipo' => $row['imagen_tipo'],
        'imagen_tamanio' => $row['imagen_tamanio'],
        'imagen_creacion' => $row['imagen_creacion']
    ];
}
$total_imagenes = count($imagenes);
echo "<div class='alert alert-info'>📊 Total de imágenes activas: <strong>" . $total_imagenes . "</strong></div>";

// Obtener todas las asociaciones existentes
$sql_asociaciones = "SELECT producto_id, imagen_id 
                      FROM gestion__productos_imagenes 
                      WHERE empresa_id = ? 
                      AND tabla_estado_registro_id = 1";

$stmt_asociaciones = mysqli_prepare($conexion, $sql_asociaciones);
if (!$stmt_asociaciones) {
    die("<div class='alert alert-danger'>Error al preparar consulta de asociaciones: " . mysqli_error($conexion) . "</div>");
}

mysqli_stmt_bind_param($stmt_asociaciones, "i", $empresa_id);
mysqli_stmt_execute($stmt_asociaciones);
$result_asociaciones = mysqli_stmt_get_result($stmt_asociaciones);

$asociaciones_existentes_map = [];
while ($row = mysqli_fetch_assoc($result_asociaciones)) {
    $asociaciones_existentes_map[$row['producto_id']][$row['imagen_id']] = true;
}
mysqli_stmt_close($stmt_asociaciones);

// ============================================================
// IDENTIFICAR IMÁGENES HUÉRFANAS
// ============================================================
echo "<h3>🔍 Paso 3: Identificando imágenes huérfanas...</h3>";

$imagenes_con_asociacion = [];
foreach ($asociaciones_existentes_map as $producto_id => $imagenes_asociadas) {
    foreach ($imagenes_asociadas as $imagen_id => $value) {
        $imagenes_con_asociacion[$imagen_id] = true;
    }
}

foreach ($imagenes as $imagen) {
    if (!isset($imagenes_con_asociacion[$imagen['imagen_id']])) {
        $imagenes_huerfanas[] = $imagen;
    }
}

$total_huerfanas = count($imagenes_huerfanas);
echo "<div class='alert " . ($total_huerfanas > 0 ? 'alert-warning' : 'alert-success') . "'>";
echo "📊 Imágenes sin producto asociado: <strong>" . $total_huerfanas . "</strong>";
if ($total_huerfanas > 0) {
    echo " <span class='badge bg-danger'>¡Requieren atención!</span>";
}
echo "</div>";

// ============================================================
// PREPARAR CONSULTA PARA INSERTAR ASOCIACIONES
// ============================================================
$sql_insert = "INSERT INTO gestion__productos_imagenes 
               (producto_id, empresa_id, imagen_id, es_principal, orden, tabla_estado_registro_id) 
               VALUES (?, ?, ?, ?, ?, 1)";

$stmt_insert = mysqli_prepare($conexion, $sql_insert);
if (!$stmt_insert) {
    die("<div class='alert alert-danger'>Error al preparar consulta de inserción: " . mysqli_error($conexion) . "</div>");
}

// ============================================================
// PROCESAR CADA PRODUCTO (ASOCIAR IMÁGENES POR CÓDIGO)
// ============================================================
echo "<h3>🔄 Paso 4: Asociando imágenes por código de producto...</h3>";
echo "<div class='progress-bar-container'><div class='progress-bar-fill' id='progressFill'></div></div>";

// Crear mapa de imágenes por nombre sin extensión
$imagenes_por_codigo = [];
foreach ($imagenes as $imagen) {
    $imagenes_por_codigo[$imagen['nombre_sin_extension']] = $imagen['imagen_id'];
}

$procesados = 0;
$asociaciones_creadas = 0;
$asociaciones_existentes = 0;
$productos_sin_imagen = [];
$errores = [];
$logs = [];

foreach ($productos as $producto_codigo => $producto) {
    $procesados++;
    $producto_id = $producto['producto_id'];
    $producto_nombre = $producto['producto_nombre'];
    
    // Buscar imagen que coincida con el código del producto
    if (!isset($imagenes_por_codigo[$producto_codigo])) {
        $productos_sin_imagen[] = $producto;
        $logs[] = [
            'tipo' => 'info',
            'mensaje' => "Producto '{$producto_codigo}' - {$producto_nombre}: No tiene imagen asociada en conf__imagenes"
        ];
        continue;
    }
    
    $imagen_id = $imagenes_por_codigo[$producto_codigo];
    
    // Verificar si ya existe la asociación
    if (isset($asociaciones_existentes_map[$producto_id][$imagen_id])) {
        $asociaciones_existentes++;
        $logs[] = [
            'tipo' => 'skip',
            'mensaje' => "Producto '{$producto_codigo}' - {$producto_nombre}: Ya tiene la imagen asociada (imagen_id: {$imagen_id})"
        ];
        continue;
    }
    
    // Crear la asociación
    $es_principal = 0;
    $orden = 0;
    
    // Verificar si ya hay alguna imagen principal para este producto
    if (isset($asociaciones_existentes_map[$producto_id])) {
        // Ya tiene imágenes, buscar si alguna es principal
        $sql_principal_check = "SELECT COUNT(*) as total FROM gestion__productos_imagenes 
                                WHERE producto_id = ? AND empresa_id = ? AND es_principal = 1";
        $stmt_principal = mysqli_prepare($conexion, $sql_principal_check);
        if ($stmt_principal) {
            mysqli_stmt_bind_param($stmt_principal, "ii", $producto_id, $empresa_id);
            mysqli_stmt_execute($stmt_principal);
            $result_principal = mysqli_stmt_get_result($stmt_principal);
            $row_principal = mysqli_fetch_assoc($result_principal);
            
            if ($row_principal['total'] == 0) {
                $es_principal = 1;
            }
            mysqli_stmt_close($stmt_principal);
        }
    } else {
        // No tiene ninguna imagen, esta será la principal
        $es_principal = 1;
    }
    
    // Insertar la asociación
    mysqli_stmt_bind_param($stmt_insert, "iiiii", $producto_id, $empresa_id, $imagen_id, $es_principal, $orden);
    $success = mysqli_stmt_execute($stmt_insert);
    
    if ($success) {
        $asociaciones_creadas++;
        $logs[] = [
            'tipo' => 'success',
            'mensaje' => "✅ Producto '{$producto_codigo}' - {$producto_nombre}: Asociación creada (imagen_id: {$imagen_id}, principal: " . ($es_principal ? 'SÍ' : 'NO') . ")"
        ];
        // Actualizar el mapa de asociaciones existentes
        $asociaciones_existentes_map[$producto_id][$imagen_id] = true;
    } else {
        $error_msg = mysqli_error($conexion);
        $errores[] = "Error al asociar producto '{$producto_codigo}': " . $error_msg;
        $logs[] = [
            'tipo' => 'error',
            'mensaje' => "❌ Producto '{$producto_codigo}' - {$producto_nombre}: Error al crear asociación: {$error_msg}"
        ];
    }
    
    // Actualizar barra de progreso
    $porcentaje = ($procesados / $total_productos) * 100;
    echo "<script>document.getElementById('progressFill').style.width = '{$porcentaje}%';</script>";
    flush();
}

// Cerrar statement de inserción
mysqli_stmt_close($stmt_insert);

// ============================================================
// MOSTRAR RESULTADOS
// ============================================================
echo "<hr>";
echo "<h3>📊 Resumen del proceso</h3>";

echo "<div class='stats'>";
echo "<div class='stat-card total'><div class='number'>" . $total_productos . "</div><div class='label'>Productos procesados</div></div>";
echo "<div class='stat-card success'><div class='number'>" . $asociaciones_creadas . "</div><div class='label'>Asociaciones creadas ✅</div></div>";
echo "<div class='stat-card skip'><div class='number'>" . $asociaciones_existentes . "</div><div class='label'>Ya existentes ⏳</div></div>";
echo "<div class='stat-card orphan'><div class='number'>" . count($productos_sin_imagen) . "</div><div class='label'>Sin imagen 🚫</div></div>";
echo "<div class='stat-card error'><div class='number'>" . count($errores) . "</div><div class='label'>Errores ❌</div></div>";
echo "</div>";

// ============================================================
// MOSTRAR LISTADO DE IMÁGENES HUÉRFANAS (ACORDEÓN)
// ============================================================
if ($total_huerfanas > 0) {
    echo "<div class='accordion mt-4' id='accordionHuérfanas'>";
    echo "<div class='accordion-item'>";
    echo "<h2 class='accordion-header'>";
    echo "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#collapseHuérfanas' aria-expanded='false'>";
    echo "<i class='fas fa-exclamation-triangle text-warning me-2'></i>";
    echo "Imágenes huérfanas (sin producto asociado) - <strong class='text-danger ms-2'>" . $total_huerfanas . "</strong>";
    echo "</button>";
    echo "</h2>";
    echo "<div id='collapseHuérfanas' class='accordion-collapse collapse' data-bs-parent='#accordionHuérfanas'>";
    echo "<div class='accordion-body p-0'>";
    
    echo "<div class='table-responsive'>";
    echo "<table class='table table-orphans table-hover mb-0'>";
    echo "<thead class='table-light'>";
    echo "<tr>";
    echo "<th>#</th>";
    echo "<th>ID</th>";
    echo "<th>Nombre del archivo</th>";
    echo "<th>Código (sin extensión)</th>";
    echo "<th>Tipo</th>";
    echo "<th>Tamaño</th>";
    echo "<th>Fecha creación</th>";
    echo "<th>Extensión</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $contador = 1;
    foreach ($imagenes_huerfanas as $imagen) {
        $extension = strtoupper(pathinfo($imagen['imagen_nombre'], PATHINFO_EXTENSION));
        $clase_extension = 'other';
        if (in_array(strtolower($extension), ['jpg', 'jpeg'])) $clase_extension = 'jpg';
        elseif (strtolower($extension) == 'png') $clase_extension = 'png';
        elseif (strtolower($extension) == 'gif') $clase_extension = 'gif';
        elseif (strtolower($extension) == 'webp') $clase_extension = 'webp';
        elseif (strtolower($extension) == 'jfif') $clase_extension = 'jfif';
        
        echo "<tr>";
        echo "<td>" . $contador . "</td>";
        echo "<td><span class='badge bg-secondary'>" . $imagen['imagen_id'] . "</span></td>";
        echo "<td><code>" . htmlspecialchars($imagen['imagen_nombre']) . "</code></td>";
        echo "<td><span class='badge bg-info text-dark'>" . htmlspecialchars($imagen['nombre_sin_extension']) . "</span></td>";
        echo "<td><small>" . htmlspecialchars($imagen['imagen_tipo']) . "</small></td>";
        echo "<td>" . formatSize($imagen['imagen_tamanio']) . "</td>";
        echo "<td><small>" . date('d/m/Y H:i', strtotime($imagen['imagen_creacion'])) . "</small></td>";
        echo "<td><span class='badge-extension " . $clase_extension . "'>" . $extension . "</span></td>";
        echo "</tr>";
        $contador++;
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Botón para eliminar imágenes huérfanas (opcional)
    echo "<div class='mt-3'>";
    echo "<button class='btn btn-outline-danger btn-sm' onclick='confirmarEliminacion()'>";
    echo "<i class='fas fa-trash me-1'></i> Eliminar imágenes huérfanas";
    echo "</button>";
    echo " <span class='text-muted small'>(Opción manual - no se ejecuta automáticamente)</span>";
    echo "</div>";
    echo "<script>
        function confirmarEliminacion() {
            Swal.fire({
                title: '¿Eliminar imágenes huérfanas?',
                text: 'Esta acción eliminará " . $total_huerfanas . " imágenes que no están asociadas a ningún producto.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?accion=eliminar_huerfanas';
                }
            });
        }
    </script>";
}

// ============================================================
// PROCESAR ELIMINACIÓN DE IMÁGENES HUÉRFANAS (si se solicita)
// ============================================================
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar_huerfanas') {
    echo "<hr>";
    echo "<h3>🗑️ Eliminando imágenes huérfanas...</h3>";
    
    $eliminados = 0;
    $errores_eliminacion = [];
    
    foreach ($imagenes_huerfanas as $imagen) {
        $sql_delete = "DELETE FROM conf__imagenes WHERE imagen_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "i", $imagen['imagen_id']);
            $success = mysqli_stmt_execute($stmt_delete);
            if ($success) {
                $eliminados++;
                echo "<div class='log-item success'>✅ Eliminada: " . htmlspecialchars($imagen['imagen_nombre']) . "</div>";
            } else {
                $errores_eliminacion[] = "Error al eliminar " . $imagen['imagen_nombre'] . ": " . mysqli_error($conexion);
                echo "<div class='log-item error'>❌ Error al eliminar: " . htmlspecialchars($imagen['imagen_nombre']) . "</div>";
            }
            mysqli_stmt_close($stmt_delete);
        }
    }
    
    echo "<div class='alert " . ($eliminados > 0 ? 'alert-success' : 'alert-warning') . "'>";
    echo "✅ Eliminadas <strong>" . $eliminados . "</strong> imágenes huérfanas.";
    if (!empty($errores_eliminacion)) {
        echo "<br>❌ Errores: " . count($errores_eliminacion);
    }
    echo "</div>";
}

// ============================================================
// MOSTRAR PRODUCTOS SIN IMAGEN (ACORDEÓN)
// ============================================================
if (count($productos_sin_imagen) > 0) {
    echo "<div class='accordion mt-3' id='accordionSinImagen'>";
    echo "<div class='accordion-item'>";
    echo "<h2 class='accordion-header'>";
    echo "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#collapseSinImagen' aria-expanded='false'>";
    echo "<i class='fas fa-image text-warning me-2'></i>";
    echo "Productos sin imagen - <strong class='text-warning ms-2'>" . count($productos_sin_imagen) . "</strong>";
    echo "</button>";
    echo "</h2>";
    echo "<div id='collapseSinImagen' class='accordion-collapse collapse' data-bs-parent='#accordionSinImagen'>";
    echo "<div class='accordion-body p-0'>";
    
    echo "<div class='table-responsive'>";
    echo "<table class='table table-orphans table-hover mb-0'>";
    echo "<thead class='table-light'>";
    echo "<tr><th>#</th><th>ID</th><th>Código</th><th>Nombre</th></tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $contador = 1;
    foreach ($productos_sin_imagen as $producto) {
        echo "<tr>";
        echo "<td>" . $contador . "</td>";
        echo "<td><span class='badge bg-secondary'>" . $producto['producto_id'] . "</span></td>";
        echo "<td><code>" . htmlspecialchars($producto['producto_codigo']) . "</code></td>";
        echo "<td>" . htmlspecialchars($producto['producto_nombre']) . "</td>";
        echo "</tr>";
        $contador++;
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

// ============================================================
// MOSTRAR LOG COMPLETO
// ============================================================
echo "<h4 class='mt-4'>📋 Log completo del proceso</h4>";
echo "<div class='log-container'>";

// Mostrar logs en orden inverso (más recientes primero)
$logs_reverse = array_reverse($logs);
foreach ($logs_reverse as $log) {
    $clase = '';
    switch ($log['tipo']) {
        case 'success': $clase = 'success'; break;
        case 'error': $clase = 'error'; break;
        case 'skip': $clase = 'skip'; break;
        case 'info': $clase = 'info'; break;
        case 'orphan': $clase = 'orphan'; break;
    }
    echo "<div class='log-item " . $clase . "'>" . htmlspecialchars($log['mensaje']) . "</div>";
}
echo "</div>";

// ============================================================
// ESTADÍSTICAS FINALES
// ============================================================
echo "<hr>";
echo "<div class='alert alert-success'>";
echo "<strong>✅ Proceso completado!</strong><br>";
echo "Total de productos: " . $total_productos . "<br>";
echo "Asociaciones creadas: " . $asociaciones_creadas . "<br>";
echo "Asociaciones ya existentes: " . $asociaciones_existentes . "<br>";
echo "Productos sin imagen: " . count($productos_sin_imagen) . "<br>";
echo "Imágenes huérfanas (sin producto): <strong class='text-danger'>" . $total_huerfanas . "</strong><br>";
echo "Errores: " . count($errores) . "<br>";
echo "</div>";

echo "<div class='mt-4 text-center'>";
echo "<a href='productos.php' class='btn btn-outline-secondary'><i class='fas fa-arrow-left me-2'></i>Volver a Productos</a>";
echo " <a href='' class='btn btn-primary'><i class='fas fa-sync me-2'></i>Ejecutar de nuevo</a>";
echo "</div>";

echo "</div>";

// ============================================================
// FOOTER Y SCRIPTS
// ============================================================
echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>";
echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
echo "</body></html>";

// Cerrar conexión
mysqli_close($conexion);
?>