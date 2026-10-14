<?php
/**
 * Helper de gestión de roles y cuentas contables asociadas por rol de entidad
 */

if (!function_exists('obtenerCatalogoRolesEntidad')) {
    function obtenerCatalogoRolesEntidad($conexion)
    {
        $sql = "SELECT rol_entidad_id, rol_entidad_codigo, rol_entidad_nombre, rol_entidad_descripcion, icono, orden 
                FROM gestion__roles_entidades 
                WHERE tabla_estado_registro_id = 1 
                ORDER BY orden ASC, rol_entidad_nombre ASC";
        $res = mysqli_query($conexion, $sql);
        $roles = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $roles[] = $row;
            }
        }
        return $roles;
    }
}

if (!function_exists('obtenerCuentasDefectoRolesEmpresa')) {
    function obtenerCuentasDefectoRolesEmpresa($conexion, $empresa_id)
    {
        $empresa_id = intval($empresa_id);
        if ($empresa_id <= 0) return [];

        $sql = "SELECT erc.rol_entidad_id, r.rol_entidad_codigo, r.rol_entidad_nombre, 
                       erc.cont_cuenta_id, cc.codigo as cuenta_codigo, cc.nombre as cuenta_nombre
                FROM gestion__empresas_roles_cuentas erc
                JOIN gestion__roles_entidades r ON erc.rol_entidad_id = r.rol_entidad_id
                LEFT JOIN gestion__cont_cuentas cc ON erc.cont_cuenta_id = cc.cont_cuenta_id
                WHERE erc.empresa_id = ? AND erc.tabla_estado_registro_id = 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return [];

        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $cuentas = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $cuentas[$row['rol_entidad_codigo']] = [
                'rol_entidad_id' => intval($row['rol_entidad_id']),
                'rol_entidad_codigo' => $row['rol_entidad_codigo'],
                'rol_entidad_nombre' => $row['rol_entidad_nombre'],
                'cont_cuenta_id' => intval($row['cont_cuenta_id']),
                'cuenta_codigo' => $row['cuenta_codigo'],
                'cuenta_nombre' => $row['cuenta_nombre'],
                'descripcion_completa' => trim(($row['cuenta_codigo'] ?? '') . ' - ' . ($row['cuenta_nombre'] ?? ''))
            ];
        }
        mysqli_stmt_close($stmt);
        return $cuentas;
    }
}

