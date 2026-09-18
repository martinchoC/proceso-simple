<?php

declare(strict_types=1);

use App\Support\Config;

if (!function_exists('e')) {
    /** Escape HTML obligatorio para TODA salida dinámica en vistas. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_path_uri')) {
    /**
     * Prefijo de URL del sitio deducido del propio servidor.
     *
     * Se usa SCRIPT_NAME (valor generado por el servidor, no por el cliente) en
     * lugar de HTTP_HOST, que es falsificable. El resultado es una URL relativa
     * a la raíz del dominio, por lo que funciona igual en dominio principal,
     * subdominio o subdirectorio, con o sin HTTPS, sin configurar nada.
     */
    function base_path_uri(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = str_replace('\\', '/', dirname($script));

        return $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }
}

if (!function_exists('url')) {
    /**
     * URL absoluta si APP_URL está definida; si no, relativa a la raíz.
     * Dejar APP_URL vacía es lo recomendado en hosting compartido: evita tener
     * que tocar el .env al mover el sitio entre dominio, subdominio o staging.
     */
    function url(string $path = ''): string
    {
        $base = (string) Config::get('app.url', '');
        if ($base === '') {
            $base = base_path_uri();
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * URL de un archivo de /assets con "cache busting".
     *
     * Le agrega ?v=<fecha de modificación> para que, al subir una versión
     * nueva del CSS o del JS, el navegador la descargue en lugar de servir la
     * copia cacheada (el .htaccess pide 30 días de caché para estáticos).
     */
    function asset(string $ruta): string
    {
        $relativa = '/assets/' . ltrim($ruta, '/');

        // SCRIPT_FILENAME apunta al index.php, es decir a la carpeta pública.
        $publico = dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $archivo = $publico . $relativa;

        $version = is_file($archivo) ? (string) filemtime($archivo) : '';

        return url($relativa) . ($version !== '' ? '?v=' . $version : '');
    }
}

if (!function_exists('url_catalogo')) {
    /**
     * Arma la URL del catálogo preservando los filtros activos.
     * http_build_query escapa los valores, así que es seguro con texto libre.
     *
     * @param array<string,mixed> $filtros
     */
    function url_catalogo(array $filtros): string
    {
        $params = array_filter([
            'q'            => array_values($filtros['terminos'] ?? []),
            'categorias'   => array_values($filtros['categorias'] ?? []),
            'marca_id'     => (int) ($filtros['marca_id'] ?? 0),
            'modelo_id'    => (int) ($filtros['modelo_id'] ?? 0),
            'submodelo_id' => (int) ($filtros['submodelo_id'] ?? 0),
            'pagina'       => (int) ($filtros['pagina'] ?? 0),
        ], static fn ($v): bool => $v !== '' && $v !== 0 && $v !== []);

        $query = http_build_query($params);

        return url('/catalogo') . ($query !== '' ? '?' . $query : '');
    }
}

if (!function_exists('money')) {
    function money(float $amount): string
    {
        return '$ ' . number_format($amount, 2, ',', '.');
    }
}
