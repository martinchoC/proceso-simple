<?php
/**
 * Asistente "Alta de cliente" del estudio (F1).
 *
 * Un cliente del estudio es una empresa del ERP (gestión normal). El asistente la crea, le otorga al estudio la
 * vinculación (conf__empresas_vinculaciones), copia la configuración desde una empresa modelo (copia autorizada
 * de empresa_inicio_model.php) y deja el cliente en el padrón. Si el cliente tiene su propio servidor
 * (origen_datos = REMOTO) solo se registra el cliente y la URL: no se crea nada en este ERP.
 *
 * Los pasos son idempotentes: si uno falla, se vuelve a ejecutar el asistente con el mismo CUIT y retoma
 * (reutiliza la empresa ya creada si fue vinculada a este estudio).
 */

declare(strict_types=1);

require_once __DIR__ . '/estudio_clientes_model.php';
require_once __DIR__ . '/../../core/empresas_vinculaciones_model.php';
require_once __DIR__ . '/../conf/empresas_model.php';
require_once __DIR__ . '/../gestion_normal/empresas_autorizaciones_copia_model.php';
require_once __DIR__ . '/../gestion_normal/empresa_inicio_model.php';

/** Módulo en el que trabajan los clientes del estudio: Gestión Normal (conf__modulos 5). */
const ESTUDIO_ALTA_MODULO_GESTION = 5;

/**
 * Asigna al usuario el perfil "Administrador" (perfil base del módulo de gestión) de la empresa nueva, vigente
 * desde hoy y sin fin. Devuelve [ok, detalle] para la lista de pasos.
 */
function estudio_alta_asignar_administrador(mysqli $conexion, int $empresa_id, int $usuario_id): array
{
    $mod = ESTUDIO_ALTA_MODULO_GESTION;
    $st = mysqli_prepare($conexion, "SELECT ep.empresa_perfil_id
                                     FROM conf__empresas_perfiles ep
                                     JOIN conf__perfiles p ON p.perfil_id = ep.perfil_id_base
                                     WHERE ep.empresa_id = ? AND ep.modulo_id = ? AND ep.tabla_estado_registro_id = 1
                                       AND p.perfil_nombre = 'Administrador'
                                     ORDER BY ep.empresa_perfil_id LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $empresa_id, $mod);
    mysqli_stmt_execute($st);
    $ep = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$ep) {
        return [false, 'La empresa no tiene perfil Administrador: asigne los usuarios en Perfiles - Usuarios.'];
    }
    $epid = (int)$ep['empresa_perfil_id'];
    $st = mysqli_prepare($conexion, "INSERT INTO conf__usuarios_perfiles
                                         (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, usuario_creacion, tabla_estado_registro_id)
                                     SELECT ?, ?, CURDATE(), '2099-12-31', ?, 1 FROM DUAL
                                     WHERE NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles
                                                       WHERE usuario_id = ? AND empresa_perfil_id = ? AND tabla_estado_registro_id = 1)");
    mysqli_stmt_bind_param($st, "iiiii", $usuario_id, $epid, $usuario_id, $usuario_id, $epid);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return [true, 'El usuario que hizo el alta quedó como Administrador de la empresa'];
}

/**
 * Si la empresa no tiene ningún punto de venta, crea el 0001 en su boca principal (como inicioCrearSucursalRapida).
 * Devuelve [ok, detalle] para la lista de pasos.
 */
function estudio_alta_asegurar_punto_venta(mysqli $conexion, int $empresa_id): array
{
    $st = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM gestion__puntos_venta WHERE empresa_id = ?");
    mysqli_stmt_bind_param($st, "i", $empresa_id);
    mysqli_stmt_execute($st);
    $n = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['n'] ?? 0);
    mysqli_stmt_close($st);
    if ($n > 0) {
        return [true, 'La empresa ya tiene punto de venta'];
    }
    $st = mysqli_prepare($conexion, "SELECT b.sucursal_id, b.boca_id FROM gestion__bocas b
                                     WHERE b.empresa_id = ? AND b.tabla_estado_registro_id = 1
                                     ORDER BY b.es_principal DESC, b.boca_id LIMIT 1");
    mysqli_stmt_bind_param($st, "i", $empresa_id);
    mysqli_stmt_execute($st);
    $b = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$b) {
        return [false, 'La empresa no tiene bocas: cargue sucursal, boca y punto de venta en Gestión Normal.'];
    }
    $suc = (int)$b['sucursal_id'];
    $boca = (int)$b['boca_id'];
    $st = mysqli_prepare($conexion, "INSERT INTO gestion__puntos_venta
                                         (empresa_id, sucursal_id, boca_id, nombre, codigo_fiscal, sistema_emision, es_web, tabla_estado_registro_id)
                                     VALUES (?, ?, ?, 'Punto de Venta 0001 (WSFE)', 1, 'WSFE', 0, 1)");
    mysqli_stmt_bind_param($st, "iii", $empresa_id, $suc, $boca);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return $ok ? [true, 'Punto de venta 0001 creado (revise el número fiscal real)'] : [false, 'No se pudo crear el punto de venta.'];
}

