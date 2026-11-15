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
                case 'alta':
                    $id = vcar_alta_vendedor($conexion, $empresa_id, [
                        'entidad_id' => intval($_POST['entidad_id'] ?? 0), 'entidad_nombre' => $_POST['entidad_nombre'] ?? '', 'cuit' => $_POST['cuit'] ?? '',
                    ], $usuario_id);
                    if (intval($_POST['usuario_id'] ?? 0) > 0) {
                        vcar_ligar_usuario($conexion, $empresa_id, $id, intval($_POST['usuario_id']), $fecha, $usuario_id);
                    }
                    $res = $ok(['vendedor_entidad_id' => $id], 'Vendedor agregado');
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
