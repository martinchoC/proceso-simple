<?php
/**
 * Libros societarios: libros rubricados, actas y demás actos societarios (asambleas, directorio, reuniones de socios,
 * asientos de registro), asistentes / firmantes, autoridades designadas, transcripción al libro (número y folios)
 * e inscripción registral. Código único para Gestión (página 140), Gestión Normal (640) y Estudio Contable (424).
 *
 * Dónde viven los registros ("fuente"): empresa_id + estudio_cliente_id
 *   - Los libros de una empresa: empresa_id = la empresa, estudio_cliente_id = 0. Los ve y carga Gestión.
 *   - Estudio, cliente con empresa en el ERP (estudio__clientes.empresa_vinculada_id) que le dio al estudio el permiso
 *     "Libros societarios" en Vinculaciones (conf__empresas_vinculaciones.permiso_societario): el estudio trabaja sobre
 *     los libros de esa empresa (LECTURA = solo ver; LECTURA_ESCRITURA = cargar y modificar; lo que carga queda en
 *     Gestión con cargado_por_empresa_id = el estudio). El estudio como su propio cliente no necesita vinculación.
 *   - Estudio, cliente sin empresa en el ERP o sin ese permiso: libros guardados en el estudio
 *     (empresa_id = estudio, estudio_cliente_id = cliente). Con el permiso de escritura se pasan a la empresa
 *     con lsoc_pasar_a_empresa().
 * Tablas: gestion__soc_libros (453), gestion__soc_actas (454), gestion__soc_actas_personas (455).
 * Scripts: _local/sql/mantenimiento/20261108_libros_societarios_01.sql y _02_vinculacion.sql
 */

require_once __DIR__ . '/../formato.php';
require_once __DIR__ . '/../empresas_vinculaciones_model.php';

const LSOC_DIR_ARCHIVOS = 'uploads/libros_societarios';   // + /<empresa dueña>/; .htaccess niega el acceso web
const LSOC_MAX_ARCHIVO = 15 * 1024 * 1024;

const LSOC_TIPOS_LIBRO = [
    'ACTAS_ASAMBLEA' => 'Actas de Asamblea',
    'ACTAS_DIRECTORIO' => 'Actas de Directorio',
    'ASISTENCIA_ASAMBLEA' => 'Depósito de Acciones y Registro de Asistencia a Asambleas',
    'REGISTRO_ACCIONES' => 'Registro de Acciones',
    'ACTAS_REUNION_SOCIOS' => 'Actas de Reunión de Socios',
    'ACTAS_GERENCIA' => 'Actas de Gerencia',
    'ACTAS_SINDICATURA' => 'Actas de Sindicatura / Consejo de Vigilancia',
    'ACTAS_COMISION_DIRECTIVA' => 'Actas de Comisión Directiva',
    'ACTAS_CONSEJO_ADMINISTRACION' => 'Actas de Consejo de Administración',
    'REGISTRO_SOCIOS' => 'Registro de Socios / Asociados',
    'DIARIO' => 'Libro Diario',
    'INVENTARIO_BALANCES' => 'Inventario y Balances',
    'OTRO' => 'Otro libro',
];

/** Tipo de acto => [nombre, libros sugeridos] (la lista solo ordena el combo de libros; no se valida). */
const LSOC_TIPOS_ACTO = [
    'ASAMBLEA_ORDINARIA' => ['Asamblea Ordinaria', ['ACTAS_ASAMBLEA', 'ACTAS_REUNION_SOCIOS']],
    'ASAMBLEA_EXTRAORDINARIA' => ['Asamblea Extraordinaria', ['ACTAS_ASAMBLEA', 'ACTAS_REUNION_SOCIOS']],
    'REUNION_SOCIOS' => ['Reunión de Socios', ['ACTAS_REUNION_SOCIOS']],
    'REUNION_DIRECTORIO' => ['Reunión de Directorio', ['ACTAS_DIRECTORIO']],
    'REUNION_GERENCIA' => ['Reunión de Gerencia', ['ACTAS_GERENCIA']],
    'SINDICATURA' => ['Sindicatura / Consejo de Vigilancia', ['ACTAS_SINDICATURA']],
    'COMISION_DIRECTIVA' => ['Comisión Directiva', ['ACTAS_COMISION_DIRECTIVA']],
    'CONSEJO_ADMINISTRACION' => ['Consejo de Administración', ['ACTAS_CONSEJO_ADMINISTRACION']],
    'ASIENTO_REGISTRO' => ['Asiento en registro (acciones, asistencia, socios)', ['REGISTRO_ACCIONES', 'ASISTENCIA_ASAMBLEA', 'REGISTRO_SOCIOS']],
    'OTRO' => ['Otro acto societario', []],
];

/** Temas tratados => [nombre, sugiere inscripción registral]. */
const LSOC_MATERIAS = [
    'APROBACION_EECC' => ['Aprobación de estados contables', false],
    'DESIGNACION_AUTORIDADES' => ['Designación / renovación de autoridades', true],
    'RENUNCIA_AUTORIDADES' => ['Renuncia o remoción de autoridades', true],
    'HONORARIOS' => ['Honorarios de directores / gerentes', false],
    'DISTRIBUCION_RESULTADOS' => ['Distribución de resultados / dividendos', false],
    'CONVOCATORIA' => ['Convocatoria a asamblea', false],
    'CAPITAL' => ['Aumento / reducción de capital', true],
    'TRANSFERENCIA' => ['Transferencia de acciones / cesión de cuotas', true],
    'REFORMA_ESTATUTO' => ['Reforma de estatuto / contrato social', true],
    'CAMBIO_SEDE' => ['Cambio de sede social', true],
    'PODERES' => ['Otorgamiento / revocación de poderes', false],
    'DISOLUCION' => ['Disolución / liquidación', true],
    'OTRO' => ['Otros temas', false],
];

const LSOC_CARACTERES = [
    'PRESIDENTE' => 'Presidente', 'VICEPRESIDENTE' => 'Vicepresidente', 'DIRECTOR_TITULAR' => 'Director titular',
    'DIRECTOR_SUPLENTE' => 'Director suplente', 'GERENTE' => 'Gerente', 'SOCIO' => 'Socio', 'ACCIONISTA' => 'Accionista',
    'SINDICO' => 'Síndico', 'APODERADO' => 'Apoderado', 'REPRESENTANTE' => 'Representante', 'SECRETARIO' => 'Secretario',
    'TESORERO' => 'Tesorero', 'ASOCIADO' => 'Asociado', 'OTRO' => 'Otro',
];

const LSOC_ESTADOS_LIBRO = ['EN_USO' => 'En uso', 'COMPLETO' => 'Completo', 'CERRADO' => 'Cerrado', 'EXTRAVIADO' => 'Extraviado'];
const LSOC_ESTADOS_INSCRIPCION = ['NO_REQUIERE' => 'No requiere', 'PENDIENTE' => 'Pendiente', 'EN_TRAMITE' => 'En trámite',
                                  'OBSERVADA' => 'Observada', 'INSCRIPTA' => 'Inscripta'];

const LSOC_DIAS_MANDATO = 90;          // aviso de mandato por vencer
const LSOC_DIAS_BORRADOR = 30;         // aviso de acta sin transcribir
const LSOC_MESES_ASAMBLEA_EECC = 4;    // art. 234 LGS: asamblea ordinaria dentro de los 4 meses del cierre

function lsoc_resp(bool $ok, string $msg = '', $data = null): array
{
    return ['success' => $ok, 'data' => $data, 'message' => $msg];
}

// --------------------------------------------------------------------------------------------- utilidades

function lsoc_filas(mysqli $c, string $sql, string $tipos = '', array $params = []): array
{
    $st = mysqli_prepare($c, $sql);
    if (!$st) {
        throw new RuntimeException('lsoc_filas: ' . mysqli_error($c));
    }
    if ($tipos !== '') {
        mysqli_stmt_bind_param($st, $tipos, ...$params);
    }
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $filas = [];
    while ($r && ($f = mysqli_fetch_assoc($r))) {
        $filas[] = $f;
    }
    mysqli_stmt_close($st);
    return $filas;
}

function lsoc_fila(mysqli $c, string $sql, string $tipos = '', array $params = []): ?array
{
    $f = lsoc_filas($c, $sql, $tipos, $params);
    return $f[0] ?? null;
}

function lsoc_ejecutar(mysqli $c, string $sql, string $tipos, array $params): int
{
    $st = mysqli_prepare($c, $sql);
    if (!$st) {
        throw new RuntimeException('lsoc_ejecutar: ' . mysqli_error($c));
    }
    mysqli_stmt_bind_param($st, $tipos, ...$params);
    if (!mysqli_stmt_execute($st)) {
        $e = mysqli_stmt_error($st);
        mysqli_stmt_close($st);
        throw new RuntimeException('lsoc_ejecutar: ' . $e);
    }
    $id = (int) mysqli_insert_id($c);
    mysqli_stmt_close($st);
    return $id;
}

function lsoc_texto($v, int $max = 0): ?string
{
    $t = trim((string) ($v ?? ''));
    if ($t === '') {
        return null;
    }
    return $max > 0 ? mb_substr($t, 0, $max) : $t;
}

function lsoc_fecha($v): ?string
{
    $v = trim((string) ($v ?? ''));
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        throw new RuntimeException('Fecha inválida: ' . $v);
    }
    return $v;
}

function lsoc_entero_o_null($v): ?int
{
    $v = trim((string) ($v ?? ''));
    return $v === '' ? null : max(0, (int) $v);
}

function lsoc_decimal_o_null($v): ?float
{
    $v = trim((string) ($v ?? ''));
    return $v === '' ? null : (float) str_replace(',', '.', $v);
}

// --------------------------------------------------------------------------------------------- fuentes y ámbito

