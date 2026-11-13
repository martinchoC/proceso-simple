<?php
/**
 * Consulta de padrón de ARCA por CUIT, interpretada para el ERP: denominación, tipo de persona, domicilio fiscal con la
 * provincia y la localidad de conf__provincias / conf__localidades, condición frente al IVA de gestion__condiciones_fiscales
 * y si el CUIT ya es una entidad de la empresa. La llamada SOAP está en arca/arca_padron.php.
 * La usan arca_padron_ajax.php de gestion / gestion_normal (botón "Buscar en ARCA" de Clientes, Proveedores y Entidades,
 * assets/js/arca-padron.js) y la herramienta consultar_padron_arca del Asistente IA.
 * Script: _local/sql/mantenimiento/20261104_arca_padron_01.sql.
 */

require_once __DIR__ . '/../arca/arca_padron.php';
require_once __DIR__ . '/entidades_roles.php';

// Impuestos del padrón que definen la condición frente al IVA (idImpuesto de ARCA)
const ARCA_IMP_MONOTRIBUTO = 20;
const ARCA_IMP_IVA = 30;
const ARCA_IMP_IVA_EXENTO = 32;
const ARCA_IMP_IVA_NO_ALCANZADO = 34;

/** ¿La empresa tiene configurada la consulta de padrón? */
function arca_padron_disponible(mysqli $c, int $empresa_id): bool
{
    return arca_padron_configuracion($c, $empresa_id) !== null;
}

/**
 * Consulta el CUIT y devuelve los datos listos para un alta. Lanza RuntimeException con un mensaje para el usuario
 * (sin configuración, CUIT inválido, inexistente en ARCA o error del servicio).
 */
function arca_padron_consultar_erp(mysqli $c, int $empresa_id, string $cuit): array
{
    $cuit = preg_replace('/\D/', '', $cuit);
    if (!arca_padron_cuit_valido($cuit)) {
        throw new RuntimeException("El CUIT $cuit no es válido (11 dígitos con dígito verificador).");
    }
    $cfg = arca_padron_configuracion($c, $empresa_id);
    if (!$cfg) {
        throw new RuntimeException('La empresa no tiene configurada la consulta de padrón de ARCA (Integraciones ARCA, servicio '
            . ARCA_PADRON_SERVICIO . ').');
    }
    $r = arca_padron_get_persona($c, $cfg, $cuit);
    if (!$r['ok']) {
        $error = (string)($r['error'] ?? '');
        if (stripos($error, 'no autorizado') !== false) {
            $error = 'ARCA no autorizó el certificado para la consulta de padrón: en ARCA hay que asociar el certificado de la '
                . 'integración (' . $cfg['ambiente_nombre'] . ') al servicio ' . ARCA_PADRON_SERVICIO . '. Detalle: ' . $error;
        }
        throw new RuntimeException($error ?: 'No se pudo consultar ARCA.');
    }
    return arca_padron_interpretar($c, $empresa_id, $cuit, $r['persona'], (string)$cfg['ambiente_nombre']);
}

