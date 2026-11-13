<?php
/**
 * Roles de entidad (gestion__entidades_roles) y su cuenta contable.
 *
 * gestion__entidades_roles es la fuente de "es cliente / es proveedor / empleador..." de una entidad:
 * una fila por entidad y rol (uk_entidad_rol), la baja es f_baja, nunca se borra. El rol se resuelve por
 * código en gestion__roles_entidades (CLI, PRO, EMP, ORG, EMPL, EST, AFIL, BAN), nunca por id.
 *
 * Reemplaza a las columnas gestion__entidades.es_cliente / es_proveedor / cont_cuenta_id_cliente /
 * cont_cuenta_id_proveedor (las borra 20261019_entidades_roles_02_drop_columnas.sql, después de cargar
 * los roles con 20261019_entidades_roles_01_backfill.sql). Para leer: en SQL ent_sql_tiene_rol() /
 * ent_sql_cuenta_rol() / ent_sql_roles_select(); en PHP ent_tiene_rol() / ent_cuenta_rol().
 *
 * Lo usan gestion, gestion_normal (vía su entidades_roles_helper.php), estudio_contable y facturas de
 * proveedores. Las funciones no abren transacción: se llaman dentro de la del alta / edición.
 */

if (!function_exists('obtenerCatalogoRolesEntidad')) {
    function obtenerCatalogoRolesEntidad($conexion)
    {
        $sql = "SELECT rol_entidad_id, rol_entidad_codigo, rol_entidad_nombre, rol_entidad_descripcion
                FROM gestion__roles_entidades
                ORDER BY rol_entidad_id";
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
    /** Cuenta contable por defecto de cada rol en la empresa (gestion__empresas_roles_cuentas), por código de rol. */
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
    /** Roles activos de la entidad con su cuenta contable. */
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

if (!function_exists('entRolesEmpresaDeEntidad')) {
    function entRolesEmpresaDeEntidad($conexion, int $entidad_id): ?int
    {
        $stmt = mysqli_prepare($conexion, "SELECT empresa_id FROM gestion__entidades WHERE entidad_id = ? LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, "i", $entidad_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ? intval($row['empresa_id']) : null;
    }
}

if (!function_exists('entRolesGuardarRol')) {
    /**
     * Da de alta o reactiva el rol (una fila por entidad y rol) con la cuenta indicada.
     * $cuenta_id null = conserva la que tenía la fila. Devuelve la cuenta que quedó.
     */
    function entRolesGuardarRol($conexion, int $entidad_id, int $rol_id, ?int $cuenta_id): ?int
    {
        $stmt = mysqli_prepare($conexion, "SELECT entidad_rol_id, cont_cuenta_id FROM gestion__entidades_roles
                                           WHERE entidad_id = ? AND rol_entidad_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $rol_id);
        mysqli_stmt_execute($stmt);
        $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existente) {
            $cuenta_final = $cuenta_id ?? (!empty($existente['cont_cuenta_id']) ? intval($existente['cont_cuenta_id']) : null);
            $er_id = intval($existente['entidad_rol_id']);
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_roles
                                               SET f_baja = NULL, tabla_estado_registro_id = 1, cont_cuenta_id = ?
                                               WHERE entidad_rol_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $cuenta_final, $er_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $cuenta_final;
        }

        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_roles (entidad_id, rol_entidad_id, cont_cuenta_id, f_alta, tabla_estado_registro_id)
                                           VALUES (?, ?, ?, CURDATE(), 1)");
        mysqli_stmt_bind_param($stmt, "iii", $entidad_id, $rol_id, $cuenta_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $cuenta_id;
    }
}

if (!function_exists('sincronizarRolesEntidad')) {
    /**
     * Deja a la entidad exactamente con los roles $roles (códigos). Los que sobran se dan de baja (f_baja).
     * Cuenta de cada rol: $roles_cuentas[codigo] > la que ya tenía > la de la empresa para el rol.
     */
    function sincronizarRolesEntidad($conexion, $entidad_id, $roles, $roles_cuentas = [], $empresa_id = null)
    {
        $entidad_id = intval($entidad_id);
        if ($entidad_id <= 0) return false;

        if (empty($empresa_id)) {
            $empresa_id = entRolesEmpresaDeEntidad($conexion, $entidad_id);
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

        $cuentas_defecto = !empty($empresa_id) ? obtenerCuentasDefectoRolesEmpresa($conexion, $empresa_id) : [];

        // Roles activos actuales
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

        // 1. Baja de los roles que ya no se quieren
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

        // 2. Alta / reactivación de los deseados con su cuenta
        foreach ($deseados as $cod) {
            if (!isset($map[$cod])) continue;

            $cuenta_id = null;
            if (!empty($roles_cuentas[$cod])) {
                $cuenta_id = intval($roles_cuentas[$cod]);
            } elseif (!empty($actuales[$cod]['cont_cuenta_id'])) {
                $cuenta_id = $actuales[$cod]['cont_cuenta_id'];
            } elseif (!empty($cuentas_defecto[$cod]['cont_cuenta_id'])) {
                $cuenta_id = intval($cuentas_defecto[$cod]['cont_cuenta_id']);
            }

            entRolesGuardarRol($conexion, $entidad_id, $map[$cod], $cuenta_id);
        }

        return true;
    }
}

if (!function_exists('actualizarRolIndividualEntidad')) {
    /**
     * Activa o da de baja un rol de la entidad sin tocar los demás.
     * Al activar sin cuenta: conserva la que tenía el rol o toma la de la empresa para el rol.
     */
    function actualizarRolIndividualEntidad($conexion, $entidad_id, $rol_codigo, $activo, $cont_cuenta_id = null, $empresa_id = null)
    {
        $entidad_id = intval($entidad_id);
        $rol_codigo = strtoupper(trim((string)$rol_codigo));
        if ($entidad_id <= 0 || $rol_codigo === '') return false;

        if (empty($empresa_id)) {
            $empresa_id = entRolesEmpresaDeEntidad($conexion, $entidad_id);
        }

        $stmtRol = mysqli_prepare($conexion, "SELECT rol_entidad_id FROM gestion__roles_entidades WHERE rol_entidad_codigo = ? LIMIT 1");
        if (!$stmtRol) return false;
        mysqli_stmt_bind_param($stmtRol, "s", $rol_codigo);
        mysqli_stmt_execute($stmtRol);
        $rowRol = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtRol));
        mysqli_stmt_close($stmtRol);
        if (!$rowRol) return false;
        $rol_id = intval($rowRol['rol_entidad_id']);

        if ($activo) {
            $cont_cuenta_id = !empty($cont_cuenta_id) ? intval($cont_cuenta_id) : null;
            if ($cont_cuenta_id === null) {
                // Sin cuenta: la que ya tenía el rol; si no tenía, la de la empresa para el rol
                $stmt = mysqli_prepare($conexion, "SELECT cont_cuenta_id FROM gestion__entidades_roles WHERE entidad_id = ? AND rol_entidad_id = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $rol_id);
                mysqli_stmt_execute($stmt);
                $previa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (empty($previa['cont_cuenta_id']) && !empty($empresa_id)) {
                    $cuentas_defecto = obtenerCuentasDefectoRolesEmpresa($conexion, $empresa_id);
                    if (!empty($cuentas_defecto[$rol_codigo]['cont_cuenta_id'])) {
                        $cont_cuenta_id = intval($cuentas_defecto[$rol_codigo]['cont_cuenta_id']);
                    }
                }
            }
            entRolesGuardarRol($conexion, $entidad_id, $rol_id, $cont_cuenta_id);
        } else {
            $stmtBaja = mysqli_prepare($conexion, "UPDATE gestion__entidades_roles SET f_baja = CURDATE()
                                                   WHERE entidad_id = ? AND rol_entidad_id = ? AND f_baja IS NULL");
            if ($stmtBaja) {
                mysqli_stmt_bind_param($stmtBaja, "ii", $entidad_id, $rol_id);
                mysqli_stmt_execute($stmtBaja);
                mysqli_stmt_close($stmtBaja);
            }
        }

        return true;
    }
}

// ---------------------------------------------------------------------------------------------------
// Lectura de roles en SQL. $col_entidad es la expresión con el entidad_id ('e.entidad_id');
// $rol es un código fijo del catálogo (CLI, PRO...), nunca un dato del request.
// ---------------------------------------------------------------------------------------------------

if (!function_exists('ent_sql_rol_validar')) {
    function ent_sql_rol_validar(string $col_entidad, string $rol): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $col_entidad) || !preg_match('/^[A-Z]{2,10}$/', $rol)) {
            throw new InvalidArgumentException("ent_sql: columna o rol inválido ({$col_entidad}, {$rol})");
        }
    }
}

if (!function_exists('ent_sql_tiene_rol')) {
    /** EXISTS (...): la entidad tiene el rol activo. */
    function ent_sql_tiene_rol(string $col_entidad, string $rol): string
    {
        ent_sql_rol_validar($col_entidad, $rol);
        $a = 'xr' . strtolower($rol);
        return "EXISTS (SELECT 1 FROM gestion__entidades_roles {$a}"
             . " JOIN gestion__roles_entidades {$a}c ON {$a}c.rol_entidad_id = {$a}.rol_entidad_id AND {$a}c.rol_entidad_codigo = '{$rol}'"
             . " WHERE {$a}.entidad_id = {$col_entidad} AND {$a}.f_baja IS NULL AND {$a}.tabla_estado_registro_id = 1)";
    }
}

if (!function_exists('ent_sql_cuenta_rol')) {
    /** Subconsulta: cuenta contable del rol activo de la entidad (NULL si no tiene el rol o la cuenta). */
    function ent_sql_cuenta_rol(string $col_entidad, string $rol): string
    {
        ent_sql_rol_validar($col_entidad, $rol);
        $a = 'xk' . strtolower($rol);
        return "(SELECT {$a}.cont_cuenta_id FROM gestion__entidades_roles {$a}"
             . " JOIN gestion__roles_entidades {$a}c ON {$a}c.rol_entidad_id = {$a}.rol_entidad_id AND {$a}c.rol_entidad_codigo = '{$rol}'"
             . " WHERE {$a}.entidad_id = {$col_entidad} AND {$a}.f_baja IS NULL AND {$a}.tabla_estado_registro_id = 1 LIMIT 1)";
    }
}

if (!function_exists('ent_sql_roles_select')) {
    /**
     * es_cliente, es_proveedor, cont_cuenta_id_cliente y cont_cuenta_id_proveedor calculadas desde los roles,
     * para los SELECT que las devuelven con esos nombres (formularios, combos).
     */
    function ent_sql_roles_select(string $col_entidad): string
    {
        return "IF(" . ent_sql_tiene_rol($col_entidad, 'CLI') . ", 1, 0) AS es_cliente, "
             . "IF(" . ent_sql_tiene_rol($col_entidad, 'PRO') . ", 1, 0) AS es_proveedor, "
             . ent_sql_cuenta_rol($col_entidad, 'CLI') . " AS cont_cuenta_id_cliente, "
             . ent_sql_cuenta_rol($col_entidad, 'PRO') . " AS cont_cuenta_id_proveedor";
    }
}

if (!function_exists('ent_tiene_rol')) {
    function ent_tiene_rol($conexion, int $entidad_id, string $rol): bool
    {
        $stmt = mysqli_prepare($conexion, "SELECT 1 FROM gestion__entidades e WHERE e.entidad_id = ? AND " . ent_sql_tiene_rol('e.entidad_id', $rol));
        mysqli_stmt_bind_param($stmt, "i", $entidad_id);
        mysqli_stmt_execute($stmt);
        $ok = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('ent_cuenta_rol')) {
    /** Cuenta contable del rol activo de la entidad, o null. */
    function ent_cuenta_rol($conexion, int $entidad_id, string $rol): ?int
    {
        $stmt = mysqli_prepare($conexion, "SELECT " . ent_sql_cuenta_rol('e.entidad_id', $rol) . " AS cuenta
                                           FROM gestion__entidades e WHERE e.entidad_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $entidad_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return !empty($row['cuenta']) ? intval($row['cuenta']) : null;
    }
}
