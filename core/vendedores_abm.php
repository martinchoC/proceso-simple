<?php
/**
 * Página "Vendedores y carteras" (147 / 647, paso 5a-2 de la distribuidora): acciones del _ajax.php de cada módulo.
 * Reglas de negocio en core/vendedores.php (vcar_*). Vista: templates/vendedores/pagina.php; JS: assets/js/vendedores.js.
 * Respuesta {success, data, message}. Las escrituras van en una transacción.
 */

require_once __DIR__ . '/vendedores.php';

if (!function_exists('vabm_buscar_entidades')) {
    /** Entidades activas de la empresa por nombre / fantasía / CUIT (máx. 30), con sus roles y si ya son vendedores. $rol: solo las que lo tienen. */
    function vabm_buscar_entidades(mysqli $conexion, int $empresa_id, string $q, string $rol = ''): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . $q . '%';
        $cond_rol = in_array($rol, ['CLI', 'VEN', 'EMP'], true) ? ' AND ' . ent_sql_tiene_rol('e.entidad_id', $rol) : '';
        return vcar_filas($conexion,
            "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit,
                    IF(" . ent_sql_tiene_rol('e.entidad_id', 'VEN') . ", 1, 0) AS es_vendedor,
                    IF(" . ent_sql_tiene_rol('e.entidad_id', 'CLI') . ", 1, 0) AS es_cliente
               FROM gestion__entidades e
              WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 $cond_rol
                AND (e.entidad_nombre LIKE ? OR IFNULL(e.entidad_fantasia, '') LIKE ? OR IFNULL(e.cuit, '') LIKE ?)
              ORDER BY e.entidad_nombre LIMIT 30", 'isss', [$empresa_id, $like, $like, $like]);
    }
}

