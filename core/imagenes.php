<?php
/**
 * Normalización de imágenes subidas (fotos de productos para carrito / web).
 *
 * Toda imagen se valida por su contenido (no por el MIME que manda el navegador),
 * se corrige la orientación EXIF de las fotos de celular, se achica para que su
 * lado más largo no pase de IMG_LADO_MAX (nunca se agranda) y se vuelve a guardar
 * en WebP (o JPG / PNG si el servidor no tiene WebP). Así lo que queda guardado
 * pesa en general 50–200 KB sin importar cómo llegó.
 */

const IMG_MAX_SUBIDA = 1048576;          // 1 MB: tope del archivo que se sube
const IMG_LADO_MAX = 1200;               // px del lado más largo de lo que se guarda
const IMG_CALIDAD = 80;                  // calidad WebP / JPG
const IMG_MAX_PIXELES = 40000000;        // 40 MP: más que eso no se decodifica (memoria)
const IMG_TIPOS_PERMITIDOS = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

/**
 * Mensaje claro para el código de error de $_FILES[...]['error'].
 */
function img_error_subida(int $codigo): string
{
    switch ($codigo) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'La imagen pesa más de ' . img_tamanio_legible(IMG_MAX_SUBIDA) . '.';
        case UPLOAD_ERR_PARTIAL:
            return 'La imagen se subió incompleta. Probá de nuevo.';
        case UPLOAD_ERR_NO_FILE:
            return 'No se eligió ninguna imagen.';
        default:
            return 'No se pudo recibir la imagen (código ' . $codigo . ').';
    }
}

function img_tamanio_legible(int $bytes): string
{
    return $bytes >= 1048576 ? round($bytes / 1048576, 2) . ' MB' : round($bytes / 1024) . ' KB';
}

/**
 * Valida y normaliza una imagen subida.
 *
 * @param string $ruta           archivo temporal ($_FILES[...]['tmp_name'])
 * @param string $nombre_original nombre que mandó el navegador
 * @return array ['data', 'tipo', 'tamanio', 'nombre', 'ancho', 'alto']
 * @throws RuntimeException con un mensaje para mostrar al usuario
 */
function img_normalizar(string $ruta, string $nombre_original = 'imagen'): array
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('El servidor no tiene la extensión GD para procesar imágenes.');
    }

    $tamanio_original = (int)@filesize($ruta);
    if ($tamanio_original <= 0) {
        throw new RuntimeException('El archivo está vacío.');
    }
    if ($tamanio_original > IMG_MAX_SUBIDA) {
        throw new RuntimeException('La imagen pesa ' . img_tamanio_legible($tamanio_original)
            . '. El máximo es ' . img_tamanio_legible(IMG_MAX_SUBIDA) . '.');
    }

    $info = @getimagesize($ruta);
    if ($info === false || !in_array($info[2], IMG_TIPOS_PERMITIDOS, true)) {
        throw new RuntimeException('El archivo no es una imagen JPG, PNG, GIF o WebP válida.');
    }
    [$ancho, $alto, $tipo_origen] = $info;
    if ($ancho <= 0 || $alto <= 0 || $ancho * $alto > IMG_MAX_PIXELES) {
        throw new RuntimeException('La imagen tiene demasiados píxeles (' . $ancho . '×' . $alto . ').');
    }

    $img = @imagecreatefromstring((string)file_get_contents($ruta));
    if ($img === false) {
        throw new RuntimeException('No se pudo leer la imagen.');
    }

    // Orientación EXIF (fotos de celular): GD la ignora, hay que aplicarla a mano
    $rotada = false;
    if ($tipo_origen === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($ruta);
        $orientacion = (int)($exif['Orientation'] ?? 1);
        if ($orientacion > 1) {
            $img = img_aplicar_orientacion($img, $orientacion);
            $rotada = true;
        }
    }

    $ancho = imagesx($img);
    $alto = imagesy($img);
    $transparente = $tipo_origen !== IMAGETYPE_JPEG && img_tiene_transparencia($img);

    // Achicar sin agrandar
    $escala = min(1, IMG_LADO_MAX / max($ancho, $alto));
    $achicada = $escala < 1;
    if ($achicada) {
        $nuevo_ancho = max(1, (int)round($ancho * $escala));
        $nuevo_alto = max(1, (int)round($alto * $escala));
        $destino = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $img, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
        $img = $destino;
        $ancho = $nuevo_ancho;
        $alto = $nuevo_alto;
    } elseif (!imageistruecolor($img)) {
        imagepalettetotruecolor($img); // GIF / PNG con paleta: WebP necesita truecolor
    }

    [$data, $tipo, $extension] = img_codificar($img, $transparente);

    // Si no hubo que tocarla y ya venía en un formato liviano más chico, se guarda tal cual
    if (!$achicada && !$rotada && strlen($data) > $tamanio_original
        && in_array($tipo_origen, [IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
        $data = (string)file_get_contents($ruta);
        $tipo = image_type_to_mime_type($tipo_origen);
        $extension = $tipo_origen === IMAGETYPE_WEBP ? 'webp' : 'jpg';
    }

    $base = pathinfo(basename($nombre_original), PATHINFO_FILENAME);
    $base = trim(preg_replace('/[^\w\-. ]+/u', '_', $base)) ?: 'imagen';
    $nombre = mb_substr($base, 0, 90) . '.' . $extension; // conf__imagenes.imagen_nombre es varchar(100)

    return [
        'data' => $data,
        'tipo' => $tipo,
        'tamanio' => strlen($data),
        'nombre' => $nombre,
        'ancho' => $ancho,
        'alto' => $alto,
    ];
}

/**
 * @return array [bytes, mime, extensión]
 */
function img_codificar($img, bool $transparente): array
{
    if (function_exists('imagewebp')) {
        imagesavealpha($img, true);
        ob_start();
        imagewebp($img, null, IMG_CALIDAD);
        return [ob_get_clean(), 'image/webp', 'webp'];
    }
    if ($transparente) {
        imagesavealpha($img, true);
        ob_start();
        imagepng($img, null, 9);
        return [ob_get_clean(), 'image/png', 'png'];
    }
    // JPG sin transparencia: fondo blanco por si quedaba algún píxel con alfa
    $fondo = imagecreatetruecolor(imagesx($img), imagesy($img));
    imagefill($fondo, 0, 0, imagecolorallocate($fondo, 255, 255, 255));
    imagecopy($fondo, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
    ob_start();
    imagejpeg($fondo, null, IMG_CALIDAD);
    return [ob_get_clean(), 'image/jpeg', 'jpg'];
}

function img_aplicar_orientacion($img, int $orientacion)
{
    // imagerotate gira en sentido antihorario
    switch ($orientacion) {
        case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
        case 3: $img = imagerotate($img, 180, 0); break;
        case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
        case 5: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, 90, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 7: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, -90, 0); break;
        case 8: $img = imagerotate($img, 90, 0); break;
    }
    return $img;
}

/**
 * Muestrea una grilla de píxeles buscando alfa (no recorre la imagen entera).
 */
function img_tiene_transparencia($img): bool
{
    $ancho = imagesx($img);
    $alto = imagesy($img);
    if (!imageistruecolor($img)) {
        return imagecolortransparent($img) >= 0;
    }
    $paso_x = max(1, intdiv($ancho, 60));
    $paso_y = max(1, intdiv($alto, 60));
    for ($y = 0; $y < $alto; $y += $paso_y) {
        for ($x = 0; $x < $ancho; $x += $paso_x) {
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                return true;
            }
        }
    }
    return false;
}
