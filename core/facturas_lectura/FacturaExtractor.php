<?php
/**
 * Lectura de facturas de proveedores desde un archivo (PDF / JPG / PNG).
 *
 * Pipeline en capas con fallback; todas devuelven un ResultadoExtraccion con el mismo esquema y, por campo,
 * valor + confianza (alta / media / baja) + capa de origen:
 *   1. QR de ARCA (CapaQrArca): emisor, tipo, PV, número, fecha, total y CAE, sin ambigüedad.
 *   2. Texto nativo del PDF (CapaTextoPdf): pdftotext -layout en el servidor o el texto que sacó pdf.js.
 *   3. OCR (CapaOcr): tesseract en el servidor o el texto de tesseract.js en el navegador.
 *   4. IA con visión (CapaIa, proveedor intercambiable: ProveedorIaClaude): solo si las anteriores no
 *      alcanzan, o si el usuario la pide; la empresa la tiene que tener habilitada.
 * Las capas 1–3 usan los binarios del servidor si están (poppler-utils, zbar-tools, tesseract-ocr-spa) y,
 * si no, lo que leyó el navegador (pdf.js, jsQR, tesseract.js): el resultado es el mismo esquema.
 *
 * Este archivo no toca la base: lo que depende de la empresa (tipo de comprobante, proveedor, duplicados,
 * alícuotas) lo resuelve el modelo de la página (gestion_normal/facturas_proveedores_lectura_model.php).
 * Configuración: fl_config() (defaults acá; claves y binarios en un archivo fuera del webroot).
 */

// Las capas se incluyen al final: implementan la interfaz CapaExtraccion de este archivo.

// ---------------------------------------------------------------------------------------------------
// Configuración
// ---------------------------------------------------------------------------------------------------

/**
 * Defaults + el archivo de configuración (PHP que devuelve un array, fuera del webroot). Su ruta sale de la
 * constante FACTURA_LECTURA_CONFIG (definida en .env) o de la variable de entorno del mismo nombre.
 * La API key: variable de entorno ANTHROPIC_API_KEY o 'ia' => ['api_key' => ...] de ese archivo; nunca en el código.
 * Ejemplo comentado: _local/docs/gestion/10_lectura_facturas_proveedores_config.example.php
 */
function fl_config(): array
{
    static $config = null;
    if ($config !== null) return $config;

    $config = [
        'max_bytes' => 10 * 1024 * 1024,
        'tolerancia' => 0.05,                 // diferencia aceptada entre componentes y total
        'texto_minimo' => 40,                 // menos caracteres = PDF escaneado (sin texto)
        'bin' => [                            // ruta del binario; null = se busca en el PATH (no en Windows)
            'pdftotext' => null,
            'pdftoppm' => null,
            'zbarimg' => null,
            'tesseract' => null,
        ],
        'ia' => [
            'proveedor' => 'gemini',
            'modelo' => 'gemini-flash-lite-latest',
            'modelo_gemini' => 'gemini-flash-lite-latest',
            'url_gemini' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'api_key' => '',
            'url' => 'https://api.anthropic.com/v1/messages',
            'version' => '2023-06-01',
            'esfuerzo' => 'medium',
            'max_tokens' => 16000,
            'timeout' => 120,
            'reintentos' => 1,                // reintentos ante 429 / 5xx / error de red
            'fallbacks' => true,              // fallbacks "default" del servidor ante un rechazo del modelo
            'automatica' => true,             // se usa sola cuando las capas locales no alcanzan
            'precio_entrada_mtok' => 0.15,    // USD por millón de tokens (costo estimado del log)
            'precio_salida_mtok' => 0.60,
            'imagen_max_px' => 2000,          // las fotos se achican antes de mandarlas
        ],
        // cron/facturas_proveedores_lectura.php: carpeta de entrada por empresa
        // [empresa_id => ['directorio' => ruta, 'usuario_id' => autor de las facturas, 'sucursal_id' => 0 = la primera]]
        'cron' => ['empresas' => []],
    ];

    $ruta = defined('FACTURA_LECTURA_CONFIG') ? (string)FACTURA_LECTURA_CONFIG : (string)getenv('FACTURA_LECTURA_CONFIG');
    if ($ruta !== '' && is_file($ruta)) {
        $externa = include $ruta;
        if (is_array($externa)) {
            $config = array_replace_recursive($config, $externa);
        }
    }
    $env_gemini = getenv('GEMINI_API_KEY');
    if (is_string($env_gemini) && $env_gemini !== '') {
        $config['ia']['api_key'] = $env_gemini;
        $config['ia']['proveedor'] = 'gemini';
    }
    $env_key = getenv('ANTHROPIC_API_KEY');
    if (is_string($env_key) && $env_key !== '' && $config['ia']['api_key'] === '') {
        $config['ia']['api_key'] = $env_key;
        $config['ia']['proveedor'] = 'claude';
    }
    return $config;
}

