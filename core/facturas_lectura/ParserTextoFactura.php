<?php
/**
 * Interpreta el texto de una factura argentina (pdftotext -layout, pdf.js u OCR) con expresiones regulares y
 * heurísticas. Cubre el formato de "Comprobantes en línea" de ARCA y los usuales de los sistemas de facturación:
 * etiquetas "Importe Neto Gravado", "IVA 21%", "Percepción IIBB …", "Importe Total", "CAE N°", "Punto de Venta /
 * Comp. Nro" o "0001-00001234", letra en recuadro, "COD. 01".
 * Lo que no encuentra queda sin cargar (no inventa valores); la confianza la fija quien lo llama.
 */

class ParserTextoFactura
{
    /** Importe con decimales: "1.234,56", "1,234.56", "1234,56", "1234.56" (no toma porcentajes ni enteros sueltos). */
    const RE_IMPORTE = '/(?<![\d.,])-?(\d{1,3}(?:\.\d{3})+,\d{2}|\d{1,3}(?:,\d{3})+\.\d{2}|\d+,\d{2}|\d+\.\d{2})(?![\d%])(?!\s*%)/';
    const RE_FECHA = '/\b(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{4})\b/';
    const RE_CUIT = '/\b((?:20|23|24|25|26|27|30|33|34)-?\d{8}-?\d)\b/';

    /** Provincias por las palabras con que aparecen en las percepciones (código de gestion__jurisdicciones). */
    const PROVINCIAS = [
        'ARBA' => ['buenos aires', 'arba', 'bs. as', 'bs.as', 'bsas', 'pcia. bs', 'prov. bs'],
        'AGIP' => ['caba', 'c.a.b.a', 'agip', 'ciudad autonoma', 'capital federal', 'ciudad de buenos aires'],
        'API_SF' => ['santa fe', 'api '],
        'DGR_CBA' => ['cordoba'],
        'DGR_MZA' => ['mendoza', 'atm'],
        'DGR_NQN' => ['neuquen'],
        'DGR_RN' => ['rio negro'],
        'DGR_CHUBUT' => ['chubut'],
        'DGR_LP' => ['la pampa'],
        'DGR_CAT' => ['catamarca'],
        'DGR_CTES' => ['corrientes'],
        'ATP_CHACO' => ['chaco'],
        'ATER' => ['entre rios', 'ater'],
        'DGR_FSA' => ['formosa'],
        'DPR_JUJUY' => ['jujuy'],
        'DGIP_LR' => ['la rioja'],
        'DGR_MIS' => ['misiones'],
        'DGR_SALTA' => ['salta'],
        'DGR_SJ' => ['san juan'],
        'DPI_SL' => ['san luis'],
        'ASIP_SC' => ['santa cruz'],
        'DGR_SDE' => ['santiago del estero'],
        'DGR_TUC' => ['tucuman'],
        'AREF_TDF' => ['tierra del fuego'],
    ];

    /** @var string[] líneas originales */
    private array $lineas = [];
    /** @var string[] líneas en minúsculas y sin acentos, para buscar etiquetas */
    private array $norm = [];

    public function __construct(string $texto)
    {
        $texto = str_replace(["\r\n", "\r", "\f"], "\n", $texto);
        foreach (explode("\n", $texto) as $l) {
            $l = rtrim(str_replace("\t", '    ', $l));
            if (trim($l) === '') continue;
            $this->lineas[] = $l;
            $this->norm[] = self::normalizar($l);
        }
    }