if (!function_exists('obtenerRolesEntidad')) {
    function obtenerRolesEntidad($conexion, $entidad_id)
    {
        $entidad_id = intval($entidad_id);
        if ($entidad_id <= 0) return [];

        $sql = "SELECT r.rol_entidad_id, r.rol_entidad_codigo, r.rol_entidad_nombre, r.rol_entidad_descripcion, 
                       er.cont_cuenta_id, cc.codigo as cuenta_codigo, cc.nombre as cuenta_nombre, er.f_alta
                FROM gestion__entidades_roles er
                JOIN gestion__roles_entidades r ON er.rol_entidad_id = r.rol_entidad_id
                LEFT JOIN gestion__cont_cuentas cc ON er.cont_cuenta_id = cc.cont_cuenta_id
                WHERE er.entidad_id = ? AND er.f_baja IS NULL AND er.tabla_estado_registro_id = 1
                ORDER BY r.rol_entidad_id";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return [];

        mysqli_stmt_bind_param($stmt, "i", $entidad_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $roles = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $roles[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $roles;
    }
}

if (!function_exists('sincronizarRolesEntidad')) {
    function sincronizarRolesEntidad($conexion, $entidad_id, $roles, $roles_cuentas = [], $empresa_id = null)
    {
        $entidad_id = intval($entidad_id);
        if ($entidad_id <= 0) return false;

        if (empty($empresa_id)) {
            $qEmp = mysqli_query($conexion, "SELECT empresa_id FROM gestion__entidades WHERE entidad_id = $entidad_id LIMIT 1");
            if ($qEmp && $rEmp = mysqli_fetch_assoc($qEmp)) {
                $empresa_id = intval($rEmp['empresa_id']);
            }
        }

        $deseados = [];
        if (is_array($roles)) {
            foreach ($roles as $r) {
                $r = strtoupper(trim((string)$r));
                if ($r !== '') $deseados[] = $r;
            }
        }
        $deseados = array_unique($deseados);

        $map = [];
        $res = mysqli_query($conexion, "SELECT rol_entidad_id, rol_entidad_codigo FROM gestion__roles_entidades");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $map[$row['rol_entidad_codigo']] = intval($row['rol_entidad_id']);
            }
        }

        $cuentas_defecto = [];
        if (!empty($empresa_id)) {
            $cuentas_defecto = obtenerCuentasDefectoRolesEmpresa($conexion, $empresa_id);
        }

        $actuales = [];
        $stmt = mysqli_prepare($conexion, "SELECT er.entidad_rol_id, r.rol_entidad_codigo, er.cont_cuenta_id 
                                           FROM gestion__entidades_roles er 
                                           JOIN gestion__roles_entidades r ON er.rol_entidad_id = r.rol_entidad_id 
                                           WHERE er.entidad_id = ? AND er.f_baja IS NULL");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $entidad_id);
            mysqli_stmt_execute($stmt);
            $resActuales = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($resActuales)) {
                $actuales[$row['rol_entidad_codigo']] = [
                    'entidad_rol_id' => intval($row['entidad_rol_id']),
                    'cont_cuenta_id' => !empty($row['cont_cuenta_id']) ? intval($row['cont_cuenta_id']) : null
                ];
            }
            mysqli_stmt_close($stmt);
        }

        foreach ($actuales as $cod => $infoRol) {
            if (!in_array($cod, $deseados)) {
                $stmtBaja = mysqli_prepare($conexion, "UPDATE gestion__entidades_roles SET f_baja = CURDATE() WHERE entidad_rol_id = ?");
                if ($stmtBaja) {
                    mysqli_stmt_bind_param($stmtBaja, "i", $infoRol['entidad_rol_id']);
                    mysqli_stmt_execute($stmtBaja);
                    mysqli_stmt_close($stmtBaja);
                }
            }
        }

        $cuenta_cli = null;
        $cuenta_pro = null;

        foreach ($deseados as $cod) {
            if (!isset($map[$cod])) continue;
            $rol_id = $map[$cod];

            $cuenta_id = null;
            if (isset($roles_cuentas[$cod]) && !empty($roles_cuentas[$cod])) {
                $cuenta_id = intval($roles_cuentas[$cod]);
            } elseif (isset($actuales[$cod]['cont_cuenta_id']) && !empty($actuales[$cod]['cont_cuenta_id'])) {
                $cuenta_id = $actuales[$cod]['cont_cuenta_id'];
            } elseif (isset($cuentas_defecto[$cod]['cont_cuenta_id'])) {
                $cuenta_id = intval($cuentas_defecto[$cod]['cont_cuenta_id']);
            }

            if ($cod === 'CLI') $cuenta_cli = $cuenta_id;
            if ($cod === 'PRO') $cuenta_pro = $cuenta_id;

            $stmtCheck = mysqli_prepare($conexion, "SELECT entidad_rol_id FROM gestion__entidades_roles WHERE entidad_id = ? AND rol_entidad_id = ? LIMIT 1");
            if ($stmtCheck) {
                mysqli_stmt_bind_param($stmtCheck, "ii", $entidad_id, $rol_id);
                mysqli_stmt_execute($stmtCheck);
                $resCheck = mysqli_stmt_get_result($stmtCheck);
                $existente = mysqli_fetch_assoc($resCheck);
                mysqli_stmt_close($stmtCheck);

                if ($existente) {
                    $stmtUpd = mysqli_prepare($conexion, "UPDATE gestion__entidades_roles SET f_baja = NULL, tabla_estado_registro_id = 1, cont_cuenta_id = ? WHERE entidad_rol_id = ?");
                    if ($stmtUpd) {
                        mysqli_stmt_bind_param($stmtUpd, "ii", $cuenta_id, $existente['entidad_rol_id']);
                        mysqli_stmt_execute($stmtUpd);
                        mysqli_stmt_close($stmtUpd);
                    }
                } else {
                    $stmtIns = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_roles (entidad_id, rol_entidad_id, cont_cuenta_id, f_alta, tabla_estado_registro_id) VALUES (?, ?, ?, CURDATE(), 1)");
                    if ($stmtIns) {
                        mysqli_stmt_bind_param($stmtIns, "iii", $entidad_id, $rol_id, $cuenta_id);
                        mysqli_stmt_execute($stmtIns);
                        mysqli_stmt_close($stmtIns);
                    }
                }
            }
        }

        $es_cliente = in_array('CLI', $deseados) ? 1 : 0;
        $es_proveedor = in_array('PRO', $deseados) ? 1 : 0;

        $sqlUpdate = "UPDATE gestion__entidades SET es_cliente = ?, es_proveedor = ?";
        $params = [$es_cliente, $es_proveedor];
        $types = "ii";

        if ($cuenta_cli !== null || in_array('CLI', $deseados)) {
            $sqlUpdate .= ", cont_cuenta_id_cliente = ?";
            $params[] = $cuenta_cli;
            $types .= "i";
        }
        if ($cuenta_pro !== null || in_array('PRO', $deseados)) {
            $sqlUpdate .= ", cont_cuenta_id_proveedor = ?";
            $params[] = $cuenta_pro;
            $types .= "i";
        }

        $sqlUpdate .= " WHERE entidad_id = ?";
        $params[] = $entidad_id;
        $types .= "i";

        $stmtSync = mysqli_prepare($conexion, $sqlUpdate);
        if ($stmtSync) {
            mysqli_stmt_bind_param($stmtSync, $types, ...$params);
            mysqli_stmt_execute($stmtSync);
            mysqli_stmt_close($stmtSync);
        }

        return true;
    }
}