/** Ruta del binario si está disponible, o null. */
/**
 * Clave de la API de la IA para la empresa (core/ia_empresa.php: la propia de Configuración > Empresas o la de la
 * empresa principal), descifrada, o '' (= CapaIa usa la del servidor). Nunca se devuelve al navegador ni se loguea.
 * No mira si la empresa tiene la IA habilitada: para eso ia_empresa_habilitada() / ia_empresa_proveedor().
 */
function fl_ia_clave_empresa(mysqli $conexion, int $empresa_id): string
{
    require_once __DIR__ . '/../ia_empresa.php';
    return ia_empresa_clave($conexion, $empresa_id);
}

function fl_bin(string $nombre): ?string
{
    static $cache = [];
    if (array_key_exists($nombre, $cache)) return $cache[$nombre];
    $conf = fl_config()['bin'][$nombre] ?? null;
    if ($conf) {
        return $cache[$nombre] = (is_file($conf) && is_executable($conf)) ? $conf : null;
    }
    if (DIRECTORY_SEPARATOR === '\\' || !function_exists('exec')) {
        return $cache[$nombre] = null;   // en Windows solo con ruta configurada
    }
    $salida = [];
    $codigo = 1;
    @exec('command -v ' . escapeshellarg($nombre) . ' 2>/dev/null', $salida, $codigo);
    $ruta = ($codigo === 0 && !empty($salida[0])) ? trim($salida[0]) : '';
    return $cache[$nombre] = ($ruta !== '' && is_executable($ruta)) ? $ruta : null;
}

/**
 * Ejecuta un binario con argumentos escapados (nunca se arma un comando con nombres de archivo sin escapar).
 * Devuelve [código, salida]. En Linux se corta a los $segundos con timeout(1) si está.
 */
function fl_ejecutar(string $binario, array $args, int $segundos = 60): array
{
    $partes = [escapeshellarg($binario)];
    foreach ($args as $a) {
        $partes[] = escapeshellarg((string)$a);
    }
    $cmd = implode(' ', $partes);
    if (DIRECTORY_SEPARATOR !== '\\' && ($timeout = fl_bin('timeout'))) {
        $cmd = escapeshellarg($timeout) . ' ' . intval($segundos) . ' ' . $cmd;
    }
    $salida = [];
    $codigo = 1;
    exec($cmd . ' 2>&1', $salida, $codigo);
    return [$codigo, implode("\n", $salida)];
}

/** Carpeta temporal propia de una lectura (se borra con fl_borrar_dir). */
function fl_dir_temporal(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fl_' . bin2hex(random_bytes(6));
    if (!mkdir($dir, 0700, true)) {
        throw new RuntimeException('No se pudo crear la carpeta temporal de lectura.');
    }
    return $dir;
}

function fl_borrar_dir(string $dir): void
{
    if ($dir === '' || !is_dir($dir) || strpos(basename($dir), 'fl_') !== 0) return;
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
        if (is_file($f)) @unlink($f);
    }
    @rmdir($dir);
}

// ---------------------------------------------------------------------------------------------------
// Utilidades de dominio
// ---------------------------------------------------------------------------------------------------

