<?php
// core/permisos.php
// Motor de control de acceso a páginas según los perfiles activos del usuario en la empresa actual.
// Jerarquía (ver CLAUDE.md):
//   Usuarios --< Usuarios_Perfiles >-- Empresas_Perfiles --< Empresas_Perfiles_Funciones (override)
//                                                        \-- Perfiles --< Perfiles_Funciones (base)

// Superadmin con acceso total, sin pasar por conf__perfiles_funciones / conf__empresas_perfiles_funciones.
// Mientras la carga de funciones por perfil esté incompleta, esto evita que el usuario dueño del sistema
// quede bloqueado de sus propias pantallas. Sacar este bypass una vez que los perfiles estén bien cargados.
const USUARIOS_ACCESO_TOTAL = [1];

/**
 * Perfiles (empresa_perfil) activos y vigentes del usuario dentro de una empresa.
 *
 * @return array<int,array{empresa_perfil_id:int,perfil_id_base:int}>
 */
function usuario_obtener_perfiles_empresa(mysqli $conexion, int $usuario_id, int $empresa_id): array
{
    $sql = "SELECT DISTINCT ep.empresa_perfil_id, ep.perfil_id_base
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
            WHERE up.usuario_id = ?
              AND ep.empresa_id = ?
              AND up.tabla_estado_registro_id = 1
              AND ep.tabla_estado_registro_id = 1
              AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $perfiles = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $perfiles[] = [
            'empresa_perfil_id' => (int) $row['empresa_perfil_id'],
            'perfil_id_base' => (int) $row['perfil_id_base'],
        ];
    }
    mysqli_stmt_close($stmt);

    return $perfiles;
}

/**
 * Páginas a las que el usuario tiene acceso dentro de una empresa.
 *
 * La única fuente de verdad es conf__empresas_perfiles_funciones (asignado = 1 para ese
 * empresa_perfil_id): es el mismo criterio que ya usa el panel de administración de
 * permisos para decidir qué función está "asignada" (ver
 * modules/conf/empresas_perfiles_funciones_model.php, función
 * obtenerPaginasFuncionesPorEmpresaPerfil / obtenerEstructuraCompletaPorEmpresaPerfil:
 * ahí "asignada" es 1 únicamente si existe la fila en empresas_perfiles_funciones, sin
 * mirar nunca conf__perfiles_funciones). Esa tabla base solo se usa como plantilla para
 * sembrar empresas_perfiles_funciones al crear un empresa_perfil
 * (heredarPaginasFuncionesDesdePerfilBase); no es un fallback en tiempo de ejecución, así
 * que acá tampoco se consulta.
 *
 * No hay "acceso libre" para páginas sin funciones definidas: si una página de negocio
 * (tabla_id != 0) todavía no tiene funciones cargadas, queda denegada hasta que se
 * configure. Los menús contenedores (páginas con hijos en conf__paginas) no necesitan
 * su propio permiso: header1.php los muestra u oculta según si al menos uno de sus hijos
 * está permitido, independientemente de esta lista.
 *
 * @return array<int,true> pagina_id => true
 */
function usuario_obtener_paginas_permitidas(mysqli $conexion, int $usuario_id, int $empresa_id): array
{
    $paginas_permitidas = [];

    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        $res = mysqli_query($conexion, "SELECT pagina_id FROM conf__paginas WHERE tabla_estado_registro_id = 1");
        while ($row = mysqli_fetch_assoc($res)) {
            $paginas_permitidas[(int) $row['pagina_id']] = true;
        }
        return $paginas_permitidas;
    }

    $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id);
    if (empty($perfiles)) {
        return $paginas_permitidas;
    }

    $sql = "SELECT DISTINCT pf.pagina_id
            FROM conf__paginas_funciones pf
            INNER JOIN conf__empresas_perfiles_funciones epf
                ON epf.pagina_funcion_id = pf.pagina_funcion_id AND epf.empresa_perfil_id = ?
            WHERE pf.tabla_estado_registro_id = 1
            AND epf.asignado = 1";
    $stmt = mysqli_prepare($conexion, $sql);

    foreach ($perfiles as $perfil) {
        mysqli_stmt_bind_param($stmt, "i", $perfil['empresa_perfil_id']);
        mysqli_stmt_execute($stmt);
        $res_pf = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res_pf)) {
            $paginas_permitidas[(int) $row['pagina_id']] = true;
        }
    }
    mysqli_stmt_close($stmt);

    return $paginas_permitidas;
}

