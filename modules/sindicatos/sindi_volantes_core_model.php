<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_volantes_core_model.php
 * Motor de generación, emisión, código de barras BNA y gestión de volantes de pago (§2.3, §5.4, §7.3.2).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/sindi_base_model.php';
require_once __DIR__ . '/sindi_intereses_core_model.php';
require_once __DIR__ . '/sindi_pagos_drivers.php';

/**
 * Obtiene todas las deudas pendientes de un empleador no incluidas en otros volantes vigentes.
 */
function sindi_obtener_deudas_pendientes_empleador(mysqli $conexion, int $empresa_id, int $empleador_id): array
{
    // Obtener entidad_id del empleador
    $sqlEnt = "SELECT entidad_id FROM sindi__empleadores WHERE empresa_id = ? AND empleador_id = ? LIMIT 1";
    $stmtE = mysqli_prepare($conexion, $sqlEnt);
    if (!$stmtE) return [];
    mysqli_stmt_bind_param($stmtE, "ii", $empresa_id, $empleador_id);
    mysqli_stmt_execute($stmtE);
    $rE = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtE));
    mysqli_stmt_close($stmtE);
    if (!$rE) return [];
    return sindi_obtener_deudas_pendientes_entidad($conexion, $empresa_id, (int)$rE['entidad_id']);
}

/**
 * Deudas del módulo con saldo de una entidad (empleador o afiliado con cuota directa) que no estén en un
 * volante vigente.
 */
function sindi_obtener_deudas_pendientes_entidad(mysqli $conexion, int $empresa_id, int $entidad_id): array
{
    // Comprobantes pendientes que NO estén en volantes vigentes (Borrador 3 o Emitido 31)
    $sqlDeudas = "SELECT c.comprobante_id, c.comprobante_codigo, c.comprobante_pv, c.comprobante_nro,
                         c.f_emision, c.f_vto, c.importe_total, c.importe_pendiente,
                         ct.codigo AS tipo_codigo, ct.comprobante_tipo AS tipo_nombre,
                         COALESCE(d.periodo, 0) AS periodo, dc.concepto_id, sc.codigo AS concepto_codigo, sc.descripcion AS concepto_descripcion
                  FROM gestion__comprobantes c
                  INNER JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                  LEFT JOIN sindi__ddjj_conceptos dc ON dc.comprobante_id = c.comprobante_id
                  LEFT JOIN sindi__ddjj d ON d.ddjj_id = dc.ddjj_id
                  LEFT JOIN sindi__conceptos sc ON sc.concepto_id = dc.concepto_id
                  WHERE c.empresa_id = ? AND c.entidad_id = ? AND c.signo = 1 AND c.importe_pendiente > 0
                    AND c.modulo = 'sindicatos'
                    AND c.comprobante_id NOT IN (
                        SELECT vd.comprobante_id
                        FROM sindi__volantes_detalles vd
                        INNER JOIN sindi__volantes v ON v.volante_id = vd.volante_id
                        WHERE v.empresa_id = ? AND v.tabla_estado_registro_id IN (3, 31)
                    )
                  ORDER BY c.f_vto ASC, c.comprobante_id ASC";

    $stmtD = mysqli_prepare($conexion, $sqlDeudas);
    if (!$stmtD) return [];
    mysqli_stmt_bind_param($stmtD, "iii", $empresa_id, $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmtD);
    $resD = mysqli_stmt_get_result($stmtD);
    $deudas = [];
    while ($row = mysqli_fetch_assoc($resD)) {
        $deudas[] = [
            'comprobante_id' => (int)$row['comprobante_id'],
            'comprobante_codigo' => $row['comprobante_codigo'] ?? '',
            'tipo_codigo' => $row['tipo_codigo'] ?? '',
            'tipo_nombre' => $row['tipo_nombre'] ?? '',
            'periodo' => (int)($row['periodo'] ?? 0),
            'concepto_id' => !empty($row['concepto_id']) ? (int)$row['concepto_id'] : null,
            'concepto_codigo' => $row['concepto_codigo'] ?? '',
            'concepto_descripcion' => $row['concepto_descripcion'] ?? '',
            'f_emision' => $row['f_emision'],
            'f_vto' => $row['f_vto'],
            'importe_total' => (float)$row['importe_total'],
            'saldo_deuda' => (float)$row['importe_pendiente']
        ];
    }
    mysqli_stmt_close($stmtD);
    return $deudas;
}

