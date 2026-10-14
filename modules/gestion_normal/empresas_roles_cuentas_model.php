<?php
/**
 * Modelo para ABM de Roles de Entidades y Cuentas Contables por Empresa
 * Tabla principal: gestion__empresas_roles_cuentas
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/entidades_roles_helper.php';

if (!function_exists('obtenerEmpresasUsuarioRoles')) {
    function obtenerEmpresasUsuarioRoles($conexion, $empresa_activa_id = 0)
    {
        $sql = "SELECT empresa_id, nombre, razon_social 
                FROM conf__empresas 
                WHERE tabla_estado_registro_id = 1 
                ORDER BY nombre ASC";
        $res = mysqli_query($conexion, $sql);
        $empresas = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $empresas[] = $row;
            }
        }
        return $empresas;
    }
}

function obtenerRolesCuentasPorEmpresa($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    if ($empresa_id <= 0) return [];

    $sql = "SELECT erc.empresa_rol_cuenta_id, erc.empresa_id, erc.rol_entidad_id, erc.cont_cuenta_id, 
                   erc.tabla_estado_registro_id, erc.fecha_creacion, erc.fecha_modificacion,
                   r.rol_entidad_codigo, r.rol_entidad_nombre, r.rol_entidad_descripcion,
                   cc.codigo AS cuenta_codigo, cc.nombre AS cuenta_nombre,
                   er.estado_registro AS estado_nombre
            FROM gestion__empresas_roles_cuentas erc
            JOIN gestion__roles_entidades r ON erc.rol_entidad_id = r.rol_entidad_id
            LEFT JOIN gestion__cont_cuentas cc ON erc.cont_cuenta_id = cc.cont_cuenta_id
            LEFT JOIN conf__estados_registros er ON erc.tabla_estado_registro_id = er.estado_registro_id
            WHERE erc.empresa_id = ?
            ORDER BY r.rol_entidad_codigo ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['cuenta_descripcion'] = !empty($row['cuenta_codigo']) 
            ? ($row['cuenta_codigo'] . ' - ' . $row['cuenta_nombre']) 
            : '<span class="text-muted fst-italic">Sin cuenta asignada</span>';
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

function obtenerRolesDisponiblesParaEmpresa($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT r.rol_entidad_id, r.rol_entidad_codigo, r.rol_entidad_nombre, r.rol_entidad_descripcion
            FROM gestion__roles_entidades r
            WHERE r.rol_entidad_id NOT IN (
                SELECT erc.rol_entidad_id 
                FROM gestion__empresas_roles_cuentas erc 
                WHERE erc.empresa_id = ? AND erc.tabla_estado_registro_id = 1
            )
            ORDER BY r.rol_entidad_nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $roles = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $roles[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $roles;
}

function obtenerCuentasContablesEmpresaSelect($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    if ($empresa_id <= 0) return [];

    $sql = "SELECT cont_cuenta_id, codigo, nombre, nivel 
            FROM gestion__cont_cuentas 
            WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1
            ORDER BY codigo ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cuentas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $cuentas[] = [
            'cont_cuenta_id' => intval($row['cont_cuenta_id']),
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'nivel' => intval($row['nivel']),
            'descripcion_completa' => $row['codigo'] . ' - ' . $row['nombre']
        ];
    }
    mysqli_stmt_close($stmt);
    return $cuentas;
}

function obtenerRolEmpresaPorId($conexion, $empresa_rol_cuenta_id, $empresa_id)
{
    $id = intval($empresa_rol_cuenta_id);
    $empresa_id = intval($empresa_id);

    $sql = "SELECT erc.*, r.rol_entidad_codigo, r.rol_entidad_nombre, r.rol_entidad_descripcion,
                   cc.codigo AS cuenta_codigo, cc.nombre AS cuenta_nombre
            FROM gestion__empresas_roles_cuentas erc
            JOIN gestion__roles_entidades r ON erc.rol_entidad_id = r.rol_entidad_id
            LEFT JOIN gestion__cont_cuentas cc ON erc.cont_cuenta_id = cc.cont_cuenta_id
            WHERE erc.empresa_rol_cuenta_id = ? AND erc.empresa_id = ? LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $item;
}

function guardarRolEmpresa($conexion, $data)
{
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $rol_entidad_id = intval($data['rol_entidad_id'] ?? 0);
    $cont_cuenta_id = !empty($data['cont_cuenta_id']) ? intval($data['cont_cuenta_id']) : null;
    $crear_nuevo_rol = !empty($data['crear_nuevo_rol']) ? true : false;

    if ($empresa_id <= 0) {
        return ['resultado' => false, 'error' => 'Empresa inválida o no seleccionada'];
    }

    // Si se crea un nuevo tipo de rol en el catálogo
    if ($crear_nuevo_rol) {
        $codigo = strtoupper(trim((string)($data['nuevo_rol_codigo'] ?? '')));
        $nombre = trim((string)($data['nuevo_rol_nombre'] ?? ''));
        $descripcion = trim((string)($data['nuevo_rol_descripcion'] ?? ''));

        if (empty($codigo)) {
            return ['resultado' => false, 'error' => 'El código del nuevo rol es obligatorio (ej: COM, ASOC)'];
        }
        if (empty($nombre)) {
            return ['resultado' => false, 'error' => 'El nombre del nuevo rol es obligatorio'];
        }

        // Verificar si el código ya existe
        $stmtChk = mysqli_prepare($conexion, "SELECT rol_entidad_id FROM gestion__roles_entidades WHERE rol_entidad_codigo = ? LIMIT 1");
        mysqli_stmt_bind_param($stmtChk, "s", $codigo);
        mysqli_stmt_execute($stmtChk);
        $resChk = mysqli_stmt_get_result($stmtChk);
        $existenteRol = mysqli_fetch_assoc($resChk);
        mysqli_stmt_close($stmtChk);

        if ($existenteRol) {
            $rol_entidad_id = intval($existenteRol['rol_entidad_id']);
        } else {
            $stmtInsRol = mysqli_prepare($conexion, "INSERT INTO gestion__roles_entidades (rol_entidad_codigo, rol_entidad_nombre, rol_entidad_descripcion) VALUES (?, ?, ?)");
            if (!$stmtInsRol) {
                return ['resultado' => false, 'error' => 'Error al preparar inserción del nuevo rol'];
            }
            mysqli_stmt_bind_param($stmtInsRol, "sss", $codigo, $nombre, $descripcion);
            if (!mysqli_stmt_execute($stmtInsRol)) {
                $err = mysqli_stmt_error($stmtInsRol);
                mysqli_stmt_close($stmtInsRol);
                return ['resultado' => false, 'error' => 'Error al registrar el nuevo rol: ' . $err];
            }
            $rol_entidad_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtInsRol);
        }
    }

    if ($rol_entidad_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar o especificar un rol de entidad'];
    }

    if (empty($cont_cuenta_id)) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la cuenta contable genérica para este rol'];
    }

    // Verificar si ya existe registro para esta empresa y rol
    $stmtCheck = mysqli_prepare($conexion, "SELECT empresa_rol_cuenta_id FROM gestion__empresas_roles_cuentas WHERE empresa_id = ? AND rol_entidad_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtCheck, "ii", $empresa_id, $rol_entidad_id);
    mysqli_stmt_execute($stmtCheck);
    $resCheck = mysqli_stmt_get_result($stmtCheck);
    $existente = mysqli_fetch_assoc($resCheck);
    mysqli_stmt_close($stmtCheck);

    if ($existente) {
        // Reactivar y actualizar cuenta
        $stmtUpd = mysqli_prepare($conexion, "UPDATE gestion__empresas_roles_cuentas SET cont_cuenta_id = ?, tabla_estado_registro_id = 1 WHERE empresa_rol_cuenta_id = ?");
        mysqli_stmt_bind_param($stmtUpd, "ii", $cont_cuenta_id, $existente['empresa_rol_cuenta_id']);
        $ok = mysqli_stmt_execute($stmtUpd);
        mysqli_stmt_close($stmtUpd);
        return [
            'resultado' => $ok,
            'mensaje' => $ok ? 'Rol y cuenta contable configurados con éxito' : 'Error al actualizar',
            'empresa_rol_cuenta_id' => $existente['empresa_rol_cuenta_id']
        ];
    } else {
        $stmtIns = mysqli_prepare($conexion, "INSERT INTO gestion__empresas_roles_cuentas (empresa_id, rol_entidad_id, cont_cuenta_id, tabla_estado_registro_id) VALUES (?, ?, ?, 1)");
        if (!$stmtIns) {
            return ['resultado' => false, 'error' => 'Error al preparar alta de configuración'];
        }
        mysqli_stmt_bind_param($stmtIns, "iii", $empresa_id, $rol_entidad_id, $cont_cuenta_id);
        $ok = mysqli_stmt_execute($stmtIns);
        $newId = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmtIns);
        return [
            'resultado' => $ok,
            'mensaje' => $ok ? 'Rol dado de alta para la empresa con éxito' : 'Error al guardar',
            'empresa_rol_cuenta_id' => $newId
        ];
    }
}

function editarRolEmpresa($conexion, $empresa_rol_cuenta_id, $data)
{
    $id = intval($empresa_rol_cuenta_id);
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $cont_cuenta_id = !empty($data['cont_cuenta_id']) ? intval($data['cont_cuenta_id']) : null;
    $estado_id = isset($data['tabla_estado_registro_id']) ? intval($data['tabla_estado_registro_id']) : 1;

    if ($id <= 0 || $empresa_id <= 0) {
        return ['resultado' => false, 'error' => 'Identificador inválido'];
    }
    if (empty($cont_cuenta_id)) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una cuenta contable genérica válida'];
    }

    $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_roles_cuentas SET cont_cuenta_id = ?, tabla_estado_registro_id = ? WHERE empresa_rol_cuenta_id = ? AND empresa_id = ?");
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }
    mysqli_stmt_bind_param($stmt, "iiii", $cont_cuenta_id, $estado_id, $id, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return [
        'resultado' => $ok,
        'mensaje' => $ok ? 'Configuración de cuenta contable actualizada con éxito' : 'Error al actualizar'
    ];
}

function cambiarEstadoRolEmpresa($conexion, $empresa_rol_cuenta_id, $empresa_id, $nuevo_estado_id)
{
    $id = intval($empresa_rol_cuenta_id);
    $empresa_id = intval($empresa_id);
    $nuevo_estado_id = intval($nuevo_estado_id);

    $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_roles_cuentas SET tabla_estado_registro_id = ? WHERE empresa_rol_cuenta_id = ? AND empresa_id = ?");
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado_id, $id, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $msg = ($nuevo_estado_id === 1) ? 'Rol habilitado con éxito' : 'Rol inhabilitado con éxito';
    return ['resultado' => $ok, 'mensaje' => $ok ? $msg : 'Error al modificar estado'];
}
