<?php
/**
 * Asistente IA: diálogo con herramientas (tool use) contra Claude (Messages API) o Gemini (generateContent),
 * por HTTP con curl (el proyecto no usa composer). El proveedor sale de la clave de la empresa como en la lectura
 * de facturas (CapaIa::proveedorDesdeConfig: AIza... = Gemini, sk-ant-... = Claude).
 *
 * El historial se guarda en el formato NATIVO de cada proveedor y se reenvía tal cual en cada pedido: Claude
 * exige que los bloques de razonamiento (thinking) vuelvan sin tocar y Gemini que vuelvan las partes con su
 * thoughtSignature. Por eso una conversación queda atada a su proveedor (asistente_service.php la reinicia si
 * cambia la clave).
 *
 * Interfaz común:
 *   agregarPedido(&$historial, $texto, $archivo)   mensaje del usuario (con PDF / imagen opcional)
 *   responder($sistema, $herramientas, &$historial) un turno del modelo: texto y/o llamadas a herramientas
 *   agregarResultados(&$historial, $resultados)     resultados de las herramientas de ese turno
 * Las herramientas se describen en JSON Schema (asistente_herramientas.php); Gemini las convierte.
 */

require_once __DIR__ . '/../facturas_lectura/FacturaExtractor.php';

interface AsisProveedor
{
    public function nombre(): string;
    public function modelo(): string;

    /** $archivo: null o ['contenido' => bytes, 'mime' => 'application/pdf' | 'image/jpeg' | 'image/png', 'nombre' => string] */
    public function agregarPedido(array &$historial, string $texto, ?array $archivo): void;

    /**
     * @return array ['texto' => string, 'llamadas' => [['id' => string, 'nombre' => string, 'entrada' => array]],
     *                'tokens_entrada' => int, 'tokens_salida' => int]
     * @throws RuntimeException con un mensaje para el usuario
     */
    public function responder(string $sistema, array $herramientas, array &$historial): array;

    /** $resultados: [['id' => ..., 'nombre' => ..., 'resultado' => array, 'error' => bool]] */
    public function agregarResultados(array &$historial, array $resultados): void;
}

/**
 * Configuración: la 'ia' de fl_config() (clave, url, timeout, reintentos, fallbacks) + estos valores por defecto +
 * la sección 'asistente' del archivo de configuración (FACTURA_LECTURA_CONFIG), que los pisa.
 */
function asis_config(): array
{
    return array_replace(fl_config()['ia'], [
        'modelo_claude' => 'claude-opus-5-5',
        'esfuerzo_asistente' => 'medium',        // low / medium / high (Claude)
        'max_tokens_respuesta' => 16000,
        'modelo_gemini_asistente' => '',          // vacío = el modelo_gemini de la lectura de facturas
        'gemini_thinking_budget' => 256,          // tope de tokens de pensamiento en Gemini 2.5+ (0=off, -1=default del modelo)
        'precio_entrada_mtok_claude' => 4.00,    // USD por millón de tokens (costo estimado del log)
        'precio_salida_mtok_claude' => 20.00,
        'precio_cache_mtok_claude' => 0.20,      // lectura de caché de prompt
    ], (array)(fl_config()['asistente'] ?? []));
}

/** Proveedor para la clave de la empresa (o la del servidor); null si no hay ninguna. */
function asis_proveedor(string $api_key = ''): ?AsisProveedor
{
    $config = asis_config();
    if (trim($api_key) !== '') $config['api_key'] = trim($api_key);
    $clave = trim((string)($config['api_key'] ?? ''));
    if ($clave === '') return null;
    $proveedor = strtolower(trim((string)($config['proveedor'] ?? 'gemini')));
    if (strpos($clave, 'AIza') === 0) $proveedor = 'gemini';
    elseif (strpos($clave, 'sk-ant-') === 0) $proveedor = 'claude';
    return $proveedor === 'claude' ? new AsisProveedorClaude($config) : new AsisProveedorGemini($config);
}

