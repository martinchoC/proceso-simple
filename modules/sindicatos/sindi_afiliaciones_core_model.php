<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_afiliaciones_core_model.php
 * Núcleo de afiliaciones y grupo familiar (Fase 5, especificación §5.6).
 *
 * - Tipos por código (ACTIVIDAD, VOLUNTARIA, JUBILADO, FAMILIAR), nunca por id.
 * - Número de afiliado de la persona: lo comparten todas sus afiliaciones titulares; los familiares
 *   llevan el número del titular con sufijo (000123/01). La secuencia se serializa bloqueando la
 *   vigencia abierta de sindi__parametros de la empresa.
 * - Familiares: vigencia = la menor entre la edad límite del parentesco (extendida con documento de
 *   excepción vigente) y la del titular. La baja del titular arrastra a sus familiares.
 * - Actividad: la mantiene la DDJJ presentada (una por persona y empleador; la rectificativa deshace
 *   lo que hizo la anterior).
 * - Cuota directa mensual (voluntarios y jubilados con importe): comprobante CAF a la entidad del
 *   afiliado, con su asiento.
 *
 * Las funciones que escriben se llaman dentro de una transacción (la del proceso que las usa o la que
 * abre cada operación de la página).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../gestion_normal/tesoreria_core_model.php';
require_once __DIR__ . '/sindi_base_model.php';
require_once __DIR__ . '/sindi_comprobantes_core.php';
require_once __DIR__ . '/sindi_importadores.php';

const SINDI_TABLA_AFILIACIONES_CUOTAS = 544;

/** Tipo de afiliación por código (fila completa). */
function sindi_afil_tipo(mysqli $conexion, int $empresa_id, string $codigo): array
{
    $stmt = mysqli_prepare($conexion, "SELECT * FROM sindi__afiliaciones_tipos WHERE empresa_id = ? AND codigo = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $codigo);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        throw new RuntimeException("No existe el tipo de afiliación {$codigo} en la empresa.");
    }
    return $fila;
}

function sindi_afil_obtener(mysqli $conexion, int $empresa_id, int $afiliacion_id, bool $bloquear = false): array
{
    $sql = "SELECT a.*, t.codigo AS tipo_codigo, t.grupo, t.paga_cuota_directa, t.importe_cuota
            FROM sindi__afiliaciones a
            INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
            WHERE a.afiliacion_id = ? AND a.empresa_id = ? LIMIT 1" . ($bloquear ? " FOR UPDATE" : "");
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $afiliacion_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        throw new RuntimeException('Afiliación no encontrada en esta empresa.');
    }
    return $fila;
}

/**
 * Persona por CUIL: la existente o una nueva. Completa fecha de nacimiento, sexo y contacto si estaban vacíos.
 */
