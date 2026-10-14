<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_ddjj_core_model.php
 * Motor unificado de cálculo, validación, confirmación y rectificativas de DDJJ.
 *
 * Sigue estrictamente la especificación técnica (§2.2, §5.3, §7.1, §7.2, §8.2).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../gestion_normal/tesoreria_core_model.php';
require_once __DIR__ . '/sindi_base_model.php';
require_once __DIR__ . '/sindi_comprobantes_core.php';
require_once __DIR__ . '/parametros_model.php';
require_once __DIR__ . '/sindi_importadores.php';
require_once __DIR__ . '/sindi_afiliaciones_core_model.php';

/**
 * Obtiene los conceptos de aporte activos configurados para la empresa.
 */
function sindi_obtener_conceptos_activos(mysqli $conexion, int $empresa_id): array
{
    $sql = "SELECT concepto_id, codigo, descripcion, base_calculo, porcentaje, valor_fijo,
                   solo_afiliados, aplica_piso_escala, incluye_sac, cuenta_concepto_codigo
            FROM sindi__conceptos
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1
            ORDER BY concepto_id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $conceptos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $conceptos[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $conceptos;
}

/**
 * Obtiene la escala salarial del CCT para una categoría y período.
 */
function sindi_obtener_escala_categoria(mysqli $conexion, int $empresa_id, int $categoria_id, int $periodo): array
{
    $sql = "SELECT basico, rem_1, rem_2, (basico + rem_1 + rem_2) AS escala_total
            FROM sindi__categorias_escalas
            WHERE empresa_id = ? AND categoria_id = ? AND periodo <= ? AND tabla_estado_registro_id = 1
            ORDER BY periodo DESC LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['basico' => 0.0, 'rem_1' => 0.0, 'rem_2' => 0.0, 'escala_total' => 0.0];
    }
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $categoria_id, $periodo);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        return ['basico' => 0.0, 'rem_1' => 0.0, 'rem_2' => 0.0, 'escala_total' => 0.0];
    }

    return [
        'basico' => (float)$row['basico'],
        'rem_1' => (float)$row['rem_1'],
        'rem_2' => (float)$row['rem_2'],
        'escala_total' => (float)$row['escala_total']
    ];
}

/**
 * Calcula los aportes de un renglón de empleado frente a los conceptos del sindicato.
 */
function sindi_calcular_renglon_empleado(
    mysqli $conexion,
    int $empresa_id,
    int $periodo,
    array $empData,
    array $conceptos,
    string $media_jornada_piso,
    float $media_jornada_factor
): array {
    $rem1 = (float)($empData['rem_1'] ?? 0.0);
    $rem2 = (float)($empData['rem_2'] ?? 0.0);
    $baseDeclarada = round($rem1 + $rem2, 2);
    $mediaJornada = !empty($empData['media_jornada']) ? 1 : 0;
    $esAfiliado = !empty($empData['afiliado']) ? 1 : 0;
    $categoriaId = (int)($empData['categoria_id'] ?? 0);

    // Escala salarial de la categoría
    $escalaInfo = sindi_obtener_escala_categoria($conexion, $empresa_id, $categoriaId, $periodo);
    $escalaTotal = $escalaInfo['escala_total'];

    // Si es mes 6 o 12, se adiciona el SAC proporcional (50%)
    $mes = $periodo % 100;
    $esMesSac = ($mes === 6 || $mes === 12);
    if ($esMesSac && $escalaTotal > 0) {
        $escalaTotal = round($escalaTotal * 1.50, 2);
    }

    // Aplicar regla de media jornada parametrizada (§2.2, §5.1)
    $pisoCalculado = $escalaTotal;
    if ($mediaJornada === 1) {
        if ($media_jornada_piso === 'COMPLETO') {
            $pisoCalculado = $escalaTotal; // AECBB legacy behavior
        } elseif ($media_jornada_piso === 'PROPORCIONAL') {
            $pisoCalculado = round($escalaTotal * $media_jornada_factor, 2);
        } elseif ($media_jornada_piso === 'SIN_PISO') {
            $pisoCalculado = 0.0;
        }
    }

    $calculos = [];
    foreach ($conceptos as $c) {
        $conceptoId = (int)$c['concepto_id'];
        $porcentaje = (float)$c['porcentaje'];
        $valorFijo = (float)$c['valor_fijo'];
        $soloAfiliados = (int)$c['solo_afiliados'];
        $aplicaPiso = (int)$c['aplica_piso_escala'];

        // Si el concepto es exclusivo para afiliados y el empleado no está marcado como afiliado
        if ($soloAfiliados === 1 && $esAfiliado === 0) {
            $calculos[$conceptoId] = [
                'concepto_id' => $conceptoId,
                'base_declarada' => $baseDeclarada,
                'piso_escala' => 0.0,
                'base_aplicada' => 0.0,
                'porcentaje' => $porcentaje,
                'valor_fijo' => $valorFijo,
                'importe' => 0.0
            ];
            continue;
        }

        $pisoRenglon = ($aplicaPiso === 1) ? $pisoCalculado : 0.0;
        $baseAplicada = max($baseDeclarada, $pisoRenglon);
        $importe = round(($baseAplicada * ($porcentaje / 100.0)) + $valorFijo, 2);

        $calculos[$conceptoId] = [
            'concepto_id' => $conceptoId,
            'base_declarada' => $baseDeclarada,
            'piso_escala' => $pisoRenglon,
            'base_aplicada' => $baseAplicada,
            'porcentaje' => $porcentaje,
            'valor_fijo' => $valorFijo,
            'importe' => $importe
        ];
    }

    return [
        'base_declarada' => $baseDeclarada,
        'piso_calculado' => $pisoCalculado,
        'calculos' => $calculos
    ];
}

/**
 * Resuelve la fecha de vencimiento según la terminación del CUIT del empleador.
 */
