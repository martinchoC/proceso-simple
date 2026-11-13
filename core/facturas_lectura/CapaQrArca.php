<?php
/**
 * Capa 1: QR de ARCA (RG 4291). El QR lleva https://www.afip.gob.ar/fe/qr/?p=<base64> (o arca.gob.ar), y el
 * base64 es un JSON con ver, fecha, cuit, ptoVta, tipoCmp, nroCmp, importe, moneda, ctz, tipoDocRec, nroDocRec,
 * tipoCodAut y codAut (CAE). Lo lee jsQR en el navegador; si no vino y el servidor tiene zbarimg (y pdftoppm
 * para los PDF), lo lee el servidor. No trae el desglose de importes: eso sale de las otras capas.
 */

class CapaQrArca implements CapaExtraccion
{
    const NOMBRE = 'QR';

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function extraer(DocumentoFactura $doc): ?ResultadoExtraccion
    {
        $contenido = $doc->qrCliente;
        if ($contenido === null || self::decodificar($contenido) === null) {
            $contenido = self::leerEnServidor($doc);
        }
        $datos = $contenido !== null ? self::decodificar($contenido) : null;
        if ($datos === null) return null;

        $r = new ResultadoExtraccion();
        $cuit = preg_replace('/\D/', '', (string)($datos['cuit'] ?? ''));
        $r->set('emisor_cuit', $cuit, 'alta', self::NOMBRE);
        $tipo = intval($datos['tipoCmp'] ?? 0);
        if ($tipo > 0) {
            $r->set('tipo_comprobante', $tipo, 'alta', self::NOMBRE);
            if (isset(FL_TIPOS_ARCA[$tipo])) $r->set('letra', FL_TIPOS_ARCA[$tipo][1], 'alta', self::NOMBRE);
        }
        if (intval($datos['ptoVta'] ?? 0) > 0) $r->set('punto_venta', intval($datos['ptoVta']), 'alta', self::NOMBRE);
        if (intval($datos['nroCmp'] ?? 0) > 0) $r->set('numero', intval($datos['nroCmp']), 'alta', self::NOMBRE);
        $r->set('fecha_emision', fl_fecha((string)($datos['fecha'] ?? '')), 'alta', self::NOMBRE);
        if (isset($datos['importe']) && is_numeric($datos['importe'])) {
            $r->set('total', round((float)$datos['importe'], 2), 'alta', self::NOMBRE);
        }
        $moneda = strtoupper(trim((string)($datos['moneda'] ?? '')));
        $r->set('moneda', $moneda, 'alta', self::NOMBRE);
        if (isset($datos['ctz']) && is_numeric($datos['ctz'])) $r->set('cotizacion', (float)$datos['ctz'], 'alta', self::NOMBRE);
        if (intval($datos['tipoDocRec'] ?? 0) === 80) {   // 80 = CUIT
            $r->set('receptor_cuit', preg_replace('/\D/', '', (string)($datos['nroDocRec'] ?? '')), 'alta', self::NOMBRE);
        }
        $cod_aut = preg_replace('/\D/', '', (string)($datos['codAut'] ?? ''));
        if ($cod_aut !== '' && strtoupper((string)($datos['tipoCodAut'] ?? 'E')) === 'E') {
            $r->set('cae', $cod_aut, 'alta', self::NOMBRE);
        } elseif ($cod_aut !== '') {
            $r->avisos[] = 'El comprobante tiene CAEA (' . $cod_aut . '), no CAE.';
        }
        return $r->campos ? $r : null;
    }

    /** JSON del QR a partir de la URL o del parámetro p; null si no es un QR de ARCA válido. */
    public static function decodificar(string $contenido): ?array
    {
        $contenido = trim($contenido);
        $p = $contenido;
        if (preg_match('#^https?://#i', $contenido)) {
            $partes = parse_url($contenido);
            $host = strtolower($partes['host'] ?? '');
            if (!preg_match('/(^|\.)(afip|arca)\.gob\.ar$/', $host)) return null;
            parse_str($partes['query'] ?? '', $q);
            $p = (string)($q['p'] ?? '');
        }
        if ($p === '' || strlen($p) > 3000) return null;
        $b64 = strtr(rawurldecode($p), '-_', '+/');
        $b64 = preg_replace('/[^A-Za-z0-9+\/=]/', '', $b64);
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $json = base64_decode($b64, true);
        $datos = $json !== false ? json_decode($json, true) : null;
        if (!is_array($datos) || empty($datos['cuit']) || empty($datos['tipoCmp']) || empty($datos['nroCmp'])) return null;
        return $datos;
    }

    /** zbarimg sobre la imagen (o sobre la primera página del PDF pasada a PNG con pdftoppm). */
    private static function leerEnServidor(DocumentoFactura $doc): ?string
    {
        $zbar = fl_bin('zbarimg');
        if (!$zbar) return null;
        $dir = '';
        try {
            $imagen = $doc->ruta;
            if ($doc->esPdf()) {
                $pdftoppm = fl_bin('pdftoppm');
                if (!$pdftoppm) return null;
                $dir = fl_dir_temporal();
                fl_ejecutar($pdftoppm, ['-png', '-r', '200', '-f', '1', '-l', '1', $doc->ruta, $dir . DIRECTORY_SEPARATOR . 'p'], 60);
                $pngs = glob($dir . DIRECTORY_SEPARATOR . 'p*.png') ?: [];
                if (!$pngs) return null;
                $imagen = $pngs[0];
            }
            [$codigo, $salida] = fl_ejecutar($zbar, ['--raw', '-q', '-Sdisable', '-Sqrcode.enable', $imagen], 30);
            if ($codigo !== 0) return null;
            foreach (explode("\n", $salida) as $linea) {
                if (self::decodificar($linea) !== null) return trim($linea);
            }
            return null;
        } finally {
            if ($dir !== '') fl_borrar_dir($dir);
        }
    }
}