/** POST JSON con reintentos ante 429 / 5xx / red. Reutiliza handle curl (Keep-Alive/HTTP2) y gzip. */
function asis_http_post(string $url, array $headers, array $cuerpo, int $timeout): array
{
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
    } else {
        curl_reset($ch);
    }
    $retry_after = 0;
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HEADERFUNCTION => function ($ch, $linea) use (&$retry_after) {
            if (stripos($linea, 'retry-after:') === 0) $retry_after = intval(trim(substr($linea, 12)));
            return strlen($linea);
        },
    ]);
    $respuesta = curl_exec($ch);
    $error = $respuesta === false ? (curl_error($ch) ?: 'error de conexión') : null;
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $json = is_string($respuesta) ? json_decode($respuesta, true) : null;
    return [$status, $json, $error, $retry_after];
}

/**
 * El historial pasa por json_decode(..., true): un objeto vacío ({} de una llamada sin argumentos) vuelve como []
 * y la API lo rechaza como lista. $ruta = [lista de partes del mensaje, campo(s) dentro de cada parte].
 */
function asis_objetos_vacios(array &$historial, array $ruta): void
{
    [$lista, $campo] = [$ruta[0], array_slice($ruta, 1)];
    foreach ($historial as &$m) {
        if (!isset($m[$lista]) || !is_array($m[$lista])) continue;
        foreach ($m[$lista] as &$parte) {
            if (!is_array($parte)) continue;
            $ref = &$parte;
            foreach (array_slice($campo, 0, -1) as $k) {
                if (!isset($ref[$k]) || !is_array($ref[$k])) continue 2;
                $ref = &$ref[$k];
            }
            $ultimo = end($campo);
            if (array_key_exists($ultimo, $ref) && $ref[$ultimo] === []) $ref[$ultimo] = new stdClass();
            unset($ref);
        }
        unset($parte);
    }
    unset($m);
}

// ---------------------------------------------------------------------------------------------------
// Claude
// ---------------------------------------------------------------------------------------------------

class AsisProveedorClaude implements AsisProveedor
{
    const BETA_FALLBACKS = 'server-side-fallback-2026-07-01';

    private array $c;

    public function __construct(array $config)
    {
        $this->c = $config;
    }

    public function nombre(): string
    {
        return 'claude';
    }

    public function modelo(): string
    {
        $m = trim((string)($this->c['modelo_claude'] ?? ''));
        return $m !== '' ? $m : 'claude-opus-5-5';
    }