function sindi_resolver_vencimiento_ddjj(mysqli $conexion, int $empresa_id, int $empleador_id, int $periodo): string
{
    // Obtener último dígito de CUIT
    $sqlC = "SELECT ent.cuit FROM sindi__empleadores emp
             INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
             WHERE emp.empleador_id = ? AND emp.empresa_id = ? LIMIT 1";
    $stmtC = mysqli_prepare($conexion, $sqlC);
    if (!$stmtC) return date('Y-m-d', strtotime('+15 days'));
    mysqli_stmt_bind_param($stmtC, "ii", $empleador_id, $empresa_id);
    mysqli_stmt_execute($stmtC);
    $rowC = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
    mysqli_stmt_close($stmtC);

    $cuit = preg_replace('/\D/', '', (string)($rowC['cuit'] ?? ''));
    $digito = !empty($cuit) ? (int)substr($cuit, -1) : 0;

    $sqlV = "SELECT f_vto FROM sindi__vencimientos
             WHERE empresa_id = ? AND periodo = ? AND terminacion_cuit = ? AND tabla_estado_registro_id = 1 LIMIT 1";
    $stmtV = mysqli_prepare($conexion, $sqlV);
    if ($stmtV) {
        mysqli_stmt_bind_param($stmtV, "iii", $empresa_id, $periodo, $digito);
        mysqli_stmt_execute($stmtV);
        $rowV = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtV));
        mysqli_stmt_close($stmtV);
        if ($rowV && !empty($rowV['f_vto'])) {
            return $rowV['f_vto'];
        }
    }

    // Default: día 15 del mes siguiente al período
    $anio = intval(substr((string)$periodo, 0, 4));
    $mes = intval(substr((string)$periodo, 4, 2));
    $mesSig = $mes + 1;
    $anioSig = $anio;
    if ($mesSig > 12) {
        $mesSig = 1;
        $anioSig++;
    }
    return sprintf('%04d-%02d-15', $anioSig, $mesSig);
}

/**
 * Guarda o actualiza un borrador completo de Declaración Jurada.
 */
/**
 * Sucursal (establecimiento) de un renglón de la DDJJ. Si viene, tiene que ser una sucursal numerada y activa
 * del empleador; si no, la del último renglón presentado de esa persona con ese empleador o la Casa Central.
 */