/**
 * Genera el código de barras para Banco Nación (Code128 posicional) según especificación (§2.3).
 * Composición: Prefijo (9) + Empleador (10) + Volante (10) + Fecha AAAAMMDD (8) + Importe centavos (10)
 */
function sindi_generar_codigo_barras_bna(
    int $empleador_id,
    int $volante_id,
    string $f_pago_1,
    float $importe_1,
    string $prefijo
): string {
    $prefijoStr = str_pad(substr(preg_replace('/\D/', '', $prefijo), 0, 9), 9, '0', STR_PAD_RIGHT);
    $empStr = str_pad((string)$empleador_id, 10, '0', STR_PAD_LEFT);
    $volStr = str_pad((string)$volante_id, 10, '0', STR_PAD_LEFT);
    $fecStr = date('Ymd', strtotime($f_pago_1));
    $centavos = (int)round($importe_1 * 100);
    $impStr = str_pad((string)$centavos, 10, '0', STR_PAD_LEFT);

    return $prefijoStr . $empStr . $volStr . $fecStr . $impStr;
}

/**
 * Emite un nuevo volante de pago para un empleador, fijando intereses y código de barras.
 */
function sindi_emitir_volante(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    array $datosVolante,
    array $comprobante_ids
): array {
    $empleador_id = (int)($datosVolante['empleador_id'] ?? 0);
    $persona_id = (int)($datosVolante['persona_id'] ?? 0);
    $f_pago_1 = (string)($datosVolante['f_pago_1'] ?? date('Y-m-d', strtotime('+10 days')));
    $f_pago_2 = (string)($datosVolante['f_pago_2'] ?? date('Y-m-d', strtotime('+20 days')));
    $observaciones = (string)($datosVolante['observaciones'] ?? '');

    if (($empleador_id > 0) === ($persona_id > 0)) {
        return ['success' => false, 'message' => 'Indique el empleador o el afiliado del volante (uno de los dos).'];
    }
    $tipoVolante = $empleador_id > 0 ? 'EMPLEADOR' : 'AFILIADO';

    if (empty($comprobante_ids)) {
        return ['success' => false, 'message' => 'Debe seleccionar al menos una deuda para incluir en el volante.'];
    }

    if (strtotime($f_pago_2) < strtotime($f_pago_1)) {
        return ['success' => false, 'message' => 'El segundo vencimiento no puede ser anterior al primero.'];
    }

    // Validar autorización multi-empleador
    if ($empleador_id > 0) {
        AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);
    }

    // Tasas resarcitorias
    $tasas = sindi_obtener_tasas_interes($conexion, $empresa_id, 1);

    // Medios de cobro activos (para publicar y para el prefijo del código de barras del Banco Nación)
    $stmtMp = mysqli_prepare($conexion, "SELECT * FROM sindi__medios_pago WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY orden, medio_pago_id");
    mysqli_stmt_bind_param($stmtMp, "i", $empresa_id);
    mysqli_stmt_execute($stmtMp);
    $mediosActivos = mysqli_fetch_all(mysqli_stmt_get_result($stmtMp), MYSQLI_ASSOC);
    mysqli_stmt_close($stmtMp);
    $prefijoBarras = null;
    foreach ($mediosActivos as $mp) {
        if ($mp['driver'] === 'BNA_BARRAS' && trim((string)$mp['barras_prefijo']) !== '') {
            $prefijoBarras = trim((string)$mp['barras_prefijo']);
            break;
        }
    }

    mysqli_begin_transaction($conexion);
    try {
        // Titular bloqueado (empleador o afiliado): dos volantes simultáneos no pueden incluir la misma deuda
        if ($empleador_id > 0) {
            $stmtLk = mysqli_prepare($conexion, "SELECT empleador_id FROM sindi__empleadores WHERE empleador_id = ? AND empresa_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmtLk, "ii", $empleador_id, $empresa_id);
            mysqli_stmt_execute($stmtLk);
            mysqli_stmt_get_result($stmtLk);
            mysqli_stmt_close($stmtLk);
            $deudasPendientes = sindi_obtener_deudas_pendientes_empleador($conexion, $empresa_id, $empleador_id);
        } else {
            $stmtLk = mysqli_prepare($conexion, "SELECT entidad_id FROM sindi__personas WHERE persona_id = ? AND empresa_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmtLk, "ii", $persona_id, $empresa_id);
            mysqli_stmt_execute($stmtLk);
            $entidadPersona = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmtLk))[0] ?? 0);
            mysqli_stmt_close($stmtLk);
            if ($entidadPersona <= 0) {
                throw new RuntimeException('El afiliado no tiene cuenta corriente: no tiene cuotas directas generadas.');
            }
            $deudasPendientes = sindi_obtener_deudas_pendientes_entidad($conexion, $empresa_id, $entidadPersona);
        }
        $deudasAProcesar = [];
        $idsMap = array_flip(array_map('intval', $comprobante_ids));
        foreach ($deudasPendientes as $dp) {
            if (isset($idsMap[$dp['comprobante_id']])) {
                $deudasAProcesar[] = $dp;
            }
        }
        if (empty($deudasAProcesar)) {
            throw new RuntimeException('No se encontraron deudas pendientes válidas para los comprobantes seleccionados (pueden estar en otro volante vigente).');
        }

        // Importes e intereses a las dos fechas, fijados al emitir (§7.3.2)
        $calculoVolante = sindi_calcular_intereses_volante_completo($deudasAProcesar, $f_pago_1, $f_pago_2, $tasas);

        // Número y código de barras se completan con el id asignado
        $numeroVolante = 'TMP-' . bin2hex(random_bytes(8)); // único hasta tener el id (uk_volante_numero)
        $codBarras = '';

        // Insertar cabecera de volante (estado 31 Emitido)
        $sqlInsV = "INSERT INTO sindi__volantes (
            empresa_id, empleador_id, persona_id, tipo_volante, numero, f_emision,
            f_pago_1, importe_capital_1, importe_interes_1, importe_1,
            f_pago_2, importe_capital_2, importe_interes_2, importe_2,
            codigo_barras, tabla_estado_registro_id, observaciones, usuario_creacion_id, fecha_creacion
        ) VALUES (
            ?, ?, ?, ?, ?, CURDATE(),
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, 31, ?, ?, NOW()
        )";
        $stmtV = mysqli_prepare($conexion, $sqlInsV);
        $impCap1 = $calculoVolante['importe_capital_1'];
        $impInt1 = $calculoVolante['importe_interes_1'];
        $impTot1 = $calculoVolante['importe_total_1'];
        $impCap2 = $calculoVolante['importe_capital_2'];
        $impInt2 = $calculoVolante['importe_interes_2'];
        $impTot2 = $calculoVolante['importe_total_2'];

        $empleadorNullable = $empleador_id > 0 ? $empleador_id : null;
        $personaNullable = $persona_id > 0 ? $persona_id : null;
        mysqli_stmt_bind_param(
            $stmtV,
            "iiisssdddsdddssi",
            $empresa_id, $empleadorNullable, $personaNullable, $tipoVolante, $numeroVolante,
            $f_pago_1, $impCap1, $impInt1, $impTot1,
            $f_pago_2, $impCap2, $impInt2, $impTot2,
            $codBarras, $observaciones, $usuario_id
        );
        mysqli_stmt_execute($stmtV);
        $volante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmtV);

        // Número y código de barras con el id definitivo. El prefijo es el del medio Banco Nación de la
        // empresa (sindi__medios_pago.barras_prefijo); sin ese medio, el código es el número del volante.
        $numeroVolanteDef = sprintf("VOL-%08d", $volante_id);
        $codBarrasDef = $prefijoBarras !== null
            ? sindi_generar_codigo_barras_bna($empleador_id > 0 ? $empleador_id : $persona_id, $volante_id, $f_pago_1, $impTot1, $prefijoBarras)
            : $numeroVolanteDef;
        $stmtNum = mysqli_prepare($conexion, "UPDATE sindi__volantes SET numero = ?, codigo_barras = ? WHERE volante_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmtNum, "ssii", $numeroVolanteDef, $codBarrasDef, $volante_id, $empresa_id);
        mysqli_stmt_execute($stmtNum);
        mysqli_stmt_close($stmtNum);

        // Insertar detalles e intereses de auditoría
        $sqlDet = "INSERT INTO sindi__volantes_detalles (
            volante_id, empresa_id, comprobante_id, concepto_id, periodo, f_vto_original,
            saldo_deuda, interes_1, subtotal_1, interes_2, subtotal_2, tabla_estado_registro_id, fecha_creacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        $stmtDet = mysqli_prepare($conexion, $sqlDet);

        $sqlIntAud = "INSERT INTO sindi__volantes_detalles_intereses (
            volante_detalle_id, empresa_id, vencimiento_nro, tasa_interes_id,
            f_desde, f_hasta, dias, tasa_mensual, interes_calculado, fecha_creacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmtIA = mysqli_prepare($conexion, $sqlIntAud);

        foreach ($calculoVolante['detalles'] as $det) {
            $cId = (int)$det['comprobante_id'];
            $concId = !empty($det['concepto_id']) ? (int)$det['concepto_id'] : null;
            $per = (int)$det['periodo'];
            $fvOrig = (string)$det['f_vto_original'];
            $sDeuda = (float)$det['saldo_deuda'];
            $i1 = (float)$det['interes_1'];
            $sub1 = (float)$det['subtotal_1'];
            $i2 = (float)$det['interes_2'];
            $sub2 = (float)$det['subtotal_2'];

            mysqli_stmt_bind_param(
                $stmtDet,
                "iiiissddddd",
                $volante_id, $empresa_id, $cId, $concId, $per, $fvOrig,
                $sDeuda, $i1, $sub1, $i2, $sub2
            );
            mysqli_stmt_execute($stmtDet);
            $vDetId = mysqli_insert_id($conexion);

            // Tramos vencimiento 1
            foreach ($det['tramos_1'] as $t1) {
                $vNro = 1;
                $tId = !empty($t1['tasa_interes_id']) ? (int)$t1['tasa_interes_id'] : null;
                $fD = (string)$t1['f_desde'];
                $fH = (string)$t1['f_hasta'];
                $ds = (int)$t1['dias'];
                $tm = (float)$t1['tasa_mensual'];
                $ic = (float)$t1['interes'];

                mysqli_stmt_bind_param($stmtIA, "iiiissidd", $vDetId, $empresa_id, $vNro, $tId, $fD, $fH, $ds, $tm, $ic);
                mysqli_stmt_execute($stmtIA);
            }

            // Tramos vencimiento 2
            foreach ($det['tramos_2'] as $t2) {
                $vNro = 2;
                $tId = !empty($t2['tasa_interes_id']) ? (int)$t2['tasa_interes_id'] : null;
                $fD = (string)$t2['f_desde'];
                $fH = (string)$t2['f_hasta'];
                $ds = (int)$t2['dias'];
                $tm = (float)$t2['tasa_mensual'];
                $ic = (float)$t2['interes'];

                mysqli_stmt_bind_param($stmtIA, "iiiissidd", $vDetId, $empresa_id, $vNro, $tId, $fD, $fH, $ds, $tm, $ic);
                mysqli_stmt_execute($stmtIA);
            }
        }
        mysqli_stmt_close($stmtDet);
        mysqli_stmt_close($stmtIA);

        // Publicar en canales de pago configurados (§7.3)
        $volanteData = [
            'volante_id' => $volante_id,
            'numero' => $numeroVolanteDef,
            'empleador_id' => $empleador_id,
            'codigo_barras' => $codBarrasDef,
            'importe_1' => $impTot1,
            'f_pago_1' => $f_pago_1
        ];

        foreach ($mediosActivos as $mp) {
            $driver = SindiPagosFactory::obtenerDriver($mp['driver']);
            $resPub = $driver->publicarDeuda($conexion, $empresa_id, $volanteData, $mp);

            $mpId = (int)$mp['medio_pago_id'];
            $idExt = (string)($resPub['id_externo'] ?? '');
            $codRes = (string)($resPub['codigo'] ?? 'OK');
            $detRes = (string)($resPub['mensaje'] ?? '');
            $estCanal = ($resPub['success'] ?? false) ? 'PUBLICADO' : 'RECHAZADO';

            $sqlCanal = "INSERT INTO sindi__volantes_canales (
                volante_id, empresa_id, medio_pago_id, id_deuda_externo, estado_canal,
                f_publicacion, resultado_codigo, resultado_detalle, fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, NOW())";
            $stmtC = mysqli_prepare($conexion, $sqlCanal);
            mysqli_stmt_bind_param($stmtC, "iiissss", $volante_id, $empresa_id, $mpId, $idExt, $estCanal, $codRes, $detRes);
            mysqli_stmt_execute($stmtC);
            mysqli_stmt_close($stmtC);
        }

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => 'Volante de pago emitido exitosamente.',
            'volante_id' => $volante_id,
            'numero' => $numeroVolanteDef,
            'codigo_barras' => $codBarrasDef,
            'importe_1' => $impTot1,
            'f_pago_1' => $f_pago_1,
            'importe_2' => $impTot2,
            'f_pago_2' => $f_pago_2
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("[SINDI_VOLANTES] Error al emitir volante: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al emitir volante: ' . $e->getMessage()];
    }
}