if (!function_exists('vabm_usuarios')) {
    /** Usuarios con perfil vigente en la empresa (para ligarlos a un vendedor), con el vendedor que ya son. */
    function vabm_usuarios(mysqli $conexion, int $empresa_id): array
    {
        $hoy = date('Y-m-d');
        return vcar_filas($conexion,
            "SELECT DISTINCT u.usuario_id, u.usuario, u.usuario_nombre,
                    (SELECT vu.vendedor_entidad_id FROM gestion__vendedores_usuarios vu
                      WHERE vu.empresa_id = ep.empresa_id AND vu.usuario_id = u.usuario_id AND vu.tabla_estado_registro_id = 1
                        AND vu.f_desde <= ? AND (vu.f_hasta IS NULL OR vu.f_hasta >= ?) ORDER BY vu.f_desde DESC LIMIT 1) AS vendedor_entidad_id
               FROM conf__usuarios u
               JOIN conf__usuarios_perfiles up ON up.usuario_id = u.usuario_id AND up.tabla_estado_registro_id = 1
                    AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin
               JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
              WHERE ep.empresa_id = ? AND u.tabla_estado_registro_id = 1
              ORDER BY u.usuario_nombre, u.usuario", 'ssi', [$hoy, $hoy, $empresa_id]);
    }
}

if (!function_exists('vabm_perfiles')) {
    /** Perfiles activos de la empresa (opcionalmente del módulo actual) para asignar al crear un usuario nuevo. */
    function vabm_perfiles(mysqli $conexion, int $empresa_id, int $modulo_id = 0): array
    {
        $sql = "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre, ep.modulo_id, m.modulo
                  FROM conf__empresas_perfiles ep
                  LEFT JOIN conf__modulos m ON m.modulo_id = ep.modulo_id
                 WHERE ep.empresa_id = ? AND ep.tabla_estado_registro_id = 1";
        $tipos = 'i';
        $params = [$empresa_id];
        if ($modulo_id > 0) {
            $sql .= " AND ep.modulo_id = ?";
            $tipos .= 'i';
            $params[] = $modulo_id;
        }
        $sql .= " ORDER BY (ep.empresa_perfil_nombre = 'Ventas') DESC, ep.empresa_perfil_nombre";
        return vcar_filas($conexion, $sql, $tipos, $params);
    }
}

if (!function_exists('vabm_crear_usuario')) {
    /**
     * Crea un usuario nuevo en conf__usuarios y le asigna el perfil de la empresa en conf__usuarios_perfiles.
     * Devuelve el usuario_id creado.
     * @throws InvalidArgumentException
     */
    function vabm_crear_usuario(mysqli $conexion, int $empresa_id, int $modulo_id, array $datos, string $fecha, int $creado_por): int
    {
        $login = trim((string) ($datos['usuario'] ?? ''));
        $nombre = mb_substr(trim((string) ($datos['usuario_nombre'] ?? '')), 0, 100);
        $email = mb_substr(trim((string) ($datos['email'] ?? '')), 0, 100);
        $password = (string) ($datos['password'] ?? '');
        $empresa_perfil_id = intval($datos['empresa_perfil_id'] ?? 0);

        if ($login === '') {
            throw new InvalidArgumentException('Escribí el usuario con el que va a ingresar al sistema.');
        }
        if (mb_strlen($login) > 20 || preg_match('/\s/', $login)) {
            throw new InvalidArgumentException('El usuario de ingreso no puede tener espacios ni superar 20 caracteres.');
        }
        if ($nombre === '') {
            throw new InvalidArgumentException('Escribí el nombre completo del usuario.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Ingresá un email válido para el usuario.');
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
        }
        if ($empresa_perfil_id <= 0) {
            throw new InvalidArgumentException('Elegí un perfil de la empresa para el nuevo usuario.');
        }

        $perfil = vcar_filas($conexion,
            "SELECT empresa_perfil_id FROM conf__empresas_perfiles
              WHERE empresa_perfil_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1"
            . ($modulo_id > 0 ? " AND modulo_id = ?" : "") . " LIMIT 1",
            $modulo_id > 0 ? 'iii' : 'ii',
            $modulo_id > 0 ? [$empresa_perfil_id, $empresa_id, $modulo_id] : [$empresa_perfil_id, $empresa_id]);
        if (!$perfil) {
            throw new InvalidArgumentException('El perfil elegido no pertenece a esta empresa.');
        }

        if (vcar_filas($conexion, "SELECT 1 FROM conf__usuarios WHERE usuario = ? LIMIT 1", 's', [$login])) {
            throw new InvalidArgumentException("Ya existe un usuario '$login'. Elegí otro nombre de usuario o seleccionalo de la lista.");
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $duracion = 60;
        $nuevo_usuario_id = vcar_ejecutar($conexion,
            "INSERT INTO conf__usuarios (usuario_nombre, usuario, email, password, duracion_sid_minutos, tabla_estado_registro_id)
             VALUES (?, ?, ?, ?, ?, 1)",
            'ssssi', [$nombre, $login, $email, $hash, $duracion], true);

        $hoy = date('Y-m-d');
        $f_inicio = $fecha < $hoy ? $fecha : $hoy;
        vcar_ejecutar($conexion,
            "INSERT INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, usuario_creacion, tabla_estado_registro_id)
             VALUES (?, ?, ?, '2099-12-31', ?, 1)",
            'iisi', [$nuevo_usuario_id, $empresa_perfil_id, $f_inicio, $creado_por], true);

        if (function_exists('auditoria_registrar_alta')) {
            auditoria_registrar_alta($conexion, 'conf__usuarios', $nuevo_usuario_id, [
                'usuario_id' => $nuevo_usuario_id,
                'usuario_nombre' => $nombre,
                'usuario' => $login,
                'email' => $email,
                'empresa_perfil_id' => $empresa_perfil_id,
            ], "Alta de usuario '{$nombre}' ({$login}) desde Vendedores");
        }

        return $nuevo_usuario_id;
    }
}

if (!function_exists('vabm_ids')) {
    /** Lista de ids desde un JSON o un array del request. */
    function vabm_ids($valor): array
    {
        if (is_string($valor)) {
            $d = json_decode($valor, true);
            $valor = is_array($d) ? $d : explode(',', $valor);
        }
        return is_array($valor) ? array_values(array_unique(array_filter(array_map('intval', $valor)))) : [];
    }
}

if (!function_exists('vabm_despachar')) {
    function vabm_despachar(mysqli $conexion, array $ctx, string $accion): array
    {
        $empresa_id = (int) $ctx['empresa_id'];
        $usuario_id = (int) $ctx['usuario_id'];
        if (!vcar_estructura($conexion)) {
            return ['success' => false, 'data' => null, 'message' => 'Falta aplicar el script de vendedores y carteras.'];
        }
        $ok = fn($data, $msg = '') => ['success' => true, 'data' => $data, 'message' => $msg];
        $R = $_REQUEST;
        $fecha = vcar_fecha($R['fecha'] ?? null);
        $escritura = in_array($accion, ['alta', 'ligar_usuario', 'asignar', 'quitar', 'reasignar', 'baja'], true);
        if ($escritura) {
            mysqli_begin_transaction($conexion);
        }
        try {
            $res = null;
            switch ($accion) {
                case 'listar':
                    $res = $ok(['vendedores' => vcar_vendedores($conexion, $empresa_id, $fecha, !empty($R['incluir_bajas'])),
                                'activo' => vcar_empresa_usa($conexion, $empresa_id)]);
                    break;
                case 'cartera':
                    $res = $ok(vcar_cartera($conexion, $empresa_id, intval($R['vendedor_entidad_id'] ?? 0), $fecha));
                    break;
                case 'sin_vendedor':
                    $res = $ok(vcar_clientes_sin_vendedor($conexion, $empresa_id, $fecha, (string) ($R['q'] ?? ''), 300));
                    break;
                case 'historial':
                    $res = $ok(vcar_historial_cliente($conexion, $empresa_id, intval($R['cliente_entidad_id'] ?? 0)));
                    break;
                case 'buscar_entidades':
                    $res = $ok(vabm_buscar_entidades($conexion, $empresa_id, (string) ($R['q'] ?? ''), (string) ($R['rol'] ?? '')));
                    break;
                case 'usuarios':
                    $res = $ok(vabm_usuarios($conexion, $empresa_id));
                    break;
                case 'perfiles':
                    $res = $ok(vabm_perfiles($conexion, $empresa_id, (int) ($ctx['modulo_id'] ?? 0)));
                    break;
                case 'alta':
                    $id = vcar_alta_vendedor($conexion, $empresa_id, [
                        'entidad_id' => intval($_POST['entidad_id'] ?? 0), 'entidad_nombre' => $_POST['entidad_nombre'] ?? '', 'cuit' => $_POST['cuit'] ?? '',
                    ], $usuario_id);
                    $vincular_usuario_id = intval($_POST['usuario_id'] ?? 0);
                    if (!empty($_POST['crear_usuario'])) {
                        $vincular_usuario_id = vabm_crear_usuario($conexion, $empresa_id, (int) ($ctx['modulo_id'] ?? 0), [
                            'usuario' => $_POST['nuevo_usuario'] ?? '',
                            'usuario_nombre' => $_POST['nuevo_usuario_nombre'] ?? '',
                            'email' => $_POST['nuevo_usuario_email'] ?? '',
                            'password' => $_POST['nuevo_usuario_password'] ?? '',
                            'empresa_perfil_id' => $_POST['nuevo_usuario_perfil_id'] ?? 0,
                        ], $fecha, $usuario_id);
                    }
                    if ($vincular_usuario_id > 0) {
                        vcar_ligar_usuario($conexion, $empresa_id, $id, $vincular_usuario_id, $fecha, $usuario_id);
                    }
                    $res = $ok(['vendedor_entidad_id' => $id, 'usuario_id' => $vincular_usuario_id], !empty($_POST['crear_usuario']) ? 'Vendedor y usuario agregados' : 'Vendedor agregado');
                    break;
                case 'ligar_usuario':
                    vcar_ligar_usuario($conexion, $empresa_id, intval($_POST['vendedor_entidad_id'] ?? 0), intval($_POST['usuario_id'] ?? 0), $fecha, $usuario_id);
                    $res = $ok(null, 'Guardado');
                    break;
                case 'asignar':
                    $r = vcar_asignar($conexion, $empresa_id, vabm_ids($_POST['clientes'] ?? []), intval($_POST['vendedor_entidad_id'] ?? 0), $fecha, $usuario_id);
                    $res = $ok($r, $r['asignados'] . ' cliente(s) asignado(s)' . ($r['sin_cambios'] ? ', ' . $r['sin_cambios'] . ' ya eran de ese vendedor' : ''));
                    break;
                case 'quitar':
                    $n = vcar_quitar($conexion, $empresa_id, vabm_ids($_POST['clientes'] ?? []), $fecha);
                    $res = $ok(['quitados' => $n], $n . ' cliente(s) sin vendedor desde ' . (function_exists('fmt_fecha') ? fmt_fecha($fecha) : $fecha));
                    break;
                case 'reasignar':
                    $r = vcar_reasignar_cartera($conexion, $empresa_id, intval($_POST['de'] ?? 0), intval($_POST['a'] ?? 0), $fecha, $usuario_id);
                    $res = $ok($r, $r['asignados'] . ' cliente(s) pasados');
                    break;
                case 'baja':
                    vcar_baja_vendedor($conexion, $empresa_id, intval($_POST['vendedor_entidad_id'] ?? 0), $fecha);
                    $res = $ok(null, 'Vendedor dado de baja');
                    break;
                default:
                    $res = ['success' => false, 'data' => null, 'message' => 'Acción desconocida.'];
            }
            if ($escritura) {
                mysqli_commit($conexion);
            }
            return $res;
        } catch (InvalidArgumentException $e) {
            if ($escritura) {
                mysqli_rollback($conexion);
            }
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($escritura) {
                mysqli_rollback($conexion);
            }
            error_log('vabm_despachar ' . $accion . ': ' . $e->getMessage());
            return ['success' => false, 'data' => null, 'message' => 'No se pudo completar la operación.'];
        }
    }
}
