<?php
/**
 * Lector de planillas para los extractos bancarios: CSV, XLSX y XLS (BIFF8), sin librerías externas.
 * Devuelve las celdas como texto o número, sin formato: el parser del extracto (extracto_parser.php) interpreta
 * fechas e importes según el formato del banco. Solo datos: no evalúa fórmulas (usa el último valor calculado),
 * no ejecuta macros ni sigue enlaces externos.
 *
 * Punto único de lectura: si más adelante se suma PhpSpreadsheet, se cambia solo bco_planilla_leer().
 *
 * Resultado: ['hojas' => ['nombre' => [ [fila 0 => [col 0 => valor, ...]], ... ]]] con filas y columnas desde 0
 * (fila 0 = fila 1 de la planilla, columna 0 = A). Las celdas vacías no aparecen.
 */

const BCO_PLANILLA_MAX_BYTES = 5 * 1024 * 1024;          // archivo subido
const BCO_PLANILLA_MAX_XML_BYTES = 40 * 1024 * 1024;     // XLSX descomprimido (evita bombas zip)
const BCO_PLANILLA_MAX_FILAS = 20000;

/**
 * @param string $tipo XLS | XLSX | CSV (el del formato del banco)
 * @param array $opciones CSV: 'delimitador', 'codificacion'
 * @throws RuntimeException con un mensaje para el usuario
 */
function bco_planilla_leer(string $ruta, string $tipo, array $opciones = []): array
{
    if (!is_file($ruta)) throw new RuntimeException('No se encontró el archivo.');
    $tam = filesize($ruta);
    if ($tam === 0) throw new RuntimeException('El archivo está vacío.');
    if ($tam > BCO_PLANILLA_MAX_BYTES) throw new RuntimeException('El archivo supera los 5 MB.');

    $cabecera = (string)file_get_contents($ruta, false, null, 0, 8);
    $esOle = $cabecera === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    $esZip = substr($cabecera, 0, 4) === "PK\x03\x04";

    switch (strtoupper($tipo)) {
        case 'XLS':
            if ($esZip) return bco_planilla_leer_xlsx($ruta);   // .xlsx con extensión vieja
            if (!$esOle) throw new RuntimeException('El archivo no es una planilla de Excel (.xls).');
            return bco_planilla_leer_xls($ruta);
        case 'XLSX':
            if ($esOle) return bco_planilla_leer_xls($ruta);
            if (!$esZip) throw new RuntimeException('El archivo no es una planilla de Excel (.xlsx).');
            return bco_planilla_leer_xlsx($ruta);
        case 'CSV':
            if ($esOle || $esZip) throw new RuntimeException('El formato espera un CSV y el archivo es una planilla de Excel.');
            return bco_planilla_leer_csv($ruta, (string)($opciones['delimitador'] ?? ''), (string)($opciones['codificacion'] ?? 'UTF-8'));
    }
    throw new RuntimeException('Tipo de archivo no soportado: ' . $tipo);
}

// ---------------------------------------------------------------------------------------------------------
// CSV
// ---------------------------------------------------------------------------------------------------------

function bco_planilla_leer_csv(string $ruta, string $delimitador, string $codificacion): array
{
    $texto = (string)file_get_contents($ruta);
    if (strncmp($texto, "\xEF\xBB\xBF", 3) === 0) {
        $texto = substr($texto, 3);
    } elseif (strtoupper($codificacion) !== 'UTF-8') {
        $texto = mb_convert_encoding($texto, 'UTF-8', $codificacion);
    } elseif (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    }
    if ($delimitador === '') {
        $primera = strtok($texto, "\n");
        $delimitador = substr_count((string)$primera, ';') > substr_count((string)$primera, ',') ? ';' : ',';
    }

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $texto);
    rewind($fh);
    $filas = [];
    $n = 0;
    while (($campos = fgetcsv($fh, 0, $delimitador)) !== false) {
        if (++$n > BCO_PLANILLA_MAX_FILAS) break;
        $fila = [];
        foreach ($campos as $c => $v) {
            if ($v !== null && trim($v) !== '') $fila[$c] = trim($v);
        }
        $filas[$n - 1] = $fila;
    }
    fclose($fh);
    return ['hojas' => ['csv' => $filas]];
}

