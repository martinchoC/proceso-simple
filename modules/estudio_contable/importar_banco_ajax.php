<?php
/**
 * Controlador AJAX: Importar resúmenes bancarios en la empresa del cliente del estudio (Mini-Gestión, página 417,
 * botón "Importar resumen bancario", minigestion_banco.js). Modelo en importar_banco_model.php.
 *
 * Como la importación de ARCA (importar_arca_ajax.php): exige vinculación vigente con escritura de compras
 * (ia_cliente_empresa), concede acceso a las sucursales del cliente solo en memoria y graba con el motor de Gestión
 * Normal con empresa_id = la empresa del cliente. Respuesta {success, data, message, error}.
 * Acciones: combos, cliente (proveedores del banco + diagnóstico), preparar, analizar, importar.
 * Escrituras (preparar, importar): POST + CSRF + función importar_banco (3570); analizar también va por POST (sube el archivo).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 417]);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/../../core/csrf.php';
require_once __DIR__ . '/../../core/formato.php';
require_once __DIR__ . '/../../core/accesos_sucursales.php';
require_once __DIR__ . '/../gestion_normal/facturas_proveedores_model.php';
require_once __DIR__ . '/estudio_clientes_alta_model.php';   // plantillas + copia autorizada + sucursal rápida (ia_preparar)
require_once __DIR__ . '/importar_banco_model.php';

header('Content-Type: application/json; charset=utf-8');

$estudio_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$accion = (string)($_REQUEST['accion'] ?? '');

if (in_array($accion, ['preparar', 'analizar', 'importar'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(ia_resp(false, 'Método no permitido.', null, 'METHOD'));
        exit;
    }
    csrf_exigir();
}
permisos_exigir_accion($conn, 417, $accion, [
    'combos' => 'importar_banco',
    'cliente' => 'importar_banco',
    'preparar' => 'importar_banco',
    'analizar' => 'importar_banco',
    'importar' => 'importar_banco',
]);

/** Cliente con escritura de compras y acceso delegado a sus sucursales. */
function ib_contexto(mysqli $conn, int $estudio_id, int $usuario_id): array
{
    $cli = ia_cliente_empresa($conn, $estudio_id, intval($_POST['cliente_id'] ?? $_GET['cliente_id'] ?? 0), 'compras');
    AccesosSucursalesHelper::accesoDelegadoPorVinculacion($usuario_id, $cli['empresa_id']);
    return $cli;
}

/** Datos comunes de analizar / importar: archivo subido, banco, formato, asignaciones a mano y proveedor del banco. */
function ib_request(mysqli $conn): array
{
    [$tmp] = bco_archivo_subido($_FILES['archivo'] ?? null);
    $asig = json_decode((string)($_POST['asignaciones'] ?? '{}'), true);
    $asignaciones = [];
    foreach (is_array($asig) ? $asig : [] as $linea => $concepto) {
        $asignaciones[(int)$linea] = (int)$concepto;
    }
    return [
        'ruta' => $tmp,
        'banco' => ib_banco($conn, intval($_POST['banco_id'] ?? 0)),
        'formato_id' => intval($_POST['formato_id'] ?? 0),
        'asignaciones' => $asignaciones,
        'proveedor' => ['cuit' => (string)($_POST['proveedor_cuit'] ?? ''), 'nombre' => trim((string)($_POST['proveedor_nombre'] ?? ''))],
    ];
}

try {
    switch ($accion) {
        case 'combos':
            echo json_encode(ia_resp(true, 'OK', [
                'clientes' => array_values(array_filter(ia_clientes($conn, $estudio_id), static fn($c) => !empty($c['escritura_compras']))),
                'plantillas' => estudio_plantillas_listar($conn, $estudio_id),
                'bancos' => bco_bancos($conn),
            ] + ib_lectores($conn, $estudio_id)));
            break;

        case 'cliente':
            $cli = ib_contexto($conn, $estudio_id, $usuario_id);
            echo json_encode(ia_resp(true, 'OK', [
                'proveedores' => ib_proveedores_banco($conn, $cli['empresa_id']),
                'diagnostico' => ib_diagnostico($conn, $cli['empresa_id']),
            ]));
            break;

        case 'preparar':
            $cli = ib_contexto($conn, $estudio_id, $usuario_id);
            $r = ib_preparar($conn, $estudio_id, $usuario_id, $cli['empresa_id'], intval($_POST['plantilla_id'] ?? 0));
            echo json_encode(ia_resp($r['ok'], $r['ok'] ? 'La empresa del cliente está lista para cargar resúmenes bancarios.' : 'Quedan prerrequisitos sin resolver.', $r));
            break;

        case 'analizar':
            $cli = ib_contexto($conn, $estudio_id, $usuario_id);
            $q = ib_request($conn);
            $a = ib_analizar($conn, $estudio_id, $cli['empresa_id'], $q['banco'], $q['formato_id'], $q['ruta'], $q['asignaciones'], $q['proveedor']);
            echo json_encode(ia_resp(true, 'OK', ib_publico($a)));
            break;

        case 'importar':
            $cli = ib_contexto($conn, $estudio_id, $usuario_id);
            $q = ib_request($conn);
            $meses = json_decode((string)($_POST['meses'] ?? '[]'), true);
            $r = ib_importar($conn, $estudio_id, $usuario_id, $cli['empresa_id'], $q['banco'], $q['formato_id'], $q['ruta'], $q['asignaciones'],
                             $q['proveedor'], is_array($meses) ? $meses : [], !empty($_POST['confirmar']), ia_pagina($conn, IA_PAGINA_COMPRAS_URL));
            $conf = $r['confirmacion'];
            $msg = 'Resúmenes cargados: ' . count($r['creados']) . '.'
                . ($conf ? " Confirmados: {$conf['confirmadas']}." . ($conf['errores'] + $conf['omitidas'] ? ' Quedaron en Borrador: ' . ($conf['errores'] + $conf['omitidas']) . '.' : '') : ' Quedaron en Borrador.')
                . ($r['aprendidas'] ? " Reglas aprendidas: {$r['aprendidas']}." : '');
            echo json_encode(ia_resp(count($r['creados']) > 0 || !$r['errores'], $msg, $r));
            break;

        default:
            echo json_encode(ia_resp(false, 'Acción no reconocida.', null, 'INVALID_ACTION'));
    }
} catch (RuntimeException $e) {
    echo json_encode(ia_resp(false, $e->getMessage(), null, 'VALIDATION'));
} catch (Throwable $e) {
    error_log('importar_banco_ajax [' . $accion . ']: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(ia_resp(false, 'Error en el servidor: ' . $e->getMessage(), null, 'SERVER_ERROR'));
}
