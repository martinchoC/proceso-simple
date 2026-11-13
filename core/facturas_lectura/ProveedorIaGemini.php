<?php
/**
 * Proveedor de IA: Google Gemini ( Interactions / generateContent REST API v1beta ) por HTTP con curl.
 *
 * Soporta lectura multimodal directa de archivos PDF (`application/pdf`) e imágenes (`image/jpeg`, `image/png`)
 * enviados en base64 dentro de `inline_data`, forzando salida estructurada JSON (`responseMimeType: application/json`
 * + `responseSchema`). Incluye manejo de reintentos ante errores transitorios (429, 5xx, 503 y errores de red).
 */

class ProveedorIaGemini implements ProveedorIa
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
        $m = trim((string)($this->c['modelo_gemini'] ?? $this->c['modelo'] ?? ''));
        if ($m === '' || stripos($m, 'claude') !== false) {
            return 'gemini-flash-lite-latest';
        }
        return $m;
    }

    public function extraer(string $contenido, string $mime, string $instrucciones, array $esquema, ?string $pedido = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('El servidor no tiene la extensión curl de PHP.');
        }

        $plantillaJson = "\n\nDevolvé ÚNICAMENTE un objeto JSON válido con exactamente estas claves (usá null cuando un dato no aplique o no figure en el comprobante):\n"
            . '{"emisor_cuit":"11 dígitos sin guiones","emisor_razon_social":"string","receptor_cuit":"11 dígitos sin guiones",'
            . '"tipo_comprobante":1,"letra":"A","punto_venta":1,"numero":1,"fecha_emision":"YYYY-MM-DD","moneda":"PES","cotizacion":null,'
            . '"neto_gravado":0.0,"subtotal":null,"iva_21":0.0,"iva_10_5":null,"iva_27":null,"iva_5":null,"iva_2_5":null,'
            . '"no_gravado":null,"exento":null,"percepciones_iibb":null,"percepciones_iva":null,"otros_tributos":null,"total":0.0,'
            . '"cae":"14 dígitos","cae_vencimiento":"YYYY-MM-DD","condicion_pago":"string o null",'
            . '"percepciones_iibb_detalle":[{"jurisdiccion":"string","importe":0.0}],'
            . '"items":[{"codigo":"string o null","descripcion":"string","cantidad":0.0,"precio_unitario":0.0,"alicuota":21.0,"subtotal":0.0}]}';

        $promptUsuario = "Analizá este documento (comprobante fiscal argentino en formato " . ($mime === 'application/pdf' ? 'PDF' : 'imagen') . ") "
            . "y extraé todos los datos fiscales, importes, alícuotas de IVA, percepciones e ítems siguiendo estrictamente las instrucciones del sistema.";
        if ($pedido !== null) {
            // Otro uso que no es leer un comprobante (p. ej. colores del logo): sin la plantilla de factura
            $plantillaJson = '';
            $promptUsuario = $pedido;
        }

        $modelo = $this->modelo();
        $genConfig = [
            'temperature' => 0.1,
            'maxOutputTokens' => min(8192, max(2048, (int)($this->c['max_tokens'] ?? 4096))),
            'responseMimeType' => 'application/json',
        ];
        // En modelos Gemini 2.5 / 3.x no-lite el modo "thinking" viene activo por defecto y agrega ~60s de latencia.
        // En los modelos flash-lite el modo thinking no aplica y enviar thinkingConfig devuelve HTTP 400.
        if (stripos($modelo, 'lite') === false) {
            $genConfig['thinkingConfig'] = ['thinkingBudget' => 0];
        }
        if ($pedido !== null) {
            $genConfig['responseSchema'] = self::convertirEsquemaGemini($esquema);
        }

        $cuerpo = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $instrucciones . $plantillaJson],
                ],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    [
                        'inline_data' => [
                            'mime_type' => $mime,
                            'data' => base64_encode($contenido),
                        ],
                    ],
                    ['text' => $promptUsuario],
                ],
            ]],
            'generationConfig' => $genConfig,
        ];

        $intentos = 1 + max(0, (int)($this->c['reintentos'] ?? 1));
        $ultimo_error = 'Sin respuesta';
        for ($i = 1; $i <= $intentos; $i++) {
            [$status, $json, $error_red, $retry_after] = $this->post($cuerpo);
            if ($error_red === null && $status === 200 && is_array($json)) {
                return $this->interpretar($json);
            }
            $mensaje = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
            $ultimo_error = $error_red ?? ('HTTP ' . $status . ($mensaje !== '' ? ': ' . $mensaje : ''));
            error_log("ProveedorIaGemini intento $i/$intentos: $ultimo_error");

            $reintentable = $error_red !== null || $status === 429 || $status === 503 || $status >= 500;
            if (!$reintentable || $i === $intentos) break;
            sleep(min(10, max(2 * $i, $retry_after)));
        }

        if ($ultimo_error !== '' && (strpos($ultimo_error, 'HTTP 400') === 0 && stripos($ultimo_error, 'API_KEY') !== false
            || strpos($ultimo_error, 'HTTP 401') === 0 || strpos($ultimo_error, 'HTTP 403') === 0)) {
            throw new RuntimeException('La clave de la API de Gemini no es válida o no tiene permiso.');
        }
        if (strpos($ultimo_error, 'HTTP 429') === 0) {
            throw new RuntimeException('La API de Gemini está saturada (límite de cuota). Pruebe en unos minutos.');
        }
        throw new RuntimeException('No se pudo leer con Gemini (' . mb_substr($ultimo_error, 0, 200) . ').');
    }

    /**
     * Convierte el JSON Schema estándar (con ['type' => ['number', 'null']]) al formato OpenAPI Schema de Gemini
     * (['type' => 'NUMBER', 'nullable' => true]).
     */
    private static function convertirEsquemaGemini(array $s): array
    {
        $out = [];
        $tipo = $s['type'] ?? 'string';
        $nullable = false;
        if (is_array($tipo)) {
            $nullable = in_array('null', $tipo, true);
            $tipos = array_values(array_diff($tipo, ['null']));
            $tipo = $tipos[0] ?? 'string';
        }
        $out['type'] = strtoupper((string)$tipo);
        if ($nullable) {
            $out['nullable'] = true;
        }
        if (isset($s['properties']) && is_array($s['properties'])) {
            $out['properties'] = [];
            foreach ($s['properties'] as $k => $prop) {
                $out['properties'][$k] = self::convertirEsquemaGemini($prop);
            }
        }
        if (isset($s['required']) && is_array($s['required'])) {
            $out['required'] = array_values($s['required']);
        }
        if (isset($s['items']) && is_array($s['items'])) {
            $out['items'] = self::convertirEsquemaGemini($s['items']);
        }
        return $out;
    }

    /** [status, json decodificado, error de red o null, segundos de retry-after] */
    private function post(array $cuerpo): array
    {
        $baseUrl = rtrim((string)($this->c['url_gemini'] ?? 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
        $url = $baseUrl . '/' . rawurlencode($this->modelo()) . ':generateContent';

        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->c['api_key'],
        ];

        $retry_after = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => (int)($this->c['timeout'] ?? 120),
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
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
        if (!empty($json['promptFeedback']['blockReason'])) {
            throw new RuntimeException('Gemini bloqueó el procesamiento del documento (' . $json['promptFeedback']['blockReason'] . ').');
        }
        $candidato = $json['candidates'][0] ?? null;
        if (!is_array($candidato)) {
            throw new RuntimeException('Gemini no devolvió ningún resultado para este archivo.');
        }
        $finish = (string)($candidato['finishReason'] ?? '');
        if ($finish === 'MAX_TOKENS') {
            throw new RuntimeException('La respuesta de Gemini quedó cortada (documento demasiado largo).');
        }
        if (in_array($finish, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true)) {
            throw new RuntimeException('Gemini no procesó este documento (' . $finish . ').');
        }

        $texto = '';
        foreach ((array)($candidato['content']['parts'] ?? []) as $parte) {
            if (!empty($parte['thought'])) continue;
            if (isset($parte['text'])) $texto .= (string)$parte['text'];
        }
        $texto = trim($texto);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $texto, $m)) {
            $texto = trim($m[1]);
        }

        $datos = json_decode($texto, true);
        if (!is_array($datos)) {
            throw new RuntimeException('Gemini no devolvió datos con el formato JSON esperado.');
        }

        $usage = (array)($json['usageMetadata'] ?? []);
        $entrada = intval($usage['promptTokenCount'] ?? 0);
        $salida = intval($usage['candidatesTokenCount'] ?? 0) + intval($usage['thoughtsTokenCount'] ?? 0);

        return [
            'datos' => $datos,
            'tokens_entrada' => $entrada,
            'tokens_salida' => $salida,
            'modelo' => (string)($json['modelVersion'] ?? $this->modelo()),
        ];
    }
}