function sindi_afil_persona(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): int
{
    $cuil = preg_replace('/\D/', '', (string)($d['cuil'] ?? ''));
    if (!sindi_validar_cuil_arg($cuil)) {
        throw new RuntimeException("CUIL inválido '{$cuil}': revise el número y el dígito verificador.");
    }
    $apellido = mb_strtoupper(trim((string)($d['apellido'] ?? '')));
    $nombre = mb_strtoupper(trim((string)($d['nombre'] ?? '')));
    $f_nac = !empty($d['f_nacimiento']) ? (string)$d['f_nacimiento'] : null;
    $sexo = in_array($d['sexo'] ?? '', ['M', 'F', 'X'], true) ? $d['sexo'] : null;
    $email = trim((string)($d['email'] ?? '')) ?: null;
    $telefono = trim((string)($d['telefono'] ?? '')) ?: null;

    $stmt = mysqli_prepare($conexion, "SELECT persona_id FROM sindi__personas WHERE empresa_id = ? AND cuil = ? LIMIT 1 FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $cuil);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($fila) {
        $persona_id = (int)$fila[0];
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__personas
            SET f_nacimiento = COALESCE(f_nacimiento, ?), sexo = COALESCE(?, sexo),
                email = COALESCE(email, ?), telefono = COALESCE(telefono, ?), usuario_actualizacion_id = ?
            WHERE persona_id = ?");
        mysqli_stmt_bind_param($stmt, "ssssii", $f_nac, $sexo, $email, $telefono, $usuario_id, $persona_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $persona_id;
    }

    if ($apellido === '') {
        throw new RuntimeException('Apellido obligatorio para dar de alta a la persona.');
    }
    $sexo = $sexo ?? 'M';
    $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__personas
            (empresa_id, cuil, apellido, nombre, sexo, f_nacimiento, email, telefono, tabla_estado_registro_id, usuario_creacion_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
    mysqli_stmt_bind_param($stmt, "isssssssi", $empresa_id, $cuil, $apellido, $nombre, $sexo, $f_nac, $email, $telefono, $usuario_id);
    mysqli_stmt_execute($stmt);
    $persona_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $persona_id;
}

/** Número de afiliado de la persona: el que ya tenga en alguna afiliación titular o el siguiente de la empresa. */
function sindi_afil_numero_titular(mysqli $conexion, int $empresa_id, int $persona_id): string
{
    $stmt = mysqli_prepare($conexion, "SELECT a.numero_afiliado FROM sindi__afiliaciones a
                                       WHERE a.empresa_id = ? AND a.persona_id = ? AND a.titular_afiliacion_id IS NULL
                                         AND a.numero_afiliado IS NOT NULL
                                       ORDER BY a.afiliacion_id LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $persona_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($fila) {
        return (string)$fila[0];
    }

    // Serializa la secuencia de la empresa hasta el commit
    $stmt = mysqli_prepare($conexion, "SELECT parametro_id FROM sindi__parametros WHERE empresa_id = ? AND f_hasta IS NULL LIMIT 1 FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion, "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_afiliado, '/', 1) AS UNSIGNED)), 0)
                                       FROM sindi__afiliaciones WHERE empresa_id = ? AND numero_afiliado IS NOT NULL");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $max = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);
    return str_pad((string)($max + 1), 6, '0', STR_PAD_LEFT);
}

/** Vigencia (fecha hasta, inclusive) de un familiar; null = sin límite. Lanza si falta la fecha de nacimiento. */
function sindi_afil_vigencia_familiar(
    mysqli $conexion,
    int $empresa_id,
    int $parentesco_id,
    ?string $f_nacimiento,
    ?string $documento_vto,
    ?string $vigencia_titular
): ?string {
    $stmt = mysqli_prepare($conexion, "SELECT edad_maxima, edad_maxima_excepcion FROM sindi__afiliaciones_vigencias
                                       WHERE empresa_id = ? AND parentesco_id = ? AND tabla_estado_registro_id = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $parentesco_id);
    mysqli_stmt_execute($stmt);
    $regla = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $vigencia = null;
    if ($regla && $regla['edad_maxima'] !== null) {
        if (!$f_nacimiento) {
            throw new RuntimeException('La fecha de nacimiento es obligatoria para este parentesco (tiene límite de edad).');
        }
        $limite = date('Y-m-d', strtotime($f_nacimiento . ' +' . (int)$regla['edad_maxima'] . ' years -1 day'));
        $vigencia = $limite;
        if ($documento_vto && $regla['edad_maxima_excepcion'] !== null) {
            $limiteExc = date('Y-m-d', strtotime($f_nacimiento . ' +' . (int)$regla['edad_maxima_excepcion'] . ' years -1 day'));
            $vigencia = max($limite, min($limiteExc, $documento_vto));
        }
    }
    if ($vigencia_titular !== null) {
        $vigencia = $vigencia === null ? $vigencia_titular : min($vigencia, $vigencia_titular);
    }
    return $vigencia;
}

/** Último documento de excepción vigente de una afiliación (fecha de vencimiento) o null. */
function sindi_afil_documento_vigente(mysqli $conexion, int $afiliacion_id): ?string
{
    $stmt = mysqli_prepare($conexion, "SELECT MAX(f_vencimiento) FROM sindi__afiliaciones_documentos
                                       WHERE afiliacion_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, "i", $afiliacion_id);
    mysqli_stmt_execute($stmt);
    $vto = mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? null;
    mysqli_stmt_close($stmt);
    return $vto ? (string)$vto : null;
}