function sindi_resolver_sucursal_renglon(mysqli $conexion, int $empresa_id, int $empleador_id, int $persona_id, ?int $sucursal_pedida): int
{
    if ($sucursal_pedida !== null && $sucursal_pedida > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT s.sucursal_id FROM gestion__entidades_sucursales s
                                           INNER JOIN sindi__empleadores e ON e.entidad_id = s.entidad_id
                                           WHERE s.sucursal_id = ? AND e.empleador_id = ? AND e.empresa_id = ?
                                             AND s.sucursal_numero IS NOT NULL AND s.tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, "iii", $sucursal_pedida, $empleador_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $ok = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$ok) {
            throw new RuntimeException('La sucursal indicada no es una sucursal activa del empleador.');
        }
        return $sucursal_pedida;
    }

    $stmt = mysqli_prepare($conexion, "SELECT de.sucursal_id FROM sindi__ddjj_empleados de
                                       INNER JOIN sindi__ddjj d ON d.ddjj_id = de.ddjj_id
                                       INNER JOIN gestion__entidades_sucursales s ON s.sucursal_id = de.sucursal_id AND s.tabla_estado_registro_id = 1
                                       WHERE d.empresa_id = ? AND d.empleador_id = ? AND de.persona_id = ? AND d.tabla_estado_registro_id IN (2, 28)
                                       ORDER BY d.periodo DESC, d.secuencia DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $empleador_id, $persona_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($fila && (int)$fila[0] > 0) {
        return (int)$fila[0];
    }

    $stmt = mysqli_prepare($conexion, "SELECT s.sucursal_id FROM gestion__entidades_sucursales s
                                       INNER JOIN sindi__empleadores e ON e.entidad_id = s.entidad_id
                                       WHERE e.empleador_id = ? AND e.empresa_id = ? AND s.sucursal_numero = 1");
    mysqli_stmt_bind_param($stmt, "ii", $empleador_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        throw new RuntimeException('El empleador no tiene Casa Central (sucursal N° 1): revise sus sucursales.');
    }
    return (int)$fila[0];
}

/** Sucursales activas y numeradas del empleador, para el combo de la nómina. */
function sindi_sucursales_empleador_combo(mysqli $conexion, int $empresa_id, int $usuario_id, int $empleador_id): array
{
    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);
    $stmt = mysqli_prepare($conexion, "SELECT s.sucursal_id, s.sucursal_numero, s.sucursal_nombre
                                       FROM gestion__entidades_sucursales s
                                       INNER JOIN sindi__empleadores e ON e.entidad_id = s.entidad_id
                                       WHERE e.empleador_id = ? AND e.empresa_id = ? AND s.sucursal_numero IS NOT NULL
                                         AND s.tabla_estado_registro_id = 1
                                       ORDER BY s.sucursal_numero");
    mysqli_stmt_bind_param($stmt, "ii", $empleador_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $lista = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $lista;
}

function sindi_guardar_borrador_ddjj(mysqli $conexion, int $empresa_id, int $usuario_id, array $cabecera, array $nomina): array
{
    $empleador_id = (int)($cabecera['empleador_id'] ?? 0);
    $periodo = (int)($cabecera['periodo'] ?? 0);
    $ddjj_id = (int)($cabecera['ddjj_id'] ?? 0);
    $tipo = in_array($cabecera['tipo'] ?? '', ['EMPLEADOR', 'OFICIO'], true) ? $cabecera['tipo'] : 'EMPLEADOR';
    $origen = in_array($cabecera['origen_ingreso'] ?? '', ['MANUAL', 'F931', 'PLANILLA', 'LOTE', 'EMAIL', 'MIGRACION'], true)
        ? $cabecera['origen_ingreso']
        : 'MANUAL';

    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);

    // Obtener parámetros del sindicato
    $params = sindi_obtener_parametros($conexion, $empresa_id);
    $mediaJornadaPiso = $params['media_jornada_piso'] ?? 'COMPLETO';
    $mediaJornadaFactor = (float)($params['media_jornada_factor'] ?? 0.50);

    $conceptos = sindi_obtener_conceptos_activos($conexion, $empresa_id);
    if (empty($conceptos)) {
        return ['success' => false, 'message' => 'No hay conceptos de aporte activos configurados para este sindicato.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        $f_vto = sindi_resolver_vencimiento_ddjj($conexion, $empresa_id, $empleador_id, $periodo);

        if ($ddjj_id > 0) {
            // Verificar que siga en borrador
            $sqlChk = "SELECT ddjj_id, tabla_estado_registro_id FROM sindi__ddjj WHERE ddjj_id = ? AND empresa_id = ? LIMIT 1";
            $stmtChk = mysqli_prepare($conexion, $sqlChk);
            mysqli_stmt_bind_param($stmtChk, "ii", $ddjj_id, $empresa_id);
            mysqli_stmt_execute($stmtChk);
            $rowChk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtChk));
            mysqli_stmt_close($stmtChk);

            if (!$rowChk || (int)$rowChk['tabla_estado_registro_id'] !== 3) {
                throw new RuntimeException('La declaración jurada ya ha sido presentada o no se encuentra en estado borrador.');
            }

            // Limpiar renglones anteriores para regenerar
            foreach (['sindi__ddjj_calculos', 'sindi__ddjj_empleados', 'sindi__ddjj_conceptos'] as $tablaDetalle) {
                $stmtDel = mysqli_prepare($conexion, "DELETE FROM {$tablaDetalle} WHERE ddjj_id = ?");
                mysqli_stmt_bind_param($stmtDel, "i", $ddjj_id);
                mysqli_stmt_execute($stmtDel);
                mysqli_stmt_close($stmtDel);
            }
        } else {
            // Determinar secuencia: si ya existe una presentada para el mismo período, es rectificativa
            $sqlSec = "SELECT COALESCE(MAX(secuencia), -1) AS max_sec,
                              (SELECT ddjj_id FROM sindi__ddjj WHERE empresa_id = ? AND empleador_id = ? AND periodo = ? AND tabla_estado_registro_id = 28 LIMIT 1) AS id_presentada
                       FROM sindi__ddjj
                       WHERE empresa_id = ? AND empleador_id = ? AND periodo = ?";
            $stmtSec = mysqli_prepare($conexion, $sqlSec);
            mysqli_stmt_bind_param($stmtSec, "iiiiii", $empresa_id, $empleador_id, $periodo, $empresa_id, $empleador_id, $periodo);
            mysqli_stmt_execute($stmtSec);
            $resSec = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSec));
            mysqli_stmt_close($stmtSec);

            $maxSec = (int)$resSec['max_sec'];
            $secuencia = $maxSec >= 0 ? ($maxSec + 1) : 0;
            $rectificadaId = !empty($resSec['id_presentada']) ? (int)$resSec['id_presentada'] : null;

            $codVerif = strtoupper(bin2hex(random_bytes(6)));

            $sqlIns = "INSERT INTO sindi__ddjj (
                empresa_id, empleador_id, periodo, secuencia, ddjj_rectificada_id, media_jornada_piso,
                tipo, origen_ingreso, f_vto, total, cantidad_empleados, codigo_verificacion,
                tabla_estado_registro_id, usuario_creacion_id, fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0, ?, 3, ?, NOW())";

            $stmtIns = mysqli_prepare($conexion, $sqlIns);
            mysqli_stmt_bind_param(
                $stmtIns,
                "iiiissssssi",
                $empresa_id, $empleador_id, $periodo, $secuencia, $rectificadaId, $mediaJornadaPiso,
                $tipo, $origen, $f_vto, $codVerif, $usuario_id
            );
            mysqli_stmt_execute($stmtIns);
            $ddjj_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtIns);
        }

        // Procesar empleados
        $totalGeneral = 0.0;
        $totalesPorConcepto = [];
        foreach ($conceptos as $c) {
            $totalesPorConcepto[(int)$c['concepto_id']] = [
                'base_total' => 0.0,
                'cantidad' => 0,
                'importe_total' => 0.0
            ];
        }

        $cantEmpleados = 0;
        foreach ($nomina as $emp) {
            $cuil = preg_replace('/\D/', '', (string)($emp['cuil'] ?? ''));
            // CUIL obligatorio y con dígito verificador válido (misma regla que el importador);
            // antes un renglón con CUIL incorrecto se descartaba en silencio
            if (!sindi_validar_cuil_arg($cuil)) {
                $nombreRenglon = trim((string)($emp['nombre'] ?? ''));
                throw new RuntimeException("CUIL inválido '{$cuil}'" . ($nombreRenglon !== '' ? " ({$nombreRenglon})" : '') . ": revise el número y el dígito verificador.");
            }

            // Resolver persona_id en sindi__personas
            $sqlPers = "SELECT persona_id FROM sindi__personas WHERE empresa_id = ? AND cuil = ? LIMIT 1";
            $stmtPers = mysqli_prepare($conexion, $sqlPers);
            mysqli_stmt_bind_param($stmtPers, "is", $empresa_id, $cuil);
            mysqli_stmt_execute($stmtPers);
            $pRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPers));
            mysqli_stmt_close($stmtPers);

            $persona_id = 0;
            if ($pRow) {
                $persona_id = (int)$pRow['persona_id'];
            } else {
                $nombreCompleto = trim($emp['nombre'] ?? 'Empleado');
                $partes = explode(' ', $nombreCompleto, 2);
                $apellido = $partes[0];
                $nombre = $partes[1] ?? '';
                $sqlNewP = "INSERT INTO sindi__personas (empresa_id, cuil, apellido, nombre, tabla_estado_registro_id, fecha_creacion)
                            VALUES (?, ?, ?, ?, 1, NOW())";
                $stmtNewP = mysqli_prepare($conexion, $sqlNewP);
                mysqli_stmt_bind_param($stmtNewP, "isss", $empresa_id, $cuil, $apellido, $nombre);
                mysqli_stmt_execute($stmtNewP);
                $persona_id = mysqli_insert_id($conexion);
                mysqli_stmt_close($stmtNewP);
            }

            $categoria_id = (int)($emp['categoria_id'] ?? 0);
            $rem_1 = (float)($emp['rem_1'] ?? 0.0);
            $rem_2 = (float)($emp['rem_2'] ?? 0.0);
            $rem_3 = (float)($emp['rem_3'] ?? 0.0);
            $media_jornada = !empty($emp['media_jornada']) ? 1 : 0;
            $jubilado = !empty($emp['jubilado']) ? 1 : 0;
            $afiliado = !empty($emp['afiliado']) ? 1 : 0;
            $obra_social_id = !empty($emp['obra_social_id']) ? (int)$emp['obra_social_id'] : null;
            $novedad_id = !empty($emp['novedad_id']) ? (int)$emp['novedad_id'] : null;
            $baja_motivo_id = !empty($emp['baja_motivo_id']) ? (int)$emp['baja_motivo_id'] : null;
            $f_baja = !empty($emp['f_baja']) ? $emp['f_baja'] : null;

            // Establecimiento del empleador donde trabaja (§5.3)
            $sucursal_id = sindi_resolver_sucursal_renglon(
                $conexion, $empresa_id, $empleador_id, $persona_id,
                !empty($emp['sucursal_id']) ? (int)$emp['sucursal_id'] : null
            );

            // Insertar sindi__ddjj_empleados
            $sqlEmpIns = "INSERT INTO sindi__ddjj_empleados (
                empresa_id, ddjj_id, persona_id, sucursal_id, categoria_id, rem_1, rem_2, rem_3,
                media_jornada, jubilado, afiliado, obra_social_id, novedad_id, baja_motivo_id, f_baja, tabla_estado_registro_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmtEmpIns = mysqli_prepare($conexion, $sqlEmpIns);
            mysqli_stmt_bind_param(
                $stmtEmpIns,
                "iiiiidddiiiiiis",
                $empresa_id, $ddjj_id, $persona_id, $sucursal_id, $categoria_id, $rem_1, $rem_2, $rem_3,
                $media_jornada, $jubilado, $afiliado, $obra_social_id, $novedad_id, $baja_motivo_id, $f_baja
            );
            mysqli_stmt_execute($stmtEmpIns);
            $ddjj_empleado_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtEmpIns);

            // Calcular conceptos
            $calcResult = sindi_calcular_renglon_empleado(
                $conexion, $empresa_id, $periodo,
                [
                    'categoria_id' => $categoria_id,
                    'rem_1' => $rem_1,
                    'rem_2' => $rem_2,
                    'media_jornada' => $media_jornada,
                    'afiliado' => $afiliado
                ],
                $conceptos,
                $mediaJornadaPiso,
                $mediaJornadaFactor
            );

            foreach ($calcResult['calculos'] as $cid => $calc) {
                $sqlCalcIns = "INSERT INTO sindi__ddjj_calculos (
                    empresa_id, ddjj_id, ddjj_empleado_id, concepto_id, base_declarada,
                    piso_escala, base_aplicada, porcentaje, valor_fijo, importe
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtCalcIns = mysqli_prepare($conexion, $sqlCalcIns);
                mysqli_stmt_bind_param(
                    $stmtCalcIns,
                    "iiiidddddd",
                    $empresa_id, $ddjj_id, $ddjj_empleado_id, $cid,
                    $calc['base_declarada'], $calc['piso_escala'], $calc['base_aplicada'],
                    $calc['porcentaje'], $calc['valor_fijo'], $calc['importe']
                );
                mysqli_stmt_execute($stmtCalcIns);
                mysqli_stmt_close($stmtCalcIns);

                if ($calc['importe'] > 0 || $calc['base_aplicada'] > 0) {
                    $totalesPorConcepto[$cid]['base_total'] += $calc['base_aplicada'];
                    $totalesPorConcepto[$cid]['cantidad'] += 1;
                    $totalesPorConcepto[$cid]['importe_total'] += $calc['importe'];
                    $totalGeneral += $calc['importe'];
                }
            }

            $cantEmpleados++;
        }

        // Insertar totales por concepto en sindi__ddjj_conceptos
        foreach ($totalesPorConcepto as $cid => $tot) {
            $baseTotal = round($tot['base_total'], 2);
            $cant = (int)$tot['cantidad'];
            $impTotal = round($tot['importe_total'], 2);

            $sqlConcIns = "INSERT INTO sindi__ddjj_conceptos (
                empresa_id, ddjj_id, concepto_id, base_total, cantidad_empleados, importe_total
            ) VALUES (?, ?, ?, ?, ?, ?)";
            $stmtConcIns = mysqli_prepare($conexion, $sqlConcIns);
            mysqli_stmt_bind_param($stmtConcIns, "iiidis", $empresa_id, $ddjj_id, $cid, $baseTotal, $cant, $impTotal);
            mysqli_stmt_execute($stmtConcIns);
            mysqli_stmt_close($stmtConcIns);
        }

        // Actualizar totales en cabecera sindi__ddjj
        $totalGeneral = round($totalGeneral, 2);
        $sqlUpd = "UPDATE sindi__ddjj SET total = ?, cantidad_empleados = ?, f_vto = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW() WHERE ddjj_id = ?";
        $stmtUpd = mysqli_prepare($conexion, $sqlUpd);
        mysqli_stmt_bind_param($stmtUpd, "disii", $totalGeneral, $cantEmpleados, $f_vto, $usuario_id, $ddjj_id);
        mysqli_stmt_execute($stmtUpd);
        mysqli_stmt_close($stmtUpd);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => 'Borrador de DDJJ guardado correctamente.',
            'ddjj_id' => $ddjj_id,
            'total' => $totalGeneral,
            'cantidad_empleados' => $cantEmpleados
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("[SINDI_DDJJ_CORE] Error al guardar borrador: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Obtiene la información completa de una DDJJ (cabecera, conceptos y nómina).
 */
function sindi_obtener_ddjj(mysqli $conexion, int $empresa_id, int $usuario_id, int $ddjj_id): ?array
{
    $sqlCab = "SELECT d.*, emp.rubro, ent.entidad_nombre AS empleador_nombre, ent.cuit AS empleador_cuit,
                      er.tabla_estado_registro AS estado_nombre, er.color_id AS estado_color_id,
                      u.usuario AS usuario_presenta
               FROM sindi__ddjj d
               INNER JOIN sindi__empleadores emp ON emp.empleador_id = d.empleador_id
               INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
               LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 521 AND er.estado_registro_id = d.tabla_estado_registro_id
               LEFT JOIN conf__usuarios u ON u.usuario_id = d.usuario_presenta_id
               WHERE d.ddjj_id = ? AND d.empresa_id = ? LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sqlCab);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $ddjj_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $cabecera = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$cabecera) return null;

    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, (int)$cabecera['empleador_id']);

    // Conceptos consolidados
    $sqlConc = "SELECT dc.*, c.codigo AS concepto_codigo, c.descripcion AS concepto_descripcion,
                       c.porcentaje, comp.comprobante_nro, comp.comprobante_pv
                FROM sindi__ddjj_conceptos dc
                INNER JOIN sindi__conceptos c ON c.concepto_id = dc.concepto_id
                LEFT JOIN gestion__comprobantes comp ON comp.comprobante_id = dc.comprobante_id
                WHERE dc.ddjj_id = ? AND dc.empresa_id = ?";
    $stmtC = mysqli_prepare($conexion, $sqlConc);
    mysqli_stmt_bind_param($stmtC, "ii", $ddjj_id, $empresa_id);
    mysqli_stmt_execute($stmtC);
    $resC = mysqli_stmt_get_result($stmtC);
    $conceptos = [];
    while ($r = mysqli_fetch_assoc($resC)) {
        $conceptos[] = $r;
    }
    mysqli_stmt_close($stmtC);
    $cabecera['conceptos'] = $conceptos;

    // Renglones de empleados con detalle
    $sqlEmp = "SELECT de.*, p.cuil, p.apellido, p.nombre, CONCAT(p.apellido, ' ', p.nombre) AS empleado_nombre,
                      c.codigo AS categoria_codigo, c.nombre AS categoria_nombre,
                      os.sigla AS obra_social_sigla, nov.descripcion AS novedad_descripcion,
                      bm.descripcion AS baja_descripcion,
                      suc.sucursal_numero, suc.sucursal_nombre
               FROM sindi__ddjj_empleados de
               INNER JOIN sindi__personas p ON p.persona_id = de.persona_id
               LEFT JOIN gestion__entidades_sucursales suc ON suc.sucursal_id = de.sucursal_id
               INNER JOIN sindi__categorias c ON c.categoria_id = de.categoria_id
               LEFT JOIN sindi__obras_sociales os ON os.obra_social_id = de.obra_social_id
               LEFT JOIN sindi__novedades nov ON nov.novedad_id = de.novedad_id
               LEFT JOIN sindi__bajas_motivos bm ON bm.baja_motivo_id = de.baja_motivo_id
               WHERE de.ddjj_id = ? AND de.empresa_id = ?
               ORDER BY p.apellido ASC, p.nombre ASC";
    $stmtE = mysqli_prepare($conexion, $sqlEmp);
    mysqli_stmt_bind_param($stmtE, "ii", $ddjj_id, $empresa_id);
    mysqli_stmt_execute($stmtE);
    $resE = mysqli_stmt_get_result($stmtE);
    $nomina = [];
    while ($re = mysqli_fetch_assoc($resE)) {
        $nomina[] = $re;
    }
    mysqli_stmt_close($stmtE);
    $cabecera['nomina'] = $nomina;

    return $cabecera;
}