// ---------------------------------------------------------------------------------------------------------
// XLSX (Office Open XML): ZipArchive + XML, sin red ni entidades externas
// ---------------------------------------------------------------------------------------------------------

function bco_planilla_leer_xlsx(string $ruta): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('El servidor no tiene la extensión zip de PHP.');
    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) throw new RuntimeException('No se pudo abrir el archivo .xlsx.');
    try {
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $total += (int)($st['size'] ?? 0);
            if (preg_match('/vbaProject\.bin$/i', (string)$st['name'])) {
                throw new RuntimeException('El archivo tiene macros: guardalo como .xlsx sin macros o como CSV.');
            }
        }
        if ($total > BCO_PLANILLA_MAX_XML_BYTES) throw new RuntimeException('El archivo descomprimido es demasiado grande.');

        $xml = static function (string $nombre) use ($zip): ?SimpleXMLElement {
            $contenido = $zip->getFromName($nombre);
            if ($contenido === false) return null;
            if (stripos($contenido, '<!DOCTYPE') !== false) throw new RuntimeException('El archivo .xlsx no es válido.');
            $x = simplexml_load_string($contenido, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
            if ($x === false) throw new RuntimeException('El archivo .xlsx no es válido.');
            return $x;
        };

        $compartidos = [];
        $sst = $xml('xl/sharedStrings.xml');
        if ($sst) {
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $compartidos[] = (string)$si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $r) $t .= (string)$r->t;
                    $compartidos[] = $t;
                }
            }
        }

        $libro = $xml('xl/workbook.xml');
        $rels = $xml('xl/_rels/workbook.xml.rels');
        if (!$libro || !$rels) throw new RuntimeException('El archivo .xlsx no es válido.');
        $destinos = [];
        foreach ($rels->Relationship as $r) $destinos[(string)$r['Id']] = (string)$r['Target'];

        $hojas = [];
        foreach ($libro->sheets->sheet as $hoja) {
            $rid = (string)$hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $destino = $destinos[$rid] ?? '';
            if ($destino === '') continue;
            $destino = ltrim($destino, '/');
            if (strpos($destino, 'xl/') !== 0) $destino = 'xl/' . $destino;
            $sx = $xml($destino);
            if (!$sx) continue;

            $filas = [];
            foreach ($sx->sheetData->row as $row) {
                $f = (int)$row['r'] - 1;
                if ($f < 0 || $f >= BCO_PLANILLA_MAX_FILAS) continue;
                foreach ($row->c as $c) {
                    $col = bco_planilla_columna_indice((string)$c['r']);
                    $t = (string)$c['t'];
                    if ($t === 's') {
                        $v = $compartidos[(int)$c->v] ?? '';
                    } elseif ($t === 'inlineStr') {
                        $v = (string)$c->is->t;
                    } elseif ($t === 'str' || $t === 'e') {
                        $v = (string)$c->v;
                    } elseif ($t === 'b') {
                        $v = (string)$c->v === '1' ? 'TRUE' : 'FALSE';
                    } else {
                        $v = isset($c->v) ? (float)(string)$c->v : '';
                    }
                    if (is_string($v)) $v = trim($v);
                    if ($v !== '') $filas[$f][$col] = $v;
                }
            }
            ksort($filas);
            $hojas[(string)$hoja['name']] = $filas;
        }
        return ['hojas' => $hojas];
    } finally {
        $zip->close();
    }
}

/** "B8" -> 1 (columna A = 0) */
function bco_planilla_columna_indice(string $ref): int
{
    $letras = preg_replace('/[^A-Z]/', '', strtoupper($ref));
    $n = 0;
    for ($i = 0, $l = strlen($letras); $i < $l; $i++) $n = $n * 26 + (ord($letras[$i]) - 64);
    return max(0, $n - 1);
}

// ---------------------------------------------------------------------------------------------------------
// XLS (BIFF8 dentro de un contenedor OLE2 / Compound File)
// ---------------------------------------------------------------------------------------------------------

function bco_planilla_leer_xls(string $ruta): array
{
    $datos = (string)file_get_contents($ruta);
    $libro = bco_ole_stream($datos, ['Workbook', 'Book']);
    return ['hojas' => bco_biff_hojas($libro)];
}