/** Los libros propios de una empresa (Gestión). */
function lsoc_fuente_empresa(int $empresa_id): array
{
    return ['empresa_id' => $empresa_id, 'estudio_cliente_id' => 0, 'cliente_id' => 0, 'cliente' => '',
            'origen' => 'EMPRESA', 'escritura' => true, 'aviso' => null];
}

/** Libros de un cliente guardados en el estudio. */
function lsoc_fuente_local(int $estudio_id, array $cli): array
{
    return ['empresa_id' => $estudio_id, 'estudio_cliente_id' => (int) $cli['cliente_id'], 'cliente_id' => (int) $cli['cliente_id'],
            'cliente' => (string) $cli['razon_social'], 'origen' => 'ESTUDIO', 'escritura' => true, 'aviso' => null];
}

/** Clientes del estudio (uno, en cualquier estado, o todos los activos). */
function lsoc_clientes_estudio(mysqli $c, int $estudio_id, int $cliente_id = 0): array
{
    if ($cliente_id > 0) {
        return lsoc_filas($c, "SELECT cliente_id, razon_social, cuit, empresa_vinculada_id FROM estudio__clientes
                               WHERE cliente_id = ? AND empresa_id = ?", "ii", [$cliente_id, $estudio_id]);
    }
    return lsoc_filas($c, "SELECT cliente_id, razon_social, cuit, empresa_vinculada_id FROM estudio__clientes
                           WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY razon_social", "i", [$estudio_id]);
}

/**
 * Dónde viven los libros de un cliente del estudio (ver el encabezado del archivo).
 * origen: CLIENTE_ERP (libros de la empresa del cliente) o ESTUDIO (guardados en el estudio).
 */
function lsoc_fuente_cliente(mysqli $c, int $estudio_id, array $cli): array
{
    $erp = (int) ($cli['empresa_vinculada_id'] ?? 0);
    $local = lsoc_fuente_local($estudio_id, $cli);
    if ($erp <= 0) {
        return $local;
    }
    $propio = $erp === $estudio_id;
    if ($propio || vinculacionesVerificarPermiso($c, $erp, $estudio_id, 'societario', false)) {
        $escribe = $propio || vinculacionesVerificarPermiso($c, $erp, $estudio_id, 'societario', true);
        return ['empresa_id' => $erp, 'estudio_cliente_id' => 0, 'cliente_id' => (int) $cli['cliente_id'],
                'cliente' => (string) $cli['razon_social'], 'origen' => 'CLIENTE_ERP', 'escritura' => $escribe,
                'aviso' => $escribe ? null : 'La empresa del cliente autorizó solo la lectura de sus libros societarios.'];
    }
    $local['aviso'] = 'La empresa del cliente no autorizó al estudio en sus libros societarios (Vinculaciones): lo que cargues queda solo en el estudio.';
    return $local;
}

/**
 * Ámbito de la consulta: ['modo', 'ctx_empresa_id', 'cliente_id' (null = todos), 'fuentes' => [fuente, ...]].
 * En el estudio, un cliente que trabaja con los libros de su empresa suma también lo que el estudio guardó antes
 * de la autorización (se ve hasta pasarlo a la empresa).
 */
function lsoc_ambito(mysqli $c, int $empresa_id, string $modo, $cliente_id_request, bool $exigir_cliente): array
{
    if ($modo !== 'estudio') {
        return ['modo' => 'empresa', 'ctx_empresa_id' => $empresa_id, 'cliente_id' => 0, 'fuentes' => [lsoc_fuente_empresa($empresa_id)]];
    }
    $cid = (int) ($cliente_id_request ?? 0);
    if ($cid <= 0 && $exigir_cliente) {
        throw new RuntimeException('Elegí un cliente.');
    }
    $clientes = lsoc_clientes_estudio($c, $empresa_id, max(0, $cid));
    if ($cid > 0 && !$clientes) {
        throw new RuntimeException('El cliente no existe.');
    }
    $fuentes = [];
    foreach ($clientes as $cli) {
        $f = lsoc_fuente_cliente($c, $empresa_id, $cli);
        $fuentes[$f['empresa_id'] . '|' . $f['estudio_cliente_id']] = $f;
        if ($f['origen'] === 'CLIENTE_ERP') {
            $loc = lsoc_fuente_local($empresa_id, $cli);
            $fuentes[$loc['empresa_id'] . '|' . $loc['estudio_cliente_id']] = $loc;
        }
    }
    return ['modo' => 'estudio', 'ctx_empresa_id' => $empresa_id, 'cliente_id' => $cid > 0 ? $cid : null, 'fuentes' => array_values($fuentes)];
}

/** La fuente donde se crean registros nuevos (la empresa del cliente si autorizó; si no, el estudio o la propia empresa). */
function lsoc_fuente_alta(array $amb): array
{
    $f = null;
    foreach ($amb['fuentes'] as $x) {
        if ($x['origen'] === 'CLIENTE_ERP') {
            $f = $x;
            break;
        }
    }
    $f = $f ?? ($amb['fuentes'][0] ?? null);
    if (!$f) {
        throw new RuntimeException('Elegí un cliente.');
    }
    if (!$f['escritura']) {
        throw new RuntimeException($f['aviso'] ?: 'Sin permiso para cargar en estos libros.');
    }
    return $f;
}

/** Condición SQL del ámbito sobre el alias dado. Devuelve [sql, tipos, params]. */
function lsoc_sql_ambito(array $amb, string $alias): array
{
    if (empty($amb['fuentes'])) {
        return ['1 = 0', '', []];
    }
    $partes = [];
    $tipos = '';
    $params = [];
    foreach ($amb['fuentes'] as $f) {
        $partes[] = "($alias.empresa_id = ? AND $alias.estudio_cliente_id = ?)";
        $tipos .= 'ii';
        $params[] = (int) $f['empresa_id'];
        $params[] = (int) $f['estudio_cliente_id'];
    }
    return ['(' . implode(' OR ', $partes) . ')', $tipos, $params];
}

/** Agrega a cada fila cliente, cliente_ref (cliente del estudio), origen y si se puede modificar. */
function lsoc_etiquetar(array $filas, array $amb): array
{
    $mapa = [];
    foreach ($amb['fuentes'] as $f) {
        $mapa[$f['empresa_id'] . '|' . $f['estudio_cliente_id']] = $f;
    }
    foreach ($filas as &$x) {
        $f = $mapa[$x['empresa_id'] . '|' . $x['estudio_cliente_id']] ?? null;
        $x['cliente'] = $f['cliente'] ?? '';
        $x['cliente_ref'] = (int) ($f['cliente_id'] ?? 0);
        $x['origen'] = $f['origen'] ?? 'EMPRESA';
        $x['editable'] = (bool) ($f['escritura'] ?? false);
    }
    unset($x);
    return $filas;
}

/**
 * Fuente de un registro existente y control de acceso. En Gestión: solo los de la empresa. En el estudio: los guardados
 * en el estudio (de sus clientes) o los de la empresa de un cliente que lo autorizó. Lanza RuntimeException.
 */
function lsoc_fuente_de_registro(mysqli $c, int $ctx_emp, string $modo, array $fila, bool $escritura): array
{
    $emp = (int) $fila['empresa_id'];
    $ecid = (int) $fila['estudio_cliente_id'];
    if ($modo !== 'estudio') {
        if ($emp !== $ctx_emp || $ecid !== 0) {
            throw new RuntimeException('El registro no existe.');
        }
        return lsoc_fuente_empresa($ctx_emp);
    }
    if ($emp === $ctx_emp && $ecid > 0) {
        $cli = lsoc_clientes_estudio($c, $ctx_emp, $ecid)[0] ?? null;
        if (!$cli) {
            throw new RuntimeException('El registro no existe.');
        }
        return lsoc_fuente_local($ctx_emp, $cli);
    }
    if ($ecid === 0) {
        $clis = lsoc_filas($c, "SELECT cliente_id, razon_social, cuit, empresa_vinculada_id FROM estudio__clientes
                                WHERE empresa_id = ? AND empresa_vinculada_id = ? ORDER BY tabla_estado_registro_id, cliente_id",
                           "ii", [$ctx_emp, $emp]);
        foreach ($clis as $cli) {
            $f = lsoc_fuente_cliente($c, $ctx_emp, $cli);
            if ($f['origen'] === 'CLIENTE_ERP') {
                if ($escritura && !$f['escritura']) {
                    throw new RuntimeException($f['aviso']);
                }
                return $f;
            }
        }
    }
    throw new RuntimeException('El registro no existe.');
}

function lsoc_libro_por_id(mysqli $c, int $libro_id, bool $bloquear = false): ?array
{
    return lsoc_fila($c, "SELECT * FROM gestion__soc_libros WHERE libro_id = ?" . ($bloquear ? ' FOR UPDATE' : ''), "i", [$libro_id]);
}

function lsoc_acta_por_id(mysqli $c, int $acta_id, bool $bloquear = false): ?array
{
    return lsoc_fila($c, "SELECT * FROM gestion__soc_actas WHERE acta_id = ?" . ($bloquear ? ' FOR UPDATE' : ''), "i", [$acta_id]);
}

/** Libro con control de acceso (null si no existe). */
function lsoc_libro_obtener(mysqli $c, int $ctx_emp, string $modo, int $libro_id): ?array
{
    $l = lsoc_libro_por_id($c, $libro_id);
    if (!$l) {
        return null;
    }
    $f = lsoc_fuente_de_registro($c, $ctx_emp, $modo, $l, false);
    return lsoc_etiquetar([$l], ['fuentes' => [$f]])[0];
}

// --------------------------------------------------------------------------------------------- combos

function lsoc_catalogos(): array
{
    $tipos_acto = [];
    foreach (LSOC_TIPOS_ACTO as $k => [$n, $libros]) {
        $tipos_acto[] = ['codigo' => $k, 'nombre' => $n, 'libros' => $libros];
    }
    $materias = [];
    foreach (LSOC_MATERIAS as $k => [$n, $insc]) {
        $materias[] = ['codigo' => $k, 'nombre' => $n, 'inscribible' => $insc];
    }
    return [
        'tipos_libro' => LSOC_TIPOS_LIBRO, 'tipos_acto' => $tipos_acto, 'materias' => $materias,
        'caracteres' => LSOC_CARACTERES, 'estados_libro' => LSOC_ESTADOS_LIBRO, 'estados_inscripcion' => LSOC_ESTADOS_INSCRIPCION,
    ];
}

function lsoc_combos(mysqli $c, int $empresa_id, string $modo): array
{
    $d = lsoc_catalogos();
    $d['modo'] = $modo;
    $d['clientes'] = [];
    if ($modo === 'estudio') {
        foreach (lsoc_clientes_estudio($c, $empresa_id) as $cli) {
            $f = lsoc_fuente_cliente($c, $empresa_id, $cli);
            $d['clientes'][] = ['cliente_id' => (int) $cli['cliente_id'], 'razon_social' => $cli['razon_social'], 'cuit' => $cli['cuit'],
                                'origen' => $f['origen'], 'escritura' => $f['escritura']];
        }
    }
    return $d;
}

// --------------------------------------------------------------------------------------------- libros

function lsoc_libro_nombre(array $l): string
{
    $n = $l['denominacion'] ?: (LSOC_TIPOS_LIBRO[$l['tipo_libro']] ?? $l['tipo_libro']);
    return $n . ' N° ' . (int) $l['numero_libro'];
}

function lsoc_libros_listar(mysqli $c, array $amb, bool $incluir_inactivos = false): array
{
    [$w, $t, $p] = lsoc_sql_ambito($amb, 'l');
    $sql = "SELECT l.*, ce.empresa AS cargado_por,
                   (SELECT MAX(a.folio_hasta) FROM gestion__soc_actas a WHERE a.libro_id = l.libro_id) AS ultimo_folio,
                   (SELECT MAX(a.numero_acta) FROM gestion__soc_actas a WHERE a.libro_id = l.libro_id) AS ultimo_numero,
                   (SELECT MAX(a.f_acto) FROM gestion__soc_actas a WHERE a.libro_id = l.libro_id AND a.tabla_estado_registro_id = 1) AS ultimo_acto,
                   (SELECT COUNT(*) FROM gestion__soc_actas a WHERE a.libro_id = l.libro_id) AS actas
            FROM gestion__soc_libros l
            LEFT JOIN conf__empresas ce ON ce.empresa_id = l.cargado_por_empresa_id
            WHERE $w" . ($incluir_inactivos ? '' : ' AND l.tabla_estado_registro_id = 1') . "
            ORDER BY l.tipo_libro, l.numero_libro";
    $filas = lsoc_etiquetar(lsoc_filas($c, $sql, $t, $p), $amb);
    foreach ($filas as &$f) {
        $f['nombre'] = lsoc_libro_nombre($f);
        $f['tipo_nombre'] = LSOC_TIPOS_LIBRO[$f['tipo_libro']] ?? $f['tipo_libro'];
        $f['estado_nombre'] = LSOC_ESTADOS_LIBRO[$f['estado_libro']] ?? $f['estado_libro'];
        $total = ($f['medio'] === 'PAPEL' && $f['folio_hasta']) ? ((int) $f['folio_hasta'] - (int) $f['folio_desde'] + 1) : 0;
        $usados = ($total && $f['ultimo_folio']) ? ((int) $f['ultimo_folio'] - (int) $f['folio_desde'] + 1) : 0;
        $f['folios_total'] = $total;
        $f['folios_usados'] = max(0, $usados);
        $f['folios_libres'] = $total ? max(0, $total - $usados) : null;
    }
    unset($f);
    usort($filas, fn($a, $b) => strcmp($a['cliente'], $b['cliente']) ?: strcmp($a['tipo_libro'], $b['tipo_libro'])
                                ?: ((int) $a['numero_libro'] <=> (int) $b['numero_libro']));
    return $filas;
}

function lsoc_libro_guardar(mysqli $c, int $ctx_emp, int $usuario_id, string $modo, array $in): array
{
    $id = (int) ($in['libro_id'] ?? 0);
    if ($id > 0) {
        $actual = lsoc_libro_por_id($c, $id);
        if (!$actual) {
            return lsoc_resp(false, 'El libro no existe.');
        }
        $fu = lsoc_fuente_de_registro($c, $ctx_emp, $modo, $actual, true);
    } else {
        $fu = lsoc_fuente_alta(lsoc_ambito($c, $ctx_emp, $modo, $in['cliente_id'] ?? 0, true));
    }
    $emp = (int) $fu['empresa_id'];
    $ecid = (int) $fu['estudio_cliente_id'];

    $tipo = (string) ($in['tipo_libro'] ?? '');
    if (!isset(LSOC_TIPOS_LIBRO[$tipo])) {
        return lsoc_resp(false, 'Elegí el tipo de libro.');
    }
    $numero = max(1, (int) ($in['numero_libro'] ?? 1));
    $medio = ($in['medio'] ?? 'PAPEL') === 'DIGITAL' ? 'DIGITAL' : 'PAPEL';
    $estado = (string) ($in['estado_libro'] ?? 'EN_USO');
    if (!isset(LSOC_ESTADOS_LIBRO[$estado])) {
        $estado = 'EN_USO';
    }
    $folio_desde = $medio === 'PAPEL' ? max(1, (int) ($in['folio_desde'] ?? 1)) : null;
    $folio_hasta = $medio === 'PAPEL' ? lsoc_entero_o_null($in['folio_hasta'] ?? '') : null;
    if ($medio === 'PAPEL' && (!$folio_hasta || $folio_hasta < $folio_desde)) {
        return lsoc_resp(false, 'Indicá los folios del libro (el último folio tiene que ser mayor o igual al primero).');
    }
    $rubrica_fecha = lsoc_fecha($in['rubrica_fecha'] ?? '');
    $f_inicio = lsoc_fecha($in['f_inicio_uso'] ?? '');
    $f_cierre = lsoc_fecha($in['f_cierre'] ?? '');
    if (in_array($estado, ['COMPLETO', 'CERRADO'], true) && !$f_cierre) {
        $f_cierre = date('Y-m-d');
    }

    // Un solo libro vigente con el mismo tipo y número por fuente
    $dup = lsoc_fila($c, "SELECT libro_id FROM gestion__soc_libros
                          WHERE empresa_id = ? AND estudio_cliente_id = ? AND tipo_libro = ? AND numero_libro = ?
                            AND tabla_estado_registro_id = 1 AND libro_id <> ?",
                     "iisii", [$emp, $ecid, $tipo, $numero, $id]);
    if ($dup) {
        return lsoc_resp(false, 'Ya hay un libro "' . LSOC_TIPOS_LIBRO[$tipo] . '" con el número ' . $numero . '.');
    }

    if ($id > 0 && $medio === 'PAPEL') {
        // Los folios del libro tienen que cubrir los de las actas ya transcriptas
        $r = lsoc_fila($c, "SELECT MIN(folio_desde) AS mn, MAX(folio_hasta) AS mx FROM gestion__soc_actas WHERE libro_id = ?", "i", [$id]);
        if ($r && $r['mn'] !== null && ((int) $r['mn'] < $folio_desde || (int) $r['mx'] > $folio_hasta)) {
            return lsoc_resp(false, "Hay actas transcriptas entre los folios {$r['mn']} y {$r['mx']}: el libro tiene que incluirlos.");
        }
    }

    $denominacion = lsoc_texto($in['denominacion'] ?? '', 120);
    $organismo = lsoc_texto($in['organismo'] ?? '', 80);
    $rubrica_numero = lsoc_texto($in['rubrica_numero'] ?? '', 60);
    $ubicacion = lsoc_texto($in['ubicacion'] ?? '', 150);
    $obs = lsoc_texto($in['observaciones'] ?? '');

    if ($id > 0) {
        lsoc_ejecutar($c, "UPDATE gestion__soc_libros SET tipo_libro = ?, numero_libro = ?, denominacion = ?, medio = ?, organismo = ?,
                               rubrica_numero = ?, rubrica_fecha = ?, folio_desde = ?, folio_hasta = ?, f_inicio_uso = ?, f_cierre = ?,
                               estado_libro = ?, ubicacion = ?, observaciones = ?
                           WHERE libro_id = ? AND empresa_id = ?",
            "sisssssiisssssii",
            [$tipo, $numero, $denominacion, $medio, $organismo, $rubrica_numero, $rubrica_fecha, $folio_desde, $folio_hasta,
             $f_inicio, $f_cierre, $estado, $ubicacion, $obs, $id, $emp]);
        return lsoc_resp(true, 'Libro actualizado.', ['libro_id' => $id]);
    }
    $cargado_por = $emp !== $ctx_emp ? $ctx_emp : null;
    $nuevo = lsoc_ejecutar($c, "INSERT INTO gestion__soc_libros (empresa_id, estudio_cliente_id, tipo_libro, numero_libro, denominacion, medio,
                                    organismo, rubrica_numero, rubrica_fecha, folio_desde, folio_hasta, f_inicio_uso, f_cierre, estado_libro,
                                    ubicacion, observaciones, tabla_estado_registro_id, usuario_id, cargado_por_empresa_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)",
        "iisisssssiisssssii",
        [$emp, $ecid, $tipo, $numero, $denominacion, $medio, $organismo, $rubrica_numero, $rubrica_fecha,
         $folio_desde, $folio_hasta, $f_inicio, $f_cierre, $estado, $ubicacion, $obs, $usuario_id, $cargado_por]);
    return lsoc_resp(true, $cargado_por ? 'Libro cargado en los libros de la empresa del cliente.' : 'Libro cargado.', ['libro_id' => $nuevo]);
}

/** Baja lógica: solo sin actas en el libro (si las tiene, se marca Cerrado / Completo). */
function lsoc_libro_inhabilitar(mysqli $c, int $ctx_emp, string $modo, int $libro_id): array
{
    $l = lsoc_libro_por_id($c, $libro_id);
    if (!$l) {
        return lsoc_resp(false, 'El libro no existe.');
    }
    lsoc_fuente_de_registro($c, $ctx_emp, $modo, $l, true);
    $n = lsoc_fila($c, "SELECT COUNT(*) AS n FROM gestion__soc_actas WHERE libro_id = ?", "i", [$libro_id]);
    if ((int) ($n['n'] ?? 0) > 0) {
        return lsoc_resp(false, 'El libro tiene actas transcriptas: no se da de baja. Cambiá su estado a Cerrado, Completo o Extraviado.');
    }
    lsoc_ejecutar($c, "UPDATE gestion__soc_libros SET tabla_estado_registro_id = 2 WHERE libro_id = ? AND empresa_id = ?",
                  "ii", [$libro_id, (int) $l['empresa_id']]);
    return lsoc_resp(true, 'Libro dado de baja.');
}

// --------------------------------------------------------------------------------------------- actas

function lsoc_materias_texto(?string $materias): string
{
    $out = [];
    foreach (array_filter(explode(',', (string) $materias)) as $m) {
        $out[] = LSOC_MATERIAS[$m][0] ?? $m;
    }
    return implode(', ', $out);
}

function lsoc_actas_listar(mysqli $c, array $amb, array $f): array
{
    [$w, $t, $p] = lsoc_sql_ambito($amb, 'a');
    if (empty($f['incluir_anuladas'])) {
        $w .= ' AND a.tabla_estado_registro_id = 1';
    }
    if (!empty($f['tipo_acto']) && isset(LSOC_TIPOS_ACTO[$f['tipo_acto']])) {
        $w .= ' AND a.tipo_acto = ?';
        $t .= 's';
        $p[] = (string) $f['tipo_acto'];
    }
    if (!empty($f['estado'])) {
        if (in_array($f['estado'], ['BORRADOR', 'TRANSCRIPTA'], true)) {
            $w .= ' AND a.estado_acta = ?';
            $t .= 's';
            $p[] = (string) $f['estado'];
        } elseif ($f['estado'] === 'INSC_PENDIENTE') {
            $w .= " AND a.inscripcion_estado IN ('PENDIENTE','EN_TRAMITE','OBSERVADA')";
        }
    }
    if (!empty($f['materia']) && isset(LSOC_MATERIAS[$f['materia']])) {
        $w .= ' AND FIND_IN_SET(?, a.materias) > 0';
        $t .= 's';
        $p[] = (string) $f['materia'];
    }
    if (!empty($f['desde'])) {
        $w .= ' AND a.f_acto >= ?';
        $t .= 's';
        $p[] = lsoc_fecha($f['desde']);
    }
    if (!empty($f['hasta'])) {
        $w .= ' AND a.f_acto <= ?';
        $t .= 's';
        $p[] = lsoc_fecha($f['hasta']);
    }
    $sql = "SELECT a.acta_id, a.empresa_id, a.estudio_cliente_id, a.tipo_acto, a.f_acto, a.materias, a.estado_acta, a.libro_id, a.numero_acta,
                   a.folio_desde, a.folio_hasta, a.f_transcripcion, a.inscripcion_estado, a.inscripcion_tramite, a.f_inscripcion,
                   a.archivo_nombre, a.mandato_hasta, a.ejercicio_cierre, a.tabla_estado_registro_id, a.motivo_anulacion,
                   LEFT(COALESCE(a.resoluciones, a.orden_del_dia, ''), 200) AS resumen,
                   l.tipo_libro, l.numero_libro, l.denominacion, ce.empresa AS cargado_por
            FROM gestion__soc_actas a
            LEFT JOIN gestion__soc_libros l ON l.libro_id = a.libro_id AND l.empresa_id = a.empresa_id
            LEFT JOIN conf__empresas ce ON ce.empresa_id = a.cargado_por_empresa_id
            WHERE $w
            ORDER BY a.f_acto DESC, a.acta_id DESC
            LIMIT 2000";
    $filas = lsoc_etiquetar(lsoc_filas($c, $sql, $t, $p), $amb);
    foreach ($filas as &$a) {
        $a['tipo_acto_nombre'] = LSOC_TIPOS_ACTO[$a['tipo_acto']][0] ?? $a['tipo_acto'];
        $a['materias_nombre'] = lsoc_materias_texto($a['materias']);
        $a['libro_nombre'] = $a['libro_id'] ? lsoc_libro_nombre($a) : '';
        $a['inscripcion_nombre'] = LSOC_ESTADOS_INSCRIPCION[$a['inscripcion_estado']] ?? $a['inscripcion_estado'];
        $a['anulada'] = (int) $a['tabla_estado_registro_id'] === 38;
        $a['tiene_archivo'] = !empty($a['archivo_nombre']);
    }
    unset($a);
    return $filas;
}

function lsoc_acta_detalle(mysqli $c, int $ctx_emp, string $modo, int $acta_id): ?array
{
    $a = lsoc_acta_por_id($c, $acta_id);
    if (!$a) {
        return null;
    }
    $fu = lsoc_fuente_de_registro($c, $ctx_emp, $modo, $a, false);
    $a = lsoc_etiquetar([$a], ['fuentes' => [$fu]])[0];
    $emp = (int) $a['empresa_id'];
    unset($a['archivo_ruta']);
    $a['personas'] = lsoc_filas($c, "SELECT persona_id, nombre, documento, caracter, cargo_designado, participacion, participacion_pct, firma
                                     FROM gestion__soc_actas_personas WHERE acta_id = ? AND empresa_id = ? ORDER BY orden, persona_id",
                                "ii", [$acta_id, $emp]);
    $a['materias_lista'] = array_values(array_filter(explode(',', (string) $a['materias'])));
    $a['tipo_acto_nombre'] = LSOC_TIPOS_ACTO[$a['tipo_acto']][0] ?? $a['tipo_acto'];
    if ($a['libro_id']) {
        $l = lsoc_libro_por_id($c, (int) $a['libro_id']);
        $a['libro_nombre'] = $l ? lsoc_libro_nombre($l) : '';
    }
    $a['cargado_por'] = '';
    if (!empty($a['cargado_por_empresa_id'])) {
        $e = lsoc_fila($c, "SELECT empresa FROM conf__empresas WHERE empresa_id = ?", "i", [(int) $a['cargado_por_empresa_id']]);
        $a['cargado_por'] = $e['empresa'] ?? '';
    }
    return $a;
}

/** Alta / edición del contenido del acta (solo en Borrador; transcripta solo cambian observaciones). */
function lsoc_acta_guardar(mysqli $c, int $ctx_emp, int $usuario_id, string $modo, array $in): array
{
    $id = (int) ($in['acta_id'] ?? 0);
    $actual = null;
    if ($id > 0) {
        $actual = lsoc_acta_por_id($c, $id);
        if (!$actual) {
            return lsoc_resp(false, 'El acta no existe.');
        }
        $fu = lsoc_fuente_de_registro($c, $ctx_emp, $modo, $actual, true);
        if ((int) $actual['tabla_estado_registro_id'] !== 1) {
            return lsoc_resp(false, 'El acta está anulada.');
        }
        if ($actual['estado_acta'] === 'TRANSCRIPTA') {
            // El texto ya está en el libro: solo cambian las observaciones (para corregir, Volver a borrador)
            lsoc_ejecutar($c, "UPDATE gestion__soc_actas SET observaciones = ? WHERE acta_id = ? AND empresa_id = ?",
                          "sii", [lsoc_texto($in['observaciones'] ?? ''), $id, (int) $actual['empresa_id']]);
            return lsoc_resp(true, 'Observaciones guardadas. El acta ya está transcripta: para cambiar su contenido, volvela a borrador.',
                             ['acta_id' => $id]);
        }
    } else {
        $fu = lsoc_fuente_alta(lsoc_ambito($c, $ctx_emp, $modo, $in['cliente_id'] ?? 0, true));
    }
    $emp = (int) $fu['empresa_id'];
    $ecid = (int) $fu['estudio_cliente_id'];

    $tipo = (string) ($in['tipo_acto'] ?? '');
    if (!isset(LSOC_TIPOS_ACTO[$tipo])) {
        return lsoc_resp(false, 'Elegí el tipo de acto.');
    }
    $f_acto = lsoc_fecha($in['f_acto'] ?? '');
    if (!$f_acto) {
        return lsoc_resp(false, 'Indicá la fecha del acto.');
    }
    $hora = trim((string) ($in['hora_acto'] ?? ''));
    $hora = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora) ? $hora : null;
    $f_conv = lsoc_fecha($in['f_convocatoria'] ?? '');
    if ($f_conv && $f_conv > $f_acto) {
        return lsoc_resp(false, 'La convocatoria no puede ser posterior al acto.');
    }
    $quorum = lsoc_decimal_o_null($in['quorum_pct'] ?? '');
    if ($quorum !== null && ($quorum < 0 || $quorum > 100)) {
        return lsoc_resp(false, 'El quórum es un porcentaje entre 0 y 100.');
    }
    $materias = $in['materias'] ?? [];
    if (!is_array($materias)) {
        $materias = explode(',', (string) $materias);
    }
    $materias = array_values(array_intersect(array_keys(LSOC_MATERIAS), array_map('strval', $materias)));
    $ejercicio = in_array('APROBACION_EECC', $materias, true) ? lsoc_fecha($in['ejercicio_cierre'] ?? '') : null;
    if (in_array('APROBACION_EECC', $materias, true) && !$ejercicio) {
        return lsoc_resp(false, 'Indicá la fecha de cierre del ejercicio cuyos estados contables se aprueban.');
    }
    $mandato = in_array('DESIGNACION_AUTORIDADES', $materias, true) ? lsoc_fecha($in['mandato_hasta'] ?? '') : null;
    if ($mandato && $mandato <= $f_acto) {
        return lsoc_resp(false, 'El vencimiento del mandato tiene que ser posterior al acto.');
    }

    $personas = lsoc_personas_validar($in['personas'] ?? []);
    $unanime = !empty($in['es_unanime']) ? 1 : 0;
    $lugar = lsoc_texto($in['lugar'] ?? '', 150);
    $odd = lsoc_texto($in['orden_del_dia'] ?? '');
    $res = lsoc_texto($in['resoluciones'] ?? '');
    $obs = lsoc_texto($in['observaciones'] ?? '');
    $mat = $materias ? implode(',', $materias) : null;
    $insc = $actual['inscripcion_estado'] ?? 'NO_REQUIERE';
    if (isset($in['requiere_inscripcion'])) {
        if (!empty($in['requiere_inscripcion']) && $insc === 'NO_REQUIERE') {
            $insc = 'PENDIENTE';
        } elseif (empty($in['requiere_inscripcion'])) {
            $insc = 'NO_REQUIERE';
        }
    }
    $cargado_por = $emp !== $ctx_emp ? $ctx_emp : null;

    mysqli_begin_transaction($c);
    try {
        if ($id > 0) {
            lsoc_ejecutar($c, "UPDATE gestion__soc_actas SET tipo_acto = ?, f_acto = ?, hora_acto = ?, lugar = ?, f_convocatoria = ?, es_unanime = ?,
                                   quorum_pct = ?, materias = ?, orden_del_dia = ?, resoluciones = ?, ejercicio_cierre = ?, mandato_hasta = ?,
                                   inscripcion_estado = ?, observaciones = ?
                               WHERE acta_id = ? AND empresa_id = ?",
                "sssssidsssssssii",
                [$tipo, $f_acto, $hora, $lugar, $f_conv, $unanime, $quorum, $mat, $odd, $res, $ejercicio, $mandato, $insc, $obs, $id, $emp]);
        } else {
            $id = lsoc_ejecutar($c, "INSERT INTO gestion__soc_actas (empresa_id, estudio_cliente_id, tipo_acto, f_acto, hora_acto, lugar, f_convocatoria,
                                         es_unanime, quorum_pct, materias, orden_del_dia, resoluciones, ejercicio_cierre, mandato_hasta,
                                         estado_acta, inscripcion_estado, observaciones, tabla_estado_registro_id, usuario_id, cargado_por_empresa_id)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'BORRADOR', ?, ?, 1, ?, ?)",
                "iisssssidsssssssii",
                [$emp, $ecid, $tipo, $f_acto, $hora, $lugar, $f_conv, $unanime, $quorum, $mat, $odd, $res,
                 $ejercicio, $mandato, $insc, $obs, $usuario_id, $cargado_por]);
        }
        lsoc_ejecutar($c, "DELETE FROM gestion__soc_actas_personas WHERE acta_id = ? AND empresa_id = ?", "ii", [$id, $emp]);
        foreach ($personas as $i => $pp) {
            lsoc_ejecutar($c, "INSERT INTO gestion__soc_actas_personas (empresa_id, acta_id, nombre, documento, caracter, cargo_designado,
                                   participacion, participacion_pct, firma, orden)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                "iissssddii",
                [$emp, $id, $pp['nombre'], $pp['documento'], $pp['caracter'], $pp['cargo_designado'],
                 $pp['participacion'], $pp['participacion_pct'], $pp['firma'], $i + 1]);
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    $msg = ($cargado_por && !$actual) ? 'Acta guardada en los libros de la empresa del cliente.' : 'Acta guardada.';
    return lsoc_resp(true, $msg, ['acta_id' => $id]);
}

