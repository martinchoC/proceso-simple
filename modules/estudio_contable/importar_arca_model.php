<?php
/**
 * Modelo: Importar Mis Comprobantes (ARCA) en la empresa del cliente del estudio (página 419).
 *
 * No hay importador propio: los comprobantes se analizan y graban con los motores de Gestión Normal, siempre con
 * la empresa del cliente (estudio__clientes.empresa_vinculada_id), nunca con la del estudio:
 *   Emitidos  -> vfiAnalizarArchivo / vfiImportar        (gestion_normal/ventas_facturas_arca_import_model.php)
 *   Recibidos -> fpPrevisualizarImportacionArca / fpImportarComprobantesArca (gestion_normal/facturas_proveedores_model.php)
 * Esos modelos no se pueden incluir juntos (syncComprobante está en los dos): los carga importar_arca_ajax.php según
 * el sentido. Este archivo tiene lo propio del estudio: cliente + vinculación, prerrequisitos de la empresa del
 * cliente y la confirmación en lote de las compras en Borrador.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/empresas_vinculaciones_model.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/estudio_clientes_model.php';

const IA_MODULO_GESTION = 5;
const IA_PAGINA_VENTAS_URL = 'ventas_facturas.php';        // página 556 de Gestión Normal
const IA_PAGINA_COMPRAS_URL = 'facturas_proveedores.php';  // página 557
// Alícuotas gravadas que puede traer Mis Comprobantes (columnas "Neto Grav. IVA x%")
const IA_ALICUOTAS_ARCA = [0.0, 2.5, 5.0, 10.5, 21.0, 27.0];
const IA_CONCEPTO_VENTA_CODIGO = 'ARCA-VTA';

function ia_resp(bool $ok, string $msg, $data = null, ?string $err = null): array
{
    return ['success' => $ok, 'message' => $msg, 'data' => $data, 'error' => $err];
}

/** Página de Gestión Normal por url (los motores toman de ahí la tabla y el estado inicial). */
function ia_pagina(mysqli $c, string $url): int
{
    $id = pagina_id_por_url($c, IA_MODULO_GESTION, $url);
    if ($id <= 0) {
        throw new RuntimeException("No está configurada la página $url de Gestión Normal.");
    }
    return $id;
}

/** Clientes del estudio con empresa en este ERP (no PADRON ni REMOTO / EXTERNO), con el estado de la vinculación. */
function ia_clientes(mysqli $c, int $estudio_id): array
{
    $st = mysqli_prepare($c, "SELECT cl.cliente_id, cl.razon_social, cl.cuit, cl.nivel_servicio, cl.empresa_vinculada_id,
                                     e.empresa AS empresa_nombre
                              FROM estudio__clientes cl
                              JOIN conf__empresas e ON e.empresa_id = cl.empresa_vinculada_id
                              WHERE cl.empresa_id = ? AND cl.tabla_estado_registro_id = 1 AND cl.empresa_vinculada_id > 0
                                AND cl.origen_datos = 'LOCAL' AND cl.nivel_servicio <> 'PADRON'
                              ORDER BY cl.razon_social");
    mysqli_stmt_bind_param($st, "i", $estudio_id);
    mysqli_stmt_execute($st);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    mysqli_stmt_close($st);
    foreach ($rows as &$r) {
        $emp = (int)$r['empresa_vinculada_id'];
        $r['escritura_ventas'] = vinculacionesVerificarPermiso($c, $emp, $estudio_id, 'ventas', true);
        $r['escritura_compras'] = vinculacionesVerificarPermiso($c, $emp, $estudio_id, 'compras', true);
    }
    unset($r);
    return $rows;
}

/**
 * Cliente del estudio + empresa del cliente, exigiendo vinculación vigente con escritura del área ('ventas' o
 * 'compras'). Devuelve ['cliente_id', 'razon_social', 'cuit', 'empresa_id']. Lanza RuntimeException si no corresponde.
 */
function ia_cliente_empresa(mysqli $c, int $estudio_id, int $cliente_id, string $area): array
{
    $st = mysqli_prepare($c, "SELECT cliente_id, razon_social, cuit, empresa_vinculada_id, origen_datos, nivel_servicio
                              FROM estudio__clientes WHERE empresa_id = ? AND cliente_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, "ii", $estudio_id, $cliente_id);
    mysqli_stmt_execute($st);
    $cl = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$cl) {
        throw new RuntimeException('El cliente no existe o no está activo.');
    }
    $emp = (int)($cl['empresa_vinculada_id'] ?? 0);
    if ($emp <= 0 || $cl['origen_datos'] !== 'LOCAL' || $cl['nivel_servicio'] === 'PADRON') {
        throw new RuntimeException('El cliente no tiene una empresa en este ERP: créela desde su ficha (nivel Fiscal o superior).');
    }
    if ($emp === $estudio_id) {
        throw new RuntimeException('El cliente está vinculado a la empresa del propio estudio: corrija la ficha del cliente.');
    }
    if (!vinculacionesVerificarPermiso($c, $emp, $estudio_id, $area, true)) {
        throw new RuntimeException("La empresa del cliente no otorgó al estudio permiso vigente de $area con escritura. Actualice la vinculación (Vinculaciones entre empresas).");
    }
    return ['cliente_id' => (int)$cl['cliente_id'], 'razon_social' => $cl['razon_social'],
            'cuit' => preg_replace('/\D/', '', (string)$cl['cuit']), 'empresa_id' => $emp];
}