/**
 * pagina_id de conf__paginas para un archivo de un módulo (para pantallas que no fijan $pagina_idx
 * porque viven como página propia en la configuración). Si hay más de una página activa con esa url,
 * $pagina_id_pedido (normalmente el pagina_id del GET que arma el sidebar) elige entre ellas; un
 * valor que no corresponda a esa url se ignora. Devuelve 0 si la url no está configurada.
 */
function pagina_id_por_url(mysqli $conexion, int $modulo_id, string $url, int $pagina_id_pedido = 0): int
{
    $base_script = basename($url);
    $like_script = '%/' . $base_script;
    $sql = "SELECT pagina_id FROM conf__paginas
            WHERE modulo_id = ? AND (url = ? OR url = ? OR url LIKE ?) AND tabla_estado_registro_id = 1
            ORDER BY (url = ? OR url = ?) DESC, pagina_id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "isssss", $modulo_id, $url, $base_script, $like_script, $url, $base_script);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $ids = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $ids[] = (int) $row['pagina_id'];
    }
    mysqli_stmt_close($stmt);

    if (empty($ids)) {
        return 0;
    }
    return in_array($pagina_id_pedido, $ids, true) ? $pagina_id_pedido : $ids[0];
}

/**
 * Página con la que trabaja un _ajax.php que la recibe del request: la pedida solo si es una página activa del módulo
 * del archivo; si no (no vino, vino 0, o es la del otro módulo de gestión), la del módulo con esa url. Así los
 * permisos de botones y acciones se miran siempre contra una página del módulo en el que se está trabajando.
 */
function permisos_pagina_modulo(mysqli $conexion, int $modulo_id, int $pagina_pedida, string $url): int
{
    if ($pagina_pedida > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT modulo_id FROM conf__paginas WHERE pagina_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, 'i', $pagina_pedida);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($fila && (int) $fila[0] === $modulo_id) {
            return $pagina_pedida;
        }
    }
    $por_url = pagina_id_por_url($conexion, $modulo_id, $url);
    return $por_url > 0 ? $por_url : $pagina_pedida;
}

/**
 * Chequeo puntual de acceso a una página (por ejemplo, la página que se está sirviendo).
 * pagina_id <= 0 se considera sin restricción (páginas que todavía no definen $pagina_idx).
 */
function usuario_tiene_acceso_pagina(mysqli $conexion, int $usuario_id, int $empresa_id, int $pagina_id): bool
{
    if ($pagina_id <= 0) {
        return true;
    }

    $paginas_permitidas = usuario_obtener_paginas_permitidas($conexion, $usuario_id, $empresa_id);
    return isset($paginas_permitidas[$pagina_id]);
}

/**
 * ¿Puede el usuario ejecutar la función (accion_js) de la página en la empresa? Para validar en el servidor los
 * botones del motor (agregar, editar, inactivar...): conf__empresas_perfiles_funciones de sus perfiles activos.
 * El superadmin puede toda función configurada y activa de la página.
 */