/** Respuesta de getPersona_v2 (arca_padron_a_array) -> datos para el ERP. */
function arca_padron_interpretar(mysqli $c, int $empresa_id, string $cuit, array $p, string $ambiente = ''): array
{
    $dg = (array)($p['datosGenerales'] ?? []);
    $avisos = [];
    // errorRegimenGeneral / errorMonotributo solo explican una condición que no se pudo determinar (un monotributista
    // siempre trae "no inscripto en el régimen general")
    $errores_regimen = [];
    foreach (['errorConstancia', 'errorRegimenGeneral', 'errorMonotributo'] as $k) {
        foreach ((array)($p[$k]['error'] ?? []) as $e) {
            if ($e === '') continue;
            if ($k === 'errorConstancia') $avisos[] = $e;
            else $errores_regimen[] = $e;
        }
    }
    if (!$dg) {
        throw new RuntimeException('ARCA no devolvió la constancia del CUIT' . ($avisos ? ': ' . implode(' ', $avisos) : '.'));
    }

    $tipo_persona = strtoupper((string)($dg['tipoPersona'] ?? ''));
    $razon = trim((string)($dg['razonSocial'] ?? ''));
    if ($razon === '') $razon = trim(($dg['apellido'] ?? '') . ' ' . ($dg['nombre'] ?? ''));
    $estado_clave = strtoupper((string)($dg['estadoClave'] ?? ''));
    if ($estado_clave !== '' && $estado_clave !== 'ACTIVO') $avisos[] = "La CUIT figura en ARCA con estado $estado_clave.";

    // Condición frente al IVA
    $impuestos = [];
    foreach (array_merge((array)($p['datosRegimenGeneral']['impuesto'] ?? []), (array)($p['datosMonotributo']['impuesto'] ?? [])) as $i) {
        $impuestos[] = ['id' => intval($i['idImpuesto'] ?? 0), 'descripcion' => (string)($i['descripcionImpuesto'] ?? ''),
                        'estado' => strtoupper((string)($i['estadoImpuesto'] ?? ''))];
    }
    $activo = fn(int $id) => (bool)array_filter($impuestos, fn($i) => $i['id'] === $id && ($i['estado'] === '' || $i['estado'] === 'ACTIVO'));
    $categoria_mt = trim((string)($p['datosMonotributo']['categoriaMonotributo']['descripcionCategoria'] ?? ''));
    if ($categoria_mt !== '' || $activo(ARCA_IMP_MONOTRIBUTO)) $cond = 'MT';
    elseif ($activo(ARCA_IMP_IVA)) $cond = 'RI';
    elseif ($activo(ARCA_IMP_IVA_EXENTO)) $cond = 'EX';
    elseif ($activo(ARCA_IMP_IVA_NO_ALCANZADO)) { $cond = 'EX'; $avisos[] = 'En ARCA figura como "IVA no alcanzado": se propone Exento.'; }
    else { $cond = 'CF'; $avisos = array_merge($avisos, $errores_regimen); }
    $cf = arca_padron_fila($c, "SELECT condicion_fiscal_id, condicion_fiscal FROM gestion__condiciones_fiscales
            WHERE condicion_fiscal_codigo = ? AND tabla_estado_registro_id = 1 LIMIT 1", 's', $cond);

    // Domicilio fiscal -> provincia y localidad del ERP
    $dom = (array)($dg['domicilioFiscal'] ?? []);
    $direccion = trim((string)($dom['direccion'] ?? ''));
    $adicional = trim((string)($dom['datoAdicional'] ?? ''));
    if ($adicional !== '') $direccion = trim("$direccion $adicional");
    $loc_arca = trim((string)($dom['localidad'] ?? ''));
    $cp = trim((string)($dom['codPostal'] ?? ''));
    $prov_arca = trim((string)($dom['descripcionProvincia'] ?? ''));
    $provincia = arca_padron_provincia($c, $prov_arca, isset($dom['idProvincia']) ? intval($dom['idProvincia']) : null);
    $localidad = $provincia ? arca_padron_localidad($c, intval($provincia['provincia_id']), $loc_arca, $cp) : null;
    if ($direccion !== '' && !$localidad) $avisos[] = "No se encontró la localidad \"$loc_arca\" (CP $cp) en el sistema: elegila a mano.";

    $actividades = [];
    foreach (array_merge((array)($p['datosRegimenGeneral']['actividad'] ?? []), (array)($p['datosMonotributo']['actividad'] ?? [])) as $a) {
        $actividades[] = ['id' => (string)($a['idActividad'] ?? ''), 'descripcion' => (string)($a['descripcionActividad'] ?? ''),
                          'orden' => intval($a['orden'] ?? 99)];
    }
    usort($actividades, fn($a, $b) => $a['orden'] <=> $b['orden']);

    $existente = arca_padron_fila($c, "SELECT e.entidad_id, e.entidad_nombre,
                IF(" . ent_sql_tiene_rol('e.entidad_id', 'CLI') . ", 1, 0) AS es_cliente,
                IF(" . ent_sql_tiene_rol('e.entidad_id', 'PRO') . ", 1, 0) AS es_proveedor
            FROM gestion__entidades e WHERE e.empresa_id = ? AND e.cuit = ? ORDER BY e.tabla_estado_registro_id, e.entidad_id LIMIT 1",
            'is', $empresa_id, $cuit);

    error_log("=== arca_padron_consultar_erp empresa $empresa_id CUIT $cuit: $razon ($cond) ===");
    return [
        'cuit' => $cuit,
        'razon_social' => mb_substr($razon, 0, 255),
        'tipo_persona' => $tipo_persona,
        'entidad_tipo_id' => $tipo_persona === 'JURIDICA' || in_array(substr($cuit, 0, 2), ['30', '33', '34'], true) ? 2 : 1,
        'estado_clave' => $estado_clave,
        'condicion_iva_codigo' => $cond,
        'condicion_fiscal_id' => $cf ? intval($cf['condicion_fiscal_id']) : null,
        'condicion_fiscal' => $cf['condicion_fiscal'] ?? null,
        'categoria_monotributo' => $categoria_mt ?: null,
        'domicilio' => mb_substr($direccion, 0, 150),
        'localidad_arca' => $loc_arca, 'cp' => $cp, 'provincia_arca' => $prov_arca,
        'provincia_id' => $provincia ? intval($provincia['provincia_id']) : null,
        'provincia' => $provincia['provincia'] ?? null,
        'localidad_id' => $localidad ? intval($localidad['localidad_id']) : null,
        'localidad' => $localidad['localidad'] ?? null,
        'localidad_cp' => $localidad['cp'] ?? null,
        'impuestos' => $impuestos,
        'actividades' => array_slice($actividades, 0, 5),
        'entidad_existente' => $existente ? ['entidad_id' => intval($existente['entidad_id']), 'nombre' => $existente['entidad_nombre'],
                                             'es_cliente' => (bool)$existente['es_cliente'], 'es_proveedor' => (bool)$existente['es_proveedor']] : null,
        'ambiente' => $ambiente,
        'avisos' => array_values(array_unique($avisos)),
    ];
}

function arca_padron_cuit_valido(string $cuit): bool
{
    if (strlen($cuit) !== 11 || !ctype_digit($cuit)) return false;
    $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) $suma += intval($cuit[$i]) * $pesos[$i];
    $dv = 11 - ($suma % 11);
    if ($dv === 11) $dv = 0;
    return $dv !== 10 && $dv === intval($cuit[10]);
}