/** Devuelve el contenido del primer stream con alguno de esos nombres. */
function bco_ole_stream(string $d, array $nombres): string
{
    $len = strlen($d);
    if ($len < 512) throw new RuntimeException('El archivo .xls está dañado.');
    $u16 = static fn(int $p): int => unpack('v', $d, $p)[1];
    $u32 = static fn(int $p): int => unpack('V', $d, $p)[1];

    $tamSector = 1 << $u16(0x1E);
    $tamMini = 1 << $u16(0x20);
    $sectorDir = $u32(0x30);
    $corte = $u32(0x38);
    $sectorMiniFat = $u32(0x3C);
    $sectorDifat = $u32(0x44);
    $cantDifat = $u32(0x48);
    if ($tamSector !== 512 && $tamSector !== 4096) throw new RuntimeException('El archivo .xls está dañado.');
    $offset = static fn(int $s): int => ($s + 1) * $tamSector;
    $porSector = intdiv($tamSector, 4);

    // DIFAT -> FAT
    $sectoresFat = [];
    for ($i = 0; $i < 109; $i++) {
        $s = $u32(0x4C + $i * 4);
        if ($s < 0xFFFFFFFA) $sectoresFat[] = $s;
    }
    for ($n = 0, $s = $sectorDifat; $n < $cantDifat && $s < 0xFFFFFFFA; $n++) {
        $base = $offset($s);
        if ($base + $tamSector > $len) break;
        for ($i = 0; $i < $porSector - 1; $i++) {
            $v = $u32($base + $i * 4);
            if ($v < 0xFFFFFFFA) $sectoresFat[] = $v;
        }
        $s = $u32($base + ($porSector - 1) * 4);
    }
    $fat = [];
    foreach ($sectoresFat as $s) {
        $base = $offset($s);
        if ($base + $tamSector > $len) throw new RuntimeException('El archivo .xls está dañado.');
        $fat = array_merge($fat, array_values(unpack('V' . $porSector, $d, $base)));
    }

    $cadena = static function (int $inicio, array $tabla, int $limite) {
        $out = [];
        $vistos = 0;
        for ($s = $inicio; $s < 0xFFFFFFFA; $s = $tabla[$s] ?? 0xFFFFFFFE) {
            $out[] = $s;
            if (++$vistos > $limite) throw new RuntimeException('El archivo .xls está dañado.');
        }
        return $out;
    };
    $leer = static function (int $inicio) use ($cadena, $fat, $d, $offset, $tamSector, $len): string {
        $buf = '';
        foreach ($cadena($inicio, $fat, intdiv($len, $tamSector) + 1) as $s) $buf .= substr($d, $offset($s), $tamSector);
        return $buf;
    };

    // Directorio
    $dir = $leer($sectorDir);
    $entradas = [];
    for ($p = 0, $l = strlen($dir); $p + 128 <= $l; $p += 128) {
        $largoNombre = unpack('v', $dir, $p + 64)[1];
        $entradas[] = [
            'nombre' => $largoNombre >= 2 ? mb_convert_encoding(substr($dir, $p, $largoNombre - 2), 'UTF-8', 'UTF-16LE') : '',
            'tipo' => ord($dir[$p + 66]),
            'inicio' => unpack('V', $dir, $p + 116)[1],
            'tam' => unpack('V', $dir, $p + 120)[1],
        ];
    }
    $raiz = $entradas[0] ?? null;
    foreach ($nombres as $buscado) {
        foreach ($entradas as $e) {
            if ($e['tipo'] !== 2 || strcasecmp($e['nombre'], $buscado) !== 0) continue;
            if ($e['tam'] >= $corte) return substr($leer($e['inicio']), 0, $e['tam']);
            // Stream chico: mini stream (contenedor = cadena de la raíz) + MiniFAT
            if (!$raiz) break;
            $contenedor = $leer($raiz['inicio']);
            $miniFat = array_values(unpack('V*', $leer($sectorMiniFat)) ?: []);
            $buf = '';
            foreach ($cadena($e['inicio'], $miniFat, count($miniFat) + 1) as $s) $buf .= substr($contenedor, $s * $tamMini, $tamMini);
            return substr($buf, 0, $e['tam']);
        }
    }
    throw new RuntimeException('El archivo .xls no tiene datos de planilla.');
}