function usuario_puede_funcion(mysqli $conexion, int $usuario_id, int $empresa_id, int $pagina_id, string $accion_js): bool
{
    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        $stmt = mysqli_prepare($conexion, "SELECT 1 FROM conf__paginas_funciones
                                           WHERE pagina_id = ? AND accion_js = ? AND tabla_estado_registro_id = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $pagina_id, $accion_js);
    } else {
        $perfiles = array_map('intval', array_column(usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id), 'empresa_perfil_id'));
        if (!$perfiles) {
            return false;
        }
        $in = implode(',', array_fill(0, count($perfiles), '?'));
        $stmt = mysqli_prepare($conexion, "SELECT 1 FROM conf__empresas_perfiles_funciones epf
                                           JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
                                           WHERE epf.empresa_perfil_id IN ($in) AND epf.asignado = 1
                                             AND pf.pagina_id = ? AND pf.accion_js = ? AND pf.tabla_estado_registro_id = 1
                                           LIMIT 1");
        $params = array_merge($perfiles, [$pagina_id, $accion_js]);
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($perfiles)) . 'is', ...$params);
    }
    mysqli_stmt_execute($stmt);
    $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Usuario y empresa contra los que se filtran los botones del motor: los que validó ajax_guard()
 * (permisos_fijar_contexto) o, si no pasó por ahí, los de la sesión.
 *
 * @return array{0:int,1:int} [usuario_id, empresa_id]
 */
function permisos_contexto(): array
{
    $ctx = $GLOBALS['permisos_ctx'] ?? null;
    if (is_array($ctx)) {
        return [(int) $ctx['usuario_id'], (int) $ctx['empresa_id']];
    }
    return [(int) ($_SESSION['usuario_id'] ?? 0), (int) ($_SESSION['empresa_id'] ?? 0)];
}

function permisos_fijar_contexto(int $usuario_id, int $empresa_id): void
{
    $GLOBALS['permisos_ctx'] = ['usuario_id' => $usuario_id, 'empresa_id' => $empresa_id];
}

function permisos_es_superadmin(?int $usuario_id = null): bool
{
    if ($usuario_id === null) {
        [$usuario_id] = permisos_contexto();
    }
    return in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true);
}

/**
 * ids de conf__paginas_funciones de la página asignados a algún perfil activo del usuario en la empresa
 * (conf__empresas_perfiles_funciones, la misma fuente que usuario_puede_funcion). null = todas (superadmin).
 *
 * @return array<int,true>|null
 */
function usuario_funciones_pagina_ids(mysqli $conexion, int $usuario_id, int $empresa_id, int $pagina_id): ?array
{
    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        return null;
    }
    static $cache = [];
    $clave = "$usuario_id|$empresa_id|$pagina_id";
    if (isset($cache[$clave])) {
        return $cache[$clave];
    }
    $ids = [];
    $perfiles = ($usuario_id > 0 && $empresa_id > 0)
        ? array_map('intval', array_column(usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id), 'empresa_perfil_id'))
        : [];
    if ($perfiles) {
        $in = implode(',', array_fill(0, count($perfiles), '?'));
        $stmt = mysqli_prepare($conexion, "SELECT DISTINCT pf.pagina_funcion_id
                                           FROM conf__empresas_perfiles_funciones epf
                                           JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
                                           WHERE epf.empresa_perfil_id IN ($in) AND epf.asignado = 1 AND pf.pagina_id = ?");
        $params = array_merge($perfiles, [$pagina_id]);
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($params)), ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_row($res)) {
            $ids[(int) $row[0]] = true;
        }
        mysqli_stmt_close($stmt);
    }
    return $cache[$clave] = $ids;
}

/**
 * Deja de una lista de funciones (filas de conf__paginas_funciones con pagina_funcion_id y pagina_id) solo las
 * que el perfil del usuario tiene asignadas. Es el filtro "por perfil" de los botones; el filtro "por estado"
 * (origen = estado del registro) lo sigue haciendo cada obtenerBotonesPorEstado. Sin usuario no queda ninguna.
 */
