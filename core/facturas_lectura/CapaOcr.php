<?php
/**
 * Capa 3: OCR para imágenes (JPG / PNG) y PDF escaneados. En el servidor: preprocesado con GD (escala de grises,
 * contraste, ampliación si la imagen es chica) y tesseract -l spa; los PDF se pasan antes a imagen con pdftoppm.
 * Sin tesseract en el servidor se usa el texto de tesseract.js del navegador (que ya hizo el mismo preprocesado
 * en un canvas). Luego, el mismo parser que la capa de texto, con confianza baja salvo que el OCR venga seguro.
 */

class CapaOcr implements CapaExtraccion
{
    const NOMBRE = 'OCR';

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function extraer(DocumentoFactura $doc): ?ResultadoExtraccion
    {
        $texto = self::ocrServidor($doc);
        $confianza_ocr = null;
        if ($texto === null) {
            $texto = $doc->ocrCliente;
            $confianza_ocr = $doc->ocrConfianzaCliente;
        }
        $parser = new ParserTextoFactura((string)$texto);
        if ($parser->longitud() < (int)fl_config()['texto_minimo']) return null;
        $confianza = ($confianza_ocr !== null && $confianza_ocr >= 85) ? 'media' : 'baja';
        $r = $parser->interpretar($doc->cuitEmpresa, $confianza, self::NOMBRE);
        if ($confianza_ocr !== null && $confianza_ocr < 60) {
            $r->avisos[] = 'La imagen se leyó con poca precisión (' . intval($confianza_ocr) . '%): revise todos los datos o use "Leer con IA".';
        }
        return $r;
    }

    private static function ocrServidor(DocumentoFactura $doc): ?string
    {
        $tesseract = fl_bin('tesseract');
        if (!$tesseract) return null;
        $dir = fl_dir_temporal();
        try {
            $imagen = $doc->ruta;
            if ($doc->esPdf()) {
                $pdftoppm = fl_bin('pdftoppm');
                if (!$pdftoppm) return null;
                fl_ejecutar($pdftoppm, ['-png', '-r', '300', '-f', '1', '-l', '2', $doc->ruta, $dir . DIRECTORY_SEPARATOR . 'p'], 90);
                $paginas = glob($dir . DIRECTORY_SEPARATOR . 'p*.png') ?: [];
            } else {
                $paginas = [$imagen];
            }
            $texto = '';
            foreach ($paginas as $i => $pagina) {
                $prep = self::preprocesar($pagina, $dir . DIRECTORY_SEPARATOR . 'ocr' . $i . '.png') ?? $pagina;
                // --psm 6: bloque de texto uniforme; tesseract corrige solo la inclinación leve
                [$codigo, $salida] = fl_ejecutar($tesseract, [$prep, 'stdout', '-l', 'spa', '--psm', '6'], 90);
                if ($codigo === 0) $texto .= $salida . "\n";
            }
            return trim($texto) !== '' ? $texto : null;
        } finally {
            fl_borrar_dir($dir);
        }
    }

    /** Escala de grises + contraste; amplía las imágenes de menos de 1500 px de ancho. null si GD no puede. */
    private static function preprocesar(string $origen, string $destino): ?string
    {
        if (!function_exists('imagecreatefromstring')) return null;
        $img = @imagecreatefromstring((string)file_get_contents($origen));
        if (!$img) return null;
        $ancho = imagesx($img);
        if ($ancho < 1500) {
            $escala = 1500 / $ancho;
            $nueva = imagescale($img, 1500, intval(imagesy($img) * $escala), IMG_BICUBIC);
            if ($nueva) { imagedestroy($img); $img = $nueva; }
        }
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        imagefilter($img, IMG_FILTER_CONTRAST, -30);
        $ok = imagepng($img, $destino);
        imagedestroy($img);
        return $ok ? $destino : null;
    }
}