/** Recorre los registros BIFF8 del stream Workbook y arma las hojas. */
function bco_biff_hojas(string $w): array
{
    $len = strlen($w);
    $registros = static function (int $desde) use ($w, $len): Generator {
        $p = $desde;
        while ($p + 4 <= $len) {
            $h = unpack('vid/vlen', $w, $p);
            yield [$h['id'], substr($w, $p + 4, $h['len']), $p];
            $p += 4 + $h['len'];
        }
    };

    // Globales: hojas (BOUNDSHEET) y tabla de textos compartidos (SST + CONTINUE)
    $hojas = [];
    $sstPartes = null;
    $enSst = false;
    foreach ($registros(0) as [$id, $data]) {
        if ($id === 0x000A) break;                                   // EOF del libro
        if ($id === 0x0085) {                                         // BOUNDSHEET
            $cch = ord($data[6]);
            $nombre = bco_biff_texto(substr($data, 8), $cch, ord($data[7]) & 1);
            $hojas[] = ['pos' => unpack('V', $data, 0)[1], 'nombre' => $nombre, 'tipo' => ord($data[5])];
        } elseif ($id === 0x00FC) {                                   // SST
            $sstPartes = [$data];
            $enSst = true;
            continue;
        } elseif ($id === 0x003C && $enSst) {                         // CONTINUE del SST
            $sstPartes[] = $data;
            continue;
        }
        $enSst = false;
    }
    $sst = $sstPartes ? bco_biff_sst($sstPartes) : [];

    $resultado = [];
    foreach ($hojas as $h) {
        if ($h['tipo'] !== 0) continue;                               // solo hojas de cálculo
        $filas = [];
        $formulaPendiente = null;
        foreach ($registros($h['pos']) as [$id, $data, $p]) {
            if ($p !== $h['pos'] && $id === 0x0809) break;            // BOF de otra subsección
            if ($id === 0x000A) break;                                // EOF de la hoja
            switch ($id) {
                case 0x00FD:                                          // LABELSST
                    $r = unpack('vrow/vcol/vxf/Vi', $data);
                    $v = trim($sst[$r['i']] ?? '');
                    if ($v !== '' && $r['row'] < BCO_PLANILLA_MAX_FILAS) $filas[$r['row']][$r['col']] = $v;
                    break;
                case 0x0204:                                          // LABEL
                    $r = unpack('vrow/vcol/vxf/vcch', $data);
                    $v = trim(bco_biff_texto(substr($data, 9), $r['cch'], ord($data[8]) & 1));
                    if ($v !== '' && $r['row'] < BCO_PLANILLA_MAX_FILAS) $filas[$r['row']][$r['col']] = $v;
                    break;
                case 0x0203:                                          // NUMBER
                    $r = unpack('vrow/vcol/vxf/enum', $data);
                    if ($r['row'] < BCO_PLANILLA_MAX_FILAS) $filas[$r['row']][$r['col']] = $r['num'];
                    break;
                case 0x027E:                                          // RK
                    $r = unpack('vrow/vcol/vxf/Vrk', $data);
                    if ($r['row'] < BCO_PLANILLA_MAX_FILAS) $filas[$r['row']][$r['col']] = bco_biff_rk($r['rk']);
                    break;
                case 0x00BD:                                          // MULRK
                    $r = unpack('vrow/vcol', $data);
                    $cant = intdiv(strlen($data) - 6, 6);
                    for ($i = 0; $i < $cant && $r['row'] < BCO_PLANILLA_MAX_FILAS; $i++) {
                        $filas[$r['row']][$r['col'] + $i] = bco_biff_rk(unpack('V', $data, 4 + $i * 6 + 2)[1]);
                    }
                    break;
                case 0x0006:                                          // FORMULA: último valor calculado
                    $r = unpack('vrow/vcol/vxf', $data);
                    if ($r['row'] >= BCO_PLANILLA_MAX_FILAS) break;
                    if (substr($data, 12, 2) === "\xFF\xFF") {
                        $tipoRes = ord($data[6]);
                        if ($tipoRes === 0) $formulaPendiente = [$r['row'], $r['col']];   // el texto viene en STRING
                        elseif ($tipoRes === 1) $filas[$r['row']][$r['col']] = ord($data[8]) ? 'TRUE' : 'FALSE';
                    } else {
                        $filas[$r['row']][$r['col']] = unpack('e', $data, 6)[1];
                    }
                    break;
                case 0x0207:                                          // STRING (resultado de la fórmula anterior)
                    if ($formulaPendiente) {
                        $cch = unpack('v', $data)[1];
                        $v = trim(bco_biff_texto(substr($data, 3), $cch, ord($data[2]) & 1));
                        if ($v !== '') $filas[$formulaPendiente[0]][$formulaPendiente[1]] = $v;
                        $formulaPendiente = null;
                    }
                    break;
            }
        }
        foreach ($filas as &$f) ksort($f);
        unset($f);
        ksort($filas);
        $resultado[$h['nombre']] = $filas;
    }
    return $resultado;
}