function permisos_filtrar_funciones(mysqli $conexion, array $funciones, ?int $pagina_id = null): array
{
    [$usuario_id, $empresa_id] = permisos_contexto();
    if (permisos_es_superadmin($usuario_id)) {
        return $funciones;
    }
    $filtradas = [];
    foreach ($funciones as $clave => $f) {
        $pid = $pagina_id ?? (int) ($f['pagina_id'] ?? 0);
        $permitidas = usuario_funciones_pagina_ids($conexion, $usuario_id, $empresa_id, $pid);
        if ($permitidas === null || isset($permitidas[(int) ($f['pagina_funcion_id'] ?? 0)])) {
            $filtradas[$clave] = $f;
        }
    }
    // Lista (0..n-1) sigue siendo lista; un arreglo indexado por id conserva sus claves
    return array_keys($funciones) === range(0, count($funciones) - 1) ? array_values($filtradas) : $filtradas;
}

/**
 * Para obtenerBotonAgregar: true si el usuario no tiene ninguna función de creación (origen 0) entre las
 * funciones ya filtradas, y entonces no se le muestra el botón (ni el botón por defecto). El superadmin
 * conserva el botón por defecto de las páginas que no tienen función de creación configurada.
 */
function permisos_sin_funcion_creacion(array $funciones_filtradas): bool
{
    if (permisos_es_superadmin()) {
        return false;
    }
    foreach ($funciones_filtradas as $f) {
        if ((int) ($f['tabla_estado_registro_origen_id'] ?? -1) === 0) {
            return false;
        }
    }
    return true;
}

/**
 * Filtro por perfil para botones armados a mano (sin leer conf__paginas_funciones): ¿puede el usuario del
 * contexto la función accion_js de la página? Cachea por request.
 */
function permisos_puede(mysqli $conexion, int $pagina_id, string $accion_js): bool
{
    [$usuario_id, $empresa_id] = permisos_contexto();
    static $cache = [];
    $clave = "$usuario_id|$empresa_id|$pagina_id|$accion_js";
    if (!isset($cache[$clave])) {
        $cache[$clave] = usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_id, $accion_js);
    }
    return $cache[$clave];
}

/**
 * Regla CSS que oculta, en la página actual, todo elemento marcado data-funcion="accion_js" cuya función el perfil
 * no tiene (la pone header1.php). Sirve para botones armados a mano en el HTML o en el JS (también los que el JS
 * dibuja después, p. ej. en las filas de un DataTable): basta marcar el botón con data-funcion. Solo se ocultan las
 * funciones configuradas en la página; el _ajax.php valida la acción igual (permisos_exigir_accion).
 */
function permisos_css_funciones_ocultas(mysqli $conexion, int $pagina_id): string
{
    if ($pagina_id <= 0 || permisos_es_superadmin()) {
        return '';
    }
    $stmt = mysqli_prepare($conexion, "SELECT DISTINCT accion_js FROM conf__paginas_funciones
                                       WHERE pagina_id = ? AND tabla_estado_registro_id = 1 AND accion_js <> ''");
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $reglas = [];
    while ($row = mysqli_fetch_row($res)) {
        $accion = (string) $row[0];
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $accion) || permisos_puede($conexion, $pagina_id, $accion)) {
            continue;
        }
        $reglas[] = '[data-funcion="' . $accion . '"]';
    }
    mysqli_stmt_close($stmt);
    return $reglas ? implode(',', $reglas) . '{display:none !important}' : '';
}

/**
 * Para los _ajax.php con acciones propias (no el ejecutar_accion del motor): $mapa = ['accion del request' =>
 * 'accion_js de la función']. Si la acción figura en el mapa y el perfil no tiene la función, responde 403 JSON
 * {success:false, message} y termina. Las acciones fuera del mapa (listados, combos) no se tocan.
 */