    public function agregarPedido(array &$historial, string $texto, ?array $archivo): void
    {
        $contenido = [];
        if ($archivo) {
            $contenido[] = $archivo['mime'] === 'application/pdf'
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($archivo['contenido'])]]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $archivo['mime'], 'data' => base64_encode($archivo['contenido'])]];
        }
        $contenido[] = ['type' => 'text', 'text' => $texto];
        $historial[] = ['role' => 'user', 'content' => $contenido];
    }

    public function responder(string $sistema, array $herramientas, array &$historial): array
    {
        asis_objetos_vacios($historial, ['content', 'input']);
        $tools = [];
        foreach ($herramientas as $h) {
            $tools[] = ['name' => $h['name'], 'description' => $h['description'], 'input_schema' => $h['input_schema']];
        }
        // Sin "thinking": en Claude Opus 5.5 el razonamiento adaptativo está siempre activo; la profundidad va por effort.
        // cache_control arriba: el sistema y las herramientas son fijos, así cada turno lee el prefijo de la caché.
        $cuerpo = [
            'model' => $this->modelo(),
            'max_tokens' => (int)($this->c['max_tokens_respuesta'] ?? 16000),
            'system' => $sistema,
            'tools' => $tools,
            'tool_choice' => ['type' => 'auto'],
            'messages' => $historial,
            'output_config' => ['effort' => (string)($this->c['esfuerzo_asistente'] ?? 'medium')],
            'cache_control' => ['type' => 'ephemeral'],
        ];
        $con_fallbacks = !empty($this->c['fallbacks']);
        if ($con_fallbacks) $cuerpo['fallbacks'] = 'default';

        $intentos = 1 + max(0, (int)($this->c['reintentos'] ?? 1));
        $ultimo = 'Sin respuesta';
        for ($i = 1; $i <= $intentos; $i++) {
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->c['api_key'],
                        'anthropic-version: ' . ($this->c['version'] ?? '2023-06-01')];
            if ($con_fallbacks) $headers[] = 'anthropic-beta: ' . self::BETA_FALLBACKS;
            [$status, $json, $error_red, $retry_after] = asis_http_post((string)$this->c['url'], $headers, $cuerpo, (int)($this->c['timeout'] ?? 120));
            if ($error_red === null && $status === 200 && is_array($json)) {
                return $this->interpretar($json, $historial);
            }
            $mensaje = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
            $ultimo = $error_red ?? ('HTTP ' . $status . ($mensaje !== '' ? ': ' . $mensaje : ''));
            error_log("AsisProveedorClaude intento $i/$intentos: $ultimo");
            if ($status === 400 && $con_fallbacks && stripos($mensaje, 'fallback') !== false) {
                unset($cuerpo['fallbacks']);
                $con_fallbacks = false;
                $i--;
                continue;
            }
            $reintentable = $error_red !== null || $status === 429 || $status === 529 || $status >= 500;
            if (!$reintentable || $i === $intentos) break;
            sleep(min(10, max(2 * $i, $retry_after)));
        }
        if (strpos($ultimo, 'HTTP 401') === 0 || strpos($ultimo, 'HTTP 403') === 0) {
            throw new RuntimeException('La clave de la API de IA no es válida o no tiene permiso.');
        }
        if (strpos($ultimo, 'HTTP 429') === 0) {
            throw new RuntimeException('La IA está saturada en este momento. Probá en unos minutos.');
        }
        throw new RuntimeException('No se pudo consultar a la IA (' . mb_substr($ultimo, 0, 200) . ').');
    }

    private function interpretar(array $json, array &$historial): array
    {
        $stop = (string)($json['stop_reason'] ?? '');
        if ($stop === 'refusal') throw new RuntimeException('La IA no procesó este pedido.');
        if ($stop === 'max_tokens') throw new RuntimeException('La respuesta de la IA quedó cortada. Probá con un pedido más corto.');
        $contenido = (array)($json['content'] ?? []);
        // El mensaje completo vuelve al historial, con los bloques de razonamiento tal cual
        $historial[] = ['role' => 'assistant', 'content' => $contenido];
        $texto = '';
        $llamadas = [];
        foreach ($contenido as $b) {
            $tipo = $b['type'] ?? '';
            if ($tipo === 'text') $texto .= (string)$b['text'];
            elseif ($tipo === 'tool_use') {
                $llamadas[] = ['id' => (string)$b['id'], 'nombre' => (string)$b['name'], 'entrada' => is_array($b['input'] ?? null) ? $b['input'] : []];
            }
        }
        $u = (array)($json['usage'] ?? []);
        $entrada = 0;
        $salida = 0;
        $cache_leidos = 0;
        $cache_creados = 0;
        foreach ((array)($u['iterations'] ?? []) ?: [$u] as $it) {
            $cache_leidos += intval($it['cache_read_input_tokens'] ?? 0);
            $cache_creados += intval($it['cache_creation_input_tokens'] ?? 0);
            $entrada += intval($it['input_tokens'] ?? 0) + intval($it['cache_creation_input_tokens'] ?? 0) + intval($it['cache_read_input_tokens'] ?? 0);
            $salida += intval($it['output_tokens'] ?? 0);
        }
        // tokens_entrada incluye los de caché; cache_leidos / cache_creados permiten el costo real y medir la caché
        return ['texto' => trim($texto), 'llamadas' => $llamadas, 'tokens_entrada' => $entrada, 'tokens_salida' => $salida,
                'cache_leidos' => $cache_leidos, 'cache_creados' => $cache_creados];
    }

    public function agregarResultados(array &$historial, array $resultados): void
    {
        // Todos los resultados del turno van en un solo mensaje
        $bloques = [];
        foreach ($resultados as $r) {
            $bloque = ['type' => 'tool_result', 'tool_use_id' => $r['id'],
                       'content' => json_encode($r['resultado'], JSON_UNESCAPED_UNICODE)];
            if (!empty($r['error'])) $bloque['is_error'] = true;
            $bloques[] = $bloque;
        }
        $historial[] = ['role' => 'user', 'content' => $bloques];
    }
}