function lsoc_personas_validar($personas): array
{
    if (is_string($personas)) {
        $personas = json_decode($personas, true) ?: [];
    }
    $out = [];
    foreach ((array) $personas as $p) {
        $nombre = lsoc_texto($p['nombre'] ?? '', 150);
        if (!$nombre) {
            continue;
        }
        $car = (string) ($p['caracter'] ?? 'OTRO');
        $pct = lsoc_decimal_o_null($p['participacion_pct'] ?? '');
        if ($pct !== null && ($pct < 0 || $pct > 100)) {
            throw new RuntimeException("El porcentaje de $nombre tiene que estar entre 0 y 100.");
        }
        $out[] = [
            'nombre' => $nombre,
            'documento' => lsoc_texto($p['documento'] ?? '', 20),
            'caracter' => isset(LSOC_CARACTERES[$car]) ? $car : 'OTRO',
            'cargo_designado' => lsoc_texto($p['cargo_designado'] ?? '', 80),
            'participacion' => lsoc_decimal_o_null($p['participacion'] ?? ''),
            'participacion_pct' => $pct,
            'firma' => !empty($p['firma']) ? 1 : 0,
        ];
    }
    if (count($out) > 200) {
        throw new RuntimeException('Demasiadas personas en el acta (máximo 200).');
    }
    return $out;
}

