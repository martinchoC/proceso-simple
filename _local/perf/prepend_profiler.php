<?php
// perf/prepend_profiler.php
// Pre-inicializador de entorno de test

if (php_sapi_name() === 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['usuario_id'] = 1;
    $_SESSION['usuario'] = 'pablo';
    $_SESSION['usuario_nombre'] = 'Pablo Passoni';
    $_SESSION['logueado'] = true;
    $_SESSION['empresa_id'] = isset($_SERVER['PERF_EMPRESA_ID']) ? (int)$_SERVER['PERF_EMPRESA_ID'] : 2;
    
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    $GLOBALS['__perf_start_time'] = microtime(true);
    $GLOBALS['__perf_start_mem'] = memory_get_usage(true);
    
    ob_start();
}