// ---------------------------------------------------------------------------------------------------
// Gemini
// ---------------------------------------------------------------------------------------------------

class AsisProveedorGemini implements AsisProveedor
{
    private array $c;

    public function __construct(array $config)
    {
        $this->c = $config;
    }

    public function nombre(): string
    {
        return 'gemini';
    }

    public function modelo(): string
    {
        $m = trim((string)($this->c['modelo_gemini_asistente'] ?? ''));
        if ($m === '') $m = trim((string)($this->c['modelo_gemini'] ?? ''));
        return ($m === '' || stripos($m, 'claude') !== false) ? 'gemini-flash-latest' : $m;
    }

    public function agregarPedido(array &$historial, string $texto, ?array $archivo): void
    {
        $partes = [];
        if ($archivo) {
            $partes[] = ['inline_data' => ['mime_type' => $archivo['mime'], 'data' => base64_encode($archivo['contenido'])]];
        }
        $partes[] = ['text' => $texto];
        $historial[] = ['role' => 'user', 'parts' => $partes];
    }

    public function responder(string $sistema, array $herramientas, array &$historial): array
    {
        asis_objetos_vacios($historial, ['parts', 'functionCall', 'args']);
        asis_objetos_vacios($historial, ['parts', 'functionResponse', 'response']);
        $declaraciones = [];
        foreach ($herramientas as $h) {
            $d = ['name' => $h['name'], 'description' => $h['description']];
            if ((array)($h['input_schema']['properties'] ?? [])) $d['parameters'] = self::esquema($h['input_schema']);
            $declaraciones[] = $d;
        }
        $gen_cfg = ['maxOutputTokens' => 4096];
        $tb = isset($this->c['gemini_thinking_budget']) ? intval($this->c['gemini_thinking_budget']) : 256;
        if ($tb >= 0) {
            $gen_cfg['thinkingConfig'] = ['thinkingBudget' => $tb];
        }
        $cuerpo = [
            'systemInstruction' => ['parts' => [['text' => $sistema]]],
            'contents' => $historial,
            'tools' => [['functionDeclarations' => $declaraciones]],
            'toolConfig' => ['functionCallingConfig' => ['mode' => 'AUTO']],
            'generationConfig' => $gen_cfg,
        ];
        $url = rtrim((string)($this->c['url_gemini'] ?? 'https://generativelanguage.googleapis.com/v1beta/models'), '/')
             . '/' . rawurlencode($this->modelo()) . ':generateContent';
        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $this->c['api_key']];

