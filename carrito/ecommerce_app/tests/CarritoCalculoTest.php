<?php

declare(strict_types=1);

/**
 * Tests unitarios del cálculo del carrito (sin BD).
 * Ejecutar:  php tests/CarritoCalculoTest.php
 *
 * Se mantiene sin dependencias para que corran en cualquier entorno; si el
 * proyecto adopta PHPUnit, migrar estas aserciones tal cual.
 */

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Domain\LineaCarrito;
use App\Domain\ResumenCarrito;

$fallas = 0;

function verificar(string $caso, float $esperado, float $obtenido): void
{
    global $fallas;
    if (abs($esperado - $obtenido) > 0.001) {
        $fallas++;
        echo "FALLA  $caso: esperado $esperado, obtenido $obtenido\n";
        return;
    }
    echo "OK     $caso\n";
}

$linea = new LineaCarrito(
    productoId: 1,
    codigo: 'X-1',
    nombre: 'Producto',
    cantidad: 3,
    precioListaNeto: 100.00,
    descuentoPct: 10.0,
    ivaAlicuotaId: 1,
    ivaPorcentaje: 21.0,
);

verificar('precio unitario con descuento', 90.00, $linea->precioUnitarioNeto());
verificar('neto gravado', 270.00, $linea->netoGravado());
verificar('iva 21%', 56.70, $linea->ivaImporte());
verificar('total línea', 326.70, $linea->total());

// Sin descuento e IVA 0: el total debe ser exactamente el neto.
$exento = new LineaCarrito(2, 'X-2', 'Exento', 2, 50.0, 0.0, 3, 0.0);
verificar('línea exenta', 100.00, $exento->total());

$resumen = new ResumenCarrito([$linea, $exento]);
verificar('subtotal del resumen', 370.00, $resumen->subtotalNeto());
verificar('iva del resumen', 56.70, $resumen->iva());
verificar('total del resumen', 426.70, $resumen->total());
verificar('descuentos del resumen', 30.00, $resumen->descuentos());

// Carrito vacío: no debe romper ni devolver NAN.
$vacio = new ResumenCarrito([]);
verificar('total carrito vacío', 0.00, $vacio->total());

echo $fallas === 0 ? "\nTodos los tests pasaron.\n" : "\n$fallas test(s) fallaron.\n";
exit($fallas === 0 ? 0 : 1);