/**
 * Entidad (cuenta corriente) de la persona para la cuota directa: la existente por CUIL o una nueva con rol AFIL.
 */
function sindi_afil_entidad_persona(mysqli $conexion, int $empresa_id, int $persona_id): int
{
    $stmt = mysqli_prepare($conexion, "SELECT cuil, apellido, nombre, entidad_id FROM sindi__personas WHERE persona_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $persona_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$p) {
        throw new RuntimeException('Persona no encontrada.');
    }
    $entidad_id = (int)($p['entidad_id'] ?? 0);
    if ($entidad_id <= 0) {
        $stmt = mysqli_prepare($conexion, "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $empresa_id, $p['cuil']);
        mysqli_stmt_execute($stmt);
        $entidad_id = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
        mysqli_stmt_close($stmt);
    }
    if ($entidad_id <= 0) {
        $nombre = trim($p['apellido'] . ' ' . $p['nombre']);
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades (empresa_id, cuit, entidad_nombre, tabla_estado_registro_id, fecha_alta)
                                           VALUES (?, ?, ?, 1, NOW())");
        mysqli_stmt_bind_param($stmt, "iss", $empresa_id, $p['cuil'], $nombre);
        mysqli_stmt_execute($stmt);
        $entidad_id = (int)mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
    }
    sindi_asegurar_rol_entidad($conexion, $entidad_id, 'AFIL');
    $stmt = mysqli_prepare($conexion, "UPDATE sindi__personas SET entidad_id = ? WHERE persona_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $persona_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $entidad_id;
}

/**
 * Alta de una afiliación titular (ACTIVIDAD, VOLUNTARIA, JUBILADO...). Devuelve el afiliacion_id.
 * No duplica: si la persona ya tiene una del mismo tipo vigente (y del mismo empleador, en ACTIVIDAD), la devuelve.
 */
function sindi_afil_alta_titular(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    int $persona_id,
    string $tipo_codigo,
    ?int $empleador_id,
    string $f_alta,
    string $origen,
    ?int $origen_id
): int {
    $tipo = sindi_afil_tipo($conexion, $empresa_id, $tipo_codigo);
    if ($tipo['grupo'] !== 'TITULAR') {
        throw new RuntimeException("El tipo {$tipo_codigo} no es de titulares.");
    }
    if ((int)$tipo['tabla_estado_registro_id'] !== 1) {
        throw new RuntimeException("El tipo de afiliación {$tipo['nombre']} está inhabilitado.");
    }
    if ((int)$tipo['requiere_empleador'] === 1 && !$empleador_id) {
        throw new RuntimeException("La afiliación {$tipo['nombre']} requiere empleador.");
    }
    if ((int)$tipo['requiere_empleador'] !== 1) {
        $empleador_id = null;
    }
    $tipo_id = (int)$tipo['afiliacion_tipo_id'];

    $stmt = mysqli_prepare($conexion, "SELECT afiliacion_id FROM sindi__afiliaciones
                                       WHERE empresa_id = ? AND persona_id = ? AND afiliacion_tipo_id = ? AND empleador_id <=> ?
                                         AND tabla_estado_registro_id IN (1, 20) LIMIT 1 FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "iiii", $empresa_id, $persona_id, $tipo_id, $empleador_id);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($existe) {
        return (int)$existe[0];
    }

    $numero = sindi_afil_numero_titular($conexion, $empresa_id, $persona_id);
    $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__afiliaciones
            (empresa_id, persona_id, afiliacion_tipo_id, empleador_id, f_alta, origen, origen_id, numero_afiliado, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt, "iiiissis", $empresa_id, $persona_id, $tipo_id, $empleador_id, $f_alta, $origen, $origen_id, $numero);
    mysqli_stmt_execute($stmt);
    $afiliacion_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ((int)$tipo['paga_cuota_directa'] === 1) {
        sindi_afil_entidad_persona($conexion, $empresa_id, $persona_id);
    }
    error_log("=== sindi_afil_alta_titular: persona {$persona_id} tipo {$tipo_codigo} -> afiliación {$afiliacion_id} N° {$numero} ===");
    return $afiliacion_id;
}

/**
 * Alta de un familiar a cargo de una afiliación titular vigente. Controla parentesco, edad y vigencia.
 * $documento: ['documento' => texto, 'f_presentacion' => fecha] opcional, para la excepción de edad.
 */
function sindi_afil_alta_familiar(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    int $titular_afiliacion_id,
    int $persona_id,
    int $parentesco_id,
    string $f_alta,
    string $origen,
    ?int $origen_id,
    ?array $documento = null
): int {
    $titular = sindi_afil_obtener($conexion, $empresa_id, $titular_afiliacion_id, true);
    if ($titular['grupo'] !== 'TITULAR' || (int)$titular['tabla_estado_registro_id'] !== 1) {
        throw new RuntimeException('El titular no tiene una afiliación titular vigente.');
    }
    if ((int)$titular['persona_id'] === $persona_id) {
        throw new RuntimeException('El familiar no puede ser el mismo titular.');
    }
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM sindi__parentescos WHERE parentesco_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, "ii", $parentesco_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $okPar = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$okPar) {
        throw new RuntimeException('Parentesco no válido.');
    }

    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM sindi__afiliaciones WHERE empresa_id = ? AND titular_afiliacion_id = ? AND persona_id = ?
                                       AND tabla_estado_registro_id IN (1, 20)");
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $titular_afiliacion_id, $persona_id);
    mysqli_stmt_execute($stmt);
    $repetido = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($repetido) {
        throw new RuntimeException('Esa persona ya figura en el grupo familiar del titular.');
    }

    $tipo = sindi_afil_tipo($conexion, $empresa_id, 'FAMILIAR');
    $stmt = mysqli_prepare($conexion, "SELECT f_nacimiento FROM sindi__personas WHERE persona_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $persona_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $f_nac = mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? null;
    mysqli_stmt_close($stmt);

    $docVto = null;
    $meses = 12;
    if ($documento && !empty($documento['documento'])) {
        $stmt = mysqli_prepare($conexion, "SELECT meses_validez_documento FROM sindi__afiliaciones_vigencias WHERE empresa_id = ? AND parentesco_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $parentesco_id);
        mysqli_stmt_execute($stmt);
        $meses = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 12);
        mysqli_stmt_close($stmt);
        $docVto = date('Y-m-d', strtotime(($documento['f_presentacion'] ?? $f_alta) . " +{$meses} months -1 day"));
    }

    $vigencia = sindi_afil_vigencia_familiar($conexion, $empresa_id, $parentesco_id, $f_nac ? (string)$f_nac : null, $docVto,
        $titular['f_vigencia'] ? (string)$titular['f_vigencia'] : null);
    if ($vigencia !== null && $vigencia < $f_alta) {
        throw new RuntimeException('El familiar supera la edad de cobertura para ese parentesco' .
            ($docVto ? '.' : ': presente el documento de excepción si corresponde.'));
    }

    // Número: el del titular con sufijo correlativo
    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) FROM sindi__afiliaciones WHERE empresa_id = ? AND titular_afiliacion_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $titular_afiliacion_id);
    mysqli_stmt_execute($stmt);
    $orden = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] + 1;
    mysqli_stmt_close($stmt);
    $numero = $titular['numero_afiliado'] . '/' . str_pad((string)$orden, 2, '0', STR_PAD_LEFT);

    $tipo_id = (int)$tipo['afiliacion_tipo_id'];
    $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__afiliaciones
            (empresa_id, persona_id, afiliacion_tipo_id, titular_afiliacion_id, parentesco_id, f_alta, f_vigencia,
             origen, origen_id, numero_afiliado, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt, "iiiiisssis", $empresa_id, $persona_id, $tipo_id, $titular_afiliacion_id, $parentesco_id,
        $f_alta, $vigencia, $origen, $origen_id, $numero);
    mysqli_stmt_execute($stmt);
    $afiliacion_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($docVto) {
        $fPres = (string)($documento['f_presentacion'] ?? $f_alta);
        $nombreDoc = mb_substr((string)$documento['documento'], 0, 100);
        $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__afiliaciones_documentos
                (empresa_id, afiliacion_id, documento, f_presentacion, f_vencimiento, tabla_estado_registro_id, usuario_creacion_id)
            VALUES (?, ?, ?, ?, ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, "iisssi", $empresa_id, $afiliacion_id, $nombreDoc, $fPres, $docVto, $usuario_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    return $afiliacion_id;
}

/** Baja de una afiliación. Si es titular, arrastra a su grupo familiar con el mismo motivo y fecha. */
function sindi_afil_baja(mysqli $conexion, int $empresa_id, int $usuario_id, int $afiliacion_id, ?int $baja_motivo_id, string $f_baja, ?int $ddjj_baja_id = null): void
{
    $a = sindi_afil_obtener($conexion, $empresa_id, $afiliacion_id, true);
    if (!in_array((int)$a['tabla_estado_registro_id'], [1, 20], true)) {
        throw new RuntimeException('La afiliación no está vigente.');
    }
    $stmt = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones
                                       SET tabla_estado_registro_id = 2, f_baja = ?, baja_motivo_id = ?, ddjj_baja_id = ?
                                       WHERE afiliacion_id = ? OR (titular_afiliacion_id = ? AND tabla_estado_registro_id IN (1, 20))");
    mysqli_stmt_bind_param($stmt, "siiii", $f_baja, $baja_motivo_id, $ddjj_baja_id, $afiliacion_id, $afiliacion_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Presenta un documento de excepción de un familiar: recalcula la vigencia y, si estaba vencida por edad y
 * vuelve a estar cubierto, la reactiva.
 */
function sindi_afil_presentar_documento(mysqli $conexion, int $empresa_id, int $usuario_id, int $afiliacion_id, string $documento, string $f_presentacion, ?string $archivo_nombre = null, ?string $archivo_ruta = null): array
{
    $a = sindi_afil_obtener($conexion, $empresa_id, $afiliacion_id, true);
    if ($a['grupo'] !== 'FAMILIAR' || empty($a['parentesco_id'])) {
        throw new RuntimeException('Los documentos de excepción son de familiares.');
    }
    $parentesco_id = (int)$a['parentesco_id'];
    $stmt = mysqli_prepare($conexion, "SELECT edad_maxima_excepcion, meses_validez_documento FROM sindi__afiliaciones_vigencias
                                       WHERE empresa_id = ? AND parentesco_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $parentesco_id);
    mysqli_stmt_execute($stmt);
    $regla = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$regla || $regla['edad_maxima_excepcion'] === null) {
        throw new RuntimeException('Ese parentesco no tiene excepción de edad por documento.');
    }
    $vto = date('Y-m-d', strtotime($f_presentacion . ' +' . (int)$regla['meses_validez_documento'] . ' months -1 day'));
    $doc = mb_substr($documento, 0, 100);
    $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__afiliaciones_documentos
            (empresa_id, afiliacion_id, documento, archivo_nombre, archivo_ruta, f_presentacion, f_vencimiento, tabla_estado_registro_id, usuario_creacion_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
    mysqli_stmt_bind_param($stmt, "iisssssi", $empresa_id, $afiliacion_id, $doc, $archivo_nombre, $archivo_ruta, $f_presentacion, $vto, $usuario_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $titular = sindi_afil_obtener($conexion, $empresa_id, (int)$a['titular_afiliacion_id']);
    $stmt = mysqli_prepare($conexion, "SELECT f_nacimiento FROM sindi__personas WHERE persona_id = ?");
    $pid = (int)$a['persona_id'];
    mysqli_stmt_bind_param($stmt, "i", $pid);
    mysqli_stmt_execute($stmt);
    $f_nac = mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? null;
    mysqli_stmt_close($stmt);

    $vigencia = sindi_afil_vigencia_familiar($conexion, $empresa_id, $parentesco_id, $f_nac ? (string)$f_nac : null,
        sindi_afil_documento_vigente($conexion, $afiliacion_id), $titular['f_vigencia'] ? (string)$titular['f_vigencia'] : null);
    $hoy = date('Y-m-d');
    $titularVigente = (int)$titular['tabla_estado_registro_id'] === 1;
    $estado = (int)$a['tabla_estado_registro_id'];
    if ($estado === 20 && $titularVigente && ($vigencia === null || $vigencia >= $hoy)) {
        $estado = 1;
    }
    $stmt = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones SET f_vigencia = ?, tabla_estado_registro_id = ? WHERE afiliacion_id = ?");
    mysqli_stmt_bind_param($stmt, "sii", $vigencia, $estado, $afiliacion_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return ['f_vigencia' => $vigencia, 'f_vencimiento_documento' => $vto, 'estado' => $estado];
}

/**
 * Vencimientos (cron diario): pasa a 20 Vencida las afiliaciones vigentes con f_vigencia anterior a $fecha y
 * devuelve las que vencen en los próximos $dias_aviso días (para avisar a la familia).
 */
function sindi_afil_procesar_vencimientos(mysqli $conexion, int $empresa_id, string $fecha, int $dias_aviso = 30): array
{
    $stmt = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones SET tabla_estado_registro_id = 20
                                       WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND f_vigencia IS NOT NULL AND f_vigencia < ?");
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $fecha);
    mysqli_stmt_execute($stmt);
    $vencidas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    $hasta = date('Y-m-d', strtotime("{$fecha} +{$dias_aviso} days"));
    $stmt = mysqli_prepare($conexion, "SELECT a.afiliacion_id, a.numero_afiliado, a.f_vigencia, p.cuil, p.apellido, p.nombre,
                                              tp.apellido AS titular_apellido, tp.nombre AS titular_nombre, tp.email AS titular_email
                                       FROM sindi__afiliaciones a
                                       INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
                                       LEFT JOIN sindi__afiliaciones t ON t.afiliacion_id = a.titular_afiliacion_id
                                       LEFT JOIN sindi__personas tp ON tp.persona_id = t.persona_id
                                       WHERE a.empresa_id = ? AND a.tabla_estado_registro_id = 1 AND a.f_vigencia BETWEEN ? AND ?
                                       ORDER BY a.f_vigencia");
    mysqli_stmt_bind_param($stmt, "iss", $empresa_id, $fecha, $hasta);
    mysqli_stmt_execute($stmt);
    $avisos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return ['vencidas' => $vencidas, 'avisos' => $avisos];
}

/**
 * Afiliaciones de ACTIVIDAD según la DDJJ presentada (dentro de su transacción):
 *  - Rectificativa: anula (38) las altas que hizo la rectificada de personas que ya no declara afiliadas y
 *    reabre las bajas que ella dio.
 *  - Renglón afiliado sin afiliación vigente con ese empleador: alta desde el primer día del período.
 *  - Renglón no afiliado con afiliación vigente: baja el día anterior al período (desafiliación declarada).
 *  - Renglón con baja laboral: baja con la fecha y el motivo del renglón.
 */
function sindi_afil_actividad_desde_ddjj(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    int $ddjj_id,
    int $empleador_id,
    int $periodo,
    array $nomina,
    ?int $rectificadaId
): void {
    $tipo = sindi_afil_tipo($conexion, $empresa_id, 'ACTIVIDAD');
    $tipo_id = (int)$tipo['afiliacion_tipo_id'];
    $inicio = sprintf('%04d-%02d-01', intdiv($periodo, 100), $periodo % 100);
    $fin = date('Y-m-t', strtotime($inicio));
    $diaAnterior = date('Y-m-d', strtotime($inicio . ' -1 day'));
    $afiliadosNomina = [];
    foreach ($nomina as $e) {
        if (!empty($e['afiliado'])) {
            $afiliadosNomina[(int)$e['persona_id']] = true;
        }
    }

    if ($rectificadaId !== null) {
        $stmt = mysqli_prepare($conexion, "SELECT afiliacion_id, persona_id FROM sindi__afiliaciones
                                           WHERE empresa_id = ? AND afiliacion_tipo_id = ? AND empleador_id = ? AND origen = 'DDJJ'
                                             AND origen_id = ? AND tabla_estado_registro_id IN (1, 20) FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "iiii", $empresa_id, $tipo_id, $empleador_id, $rectificadaId);
        mysqli_stmt_execute($stmt);
        $altas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        foreach ($altas as $alta) {
            if (!isset($afiliadosNomina[(int)$alta['persona_id']])) {
                $aid = (int)$alta['afiliacion_id'];
                $stmt = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones SET tabla_estado_registro_id = 38
                                                   WHERE afiliacion_id = ? OR (titular_afiliacion_id = ? AND tabla_estado_registro_id IN (1, 20))");
                mysqli_stmt_bind_param($stmt, "ii", $aid, $aid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones
                                           SET tabla_estado_registro_id = 1, f_baja = NULL, baja_motivo_id = NULL, ddjj_baja_id = NULL
                                           WHERE empresa_id = ? AND afiliacion_tipo_id = ? AND empleador_id = ? AND ddjj_baja_id = ?
                                             AND tabla_estado_registro_id = 2");
        mysqli_stmt_bind_param($stmt, "iiii", $empresa_id, $tipo_id, $empleador_id, $rectificadaId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $buscar = mysqli_prepare($conexion, "SELECT afiliacion_id FROM sindi__afiliaciones
                                         WHERE empresa_id = ? AND persona_id = ? AND afiliacion_tipo_id = ? AND empleador_id = ?
                                           AND tabla_estado_registro_id IN (1, 20) LIMIT 1 FOR UPDATE");
    foreach ($nomina as $e) {
        $persona_id = (int)$e['persona_id'];
        mysqli_stmt_bind_param($buscar, "iiii", $empresa_id, $persona_id, $tipo_id, $empleador_id);
        mysqli_stmt_execute($buscar);
        $vigente = mysqli_fetch_row(mysqli_stmt_get_result($buscar));
        $vigente_id = $vigente ? (int)$vigente[0] : 0;

        $bajaMotivo = !empty($e['baja_motivo_id']) ? (int)$e['baja_motivo_id'] : null;
        $fBaja = !empty($e['f_baja']) ? (string)$e['f_baja'] : null;
        $esBajaLaboral = $bajaMotivo !== null || $fBaja !== null;

        if (!empty($e['afiliado']) && $vigente_id === 0) {
            $vigente_id = sindi_afil_alta_titular($conexion, $empresa_id, $usuario_id, $persona_id, 'ACTIVIDAD', $empleador_id, $inicio, 'DDJJ', $ddjj_id);
        }
        if ($vigente_id > 0 && $esBajaLaboral) {
            sindi_afil_baja($conexion, $empresa_id, $usuario_id, $vigente_id, $bajaMotivo, $fBaja ?? $fin, $ddjj_id);
        } elseif ($vigente_id > 0 && empty($e['afiliado'])) {
            sindi_afil_baja($conexion, $empresa_id, $usuario_id, $vigente_id, null, $diaAnterior, $ddjj_id);
        }
    }
    mysqli_stmt_close($buscar);
}

/**
 * Cuotas directas del período (cron mensual o a pedido): un comprobante CAF por afiliación titular vigente cuyo
 * tipo paga cuota directa con importe. Idempotente por (afiliación, período).
 */
function sindi_afil_generar_cuotas(mysqli $conexion, int $empresa_id, int $usuario_id, int $periodo): array
{
    $tipo_caf = emp_comprobante_tipo_id($conexion, $empresa_id, 'CAF');
    if ($tipo_caf <= 0) {
        throw new RuntimeException('Falta el tipo de comprobante CAF (cuota de afiliado) de la empresa.');
    }
    $cuentaDeudores = sindi_cuenta_obligatoria($conexion, $empresa_id, 'SINDI_DEUDORES_AFILIADOS');
    $cuentaRecurso = sindi_cuenta_obligatoria($conexion, $empresa_id, 'SINDI_CUOTAS_AFILIADOS');
    $inicio = sprintf('%04d-%02d-01', intdiv($periodo, 100), $periodo % 100);
    $f_vto = date('Y-m-15', strtotime($inicio . ' +1 month'));

    $stmt = mysqli_prepare($conexion, "SELECT a.afiliacion_id, a.persona_id, a.numero_afiliado, t.importe_cuota, t.nombre AS tipo_nombre
                                       FROM sindi__afiliaciones a
                                       INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
                                       WHERE a.empresa_id = ? AND a.tabla_estado_registro_id = 1 AND a.titular_afiliacion_id IS NULL
                                         AND t.paga_cuota_directa = 1 AND t.importe_cuota > 0 AND a.f_alta <= LAST_DAY(?)
                                         AND NOT EXISTS (SELECT 1 FROM sindi__afiliaciones_cuotas c WHERE c.afiliacion_id = a.afiliacion_id AND c.periodo = ?)");
    mysqli_stmt_bind_param($stmt, "isi", $empresa_id, $inicio, $periodo);
    mysqli_stmt_execute($stmt);
    $pendientes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $generadas = 0;
    $errores = [];
    foreach ($pendientes as $p) {
        mysqli_begin_transaction($conexion);
        try {
            $importe = round((float)$p['importe_cuota'], 2);
            $afiliacion_id = (int)$p['afiliacion_id'];
            $entidad_id = sindi_afil_entidad_persona($conexion, $empresa_id, (int)$p['persona_id']);

            $stmtC = mysqli_prepare($conexion, "INSERT INTO sindi__afiliaciones_cuotas (empresa_id, afiliacion_id, periodo, importe) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmtC, "iiid", $empresa_id, $afiliacion_id, $periodo, $importe);
            mysqli_stmt_execute($stmtC);
            $cuota_id = (int)mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtC);

            $emi = sindi_emitir_comprobante($conexion, [
                'empresa_id' => $empresa_id,
                'tabla_origen_id' => SINDI_TABLA_AFILIACIONES_CUOTAS,
                'registro_origen_id' => $cuota_id,
                'comprobante_tipo_id' => $tipo_caf,
                'entidad_id' => $entidad_id,
                'importe' => $importe,
                'signo' => 1,
                'f_emision' => $inicio,
                'f_vto' => $f_vto,
                'usuario_id' => $usuario_id,
                'observaciones' => "Cuota {$p['tipo_nombre']} {$periodo} - afiliado {$p['numero_afiliado']}",
            ]);
            tesoreriaGenerarAsiento($conexion, [
                'empresa_id' => $empresa_id,
                'comprobante_id' => $emi['comprobante_id'],
                'entidad_id' => $entidad_id,
                'f_asiento' => $inicio,
                'descripcion' => "Cuota de afiliado {$p['numero_afiliado']} período {$periodo}",
                'usuario_id' => $usuario_id,
                'cont_tipo_asiento_codigo' => 'AUT'
            ], [
                ['cuenta_id' => $cuentaDeudores, 'debe' => $importe, 'haber' => 0.00, 'descripcion' => 'Cuota de afiliado a cobrar'],
                ['cuenta_id' => $cuentaRecurso, 'debe' => 0.00, 'haber' => $importe, 'descripcion' => 'Cuotas de afiliados'],
            ]);
            $stmtU = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones_cuotas SET comprobante_id = ? WHERE afiliacion_cuota_id = ?");
            mysqli_stmt_bind_param($stmtU, "ii", $emi['comprobante_id'], $cuota_id);
            mysqli_stmt_execute($stmtU);
            mysqli_stmt_close($stmtU);

            mysqli_commit($conexion);
            $generadas++;
        } catch (Throwable $t) {
            mysqli_rollback($conexion);
            $errores[] = "Afiliado {$p['numero_afiliado']}: " . $t->getMessage();
            error_log("[SINDI_AFIL] Error cuota {$periodo} afiliación {$p['afiliacion_id']}: " . $t->getMessage());
        }
    }
    return ['generadas' => $generadas, 'errores' => $errores];
}