function permisos_exigir_accion(mysqli $conexion, int $pagina_id, string $accion, array $mapa): void
{
    if (!isset($mapa[$accion]) || permisos_puede($conexion, $pagina_id, (string) $mapa[$accion])) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'resultado' => false, 'error' => 'Tu perfil no tiene permiso para esta acción.',
                      'message' => 'Tu perfil no tiene permiso para esta acción.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Para el ejecutar_accion del motor (y las acciones agregar / editar) de un _ajax.php: si el perfil del usuario no
 * tiene ninguna función con ese accion_js en la página, responde 403 JSON y termina. Es el control de servidor de
 * lo que los botones ya filtran en pantalla (el estado de origen lo sigue validando la transición del modelo).
 * $solo_si_configurada: para acciones que una página puede no tener como función (agregar / editar de un ABM
 * viejo): si la página no tiene ninguna función activa con ese accion_js, no se exige nada.
 */
function permisos_exigir_funcion(mysqli $conexion, int $pagina_id, string $accion_js, bool $solo_si_configurada = false): void
{
    if (permisos_es_superadmin() || permisos_puede($conexion, $pagina_id, $accion_js)) {
        return;
    }
    if ($solo_si_configurada) {
        $stmt = mysqli_prepare($conexion, "SELECT 1 FROM conf__paginas_funciones
                                           WHERE pagina_id = ? AND accion_js = ? AND tabla_estado_registro_id = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'is', $pagina_id, $accion_js);
        mysqli_stmt_execute($stmt);
        $existe = (bool) mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$existe) {
            return;
        }
    }
    permisos_exigir_accion($conexion, $pagina_id, $accion_js, [$accion_js => $accion_js]);
}

/**
 * Empresas y módulos habilitados para el usuario según los perfiles que tiene asignados:
 * no "todos los módulos habilitados en la empresa", sino específicamente el módulo de cada
 * conf__empresas_perfiles del usuario (una fila de conf__usuarios_perfiles apunta a un
 * empresa_perfil, y ese empresa_perfil tiene un modulo_id propio). Se usa tanto para el
 * selector de empresa/módulo del sidebar como para decidir el redirect automático de
 * index.php cuando el usuario solo tiene una combinación posible.
 *
 * @return array<int,array{nombre:string,modulos:array<int,array{id:int,nombre:string,url:string}>}>
 */
function usuario_obtener_empresas_modulos(mysqli $conexion, int $usuario_id): array
{
    $empresas_modulos = [];

    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        $res = mysqli_query($conexion, "
            SELECT e.empresa_id, e.empresa, m.modulo_id, m.modulo, m.modulo_url
            FROM conf__empresas e
            INNER JOIN conf__empresas_modulos em ON em.empresa_id = e.empresa_id AND em.tabla_estado_registro_id = 1
            INNER JOIN conf__modulos m ON m.modulo_id = em.modulo_id AND m.tabla_estado_registro_id = 1
            WHERE e.tabla_estado_registro_id = 1
            ORDER BY e.empresa, m.modulo
        ");
    } else {
        $sql = "SELECT DISTINCT
                    e.empresa_id, e.empresa,
                    m.modulo_id, m.modulo, m.modulo_url
                FROM conf__usuarios_perfiles up
                INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
                INNER JOIN conf__empresas e ON e.empresa_id = ep.empresa_id
                INNER JOIN conf__empresas_modulos em
                    ON em.empresa_id = ep.empresa_id AND em.modulo_id = ep.modulo_id
                    AND em.tabla_estado_registro_id = 1
                INNER JOIN conf__modulos m ON m.modulo_id = em.modulo_id AND m.tabla_estado_registro_id = 1
                WHERE up.usuario_id = ?
                  AND up.tabla_estado_registro_id = 1
                  AND ep.tabla_estado_registro_id = 1
                  AND e.tabla_estado_registro_id = 1
                  AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin
                ORDER BY e.empresa, m.modulo";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $usuario_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $eid = (int) $row['empresa_id'];
        if (!isset($empresas_modulos[$eid])) {
            $empresas_modulos[$eid] = [
                'nombre' => $row['empresa'],
                'modulos' => [],
            ];
        }
        $empresas_modulos[$eid]['modulos'][] = [
            'id' => (int) $row['modulo_id'],
            'nombre' => $row['modulo'],
            'url' => $row['modulo_url'],
        ];
    }

    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }

    return $empresas_modulos;
}
