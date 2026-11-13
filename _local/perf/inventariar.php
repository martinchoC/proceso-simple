<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../../db.php';

// Mapeo de carpetas de módulos
$modulos = [
    'conf' => ['id' => 1, 'path' => 'modules/conf'],
    'gestion' => ['id' => 2, 'path' => 'modules/gestion'],
    'ml' => ['id' => 3, 'path' => 'modules/ml'],
    'estudio_contable' => ['id' => 4, 'path' => 'modules/estudio_contable'],
    'gestion_normal' => ['id' => 5, 'path' => 'modules/gestion_normal'],
    'sindicatos' => ['id' => 6, 'path' => 'modules/sindicatos']
];

// Obtener páginas de conf__paginas
$dbPages = [];
$res = mysqli_query($conn, "SELECT modulo_id, pagina_id, pagina, url FROM conf__paginas WHERE tabla_estado_registro_id = 1");
while ($r = mysqli_fetch_assoc($res)) {
    $dbPages[$r['modulo_id']][] = $r;
}

$inventario = [];

foreach ($modulos as $modSlug => $info) {
    $dir = __DIR__ . '/../../' . $info['path'];
    if (!is_dir($dir)) continue;

    $files = scandir($dir);
    foreach ($files as $f) {
        if (!str_ends_with($f, '.php')) continue;

        // Descartar archivos que no son de pantalla
        if (str_ends_with($f, '_ajax.php') || str_ends_with($f, '-ajax.php') ||
            str_ends_with($f, '_model.php') || str_ends_with($f, '_funciones.php') ||
            in_array($f, ['header1.php', 'footer1.php', 'menu1.php', 'constantes.php', 'db.php', 'funciones.php'])) {
            continue;
        }

        $fullPath = $dir . '/' . $f;
        $content = file_get_contents($fullPath);

        // Detectar si es una pantalla real: suele definir $pagina_idx o incluir header1.php o templates/header.php
        $esPantalla = false;
        if (strpos($content, 'header1.php') !== false || 
            strpos($content, 'pagina_idx') !== false || 
            strpos($content, 'header.php') !== false ||
            strpos($content, 'layout') !== false) {
            $esPantalla = true;
        }

        if (isset($dbPages[$info['id']])) {
            foreach ($dbPages[$info['id']] as $dbp) {
                if ($dbp['url'] === $f || $dbp['url'] === str_replace('.php', '', $f)) {
                    $esPantalla = true;
                    break;
                }
            }
        }

        if (!$esPantalla) {
            continue;
        }

        // Clasificar ejecutabilidad dinámica con reglas de seguridad estrictas:
        $ejecutable = 1;
        $motivo = '';

        // 1. ARCA / Servicios externos
        if (preg_match('/(^|_)arca(_|\.|$)/i', $f) || stripos($content, 'wsaa') !== false || stripos($content, 'solicitarcae') !== false) {
            $ejecutable = 0;
            $motivo = 'Llamada o configuración de servicios externos (ARCA/AFIP)';
        }
        // 2. Procesos masivos, recálculos, motores y generadores automáticos (pueden modificar registros masivamente al ejecutarse)
        elseif (stripos($f, 'generador') !== false || stripos($f, 'recalculos') !== false || stripos($f, 'cargas_masivas') !== false || stripos($f, 'cambios_masivos') !== false) {
            $ejecutable = 0;
            $motivo = 'Motor de generación o modificación masiva de registros';
        }
        // 3. Importaciones o migraciones de datos
        elseif (stripos($f, 'importar') !== false || stripos($f, 'migrar') !== false) {
            $ejecutable = 0;
            $motivo = 'Script de importación / migración de datos';
        }
        // 4. Descargas directas binarias (PDF / Excel que cortan ejecución con header/exit)
        elseif (stripos($f, 'descargar') !== false || stripos($f, 'exportar') !== false) {
            $ejecutable = 0;
            $motivo = 'Descarga directa de archivos binarios (PDF/Excel)';
        }
        // 5. Cierres fiscales o contables (operaciones destructivas o de bloqueo de períodos)
        elseif (stripos($f, 'cierre') !== false) {
            $ejecutable = 0;
            $motivo = 'Operación de cierre fiscal o contable (modificación de períodos)';
        }
        // 6. Envíos de correo
        elseif (preg_match('/\bmail\s*\(|\bmb_send_mail\s*\(|PHPMailer|SendGrid/i', $content)) {
            $ejecutable = 0;
            $motivo = 'Envío de correo electrónico directo';
        }

        $inventario[] = [
            'modulo' => $modSlug,
            'archivo' => $f,
            'ruta' => $info['path'] . '/' . $f,
            'ejecutable' => $ejecutable,
            'motivo' => $motivo
        ];
    }
}

// Guardar en perf_paginas
mysqli_query($conn, "TRUNCATE TABLE perf_paginas");
$stmt = mysqli_prepare($conn, "INSERT INTO perf_paginas (modulo, archivo, ruta, ejecutable_dinamico, motivo_no_ejecutada) VALUES (?, ?, ?, ?, ?)");

foreach ($inventario as $p) {
    mysqli_stmt_bind_param($stmt, "sssis", $p['modulo'], $p['archivo'], $p['ruta'], $p['ejecutable'], $p['motivo']);
    mysqli_stmt_execute($stmt);
}

echo "Total páginas inventariadas: " . count($inventario) . PHP_EOL;

// Resumen por módulo
$res = mysqli_query($conn, "SELECT modulo, COUNT(*) as total, SUM(ejecutable_dinamico) as ejecutables, SUM(CASE WHEN ejecutable_dinamico=0 THEN 1 ELSE 0 END) as no_ejecutables FROM perf_paginas GROUP BY modulo ORDER BY modulo");
while ($r = mysqli_fetch_assoc($res)) {
    echo sprintf("Módulo: %-18s | Total: %3d | Ejecutables: %3d | No ejecutables: %2d\n", $r['modulo'], $r['total'], $r['ejecutables'], $r['no_ejecutables']);
}