/** CUIT válido: 11 dígitos, prefijo conocido y dígito verificador módulo 11. */
function fl_cuit_valido(string $cuit): bool
{
    $c = preg_replace('/\D/', '', $cuit);
    if (strlen($c) !== 11 || !in_array(substr($c, 0, 2), ['20', '23', '24', '25', '26', '27', '30', '33', '34'], true)) {
        return false;
    }
    $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $suma += intval($c[$i]) * $pesos[$i];
    }
    $dv = 11 - ($suma % 11);
    if ($dv === 11) $dv = 0;
    if ($dv === 10) return false;
    return $dv === intval($c[10]);
}

/** Importe en formato argentino o internacional ("1.234,56", "1,234.56", "1234,56", "$ 10") a float; null si no es número. */
function fl_numero($valor): ?float
{
    if ($valor === null) return null;
    if (is_int($valor) || is_float($valor)) return round((float)$valor, 2);
    $s = preg_replace('/[^\d.,\-]/', '', (string)$valor);
    if ($s === '' || $s === '-' || !preg_match('/\d/', $s)) return null;
    $neg = strpos($s, '-') === 0;
    $s = str_replace('-', '', $s);
    $ult_punto = strrpos($s, '.');
    $ult_coma = strrpos($s, ',');
    if ($ult_punto !== false && $ult_coma !== false) {
        $dec = $ult_punto > $ult_coma ? '.' : ',';
        $mil = $dec === '.' ? ',' : '.';
        $s = str_replace([$mil, $dec], ['', '.'], $s);
    } elseif ($ult_coma !== false) {
        // "1,234,567" = miles; una sola coma = decimal (formato argentino)
        $s = substr_count($s, ',') > 1 ? str_replace(',', '', $s) : str_replace(',', '.', $s);
    } elseif ($ult_punto !== false) {
        // "1.234.567" o "1.234" = miles; "12.50" = decimal
        if (substr_count($s, '.') > 1 || strlen($s) - $ult_punto - 1 === 3) {
            $s = str_replace('.', '', $s);
        }
    }
    if (!is_numeric($s)) return null;
    return round(($neg ? -1 : 1) * (float)$s, 2);
}

/** Fecha dd/mm/aaaa, dd-mm-aaaa, dd.mm.aaaa, aaaa-mm-dd o aaaammdd a aaaa-mm-dd; null si no es válida. */
function fl_fecha($valor): ?string
{
    $s = trim((string)$valor);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $s, $m)) {
        $a = (int)$m[3];
        if ($a < 100) $a += 2000;
        if (checkdate((int)$m[2], (int)$m[1], $a)) return sprintf('%04d-%02d-%02d', $a, $m[2], $m[1]);
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $s, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    return null;
}

/** Códigos de comprobante de ARCA: [clase, letra] por código y código por clase + letra. */
const FL_TIPOS_ARCA = [
    1 => ['FACTURA', 'A'], 2 => ['NOTA_DEBITO', 'A'], 3 => ['NOTA_CREDITO', 'A'], 4 => ['RECIBO', 'A'],
    6 => ['FACTURA', 'B'], 7 => ['NOTA_DEBITO', 'B'], 8 => ['NOTA_CREDITO', 'B'], 9 => ['RECIBO', 'B'],
    11 => ['FACTURA', 'C'], 12 => ['NOTA_DEBITO', 'C'], 13 => ['NOTA_CREDITO', 'C'], 15 => ['RECIBO', 'C'],
    19 => ['FACTURA', 'E'], 20 => ['NOTA_DEBITO', 'E'], 21 => ['NOTA_CREDITO', 'E'],
    51 => ['FACTURA', 'M'], 52 => ['NOTA_DEBITO', 'M'], 53 => ['NOTA_CREDITO', 'M'], 54 => ['RECIBO', 'M'],
    201 => ['FACTURA', 'A'], 202 => ['NOTA_DEBITO', 'A'], 203 => ['NOTA_CREDITO', 'A'],
    206 => ['FACTURA', 'B'], 207 => ['NOTA_DEBITO', 'B'], 208 => ['NOTA_CREDITO', 'B'],
    211 => ['FACTURA', 'C'], 212 => ['NOTA_DEBITO', 'C'], 213 => ['NOTA_CREDITO', 'C'],
    81 => ['FACTURA', 'A'], 82 => ['FACTURA', 'B'], 83 => ['FACTURA', 'B'], 111 => ['FACTURA', 'C'],
];

