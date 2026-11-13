<?php
/**
 * Capa 2: texto nativo del PDF. pdftotext -layout en el servidor si está (poppler-utils); si no, el texto que
 * armó pdf.js en el navegador (con las líneas reconstruidas por posición). Un PDF sin texto (escaneado) devuelve
 * null y el extractor pasa al OCR.
 */

class CapaTextoPdf implements CapaExtraccion
{
    const NOMBRE = 'TEXTO';

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function extraer(DocumentoFactura $doc): ?ResultadoExtraccion
    {
        if (!$doc->esPdf()) return null;
        $texto = self::textoServidor($doc) ?? $doc->textoCliente;
        $parser = new ParserTextoFactura($texto);
        if ($parser->longitud() < (int)fl_config()['texto_minimo']) return null;
        return $parser->interpretar($doc->cuitEmpresa, 'media', self::NOMBRE);
    }

    /** Texto de las primeras 3 páginas con pdftotext, o null si no está el binario o no hay texto. */
    private static function textoServidor(DocumentoFactura $doc): ?string
    {
        $bin = fl_bin('pdftotext');
        if (!$bin) return null;
        $dir = fl_dir_temporal();
        try {
            $salida = $dir . DIRECTORY_SEPARATOR . 'texto.txt';
            [$codigo] = fl_ejecutar($bin, ['-layout', '-enc', 'UTF-8', '-f', '1', '-l', '3', $doc->ruta, $salida], 60);
            $texto = ($codigo === 0 && is_file($salida)) ? (string)file_get_contents($salida) : '';
            return trim($texto) !== '' ? $texto : null;
        } finally {
            fl_borrar_dir($dir);
        }
    }
}