/** El archivo tiene que ser del cliente: CUIT del título / nombre del archivo (o receptor en Recibidos). */
function ia_validar_cuit_archivo(array $cli, string $cuit_archivo): void
{
    $cuit_archivo = preg_replace('/\D/', '', $cuit_archivo);
    if ($cuit_archivo === '') {
        throw new RuntimeException('No se pudo leer el CUIT del archivo: use el archivo tal como lo baja ARCA (con el CUIT en el título o en el nombre).');
    }
    if ($cuit_archivo !== $cli['cuit']) {
        throw new RuntimeException("El archivo es del CUIT $cuit_archivo y el cliente elegido es {$cli['razon_social']} (CUIT {$cli['cuit']}).");
    }
}

// ---------------------------------------------------------------------------------------- prerrequisitos

/** Lo que la empresa necesita según los códigos del archivo. $req: tipos (códigos ARCA), pvs (códigos), alicuotas, no_gravado, exento. */
function ia_requisitos_de_filas(string $sentido, array $filas): array
{
    $req = ['tipos' => [], 'pvs' => [], 'tipos_por_pv' => [], 'alicuotas' => [], 'no_gravado' => false, 'exento' => false];
    foreach ($filas as $f) {
        if (!is_array($f)) {
            continue;
        }
        $codigo = $sentido === 'EMITIDOS' ? intval($f['tipo_codigo'] ?? 0) : intval($f['tipo'] ?? 0);
        if ($codigo > 0) {
            $req['tipos'][$codigo] = true;
        }
        if ($sentido === 'EMITIDOS') {
            $pv = intval($f['pv'] ?? 0);
            if ($pv > 0) {
                $req['pvs'][$pv] = true;
                if ($codigo > 0) {
                    $req['tipos_por_pv'][$pv][$codigo] = true;
                }
            }
            foreach ((array)($f['neto'] ?? []) as $pct => $neto) {
                if (abs((float)$neto) >= 0.005 || abs((float)(($f['iva'] ?? [])[$pct] ?? 0)) >= 0.005) {
                    $req['alicuotas'][number_format((float)$pct, 2, '.', '')] = true;
                }
            }
            $req['no_gravado'] = $req['no_gravado'] || abs((float)($f['no_gravado'] ?? 0)) >= 0.005;
            $req['exento'] = $req['exento'] || abs((float)($f['exento'] ?? 0)) >= 0.005;
        } else {
            foreach ((array)($f['alicuotas'] ?? []) as $a) {
                $n = ia_numero($a['neto'] ?? 0);
                $i = ia_numero($a['iva'] ?? 0);
                if (abs($n) >= 0.005 || abs($i) >= 0.005) {
                    $req['alicuotas'][number_format((float)($a['porcentaje'] ?? 0), 2, '.', '')] = true;
                }
            }
            $req['no_gravado'] = $req['no_gravado'] || abs(ia_numero($f['no_gravado'] ?? 0)) >= 0.005;
            $req['exento'] = $req['exento'] || abs(ia_numero($f['exento'] ?? 0)) >= 0.005;
        }
    }
    // Recibidos sin columnas por alícuota (formato viejo) o Factura C: el motor deduce o va todo a no gravado
    if ($sentido === 'RECIBIDOS') {
        $req['no_gravado'] = true;
    }
    return $req;
}