function fl_codigo_arca(string $clase, string $letra, bool $fce = false): ?int
{
    $letra = strtoupper($letra);
    if ($fce) {
        $base = ['A' => 200, 'B' => 205, 'C' => 210][$letra] ?? null;
        $off = ['FACTURA' => 1, 'NOTA_DEBITO' => 2, 'NOTA_CREDITO' => 3][$clase] ?? null;
        return ($base !== null && $off !== null) ? $base + $off : null;
    }
    foreach (FL_TIPOS_ARCA as $cod => [$c, $l]) {
        if ($cod < 81 && $c === $clase && $l === $letra) return $cod;
    }
    return null;
}

const FL_NOMBRES_CLASE = ['FACTURA' => 'Factura', 'NOTA_DEBITO' => 'Nota de Débito', 'NOTA_CREDITO' => 'Nota de Crédito', 'RECIBO' => 'Recibo'];

/** Alícuotas de IVA reconocidas, como claves de texto ("21.00"). */
const FL_ALICUOTAS = ['27.00', '21.00', '10.50', '5.00', '2.50', '0.00'];

// ---------------------------------------------------------------------------------------------------
// Documento de entrada y resultado normalizado
// ---------------------------------------------------------------------------------------------------

/** Archivo a leer y lo que ya leyó el navegador (QR, texto de pdf.js, texto del OCR): datos no confiables. */
class DocumentoFactura
{
    public string $ruta;
    public string $mime;
    public string $cuitEmpresa;
    public ?string $qrCliente;
    public string $textoCliente;
    public string $ocrCliente;
    public ?float $ocrConfianzaCliente;

    public function __construct(string $ruta, string $mime, string $cuitEmpresa, array $cliente = [])
    {
        $this->ruta = $ruta;
        $this->mime = $mime;
        $this->cuitEmpresa = preg_replace('/\D/', '', $cuitEmpresa);
        $qr = trim((string)($cliente['qr'] ?? ''));
        $this->qrCliente = $qr !== '' ? mb_substr($qr, 0, 4000) : null;
        $this->textoCliente = mb_substr((string)($cliente['texto'] ?? ''), 0, 200000);
        $this->ocrCliente = mb_substr((string)($cliente['ocr'] ?? ''), 0, 200000);
        $conf = $cliente['ocr_confianza'] ?? null;
        $this->ocrConfianzaCliente = is_numeric($conf) ? max(0.0, min(100.0, (float)$conf)) : null;
    }

    public function esPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }
}

/**
 * Resultado de una capa o de la combinación de varias: campos del esquema normalizado con valor, confianza y
 * capa; ítems; percepciones de IIBB por jurisdicción; avisos.
 */
class ResultadoExtraccion
{
    /** Campos del esquema. iva_alicuotas = ['21.00' => importe, ...] (de ahí salen iva_21 / iva_10_5 / iva_27 / otros_iva). */
    const CAMPOS = ['emisor_cuit', 'emisor_razon_social', 'receptor_cuit', 'tipo_comprobante', 'letra', 'punto_venta',
                    'numero', 'fecha_emision', 'moneda', 'cotizacion', 'neto_gravado', 'subtotal', 'iva_alicuotas',
                    'no_gravado', 'exento', 'percepciones_iibb', 'percepciones_iva', 'otros_tributos', 'total', 'cae',
                    'cae_vencimiento', 'condicion_pago', 'descuento_general', 'descuento_general_pct'];
    const OBLIGATORIOS = ['emisor_cuit', 'tipo_comprobante', 'punto_venta', 'numero', 'fecha_emision', 'total'];
    const CONFIANZAS = ['baja' => 1, 'media' => 2, 'alta' => 3];

    /** @var array<string, array{valor:mixed, confianza:string, capa:string}> */
    public array $campos = [];
    /** Ítems leídos: codigo, descripcion, cantidad, precio_unitario, alicuota, subtotal. */
    public array $items = [];
    /** Percepciones de IIBB por provincia: [['jurisdiccion' => texto, 'importe' => float]]. */
    public array $percepcionesIibb = [];
    public array $avisos = [];
    /** Capas que aportaron algo, en orden. */
    public array $capas = [];