/** CUIT/CUIL válido (11 dígitos + dígito verificador módulo 11). */
function estudio_alta_cuit_valido(string $cuit): bool
{
    $c = preg_replace('/\D/', '', $cuit);
    if (strlen($c) !== 11) {
        return false;
    }
    $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $suma += intval($c[$i]) * $pesos[$i];
    }
    $dv = 11 - ($suma % 11);
    if ($dv === 11) {
        $dv = 0;
    } elseif ($dv === 10) {
        $dv = 9;
    }
    return $dv === intval($c[10]);
}

/** Plantillas del estudio. Solo se ofrecen las empresas que además le otorgaron una vinculación al estudio. */
function estudio_plantillas_listar(mysqli $conexion, int $estudio_id): array
{
    $sql = "SELECT p.plantilla_id, p.empresa_origen_id, p.nombre, e.empresa
            FROM estudio__plantillas_cliente p
            INNER JOIN conf__empresas e ON e.empresa_id = p.empresa_origen_id
            WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1
            ORDER BY p.nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $estudio_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        if (estudio_cliente_tiene_vinculacion($conexion, $estudio_id, (int)$r['empresa_origen_id'])) {
            $out[] = $r;
        }
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/**
 * Registra una empresa como plantilla. La empresa debe haberle otorgado antes una vinculación vigente al estudio:
 * así nadie puede clonar la configuración de una empresa que no consintió.
 */
function estudio_plantilla_guardar(mysqli $conexion, int $estudio_id, int $empresa_origen_id, string $nombre): array
{
    $nombre = trim($nombre);
    if ($empresa_origen_id <= 0 || $nombre === '') {
        return ['success' => false, 'message' => 'Indique la empresa modelo y un nombre.', 'data' => null, 'error' => 'VALIDATION_ERROR'];
    }
    if (!estudio_cliente_tiene_vinculacion($conexion, $estudio_id, $empresa_origen_id)) {
        return ['success' => false, 'message' => 'La empresa modelo debe otorgar primero una vinculación vigente al estudio.', 'data' => null, 'error' => 'SIN_VINCULACION'];
    }
    $stmt = mysqli_prepare($conexion, "INSERT INTO estudio__plantillas_cliente (empresa_id, empresa_origen_id, nombre, tabla_estado_registro_id)
                                       VALUES (?, ?, ?, 1)
                                       ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, "iis", $estudio_id, $empresa_origen_id, $nombre);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok
        ? ['success' => true, 'message' => 'Plantilla guardada.', 'data' => null, 'error' => null]
        : ['success' => false, 'message' => 'No se pudo guardar la plantilla.', 'data' => null, 'error' => 'DB_ERROR'];
}

/** Módulos de gestión cuyas empresas sirven de plantilla: Gestión (2) y Gestión Normal (5). */
const ESTUDIO_PLANTILLA_MODULOS_GESTION = [2, 5];

/**
 * Componentes que se copian desde la empresa plantilla: los mismos que ofrece "Copiar Datos Iniciales" del
 * asistente de inicio de empresa (empresa_inicio.js, componentesConfig).
 */
const ESTUDIO_PLANTILLA_COMPONENTES = [
    'plan_cuentas', 'conceptos_contables', 'roles_cuentas', 'comprobantes_tipos', 'condiciones_pago',
    'sucursales_bocas', 'listas_precios', 'parametros', 'datos_basicos', 'tesoreria', 'impuestos_config', 'productos',
];

/**
 * Empresas que se pueden usar como plantilla de configuración: todas las que tienen activo un módulo de gestión
 * (2 o 5). Si además están registradas como plantilla del estudio se muestra ese nombre.
 * Filas: empresa_id, empresa, plantilla_nombre (null si no está registrada).
 */
function estudio_plantillas_empresas_gestion(mysqli $conexion, int $estudio_id): array
{
    $m1 = ESTUDIO_PLANTILLA_MODULOS_GESTION[0];
    $m2 = ESTUDIO_PLANTILLA_MODULOS_GESTION[1];
    $sql = "SELECT e.empresa_id, e.empresa, MAX(p.nombre) AS plantilla_nombre
            FROM conf__empresas e
            INNER JOIN conf__empresas_modulos em ON em.empresa_id = e.empresa_id
                   AND em.modulo_id IN (?, ?) AND em.tabla_estado_registro_id = 1
            LEFT JOIN estudio__plantillas_cliente p ON p.empresa_origen_id = e.empresa_id
                   AND p.empresa_id = ? AND p.tabla_estado_registro_id = 1
            WHERE e.tabla_estado_registro_id = 1
            GROUP BY e.empresa_id, e.empresa
            ORDER BY (MAX(p.nombre) IS NULL), e.empresa";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $m1, $m2, $estudio_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $out[] = ['empresa_id' => (int)$r['empresa_id'], 'empresa' => $r['empresa'], 'plantilla_nombre' => $r['plantilla_nombre']];
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/**
 * Empresa origen de la plantilla elegida: plantilla_empresa_id (empresa de gestión) o, por compatibilidad,
 * plantilla_id (registro de estudio__plantillas_cliente). 0 = sin plantilla, -1 = no válida.
 */
function estudio_plantilla_resolver_origen(mysqli $conexion, int $estudio_id, array $d): int
{
    $emp = intval($d['plantilla_empresa_id'] ?? 0);
    if ($emp > 0) {
        foreach (estudio_plantillas_empresas_gestion($conexion, $estudio_id) as $e) {
            if ($e['empresa_id'] === $emp) {
                return $emp;
            }
        }
        return -1;
    }
    $plantilla_id = intval($d['plantilla_id'] ?? 0);
    if ($plantilla_id > 0) {
        foreach (estudio_plantillas_listar($conexion, $estudio_id) as $p) {
            if ((int)$p['plantilla_id'] === $plantilla_id) {
                return (int)$p['empresa_origen_id'];
            }
        }
        return -1;
    }
    return 0;
}

/** Combos propios del asistente (localidades por provincia se resuelven en la ABM de empresas). */
function estudio_alta_combos(mysqli $conexion, int $estudio_id): array
{
    return [
        'plantillas' => estudio_plantillas_listar($conexion, $estudio_id),
        'plantillas_empresas' => estudio_plantillas_empresas_gestion($conexion, $estudio_id),
        'iibb_regimenes' => obtenerIibbRegimenes($conexion),
        'jurisdicciones_sedes' => obtenerJurisdiccionesSedes($conexion),
    ];
}

// ESTUDIO_NIVELES_SERVICIO / ESTUDIO_ORIGENES_DATOS: en estudio_clientes_model.php

/** Graba nivel de servicio y origen de datos del cliente (columnas de 20261105_..._origen_datos_01 y 20261106_..._nivel_servicio_01). */
function estudio_alta_marcar_cliente(mysqli $conexion, int $estudio_id, int $cliente_id, string $nivel, string $origen, ?string $url): bool
{
    try {
        $up = mysqli_prepare($conexion, "UPDATE estudio__clientes SET nivel_servicio = ?, origen_datos = ?, remoto_url = ? WHERE empresa_id = ? AND cliente_id = ?");
        mysqli_stmt_bind_param($up, "sssii", $nivel, $origen, $url, $estudio_id, $cliente_id);
        $ok = mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Crea (o reutiliza, si ya está vinculada al estudio) la empresa del ERP de un cliente, registra la vinculación
 * cliente -> estudio y, con plantilla, autoriza y ejecuta la copia de configuración. La usan el asistente de alta y
 * la ficha del cliente ("Crear empresa" al pasar un PADRON a FISCAL / CONTABLE / GESTION).
 * $d: razon_social, cuit (11 dígitos), condicion_iva_id, nivel_servicio, plantilla_empresa_id (o plantilla_id), modo_acceso, iibb_regimen_id,
 * jurisdiccion_sede_id, tipo_operacion, localidad_id.
 * Devuelve ['ok' => true, 'empresa_id', 'plantilla_origen'] o ['ok' => false, 'message', 'code'].
 */
function estudio_alta_preparar_empresa(mysqli $conexion, int $estudio_id, int $usuario_id, array $d, callable $paso): array
{
    $mal = function (string $m, string $c) { return ['ok' => false, 'message' => $m, 'code' => $c]; };
    $razon = trim((string)($d['razon_social'] ?? ''));
    $cuit = preg_replace('/\D/', '', (string)($d['cuit'] ?? ''));
    $cond = intval($d['condicion_iva_id'] ?? 0);
    $nivel = in_array($d['nivel_servicio'] ?? '', ESTUDIO_NIVELES_SERVICIO, true) ? $d['nivel_servicio'] : 'FISCAL';
    $modo_acceso = ($d['modo_acceso'] ?? 'LECTURA') === 'LECTURA_ESCRITURA' ? 'LECTURA_ESCRITURA' : 'LECTURA';
    if ($nivel === 'PADRON') {
        return $mal('Un cliente de nivel Padrón no lleva empresa en el ERP.', 'VALIDATION_ERROR');
    }
    if ($razon === '' || $cond <= 0 || !estudio_alta_cuit_valido($cuit)) {
        return $mal('Faltan la razón social o la condición frente al IVA, o el CUIT no es válido.', 'VALIDATION_ERROR');
    }

    // Plantilla (opcional): una empresa con módulo de gestión activo (o, por compatibilidad, una plantilla registrada).
    $plantilla_origen = estudio_plantilla_resolver_origen($conexion, $estudio_id, $d);
    if ($plantilla_origen < 0) {
        return $mal('La plantilla elegida no es válida: elija una empresa con módulo de gestión activo.', 'INVALID_TEMPLATE');
    }

    // 1. Empresa: reutiliza una con ese CUIT solo si ya está vinculada a este estudio (reintento o empresa existente).
    $st = mysqli_prepare($conexion, "SELECT empresa_id FROM conf__empresas
                                     WHERE REPLACE(REPLACE(documento_numero, '-', ''), ' ', '') = ? AND empresa_id <> ? LIMIT 1");
    mysqli_stmt_bind_param($st, "si", $cuit, $estudio_id);
    mysqli_stmt_execute($st);
    $ex = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    $vinc_existente = false;
    if ($ex) {
        $empresa_id = (int)$ex['empresa_id'];
        if (!estudio_cliente_tiene_vinculacion($conexion, $estudio_id, $empresa_id)) {
            return $mal('Ya existe en el ERP una empresa con ese CUIT que no vinculó al estudio. Pídale que registre la vinculación en "Vinculaciones entre empresas" y luego asóciela desde la ficha del cliente.', 'EMPRESA_EXISTENTE');
        }
        $vinc_existente = true;
        $paso('Empresa del ERP', true, "Se reutiliza la empresa #$empresa_id (ya vinculada al estudio)");
    } else {
        // Localidad obligatoria en la ABM de empresas: por defecto la del estudio; se corrige luego en la ficha de la empresa.
        $loc = intval($d['localidad_id'] ?? 0);
        if ($loc > 0) {
            // La que viene del formulario (constancia de ARCA) tiene que existir en el catálogo
            $st = mysqli_prepare($conexion, "SELECT localidad_id FROM conf__localidades WHERE localidad_id = ? AND tabla_estado_registro_id = 1");
            mysqli_stmt_bind_param($st, "i", $loc);
            mysqli_stmt_execute($st);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($st))) $loc = 0;
            mysqli_stmt_close($st);
        }
        if ($loc <= 0) {
            $st = mysqli_prepare($conexion, "SELECT localidad_id FROM conf__empresas WHERE empresa_id = ?");
            mysqli_stmt_bind_param($st, "i", $estudio_id);
            mysqli_stmt_execute($st);
            $loc = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['localidad_id'] ?? 0);
            mysqli_stmt_close($st);
        }
        if ($loc <= 0) {
            return $mal('No se pudo determinar la localidad. Indique una localidad o complete la del estudio.', 'VALIDATION_ERROR');
        }
        // Domicilio fiscal e inicio de actividades: los trae la constancia de ARCA leída con IA (opcionales)
        $domicilio = mb_substr(trim((string)($d['domicilio'] ?? '')), 0, 150);
        $inicio = (string)($d['inicio_actividades'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !strtotime($inicio)) $inicio = '';
        $nuevo = 0;
        $ok = agregarempresa($conexion, [
            'empresa' => $razon,
            'documento_tipo_id' => 1,
            'documento_numero' => $cuit,
            'domicilio' => $domicilio,
            'inicio_actividades' => $inicio ?: null,
            'localidad_id' => $loc,
            'condicion_fiscal_id' => $cond,
            'iibb_regimen_id' => intval($d['iibb_regimen_id'] ?? 1),
            'jurisdiccion_sede_id' => intval($d['jurisdiccion_sede_id'] ?? 2),
            'tipo_operacion' => $d['tipo_operacion'] ?? 'SERVICIOS',
            'nivel_gestion' => estudio_nivel_gestion_de_servicio($nivel),
            'tabla_estado_registro_id' => 1,
        ], $usuario_id, $nuevo);
        if (!$ok || $nuevo <= 0) {
            return $mal('No se pudo crear la empresa en el ERP.', 'EMPRESA_ERROR');
        }
        $empresa_id = (int)$nuevo;
        $paso('Empresa del ERP', true, "Empresa #$empresa_id creada");
    }

    // 2. Vinculación: la empresa cliente le concede al estudio el acceso a su información.
    if (!$vinc_existente) {
        $v = vinculacionesGuardar($conexion, [
            'empresa_destino_id' => $estudio_id,
            'tipo_vinculo' => 'ESTUDIO_CONTABLE',
            'modo_acceso' => $modo_acceso,
            'permiso_ventas' => 1, 'permiso_compras' => 1, 'permiso_contabilidad' => 1,
            'permiso_impuestos' => 1, 'permiso_tesoreria' => 1,
            'observaciones' => 'Alta de cliente desde el estudio',
        ], $empresa_id, $usuario_id);
        if (empty($v['success'])) {
            $paso('Vinculación con el estudio', false, $v['error'] ?? '');
            return $mal('La empresa se creó pero no se pudo registrar la vinculación: ' . ($v['error'] ?? ''), 'VINCULACION_ERROR');
        }
        $paso('Vinculación con el estudio', true, "Modo $modo_acceso; ventas, compras, contabilidad, impuestos y tesorería");
    }

    // 3. Copia de configuración desde la plantilla (autorización + copia), con todos los componentes de
    //    "Copiar Datos Iniciales" del asistente de inicio de empresa.
    if ($plantilla_origen > 0 && $plantilla_origen === $empresa_id) {
        $paso('Copia de configuración', true, 'La plantilla es la misma empresa del cliente: no se copia nada');
    } elseif ($plantilla_origen > 0) {
        $comp = ESTUDIO_PLANTILLA_COMPONENTES;
        $permisos = [];
        foreach ($comp as $k) {
            $permisos['permite_' . $k] = 1;
        }
        $aut = autCopiaGuardar($conexion, $permisos + [
            'empresa_destino_id' => $empresa_id,
            'motivo' => 'Alta de cliente del estudio #' . $estudio_id,
        ], $plantilla_origen, $usuario_id);
        if (empty($aut['success'])) {
            $paso('Autorización de copia', false, $aut['error'] ?? '');
            return $mal('No se pudo autorizar la copia desde la plantilla: ' . ($aut['error'] ?? ''), 'AUTORIZACION_ERROR');
        }
        $cop = inicioEjecutarCopia($conexion, $plantilla_origen, $empresa_id, array_fill_keys($comp, 1), $usuario_id);
        $paso('Copia de configuración desde la plantilla', !empty($cop['success']), $cop['message'] ?? ($cop['error'] ?? ''));
        if (empty($cop['success'])) {
            return $mal('Falló la copia desde la plantilla: ' . ($cop['error'] ?? ''), 'COPIA_ERROR');
        }
    } else {
        $paso('Copia de configuración', true, 'Sin plantilla: la empresa queda vacía (configúrela en Gestión Normal)');
    }

    return ['ok' => true, 'empresa_id' => $empresa_id, 'plantilla_origen' => $plantilla_origen];
}

/** GESTION = empresa COMPLETA; FISCAL / CONTABLE = SIMPLIFICADA. */
function estudio_nivel_gestion_de_servicio(string $nivel_servicio): string
{
    return $nivel_servicio === 'GESTION' ? 'COMPLETA' : 'SIMPLIFICADA';
}

/**
 * Deja la empresa del cliente como corresponde a su nivel de servicio: nivel de gestión, módulo Gestión Normal y,
 * en CONTABLE / GESTION, perfiles, sucursal, punto de venta y el usuario como Administrador. Al bajar a SIMPLIFICADA
 * desasigna de los perfiles existentes las funciones de páginas "solo gestión completa" (reversible).
 * Es idempotente: se usa en el alta y cada vez que cambia el nivel en la ficha.
 */
function estudio_alta_aplicar_nivel(mysqli $conexion, int $usuario_id, int $empresa_id, string $nivel_servicio, callable $paso): void
{
    $mod = ESTUDIO_ALTA_MODULO_GESTION;
    $nivel_gestion = estudio_nivel_gestion_de_servicio($nivel_servicio);
    if (empresa_nivel_gestion($conexion, $empresa_id) !== $nivel_gestion) {
        empresa_guardar_nivel_gestion($conexion, $empresa_id, $nivel_gestion);
        // Perfiles ya creados: al bajar se desasignan las funciones de "solo gestión completa"; al subir se devuelven.
        $cambiadas = $nivel_gestion === 'SIMPLIFICADA'
            ? perfiles_quitar_funciones_simplificada($conexion, $empresa_id, $mod)
            : perfiles_restaurar_funciones_completa($conexion, $empresa_id, $mod);
        $paso('Nivel de gestión de la empresa', true, $nivel_gestion . ($cambiadas > 0 ? " ($cambiadas funciones de perfiles ajustadas)" : ''));
    }

    // Módulo Gestión Normal habilitado para la empresa (sin esto no aparece en el selector de módulos).
    $st = mysqli_prepare($conexion, "INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id)
                                     SELECT ?, ?, 1 FROM DUAL
                                     WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_modulos WHERE empresa_id = ? AND modulo_id = ?)");
    mysqli_stmt_bind_param($st, "iiii", $empresa_id, $mod, $empresa_id, $mod);
    mysqli_stmt_execute($st);
    $nuevo_mod = mysqli_stmt_affected_rows($st) > 0;
    mysqli_stmt_close($st);
    if ($nuevo_mod) {
        $paso('Módulo Gestión Normal', true, 'Habilitado para la empresa');
    }

    if (!in_array($nivel_servicio, ['CONTABLE', 'GESTION'], true)) {
        $paso('Perfiles y sucursal', true, 'No se crean en nivel FISCAL: el estudio opera por la vinculación');
        return;
    }
    // Pasos idempotentes: en un reintento "ya existe" no es una falla.
    $per = inicioCrearPerfilesPredefinidos($conexion, $empresa_id, $mod);
    $per_ok = !empty($per['success']) || str_contains((string)($per['error'] ?? ''), 'No hay perfiles predefinidos pendientes');
    $paso('Perfiles predefinidos', $per_ok, $per['message'] ?? ($per['error'] ?? ''));
    $suc = inicioCrearSucursalRapida($conexion, $empresa_id, $usuario_id);
    $suc_ok = !empty($suc['success']) || str_contains((string)($suc['error'] ?? ''), 'ya cuenta con una sucursal');
    $paso('Sucursal', $suc_ok, $suc['message'] ?? ($suc['error'] ?? ''));
    // La copia de la plantilla trae sucursal y bocas pero no puntos de venta: sin PV no se puede facturar.
    $paso('Punto de venta', ...estudio_alta_asegurar_punto_venta($conexion, $empresa_id));
    // Quien hace el alta queda como Administrador de la empresa (si no, solo el superadmin puede entrar).
    $paso('Acceso del usuario del estudio', ...estudio_alta_asignar_administrador($conexion, $empresa_id, $usuario_id));
}

/**
 * Ficha del cliente: crea la empresa de un cliente que ya está en el padrón (PADRON que pasa a FISCAL / CONTABLE /
 * GESTION, o clientes cargados antes del asistente) y lo deja vinculado con ese nivel.
 * $d: nivel_servicio, plantilla_empresa_id (o plantilla_id), modo_acceso (el resto sale de la ficha del cliente).
 */
function estudio_cliente_crear_empresa(mysqli $conexion, int $estudio_id, int $usuario_id, int $cliente_id, array $d): array
{
    $pasos = [];
    $paso = function (string $nombre, bool $ok, string $detalle = '') use (&$pasos) {
        $pasos[] = ['paso' => $nombre, 'ok' => $ok, 'detalle' => $detalle];
    };
    $c = estudio_cliente_obtener($conexion, $estudio_id, $cliente_id);
    if (!$c) {
        return ['success' => false, 'message' => 'El cliente no existe.', 'data' => null, 'error' => 'NOT_FOUND'];
    }
    if ((int)($c['empresa_vinculada_id'] ?? 0) > 0) {
        return ['success' => false, 'message' => 'El cliente ya tiene una empresa vinculada.', 'data' => null, 'error' => 'YA_VINCULADO'];
    }
    // El origen que vale es el que eligió la ficha (un REMOTO / EXTERNO que pasa a LOCAL todavía no lo tiene guardado,
    // porque la ficha no se graba hasta que exista la empresa); si no viene, el guardado.
    $origen = strtoupper(trim((string)($d['origen_datos'] ?? ($c['origen_datos'] ?? 'LOCAL'))));
    if ($origen !== 'LOCAL') {
        return ['success' => false, 'message' => 'Un cliente con servidor propio o software externo no lleva empresa en este ERP.', 'data' => null, 'error' => 'VALIDATION_ERROR'];
    }
    $nivel = in_array($d['nivel_servicio'] ?? '', ['FISCAL', 'CONTABLE', 'GESTION'], true) ? $d['nivel_servicio'] : 'FISCAL';

    $prep = estudio_alta_preparar_empresa($conexion, $estudio_id, $usuario_id, [
        'razon_social' => $c['razon_social'], 'cuit' => $c['cuit'], 'condicion_iva_id' => $c['condicion_iva_id'],
        'nivel_servicio' => $nivel, 'plantilla_id' => intval($d['plantilla_id'] ?? 0), 'plantilla_empresa_id' => intval($d['plantilla_empresa_id'] ?? 0),
        'modo_acceso' => $d['modo_acceso'] ?? 'LECTURA_ESCRITURA',
    ], $paso);
    if (!$prep['ok']) {
        return ['success' => false, 'message' => $prep['message'], 'data' => ['pasos' => $pasos], 'error' => $prep['code']];
    }
    $empresa_id = (int)$prep['empresa_id'];
    estudio_alta_aplicar_nivel($conexion, $usuario_id, $empresa_id, $nivel, $paso);

    $st = mysqli_prepare($conexion, "UPDATE estudio__clientes SET empresa_vinculada_id = ?, nivel_servicio = ?, origen_datos = 'LOCAL', remoto_url = NULL
                                     WHERE empresa_id = ? AND cliente_id = ?");
    mysqli_stmt_bind_param($st, "isii", $empresa_id, $nivel, $estudio_id, $cliente_id);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    $paso('Ficha del cliente', $ok, $ok ? "Vinculado a la empresa #$empresa_id, nivel $nivel" : 'No se pudo actualizar la ficha');

    return $ok
        ? ['success' => true, 'message' => 'Empresa creada y vinculada al cliente.', 'data' => ['cliente_id' => $cliente_id, 'empresa_id' => $empresa_id, 'pasos' => $pasos], 'error' => null]
        : ['success' => false, 'message' => 'La empresa se creó pero no se pudo actualizar la ficha del cliente.', 'data' => ['empresa_id' => $empresa_id, 'pasos' => $pasos], 'error' => 'DB_ERROR'];
}

/**
 * Ejecuta el asistente. $d: razon_social, cuit, condicion_iva_id (gestion__condiciones_fiscales), cliente_tipo_id,
 * nivel_servicio (PADRON|FISCAL|CONTABLE|GESTION), origen_datos (LOCAL|REMOTO|EXTERNO), remoto_url, plantilla_empresa_id (o plantilla_id), modo_acceso (LECTURA|LECTURA_ESCRITURA), iibb_regimen_id,
 * jurisdiccion_sede_id, mes_cierre_ejercicio, tipo_operacion.
 * PADRON no crea empresa (solo padrón, vencimientos, tareas, honorarios). FISCAL/CONTABLE crean empresa SIMPLIFICADA
 * (FISCAL sin perfiles ni sucursal: el estudio opera por la vinculación); GESTION crea empresa COMPLETA.
 * REMOTO/EXTERNO solo registran el cliente (no se crea empresa en este ERP).
 * Devuelve el resultado con la lista de pasos ejecutados.
 */
function estudio_alta_cliente(mysqli $conexion, int $estudio_id, int $usuario_id, array $d): array
{
    $pasos = [];
    $paso = function (string $nombre, bool $ok, string $detalle = '') use (&$pasos) {
        $pasos[] = ['paso' => $nombre, 'ok' => $ok, 'detalle' => $detalle];
    };
    $falla = function (string $msg, string $code = 'ERROR') use (&$pasos) {
        return ['success' => false, 'message' => $msg, 'data' => ['pasos' => $pasos], 'error' => $code];
    };

    $razon = trim((string)($d['razon_social'] ?? ''));
    $cuit_limpio = preg_replace('/\D/', '', (string)($d['cuit'] ?? ''));
    $cond = intval($d['condicion_iva_id'] ?? 0);
    $tipo_cliente = intval($d['cliente_tipo_id'] ?? 0);
    $nivel_servicio = in_array($d['nivel_servicio'] ?? '', ESTUDIO_NIVELES_SERVICIO, true) ? $d['nivel_servicio'] : 'FISCAL';
    $origen = in_array($d['origen_datos'] ?? '', ESTUDIO_ORIGENES_DATOS, true) ? $d['origen_datos'] : 'LOCAL';
    if ($nivel_servicio === 'PADRON') {
        $origen = 'LOCAL'; // sin datos propios: el origen no aplica
    }
    $con_perfiles = in_array($nivel_servicio, ['CONTABLE', 'GESTION'], true);
    $remoto_url = trim((string)($d['remoto_url'] ?? ''));
    $modo_acceso = ($d['modo_acceso'] ?? 'LECTURA') === 'LECTURA_ESCRITURA' ? 'LECTURA_ESCRITURA' : 'LECTURA';
    $mes_cierre = max(1, min(12, intval($d['mes_cierre_ejercicio'] ?? 12)));

    if ($razon === '' || $cond <= 0 || $tipo_cliente <= 0) {
        return $falla('Complete razón social, tipo de cliente y condición frente al IVA.', 'VALIDATION_ERROR');
    }
    if (!estudio_alta_cuit_valido($cuit_limpio)) {
        return $falla('El CUIT no es válido (dígito verificador incorrecto).', 'INVALID_CUIT');
    }
    $cuit_fmt = substr($cuit_limpio, 0, 2) . '-' . substr($cuit_limpio, 2, 8) . '-' . substr($cuit_limpio, 10, 1);

    // El cliente no puede estar ya en el padrón del estudio.
    $st = mysqli_prepare($conexion, "SELECT cliente_id FROM estudio__clientes
                                     WHERE empresa_id = ? AND REPLACE(cuit, '-', '') = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "is", $estudio_id, $cuit_limpio);
    mysqli_stmt_execute($st);
    $ya = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if ($ya) {
        return $falla('Ya existe un cliente del estudio con ese CUIT.', 'CLIENTE_DUPLICADO');
    }

    // ---- Solo se registra en el padrón (nivel PADRON, servidor propio o software externo) -----------------
    if ($nivel_servicio === 'PADRON' || $origen !== 'LOCAL') {
        if ($origen === 'REMOTO' && ($remoto_url === '' || !preg_match('#^https://[^\s]+$#i', $remoto_url))) {
            return $falla('Para un cliente con servidor propio indique la URL https:// de su ERP.', 'INVALID_URL');
        }
        $res = estudio_cliente_guardar($conexion, $estudio_id, [
            'cliente_tipo_id' => $tipo_cliente, 'razon_social' => $razon, 'cuit' => $cuit_fmt,
            'condicion_iva_id' => $cond, 'mes_cierre_ejercicio' => $mes_cierre,
            'ingresos_brutos_tipo' => $d['ingresos_brutos_tipo'] ?? 'LOCAL', 'tabla_estado_registro_id' => 1,
        ], $usuario_id);
        if (empty($res['success'])) {
            return $falla($res['message'] ?? 'No se pudo registrar el cliente.', $res['error'] ?? 'ERROR');
        }
        $cid = (int)$res['data']['cliente_id'];
        if (!estudio_alta_marcar_cliente($conexion, $estudio_id, $cid, $nivel_servicio, $origen, $origen === 'REMOTO' ? $remoto_url : null)) {
            $paso('Nivel de servicio y origen de datos', false, 'No se pudieron grabar: ejecute los scripts origen_datos_01 y nivel_servicio_01');
            return $falla('El cliente se registró, pero faltan columnas del script de nivel de servicio.', 'ESQUEMA_DESACTUALIZADO');
        }
        $detalle = $nivel_servicio === 'PADRON'
            ? 'Solo padrón: vencimientos, tareas y honorarios (sin empresa en el ERP)'
            : ($origen === 'REMOTO' ? $remoto_url . ' (la sincronización se habilita en una etapa posterior)' : 'Software externo: los datos se incorporarán por importación de archivos');
        $paso('Registro del cliente (' . $nivel_servicio . ' / ' . $origen . ')', true, $detalle);
        return ['success' => true, 'message' => 'Cliente registrado.', 'data' => ['cliente_id' => $cid, 'empresa_id' => null, 'pasos' => $pasos], 'error' => null];
    }

    // ---- Cliente en este ERP ------------------------------------------------------------------------------
    $prep = estudio_alta_preparar_empresa($conexion, $estudio_id, $usuario_id, [
        "razon_social" => $razon, "cuit" => $cuit_limpio, "condicion_iva_id" => $cond,
        "nivel_servicio" => $nivel_servicio, "plantilla_id" => intval($d["plantilla_id"] ?? 0), "plantilla_empresa_id" => intval($d["plantilla_empresa_id"] ?? 0), "modo_acceso" => $modo_acceso,
        "iibb_regimen_id" => $d["iibb_regimen_id"] ?? null, "jurisdiccion_sede_id" => $d["jurisdiccion_sede_id"] ?? null,
        "tipo_operacion" => $d["tipo_operacion"] ?? "SERVICIOS", "localidad_id" => $d["localidad_id"] ?? 0,
        "domicilio" => $d["domicilio"] ?? "", "inicio_actividades" => $d["inicio_actividades"] ?? "",
    ], $paso);
    if (!$prep["ok"]) {
        return $falla($prep["message"], $prep["code"]);
    }
    $empresa_id = (int)$prep["empresa_id"];
    $plantilla_origen = (int)$prep["plantilla_origen"];

    // 4. Nivel de la empresa, módulo y (CONTABLE / GESTION) perfiles, sucursal, punto de venta y administrador.
    estudio_alta_aplicar_nivel($conexion, $usuario_id, $empresa_id, $nivel_servicio, $paso);

    // 5. Alta en el padrón del estudio con los datos fiscales resueltos desde la empresa.
    $info = estudio_cliente_obtener_empresa_erp($conexion, $empresa_id) ?: [];
    $res = estudio_cliente_guardar($conexion, $estudio_id, [
        'cliente_tipo_id' => $tipo_cliente,
        'razon_social' => $razon,
        'cuit' => $cuit_fmt,
        'condicion_iva_id' => $cond,
        'empresa_vinculada_id' => $empresa_id,
        'mes_cierre_ejercicio' => $mes_cierre,
        'ingresos_brutos_tipo' => $info['ingresos_brutos_tipo'] ?? 'LOCAL',
        'jurisdiccion_sede_id' => $info['jurisdiccion_id_estudio'] ?? null,
        'tabla_estado_registro_id' => 1,
    ], $usuario_id);
    if (empty($res['success'])) {
        $paso('Alta en el padrón del estudio', false, $res['message'] ?? '');
        return $falla('La empresa quedó lista pero no se pudo dar de alta en el padrón: ' . ($res['message'] ?? ''), $res['error'] ?? 'ERROR');
    }
    $cid = (int)$res['data']['cliente_id'];
    $marcado = estudio_alta_marcar_cliente($conexion, $estudio_id, $cid, $nivel_servicio, 'LOCAL', null);
    $paso('Alta en el padrón del estudio', $marcado, "Cliente #$cid, nivel $nivel_servicio" . ($marcado ? '' : ' (falta ejecutar el script nivel_servicio_01)'));

    // 6. Diagnóstico final de la empresa (pendientes que el asistente no puede resolver solo).
    $diag = [];
    try {
        $diag = inicioObtenerDiagnostico($conexion, $empresa_id, ESTUDIO_ALTA_MODULO_GESTION);
    } catch (Throwable $e) {
        $diag = [];
    }
    $pendientes = ['Revisar la localidad y el domicilio fiscal de la empresa si se usó la localidad del estudio.'];
    if ($con_perfiles) {
        $pendientes[] = 'Asignar al resto de los usuarios del estudio y del cliente a los perfiles de la empresa (Perfiles - Usuarios).';
        $pendientes[] = 'Cargar el punto de venta fiscal real y las categorías de activos fijos si el cliente los usa.';
    }
    if ($plantilla_origen <= 0) {
        $pendientes[] = 'Cargar el plan de cuentas (sin plantilla la empresa quedó vacía).';
    }

    return [
        'success' => true,
        'message' => 'Cliente creado y listo para gestionar.',
        'data' => ['cliente_id' => $cid, 'empresa_id' => $empresa_id, 'pasos' => $pasos, 'pendientes' => $pendientes, 'diagnostico' => $diag],
        'error' => null,
    ];
}
