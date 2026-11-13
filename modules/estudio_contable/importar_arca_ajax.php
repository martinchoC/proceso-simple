<?php
/**
 * Controlador AJAX: Importar Mis Comprobantes (ARCA) en la empresa del cliente del estudio.
 * Módulo 4: Estudio Contable (importar_arca_ajax.php). Página ID: 419.
 *
 * Toda acción sobre la empresa del cliente:
 *   1. exige vinculación vigente con escritura del área (ventas = Emitidos, compras = Recibidos), ia_cliente_empresa();
 *   2. concede acceso a sus sucursales solo en memoria para este request (el usuario del estudio no tiene perfil en
 *      una empresa FISCAL), AccesosSucursalesHelper::accesoDelegadoPorVinculacion();
 *   3. llama al motor de Gestión Normal con empresa_id = la empresa del cliente.
 * Respuesta {success, data, message}. Escrituras: POST + CSRF + función de la página.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 419]);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/../../core/csrf.php';
require_once __DIR__ . '/../../core/accesos_sucursales.php';
require_once __DIR__ . '/importar_arca_model.php';

header('Content-Type: application/json; charset=utf-8');

$estudio_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$accion = (string)($_REQUEST['accion'] ?? '');

$escrituras = ['preparar', 'importar', 'confirmar_compras'];
if (in_array($accion, $escrituras, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(ia_resp(false, 'Método no permitido.', null, 'METHOD'));
        exit;
    }
    csrf_exigir();
}
permisos_exigir_accion($conn, 419, $accion, [
    'preparar' => 'importar_arca',
    'importar' => 'importar_arca',
    'confirmar_compras' => 'confirmar_compras',
]);

/** Cliente + empresa con escritura del área del sentido, y acceso delegado a sus sucursales. */
function ia_contexto(mysqli $conn, int $estudio_id, int $usuario_id, string $sentido): array
{
    if (!in_array($sentido, ['EMITIDOS', 'RECIBIDOS'], true)) {
        throw new RuntimeException('Indique si el archivo es de Comprobantes Emitidos o Recibidos.');
    }
    $cli = ia_cliente_empresa($conn, $estudio_id, intval($_POST['cliente_id'] ?? $_GET['cliente_id'] ?? 0),
                              $sentido === 'EMITIDOS' ? 'ventas' : 'compras');
    AccesosSucursalesHelper::accesoDelegadoPorVinculacion($usuario_id, $cli['empresa_id']);
    return $cli;
}

/** Filas del archivo (JSON armado por ArcaMC en el navegador) + validación del CUIT del archivo. */
function ia_filas_request(array $cli): array
{
    $filas = json_decode((string)($_POST['filas'] ?? '[]'), true);
    if (!is_array($filas) || !$filas) {
        throw new RuntimeException('El archivo no trae comprobantes.');
    }
    if (count($filas) > 5000) {
        throw new RuntimeException('El archivo tiene más de 5000 comprobantes: divídalo por período.');
    }
    ia_validar_cuit_archivo($cli, (string)($_POST['cuit_archivo'] ?? ''));
    return array_values($filas);
}