    public static function normalizar(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'º' => '°']);
        return str_replace(['i.v.a.', 'i.v.a', 'i. v. a.'], 'iva', $s);
    }

    public function longitud(): int
    {
        return mb_strlen(implode('', $this->lineas));
    }

    /**
     * Lee todo lo que puede. $confianza: la que se asigna a cada campo leído; $capa: TEXTO u OCR.
     */
    public function interpretar(string $cuitEmpresa, string $confianza, string $capa): ResultadoExtraccion
    {
        $r = new ResultadoExtraccion();
        $texto = implode("\n", $this->norm);

        // CUITs: el emisor es el primero válido que no es el de la empresa; el receptor, el de la empresa si aparece
        $cuits = [];
        $invalidos = [];
        if (preg_match_all(self::RE_CUIT, implode("\n", $this->lineas), $m)) {
            foreach ($m[1] as $c) {
                $c = preg_replace('/\D/', '', $c);
                if (fl_cuit_valido($c)) { if (!in_array($c, $cuits, true)) $cuits[] = $c; }
                else $invalidos[] = $c;
            }
        }
        foreach ($cuits as $c) {
            if ($c !== $cuitEmpresa) { $r->set('emisor_cuit', $c, $confianza, $capa); break; }
        }
        // Sin ninguno válido: el primero que parece CUIT, para que la validación avise del dígito verificador
        if (!$r->tiene('emisor_cuit') && $invalidos) $r->set('emisor_cuit', $invalidos[0], 'baja', $capa);
        if ($cuitEmpresa !== '' && in_array($cuitEmpresa, $cuits, true)) {
            $r->set('receptor_cuit', $cuitEmpresa, $confianza, $capa);
        }

        $r->set('emisor_razon_social', $this->razonSocial(), 'baja', $capa);
        $this->tipoComprobante($r, $texto, $confianza, $capa);
        $this->numeracion($r, $confianza, $capa);

        $fecha = $this->fechaTrasEtiqueta(['fecha de emision', 'fecha emision', 'f. emision', 'fecha de comprobante', 'fecha:']);
        if ($fecha === null && preg_match(self::RE_FECHA, $texto, $mf)) $fecha = fl_fecha($mf[1]);
        $r->set('fecha_emision', $fecha, $confianza, $capa);

        if (preg_match('/c\.?\s?a\.?\s?e\.?\s*(?:n\s?°|nro\.?|numero|n\b\.?)?\s*:?\s*(\d{14})\b/', $texto, $mc)) {
            $r->set('cae', $mc[1], $confianza, $capa);
        }
        $r->set('cae_vencimiento', $this->fechaTrasEtiqueta(['vto. de cae', 'vto de cae', 'vencimiento de cae', 'vencimiento cae',
                                                             'vto. cae', 'vto cae', 'fecha de vto', 'vto. del cae']), $confianza, $capa);
        // "CAE N°: 86428126092697 - Vencimiento: 27/10/2026"
        if (!$r->tiene('cae_vencimiento')
            && preg_match('/c\.?\s?a\.?\s?e\.?[^\n]{0,20}?\d{14}\s*[-–]?\s*(?:vto\.?|vencimiento)\s*:?\s*(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{4})/', $texto, $mv)) {
            $r->set('cae_vencimiento', fl_fecha($mv[1]), $confianza, $capa);
        }

        foreach ($this->norm as $i => $n) {
            if (!preg_match('/condicion de (?:venta|pago)\s*:?\s*/', $n, $mp, PREG_OFFSET_CAPTURE)) continue;
            $resto = mb_substr($this->lineas[$i], mb_strlen(substr($n, 0, $mp[0][1] + strlen($mp[0][0]))));
            $cond = trim(preg_split('/\s{2,}/', trim($resto))[0] ?? '');
            $r->set('condicion_pago', mb_substr($cond, 0, 60), 'baja', $capa);
            break;
        }
        if (preg_match('/\b(u\$s|usd|dolares estadounidenses|moneda:\s*dol)/', $texto)) {
            $r->set('moneda', 'DOL', 'baja', $capa);
        }

        $this->codigoBarras($r, $capa);
        $this->importes($r, $confianza, $capa);
        $r->items = $this->items();
        $neto = $r->valor('neto_gravado') ?? $r->valor('subtotal');
        $suma = fn(array $its) => round(array_sum(array_column($its, 'subtotal')), 2);
        if ($neto === null || !$r->items || abs($suma($r->items) - (float)$neto) > 0.05) {
            // Tabla con los precios corridos de renglón: se prueba armar los ítems por la cuenta cantidad × precio
            $alt = $this->itemsPorCuenta();
            if ($alt && ($neto === null ? !$r->items : abs($suma($alt) - (float)$neto) <= 0.05)) $r->items = $alt;
        }
        return $r;
    }

    /**
     * Código de barras de los comprobantes autorizados (RG 1702, anterior al QR): CUIT emisor (11) + tipo (2) + punto
     * de venta (4) + CAE (14) + vencimiento AAAAMMDD (8) [+ dígito verificador]. Si está y es coherente, manda sobre
     * lo leído por etiquetas.
     */
    private function codigoBarras(ResultadoExtraccion $r, string $capa): void
    {
        foreach ($this->lineas as $l) {
            if (!preg_match_all('/(?<!\d)(\d{11})(\d{2})(\d{4})(\d{14})(\d{8})\d?(?!\d)/', $l, $mm, PREG_SET_ORDER)) continue;
            foreach ($mm as $m) {
                $tipo = intval($m[2]);
                $vto = fl_fecha($m[5]);
                if (!fl_cuit_valido($m[1]) || !isset(FL_TIPOS_ARCA[$tipo]) || !$vto || intval($m[3]) <= 0) continue;
                if (!$r->tiene('emisor_cuit') || $r->valor('emisor_cuit') === $m[1] || $r->campos['emisor_cuit']['confianza'] === 'baja') {
                    $r->set('emisor_cuit', $m[1], 'alta', $capa);
                }
                if ($r->valor('emisor_cuit') !== $m[1]) continue;
                $r->set('tipo_comprobante', $tipo, 'alta', $capa);
                $r->set('letra', FL_TIPOS_ARCA[$tipo][1], 'alta', $capa);
                $r->set('punto_venta', intval($m[3]), 'alta', $capa);
                $r->set('cae', $m[4], 'alta', $capa);
                $r->set('cae_vencimiento', $vto, 'alta', $capa);
                return;
            }
        }
    }

    // ------------------------------------------------------------------------------------------------

    private function razonSocial(): ?string
    {
        foreach ($this->norm as $i => $n) {
            if (strpos($n, 'razon social') === false || strpos($n, 'apellido') !== false || strpos($n, 'receptor') !== false) continue;
            $orig = $this->lineas[$i];
            $pos = mb_stripos(self::normalizar($orig), 'razon social');
            $resto = trim(ltrim(mb_substr($orig, $pos + 12), ': '));
            $resto = trim(preg_split('/\s{3,}/', $resto)[0] ?? '');
            if ($resto === '' && isset($this->lineas[$i + 1])) $resto = trim(preg_split('/\s{3,}/', trim($this->lineas[$i + 1]))[0] ?? '');
            if (mb_strlen($resto) >= 3) return mb_substr($resto, 0, 200);
        }
        return null;
    }

    private function tipoComprobante(ResultadoExtraccion $r, string $texto, string $confianza, string $capa): void
    {
        // "COD. 01" / "Código 006": el código de ARCA directo
        if (preg_match('/\bcod(?:igo)?\.?\s*(?:n\s?°)?\s*:?\s*0*(\d{1,3})\b/', $texto, $m) && isset(FL_TIPOS_ARCA[intval($m[1])])) {
            $r->set('tipo_comprobante', intval($m[1]), $confianza, $capa);
            $r->set('letra', FL_TIPOS_ARCA[intval($m[1])][1], $confianza, $capa);
            return;
        }
        $clase = null;
        if (preg_match('/nota\s+de\s+credito/', $texto)) $clase = 'NOTA_CREDITO';
        elseif (preg_match('/nota\s+de\s+debito/', $texto)) $clase = 'NOTA_DEBITO';
        elseif (preg_match('/\bfactura\b/', $texto)) $clase = 'FACTURA';
        elseif (preg_match('/\brecibo\b/', $texto)) $clase = 'RECIBO';
        $fce = (bool)preg_match('/factura\s+de\s+credito\s+electronica|mipymes?\b.*\bfce\b|\bfce\b/', $texto);

        $letra = null;
        // (?![\w.]): la "c" de "FACTURA    C.U.I.T." no es la letra
        if (preg_match('/(?:factura|nota\s+de\s+(?:credito|debito)|recibo)\s+(?:electronica\s+)?(?:tipo\s+)?"?([abcem])"?(?![\w.])/', $texto, $ml)) {
            $letra = strtoupper($ml[1]);
        } else {
            // letra sola en una línea (el recuadro del comprobante), entre las primeras
            foreach (array_slice($this->lineas, 0, 25) as $l) {
                if (preg_match('/^\s*\|?\s*([ABCEM])\s*\|?\s*$/', $l, $ml) || preg_match('/\s{3,}([ABCEM])\s{3,}/', $l, $ml)) {
                    $letra = $ml[1];
                    break;
                }
            }
        }
        if ($letra) $r->set('letra', $letra, $clase ? $confianza : 'baja', $capa);
        if ($clase && $letra) {
            $r->set('tipo_comprobante', fl_codigo_arca($clase, $letra, $fce), 'media', $capa);
        }
    }

    private function numeracion(ResultadoExtraccion $r, string $confianza, string $capa): void
    {
        $texto = implode("\n", $this->norm);
        // Formato ARCA: "Punto de Venta: 00002   Comp. Nro: 00000123"
        if (preg_match('/punto\s+de\s+venta\s*:?\s*(\d{1,5}).{0,40}?comp(?:robante)?\.?\s*n(?:ro|°|umero)\.?\s*:?\s*(\d{1,8})/s', $texto, $m)) {
            $r->set('punto_venta', intval($m[1]), $confianza, $capa);
            $r->set('numero', intval($m[2]), $confianza, $capa);
            return;
        }
        // "N° 0001-00001234", "0001 - 00001234", "A 00003-00000045"
        if (preg_match('/(?:n(?:ro|°|umero)?\.?\s*:?\s*)?\b(\d{4,5})\s*-\s*(\d{8})\b/', $texto, $m)) {
            $r->set('punto_venta', intval($m[1]), $confianza, $capa);
            $r->set('numero', intval($m[2]), $confianza, $capa);
            return;
        }
        if (preg_match('/punto\s+de\s+venta\s*:?\s*(\d{1,5})/', $texto, $m)) $r->set('punto_venta', intval($m[1]), 'baja', $capa);
        if (preg_match('/(?:comp(?:robante)?\.?\s*)?n(?:ro|°|umero)\.?\s*:?\s*(\d{8})\b/', $texto, $m)) $r->set('numero', intval($m[1]), 'baja', $capa);
    }

    private function importes(ResultadoExtraccion $r, string $confianza, string $capa): void
    {
        $r->set('neto_gravado', $this->importeTrasEtiqueta(['importe neto gravado', 'neto gravado', 'subtotal neto', 'importe neto', 'neto']), $confianza, $capa);
        $r->set('subtotal', $this->importeTrasEtiqueta(['subtotal']), $confianza, $capa);
        $r->set('no_gravado', $this->importeTrasEtiqueta(['importe no gravado', 'conceptos no gravados', 'no gravado']), $confianza, $capa);
        $r->set('exento', $this->importeTrasEtiqueta(['importe exento', 'operaciones exentas', 'exento']), $confianza, $capa);

        // IVA por alícuota: "IVA 21%: $ 210,00", "IVA 10,5 %", "I.V.A. 21,00%"
        $alicuotas = [];
        foreach ($this->norm as $i => $n) {
            if (strpos($n, 'percep') !== false || strpos($n, 'perc.') !== false || strpos($n, 'ret.') !== false) continue;
            if (!preg_match_all('/iva\s*(?:insc(?:ripto)?\.?\s*)?\(?\s*(27|21|10[.,]5|5|2[.,]5|0)(?:[.,]0+)?\s*%\s*\)?\s*:?/', $n, $mm, PREG_OFFSET_CAPTURE)) continue;
            foreach ($mm[0] as $k => $coinc) {
                $pct = number_format((float)str_replace(',', '.', $mm[1][$k][0]), 2, '.', '');
                $resto = substr($n, $coinc[1] + strlen($coinc[0]));
                $siguiente = $mm[0][$k + 1][1] ?? null;
                if ($siguiente !== null) $resto = substr($n, $coinc[1] + strlen($coinc[0]), $siguiente - $coinc[1] - strlen($coinc[0]));
                $importe = self::primerImporte($resto);
                if ($importe === null && $siguiente === null && isset($this->norm[$i + 1])) $importe = self::primerImporte($this->norm[$i + 1]);
                if ($importe !== null && in_array($pct, FL_ALICUOTAS, true) && !isset($alicuotas[$pct])) {
                    $alicuotas[$pct] = $importe;
                }
            }
        }
        $alicuotas = array_filter($alicuotas, fn($v) => abs($v) >= 0.005);
        if (!$alicuotas && ($iva = $this->importeTrasEtiqueta(['importe iva', 'iva inscripto', 'total iva', 'iva'])) !== null && $iva > 0) {
            // un solo IVA sin alícuota: se deduce del neto
            $neto = (float)$r->valor('neto_gravado');
            foreach ([21, 10.5, 27, 5, 2.5] as $p) {
                if ($neto > 0 && abs($neto * $p / 100 - $iva) <= max(0.05, $iva * 0.002)) {
                    $alicuotas[number_format($p, 2, '.', '')] = $iva;
                    break;
                }
            }
        }
        $r->set('iva_alicuotas', $alicuotas ?: null, $confianza, $capa);

        // Percepciones: una línea por tributo; IIBB con su provincia
        $perc_iibb = 0.0;
        $perc_iva = 0.0;
        $detalle = [];
        foreach ($this->norm as $n) {
            $es_perc = (bool)preg_match('/percep|perc\.|per\.\s*\/?\s*ret|\bper\b/', $n);
            if (!$es_perc) continue;
            $importe = self::ultimoImporte($n);
            if ($importe === null || $importe <= 0) continue;
            if (preg_match('/iibb|ii\.?bb|ingresos\s+brutos|\bib\b|i\.b\./', $n)) {
                $perc_iibb += $importe;
                $detalle[] = ['jurisdiccion' => self::provincia($n) ?? '', 'importe' => $importe];
            } elseif (preg_match('/\biva\b|valor agregado/', $n)) {
                $perc_iva += $importe;
            }
        }
        if ($perc_iibb > 0) {
            $r->set('percepciones_iibb', round($perc_iibb, 2), $confianza, $capa);
            $r->percepcionesIibb = $detalle;
        }
        if ($perc_iva > 0) $r->set('percepciones_iva', round($perc_iva, 2), $confianza, $capa);

        // "Importe Otros Tributos" de ARCA incluye las percepciones: lo que sobra son otros tributos
        $otros = $this->importeTrasEtiqueta(['importe otros tributos', 'otros tributos', 'impuestos internos', 'imp. internos']);
        if ($otros !== null) {
            $resto = round($otros - $perc_iibb - $perc_iva, 2);
            if ($resto > 0.009) $r->set('otros_tributos', $resto, $confianza, $capa);
        }

        $total = $this->importeTrasEtiqueta(['importe total', 'total a pagar', 'total factura', 'total comprobante', 'total general']);
        if ($total === null) {
            // "Total" al principio de una línea (no "Subtotal"): el más grande
            foreach ($this->norm as $n) {
                if (preg_match('/^\s*total\b(?!\s*iva)/', $n) && ($v = self::ultimoImporte($n)) !== null) {
                    $total = $total === null ? $v : max($total, $v);
                }
            }
        }
        $r->set('total', $total, $confianza, $capa);
        $this->tablaTotales($r, $confianza, $capa);
    }

    /** Etiquetas de la tabla de totales horizontal, de la más larga a la más corta. */
    const ETIQUETAS_TOTALES = [
        'neto' => 'subtotal\s+neto|importe\s+neto(?:\s+gravado)?|neto\s+gravado',
        'subtotal' => 'subtotal',
        'descuento' => 'descuentos?|bonificacion(?:es)?',
        'no_gravado' => 'no\s+gravado',
        'exento' => 'exento',
        'perc_iibb' => 'perc(?:ep(?:cion)?)?\.?\s*(?:iibb|ii\.?\s?bb|ing(?:resos)?\.?\s*brutos)(?:\s+(?!(?:sub)?total\b|iva\b|neto\b)[a-z.]+){0,3}',
        'perc_iva' => 'perc(?:ep(?:cion)?)?\.?\s*iva',
        'iva' => 'iva(?:\s+insc(?:ripto)?\.?)?(?:\s+\d+(?:[.,]\d+)?\s*%)?',
        'total' => 'total',
    ];

    /**
     * Tabla de totales horizontal: una fila de etiquetas ("SUBTOTAL  DESCUENTO  SUBTOTAL NETO  IVA INSCRIPTO
     * PERC. IIBB Buenos Aires  TOTAL") y los valores en la(s) fila(s) de abajo, en el mismo orden ("NaN" o "-" en
     * las vacías). Solo completa lo que no se leyó por etiqueta, y solo si el total cierra.
     */
    private function tablaTotales(ResultadoExtraccion $r, string $confianza, string $capa): void
    {
        $re = [];
        foreach (self::ETIQUETAS_TOTALES as $clave => $patron) $re[] = "(?<$clave>$patron)";
        $re = '/(?<![a-z])(?:' . implode('|', $re) . ')(?![a-z])/';
        foreach ($this->norm as $i => $n) {
            if (self::esImporte($n) || !preg_match_all($re, $n, $mm, PREG_SET_ORDER) || count($mm) < 3) continue;
            $resto = preg_replace($re, '', $n);
            if (preg_match_all('/[a-z]/', $resto) > 3) continue;
            $etiquetas = [];
            foreach ($mm as $m) {
                foreach (array_keys(self::ETIQUETAS_TOTALES) as $clave) {
                    if (($m[$clave] ?? '') !== '') { $etiquetas[] = [$clave, $m[0]]; break; }
                }
            }
            // valores: hasta 3 líneas que solo tengan importes o marcas de vacío
            $valores = [];
            for ($j = $i + 1; $j <= $i + 3 && isset($this->norm[$j]) && count($valores) < count($etiquetas); $j++) {
                $tokens = preg_split('/\s+/', trim(str_replace('$', ' ', $this->norm[$j])));
                $fila = [];
                foreach ($tokens as $t) {
                    if (preg_match('/^(nan|-+|—)$/', $t)) $fila[] = null;
                    elseif (preg_match(self::RE_IMPORTE, $t, $mi) && $mi[0] === $t) $fila[] = fl_numero($t);
                    else { $fila = false; break; }
                }
                if ($fila === false) break;
                $valores = array_merge($valores, $fila);
            }
            if (count($valores) !== count($etiquetas)) continue;

            $v = [];
            $perc_iibb = [];
            foreach ($etiquetas as $k => [$clave, $texto_etiqueta]) {
                if ($valores[$k] === null) continue;
                if ($clave === 'perc_iibb') $perc_iibb[] = ['jurisdiccion' => self::provincia($texto_etiqueta) ?? '', 'importe' => $valores[$k]];
                else $v[$clave] = ($v[$clave] ?? 0) + $valores[$k];
            }
            $neto = $v['neto'] ?? (isset($v['subtotal']) ? $v['subtotal'] - ($v['descuento'] ?? 0) : null);
            $piibb = array_sum(array_column($perc_iibb, 'importe'));
            if (isset($v['total'])) {
                $suma = ($neto ?? 0) + ($v['iva'] ?? 0) + $piibb + ($v['perc_iva'] ?? 0) + ($v['no_gravado'] ?? 0) + ($v['exento'] ?? 0);
                if (abs($suma - $v['total']) > 0.05 * count($etiquetas)) continue;
            }

            if (!$r->tiene('neto_gravado') && $neto !== null) $r->set('neto_gravado', round($neto, 2), $confianza, $capa);
            if (!$r->tiene('subtotal') && isset($v['subtotal'])) $r->set('subtotal', $v['subtotal'], $confianza, $capa);
            if (!$r->tiene('no_gravado') && isset($v['no_gravado'])) $r->set('no_gravado', $v['no_gravado'], $confianza, $capa);
            if (!$r->tiene('exento') && isset($v['exento'])) $r->set('exento', $v['exento'], $confianza, $capa);
            if (!$r->tiene('iva_alicuotas') && !empty($v['iva']) && $neto > 0) {
                foreach ([21, 10.5, 27, 5, 2.5] as $p) {
                    if (abs($neto * $p / 100 - $v['iva']) <= max(0.05, $v['iva'] * 0.002)) {
                        $r->set('iva_alicuotas', [number_format($p, 2, '.', '') => $v['iva']], $confianza, $capa);
                        break;
                    }
                }
            }
            if (!$r->tiene('percepciones_iibb') && $piibb > 0) {
                $r->set('percepciones_iibb', round($piibb, 2), $confianza, $capa);
                $r->percepcionesIibb = $perc_iibb;
            }
            if (!$r->tiene('percepciones_iva') && !empty($v['perc_iva'])) $r->set('percepciones_iva', $v['perc_iva'], $confianza, $capa);
            if (!$r->tiene('total') && isset($v['total'])) $r->set('total', $v['total'], $confianza, $capa);
            return;
        }
    }

    /** Ítems con código: líneas de la tabla de detalle con cantidad, precio y subtotal. Referencia, confianza baja. */
    private function items(): array
    {
        $items = [];
        $en_tabla = false;
        foreach ($this->lineas as $i => $l) {
            $n = $this->norm[$i];
            if (!$en_tabla) {
                if (preg_match('/cantidad|cant\./', $n) && preg_match('/precio|p\.\s*unit|unitario/', $n)) $en_tabla = true;
                continue;
            }
            if (preg_match('/^\s*(subtotal|importe neto|neto gravado|importe total|total)\b/', $n)) break;
            if (!preg_match_all(self::RE_IMPORTE, $l, $mm, PREG_OFFSET_CAPTURE) || count($mm[1]) < 2) continue;
            $alic = null;
            $pos_alic = null;
            if (preg_match('/\b(27|21|10[.,]5|5|2[.,]5|0)(?:[.,]0+)?\s*%/', $l, $ma, PREG_OFFSET_CAPTURE)) {
                $alic = (float)str_replace(',', '.', $ma[1][0]);
                $pos_alic = $ma[0][1];
            }
            $nums = [];
            $antes_alic = [];
            foreach ($mm[1] as [$txt, $pos]) {
                $nums[] = fl_numero($txt);
                if ($pos_alic !== null && $pos < $pos_alic) $antes_alic[] = fl_numero($txt);
            }
            $partes = preg_split('/\s{2,}/', trim($l));
            // "10   7800/3   DESCRIPCIÓN   14.476,24  144.762,40": cantidad entera adelante, si la cuenta cierra
            $cant_adelante = null;
            if (preg_match('/^\s*(\d{1,6}(?:[.,]\d{1,3})?)\s+\S/', $l, $mq) && !self::esImporte($mq[1]) && count($nums) >= 2) {
                $q = (float)str_replace(',', '.', $mq[1]);
                $sub = $antes_alic ? end($antes_alic) : end($nums);
                if ($q > 0 && abs($q * $nums[0] - $sub) <= max(0.02, $q * 0.005)) {
                    $cant_adelante = $q;
                    $partes = count($partes) > 1 && $partes[0] === $mq[1]
                        ? array_slice($partes, 1)
                        : preg_split('/\s{2,}/', trim(substr(trim($l), strlen($mq[1]))));
                }
            }
            $codigo = '';
            $descripcion = trim($partes[0] ?? '');
            if (count($partes) >= 3 && preg_match('/^[A-Za-z0-9][\w.\-\/]{0,29}$/u', $partes[0]) && !self::esImporte($partes[0])) {
                $codigo = $partes[0];
                $descripcion = trim($partes[1]);
            } elseif (preg_match('/^([A-Za-z0-9][\w.\-\/]{1,29})\s+(\D.+)$/u', $descripcion, $mc) && preg_match('/\d/', $mc[1])) {
                $codigo = $mc[1];
                $descripcion = $mc[2];
            }
            $descripcion = trim(preg_replace(self::RE_IMPORTE, '', $descripcion));
            $cantidad = $cant_adelante ?? $nums[0];
            $precio = $cant_adelante !== null ? $nums[0] : $nums[1];
            // Formato ARCA: "... Subtotal  21%  Subtotal c/IVA": el subtotal sin IVA es el último antes de la alícuota
            $subtotal = $antes_alic ? end($antes_alic) : end($nums);
            if ($cantidad === null || $precio === null || $cantidad <= 0) continue;
            $items[] = ['codigo' => $codigo, 'descripcion' => mb_substr($descripcion, 0, 200), 'cantidad' => $cantidad,
                        'precio_unitario' => $precio, 'alicuota' => $alic, 'subtotal' => $subtotal];
            if (count($items) >= 200) break;
        }
        return $items;
    }

    /**
     * Ítems cuando la tabla sale con los precios corridos de renglón (algunos PDF ubican cada columna a otra altura):
     * los renglones "cantidad código descripción" dan los artículos en orden, y los importes de la tabla, sueltos, se
     * reparten de a pares (precio, importe) que cumplan cantidad × precio = importe, tomando siempre los primeros
     * libres. Solo vale si todos los artículos encuentran su par; quien lo llama además exige que sumen el neto.
     */
    private function itemsPorCuenta(): array
    {
        $articulos = [];
        $valores = [];
        $en_tabla = false;
        foreach ($this->lineas as $i => $l) {
            $n = $this->norm[$i];
            if (!$en_tabla) {
                if (preg_match('/cantidad|cant\./', $n) && preg_match('/precio|p\.\s*unit|unitario/', $n)) $en_tabla = true;
                continue;
            }
            if (preg_match('/^\s*(subtotal|importe neto|neto gravado|importe total|total)\b/', $n)) break;
            $resto = $l;
            if (preg_match('/^\s*(\d{1,6}(?:[.,]\d{1,3})?)\s+([A-Za-z0-9][\w.\-\/]{0,29})\s+(.+)$/u', $l, $m) && preg_match('/\d/', $m[2])) {
                $partes = preg_split('/\s{2,}/', trim($m[3]));
                $descripcion = trim(preg_replace(self::RE_IMPORTE, '', $partes[0]));
                $articulos[] = ['codigo' => $m[2], 'descripcion' => mb_substr($descripcion, 0, 200),
                                'cantidad' => (float)str_replace(',', '.', $m[1])];
                $resto = implode('  ', array_slice($partes, 1));
            }
            if (preg_match_all(self::RE_IMPORTE, $resto, $mm)) {
                foreach ($mm[1] as $t) $valores[] = fl_numero($t);
            }
            if (count($articulos) > 200) return [];
        }
        if (!$articulos || count($valores) < 2 * count($articulos)) return [];

        $libre = array_fill(0, count($valores), true);
        $items = [];
        foreach ($articulos as $a) {
            $q = $a['cantidad'];
            if ($q <= 0) return [];
            $par = null;
            foreach ($valores as $x => $precio) {
                if (!$libre[$x]) continue;
                foreach ($valores as $y => $importe) {
                    if ($y === $x || !$libre[$y] || abs($q * $precio - $importe) > max(0.02, $q * 0.005)) continue;
                    $clave = [min($x, $y), max($x, $y)];
                    if ($par === null || $clave < $par[2]) $par = [$x, $y, $clave];
                }
            }
            if ($par === null) return [];
            $libre[$par[0]] = $libre[$par[1]] = false;
            $items[] = $a + ['precio_unitario' => $valores[$par[0]], 'alicuota' => null, 'subtotal' => $valores[$par[1]]];
        }
        return $items;
    }

    // ------------------------------------------------------------------------------------------------

    /** Primer importe después de la primera etiqueta que aparezca (en la misma línea o, si no, en la siguiente). */
    private function importeTrasEtiqueta(array $etiquetas): ?float
    {
        foreach ($etiquetas as $et) {
            foreach ($this->norm as $i => $n) {
                $pos = self::posEtiqueta($n, $et);
                if ($pos === null) continue;
                $resto = substr($n, $pos + strlen($et));
                $v = self::primerImporte($resto);
                if ($v === null && isset($this->norm[$i + 1]) && trim($resto, " :$\t") === '') $v = self::primerImporte($this->norm[$i + 1]);
                if ($v !== null) return $v;
            }
        }
        return null;
    }

    private function fechaTrasEtiqueta(array $etiquetas): ?string
    {
        foreach ($etiquetas as $et) {
            foreach ($this->norm as $i => $n) {
                $pos = self::posEtiqueta($n, $et);
                if ($pos === null) continue;
                $resto = substr($n, $pos + strlen($et));
                if (!preg_match(self::RE_FECHA, $resto, $m) && isset($this->norm[$i + 1])) preg_match(self::RE_FECHA, $this->norm[$i + 1], $m);
                if (!empty($m[1]) && ($f = fl_fecha($m[1]))) return $f;
            }
        }
        return null;
    }

    /** Posición de la etiqueta como palabra (no "subtotal" para "total", no "neto gravado" para "no gravado"). */
    private static function posEtiqueta(string $linea, string $etiqueta): ?int
    {
        $re = '/(?<![a-z])' . preg_quote($etiqueta, '/') . '(?![a-z])/';
        if (!preg_match($re, $linea, $m, PREG_OFFSET_CAPTURE)) return null;
        $pos = $m[0][1];
        if ($etiqueta === 'no gravado' && $pos > 0 && preg_match('/neto\s*$/', substr($linea, 0, $pos))) return null;
        if ($etiqueta === 'neto' && preg_match('/^\s*(no\s+)?gravado/', substr($linea, $pos + 4)) && strpos(substr($linea, $pos), 'no gravado') !== false) return null;
        return $pos;
    }

    private static function primerImporte(string $s): ?float
    {
        return preg_match(self::RE_IMPORTE, $s, $m) ? fl_numero($m[1]) : null;
    }

    private static function ultimoImporte(string $s): ?float
    {
        return preg_match_all(self::RE_IMPORTE, $s, $m) ? fl_numero(end($m[1])) : null;
    }

    private static function esImporte(string $s): bool
    {
        return (bool)preg_match(self::RE_IMPORTE, $s);
    }

    /** Código de jurisdicción (ARBA, AGIP, DGR_RN…) mencionado en la línea, o null. */
    public static function provincia(string $linea): ?string
    {
        $n = self::normalizar($linea);
        // "santiago del estero" antes que nada; "ciudad de buenos aires" antes que "buenos aires"
        $orden = ['DGR_SDE', 'AGIP'];
        foreach (array_merge($orden, array_diff(array_keys(self::PROVINCIAS), $orden)) as $cod) {
            foreach (self::PROVINCIAS[$cod] as $palabra) {
                if (strpos($n, $palabra) !== false) return $cod;
            }
        }
        return null;
    }
}