if (!function_exists('actualizarRolIndividualEntidad')) {
    /**
     * Activa o desactiva un rol específico para una entidad sin afectar otros roles ya asignados.
     */
    function actualizarRolIndividualEntidad($conexion, $entidad_id, $rol_codigo, $activo, $cont_cuenta_id = null, $empresa_id = null)
    {
        $entidad_id = intval($entidad_id);
        $rol_codigo = strtoupper(trim((string)$rol_codigo));
        if ($entidad_id <= 0 || empty($rol_codigo)) return false;

        if (empty($empresa_id)) {
            $qEmp = mysqli_query($conexion, "SELECT empresa_id FROM gestion__entidades WHERE entidad_id = $entidad_id LIMIT 1");
            if ($qEmp && $rEmp = mysqli_fetch_assoc($qEmp)) {
                $empresa_id = intval($rEmp['empresa_id']);
            }
        }

        // Buscar rol_entidad_id
        $stmtRol = mysqli_prepare($conexion, "SELECT rol_entidad_id FROM gestion__roles_entidades WHERE rol_entidad_codigo = ? LIMIT 1");
        if (!$stmtRol) return false;
        mysqli_stmt_bind_param($stmtRol, "s", $rol_codigo);
        mysqli_stmt_execute($stmtRol);
        $resRol = mysqli_stmt_get_result($stmtRol);
        $rowRol = mysqli_fetch_assoc($resRol);
        mysqli_stmt_close($stmtRol);
        if (!$rowRol) return false;

        $rol_id = intval($rowRol['rol_entidad_id']);

        if ($activo) {
            // Si no se proporcionó cuenta contable, buscar la por defecto de la empresa para este rol
            if (empty($cont_cuenta_id) && !empty($empresa_id)) {
                $cuentas_defecto = obtenerCuentasDefectoRolesEmpresa($conexion, $empresa_id);
                if (isset($cuentas_defecto[$rol_codigo]['cont_cuenta_id'])) {
                    $cont_cuenta_id = intval($cuentas_defecto[$rol_codigo]['cont_cuenta_id']);
                }
            }
            $cont_cuenta_id = !empty($cont_cuenta_id) ? intval($cont_cuenta_id) : null;

            // Verificar si ya existe en gestion__entidades_roles
            $stmtCheck = mysqli_prepare($conexion, "SELECT entidad_rol_id, cont_cuenta_id FROM gestion__entidades_roles WHERE entidad_id = ? AND rol_entidad_id = ? LIMIT 1");
            if ($stmtCheck) {
                mysqli_stmt_bind_param($stmtCheck, "ii", $entidad_id, $rol_id);
                mysqli_stmt_execute($stmtCheck);
                $resCheck = mysqli_stmt_get_result($stmtCheck);
                $existente = mysqli_fetch_assoc($resCheck);
                mysqli_stmt_close($stmtCheck);

                if ($existente) {
                    $cuentaFinal = $cont_cuenta_id !== null ? $cont_cuenta_id : $existente['cont_cuenta_id'];
                    $stmtUpd = mysqli_prepare($conexion, "UPDATE gestion__entidades_roles SET f_baja = NULL, tabla_estado_registro_id = 1, cont_cuenta_id = ? WHERE entidad_rol_id = ?");
                    if ($stmtUpd) {
                        mysqli_stmt_bind_param($stmtUpd, "ii", $cuentaFinal, $existente['entidad_rol_id']);
                        mysqli_stmt_execute($stmtUpd);
                        mysqli_stmt_close($stmtUpd);
                    }
                } else {
                    $stmtIns = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_roles (entidad_id, rol_entidad_id, cont_cuenta_id, f_alta, tabla_estado_registro_id) VALUES (?, ?, ?, CURDATE(), 1)");
                    if ($stmtIns) {
                        mysqli_stmt_bind_param($stmtIns, "iii", $entidad_id, $rol_id, $cont_cuenta_id);
                        mysqli_stmt_execute($stmtIns);
                        mysqli_stmt_close($stmtIns);
                    }
                }
            }

            // Sincronizar flags en gestion__entidades
            if ($rol_codigo === 'CLI') {
                $stmtEnt = mysqli_prepare($conexion, "UPDATE gestion__entidades SET es_cliente = 1, cont_cuenta_id_cliente = ? WHERE entidad_id = ?");
                if ($stmtEnt) {
                    mysqli_stmt_bind_param($stmtEnt, "ii", $cont_cuenta_id, $entidad_id);
                    mysqli_stmt_execute($stmtEnt);
                    mysqli_stmt_close($stmtEnt);
                }
            } elseif ($rol_codigo === 'PRO') {
                $stmtEnt = mysqli_prepare($conexion, "UPDATE gestion__entidades SET es_proveedor = 1, cont_cuenta_id_proveedor = ? WHERE entidad_id = ?");
                if ($stmtEnt) {
                    mysqli_stmt_bind_param($stmtEnt, "ii", $cont_cuenta_id, $entidad_id);
                    mysqli_stmt_execute($stmtEnt);
                    mysqli_stmt_close($stmtEnt);
                }
            }
        } else {
            // Dar de baja el rol
            $stmtBaja = mysqli_prepare($conexion, "UPDATE gestion__entidades_roles SET f_baja = CURDATE() WHERE entidad_id = ? AND rol_entidad_id = ? AND f_baja IS NULL");
            if ($stmtBaja) {
                mysqli_stmt_bind_param($stmtBaja, "ii", $entidad_id, $rol_id);
                mysqli_stmt_execute($stmtBaja);
                mysqli_stmt_close($stmtBaja);
            }

            // Sincronizar flags en gestion__entidades
            if ($rol_codigo === 'CLI') {
                mysqli_query($conexion, "UPDATE gestion__entidades SET es_cliente = 0 WHERE entidad_id = $entidad_id");
            } elseif ($rol_codigo === 'PRO') {
                mysqli_query($conexion, "UPDATE gestion__entidades SET es_proveedor = 0 WHERE entidad_id = $entidad_id");
            }
        }

        return true;
    }
}