try {
    $sentido = strtoupper((string)($_POST['sentido'] ?? $_GET['sentido'] ?? ''));
    switch ($accion) {
        case 'combos':
            require_once __DIR__ . '/estudio_clientes_alta_model.php';
            echo json_encode(ia_resp(true, 'OK', [
                'clientes' => ia_clientes($conn, $estudio_id),
                'plantillas' => estudio_plantillas_listar($conn, $estudio_id),
            ]));
            break;

        case 'diagnostico':
            $cli = ia_contexto($conn, $estudio_id, $usuario_id, $sentido);
            $filas = ia_filas_request($cli);
            echo json_encode(ia_resp(true, 'OK', ia_diagnostico($conn, $cli['empresa_id'], $sentido, ia_requisitos_de_filas($sentido, $filas))));
            break;

        case 'preparar':
            require_once __DIR__ . '/estudio_clientes_alta_model.php';   // plantillas + copia autorizada + sucursal rápida
            $cli = ia_contexto($conn, $estudio_id, $usuario_id, $sentido);
            $filas = ia_filas_request($cli);
            $r = ia_preparar($conn, $estudio_id, $usuario_id, $cli['empresa_id'], $sentido, ia_requisitos_de_filas($sentido, $filas),
                             intval($_POST['plantilla_id'] ?? 0));
            echo json_encode(ia_resp($r['ok'], $r['ok'] ? 'La empresa del cliente está lista para importar.' : 'Quedan prerrequisitos sin resolver.', $r));
            break;

        case 'analizar':
        case 'importar':
            $cli = ia_contexto($conn, $estudio_id, $usuario_id, $sentido);
            $filas = ia_filas_request($cli);
            $diag = ia_diagnostico($conn, $cli['empresa_id'], $sentido, ia_requisitos_de_filas($sentido, $filas));
            if ($accion === 'importar' && !$diag['ok']) {
                echo json_encode(ia_resp(false, 'Primero prepare la empresa del cliente (faltan prerrequisitos).', ['diagnostico' => $diag], 'PRERREQUISITOS'));
                break;
            }
            if ($sentido === 'EMITIDOS') {
                require_once __DIR__ . '/../gestion_normal/ventas_facturas_model.php';
                require_once __DIR__ . '/../gestion_normal/ventas_facturas_arca_import_model.php';
                $pag = ia_pagina($conn, IA_PAGINA_VENTAS_URL);
                if ($accion === 'analizar') {
                    $r = vfiAnalizarArchivo($conn, $cli['empresa_id'], $pag, $filas);
                    echo json_encode(ia_resp(true, 'OK', ['sentido' => $sentido, 'diagnostico' => $diag, 'listos' => $r['listos'],
                        'duplicados' => $r['duplicados'], 'errores' => $r['errores'], 'nuevos' => $r['clientes_nuevos'], 'items' => $r['items']]));
                } else {
                    $r = vfiImportar($conn, $cli['empresa_id'], $pag, $usuario_id, $filas, ia_concepto_venta_id($conn, $cli['empresa_id']), true);
                    echo json_encode(ia_resp(!empty($r['success']), (string)($r['message'] ?? ''), $r['data'] ?? null));
                }
            } else {
                require_once __DIR__ . '/../gestion_normal/facturas_proveedores_model.php';
                if ($accion === 'analizar') {
                    $r = fpPrevisualizarImportacionArca($conn, $cli['empresa_id'], $filas, true);
                    echo json_encode(ia_resp(!empty($r['success']), (string)($r['error'] ?? 'OK'), ['sentido' => $sentido, 'diagnostico' => $diag,
                        'listos' => $r['listos'] ?? 0, 'duplicados' => $r['duplicados'] ?? 0, 'errores' => $r['errores'] ?? 0,
                        'nuevos' => $r['proveedores_nuevos'] ?? 0, 'con_avisos' => $r['con_avisos'] ?? 0, 'items' => $r['items'] ?? []]));
                } else {
                    $sucursal = ia_sucursal_compras($conn, $cli['empresa_id']);
                    $r = fpImportarComprobantesArca($conn, $cli['empresa_id'], $filas, [
                        'sucursal_id' => $sucursal, 'condicion_pago_id' => ia_condicion_pago_compras($conn, $cli['empresa_id']),
                        'crear_proveedores_auto' => 1, 'pagina_idx' => ia_pagina($conn, IA_PAGINA_COMPRAS_URL),
                    ], $usuario_id);
                    $msg = !empty($r['success']) ? (string)($r['message'] ?? 'Importación terminada.') : (string)($r['error'] ?? 'No se pudo importar.');
                    echo json_encode(ia_resp(!empty($r['success']), $msg, $r));
                }
            }
            break;

        case 'borradores':
        case 'confirmar_compras':
            require_once __DIR__ . '/../gestion_normal/facturas_proveedores_model.php';
            $cli = ia_contexto($conn, $estudio_id, $usuario_id, 'RECIBIDOS');
            $pag = ia_pagina($conn, IA_PAGINA_COMPRAS_URL);
            $borrador = (int)obtenerEstadoInicialPagina($conn, $pag) ?: 3;
            if ($accion === 'borradores') {
                echo json_encode(ia_resp(true, 'OK', ia_compras_borrador($conn, $cli['empresa_id'], $borrador)));
                break;
            }
            $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
            if (!is_array($ids) || !$ids) {
                echo json_encode(ia_resp(false, 'Elija las compras a confirmar.', null, 'VALIDATION'));
                break;
            }
            $r = ia_confirmar_compras($conn, $cli['empresa_id'], $pag, $borrador, $ids);
            echo json_encode(ia_resp(true, "Confirmadas: {$r['confirmadas']}. Omitidas: {$r['omitidas']}. Con error: {$r['errores']}.", $r));
            break;

        default:
            echo json_encode(ia_resp(false, 'Acción no reconocida.', null, 'INVALID_ACTION'));
    }
} catch (RuntimeException $e) {
    echo json_encode(ia_resp(false, $e->getMessage(), null, 'VALIDATION'));
} catch (Throwable $e) {
    error_log('importar_arca_ajax [' . $accion . ']: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(ia_resp(false, 'Error en el servidor: ' . $e->getMessage(), null, 'SERVER_ERROR'));
}
