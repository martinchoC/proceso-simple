<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/autoload.php';

use App\Support\Documento;

$casos = [
    // [entrada, esperado, descripcion]
    ['20304050607',   '20304050607', 'CUIL de 11 digitos'],
    ['20-30405060-7', '20304050607', 'CUIL con guiones'],
    ['20.30405060.7', '20304050607', 'CUIL con puntos'],
    [' 20304050607 ', '20304050607', 'espacios alrededor'],
    ['020304050607',  '20304050607', 'cero a la izquierda'],
    ['27123456',      '27123456',    'documento corto admitido'],

    ['martin',        '', 'nombre de usuario alfabetico'],
    ['',              '', 'cadena vacia'],
    ['cliente001',    '', 'alfanumerico: los digitos no alcanzan el minimo'],
    ['123',           '', 'demasiado corto'],
    ['123456789012345', '', 'demasiado largo'],
    ['203040506',     '', '9 digitos: no es CUIL ni DNI'],
    ['2030405060712', '', '13 digitos: no es CUIL ni DNI'],
    ['00000000000',   '', 'todo ceros'],
    ['0',             '', 'un cero'],
    ["20304050607' OR '1'='1", '', 'intento de inyeccion'],
];

$fallas = 0;
foreach ($casos as [$entrada, $esperado, $descripcion]) {
    $obtenido = Documento::normalizar($entrada);
    if ($obtenido === $esperado) {
        echo "  OK   {$descripcion}\n";
        continue;
    }
    $fallas++;
    echo "  FALLA {$descripcion}: esperado '{$esperado}', obtenido '{$obtenido}'\n";
}

echo $fallas === 0
    ? "\nDocumentoTest: " . count($casos) . " casos OK\n"
    : "\nDocumentoTest: {$fallas} fallas\n";

exit($fallas === 0 ? 0 : 1);