    public function set(string $campo, $valor, string $confianza, string $capa): void
    {
        if ($valor === null || $valor === '' || $valor === []) return;
        $this->campos[$campo] = ['valor' => $valor, 'confianza' => $confianza, 'capa' => $capa];
        if (!in_array($capa, $this->capas, true)) $this->capas[] = $capa;
    }

    public function valor(string $campo)
    {
        return $this->campos[$campo]['valor'] ?? null;
    }

    public function tiene(string $campo): bool
    {
        return isset($this->campos[$campo]);
    }

    public function confianza(string $campo): ?string
    {
        return $this->campos[$campo]['confianza'] ?? null;
    }

    public function bajarConfianza(string $campo, string $a): void
    {
        if (isset($this->campos[$campo]) && self::CONFIANZAS[$a] < self::CONFIANZAS[$this->campos[$campo]['confianza']]) {
            $this->campos[$campo]['confianza'] = $a;
        }
    }

    public function subirConfianza(string $campo, string $a): void
    {
        if (isset($this->campos[$campo]) && self::CONFIANZAS[$a] > self::CONFIANZAS[$this->campos[$campo]['confianza']]) {
            $this->campos[$campo]['confianza'] = $a;
        }
    }

    /**
     * Incorpora otra capa. $modo 'completar': solo los campos que faltan. 'reemplazar': pisa todo salvo lo que vino
     * del QR (la IA corrige lo que el texto / OCR leyó mal). Ítems y percepciones por provincia se toman de la
     * otra capa si esta no tiene.
     */
    public function combinar(ResultadoExtraccion $otro, string $modo = 'completar'): void
    {
        foreach ($otro->campos as $campo => $c) {
            $actual = $this->campos[$campo] ?? null;
            if ($actual === null || ($modo === 'reemplazar' && $actual['capa'] !== CapaQrArca::NOMBRE)) {
                $this->set($campo, $c['valor'], $c['confianza'], $c['capa']);
            }
        }
        if ((!$this->items || $modo === 'reemplazar') && $otro->items) $this->items = $otro->items;
        if ((!$this->percepcionesIibb || $modo === 'reemplazar') && $otro->percepcionesIibb) $this->percepcionesIibb = $otro->percepcionesIibb;
        foreach ($otro->avisos as $a) {
            if (!in_array($a, $this->avisos, true)) $this->avisos[] = $a;
        }
        foreach ($otro->capas as $c) {
            if (!in_array($c, $this->capas, true)) $this->capas[] = $c;
        }
    }

    /** IVA total discriminado. */
    public function ivaTotal(): float
    {
        return round(array_sum(array_map('floatval', (array)($this->valor('iva_alicuotas') ?? []))), 2);
    }

    public function faltantes(): array
    {
        return array_values(array_filter(self::OBLIGATORIOS, fn($c) => !$this->tiene($c)));
    }

    /** Para el log y para rearmar la propuesta sin volver a leer el archivo. */
    public function aArray(): array
    {
        return ['campos' => $this->campos, 'items' => $this->items, 'percepciones_iibb' => $this->percepcionesIibb,
                'avisos' => $this->avisos, 'capas' => $this->capas];
    }

    public static function desdeArray(array $a): self
    {
        $r = new self();
        $r->campos = (array)($a['campos'] ?? []);
        $r->items = (array)($a['items'] ?? []);
        $r->percepcionesIibb = (array)($a['percepciones_iibb'] ?? []);
        $r->avisos = (array)($a['avisos'] ?? []);
        $r->capas = (array)($a['capas'] ?? []);
        return $r;
    }

    /** Campos para mostrar: el esquema pedido (iva_21, iva_10_5, iva_27, otros_iva) con valor, confianza y capa. */
    public function camposParaMostrar(): array
    {
        $out = [];
        foreach ($this->campos as $campo => $c) {
            if ($campo === 'iva_alicuotas') continue;
            $out[$campo] = $c;
        }
        if ($this->tiene('iva_alicuotas')) {
            $base = $this->campos['iva_alicuotas'];
            $al = (array)$base['valor'];
            foreach (['21.00' => 'iva_21', '10.50' => 'iva_10_5', '27.00' => 'iva_27'] as $pct => $nombre) {
                if (isset($al[$pct])) $out[$nombre] = ['valor' => $al[$pct]] + $base;
            }
            $otros = round(floatval($al['5.00'] ?? 0) + floatval($al['2.50'] ?? 0), 2);
            if ($otros != 0.0) $out['otros_iva'] = ['valor' => $otros] + $base;
        }
        return $out;
    }
}