/** Próximo número de acta y folio sugeridos para un libro. */
function lsoc_sugerencia(mysqli $c, int $ctx_emp, string $modo, int $libro_id): array
{
    $l = lsoc_libro_por_id($c, $libro_id);
    if (!$l) {
        return lsoc_resp(false, 'El libro no existe.');
    }
    lsoc_fuente_de_registro($c, $ctx_emp, $modo, $l, false);
    $r = lsoc_fila($c, "SELECT MAX(numero_acta) AS n, MAX(folio_hasta) AS f FROM gestion__soc_actas WHERE libro_id = ?", "i", [$libro_id]);
    $numero = (int) ($r['n'] ?? 0) + 1;
    if ($numero === 1) {
        // Libro N° 2 en adelante: la numeración de actas sigue la del libro anterior del mismo tipo
        $prev = lsoc_fila($c, "SELECT MAX(a.numero_acta) AS n FROM gestion__soc_actas a
                               JOIN gestion__soc_libros l ON l.libro_id = a.libro_id AND l.empresa_id = a.empresa_id
                               WHERE l.empresa_id = ? AND l.estudio_cliente_id = ? AND l.tipo_libro = ? AND l.numero_libro < ?",
                          "iisi", [(int) $l['empresa_id'], (int) $l['estudio_cliente_id'], $l['tipo_libro'], (int) $l['numero_libro']]);
        $numero = (int) ($prev['n'] ?? 0) + 1;
    }
    $folio = $l['medio'] === 'PAPEL' ? max((int) $l['folio_desde'], (int) ($r['f'] ?? 0) + 1) : null;
    return lsoc_resp(true, 'OK', [
        'numero_acta' => $numero, 'folio_desde' => $folio, 'medio' => $l['medio'],
        'libro_folio_desde' => (int) $l['folio_desde'], 'libro_folio_hasta' => $l['folio_hasta'] !== null ? (int) $l['folio_hasta'] : null,
    ]);
}

