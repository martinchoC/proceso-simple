<?php
// core/assets.php
// URL base y helpers de assets, sin sesión ni base de datos: lo usan config.php
// y las páginas que no lo cargan (impresiones, portal de sindicatos).

if (!function_exists('app_ruta_web')) {
    // Ruta web de la raíz del sistema: la carpeta relativa a la raíz de Apache (DOCUMENT_ROOT), así funciona
    // directo en htdocs (/casalucho), anidado (/web_procesosimple/casalucho) o como raíz del sitio ('').
    // Sin DOCUMENT_ROOT (cron, CLI) o si la carpeta está fuera de él (Alias), el nombre de la carpeta, como antes.
    function app_ruta_web(): string
    {
        $raiz = str_replace('\\', '/', (string)(realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
        $doc = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        $doc = $doc !== '' ? rtrim(str_replace('\\', '/', (string)(realpath($doc) ?: $doc)), '/') : '';
        if ($doc !== '' && ($raiz === $doc || stripos($raiz . '/', $doc . '/') === 0)) {
            return substr($raiz, strlen($doc));
        }
        return '/' . basename($raiz);
    }
}

if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocol . '://' . $host . app_ruta_web());
}

if (!function_exists('asset')) {
    function asset($path)
    {
        return BASE_URL . '/templates/adminlte/' . ltrim($path, '/');
    }
}

if (!function_exists('asset_local')) {
    // Recursos estáticos propios y librerías de terceros en assets/<css|js|fonts>/<libreria>-<version>/
    function asset_local($path)
    {
        return BASE_URL . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url($path = '')
    {
        $cleanPath = ltrim((string)$path, '/');
        // Ocultar extensión .php en enlaces visibles generados por url()
        if (substr($cleanPath, -4) === '.php' 
            && strpos($cleanPath, '_ajax.php') === false 
            && strpos($cleanPath, 'arca/') === false 
            && strpos($cleanPath, 'cron/') === false) {
            $cleanPath = substr($cleanPath, 0, -4);
        }
        return BASE_URL . ($cleanPath !== '' ? '/' . $cleanPath : '');
    }
}
