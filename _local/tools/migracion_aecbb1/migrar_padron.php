<?php
declare(strict_types=1);

/**
 * tools/migracion_aecbb1/migrar_padron.php
 *
 * Herramienta CLI de migración del Padrón desde develops_aecbb1 hacia gestion_multipyme:
 *   1. Empleadores: sindi__sociedades -> gestion__entidades + roles + sucursales + sindi__empleadores
 *   2. Personas:    sindi__personas   -> sindi__personas (normalización de CUIL, nombres y fechas)
 *   3. Afiliados:   sindi__re_sindicales -> sindi__afiliaciones (consolidadas por persona y empleador)
 *                   sindi__afiliados + sindi__re_sindicales_solicitudes -> voluntarias/solicitudes
 *
 * Uso:
 *   php tools/migracion_aecbb1/migrar_padron.php [--empresa=7] [--simular] [--paso=all|empleadores|personas|afiliaciones] [--limite=N] [--dry-run]
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Parseo de argumentos de línea de comando
$options = getopt('', ['empresa::', 'origen-db::', 'simular', 'dry-run', 'paso::', 'limite::', 'ayuda']);

if (isset($options['ayuda'])) {
    echo <<<HELP
Herramienta de Migración de Padrón AECBB -> Sindicatos
======================================================
Uso: php tools/migracion_aecbb1/migrar_padron.php [opciones]

Opciones:
  --empresa=ID       ID de la empresa destino (por defecto: 7 - AEC)
  --origen-db=NOMBRE Base de datos origen (por defecto: develops_aecbb1)
  --simular          Ejecuta sobre gestion_multipyme_sim en lugar de gestion_multipyme
  --dry-run          Ejecuta toda la lógica pero hace ROLLBACK al final
  --paso=PASO        all | empleadores | personas | afiliaciones (por defecto: all)
  --limite=N         Límite de registros a procesar por paso (para pruebas)
  --ayuda            Muestra esta ayuda

HELP;
    exit(0);
}

$empresa_id = isset($options['empresa']) ? (int)$options['empresa'] : 7;
$origen_db  = isset($options['origen-db']) ? (string)$options['origen-db'] : 'develops_aecbb1';
$es_simular = isset($options['simular']);
$dry_run    = isset($options['dry-run']);
$paso       = isset($options['paso']) ? strtolower((string)$options['paso']) : 'all';
$limite     = isset($options['limite']) ? (int)$options['limite'] : 0;

$destino_db = $es_simular ? 'gestion_multipyme_sim' : 'gestion_multipyme';

echo "\n======================================================================\n";
echo " MIGRACIÓN DE PADRÓN Y AFILIADOS DESDE AECBB1\n";
echo "======================================================================\n";
echo " Base origen:  {$origen_db}\n";
echo " Base destino: {$destino_db}\n";
echo " Empresa ID:   {$empresa_id}\n";
echo " Paso:         {$paso}\n";
echo " Modo:         " . ($dry_run ? "DRY-RUN (Simulación sin guardar)" : ($es_simular ? "SIMULACIÓN (gestion_multipyme_sim)" : "PRODUCCIÓN / REAL")) . "\n";
echo " Límite:       " . ($limite > 0 ? $limite : "Sin límite (completo)") . "\n";
echo "======================================================================\n\n";

// Conexiones MySQL
$origenConn = mysqli_connect('localhost', 'root', '', $origen_db);
if (!$origenConn) {
    die("ERROR: No se pudo conectar a la base origen {$origen_db}: " . mysqli_connect_error() . "\n");
}
mysqli_set_charset($origenConn, 'latin1'); // La base origen usa latin1

$destConn = mysqli_connect('localhost', 'root', '', $destino_db);
if (!$destConn) {
    die("ERROR: No se pudo conectar a la base destino {$destino_db}: " . mysqli_connect_error() . "\n");
}
mysqli_set_charset($destConn, 'utf8mb4');

// Cargar helpers del ERP
require_once __DIR__ . '/../../../modules/sindicatos/sindi_base_model.php';

// Validar que la empresa destino exista y tenga módulos activos
$stmt = mysqli_prepare($destConn, "SELECT empresa FROM conf__empresas WHERE empresa_id = ?");
mysqli_stmt_bind_param($stmt, "i", $empresa_id);
mysqli_stmt_execute($stmt);
$empData = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$empData) {
    die("ERROR: La empresa {$empresa_id} no existe en {$destino_db}.\n");
}
echo "Empresa destino confirmada: {$empData['empresa']} (ID: {$empresa_id})\n\n";

// Helper para convertir texto latin1 -> UTF-8 limpio
function limpiarTexto(?string $txt): string {
    if ($txt === null) return '';
    $t = trim($txt);
    if ($t === '') return '';
    // Si viene en latin1 desde la base legacy
    if (!mb_check_encoding($t, 'UTF-8')) {
        $t = mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1');
    }
    return $t;
}

// Helper para formatear CUIL/CUIT a 11 dígitos
function formatearCuit($num): ?string {
    if (!$num) return null;
    $s = preg_replace('/\D/', '', number_format((float)$num, 0, '', ''));
    if (strlen($s) < 10) return null;
    return str_pad($s, 11, '0', STR_PAD_LEFT);
}

// Iniciar transacción global si es dry-run o por bloques
mysqli_begin_transaction($destConn);

try {
    $stats = [
        'empleadores' => ['leidos' => 0, 'insertados' => 0, 'actualizados' => 0, 'sucursales' => 0, 'errores' => 0],
        'personas'    => ['leidos' => 0, 'insertados' => 0, 'actualizados' => 0, 'errores' => 0],
        'afiliaciones'=> ['leidas' => 0, 'insertadas' => 0, 'actualizadas' => 0, 'omitidas' => 0],
    ];

    // =========================================================================
    // PASO 1: EMPLEADORES (sindi__sociedades -> gestion__entidades + sindi__empleadores)
    // =========================================================================
    if (in_array($paso, ['all', 'empleadores'], true)) {
        echo ">>> [1/3] Migrando Empleadores (sindi__sociedades)...\n";

        // Obtener convenio por defecto de la empresa (si la tabla existe)
        $convenio_id = null;
        $checkTable = mysqli_query($destConn, "SHOW TABLES LIKE 'sindi__convenios'");
        if ($checkTable && mysqli_num_rows($checkTable) > 0) {
            $stmtConv = mysqli_prepare($destConn, "SELECT convenio_id FROM sindi__convenios WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY convenio_id LIMIT 1");
            if ($stmtConv) {
                mysqli_stmt_bind_param($stmtConv, "i", $empresa_id);
                mysqli_stmt_execute($stmtConv);
                $convRow = mysqli_fetch_row(mysqli_stmt_get_result($stmtConv));
                mysqli_stmt_close($stmtConv);
                if ($convRow) {
                    $convenio_id = (int)$convRow[0];
                }
            }
        }

        $sqlSoc = "SELECT * FROM sindi__sociedades WHERE cuit IS NOT NULL AND cuit > 0 ORDER BY sociedad_id";
        if ($limite > 0) $sqlSoc .= " LIMIT {$limite}";
        $resSoc = mysqli_query($origenConn, $sqlSoc);

        while ($soc = mysqli_fetch_assoc($resSoc)) {
            $stats['empleadores']['leidos']++;
            $sociedad_id = (int)$soc['sociedad_id'];
            $cuit = formatearCuit($soc['cuit']);
            if (!$cuit) {
                $stats['empleadores']['errores']++;
                continue;
            }

            $razon_social = limpiarTexto($soc['razon_social']);
            if ($razon_social === '') {
                $razon_social = "EMPLEADOR CUIT " . $cuit;
            }
            $nombre_fantasia = limpiarTexto($soc['nombre_fantasia'] ?? '');
            $domicilio_legal = limpiarTexto($soc['dom_legal_calle'] ?? $soc['dom_comer_calle'] ?? '');
            $telefono = limpiarTexto($soc['dom_legal_telefono'] ?? $soc['dom_comer_telefono'] ?? $soc['contacto_telefono'] ?? '');
            $email = limpiarTexto($soc['email_sociedad'] ?? '');
            $estado = in_array((int)$soc['estado_registro_id'], [1, 5], true) ? 1 : 2;

            $localidad_id = !empty($soc['dom_legal_localidad_id']) ? (int)$soc['dom_legal_localidad_id'] : 16353;
            // Verificar localidad válida
            $stLoc = mysqli_prepare($destConn, "SELECT localidad_id FROM conf__localidades WHERE localidad_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stLoc, "i", $localidad_id);
            mysqli_stmt_execute($stLoc);
            if (!mysqli_fetch_row(mysqli_stmt_get_result($stLoc))) {
                $localidad_id = 16353; // Bahía Blanca por defecto
            }
            mysqli_stmt_close($stLoc);

            // 1. Buscar o insertar gestion__entidades
            $stmtEnt = mysqli_prepare($destConn, "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? LIMIT 1 FOR UPDATE");
            $cuitBigInt = (int)$cuit;
            mysqli_stmt_bind_param($stmtEnt, "ii", $empresa_id, $cuitBigInt);
            mysqli_stmt_execute($stmtEnt);
            $rowEnt = mysqli_fetch_row(mysqli_stmt_get_result($stmtEnt));
            mysqli_stmt_close($stmtEnt);

            $entidad_id = 0;
            if ($rowEnt) {
                $entidad_id = (int)$rowEnt[0];
                $stmtUpd = mysqli_prepare($destConn, "UPDATE gestion__entidades 
                    SET entidad_nombre = ?, entidad_fantasia = ?, domicilio_legal = ?, localidad_id = ?, tabla_estado_registro_id = ?
                    WHERE entidad_id = ?");
                mysqli_stmt_bind_param($stmtUpd, "sssiii", $razon_social, $nombre_fantasia, $domicilio_legal, $localidad_id, $estado, $entidad_id);
                mysqli_stmt_execute($stmtUpd);
                mysqli_stmt_close($stmtUpd);
                $stats['empleadores']['actualizados']++;
            } else {
                $stmtIns = mysqli_prepare($destConn, "INSERT INTO gestion__entidades 
                    (empresa_id, entidad_nombre, entidad_fantasia, cuit, domicilio_legal, localidad_id, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmtIns, "issisii", $empresa_id, $razon_social, $nombre_fantasia, $cuitBigInt, $domicilio_legal, $localidad_id, $estado);
                mysqli_stmt_execute($stmtIns);
                $entidad_id = (int)mysqli_insert_id($destConn);
                mysqli_stmt_close($stmtIns);
                $stats['empleadores']['insertados']++;
            }

            // 2. Rol EMPL (y CLI: el empleador también se ve como cliente en Gestión Normal)
            sindi_asegurar_rol_entidad($destConn, $entidad_id, 'EMPL');
            sindi_asegurar_rol_entidad($destConn, $entidad_id, 'CLI');

            // 3. Casa Central N° 1
            sindi_guardar_sucursal_central($destConn, $empresa_id, $entidad_id, $domicilio_legal, $telefono, $email, $localidad_id);
            $stats['empleadores']['sucursales']++;

            // 4. Sucursales adicionales desde sindi__sociedades_sucursales
            $sqlSuc = "SELECT * FROM sindi__sociedades_sucursales WHERE sociedad_id = {$sociedad_id} AND f_baja = '0000-00-00' ORDER BY sociedad_sucursal_id";
            $resSuc = mysqli_query($origenConn, $sqlSuc);
            $numSuc = 2;
            while ($sucRow = mysqli_fetch_assoc($resSuc)) {
                $sucNombre = limpiarTexto($sucRow['sucursal_nombre'] ?: "Sucursal {$numSuc}");
                $sucDir = limpiarTexto($sucRow['dom_comer_calle'] ?? '');
                $sucTel = limpiarTexto($sucRow['dom_comer_telefono'] ?? '');
                $sucMail = limpiarTexto($sucRow['email_comer'] ?? '');
                $sucLoc = !empty($sucRow['dom_comer_localidad_id']) ? (int)$sucRow['dom_comer_localidad_id'] : $localidad_id;

                $stCheckSuc = mysqli_prepare($destConn, "SELECT sucursal_id FROM gestion__entidades_sucursales WHERE entidad_id = ? AND sucursal_numero = ?");
                mysqli_stmt_bind_param($stCheckSuc, "ii", $entidad_id, $numSuc);
                mysqli_stmt_execute($stCheckSuc);
                $existSuc = mysqli_fetch_row(mysqli_stmt_get_result($stCheckSuc));
                mysqli_stmt_close($stCheckSuc);

                if (!$existSuc) {
                    $stInsSuc = mysqli_prepare($destConn, "INSERT INTO gestion__entidades_sucursales
                        (entidad_id, empresa_id, sucursal_numero, sucursal_nombre, sucursal_direccion, localidad_id, sucursal_telefono, sucursal_email, tabla_estado_registro_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    mysqli_stmt_bind_param($stInsSuc, "iiississ", $entidad_id, $empresa_id, $numSuc, $sucNombre, $sucDir, $sucLoc, $sucTel, $sucMail);
                    mysqli_stmt_execute($stInsSuc);
                    mysqli_stmt_close($stInsSuc);
                    $stats['empleadores']['sucursales']++;
                }
                $numSuc++;
            }

            // 5. sindi__empleadores
            $f_alta = ($soc['f_alta'] && $soc['f_alta'] !== '0000-00-00') ? $soc['f_alta'] : (($soc['f_confirma'] && $soc['f_confirma'] !== '0000-00-00') ? $soc['f_confirma'] : '2020-01-01');
            $rubro = limpiarTexto($soc['sociedad_categoria_motivo'] ?? '');

            $stEmp = mysqli_prepare($destConn, "SELECT empleador_id FROM sindi__empleadores WHERE empresa_id = ? AND entidad_id = ? LIMIT 1 FOR UPDATE");
            mysqli_stmt_bind_param($stEmp, "ii", $empresa_id, $entidad_id);
            mysqli_stmt_execute($stEmp);
            $rowEmp = mysqli_fetch_row(mysqli_stmt_get_result($stEmp));
            mysqli_stmt_close($stEmp);

            if ($rowEmp) {
                $empleador_id = (int)$rowEmp[0];
                $stUpdEmp = mysqli_prepare($destConn, "UPDATE sindi__empleadores 
                    SET legacy_id = ?, convenio_id = COALESCE(convenio_id, ?), rubro = COALESCE(rubro, ?), tabla_estado_registro_id = ?
                    WHERE empleador_id = ?");
                mysqli_stmt_bind_param($stUpdEmp, "iisii", $sociedad_id, $convenio_id, $rubro, $estado, $empleador_id);
                mysqli_stmt_execute($stUpdEmp);
                mysqli_stmt_close($stUpdEmp);
            } else {
                $stInsEmp = mysqli_prepare($destConn, "INSERT INTO sindi__empleadores 
                    (empresa_id, entidad_id, rubro, convenio_id, f_alta_padron, origen, legacy_id, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, ?, 'MIGRACION', ?, ?)");
                mysqli_stmt_bind_param($stInsEmp, "iisisii", $empresa_id, $entidad_id, $rubro, $convenio_id, $f_alta, $sociedad_id, $estado);
                mysqli_stmt_execute($stInsEmp);
                $empleador_id = (int)mysqli_insert_id($destConn);
                mysqli_stmt_close($stInsEmp);
            }
            $mapEmpleadores[$sociedad_id] = $empleador_id;
        }
        echo "   -> Leídos: {$stats['empleadores']['leidos']} | Insertados: {$stats['empleadores']['insertados']} | Actualizados: {$stats['empleadores']['actualizados']} | Sucursales: {$stats['empleadores']['sucursales']}\n\n";
    }

    // =========================================================================
    // PASO 2: PERSONAS (sindi__personas)
    // =========================================================================
    if (in_array($paso, ['all', 'personas'], true)) {
        echo ">>> [2/3] Migrando Personas (sindi__personas)...\n";

        $sqlPer = "SELECT * FROM sindi__personas WHERE cuil IS NOT NULL AND cuil > 0 ORDER BY persona_id";
        if ($limite > 0) $sqlPer .= " LIMIT {$limite}";
        $resPer = mysqli_query($origenConn, $sqlPer);

        while ($p = mysqli_fetch_assoc($resPer)) {
            $stats['personas']['leidos']++;
            $persona_id_legacy = (int)$p['persona_id'];
            $cuil = formatearCuit($p['cuil']);
            if (!$cuil) {
                $stats['personas']['errores']++;
                continue;
            }

            $apellido = mb_strtoupper(limpiarTexto($p['apellido']));
            if ($apellido === '') $apellido = 'SIN APELLIDO';
            $nombre = mb_strtoupper(limpiarTexto($p['nombre']));
            $doc_nro = substr($cuil, 2, 8);
            $sexo = in_array(strtoupper(trim($p['sexo'] ?? '')), ['M', 'F', 'X'], true) ? strtoupper(trim($p['sexo'])) : 'M';
            $f_nac = (!empty($p['f_nacimiento']) && $p['f_nacimiento'] !== '0000-00-00') ? $p['f_nacimiento'] : null;
            $email = filter_var(trim($p['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? strtolower(trim($p['email'])) : null;
            $telefono = limpiarTexto($p['telefono'] ?? $p['telefono_celular'] ?? '');
            if ($telefono === '') $telefono = null;
            $direccion = limpiarTexto($p['domicilio'] ?? '');
            if ($direccion === '') $direccion = null;
            $localidad_id = !empty($p['localidad_id']) ? (int)$p['localidad_id'] : 16353;
            $estado = ((int)$p['estado_registro_id'] === 1 || (int)$p['estado_registro_id'] === 5) ? 1 : 2;

            $stCheckP = mysqli_prepare($destConn, "SELECT persona_id FROM sindi__personas WHERE empresa_id = ? AND cuil = ? LIMIT 1 FOR UPDATE");
            mysqli_stmt_bind_param($stCheckP, "is", $empresa_id, $cuil);
            mysqli_stmt_execute($stCheckP);
            $rowP = mysqli_fetch_row(mysqli_stmt_get_result($stCheckP));
            mysqli_stmt_close($stCheckP);

            $dest_persona_id = 0;
            if ($rowP) {
                $dest_persona_id = (int)$rowP[0];
                $stUpdP = mysqli_prepare($destConn, "UPDATE sindi__personas 
                    SET apellido = ?, nombre = ?, documento_nro = ?, sexo = ?, f_nacimiento = COALESCE(f_nacimiento, ?),
                        email = COALESCE(email, ?), telefono = COALESCE(telefono, ?), direccion = COALESCE(direccion, ?),
                        legacy_id = ?, tabla_estado_registro_id = ?
                    WHERE persona_id = ?");
                mysqli_stmt_bind_param($stUpdP, "ssssssssiii", $apellido, $nombre, $doc_nro, $sexo, $f_nac, $email, $telefono, $direccion, $persona_id_legacy, $estado, $dest_persona_id);
                mysqli_stmt_execute($stUpdP);
                mysqli_stmt_close($stUpdP);
                $stats['personas']['actualizados']++;
            } else {
                $stInsP = mysqli_prepare($destConn, "INSERT INTO sindi__personas 
                    (empresa_id, cuil, apellido, nombre, documento_tipo_id, documento_nro, sexo, f_nacimiento, email, telefono, direccion, localidad_id, legacy_id, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stInsP, "isssssssssiii", $empresa_id, $cuil, $apellido, $nombre, $doc_nro, $sexo, $f_nac, $email, $telefono, $direccion, $localidad_id, $persona_id_legacy, $estado);
                mysqli_stmt_execute($stInsP);
                $dest_persona_id = (int)mysqli_insert_id($destConn);
                mysqli_stmt_close($stInsP);
                $stats['personas']['insertados']++;
            }
            $mapPersonas[$persona_id_legacy] = $dest_persona_id;
        }
        echo "   -> Leídos: {$stats['personas']['leidos']} | Insertados: {$stats['personas']['insertados']} | Actualizados: {$stats['personas']['actualizados']}\n\n";
    }

    // =========================================================================
    // PASO 3: AFILIACIONES (sindi__re_sindicales -> sindi__afiliaciones)
    // =========================================================================
    if (in_array($paso, ['all', 'afiliaciones'], true)) {
        echo ">>> [3/3] Migrando Afiliaciones Sindicales (sindi__re_sindicales)...\n";

        // Obtener tipo ACTIVIDAD para la empresa
        $stTipo = mysqli_prepare($destConn, "SELECT afiliacion_tipo_id FROM sindi__afiliaciones_tipos WHERE empresa_id = ? AND codigo = 'ACTIVIDAD' LIMIT 1");
        mysqli_stmt_bind_param($stTipo, "i", $empresa_id);
        mysqli_stmt_execute($stTipo);
        $tipoRow = mysqli_fetch_row(mysqli_stmt_get_result($stTipo));
        mysqli_stmt_close($stTipo);
        if (!$tipoRow) {
            throw new RuntimeException("No existe el tipo de afiliación 'ACTIVIDAD' en la empresa {$empresa_id}.");
        }
        $tipo_actividad_id = (int)$tipoRow[0];

        // Completar mapa en memoria de personas con las ya existentes en DB
        $rMapP = mysqli_query($destConn, "SELECT legacy_id, persona_id FROM sindi__personas WHERE empresa_id = {$empresa_id} AND legacy_id IS NOT NULL");
        while ($mp = mysqli_fetch_assoc($rMapP)) {
            $mapPersonas[(int)$mp['legacy_id']] = (int)$mp['persona_id'];
        }

        // Completar mapa en memoria de empleadores con los ya existentes en DB
        $rMapE = mysqli_query($destConn, "SELECT legacy_id, empleador_id FROM sindi__empleadores WHERE empresa_id = {$empresa_id} AND legacy_id IS NOT NULL");
        while ($me = mysqli_fetch_assoc($rMapE)) {
            $mapEmpleadores[(int)$me['legacy_id']] = (int)$me['empleador_id'];
        }

        // Obtener la secuencia inicial de número de afiliados
        $stMaxAf = mysqli_query($destConn, "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_afiliado, '/', 1) AS UNSIGNED)), 0) FROM sindi__afiliaciones WHERE empresa_id = {$empresa_id} AND numero_afiliado IS NOT NULL");
        $nextNumAfiliado = (int)mysqli_fetch_row($stMaxAf)[0];

        // Mapa de personas que ya tienen número de afiliado
        $mapNumerosAfiliado = [];
        $rNum = mysqli_query($destConn, "SELECT persona_id, numero_afiliado FROM sindi__afiliaciones WHERE empresa_id = {$empresa_id} AND titular_afiliacion_id IS NULL AND numero_afiliado IS NOT NULL");
        while ($rn = mysqli_fetch_assoc($rNum)) {
            $mapNumerosAfiliado[(int)$rn['persona_id']] = (string)$rn['numero_afiliado'];
        }

        // Agrupar relaciones sindicales por persona y sociedad
        $sqlRel = "SELECT persona_id, sociedad_id,
                          MIN(CASE WHEN f_alta IS NOT NULL AND f_alta <> '0000-00-00' THEN f_alta ELSE NULL END) as min_alta,
                          MAX(CASE WHEN f_baja IS NOT NULL AND f_baja <> '0000-00-00' THEN f_baja ELSE NULL END) as max_baja,
                          SUM(CASE WHEN estado_registro_id = 1 AND (f_baja IS NULL OR f_baja = '0000-00-00' OR f_baja > CURDATE()) THEN 1 ELSE 0 END) as cnt_activas,
                          MAX(re_sindical_id) as legacy_id
                   FROM sindi__re_sindicales
                   WHERE persona_id > 0 AND sociedad_id > 0
                   GROUP BY persona_id, sociedad_id
                   ORDER BY min_alta ASC, persona_id ASC";
        if ($limite > 0) $sqlRel .= " LIMIT {$limite}";
        $resRel = mysqli_query($origenConn, $sqlRel);

        while ($rel = mysqli_fetch_assoc($resRel)) {
            $stats['afiliaciones']['leidas']++;
            $legacy_p = (int)$rel['persona_id'];
            $legacy_s = (int)$rel['sociedad_id'];

            if (!isset($mapPersonas[$legacy_p])) {
                $stats['afiliaciones']['omitidas']++;
                continue;
            }
            if (!isset($mapEmpleadores[$legacy_s])) {
                $stats['afiliaciones']['omitidas']++;
                continue;
            }

            $dest_persona_id   = $mapPersonas[$legacy_p];
            $dest_empleador_id = $mapEmpleadores[$legacy_s];
            $es_activa         = (int)$rel['cnt_activas'] > 0;
            $estado            = $es_activa ? 1 : 2;
            $f_alta            = $rel['min_alta'] ?: '2020-01-01';
            $f_baja            = $es_activa ? null : ($rel['max_baja'] ?: date('Y-m-d'));
            $legacy_id         = (int)$rel['legacy_id'];

            // Asignar o reutilizar número de afiliado para la persona
            if (!isset($mapNumerosAfiliado[$dest_persona_id])) {
                $nextNumAfiliado++;
                $mapNumerosAfiliado[$dest_persona_id] = str_pad((string)$nextNumAfiliado, 6, '0', STR_PAD_LEFT);
            }
            $nroAfiliado = $mapNumerosAfiliado[$dest_persona_id];

            // Buscar si ya existe la afiliación
            $stCheckAf = mysqli_prepare($destConn, "SELECT afiliacion_id, tabla_estado_registro_id FROM sindi__afiliaciones 
                WHERE empresa_id = ? AND persona_id = ? AND empleador_id = ? AND afiliacion_tipo_id = ? LIMIT 1 FOR UPDATE");
            mysqli_stmt_bind_param($stCheckAf, "iiii", $empresa_id, $dest_persona_id, $dest_empleador_id, $tipo_actividad_id);
            mysqli_stmt_execute($stCheckAf);
            $rowAf = mysqli_fetch_assoc(mysqli_stmt_get_result($stCheckAf));
            mysqli_stmt_close($stCheckAf);

            if ($rowAf) {
                $stUpdAf = mysqli_prepare($destConn, "UPDATE sindi__afiliaciones 
                    SET f_alta = ?, f_baja = ?, numero_afiliado = COALESCE(numero_afiliado, ?), tabla_estado_registro_id = ?, origen = 'DDJJ', origen_id = ?
                    WHERE afiliacion_id = ?");
                mysqli_stmt_bind_param($stUpdAf, "sssiii", $f_alta, $f_baja, $nroAfiliado, $estado, $legacy_id, $rowAf['afiliacion_id']);
                mysqli_stmt_execute($stUpdAf);
                mysqli_stmt_close($stUpdAf);
                $stats['afiliaciones']['actualizadas']++;
            } else {
                $stInsAf = mysqli_prepare($destConn, "INSERT INTO sindi__afiliaciones 
                    (empresa_id, persona_id, afiliacion_tipo_id, empleador_id, f_alta, f_baja, origen, origen_id, numero_afiliado, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, ?, ?, 'DDJJ', ?, ?, ?)");
                mysqli_stmt_bind_param($stInsAf, "iiiissisi", $empresa_id, $dest_persona_id, $tipo_actividad_id, $dest_empleador_id, $f_alta, $f_baja, $legacy_id, $nroAfiliado, $estado);
                mysqli_stmt_execute($stInsAf);
                mysqli_stmt_close($stInsAf);
                $stats['afiliaciones']['insertadas']++;
            }
        }
        echo "   -> Leídas: {$stats['afiliaciones']['leidas']} | Insertadas: {$stats['afiliaciones']['insertadas']} | Actualizadas: {$stats['afiliaciones']['actualizadas']} | Omitidas: {$stats['afiliaciones']['omitidas']}\n\n";

        // Migrar Solicitudes de Afiliación (sindi__re_sindicales_solicitudes)
        echo ">>> Migrando Solicitudes de Afiliación (sindi__re_sindicales_solicitudes)...\n";
        $resSol = mysqli_query($origenConn, "SELECT * FROM sindi__re_sindicales_solicitudes ORDER BY re_sindical_solicitud_id");
        $stats['solicitudes'] = ['leidas' => 0, 'insertadas' => 0];
        while ($sol = mysqli_fetch_assoc($resSol)) {
            $stats['solicitudes']['leidas']++;
            $sol_id_legacy = (int)$sol['re_sindical_solicitud_id'];
            $cuilSol = formatearCuit($sol['cuil']);
            if (!$cuilSol) continue;

            $destPid = isset($mapPersonas[(int)$sol['persona_id']]) ? $mapPersonas[(int)$sol['persona_id']] : null;
            $destEid = (!empty($sol['sociedad_id']) && isset($mapEmpleadores[(int)$sol['sociedad_id']])) ? $mapEmpleadores[(int)$sol['sociedad_id']] : null;
            $legacyEstado = (int)$sol['estado_registro_id'];
            $destEstado = ($legacyEstado === 5) ? 30 : (($legacyEstado === 3) ? 4 : (($legacyEstado === 1) ? 3 : 25));

            $apeSol = mb_strtoupper(limpiarTexto($sol['apellido']));
            $nomSol = mb_strtoupper(limpiarTexto($sol['nombre']));
            $sexSol = in_array(strtoupper(trim($sol['sexo'] ?? '')), ['M', 'F', 'X'], true) ? strtoupper(trim($sol['sexo'])) : 'M';
            $f_nacSol = (!empty($sol['f_nacimiento']) && $sol['f_nacimiento'] !== '0000-00-00') ? $sol['f_nacimiento'] : null;
            $emailSol = filter_var(trim($sol['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? strtolower(trim($sol['email'])) : null;
            $telSol = limpiarTexto($sol['telefono'] ?? $sol['telefono_celular'] ?? '');
            $f_sol = (!empty($sol['f_solicitud']) && $sol['f_solicitud'] !== '0000-00-00') ? $sol['f_solicitud'] : date('Y-m-d');
            $f_res = (!empty($sol['f_confirma']) && $sol['f_confirma'] !== '0000-00-00') ? $sol['f_confirma'] : null;

            $stCheckSol = mysqli_prepare($destConn, "SELECT solicitud_id FROM sindi__afiliaciones_solicitudes WHERE empresa_id = ? AND cuil = ? AND f_solicitud = ? LIMIT 1");
            mysqli_stmt_bind_param($stCheckSol, "iss", $empresa_id, $cuilSol, $f_sol);
            mysqli_stmt_execute($stCheckSol);
            $rowSol = mysqli_fetch_row(mysqli_stmt_get_result($stCheckSol));
            mysqli_stmt_close($stCheckSol);

            if (!$rowSol) {
                $stInsSol = mysqli_prepare($destConn, "INSERT INTO sindi__afiliaciones_solicitudes 
                    (empresa_id, afiliacion_tipo_id, persona_id, cuil, apellido, nombre, f_nacimiento, sexo, email, telefono, empleador_id, canal, f_solicitud, f_resolucion, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'INTERNO', ?, ?, ?)");
                mysqli_stmt_bind_param($stInsSol, "iiisssssssissi", $empresa_id, $tipo_actividad_id, $destPid, $cuilSol, $apeSol, $nomSol, $f_nacSol, $sexSol, $emailSol, $telSol, $destEid, $f_sol, $f_res, $destEstado);
                mysqli_stmt_execute($stInsSol);
                mysqli_stmt_close($stInsSol);
                $stats['solicitudes']['insertadas']++;
            }
        }
        echo "   -> Solicitudes leídas: {$stats['solicitudes']['leidas']} | Insertadas: {$stats['solicitudes']['insertadas']}\n\n";
    }

    if ($dry_run) {
        mysqli_rollback($destConn);
        echo "\n>>> MODO DRY-RUN: Transacción revertida con éxito (ningún cambio fue guardado).\n";
    } else {
        mysqli_commit($destConn);
        echo "\n>>> MIGRACIÓN COMPLETADA CON ÉXITO Y GUARDADA EN {$destino_db}.\n";
    }

    // =========================================================================
    // REPORTE FINAL DE AUDITORÍA Y TOTALES
    // =========================================================================
    echo "\n======================================================================\n";
    echo " RESUMEN Y TOTALES EN EMPRESA {$empresa_id}\n";
    echo "======================================================================\n";

    $rTotEmp = mysqli_query($destConn, "SELECT count(*), sum(case when tabla_estado_registro_id=1 then 1 else 0 end) as activos FROM sindi__empleadores WHERE empresa_id = {$empresa_id}");
    $rowTotEmp = mysqli_fetch_row($rTotEmp);
    echo " Empleadores en sindicato: {$rowTotEmp[0]} totales (" . (int)($rowTotEmp[1] ?? 0) . " activos)\n";

    $rTotPer = mysqli_query($destConn, "SELECT count(*), sum(case when tabla_estado_registro_id=1 then 1 else 0 end) as activos FROM sindi__personas WHERE empresa_id = {$empresa_id}");
    $rowTotPer = mysqli_fetch_row($rTotPer);
    echo " Personas en padrón:        {$rowTotPer[0]} totales (" . (int)($rowTotPer[1] ?? 0) . " activas)\n";

    $rTotAf = mysqli_query($destConn, "SELECT count(*), sum(case when tabla_estado_registro_id=1 then 1 else 0 end) as activas FROM sindi__afiliaciones WHERE empresa_id = {$empresa_id}");
    $rowTotAf = mysqli_fetch_row($rTotAf);
    echo " Afiliaciones registradas:  {$rowTotAf[0]} totales (" . (int)($rowTotAf[1] ?? 0) . " vigentes)\n";
    echo "======================================================================\n\n";

} catch (Throwable $e) {
    mysqli_rollback($destConn);
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