/** Importe de una celda de ARCA (número o texto con coma decimal), como fpArcaNumero. */
function ia_numero($v): float
{
    if (is_int($v) || is_float($v)) {
        return (float)$v;
    }
    $s = trim(str_replace(['$', ' '], '', (string)$v));
    if ($s === '') {
        return 0.0;
    }
    if (strpos($s, ',') !== false) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    }
    return is_numeric($s) ? (float)$s : 0.0;
}

function ia_filas(mysqli $c, string $sql, string $tipos, array $params): array
{
    $st = mysqli_prepare($c, $sql);
    mysqli_stmt_bind_param($st, $tipos, ...$params);
    mysqli_stmt_execute($st);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    mysqli_stmt_close($st);
    return $rows;
}

/** Tipos de comprobante de la empresa por código ARCA en el subgrupo de la página (ventas: tabla de la 556; compras: FC). */
function ia_tipos_por_codigo(mysqli $c, int $empresa_id, string $sentido): array
{
    if ($sentido === 'EMITIDOS') {
        $pag = ia_pagina($c, IA_PAGINA_VENTAS_URL);
        $sql = "SELECT ct.comprobante_tipo_id, cf.codigo
                FROM gestion__comprobantes_tipos ct
                JOIN gestion__comprobantes_subgrupos cs ON cs.comprobante_subgrupo_id = ct.comprobante_subgrupo_id AND cs.tabla_estado_registro_id = 1
                JOIN conf__paginas p ON p.pagina_id = ? AND p.tabla_id = cs.tabla_id
                JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
                WHERE ct.empresa_id = ? AND ct.tabla_estado_registro_id = 1";
        $rows = ia_filas($c, $sql, "ii", [$pag, $empresa_id]);
    } else {
        // Consulta directa (no emp_subgrupo_id): esa caché quedaría desactualizada después de copiar los tipos en el mismo request
        $r = ia_filas($c, "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos WHERE empresa_id = ? AND codigo = 'FC'
                           AND tabla_estado_registro_id = 1 ORDER BY comprobante_subgrupo_id LIMIT 1", "i", [$empresa_id]);
        $sg = $r ? (int)$r[0]['comprobante_subgrupo_id'] : 0;
        $sql = "SELECT ct.comprobante_tipo_id, cf.codigo
                FROM gestion__comprobantes_tipos ct
                JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
                WHERE ct.empresa_id = ? AND ct.comprobante_subgrupo_id = ? AND ct.tabla_estado_registro_id = 1";
        $rows = $sg > 0 ? ia_filas($c, $sql, "ii", [$empresa_id, $sg]) : [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[(int)$r['codigo']][] = (int)$r['comprobante_tipo_id'];
    }
    return $out;
}

/** Diagnóstico: qué le falta a la empresa del cliente para importar este archivo. */
function ia_diagnostico(mysqli $c, int $empresa_id, string $sentido, array $req): array
{
    $falta = [];

    // Alícuotas de IVA (gravadas por porcentaje, no gravado, exento)
    $alic = ia_filas($c, "SELECT porcentaje, es_gravado, es_no_gravado, es_exento FROM gestion__impuestos__iva_alicuotas
                          WHERE empresa_id = ? AND tabla_estado_registro_id = 1", "i", [$empresa_id]);
    $grav = [];
    $ng = false;
    $ex = false;
    foreach ($alic as $a) {
        if ((int)$a['es_no_gravado'] === 1) {
            $ng = true;
        } elseif ((int)$a['es_exento'] === 1) {
            $ex = true;
        } elseif ((int)$a['es_gravado'] === 1) {
            $grav[number_format((float)$a['porcentaje'], 2, '.', '')] = true;
        }
    }
    $falta['alicuotas'] = array_values(array_diff(array_keys($req['alicuotas']), array_keys($grav)));
    if ($req['no_gravado'] && !$ng) {
        $falta['alicuotas'][] = 'NO_GRAVADO';
    }
    if ($req['exento'] && !$ex) {
        $falta['alicuotas'][] = 'EXENTO';
    }

    // Tipos de comprobante por código ARCA (compras: los tiques los resuelve el motor con su equivalente)
    $tipos = ia_tipos_por_codigo($c, $empresa_id, $sentido);
    $falta['tipos'] = [];
    foreach (array_keys($req['tipos']) as $cod) {
        if (empty($tipos[$cod]) && !($sentido === 'RECIBIDOS' && ia_tique_equivalente($cod, $tipos))) {
            $falta['tipos'][] = $cod;
        }
    }

    // Sucursal (las dos), puntos de venta y tipos habilitados en cada PV (Emitidos), concepto de venta
    $suc = ia_filas($c, "SELECT COUNT(*) n FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1", "i", [$empresa_id]);
    $falta['sucursal'] = (int)$suc[0]['n'] === 0;
    $falta['pvs'] = [];
    $falta['pv_tipos'] = [];
    $falta['concepto_venta'] = false;
    if ($sentido === 'EMITIDOS') {
        foreach (array_keys($req['pvs']) as $cod) {
            $pv = ia_pv_por_codigo($c, $empresa_id, (int)$cod);
            if (!$pv) {
                $falta['pvs'][] = $cod;
                continue;
            }
            foreach (array_keys($req['tipos_por_pv'][$cod] ?? []) as $tcod) {
                if (!ia_pv_tiene_tipo($c, $empresa_id, $pv, $tipos[$tcod] ?? [])) {
                    $falta['pv_tipos'][] = sprintf('%05d: %03d', $cod, $tcod);
                }
            }
        }
        $prod = ia_filas($c, "SELECT COUNT(*) n FROM gestion__productos WHERE empresa_id = ? AND aplica_venta = 1 AND controla_stock = 0
                                AND tabla_estado_registro_id = 1", "i", [$empresa_id]);
        $falta['concepto_venta'] = (int)$prod[0]['n'] === 0;
    }
    $falta['ok'] = !$falta['alicuotas'] && !$falta['tipos'] && !$falta['sucursal'] && !$falta['pvs'] && !$falta['pv_tipos'] && !$falta['concepto_venta'];
    return $falta;
}

/** Compras: un tique sin tipo propio lo registra el motor con su equivalente (FP_ARCA_TIQUES_EQUIVALENTES de facturas_proveedores_model.php). */
function ia_tique_equivalente(int $cod, array $tipos): bool
{
    $eq = [81 => 1, 82 => 6, 111 => 11, 118 => 51, 115 => 2, 116 => 7, 117 => 12, 120 => 52, 112 => 3, 113 => 8, 114 => 13, 119 => 53];
    $eq = defined('FP_ARCA_TIQUES_EQUIVALENTES') ? FP_ARCA_TIQUES_EQUIVALENTES : $eq;
    return isset($eq[$cod]) && !empty($tipos[$eq[$cod]]);
}

function ia_pv_por_codigo(mysqli $c, int $empresa_id, int $codigo): int
{
    $r = ia_filas($c, "SELECT punto_venta_id FROM gestion__puntos_venta WHERE empresa_id = ? AND codigo_fiscal = ? AND tabla_estado_registro_id = 1
                       ORDER BY punto_venta_id LIMIT 1", "ii", [$empresa_id, $codigo]);
    return $r ? (int)$r[0]['punto_venta_id'] : 0;
}

function ia_pv_tiene_tipo(mysqli $c, int $empresa_id, int $pv_id, array $tipo_ids): bool
{
    foreach ($tipo_ids as $t) {
        $r = ia_filas($c, "SELECT 1 FROM gestion__puntos_venta_comprobantes WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?
                           AND tabla_estado_registro_id = 1 LIMIT 1", "iii", [$empresa_id, $pv_id, $t]);
        if ($r) {
            return true;
        }
    }
    return false;
}

/**
 * Deja la empresa del cliente lista para importar el archivo. Alícuotas y tipos de comprobante tienen cuentas y
 * subgrupos propios de cada empresa: si faltan se copian desde una plantilla del estudio por la copia autorizada
 * (plan de cuentas para remapear, datos básicos = alícuotas, tipos de comprobante). Una alícuota gravada que la
 * plantilla tampoco tiene (2,5 % / 5 %) se crea con las cuentas de la de 21 %. Sucursal, puntos de venta con el
 * código de ARCA del archivo, tipos habilitados en cada PV y el concepto de venta se crean acá.
 * Requiere modules/gestion_normal/empresa_inicio_model.php y empresas_autorizaciones_copia_model.php (los carga el ajax).
 */
function ia_preparar(mysqli $c, int $estudio_id, int $usuario_id, int $empresa_id, string $sentido, array $req, int $plantilla_id): array
{
    $pasos = [];
    $paso = function (string $n, bool $ok, string $d = '') use (&$pasos) { $pasos[] = ['paso' => $n, 'ok' => $ok, 'detalle' => $d]; };
    $diag = ia_diagnostico($c, $empresa_id, $sentido, $req);

    // 1. Alícuotas / tipos desde la plantilla (copia autorizada, idempotente: remapea por código)
    if ($diag['alicuotas'] || $diag['tipos']) {
        $origen = 0;
        if ($plantilla_id > 0) {
            foreach (estudio_plantillas_listar($c, $estudio_id) as $p) {
                if ((int)$p['plantilla_id'] === $plantilla_id) {
                    $origen = (int)$p['empresa_origen_id'];
                }
            }
        }
        if ($origen <= 0) {
            $paso('Alícuotas y tipos de comprobante', false, 'Elija una plantilla del estudio para copiarlos (faltan: '
                . implode(', ', array_merge($diag['alicuotas'], array_map(fn($t) => 'tipo ' . $t, $diag['tipos']))) . ').');
            return ['ok' => false, 'pasos' => $pasos, 'diagnostico' => $diag];
        }
        if ($origen === $empresa_id) {
            $paso('Alícuotas y tipos de comprobante', false, 'La plantilla es la misma empresa del cliente.');
            return ['ok' => false, 'pasos' => $pasos, 'diagnostico' => $diag];
        }
        $aut = autCopiaGuardar($c, [
            'empresa_destino_id' => $empresa_id,
            'permite_plan_cuentas' => 1, 'permite_datos_basicos' => 1, 'permite_comprobantes_tipos' => 1,
            'motivo' => 'Importación de Mis Comprobantes desde el estudio #' . $estudio_id,
        ], $origen, $usuario_id);
        if (empty($aut['success'])) {
            $paso('Autorización de copia', false, (string)($aut['error'] ?? ''));
            return ['ok' => false, 'pasos' => $pasos, 'diagnostico' => $diag];
        }
        $cop = inicioEjecutarCopia($c, $origen, $empresa_id, ['plan_cuentas' => 1, 'datos_basicos' => 1, 'comprobantes_tipos' => 1], $usuario_id);
        $paso('Copia desde la plantilla (plan de cuentas, alícuotas, tipos)', !empty($cop['success']), (string)($cop['message'] ?? $cop['error'] ?? ''));
        if (empty($cop['success'])) {
            return ['ok' => false, 'pasos' => $pasos, 'diagnostico' => $diag];
        }
    }

    // 2. Alícuotas gravadas que la plantilla tampoco tiene: con las cuentas de la de 21 %
    $diag = ia_diagnostico($c, $empresa_id, $sentido, $req);
    foreach ($diag['alicuotas'] as $a) {
        if (in_array($a, ['NO_GRAVADO', 'EXENTO'], true)) {
            continue;
        }
        $base = ia_filas($c, "SELECT cont_cuenta_compra_id, cont_cuenta_venta_id FROM gestion__impuestos__iva_alicuotas
                              WHERE empresa_id = ? AND es_gravado = 1 AND porcentaje = 21 AND tabla_estado_registro_id = 1 LIMIT 1", "i", [$empresa_id]);
        if (!$base) {
            $paso("Alícuota $a %", false, 'La empresa no tiene la alícuota de 21 % para tomar sus cuentas.');
            continue;
        }
        $pct = (float)$a;
        $cod = rtrim(rtrim($a, '0'), '.');
        $nom = 'IVA ' . $cod . '%';
        $cc = (int)$base[0]['cont_cuenta_compra_id'];
        $cv = (int)$base[0]['cont_cuenta_venta_id'];
        $st = mysqli_prepare($c, "INSERT INTO gestion__impuestos__iva_alicuotas
                (empresa_id, codigo, iva_alicuota, porcentaje, es_gravado, es_exento, es_no_gravado, cont_cuenta_compra_id, cont_cuenta_venta_id, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, 1, 0, 0, ?, ?, 1)");
        mysqli_stmt_bind_param($st, "issdii", $empresa_id, $cod, $nom, $pct, $cc, $cv);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        $paso("Alícuota $cod %", $ok, $ok ? 'Creada con las cuentas de la de 21 %' : 'No se pudo crear');
    }

    // 3. Sucursal (las dos) y, en Emitidos, puntos de venta con el código del archivo + tipos habilitados
    $diag = ia_diagnostico($c, $empresa_id, $sentido, $req);
    if ($diag['sucursal']) {
        $s = inicioCrearSucursalRapida($c, $empresa_id, $usuario_id);
        $paso('Sucursal', !empty($s['success']), (string)($s['message'] ?? $s['error'] ?? ''));
    }
    if ($sentido === 'EMITIDOS') {
        $tipos = ia_tipos_por_codigo($c, $empresa_id, $sentido);
        foreach (array_keys($req['pvs']) as $cod) {
            $cod = (int)$cod;
            $pv = ia_pv_por_codigo($c, $empresa_id, $cod);
            if (!$pv) {
                $b = ia_filas($c, "SELECT sucursal_id, boca_id FROM gestion__bocas WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                   ORDER BY es_principal DESC, boca_id LIMIT 1", "i", [$empresa_id]);
                if (!$b) {
                    $paso(sprintf('Punto de venta %05d', $cod), false, 'La empresa no tiene bocas.');
                    continue;
                }
                $nombre = sprintf('Punto de Venta %05d (Mis Comprobantes ARCA)', $cod);
                $suc = (int)$b[0]['sucursal_id'];
                $boca = (int)$b[0]['boca_id'];
                // RCEL: comprobantes ya autorizados en ARCA (no se pide CAE desde este ERP)
                $st = mysqli_prepare($c, "INSERT INTO gestion__puntos_venta (empresa_id, sucursal_id, boca_id, nombre, codigo_fiscal, sistema_emision, es_web, tabla_estado_registro_id)
                                          VALUES (?, ?, ?, ?, ?, 'RCEL', 0, 1)");
                mysqli_stmt_bind_param($st, "iiisi", $empresa_id, $suc, $boca, $nombre, $cod);
                mysqli_stmt_execute($st);
                $pv = (int)mysqli_insert_id($c);
                mysqli_stmt_close($st);
                $paso(sprintf('Punto de venta %05d', $cod), $pv > 0, 'Creado');
            }
            $habilitados = [];
            foreach (array_keys($req['tipos_por_pv'][$cod] ?? []) as $tcod) {
                foreach ($tipos[$tcod] ?? [] as $tid) {
                    if (ia_pv_tiene_tipo($c, $empresa_id, $pv, [$tid])) {
                        continue;
                    }
                    $st = mysqli_prepare($c, "INSERT INTO gestion__puntos_venta_comprobantes (empresa_id, punto_venta_id, comprobante_tipo_id, requiere_afip, tabla_estado_registro_id)
                                              VALUES (?, ?, ?, 1, 1)");
                    mysqli_stmt_bind_param($st, "iii", $empresa_id, $pv, $tid);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_close($st);
                    $habilitados[] = sprintf('%03d', $tcod);
                }
            }
            if ($habilitados) {
                $paso(sprintf('Tipos en el PV %05d', $cod), true, 'Habilitados: ' . implode(', ', array_unique($habilitados)));
            }
        }
        if ($diag['concepto_venta']) {
            $paso('Concepto de venta', ...ia_crear_concepto_venta($c, $empresa_id));
        }
    }

    $diag = ia_diagnostico($c, $empresa_id, $sentido, $req);
    if (!$pasos) {
        $paso('Empresa del cliente', true, 'No le falta nada para importar este archivo');
    }
    return ['ok' => $diag['ok'], 'pasos' => $pasos, 'diagnostico' => $diag];
}

/** Concepto "Venta según ARCA" (servicio, sin stock) para las líneas de las ventas importadas. [ok, detalle] */
function ia_crear_concepto_venta(mysqli $c, int $empresa_id): array
{
    $cuenta = emp_cuenta_concepto_id($c, $empresa_id, 'VENTAS');
    $iva = emp_iva_alicuota_id($c, $empresa_id, 21.0);
    if ($cuenta <= 0 || $iva <= 0) {
        return [false, 'Falta la cuenta del concepto contable VENTAS o la alícuota de 21 % de la empresa.'];
    }
    $tipo = ia_filas($c, "SELECT producto_tipo_id FROM gestion__productos_tipos WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                          ORDER BY (producto_tipo LIKE 'Servicio%') DESC, producto_tipo_id LIMIT 1", "i", [$empresa_id]);
    $tipo_id = $tipo ? (int)$tipo[0]['producto_tipo_id'] : null;
    $cod = IA_CONCEPTO_VENTA_CODIGO;
    $nom = 'Venta según ARCA (Mis Comprobantes)';
    $st = mysqli_prepare($c, "INSERT INTO gestion__productos (empresa_id, producto_codigo, producto_nombre, producto_tipo_id, cont_cuenta_id,
                                      iva_alicuota_id, controla_stock, es_servicio, aplica_compra, aplica_venta, tabla_estado_registro_id)
                              VALUES (?, ?, ?, ?, ?, ?, 0, 1, 0, 1, 1)");
    mysqli_stmt_bind_param($st, "issiii", $empresa_id, $cod, $nom, $tipo_id, $cuenta, $iva);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return $ok ? [true, "Creado ($cod)"] : [false, 'No se pudo crear el concepto de venta.'];
}

/** Concepto de venta para vfiImportar: el ARCA-VTA si existe, si no el primero de venta sin stock. */
function ia_concepto_venta_id(mysqli $c, int $empresa_id): int
{
    $r = ia_filas($c, "SELECT producto_id FROM gestion__productos WHERE empresa_id = ? AND aplica_venta = 1 AND controla_stock = 0
                       AND tabla_estado_registro_id = 1 ORDER BY (producto_codigo = ?) DESC, producto_id LIMIT 1", "is",
                   [$empresa_id, IA_CONCEPTO_VENTA_CODIGO]);
    return $r ? (int)$r[0]['producto_id'] : 0;
}

/** Sucursal para las compras importadas: la principal (la que tiene la boca principal) o la primera. */
function ia_sucursal_compras(mysqli $c, int $empresa_id): int
{
    $r = ia_filas($c, "SELECT s.sucursal_id FROM gestion__sucursales s
                       LEFT JOIN gestion__bocas b ON b.sucursal_id = s.sucursal_id AND b.es_principal = 1 AND b.tabla_estado_registro_id = 1
                       WHERE s.empresa_id = ? AND s.tabla_estado_registro_id = 1 ORDER BY (b.boca_id IS NOT NULL) DESC, s.sucursal_id LIMIT 1",
                   "i", [$empresa_id]);
    return $r ? (int)$r[0]['sucursal_id'] : 0;
}

/**
 * Condición de pago de las compras importadas: la de cuenta corriente con menos días de la empresa (la compra
 * queda como deuda con el proveedor; "Contado" puede disparar circuitos de pago). Si no hay, la primera activa.
 */
function ia_condicion_pago_compras(mysqli $c, int $empresa_id): int
{
    $r = ia_filas($c, "SELECT condicion_pago_id FROM gestion__condiciones_pago WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                       ORDER BY (tipo = 'CUENTA_CORRIENTE') DESC, dias_primer_vencimiento, orden, condicion_pago_id LIMIT 1", "i", [$empresa_id]);
    return $r ? (int)$r[0]['condicion_pago_id'] : 0;
}

// --------------------------------------------------------------------------- compras en Borrador

/**
 * Compras en el estado inicial de la página 557 (Borrador) de la empresa del cliente. "lista" = se puede confirmar:
 * sin Otros Tributos o con su detalle de percepciones cargado (gestion__comprobantes_impuestos) por el mismo importe.
 */
function ia_compras_borrador(mysqli $c, int $empresa_id, int $estado_borrador): array
{
    $rows = ia_filas($c, "SELECT fp.factura_proveedor_id, fp.f_emision, fp.comprobante_pv, fp.comprobante_nro, ct.comprobante_tipo,
                                 e.entidad_nombre, e.cuit, fp.total, fp.otros_impuestos,
                                 COALESCE((SELECT SUM(ci.importe) FROM gestion__comprobantes_impuestos ci
                                           WHERE ci.comprobante_id = fp.comprobante_id AND fp.comprobante_id > 0
                                             AND ci.tabla_estado_registro_id = 1), 0) AS percepciones_cargadas
                          FROM gestion__facturas_proveedores fp
                          JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id
                          LEFT JOIN gestion__entidades e ON e.entidad_id = fp.entidad_id
                          WHERE fp.empresa_id = ? AND fp.tabla_estado_registro_id = ?
                          ORDER BY fp.f_emision, fp.factura_proveedor_id", "ii", [$empresa_id, $estado_borrador]);
    foreach ($rows as &$r) {
        $otros = round((float)$r['otros_impuestos'], 2);
        $r['lista'] = $otros < 0.005 || abs($otros - round((float)$r['percepciones_cargadas'], 2)) < 0.01;
        $r['motivo'] = $r['lista'] ? '' : 'Otros Tributos sin detallar: cargue las percepciones antes de confirmar.';
    }
    unset($r);
    return $rows;
}

/**
 * Confirma en lote: por cada compra en Borrador y "lista", aprobar -> confirmar con ejecutarTransicionEstado (el motor
 * de facturas de proveedores: comprobante, stock, asiento, costos). Cada una es independiente. Requiere
 * gestion_normal/facturas_proveedores_model.php (lo carga el ajax).
 */
function ia_confirmar_compras(mysqli $c, int $empresa_id, int $pagina_id, int $estado_borrador, array $ids): array
{
    $listas = [];
    foreach (ia_compras_borrador($c, $empresa_id, $estado_borrador) as $r) {
        $listas[(int)$r['factura_proveedor_id']] = $r;
    }
    $res = ['confirmadas' => 0, 'omitidas' => 0, 'errores' => 0, 'items' => []];
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $r = $listas[$id] ?? null;
        if (!$r) {
            $res['omitidas']++;
            $res['items'][] = ['factura_proveedor_id' => $id, 'estado' => 'omitida', 'mensaje' => 'No está en Borrador en esta empresa.'];
            continue;
        }
        if (!$r['lista']) {
            $res['omitidas']++;
            $res['items'][] = ['factura_proveedor_id' => $id, 'estado' => 'omitida', 'mensaje' => $r['motivo']];
            continue;
        }
        $msg = '';
        foreach (['aprobar', 'confirmar'] as $accion) {
            try {
                $t = ejecutarTransicionEstado($c, $id, $accion, $empresa_id, $pagina_id);
            } catch (Throwable $e) {
                $t = ['success' => false, 'error' => $e->getMessage()];
            }
            if (empty($t['success'])) {
                $msg = ucfirst($accion) . ': ' . ($t['error'] ?? $t['message'] ?? 'no se pudo');
                break;
            }
        }
        if ($msg === '') {
            $res['confirmadas']++;
            $res['items'][] = ['factura_proveedor_id' => $id, 'estado' => 'confirmada', 'mensaje' => 'Confirmada.'];
        } else {
            $res['errores']++;
            $res['items'][] = ['factura_proveedor_id' => $id, 'estado' => 'error', 'mensaje' => $msg];
        }
    }
    return $res;
}