/** Contrato de cada capa. Devuelve null si no aplica o no leyó nada. */
interface CapaExtraccion
{
    public function nombre(): string;
    public function extraer(DocumentoFactura $doc): ?ResultadoExtraccion;
}

// ---------------------------------------------------------------------------------------------------
// Validaciones que no dependen de la base
// ---------------------------------------------------------------------------------------------------

class ValidadorFactura
{
    /**
     * Valida y ajusta el resultado (Factura B / C sin IVA: el neto va a no gravado; confianza de los importes según
     * si la aritmética cierra). Devuelve ['errores' => [campo => motivo], 'avisos' => [...]].
     */
    public static function validar(ResultadoExtraccion $r, string $cuitEmpresa): array
    {
        $errores = [];
        $avisos = [];
        $tol = (float)fl_config()['tolerancia'];

        foreach ($r->faltantes() as $f) {
            $errores[$f] = 'No se pudo leer';
        }

        $cuit = (string)$r->valor('emisor_cuit');
        if ($cuit !== '' && !fl_cuit_valido($cuit)) {
            $errores['emisor_cuit'] = 'El dígito verificador del CUIT no es correcto';
        }
        if ($cuit !== '' && $cuitEmpresa !== '' && $cuit === $cuitEmpresa) {
            $errores['emisor_cuit'] = 'El CUIT leído como emisor es el de esta empresa';
        }
        $receptor = (string)$r->valor('receptor_cuit');
        if ($receptor !== '' && $cuitEmpresa !== '' && $receptor !== $cuitEmpresa && strlen($receptor) === 11) {
            $avisos[] = 'El comprobante está emitido a otro CUIT (' . $receptor . '), no a esta empresa.';
        }

        $fecha = (string)$r->valor('fecha_emision');
        if ($fecha !== '' && $fecha > date('Y-m-d')) {
            $errores['fecha_emision'] = 'La fecha de emisión es futura';
        }
        $vto = (string)$r->valor('cae_vencimiento');
        $cae = (string)$r->valor('cae');
        if ($cae !== '' && !preg_match('/^\d{14}$/', $cae)) {
            $errores['cae'] = 'El CAE tiene que tener 14 dígitos';
        }
        if ($vto !== '' && $fecha !== '' && $vto < $fecha) {
            $errores['cae_vencimiento'] = 'El vencimiento del CAE es anterior a la emisión';
        }

        $moneda = strtoupper((string)$r->valor('moneda'));
        if ($moneda !== '' && !in_array($moneda, ['PES', 'ARS', '$'], true)) {
            $errores['moneda'] = 'Por ahora solo se cargan comprobantes en pesos';
        }

        // Letra y coherencia con el IVA discriminado
        $codigo = intval($r->valor('tipo_comprobante'));
        $letra = $codigo && isset(FL_TIPOS_ARCA[$codigo]) ? FL_TIPOS_ARCA[$codigo][1] : strtoupper((string)$r->valor('letra'));
        if ($letra !== '' && !$r->tiene('letra')) $r->set('letra', $letra, $r->confianza('tipo_comprobante') ?? 'media', $r->campos['tipo_comprobante']['capa'] ?? 'TEXTO');
        $iva = $r->ivaTotal();
        if ($letra === 'C' && $iva > $tol) {
            $errores['iva_alicuotas'] = 'Una Factura C no discrimina IVA';
        }

        $total = $r->valor('total');
        $no_grav = (float)($r->valor('no_gravado') ?? 0);
        $exento = (float)($r->valor('exento') ?? 0);
        $perc = (float)($r->valor('percepciones_iibb') ?? 0) + (float)($r->valor('percepciones_iva') ?? 0) + (float)($r->valor('otros_tributos') ?? 0);
        $neto = $r->valor('neto_gravado');

        // B / C sin IVA discriminado: lo que no es exento ni tributo va a no gravado (igual que la importación de ARCA)
        if (in_array($letra, ['B', 'C'], true) && $iva <= $tol && $total !== null) {
            $resto = round((float)$total - $exento - $perc, 2);
            $subtotal = $r->valor('subtotal') ?? $neto;
            if ($subtotal !== null && abs((float)$subtotal + $no_grav - $resto) > $tol && abs((float)$subtotal - $resto) > $tol) {
                $errores['total'] = 'El subtotal ($' . self::fmt((float)$subtotal) . ') más los tributos no da el total ($' . self::fmt((float)$total) . ')';
            } else {
                $capa = $r->campos['total']['capa'];
                $r->set('no_gravado', max(0.0, $resto), $r->confianza('total'), $capa);
                unset($r->campos['neto_gravado']);
                $no_grav = max(0.0, $resto);
                $neto = null;
            }
        } elseif (in_array($letra, ['A', 'M'], true) && $iva <= $tol && (float)$neto > $tol) {
            $avisos[] = 'Es un comprobante ' . $letra . ' pero no se leyó el IVA discriminado.';
        }

        // Aritmética: neto + IVA + no gravado + exento + percepciones / tributos = total
        if ($total !== null && !isset($errores['total'])) {
            if ($neto === null && in_array($letra, ['B', 'C'], true) && !$r->tiene('no_gravado')) $neto = $r->valor('subtotal');
            $suma = round((float)$neto + $iva + $no_grav + $exento + $perc, 2);
            $dif = round((float)$total - $suma, 2);
            $importes = ['neto_gravado', 'iva_alicuotas', 'no_gravado', 'exento', 'percepciones_iibb', 'percepciones_iva', 'otros_tributos', 'total'];
            if (abs($dif) > $tol) {
                $errores['total'] = 'Los componentes suman $' . self::fmt($suma) . ' y el total es $' . self::fmt((float)$total)
                                  . ' (diferencia $' . self::fmt($dif) . ')';
                foreach ($importes as $c) $r->bajarConfianza($c, 'baja');
            } else {
                foreach ($importes as $c) {
                    if ($r->confianza($c) === 'media') $r->subirConfianza($c, 'alta');
                }
            }
        }

        // IVA de cada alícuota contra su base: si las bases suman el neto, se usan para las líneas
        $al = (array)($r->valor('iva_alicuotas') ?? []);
        if (count(array_filter($al, fn($v) => abs((float)$v) > $tol)) > 1 && $neto !== null) {
            $bases = 0.0;
            foreach ($al as $pct => $importe) {
                if ((float)$pct > 0) $bases += (float)$importe / ((float)$pct / 100);
            }
            if (abs($bases - (float)$neto) > max(1.0, (float)$neto * 0.005)) {
                $avisos[] = 'Hay varias alícuotas de IVA y el neto leído no coincide con sus bases: revise el detalle por alícuota.';
            }
        }

        foreach ($r->percepcionesIibb as $p) {
            if (trim((string)($p['jurisdiccion'] ?? '')) === '') {
                $avisos[] = 'Hay percepciones de IIBB sin provincia: elíjala antes de guardar.';
                break;
            }
        }

        return ['errores' => $errores, 'avisos' => array_values(array_unique(array_merge($r->avisos, $avisos)))];
    }