/** Pasa el acta a Transcripta: libro, número y folios (sin superposición con las demás actas del libro, anuladas incluidas). */
function lsoc_acta_transcribir(mysqli $c, int $ctx_emp, string $modo, array $in): array
{
    $acta_id = (int) ($in['acta_id'] ?? 0);
    $libro_id = (int) ($in['libro_id'] ?? 0);
    $numero = (int) ($in['numero_acta'] ?? 0);
    $f_trans = lsoc_fecha($in['f_transcripcion'] ?? '') ?: date('Y-m-d');
    if ($numero <= 0) {
        return lsoc_resp(false, 'Indicá el número de acta.');
    }
    $completo = false;

    mysqli_begin_transaction($c);
    try {
        $l = lsoc_libro_por_id($c, $libro_id, true);   // serializa la numeración del libro
        $a = lsoc_acta_por_id($c, $acta_id, true);
        if (!$a || !$l) {
            throw new RuntimeException('El acta o el libro no existen.');
        }
        lsoc_fuente_de_registro($c, $ctx_emp, $modo, $a, true);
        if ((int) $l['empresa_id'] !== (int) $a['empresa_id'] || (int) $l['estudio_cliente_id'] !== (int) $a['estudio_cliente_id']) {
            throw new RuntimeException('El acta y el libro tienen que ser de la misma empresa (si el acta quedó en el estudio, pasala antes a la empresa).');
        }
        $emp = (int) $a['empresa_id'];
        if ((int) $a['tabla_estado_registro_id'] !== 1 || $a['estado_acta'] !== 'BORRADOR') {
            throw new RuntimeException('Solo se transcriben actas vigentes en borrador.');
        }
        if ((int) $l['tabla_estado_registro_id'] !== 1 || $l['estado_libro'] !== 'EN_USO') {
            throw new RuntimeException('El libro no está en uso.');
        }
        if ($f_trans < $a['f_acto']) {
            throw new RuntimeException('La transcripción no puede ser anterior al acto.');
        }
        if ($l['rubrica_fecha'] && $f_trans < $l['rubrica_fecha']) {
            throw new RuntimeException('El libro se rubricó después de esa fecha de transcripción.');
        }
        $dup = lsoc_fila($c, "SELECT acta_id FROM gestion__soc_actas WHERE libro_id = ? AND numero_acta = ? AND acta_id <> ?",
                         "iii", [$libro_id, $numero, $acta_id]);
        if ($dup) {
            throw new RuntimeException("Ya hay un acta N° $numero en ese libro.");
        }
        $fd = $fh = null;
        if ($l['medio'] === 'PAPEL') {
            $fd = (int) ($in['folio_desde'] ?? 0);
            $fh = (int) ($in['folio_hasta'] ?? 0);
            if ($fd <= 0 || $fh < $fd) {
                throw new RuntimeException('Indicá los folios (el último tiene que ser mayor o igual al primero).');
            }
            if ($fd < (int) $l['folio_desde'] || ($l['folio_hasta'] !== null && $fh > (int) $l['folio_hasta'])) {
                throw new RuntimeException("El libro va del folio {$l['folio_desde']} al {$l['folio_hasta']}.");
            }
            $sup = lsoc_fila($c, "SELECT numero_acta, folio_desde, folio_hasta FROM gestion__soc_actas
                                  WHERE libro_id = ? AND acta_id <> ? AND folio_desde IS NOT NULL
                                    AND NOT (folio_hasta < ? OR folio_desde > ?) LIMIT 1",
                             "iiii", [$libro_id, $acta_id, $fd, $fh]);
            if ($sup) {
                throw new RuntimeException("Los folios se superponen con el acta N° {$sup['numero_acta']} (folios {$sup['folio_desde']} a {$sup['folio_hasta']}).");
            }
        }
        lsoc_ejecutar($c, "UPDATE gestion__soc_actas SET estado_acta = 'TRANSCRIPTA', libro_id = ?, numero_acta = ?, folio_desde = ?,
                               folio_hasta = ?, f_transcripcion = ? WHERE acta_id = ? AND empresa_id = ?",
                      "iiiisii", [$libro_id, $numero, $fd, $fh, $f_trans, $acta_id, $emp]);
        $completo = $l['medio'] === 'PAPEL' && $l['folio_hasta'] !== null && $fh >= (int) $l['folio_hasta'];
        if ($completo) {
            lsoc_ejecutar($c, "UPDATE gestion__soc_libros SET estado_libro = 'COMPLETO', f_cierre = ? WHERE libro_id = ? AND empresa_id = ?",
                          "sii", [$f_trans, $libro_id, $emp]);
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        if ($e instanceof RuntimeException) {
            return lsoc_resp(false, $e->getMessage());
        }
        throw $e;
    }
    return lsoc_resp(true, $completo ? 'Acta transcripta. El libro quedó completo: dá de alta el siguiente.' : 'Acta transcripta.');
}

/** Deshace la transcripción (para corregir datos cargados mal). No si la inscripción ya está en trámite o inscripta. */
function lsoc_acta_volver_borrador(mysqli $c, int $ctx_emp, string $modo, int $acta_id): array
{
    $a = lsoc_acta_por_id($c, $acta_id);
    if (!$a) {
        return lsoc_resp(false, 'El acta no existe.');
    }
    lsoc_fuente_de_registro($c, $ctx_emp, $modo, $a, true);
    if ((int) $a['tabla_estado_registro_id'] !== 1 || $a['estado_acta'] !== 'TRANSCRIPTA') {
        return lsoc_resp(false, 'Solo vuelve a borrador un acta transcripta vigente.');
    }
    if (in_array($a['inscripcion_estado'], ['EN_TRAMITE', 'INSCRIPTA'], true)) {
        return lsoc_resp(false, 'La inscripción ya está en trámite o inscripta: no se puede volver a borrador.');
    }
    lsoc_ejecutar($c, "UPDATE gestion__soc_actas SET estado_acta = 'BORRADOR', libro_id = NULL, numero_acta = NULL, folio_desde = NULL,
                           folio_hasta = NULL, f_transcripcion = NULL WHERE acta_id = ? AND empresa_id = ?", "ii", [$acta_id, (int) $a['empresa_id']]);
    return lsoc_resp(true, 'El acta volvió a borrador.');
}

function lsoc_acta_inscripcion(mysqli $c, int $ctx_emp, string $modo, array $in): array
{
    $acta_id = (int) ($in['acta_id'] ?? 0);
    $a = lsoc_acta_por_id($c, $acta_id);
    if (!$a) {
        return lsoc_resp(false, 'El acta no existe.');
    }
    lsoc_fuente_de_registro($c, $ctx_emp, $modo, $a, true);
    if ((int) $a['tabla_estado_registro_id'] !== 1) {
        return lsoc_resp(false, 'El acta está anulada.');
    }
    $estado = (string) ($in['inscripcion_estado'] ?? '');
    if (!isset(LSOC_ESTADOS_INSCRIPCION[$estado])) {
        return lsoc_resp(false, 'Elegí el estado de la inscripción.');
    }
    $f_pres = lsoc_fecha($in['f_presentacion'] ?? '');
    $f_insc = lsoc_fecha($in['f_inscripcion'] ?? '');
    if (in_array($estado, ['EN_TRAMITE', 'OBSERVADA', 'INSCRIPTA'], true) && $a['estado_acta'] !== 'TRANSCRIPTA') {
        return lsoc_resp(false, 'Primero transcribí el acta al libro.');
    }
    if (in_array($estado, ['EN_TRAMITE', 'OBSERVADA'], true) && !$f_pres) {
        return lsoc_resp(false, 'Indicá la fecha de presentación.');
    }
    if ($estado === 'INSCRIPTA' && !$f_insc) {
        return lsoc_resp(false, 'Indicá la fecha de inscripción.');
    }
    if ($f_pres && $f_pres < $a['f_acto']) {
        return lsoc_resp(false, 'La presentación no puede ser anterior al acto.');
    }
    if ($f_pres && $f_insc && $f_insc < $f_pres) {
        return lsoc_resp(false, 'La inscripción no puede ser anterior a la presentación.');
    }
    lsoc_ejecutar($c, "UPDATE gestion__soc_actas SET inscripcion_estado = ?, inscripcion_organismo = ?, inscripcion_tramite = ?,
                           f_presentacion = ?, f_inscripcion = ?, inscripcion_datos = ? WHERE acta_id = ? AND empresa_id = ?",
        "ssssssii",
        [$estado, lsoc_texto($in['inscripcion_organismo'] ?? '', 80), lsoc_texto($in['inscripcion_tramite'] ?? '', 60),
         $f_pres, $f_insc, lsoc_texto($in['inscripcion_datos'] ?? '', 150), $acta_id, (int) $a['empresa_id']]);
    return lsoc_resp(true, 'Inscripción actualizada.');
}

/** Anula el acta con motivo. Si estaba transcripta conserva libro, número y folios (las fojas siguen usadas en el libro). */
function lsoc_acta_anular(mysqli $c, int $ctx_emp, int $usuario_id, string $modo, int $acta_id, string $motivo): array
{
    $motivo = trim($motivo);
    if ($motivo === '') {
        return lsoc_resp(false, 'Indicá el motivo de la anulación.');
    }
    $a = lsoc_acta_por_id($c, $acta_id);
    if (!$a) {
        return lsoc_resp(false, 'El acta no existe.');
    }
    lsoc_fuente_de_registro($c, $ctx_emp, $modo, $a, true);
    if ((int) $a['tabla_estado_registro_id'] !== 1) {
        return lsoc_resp(false, 'El acta ya está anulada.');
    }
    if ($a['inscripcion_estado'] === 'INSCRIPTA') {
        return lsoc_resp(false, 'El acta está inscripta en el registro: no se anula (se deja sin efecto con otra acta).');
    }
    lsoc_ejecutar($c, "UPDATE gestion__soc_actas SET tabla_estado_registro_id = 38, motivo_anulacion = ?, f_anulacion = NOW(),
                           usuario_anulacion_id = ? WHERE acta_id = ? AND empresa_id = ?",
                  "siii", [mb_substr($motivo, 0, 255), $usuario_id, $acta_id, (int) $a['empresa_id']]);
    return lsoc_resp(true, 'Acta anulada.');
}

// --------------------------------------------------------------------------------------------- archivo

/** Carpeta de las copias de una empresa (la crea y la protege). Devuelve [ruta absoluta, ruta relativa]. */
function lsoc_dir_empresa(int $empresa_id): array
{
    $base = dirname(__DIR__, 2) . '/' . LSOC_DIR_ARCHIVOS;
    $dir = $base . '/' . $empresa_id;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de actas.');
    }
    if (!file_exists($base . '/.htaccess')) {
        $ht = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
        if (file_put_contents($base . '/.htaccess', $ht) === false) {
            throw new RuntimeException('No se pudo proteger la carpeta de actas.');
        }
    }
    return [$dir, LSOC_DIR_ARCHIVOS . '/' . $empresa_id];
}

function lsoc_archivo_subir(mysqli $c, int $ctx_emp, string $modo, int $acta_id, array $file): array
{
    $a = lsoc_acta_por_id($c, $acta_id);
    if (!$a) {
        return lsoc_resp(false, 'El acta no existe.');
    }
    lsoc_fuente_de_registro($c, $ctx_emp, $modo, $a, true);
    $emp = (int) $a['empresa_id'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return lsoc_resp(false, 'No llegó el archivo.');
    }
    if ((int) $file['size'] > LSOC_MAX_ARCHIVO) {
        return lsoc_resp(false, 'El archivo supera los 15 MB.');
    }
    $nombre = basename((string) $file['name']);
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    if (!isset($mimes[$ext])) {
        return lsoc_resp(false, 'El archivo tiene que ser PDF o imagen (.jpg, .png).');
    }
    $mime = function_exists('mime_content_type') ? (string) @mime_content_type($file['tmp_name']) : $mimes[$ext];
    if ($mime !== $mimes[$ext]) {
        return lsoc_resp(false, 'El contenido del archivo no corresponde a su extensión.');
    }
    [$dir, $rel] = lsoc_dir_empresa($emp);
    $archivo = date('Ymd') . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$archivo")) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }
    lsoc_ejecutar($c, "UPDATE gestion__soc_actas SET archivo_ruta = ?, archivo_nombre = ? WHERE acta_id = ? AND empresa_id = ?",
                  "ssii", ["$rel/$archivo", mb_substr($nombre, 0, 255), $acta_id, $emp]);
    if (!empty($a['archivo_ruta']) && strpos($a['archivo_ruta'], $rel . '/') === 0) {
        @unlink(dirname(__DIR__, 2) . '/' . $a['archivo_ruta']);
    }
    return lsoc_resp(true, 'Archivo adjuntado.');
}

/** Envía el archivo del acta (descarga). Devuelve false si no existe. */
function lsoc_archivo_enviar(mysqli $c, int $ctx_emp, string $modo, int $acta_id): bool
{
    $a = lsoc_acta_por_id($c, $acta_id);
    if (!$a || empty($a['archivo_ruta'])) {
        return false;
    }
    lsoc_fuente_de_registro($c, $ctx_emp, $modo, $a, false);
    if (strpos($a['archivo_ruta'], LSOC_DIR_ARCHIVOS . '/' . (int) $a['empresa_id'] . '/') !== 0) {
        return false;
    }
    $ruta = dirname(__DIR__, 2) . '/' . $a['archivo_ruta'];
    if (!is_file($ruta)) {
        return false;
    }
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? 'application/octet-stream';
    while (ob_get_level()) {
        ob_end_clean();
    }
    $nombre = str_replace(['"', "\r", "\n"], '', (string) $a['archivo_nombre']);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($ruta));
    header('Content-Disposition: inline; filename="' . $nombre . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($ruta);
    return true;
}

// --------------------------------------------------------------------------------------------- pasar a la empresa

/**
 * Estudio: pasa los libros y actas que guardó para un cliente a los libros de la empresa del cliente, cuando esta
 * autorizó la escritura. Un libro con el mismo tipo y número que uno vigente de la empresa no se pasa (ni sus actas).
 */
function lsoc_pasar_a_empresa(mysqli $c, int $ctx_emp, int $usuario_id, int $cliente_id): array
{
    $cli = lsoc_clientes_estudio($c, $ctx_emp, $cliente_id)[0] ?? null;
    if (!$cli) {
        return lsoc_resp(false, 'El cliente no existe.');
    }
    $fu = lsoc_fuente_cliente($c, $ctx_emp, $cli);
    if ($fu['origen'] !== 'CLIENTE_ERP' || !$fu['escritura']) {
        return lsoc_resp(false, 'La empresa del cliente tiene que autorizar al estudio a cargar sus libros societarios (Vinculaciones, modo lectura y escritura).');
    }
    $erp = (int) $fu['empresa_id'];
    $cargado_por = $erp !== $ctx_emp ? $ctx_emp : null;
    $raiz = dirname(__DIR__, 2);
    $movidos = [];   // [origen, destino] para deshacer si falla
    $omitidos = [];
    $n_libros = $n_actas = 0;

    mysqli_begin_transaction($c);
    try {
        $libros = lsoc_filas($c, "SELECT * FROM gestion__soc_libros WHERE empresa_id = ? AND estudio_cliente_id = ? FOR UPDATE",
                             "ii", [$ctx_emp, $cliente_id]);
        $saltear = [];
        foreach ($libros as $l) {
            $choca = (int) $l['tabla_estado_registro_id'] === 1 && lsoc_fila($c,
                "SELECT libro_id FROM gestion__soc_libros WHERE empresa_id = ? AND estudio_cliente_id = 0 AND tipo_libro = ?
                   AND numero_libro = ? AND tabla_estado_registro_id = 1", "isi", [$erp, $l['tipo_libro'], (int) $l['numero_libro']]);
            if ($choca) {
                $saltear[(int) $l['libro_id']] = true;
                $omitidos[] = lsoc_libro_nombre($l);
                continue;
            }
            lsoc_ejecutar($c, "UPDATE gestion__soc_libros SET empresa_id = ?, estudio_cliente_id = 0, cargado_por_empresa_id = ?
                               WHERE libro_id = ? AND empresa_id = ?", "iiii", [$erp, $cargado_por, (int) $l['libro_id'], $ctx_emp]);
            $n_libros++;
        }
        $actas = lsoc_filas($c, "SELECT acta_id, libro_id, archivo_ruta FROM gestion__soc_actas
                                 WHERE empresa_id = ? AND estudio_cliente_id = ? FOR UPDATE", "ii", [$ctx_emp, $cliente_id]);
        [$dir_destino, $rel_destino] = lsoc_dir_empresa($erp);
        foreach ($actas as $a) {
            if ($a['libro_id'] && isset($saltear[(int) $a['libro_id']])) {
                continue;
            }
            $ruta = $a['archivo_ruta'];
            if ($ruta && $erp !== $ctx_emp && strpos($ruta, LSOC_DIR_ARCHIVOS . '/' . $ctx_emp . '/') === 0 && is_file("$raiz/$ruta")) {
                $nuevo = $dir_destino . '/' . basename($ruta);
                if (!rename("$raiz/$ruta", $nuevo)) {
                    throw new RuntimeException('No se pudo mover la copia de un acta.');
                }
                $movidos[] = ["$raiz/$ruta", $nuevo];
                $ruta = $rel_destino . '/' . basename($ruta);
            }
            lsoc_ejecutar($c, "UPDATE gestion__soc_actas SET empresa_id = ?, estudio_cliente_id = 0, cargado_por_empresa_id = ?, archivo_ruta = ?
                               WHERE acta_id = ? AND empresa_id = ?", "iisii", [$erp, $cargado_por, $ruta, (int) $a['acta_id'], $ctx_emp]);
            lsoc_ejecutar($c, "UPDATE gestion__soc_actas_personas SET empresa_id = ? WHERE acta_id = ? AND empresa_id = ?",
                          "iii", [$erp, (int) $a['acta_id'], $ctx_emp]);
            $n_actas++;
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        foreach (array_reverse($movidos) as [$orig, $dest]) {
            @rename($dest, $orig);
        }
        throw $e;
    }
    error_log("=== lsoc_pasar_a_empresa cliente $cliente_id -> empresa $erp: $n_libros libros, $n_actas actas, usuario $usuario_id ===");
    $msg = "Se pasaron $n_libros libro(s) y $n_actas acta(s) a los libros de la empresa del cliente.";
    if ($omitidos) {
        $msg .= ' No se pasaron (la empresa ya tiene un libro con ese tipo y número): ' . implode(', ', $omitidos) . '.';
    }
    return lsoc_resp(true, $msg, ['libros' => $n_libros, 'actas' => $n_actas, 'omitidos' => $omitidos]);
}

// --------------------------------------------------------------------------------------------- autoridades y alertas

/**
 * Autoridades vigentes por fuente: las personas con cargo designado del acta vigente más reciente
 * (transcripta o no) que trató la designación de autoridades, con su mandato.
 */
function lsoc_autoridades(mysqli $c, array $amb): array
{
    [$w, $t, $p] = lsoc_sql_ambito($amb, 'a');
    $actas = lsoc_etiquetar(lsoc_filas($c, "SELECT a.acta_id, a.empresa_id, a.estudio_cliente_id, a.f_acto, a.mandato_hasta, a.estado_acta,
                                    a.inscripcion_estado, a.tipo_acto, a.numero_acta
                             FROM gestion__soc_actas a
                             WHERE $w AND a.tabla_estado_registro_id = 1 AND FIND_IN_SET('DESIGNACION_AUTORIDADES', a.materias) > 0
                               AND NOT EXISTS (SELECT 1 FROM gestion__soc_actas b
                                               WHERE b.empresa_id = a.empresa_id AND b.estudio_cliente_id = a.estudio_cliente_id
                                                 AND b.tabla_estado_registro_id = 1
                                                 AND FIND_IN_SET('DESIGNACION_AUTORIDADES', b.materias) > 0
                                                 AND (b.f_acto > a.f_acto OR (b.f_acto = a.f_acto AND b.acta_id > a.acta_id)))", $t, $p), $amb);
    $out = [];
    $hoy = date('Y-m-d');
    foreach ($actas as $a) {
        $pers = lsoc_filas($c, "SELECT nombre, documento, caracter, cargo_designado FROM gestion__soc_actas_personas
                                WHERE acta_id = ? AND empresa_id = ? AND cargo_designado IS NOT NULL AND cargo_designado <> ''
                                ORDER BY orden", "ii", [(int) $a['acta_id'], (int) $a['empresa_id']]);
        foreach ($pers as $pp) {
            $out[] = $pp + [
                'acta_id' => (int) $a['acta_id'], 'cliente' => $a['cliente'], 'cliente_ref' => $a['cliente_ref'],
                'f_acto' => $a['f_acto'], 'mandato_hasta' => $a['mandato_hasta'], 'numero_acta' => $a['numero_acta'],
                'tipo_acto_nombre' => LSOC_TIPOS_ACTO[$a['tipo_acto']][0] ?? $a['tipo_acto'],
                'inscripcion_nombre' => LSOC_ESTADOS_INSCRIPCION[$a['inscripcion_estado']] ?? '',
                'vencido' => $a['mandato_hasta'] && $a['mandato_hasta'] < $hoy,
            ];
        }
    }
    usort($out, fn($x, $y) => strcmp($x['cliente'], $y['cliente']));
    return $out;
}

/** Fin del último ejercicio cerrado antes de hoy, según el mes de cierre. */
function lsoc_ultimo_cierre(int $mes_cierre, string $hoy): ?string
{
    if ($mes_cierre < 1 || $mes_cierre > 12) {
        return null;
    }
    $anio = (int) substr($hoy, 0, 4);
    $fin = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $mes_cierre)));
    if ($fin >= $hoy) {
        $fin = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio - 1, $mes_cierre)));
    }
    return $fin;
}

/** Mes de cierre del ejercicio de una fuente: Parámetros de Empresa, o el padrón del cliente si los libros están en el estudio. */
function lsoc_mes_cierre(mysqli $c, array $fu, string $hoy): int
{
    if ((int) $fu['estudio_cliente_id'] > 0) {
        $f = lsoc_fila($c, "SELECT mes_cierre_ejercicio FROM estudio__clientes WHERE cliente_id = ? AND empresa_id = ?",
                       "ii", [(int) $fu['estudio_cliente_id'], (int) $fu['empresa_id']]);
        return (int) ($f['mes_cierre_ejercicio'] ?? 0);
    }
    $cp = dirname(__DIR__) . '/cont_periodos.php';
    if (is_file($cp)) {
        require_once $cp;
        if (function_exists('cont_mes_cierre')) {
            try {
                return (int) cont_mes_cierre($c, (int) $fu['empresa_id'], $hoy);
            } catch (Throwable $e) {
                error_log('lsoc_mes_cierre: ' . $e->getMessage());
            }
        }
    }
    return 12;
}

/**
 * Indicadores y avisos. Solo se controlan los clientes (o la empresa) con al menos un libro activo, para no avisar a
 * quien no lleva libros societarios. Con un cliente elegido en el estudio devuelve además de dónde salen sus libros.
 */
function lsoc_resumen(mysqli $c, array $amb): array
{
    $hoy = date('Y-m-d');
    $libros = lsoc_libros_listar($c, $amb);
    [$w, $t, $p] = lsoc_sql_ambito($amb, 'a');
    $k = lsoc_fila($c, "SELECT SUM(a.estado_acta = 'BORRADOR') AS borradores,
                               SUM(a.inscripcion_estado IN ('PENDIENTE','EN_TRAMITE','OBSERVADA')) AS inscripciones
                        FROM gestion__soc_actas a WHERE $w AND a.tabla_estado_registro_id = 1", $t, $p);
    $alertas = [];

    $grupos = [];   // cliente del estudio (0 = la empresa) con libros
    foreach ($libros as $l) {
        $grupos[(int) $l['cliente_ref']] = true;
        if ($l['estado_libro'] === 'EN_USO' && $l['folios_total'] > 0 && $l['folios_libres'] !== null
            && $l['folios_libres'] <= max(5, (int) ceil($l['folios_total'] * 0.1))) {
            $alertas[] = ['nivel' => 'warning', 'cliente' => $l['cliente'], 'cliente_id' => $l['cliente_ref'],
                          'texto' => $l['nombre'] . ': quedan ' . $l['folios_libres'] . ' folios. Conviene rubricar el siguiente.'];
        }
    }
    foreach (array_keys($grupos) as $cref) {
        $fcs = array_values(array_filter($amb['fuentes'], fn($x) => (int) $x['cliente_id'] === $cref));
        if (!$fcs) {
            continue;
        }
        $fu = $fcs[0];
        foreach ($fcs as $x) {
            if ($x['origen'] === 'CLIENTE_ERP') {
                $fu = $x;
            }
        }
        [$wc, $tc, $pc] = lsoc_sql_ambito(['fuentes' => $fcs], 'a');
        $base = ['cliente' => $fu['cliente'], 'cliente_id' => $cref];
        // Mandato de autoridades
        $m = lsoc_fila($c, "SELECT a.mandato_hasta FROM gestion__soc_actas a
                            WHERE $wc AND a.tabla_estado_registro_id = 1 AND FIND_IN_SET('DESIGNACION_AUTORIDADES', a.materias) > 0
                            ORDER BY a.f_acto DESC, a.acta_id DESC LIMIT 1", $tc, $pc);
        if ($m && $m['mandato_hasta']) {
            $dias = (int) floor((strtotime($m['mandato_hasta']) - strtotime($hoy)) / 86400);
            if ($dias < 0) {
                $alertas[] = $base + ['nivel' => 'danger', 'fecha' => $m['mandato_hasta'], 'tipo' => 'MANDATO',
                                      'texto' => 'El mandato de las autoridades venció. Falta el acta de renovación.'];
            } elseif ($dias <= LSOC_DIAS_MANDATO) {
                $alertas[] = $base + ['nivel' => 'warning', 'fecha' => $m['mandato_hasta'], 'tipo' => 'MANDATO',
                                      'texto' => "El mandato de las autoridades vence en $dias días."];
            }
        }
        // Asamblea que aprueba los estados contables del último ejercicio cerrado (4 meses, art. 234 LGS)
        $fin = lsoc_ultimo_cierre(lsoc_mes_cierre($c, $fu, $hoy), $hoy);
        if ($fin) {
            $hay = lsoc_fila($c, "SELECT 1 AS x FROM gestion__soc_actas a
                                  WHERE $wc AND a.tabla_estado_registro_id = 1 AND FIND_IN_SET('APROBACION_EECC', a.materias) > 0
                                    AND a.ejercicio_cierre = ? LIMIT 1", $tc . 's', array_merge($pc, [$fin]));
            if (!$hay) {
                $plazo = date('Y-m-t', strtotime(date('Y-m-01', strtotime($fin)) . ' +' . LSOC_MESES_ASAMBLEA_EECC . ' months'));
                $alertas[] = $base + ['nivel' => $plazo < $hoy ? 'danger' : 'info', 'fecha' => $plazo, 'tipo' => 'EECC', 'ejercicio' => $fin,
                                      'texto' => 'Falta el acta que aprueba los estados contables del ejercicio cerrado el ' . fmt_fecha($fin)
                                               . ($plazo < $hoy ? '. El plazo venció.' : '. Plazo de la asamblea:')];
            }
        }
    }

    // Actas sin transcribir hace más de N días
    $viejas = lsoc_etiquetar(lsoc_filas($c, "SELECT a.acta_id, a.empresa_id, a.estudio_cliente_id, a.f_acto, a.tipo_acto
                              FROM gestion__soc_actas a
                              WHERE $w AND a.tabla_estado_registro_id = 1 AND a.estado_acta = 'BORRADOR'
                                AND a.f_acto < DATE_SUB(CURDATE(), INTERVAL " . LSOC_DIAS_BORRADOR . " DAY)
                              ORDER BY a.f_acto LIMIT 20", $t, $p), $amb);
    foreach ($viejas as $v) {
        $alertas[] = ['nivel' => 'warning', 'cliente' => $v['cliente'], 'cliente_id' => $v['cliente_ref'],
                      'acta_id' => (int) $v['acta_id'], 'fecha' => $v['f_acto'],
                      'texto' => (LSOC_TIPOS_ACTO[$v['tipo_acto']][0] ?? 'Acta') . ' sin transcribir al libro.'];
    }
    // Inscripciones observadas
    $obs = lsoc_etiquetar(lsoc_filas($c, "SELECT a.acta_id, a.empresa_id, a.estudio_cliente_id, a.f_presentacion, a.inscripcion_tramite
                           FROM gestion__soc_actas a
                           WHERE $w AND a.tabla_estado_registro_id = 1 AND a.inscripcion_estado = 'OBSERVADA' LIMIT 20", $t, $p), $amb);
    foreach ($obs as $o) {
        $alertas[] = ['nivel' => 'danger', 'cliente' => $o['cliente'], 'cliente_id' => $o['cliente_ref'],
                      'acta_id' => (int) $o['acta_id'], 'fecha' => $o['f_presentacion'],
                      'texto' => 'Inscripción observada' . ($o['inscripcion_tramite'] ? ' (trámite ' . $o['inscripcion_tramite'] . ')' : '') . '.'];
    }

    $orden = ['danger' => 0, 'warning' => 1, 'info' => 2];
    usort($alertas, fn($a, $b) => ($orden[$a['nivel']] <=> $orden[$b['nivel']]) ?: strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? '')));

    // Con un cliente elegido: de dónde salen sus libros y si quedó algo guardado solo en el estudio
    $fuente = null;
    if ($amb['modo'] === 'estudio' && $amb['cliente_id']) {
        $principal = null;
        foreach ($amb['fuentes'] as $f) {
            if ($f['origen'] === 'CLIENTE_ERP') {
                $principal = $f;
            }
        }
        $pendientes = 0;
        if ($principal) {
            $n = lsoc_fila($c, "SELECT (SELECT COUNT(*) FROM gestion__soc_libros WHERE empresa_id = ? AND estudio_cliente_id = ?)
                                     + (SELECT COUNT(*) FROM gestion__soc_actas WHERE empresa_id = ? AND estudio_cliente_id = ?) AS n",
                           "iiii", [$amb['ctx_empresa_id'], $amb['cliente_id'], $amb['ctx_empresa_id'], $amb['cliente_id']]);
            $pendientes = (int) ($n['n'] ?? 0);
        } else {
            $principal = $amb['fuentes'][0] ?? null;
        }
        if ($principal) {
            $fuente = ['origen' => $principal['origen'], 'escritura' => $principal['escritura'], 'aviso' => $principal['aviso'],
                       'propio' => $principal['origen'] === 'CLIENTE_ERP' && (int) $principal['empresa_id'] === (int) $amb['ctx_empresa_id'],
                       'pendientes_estudio' => $pendientes];
        }
    }

    return [
        'kpis' => [
            'libros_en_uso' => count(array_filter($libros, fn($l) => $l['estado_libro'] === 'EN_USO')),
            'borradores' => (int) ($k['borradores'] ?? 0),
            'inscripciones' => (int) ($k['inscripciones'] ?? 0),
            'alertas' => count($alertas),
        ],
        'alertas' => $alertas,
        'fuente' => $fuente,
    ];
}