/**
 * Obtiene un volante completo por su ID con detalles de deuda y canales.
 */
function sindi_obtener_volante(mysqli $conexion, int $empresa_id, int $usuario_id, int $volante_id): ?array
{
    $sqlCab = "SELECT v.*, COALESCE(ent.entidad_nombre, CONCAT(per.apellido, ' ', per.nombre)) AS empleador_nombre, COALESCE(ent.cuit, per.cuil) AS empleador_cuit,
                      er.tabla_estado_registro AS estado_nombre, er.color_id AS estado_color_id
               FROM sindi__volantes v
               LEFT JOIN sindi__empleadores emp ON emp.empleador_id = v.empleador_id
               LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
               LEFT JOIN sindi__personas per ON per.persona_id = v.persona_id
               LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 528 AND er.estado_registro_id = v.tabla_estado_registro_id
               WHERE v.volante_id = ? AND v.empresa_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sqlCab);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $volante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $cabecera = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$cabecera) return null;

    if (!empty($cabecera['empleador_id'])) {
        AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, (int)$cabecera['empleador_id']);
    } elseif (!AccesosEmpleadoresHelper::obtenerAccesos($conexion, $usuario_id, $empresa_id)['tiene_acceso_total']) {
        throw new RuntimeException('Acceso denegado al volante.');
    }

    // Detalles
    $sqlDet = "SELECT vd.*, c.comprobante_codigo, ct.comprobante_tipo AS tipo_nombre,
                      sc.codigo AS concepto_codigo, sc.descripcion AS concepto_descripcion
               FROM sindi__volantes_detalles vd
               INNER JOIN gestion__comprobantes c ON c.comprobante_id = vd.comprobante_id
               INNER JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
               LEFT JOIN sindi__conceptos sc ON sc.concepto_id = vd.concepto_id
               WHERE vd.volante_id = ? AND vd.empresa_id = ?
               ORDER BY vd.volante_detalle_id ASC";
    $stmtD = mysqli_prepare($conexion, $sqlDet);
    mysqli_stmt_bind_param($stmtD, "ii", $volante_id, $empresa_id);
    mysqli_stmt_execute($stmtD);
    $resD = mysqli_stmt_get_result($stmtD);
    $detalles = [];
    while ($r = mysqli_fetch_assoc($resD)) {
        $detalles[] = $r;
    }
    mysqli_stmt_close($stmtD);
    $cabecera['detalles'] = $detalles;

    return $cabecera;
}