    private static function fmt(float $n): string
    {
        return number_format($n, 2, ',', '.');
    }
}

// ---------------------------------------------------------------------------------------------------
// Orquestador
// ---------------------------------------------------------------------------------------------------

class FacturaExtractor
{
    /** @var CapaExtraccion[] */
    private array $capasLocales;

    public function __construct(?array $capasLocales = null)
    {
        $this->capasLocales = $capasLocales ?? [new CapaQrArca(), new CapaTextoPdf(), new CapaOcr()];
    }

    /**
     * Capas locales en orden: QR, después texto del PDF y, si no hubo texto, OCR. Se corta cuando los datos
     * validan. Devuelve ['resultado', 'validacion', 'completo', 'traza' => [[capa, ok, ms]]].
     */
    public function extraer(DocumentoFactura $doc): array
    {
        $r = new ResultadoExtraccion();
        $traza = [];
        $hubo_texto = false;
        foreach ($this->capasLocales as $capa) {
            if ($capa instanceof CapaOcr && $hubo_texto) continue;          // el PDF tiene texto: no hace falta OCR
            if ($capa instanceof CapaTextoPdf && !$doc->esPdf()) continue;   // imagen: va directo al OCR
            $t0 = microtime(true);
            try {
                $parcial = $capa->extraer($doc);
            } catch (Throwable $e) {
                error_log('FacturaExtractor ' . $capa->nombre() . ': ' . $e->getMessage());
                $parcial = null;
            }
            $traza[] = ['capa' => $capa->nombre(), 'ok' => $parcial !== null, 'ms' => intval((microtime(true) - $t0) * 1000)];
            if ($parcial === null) continue;
            if ($capa instanceof CapaTextoPdf) $hubo_texto = true;
            self::compararConQr($r, $parcial);
            $r->combinar($parcial, 'completar');

            $prueba = ResultadoExtraccion::desdeArray($r->aArray());
            $v = ValidadorFactura::validar($prueba, $doc->cuitEmpresa);
            $con_importes = $r->tiene('neto_gravado') || $r->tiene('subtotal') || $r->tiene('no_gravado');
            if (!$v['errores'] && $con_importes) break;   // el QR solo no trae el desglose: sigue a la capa de texto
        }
        $validacion = ValidadorFactura::validar($r, $doc->cuitEmpresa);
        return ['resultado' => $r, 'validacion' => $validacion, 'completo' => !$validacion['errores'], 'traza' => $traza];
    }

