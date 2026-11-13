<?php
/**
 * Proveedor de IA: Claude (Messages API de Anthropic) por HTTP con curl; el proyecto no usa composer, así que
 * no está el SDK. El PDF va como bloque "document" y la imagen como "image", ambos en base64; la salida se
 * fuerza a JSON con output_config.format (json_schema). Con 'fallbacks' activo se pide el reintento del lado del
 * servidor si el modelo rechaza el pedido (fallbacks "default", beta server-side-fallback-2026-07-01).
 * Timeout y reintentos limitados (429, 5xx, 529 y errores de red).
 */

class ProveedorIaClaude implements ProveedorIa
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
        return (string)$this->c['modelo'];
    }

    public function extraer(string $contenido, string $mime, string $instrucciones, array $esquema, ?string $pedido = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('El servidor no tiene la extensión curl de PHP.');
        }
        $bloque = $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($contenido)]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($contenido)]];
        $cuerpo = [
            'model' => $this->modelo(),
            'max_tokens' => (int)$this->c['max_tokens'],
            'system' => $instrucciones,
            'messages' => [[
                'role' => 'user',
                'content' => [$bloque, ['type' => 'text', 'text' => $pedido ?? 'Extraé los datos de este comprobante según el esquema.']],
            ]],
            'output_config' => [
                'effort' => (string)$this->c['esfuerzo'],
                'format' => ['type' => 'json_schema', 'schema' => $esquema],
            ],
        ];
        $con_fallbacks = !empty($this->c['fallbacks']);
        if ($con_fallbacks) $cuerpo['fallbacks'] = 'default';

        $intentos = 1 + max(0, (int)$this->c['reintentos']);
        $ultimo_error = 'Sin respuesta';
        for ($i = 1; $i <= $intentos; $i++) {
            [$status, $json, $error_red, $retry_after] = $this->post($cuerpo, $con_fallbacks);
            if ($error_red === null && $status === 200 && is_array($json)) {
                return $this->interpretar($json);
            }
            $mensaje = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
            $ultimo_error = $error_red ?? ('HTTP ' . $status . ($mensaje !== '' ? ': ' . $mensaje : ''));
            error_log("ProveedorIaClaude intento $i/$intentos: $ultimo_error");

            // El parámetro fallbacks rechazado (cuenta o plataforma sin la beta): se reintenta una vez sin él
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
        if ($ultimo_error !== '' && (strpos($ultimo_error, 'HTTP 401') === 0 || strpos($ultimo_error, 'HTTP 403') === 0)) {
            throw new RuntimeException('La clave de la API de IA no es válida o no tiene permiso.');
        }
        if (strpos($ultimo_error, 'HTTP 429') === 0) {
            throw new RuntimeException('La API de IA está saturada (límite de uso). Pruebe en unos minutos.');
        }
        throw new RuntimeException('No se pudo leer con IA (' . mb_substr($ultimo_error, 0, 200) . ').');
    }

    /** [status, json decodificado, error de red o null, segundos de retry-after] */
    private function post(array $cuerpo, bool $con_fallbacks): array
    {
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->c['api_key'],
            'anthropic-version: ' . $this->c['version'],
        ];
        if ($con_fallbacks) $headers[] = 'anthropic-beta: ' . self::BETA_FALLBACKS;
        $retry_after = 0;
        $ch = curl_init((string)$this->c['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => (int)$this->c['timeout'],
            CURLOPT_HEADERFUNCTION => function ($ch, $linea) use (&$retry_after) {
                if (stripos($linea, 'retry-after:') === 0) $retry_after = intval(trim(substr($linea, 12)));
                return strlen($linea);
            },
        ]);
        $respuesta = curl_exec($ch);
        $error = $respuesta === false ? (curl_error($ch) ?: 'error de conexión') : null;
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = is_string($respuesta) ? json_decode($respuesta, true) : null;
        return [$status, $json, $error, $retry_after];
    }

    private function interpretar(array $json): array
    {
        $stop = (string)($json['stop_reason'] ?? '');
        if ($stop === 'refusal') {
            throw new RuntimeException('La IA no procesó este documento.');
        }
        if ($stop === 'max_tokens') {
            throw new RuntimeException('La respuesta de la IA quedó cortada (documento demasiado largo).');
        }
        $texto = '';
        foreach ((array)($json['content'] ?? []) as $bloque) {
            if (($bloque['type'] ?? '') === 'text') $texto .= (string)$bloque['text'];
        }
        $datos = json_decode($texto, true);
        if (!is_array($datos)) {
            throw new RuntimeException('La IA no devolvió datos con el formato esperado.');
        }
        // Con fallback, usage.iterations trae cada intento; el top-level es solo el que respondió
        $entrada = 0;
        $salida = 0;
        $usage = (array)($json['usage'] ?? []);
        $iteraciones = (array)($usage['iterations'] ?? []);
        foreach ($iteraciones ?: [$usage] as $u) {
            $entrada += intval($u['input_tokens'] ?? 0) + intval($u['cache_creation_input_tokens'] ?? 0) + intval($u['cache_read_input_tokens'] ?? 0);
            $salida += intval($u['output_tokens'] ?? 0);
        }
        return ['datos' => $datos, 'tokens_entrada' => $entrada, 'tokens_salida' => $salida,
                'modelo' => (string)($json['model'] ?? $this->modelo())];
    }
}