/**
 * Lista volantes de pago con filtro multi-empleador y DataTables.
 */
function sindi_listar_volantes(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    int $pagina_id,
    ?int $empleador_id = null
): array {
    $filtroAcceso = AccesosEmpleadoresHelper::filtroSql($conexion, $usuario_id, $empresa_id, 'v.empleador_id');
    $whereExtra = "";
    $params = [$empresa_id];
    $types = "i";

    if ($empleador_id !== null && $empleador_id > 0) {
        $whereExtra .= " AND v.empleador_id = ? ";
        $params[] = $empleador_id;
        $types .= "i";
    }

    $sql = "SELECT v.*, COALESCE(ent.entidad_nombre, CONCAT(per.apellido, ' ', per.nombre)) AS empleador_nombre, COALESCE(ent.cuit, per.cuil) AS empleador_cuit,
                   er.tabla_estado_registro AS estado_nombre, er.color_id AS estado_color_id
            FROM sindi__volantes v
            LEFT JOIN sindi__empleadores emp ON emp.empleador_id = v.empleador_id
            LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN sindi__personas per ON per.persona_id = v.persona_id
            LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 528 AND er.estado_registro_id = v.tabla_estado_registro_id
            WHERE v.empresa_id = ? AND {$filtroAcceso} {$whereExtra}
            ORDER BY v.volante_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['volante_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Anula un volante y despublica su deuda en los canales habilitados.
 */
function sindi_anular_volante(mysqli $conexion, int $empresa_id, int $usuario_id, int $volante_id): array
{
    $volante = sindi_obtener_volante($conexion, $empresa_id, $usuario_id, $volante_id);
    if (!$volante) {
        return ['success' => false, 'message' => 'Volante no encontrado.'];
    }

    if ((int)$volante['tabla_estado_registro_id'] !== 31 && (int)$volante['tabla_estado_registro_id'] !== 3) {
        return ['success' => false, 'message' => 'Solo se pueden anular volantes en estado Borrador o Emitido.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // Despublicar en canales
        $sqlCanales = "SELECT vc.*, mp.driver, mp.nombre AS medio_nombre
                       FROM sindi__volantes_canales vc
                       INNER JOIN sindi__medios_pago mp ON mp.medio_pago_id = vc.medio_pago_id
                       WHERE vc.volante_id = ? AND vc.empresa_id = ?";
        $stmtC = mysqli_prepare($conexion, $sqlCanales);
        mysqli_stmt_bind_param($stmtC, "ii", $volante_id, $empresa_id);
        mysqli_stmt_execute($stmtC);
        $resC = mysqli_stmt_get_result($stmtC);
        while ($c = mysqli_fetch_assoc($resC)) {
            $driver = SindiPagosFactory::obtenerDriver($c['driver']);
            $driver->anularDeuda($conexion, $empresa_id, $c, $c);
            $stmtDp = mysqli_prepare($conexion, "UPDATE sindi__volantes_canales SET estado_canal = 'DESPUBLICADO', f_baja = NOW() WHERE volante_canal_id = ?");
            $vcId = (int)$c['volante_canal_id'];
            mysqli_stmt_bind_param($stmtDp, "i", $vcId);
            mysqli_stmt_execute($stmtDp);
            mysqli_stmt_close($stmtDp);
        }
        mysqli_stmt_close($stmtC);

        // Pasar volante a estado 38 (Anulado)
        $stmtAn = mysqli_prepare($conexion, "UPDATE sindi__volantes SET tabla_estado_registro_id = 38, usuario_actualizacion_id = ? WHERE volante_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmtAn, "iii", $usuario_id, $volante_id, $empresa_id);
        mysqli_stmt_execute($stmtAn);
        mysqli_stmt_close($stmtAn);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Volante anulado y despublicado exitosamente.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("[SINDI_VOLANTES] Error al anular volante: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al anular volante: ' . $e->getMessage()];
    }
}
