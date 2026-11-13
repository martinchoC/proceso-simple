<?php
// perf/run_page.php
// Runner CLI para ejecutar una página o endpoint AJAX y capturar profiling

$options = getopt('', ['target:', 'empresa_id::', 'params::']);
$target = $options['target'] ?? null;
if (!$target) {
    die("Falta --target");
}

$fullTarget = realpath(__DIR__ . '/../../' . $target);
if (!$fullTarget || !file_exists($fullTarget)) {
    die("Archivo no encontrado: " . $target);
}

$rootPath = realpath(__DIR__ . '/../..');
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $rootPath);
}

$_SERVER['PERF_RUNNER'] = '1';
$_SERVER['PERF_EMPRESA_ID'] = $options['empresa_id'] ?? 2;
$_SERVER['DOCUMENT_ROOT'] = $rootPath;
$_SERVER['SCRIPT_FILENAME'] = $fullTarget;
$relPath = str_replace('\\', '/', substr($fullTarget, strlen($rootPath)));
$_SERVER['SCRIPT_NAME'] = $relPath;
$_SERVER['PHP_SELF'] = $relPath;
$_SERVER['REQUEST_URI'] = $relPath;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

if (!empty($options['params'])) {
    parse_str($options['params'], $extraParams);
    $_GET = array_merge($_GET, $extraParams);
    $_REQUEST = array_merge($_REQUEST, $extraParams);
}

// Conexión previa y activación de profiling
require_once $rootPath . '/db.php';
$connObj = $conn ?? $conexion ?? null;
if ($connObj instanceof mysqli) {
    mysqli_query($connObj, "SET profiling = 1");
    mysqli_query($connObj, "SET profiling_history_size = 100");
}

chdir(dirname($fullTarget));
error_reporting(E_ALL);
ini_set('display_errors', '1');

$metricasRecogidas = false;

$recolectarMetricas = function() use (&$metricasRecogidas) {
    if ($metricasRecogidas) return;
    $metricasRecogidas = true;

    $endTime = microtime(true);
    $startTime = $GLOBALS['__perf_start_time'] ?? $endTime;
    $totalMs = ($endTime - $startTime) * 1000;
    $peakMemMb = memory_get_peak_usage(true) / (1024 * 1024);

    $html = '';
    while (ob_get_level() > 0) {
        $html .= ob_get_clean();
    }

    $htmlBytes = strlen($html);
    $htmlGzipBytes = strlen(gzencode($html, 6));

    $queries = [];
    $totalMysqlMs = 0;
    $connObj = $GLOBALS['conn'] ?? $GLOBALS['conexion'] ?? null;

    if ($connObj instanceof mysqli) {
        try {
            $res = @mysqli_query($connObj, "SHOW PROFILES");
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $durMs = floatval($r['Duration']) * 1000;
                    $totalMysqlMs += $durMs;
                    $queries[] = [
                        'sql' => $r['Query'],
                        'duration_ms' => $durMs
                    ];
                }
            }
        } catch (\Throwable $exConn) {
            // Conexión cerrada por el script de destino
        }
    }

    $metricas = [
        'ms_total' => round($totalMs, 2),
        'kb_html' => round($htmlBytes / 1024, 2),
        'kb_html_gzip' => round($htmlGzipBytes / 1024, 2),
        'memoria_pico_mb' => round($peakMemMb, 2),
        'cant_consultas' => count($queries),
        'ms_mysql' => round($totalMysqlMs, 2),
        'consultas' => $queries
    ];

    echo "___PERF_METRICS_START___" . json_encode($metricas) . "___PERF_METRICS_END___";
};

register_shutdown_function($recolectarMetricas);

try {
    include $fullTarget;
} catch (\Throwable $e) {
    error_log("Error ejecutando target $target: " . $e->getMessage());
}

$recolectarMetricas();