/**
 * Lista declaraciones juradas con filtrado de acceso multi-empleador.
 */
function sindi_listar_ddjj(mysqli $conexion, int $empresa_id, int $usuario_id, int $pagina_id, ?int $empleador_id = null, ?int $periodo = null): array
{
    $filtroAcceso = AccesosEmpleadoresHelper::filtroSql($conexion, $usuario_id, $empresa_id, 'd.empleador_id');
    $whereExtra = "";
    $params = [$empresa_id];
    $types = "i";

    if ($empleador_id !== null && $empleador_id > 0) {
        $whereExtra .= " AND d.empleador_id = ? ";
        $params[] = $empleador_id;
        $types .= "i";
    }

    if ($periodo !== null && $periodo > 0) {
        $whereExtra .= " AND d.periodo = ? ";
        $params[] = $periodo;
        $types .= "i";
    }

    $sql = "SELECT d.*, ent.entidad_nombre AS empleador_nombre, ent.cuit AS empleador_cuit,
                   er.tabla_estado_registro AS estado_nombre, er.color_id AS estado_color_id
            FROM sindi__ddjj d
            INNER JOIN sindi__empleadores emp ON emp.empleador_id = d.empleador_id
            INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 521 AND er.estado_registro_id = d.tabla_estado_registro_id
            WHERE d.empresa_id = ? AND {$filtroAcceso} {$whereExtra}
            ORDER BY d.periodo DESC, d.secuencia DESC, d.ddjj_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['ddjj_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Confirma y presenta formalmente una DDJJ.
 * Genera comprobantes en gestion__comprobantes, asientos contables en gestion__cont_asientos,
 * revierte rectificadas anteriores si aplica, actualiza la matriz empleador×período y las afiliaciones.
 */
/**
 * Mantiene sindi__relaciones_laborales al presentar una DDJJ (dentro de su transacción):
 *  - Rectificativa: primero deshace lo que hizo la DDJJ rectificada (anula las altas de personas que ya no
 *    figuran y reabre las bajas que ella había cerrado); después aplica la nómina nueva.
 *  - Persona sin relación abierta con el empleador: alta desde el primer día del período.
 *  - Relación abierta: si el período es anterior a su alta, adelanta el alta; la sucursal se actualiza solo
 *    si no hay una DDJJ presentada posterior del empleador (una rectificativa de un período viejo no la pisa).
 *  - Renglón con baja (motivo o fecha): cierra la relación con esa fecha o el último día del período.
 *  - Quienes tienen relación abierta y no figuran en la DDJJ no se tocan: fiscalización los detecta como omitidos.
 */
function sindi_actualizar_relaciones_laborales(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    int $ddjj_id,
    int $empleador_id,
    int $periodo,
    array $nomina,
    ?int $rectificadaId
): void {
    $inicio = sprintf('%04d-%02d-01', intdiv($periodo, 100), $periodo % 100);
    $fin = date('Y-m-t', strtotime($inicio));
    $personasNomina = array_map(fn($e) => (int)$e['persona_id'], $nomina);

    if ($rectificadaId !== null) {
        // Altas hechas por la rectificada de personas que ya no figuran: se anulan
        $stmt = mysqli_prepare($conexion, "SELECT relacion_laboral_id, persona_id FROM sindi__relaciones_laborales
                                           WHERE empresa_id = ? AND empleador_id = ? AND ddjj_alta_id = ? AND tabla_estado_registro_id = 1 FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $empleador_id, $rectificadaId);
        mysqli_stmt_execute($stmt);
        $altas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        $anular = mysqli_prepare($conexion, "UPDATE sindi__relaciones_laborales SET tabla_estado_registro_id = 38, usuario_actualizacion_id = ?
                                             WHERE relacion_laboral_id = ?");
        foreach ($altas as $a) {
            if (!in_array((int)$a['persona_id'], $personasNomina, true)) {
                $rid = (int)$a['relacion_laboral_id'];
                mysqli_stmt_bind_param($anular, "ii", $usuario_id, $rid);
                mysqli_stmt_execute($anular);
            }
        }
        mysqli_stmt_close($anular);

        // Bajas cerradas por la rectificada: se reabren (la nómina nueva las vuelve a cerrar si corresponde)
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__relaciones_laborales
                                           SET f_baja = NULL, baja_motivo_id = NULL, ddjj_baja_id = NULL, usuario_actualizacion_id = ?
                                           WHERE empresa_id = ? AND empleador_id = ? AND ddjj_baja_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, "iiii", $usuario_id, $empresa_id, $empleador_id, $rectificadaId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // ¿Hay una DDJJ presentada posterior? Entonces este período no actualiza la sucursal vigente
    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) FROM sindi__ddjj WHERE empresa_id = ? AND empleador_id = ? AND periodo > ?
                                       AND tabla_estado_registro_id = 28");
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $empleador_id, $periodo);
    mysqli_stmt_execute($stmt);
    $hayPosterior = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] > 0;
    mysqli_stmt_close($stmt);

    $buscar = mysqli_prepare($conexion, "SELECT relacion_laboral_id, f_alta FROM sindi__relaciones_laborales
                                         WHERE empresa_id = ? AND empleador_id = ? AND persona_id = ? AND f_baja IS NULL
                                           AND tabla_estado_registro_id = 1
                                         ORDER BY relacion_laboral_id DESC LIMIT 1 FOR UPDATE");
    $alta = mysqli_prepare($conexion, "INSERT INTO sindi__relaciones_laborales
                                           (empresa_id, empleador_id, persona_id, sucursal_id, f_alta, ddjj_alta_id, tabla_estado_registro_id, usuario_creacion_id)
                                       VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
    $cerrar = mysqli_prepare($conexion, "UPDATE sindi__relaciones_laborales
                                         SET f_baja = ?, baja_motivo_id = ?, ddjj_baja_id = ?, usuario_actualizacion_id = ?
                                         WHERE relacion_laboral_id = ?");

    foreach ($nomina as $e) {
        $persona_id = (int)$e['persona_id'];
        $sucursal_id = !empty($e['sucursal_id']) ? (int)$e['sucursal_id'] : null;

        mysqli_stmt_bind_param($buscar, "iii", $empresa_id, $empleador_id, $persona_id);
        mysqli_stmt_execute($buscar);
        $rel = mysqli_fetch_assoc(mysqli_stmt_get_result($buscar));

        if (!$rel) {
            mysqli_stmt_bind_param($alta, "iiiisii", $empresa_id, $empleador_id, $persona_id, $sucursal_id, $inicio, $ddjj_id, $usuario_id);
            mysqli_stmt_execute($alta);
            $relacion_id = (int)mysqli_insert_id($conexion);
        } else {
            $relacion_id = (int)$rel['relacion_laboral_id'];
            $nuevoAlta = $rel['f_alta'] > $inicio ? $inicio : $rel['f_alta'];
            $sql = "UPDATE sindi__relaciones_laborales SET f_alta = ?, usuario_actualizacion_id = ?"
                 . (!$hayPosterior && $sucursal_id ? ", sucursal_id = ?" : "")
                 . " WHERE relacion_laboral_id = ?";
            $upd = mysqli_prepare($conexion, $sql);
            if (!$hayPosterior && $sucursal_id) {
                mysqli_stmt_bind_param($upd, "siii", $nuevoAlta, $usuario_id, $sucursal_id, $relacion_id);
            } else {
                mysqli_stmt_bind_param($upd, "sii", $nuevoAlta, $usuario_id, $relacion_id);
            }
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }

        $bajaMotivo = !empty($e['baja_motivo_id']) ? (int)$e['baja_motivo_id'] : null;
        $fBaja = !empty($e['f_baja']) ? (string)$e['f_baja'] : null;
        if ($bajaMotivo !== null || $fBaja !== null) {
            $fBaja = $fBaja ?? $fin;
            mysqli_stmt_bind_param($cerrar, "siiii", $fBaja, $bajaMotivo, $ddjj_id, $usuario_id, $relacion_id);
            mysqli_stmt_execute($cerrar);
        }
    }
    mysqli_stmt_close($buscar);
    mysqli_stmt_close($alta);
    mysqli_stmt_close($cerrar);
}

function sindi_confirmar_ddjj(mysqli $conexion, int $empresa_id, int $usuario_id, int $ddjj_id): array
{
    $ddjj = sindi_obtener_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id);
    if (!$ddjj) {
        return ['success' => false, 'message' => 'Declaración jurada no encontrada.'];
    }

    if ((int)$ddjj['tabla_estado_registro_id'] !== 3) {
        return ['success' => false, 'message' => 'Solo se pueden confirmar declaraciones juradas en estado Borrador.'];
    }

    $empleador_id = (int)$ddjj['empleador_id'];
    $periodo = (int)$ddjj['periodo'];
    $secuencia = (int)$ddjj['secuencia'];
    $rectificadaId = !empty($ddjj['ddjj_rectificada_id']) ? (int)$ddjj['ddjj_rectificada_id'] : null;

    // Obtener entidad_id del empleador
    $sqlEntId = "SELECT entidad_id FROM sindi__empleadores WHERE empleador_id = ? AND empresa_id = ? LIMIT 1";
    $stmtE = mysqli_prepare($conexion, $sqlEntId);
    mysqli_stmt_bind_param($stmtE, "ii", $empleador_id, $empresa_id);
    mysqli_stmt_execute($stmtE);
    $rowEntId = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtE));
    mysqli_stmt_close($stmtE);
    $entidad_id = (int)($rowEntId['entidad_id'] ?? 0);

    // Cuentas: errores explícitos si falta configuración (nunca ids fijos ni cuentas de otra empresa)
    try {
        $cuenta_deudores_id = sindi_cuenta_obligatoria($conexion, $empresa_id, 'SINDI_DEUDORES_APORTES');
    } catch (RuntimeException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

    $f_hoy = date('Y-m-d');

    mysqli_begin_transaction($conexion);
    try {
        // Bloquear la DDJJ y revalidar el estado dentro de la transacción (doble clic / dos usuarios)
        $stmtLk = mysqli_prepare($conexion, "SELECT tabla_estado_registro_id FROM sindi__ddjj WHERE ddjj_id = ? AND empresa_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmtLk, "ii", $ddjj_id, $empresa_id);
        mysqli_stmt_execute($stmtLk);
        $estadoActual = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmtLk))[0] ?? 0);
        mysqli_stmt_close($stmtLk);
        if ($estadoActual !== 3) {
            throw new RuntimeException('La declaración jurada ya no está en Borrador.');
        }

        // 1. Rectificativa: revertir la DDJJ anterior completa (§7.1). Por concepto, una reversión por el
        //    total, imputada contra el saldo pendiente de la anterior; lo que ya estaba pagado queda como
        //    saldo a favor en la reversión y se aplica a la nueva DDJJ del mismo concepto (paso 2).
        $creditosPorConcepto = []; // concepto_id => [comprobante_id de reversión con saldo a favor]
        if ($secuencia > 0 && $rectificadaId !== null) {
            $sqlAntConc = "SELECT dc.ddjj_concepto_id, dc.concepto_id, dc.importe_total, dc.comprobante_id, c.codigo AS conc_codigo
                           FROM sindi__ddjj_conceptos dc
                           INNER JOIN sindi__conceptos c ON c.concepto_id = dc.concepto_id
                           WHERE dc.ddjj_id = ? AND dc.empresa_id = ?";
            $stmtAC = mysqli_prepare($conexion, $sqlAntConc);
            mysqli_stmt_bind_param($stmtAC, "ii", $rectificadaId, $empresa_id);
            mysqli_stmt_execute($stmtAC);
            $resAC = mysqli_stmt_get_result($stmtAC);
            $conceptosAnt = [];
            while ($fila = mysqli_fetch_assoc($resAC)) {
                $conceptosAnt[] = $fila;
            }
            mysqli_stmt_close($stmtAC);

            foreach ($conceptosAnt as $cAnt) {
                $importeAnt = round((float)$cAnt['importe_total'], 2);
                $compOrigId = (int)($cAnt['comprobante_id'] ?? 0);
                if ($importeAnt <= 0 || $compOrigId <= 0) {
                    continue;
                }
                $conceptoId = (int)$cAnt['concepto_id'];

                // Origen de la reversión: el renglón de concepto de la DDJJ anterior con el id en negativo,
                // para no chocar con el comprobante de su deuda en el UNIQUE (tabla_origen_id, registro_origen_id).
                $rev = sindi_emitir_comprobante($conexion, [
                    'empresa_id' => $empresa_id,
                    'tabla_origen_id' => SINDI_TABLA_DDJJ_CONCEPTOS,
                    'registro_origen_id' => -1 * (int)$cAnt['ddjj_concepto_id'],
                    'comprobante_tipo_id' => sindi_tipo_comprobante_concepto($conexion, $empresa_id, $conceptoId, true),
                    'entidad_id' => $entidad_id,
                    'importe' => $importeAnt,
                    'signo' => -1,
                    'f_emision' => $f_hoy,
                    'usuario_id' => $usuario_id,
                    'observaciones' => "Reversión por rectificativa DDJJ {$periodo} sec. {$secuencia} ({$cAnt['conc_codigo']})",
                ]);
                $compRevId = $rev['comprobante_id'];

                tesoreriaGenerarAsiento($conexion, [
                    'empresa_id' => $empresa_id,
                    'comprobante_id' => $compRevId,
                    'entidad_id' => $entidad_id,
                    'f_asiento' => $f_hoy,
                    'descripcion' => "Reversión DDJJ período {$periodo} sec. " . ($secuencia - 1) . " {$cAnt['conc_codigo']}",
                    'usuario_id' => $usuario_id,
                    'cont_tipo_asiento_codigo' => 'AUT'
                ], [
                    ['cuenta_id' => sindi_cuenta_recurso_concepto($conexion, $empresa_id, $conceptoId), 'debe' => $importeAnt, 'haber' => 0.00, 'descripcion' => 'Reversión recurso sindical'],
                    ['cuenta_id' => $cuenta_deudores_id, 'debe' => 0.00, 'haber' => $importeAnt, 'descripcion' => 'Reversión deuda sindical'],
                ]);

                // Imputar la reversión contra lo que quede pendiente de la anterior
                $pendienteAnt = sindi_saldo_comprobante($conexion, $compOrigId, $empresa_id);
                if ($pendienteAnt > 0) {
                    tesoreriaRegistrarImputacion($conexion, [
                        'empresa_id' => $empresa_id,
                        'comp_origen_id' => $compRevId,
                        'comp_destino_id' => $compOrigId,
                        'importe_imputado' => min($pendienteAnt, $importeAnt),
                        'f_imputacion' => $f_hoy,
                        'usuario_id' => $usuario_id,
                    ]);
                }
                if ($importeAnt - $pendienteAnt > 0.004) {
                    $creditosPorConcepto[$conceptoId][] = $compRevId;
                }
            }

            // La anterior pasa a 2 (etiqueta propia "Rectificada")
            $stmtRe = mysqli_prepare($conexion, "UPDATE sindi__ddjj SET tabla_estado_registro_id = 2 WHERE ddjj_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmtRe, "ii", $rectificadaId, $empresa_id);
            mysqli_stmt_execute($stmtRe);
            mysqli_stmt_close($stmtRe);

            // Volantes vigentes con deudas de la anterior: se anulan (38) y el cron
            // sindicatos_publicar_deudas.php los despublica de los canales (no se llama a servicios
            // externos dentro de la transacción).
            $stmtVol = mysqli_prepare($conexion, "UPDATE sindi__volantes v
                SET v.tabla_estado_registro_id = 38,
                    v.observaciones = LEFT(CONCAT_WS(' | ', v.observaciones, 'Anulado por rectificativa de DDJJ'), 255)
                WHERE v.empresa_id = ? AND v.tabla_estado_registro_id IN (3, 31)
                  AND EXISTS (SELECT 1 FROM sindi__volantes_detalles vd
                              INNER JOIN sindi__ddjj_conceptos dc ON dc.comprobante_id = vd.comprobante_id
                              WHERE vd.volante_id = v.volante_id AND dc.ddjj_id = ?)");
            mysqli_stmt_bind_param($stmtVol, "ii", $empresa_id, $rectificadaId);
            mysqli_stmt_execute($stmtVol);
            $volantesAnulados = mysqli_stmt_affected_rows($stmtVol);
            mysqli_stmt_close($stmtVol);
            if ($volantesAnulados > 0) {
                error_log("=== sindi_confirmar_ddjj {$ddjj_id}: {$volantesAnulados} volante(s) anulado(s) por rectificativa ===");
            }
        }

        // 2. Comprobantes y asientos de la DDJJ actual, uno por concepto
        $comprobantesEmitidos = [];
        foreach ($ddjj['conceptos'] as $conc) {
            $importeConc = round((float)$conc['importe_total'], 2);
            if ($importeConc <= 0) continue;
            $conceptoId = (int)$conc['concepto_id'];
            $dcId = (int)$conc['ddjj_concepto_id'];

            $emi = sindi_emitir_comprobante($conexion, [
                'empresa_id' => $empresa_id,
                'tabla_origen_id' => SINDI_TABLA_DDJJ_CONCEPTOS,
                'registro_origen_id' => $dcId,
                'comprobante_tipo_id' => sindi_tipo_comprobante_concepto($conexion, $empresa_id, $conceptoId),
                'entidad_id' => $entidad_id,
                'importe' => $importeConc,
                'signo' => 1,
                'f_emision' => $f_hoy,
                'f_vto' => $ddjj['f_vto'],
                'usuario_id' => $usuario_id,
                'observaciones' => "DDJJ {$periodo} sec. {$secuencia} {$conc['concepto_codigo']}",
            ]);
            $comprobante_id = $emi['comprobante_id'];

            $stmtDc = mysqli_prepare($conexion, "UPDATE sindi__ddjj_conceptos SET comprobante_id = ? WHERE ddjj_concepto_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmtDc, "iii", $comprobante_id, $dcId, $empresa_id);
            mysqli_stmt_execute($stmtDc);
            mysqli_stmt_close($stmtDc);

            tesoreriaGenerarAsiento($conexion, [
                'empresa_id' => $empresa_id,
                'comprobante_id' => $comprobante_id,
                'entidad_id' => $entidad_id,
                'f_asiento' => $f_hoy,
                'descripcion' => "DDJJ período {$periodo} sec. {$secuencia} {$conc['concepto_codigo']}",
                'usuario_id' => $usuario_id,
                'cont_tipo_asiento_codigo' => 'AUT'
            ], [
                ['cuenta_id' => $cuenta_deudores_id, 'debe' => $importeConc, 'haber' => 0.00, 'descripcion' => 'Deuda aporte sindical'],
                ['cuenta_id' => sindi_cuenta_recurso_concepto($conexion, $empresa_id, $conceptoId), 'debe' => 0.00, 'haber' => $importeConc, 'descripcion' => 'Recurso aporte gremial'],
            ]);

            // Saldo a favor de la reversión del mismo concepto (pagos hechos sobre la DDJJ rectificada)
            foreach ($creditosPorConcepto[$conceptoId] ?? [] as $compCreditoId) {
                $aplicar = min(
                    sindi_saldo_comprobante($conexion, $compCreditoId, $empresa_id),
                    sindi_saldo_comprobante($conexion, $comprobante_id, $empresa_id)
                );
                if ($aplicar > 0) {
                    tesoreriaRegistrarImputacion($conexion, [
                        'empresa_id' => $empresa_id,
                        'comp_origen_id' => $compCreditoId,
                        'comp_destino_id' => $comprobante_id,
                        'importe_imputado' => $aplicar,
                        'f_imputacion' => $f_hoy,
                        'usuario_id' => $usuario_id,
                    ]);
                }
            }

            $comprobantesEmitidos[] = $comprobante_id;
        }

        // 3. Pasar DDJJ a estado 28 (Presentada)
        $sqlPres = "UPDATE sindi__ddjj SET tabla_estado_registro_id = 28, f_presentacion = NOW(), usuario_presenta_id = ? WHERE ddjj_id = ?";
        $stmtPres = mysqli_prepare($conexion, $sqlPres);
        mysqli_stmt_bind_param($stmtPres, "ii", $usuario_id, $ddjj_id);
        mysqli_stmt_execute($stmtPres);
        mysqli_stmt_close($stmtPres);

        // 4. Actualizar matriz de cumplimiento mensual en sindi__empleadores_periodos
        $sqlPer = "INSERT INTO sindi__empleadores_periodos (
            empresa_id, empleador_id, periodo, f_vto, ddjj_vigente_id, situacion, dias_atraso, fecha_actualizacion
        ) VALUES (
            ?, ?, ?, ?, ?, 'PRESENTADA', 0, NOW()
        ) ON DUPLICATE KEY UPDATE
            ddjj_vigente_id = VALUES(ddjj_vigente_id),
            situacion = VALUES(situacion),
            dias_atraso = 0,
            fecha_actualizacion = NOW()";
        $stmtPer = mysqli_prepare($conexion, $sqlPer);
        $f_vto = $ddjj['f_vto'];
        mysqli_stmt_bind_param($stmtPer, "iiisi", $empresa_id, $empleador_id, $periodo, $f_vto, $ddjj_id);
        mysqli_stmt_execute($stmtPer);
        mysqli_stmt_close($stmtPer);

        // 4 bis. Relaciones laborales del empleador según la nómina presentada (§5.2)
        sindi_actualizar_relaciones_laborales($conexion, $empresa_id, $usuario_id, $ddjj_id, $empleador_id, $periodo, $ddjj['nomina'], $rectificadaId);

        // 5. Afiliaciones de actividad declaradas en la nómina (§5.6): una por persona y empleador, con baja
        //    por desafiliación o baja laboral, y la rectificativa deshace lo que hizo la anterior
        sindi_afil_actividad_desde_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id, $empleador_id, $periodo, $ddjj['nomina'], $rectificadaId);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => 'Declaración jurada presentada y confirmada exitosamente.',
            'ddjj_id' => $ddjj_id,
            'codigo_verificacion' => $ddjj['codigo_verificacion'],
            'comprobantes' => $comprobantesEmitidos
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("[SINDI_DDJJ_CORE] Error al confirmar DDJJ: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al confirmar: ' . $e->getMessage()];
    }
}

/**
 * Inicia una rectificativa para una DDJJ presentada.
 */
function sindi_iniciar_rectificativa(mysqli $conexion, int $empresa_id, int $usuario_id, int $ddjj_id_confirmada): array
{
    $ddjjAnt = sindi_obtener_ddjj($conexion, $empresa_id, $usuario_id, $ddjj_id_confirmada);
    if (!$ddjjAnt) {
        return ['success' => false, 'message' => 'Declaración jurada original no encontrada.'];
    }

    if ((int)$ddjjAnt['tabla_estado_registro_id'] !== 28) {
        return ['success' => false, 'message' => 'Solo se pueden rectificar declaraciones juradas confirmadas/presentadas.'];
    }

    $empleador_id = (int)$ddjjAnt['empleador_id'];
    $periodo = (int)$ddjjAnt['periodo'];

    // Verificar si ya hay un borrador abierto para ese período
    $sqlChk = "SELECT ddjj_id FROM sindi__ddjj WHERE empresa_id = ? AND empleador_id = ? AND periodo = ? AND tabla_estado_registro_id = 3 LIMIT 1";
    $stmtC = mysqli_prepare($conexion, $sqlChk);
    mysqli_stmt_bind_param($stmtC, "iii", $empresa_id, $empleador_id, $periodo);
    mysqli_stmt_execute($stmtC);
    $rowC = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
    mysqli_stmt_close($stmtC);

    if ($rowC) {
        return [
            'success' => true,
            'message' => 'Ya existe un borrador de rectificativa en curso para este período.',
            'ddjj_id' => (int)$rowC['ddjj_id']
        ];
    }

    // Copiar la nómina de la DDJJ anterior para iniciar el nuevo borrador
    $nominaCopia = [];
    foreach ($ddjjAnt['nomina'] as $emp) {
        $nominaCopia[] = [
            'cuil' => $emp['cuil'],
            'nombre' => $emp['empleado_nombre'],
            'sucursal_id' => $emp['sucursal_id'] ?? null,
            'categoria_id' => $emp['categoria_id'],
            'rem_1' => $emp['rem_1'],
            'rem_2' => $emp['rem_2'],
            'rem_3' => $emp['rem_3'],
            'media_jornada' => $emp['media_jornada'],
            'jubilado' => $emp['jubilado'],
            'afiliado' => $emp['afiliado'],
            'obra_social_id' => $emp['obra_social_id'],
            'novedad_id' => $emp['novedad_id'],
            'baja_motivo_id' => $emp['baja_motivo_id'],
            'f_baja' => $emp['f_baja']
        ];
    }

    $resBorrador = sindi_guardar_borrador_ddjj(
        $conexion,
        $empresa_id,
        $usuario_id,
        [
            'empleador_id' => $empleador_id,
            'periodo' => $periodo,
            'ddjj_id' => 0,
            'tipo' => 'EMPLEADOR',
            'origen_ingreso' => 'MANUAL'
        ],
        $nominaCopia
    );

    return $resBorrador;
}