    /** Lectura con la IA sobre lo ya leído: la IA corrige lo del texto / OCR, el QR prevalece. */
    public function extraerConIa(DocumentoFactura $doc, ?ResultadoExtraccion $previo, CapaIa $ia): array
    {
        $r = $previo ? ResultadoExtraccion::desdeArray($previo->aArray()) : new ResultadoExtraccion();
        $t0 = microtime(true);
        $parcial = $ia->extraer($doc);
        $traza = [['capa' => $ia->nombre(), 'ok' => $parcial !== null, 'ms' => intval((microtime(true) - $t0) * 1000)]];
        if ($parcial !== null) {
            self::compararConQr($r, $parcial);
            $r->combinar($parcial, 'reemplazar');
        }
        $validacion = ValidadorFactura::validar($r, $doc->cuitEmpresa);
        return ['resultado' => $r, 'validacion' => $validacion, 'completo' => !$validacion['errores'], 'traza' => $traza,
                'uso' => $ia->ultimoUso()];
    }

    /** Si el texto (u otra capa) contradice al QR en total o número, prevalece el QR y se avisa. */
    private static function compararConQr(ResultadoExtraccion $base, ResultadoExtraccion $nuevo): void
    {
        $etiquetas = ['total' => 'el total', 'numero' => 'el número', 'punto_venta' => 'el punto de venta',
                      'emisor_cuit' => 'el CUIT del emisor', 'fecha_emision' => 'la fecha', 'cae' => 'el CAE'];
        foreach ($etiquetas as $campo => $etiqueta) {
            if (($base->campos[$campo]['capa'] ?? '') !== CapaQrArca::NOMBRE || !$nuevo->tiene($campo)) continue;
            $a = $base->valor($campo);
            $b = $nuevo->valor($campo);
            $distinto = is_numeric($a) && is_numeric($b) ? abs((float)$a - (float)$b) > 0.009 : (string)$a !== (string)$b;
            if ($distinto) {
                $base->avisos[] = 'El ' . strtolower($nuevo->campos[$campo]['capa']) . ' dice ' . $b . ' para ' . $etiqueta
                                . ', pero el QR de ARCA dice ' . $a . ': se toma el del QR.';
            }
        }
    }
}

require_once __DIR__ . '/ParserTextoFactura.php';
require_once __DIR__ . '/CapaQrArca.php';
require_once __DIR__ . '/CapaTextoPdf.php';
require_once __DIR__ . '/CapaOcr.php';
require_once __DIR__ . '/CapaIa.php';
