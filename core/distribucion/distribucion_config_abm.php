<?php
/**
 * Página "Distribución - Configuración" (148 / 648, paso 5b-2): acciones del _ajax.php de cada módulo.
 * Reglas en distribucion_service.php (vdis_*). Vista: templates/distribucion/config.php; JS: assets/js/distribucion-config.js.
 * Respuesta {success, data, message}. Las escrituras van en una transacción.
 */

require_once __DIR__ . '/distribucion_service.php';

if (!function_exists('vdcf_buscar_clientes')) {
    /** Clientes activos por nombre / fantasía / CUIT (máx. 30), con su zona de hoy. */
    function vdcf_buscar_clientes(mysqli $conexion, int $empresa_id, string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . $q . '%';
        $filas = vcar_filas($conexion,
            "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit FROM gestion__entidades e
              WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI') . "
                AND (e.entidad_nombre LIKE ? OR IFNULL(e.entidad_fantasia, '') LIKE ? OR IFNULL(e.cuit, '') LIKE ?)
              ORDER BY e.entidad_nombre LIMIT 30", 'isss', [$empresa_id, $like, $like, $like]);
        foreach ($filas as &$f) {
            $z = vdis_zona_de_cliente($conexion, $empresa_id, (int) $f['entidad_id']);
            $f['zona_nombre'] = $z['nombre'] ?? null;
        }
        unset($f);
        return $filas;
    }
}

if (!function_exists('vdcf_despachar')) {
    function vdcf_despachar(mysqli $conexion, array $ctx, string $accion): array
    {
        $empresa_id = (int) $ctx['empresa_id'];
        $usuario_id = (int) $ctx['usuario_id'];
        if (!vdis_estructura($conexion)) {
            return ['success' => false, 'data' => null, 'message' => 'Falta aplicar el script de distribución.'];
        }
        $ok = fn($data, $msg = '') => ['success' => true, 'data' => $data, 'message' => $msg];
        $R = $_REQUEST;
        $fecha = vcar_fecha($R['fecha'] ?? null);
        $escritura = in_array($accion, ['zona_agregar', 'zona_editar', 'zona_estado', 'zona_asignar', 'zona_quitar', 'camion_agregar', 'camion_editar', 'camion_estado'], true);
        if ($escritura) {
            mysqli_begin_transaction($conexion);
        }
        try {
            switch ($accion) {
                case 'catalogos':
                    $res = $ok(['depositos' => vdis_depositos($conexion, $empresa_id), 'activo' => vdis_empresa_usa($conexion, $empresa_id)]);
                    break;
                case 'zonas_listar':
                    $res = $ok(vdis_zonas($conexion, $empresa_id, !empty($R['incluir_inactivas'])));
                    break;
                case 'zona_agregar':
                case 'zona_editar':
                    $d = $_POST;
                    if ($accion === 'zona_agregar') $d['zona_id'] = 0;
                    elseif (intval($d['zona_id'] ?? 0) <= 0) throw new InvalidArgumentException('Falta la zona.');
                    $res = $ok(['zona_id' => vdis_zona_guardar($conexion, $empresa_id, $d, $usuario_id)], 'Guardado');
                    break;
                case 'zona_estado':
                    vdis_zona_estado($conexion, $empresa_id, intval($_POST['zona_id'] ?? 0), intval($_POST['estado'] ?? 2));
                    $res = $ok(null, 'Guardado');
                    break;
                case 'zona_clientes':
                    $res = $ok(vdis_zona_clientes($conexion, $empresa_id, intval($R['zona_id'] ?? 0), $fecha));
                    break;
                case 'sin_zona':
                    $res = $ok(vdis_clientes_sin_zona($conexion, $empresa_id, $fecha, (string) ($R['q'] ?? '')));
                    break;
                case 'buscar_clientes':
                    $res = $ok(vdcf_buscar_clientes($conexion, $empresa_id, (string) ($R['q'] ?? '')));
                    break;
                case 'sucursales_cliente':
                    $res = $ok(vcar_filas($conexion,
                        "SELECT es.sucursal_id, es.sucursal_nombre, es.sucursal_direccion FROM gestion__entidades_sucursales es
                           JOIN gestion__entidades e ON e.entidad_id = es.entidad_id AND e.empresa_id = ?
                          WHERE es.entidad_id = ? AND es.tabla_estado_registro_id = 1 ORDER BY es.sucursal_numero",
                        'ii', [$empresa_id, intval($R['cliente_entidad_id'] ?? 0)]));
                    break;
                case 'zona_asignar':
                    $ids = json_decode((string) ($_POST['clientes'] ?? '[]'), true);
                    $r = vdis_zona_asignar($conexion, $empresa_id, is_array($ids) ? $ids : [], intval($_POST['zona_id'] ?? 0),
                        intval($_POST['entidad_sucursal_id'] ?? 0) ?: null, $fecha, $usuario_id);
                    $res = $ok($r, $r['asignados'] . ' cliente(s) en la zona' . ($r['sin_cambios'] ? ', ' . $r['sin_cambios'] . ' ya estaban' : ''));
                    break;
                case 'zona_quitar':
                    $ids = json_decode((string) ($_POST['clientes'] ?? '[]'), true);
                    vdis_zona_quitar($conexion, $empresa_id, is_array($ids) ? $ids : [], $fecha);
                    $res = $ok(null, 'Guardado');
                    break;
                case 'camiones_listar':
                    $res = $ok(vdis_camiones($conexion, $empresa_id, !empty($R['incluir_inactivos'])));
                    break;
                case 'camion_agregar':
                case 'camion_editar':
                    $d = $_POST;
                    if ($accion === 'camion_agregar') $d['camion_id'] = 0;
                    elseif (intval($d['camion_id'] ?? 0) <= 0) throw new InvalidArgumentException('Falta el camión.');
                    $res = $ok(['camion_id' => vdis_camion_guardar($conexion, $empresa_id, $d, $usuario_id)], 'Guardado');
                    break;
                case 'camion_estado':
                    vdis_camion_estado($conexion, $empresa_id, intval($_POST['camion_id'] ?? 0), intval($_POST['estado'] ?? 2));
                    $res = $ok(null, 'Guardado');
                    break;
                default:
                    $res = ['success' => false, 'data' => null, 'message' => 'Acción desconocida.'];
            }
            if ($escritura) {
                mysqli_commit($conexion);
            }
            return $res;
        } catch (InvalidArgumentException $e) {
            if ($escritura) mysqli_rollback($conexion);
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($escritura) mysqli_rollback($conexion);
            error_log('vdcf_despachar ' . $accion . ': ' . $e->getMessage());
            return ['success' => false, 'data' => null, 'message' => 'No se pudo completar la operación.'];
        }
    }
}