        $intentos = 1 + max(0, (int)($this->c['reintentos'] ?? 1));
        $ultimo = 'Sin respuesta';
        for ($i = 1; $i <= $intentos; $i++) {
            [$status, $json, $error_red, $retry_after] = asis_http_post($url, $headers, $cuerpo, (int)($this->c['timeout'] ?? 120));
            if ($error_red === null && $status === 200 && is_array($json)) {
                return $this->interpretar($json, $historial);
            }
            $mensaje = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
            if ($status === 400 && isset($cuerpo['generationConfig']['thinkingConfig']) && stripos($mensaje, 'thinking') !== false) {
                unset($cuerpo['generationConfig']['thinkingConfig']);
                $intentos++;
                continue;
            }
            $ultimo = $error_red ?? ('HTTP ' . $status . ($mensaje !== '' ? ': ' . $mensaje : ''));
            error_log("AsisProveedorGemini intento $i/$intentos: $ultimo");
            $reintentable = $error_red !== null || $status === 429 || $status >= 500;
            if (!$reintentable || $i === $intentos) break;
            sleep(min(10, max(2 * $i, $retry_after)));
        }
        if ((strpos($ultimo, 'HTTP 400') === 0 && stripos($ultimo, 'API_KEY') !== false)
            || strpos($ultimo, 'HTTP 401') === 0 || strpos($ultimo, 'HTTP 403') === 0) {
            throw new RuntimeException('La clave de la API de Gemini no es válida o no tiene permiso.');
        }
        if (strpos($ultimo, 'HTTP 429') === 0) {
            throw new RuntimeException('La IA está saturada en este momento (límite de cuota). Probá en unos minutos.');
        }
        throw new RuntimeException('No se pudo consultar a la IA (' . mb_substr($ultimo, 0, 200) . ').');
    }

    private function interpretar(array $json, array &$historial): array
    {
        if (!empty($json['promptFeedback']['blockReason'])) {
            throw new RuntimeException('La IA no procesó este pedido (' . $json['promptFeedback']['blockReason'] . ').');
        }
        $cand = $json['candidates'][0] ?? null;
        if (!is_array($cand)) throw new RuntimeException('La IA no devolvió respuesta.');
        $fin = (string)($cand['finishReason'] ?? '');
        if ($fin === 'MAX_TOKENS') throw new RuntimeException('La respuesta de la IA quedó cortada. Probá con un pedido más corto.');
        if (in_array($fin, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true)) {
            throw new RuntimeException('La IA no procesó este pedido (' . $fin . ').');
        }
        $partes = (array)($cand['content']['parts'] ?? []);
        // Las partes vuelven al historial tal cual (con thoughtSignature)
        $historial[] = ['role' => 'model', 'parts' => $partes ?: [['text' => '']]];
        $texto = '';
        $llamadas = [];
        foreach ($partes as $n => $p) {
            if (!empty($p['thought'])) continue;
            if (isset($p['text'])) $texto .= (string)$p['text'];
            if (isset($p['functionCall'])) {
                $fc = $p['functionCall'];
                $llamadas[] = ['id' => (string)($fc['id'] ?? ('#' . $n)), 'nombre' => (string)$fc['name'],
                               'entrada' => is_array($fc['args'] ?? null) ? $fc['args'] : []];
            }
        }
        $u = (array)($json['usageMetadata'] ?? []);
        return ['texto' => trim($texto), 'llamadas' => $llamadas,
                'tokens_entrada' => intval($u['promptTokenCount'] ?? 0),
                'tokens_salida' => intval($u['candidatesTokenCount'] ?? 0) + intval($u['thoughtsTokenCount'] ?? 0),
                'cache_leidos' => intval($u['cachedContentTokenCount'] ?? 0), 'cache_creados' => 0];
    }

    public function agregarResultados(array &$historial, array $resultados): void
    {
        $partes = [];
        foreach ($resultados as $r) {
            $fr = ['name' => $r['nombre'], 'response' => !empty($r['error']) ? ['error' => $r['resultado']] : ['resultado' => $r['resultado']]];
            if ($r['id'] !== '' && $r['id'][0] !== '#') $fr['id'] = $r['id'];
            $partes[] = ['functionResponse' => $fr];
        }
        $historial[] = ['role' => 'user', 'parts' => $partes];
    }

    /** JSON Schema -> esquema OpenAPI de Gemini (tipos en mayúscula, nullable). */
    private static function esquema(array $s): array
    {
        $out = [];
        $tipo = $s['type'] ?? 'string';
        if (is_array($tipo)) {
            if (in_array('null', $tipo, true)) $out['nullable'] = true;
            $tipo = array_values(array_diff($tipo, ['null']))[0] ?? 'string';
        }
        $out['type'] = strtoupper((string)$tipo);
        if (isset($s['description'])) $out['description'] = (string)$s['description'];
        if (isset($s['enum'])) {
            $vals = array_values(array_filter(array_map('strval', $s['enum']), static fn($v) => $v !== ''));
            if ($vals) $out['enum'] = $vals;
        }
        if (!empty($s['properties'])) {
            $out['properties'] = [];
            foreach ($s['properties'] as $k => $p) $out['properties'][$k] = self::esquema($p);
        }
        if (!empty($s['required'])) $out['required'] = array_values($s['required']);
        if (isset($s['items'])) $out['items'] = self::esquema($s['items']);
        return $out;
    }
}