function bco_biff_texto(string $bytes, int $cch, int $doble): string
{
    return $doble
        ? mb_convert_encoding(substr($bytes, 0, $cch * 2), 'UTF-8', 'UTF-16LE')
        : mb_convert_encoding(substr($bytes, 0, $cch), 'UTF-8', 'ISO-8859-1');
}

function bco_biff_rk(int $rk): float
{
    if ($rk & 2) {
        $v = (float)($rk >> 2);
        if ($rk & 0x80000000) $v = (float)(($rk >> 2) - (1 << 30));     // entero de 30 bits con signo
    } else {
        $v = unpack('e', "\0\0\0\0" . pack('V', $rk & 0xFFFFFFFC))[1];
    }
    return ($rk & 1) ? $v / 100 : $v;
}

/**
 * Tabla de textos compartidos. Un texto puede partirse entre registros CONTINUE: en ese caso el primer byte
 * del CONTINUE repite el flag de 8/16 bits para los caracteres que siguen.
 */
function bco_biff_sst(array $partes): array
{
    $textos = [];
    $total = unpack('V', $partes[0], 4)[1];
    $parte = 0;
    $buf = $partes[0];
    $p = 8;

    $asegurar = static function (int $n) use (&$buf, &$p, &$parte, $partes): bool {
        while ($p + $n > strlen($buf)) {
            if ($p < strlen($buf)) return true;      // lo que falta se lee de a pedazos
            if (!isset($partes[$parte + 1])) return false;
            $parte++;
            $buf = $partes[$parte];
            $p = 0;
        }
        return true;
    };

    for ($i = 0; $i < $total; $i++) {
        if (!$asegurar(3)) break;
        $cch = unpack('v', $buf, $p)[1];
        $flags = ord($buf[$p + 2]);
        $p += 3;
        $doble = $flags & 0x01;
        $runs = 0;
        $ext = 0;
        if ($flags & 0x08) { $runs = unpack('v', $buf, $p)[1]; $p += 2; }
        if ($flags & 0x04) { $ext = unpack('V', $buf, $p)[1]; $p += 4; }

        $texto = '';
        $faltan = $cch;
        while ($faltan > 0) {
            if ($p >= strlen($buf)) {
                if (!isset($partes[$parte + 1])) break 2;
                $parte++;
                $buf = $partes[$parte];
                $doble = ord($buf[0]) & 0x01;         // flag repetido al inicio del CONTINUE
                $p = 1;
            }
            $ancho = $doble ? 2 : 1;
            $disponibles = intdiv(strlen($buf) - $p, $ancho);
            $tomar = min($faltan, $disponibles);
            $texto .= bco_biff_texto(substr($buf, $p, $tomar * $ancho), $tomar, $doble);
            $p += $tomar * $ancho;
            $faltan -= $tomar;
        }
        $textos[] = $texto;

        // Saltear formato enriquecido y datos extendidos (pueden cruzar registros)
        $saltear = $runs * 4 + $ext;
        while ($saltear > 0) {
            if ($p >= strlen($buf)) {
                if (!isset($partes[$parte + 1])) break 2;
                $parte++;
                $buf = $partes[$parte];
                $p = 0;
            }
            $tomar = min($saltear, strlen($buf) - $p);
            $p += $tomar;
            $saltear -= $tomar;
        }
    }
    return $textos;
}