function arca_padron_fila(mysqli $c, string $sql, string $tipos, ...$params): ?array
{
    $stmt = mysqli_prepare($c, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $f ?: null;
}

/** Mayúsculas sin acentos ni signos, para comparar nombres de provincia / localidad. */
function arca_padron_normalizar(string $t): string
{
    $t = strtr(mb_strtoupper($t, 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9 ]/', ' ', $t)));
}

/** Provincia de conf__provincias para la de ARCA (idProvincia 0 = Ciudad Autónoma de Buenos Aires). */
function arca_padron_provincia(mysqli $c, string $nombre, ?int $id_arca): ?array
{
    $provincias = [];
    $res = mysqli_query($c, "SELECT provincia_id, provincia FROM conf__provincias WHERE tabla_estado_registro_id = 1");
    while ($res && ($f = mysqli_fetch_assoc($res))) $provincias[] = $f;
    $buscado = arca_padron_normalizar($nombre);
    $es_caba = $id_arca === 0 || strpos($buscado, 'CIUDAD AUTONOMA') !== false || $buscado === 'CAPITAL FEDERAL';
    foreach ($provincias as $p) {
        $n = arca_padron_normalizar($p['provincia']);
        if ($es_caba ? (strpos($n, 'CIUDAD') !== false || strpos($n, 'CAPITAL') !== false) : $n === $buscado) return $p;
    }
    if ($buscado === '') return null;
    foreach ($provincias as $p) {
        $n = arca_padron_normalizar($p['provincia']);
        if (!$es_caba && ($n !== '' && (strpos($buscado, $n) !== false || strpos($n, $buscado) !== false))) return $p;
    }
    return null;
}

/** Localidad de la provincia: por código postal y nombre; si el nombre no coincide, la única de ese CP. */
function arca_padron_localidad(mysqli $c, int $provincia_id, string $nombre, string $cp): ?array
{
    $cp_num = intval(preg_replace('/\D/', '', $cp));
    $buscado = arca_padron_normalizar($nombre);
    $candidatas = [];
    if ($cp_num > 0) {
        $stmt = mysqli_prepare($c, "SELECT localidad_id, localidad, cp FROM conf__localidades
                WHERE provincia_id = ? AND cp = ? AND tabla_estado_registro_id = 1 ORDER BY localidad");
        mysqli_stmt_bind_param($stmt, 'ii', $provincia_id, $cp_num);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($f = mysqli_fetch_assoc($res)) $candidatas[] = $f;
        mysqli_stmt_close($stmt);
    }
    foreach ($candidatas as $l) if (arca_padron_normalizar($l['localidad']) === $buscado) return $l;
    foreach ($candidatas as $l) {
        $n = arca_padron_normalizar($l['localidad']);
        if ($buscado !== '' && (strpos($n, $buscado) !== false || strpos($buscado, $n) !== false)) return $l;
    }
    if (count($candidatas) === 1) return $candidatas[0];
    if ($buscado === '' && $candidatas) return $candidatas[0];
    // Sin CP o sin coincidencia: por nombre exacto en la provincia
    $stmt = mysqli_prepare($c, "SELECT localidad_id, localidad, cp FROM conf__localidades
            WHERE provincia_id = ? AND localidad = ? AND tabla_estado_registro_id = 1 ORDER BY cp LIMIT 2");
    mysqli_stmt_bind_param($stmt, 'is', $provincia_id, $nombre);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($f = mysqli_fetch_assoc($res)) $filas[] = $f;
    mysqli_stmt_close($stmt);
    if (count($filas) === 1) return $filas[0];
    if ($candidatas) return $candidatas[0];
    // Provincia con una sola localidad (Ciudad de Buenos Aires)
    $stmt = mysqli_prepare($c, "SELECT localidad_id, localidad, cp FROM conf__localidades
            WHERE provincia_id = ? AND tabla_estado_registro_id = 1 LIMIT 2");
    mysqli_stmt_bind_param($stmt, 'i', $provincia_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($f = mysqli_fetch_assoc($res)) $filas[] = $f;
    mysqli_stmt_close($stmt);
    return count($filas) === 1 ? $filas[0] : null;
}
