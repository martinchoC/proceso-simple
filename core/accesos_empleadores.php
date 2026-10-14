<?php
declare(strict_types=1);

/**
 * core/accesos_empleadores.php
 * Motor de autorización y aislamiento multi-empleador para el Módulo 6 (Sindicatos).
 *
 * Determina a qué empleadores tiene acceso un usuario dentro de la empresa sindical:
 * - Superadmin (USUARIOS_ACCESO_TOTAL) -> acceso total a todos los empleadores.
 * - Usuarios con la función "Ver todos los empleadores" (página 1010) -> acceso total.
 *   Se asigna a los perfiles internos (Admin, Operador, Inspector) desde la administración de perfiles.
 * - Perfiles externos (Empleador titular, Estudio contable, Delegado) -> restringidos
 *   estrictamente a los empleadores asignados en `sindi__usuarios_empleadores`.
 */

require_once __DIR__ . '/permisos.php';

class AccesosEmpleadoresHelper
{
    private static array $cache = [];

    /** Página de Empleadores, donde vive la función de permiso "Ver todos los empleadores". */
    private const PAGINA_EMPLEADORES = 1010;
    private const ACCION_VER_TODOS = 'ver_todos_empleadores';

    private static function tieneFuncionVerTodos(mysqli $conexion, int $usuario_id, int $empresa_id): bool
    {
        $sql = "SELECT 1
                FROM conf__usuarios_perfiles up
                INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
                INNER JOIN conf__empresas_perfiles_funciones epf ON epf.empresa_perfil_id = ep.empresa_perfil_id
                INNER JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
                WHERE up.usuario_id = ?
                  AND ep.empresa_id = ?
                  AND up.tabla_estado_registro_id = 1
                  AND ep.tabla_estado_registro_id = 1
                  AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin
                  AND epf.asignado = 1
                  AND pf.pagina_id = ?
                  AND pf.accion_js = ?
                  AND pf.tabla_estado_registro_id = 1
                LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return false;
        }
        $pagina = self::PAGINA_EMPLEADORES;
        $accion = self::ACCION_VER_TODOS;
        mysqli_stmt_bind_param($stmt, "iiis", $usuario_id, $empresa_id, $pagina, $accion);
        mysqli_stmt_execute($stmt);
        $tiene = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $tiene;
    }

    /**
     * Resuelve los empleadores permitidos para el usuario en la empresa.
     * Retorna array:
     * [
     *   'tiene_acceso_total' => bool,
     *   'empleadores' => [empleador_id => 'TITULAR'|'ESTUDIO'|'DELEGADO'|'INTERNO'],
     * ]
     */
    public static function obtenerAccesos(mysqli $conexion, int $usuario_id, int $empresa_id): array
    {
        $cacheKey = "{$usuario_id}_{$empresa_id}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $resultado = [
            'tiene_acceso_total' => false,
            'empleadores' => [],
        ];

        // 1. Bypass superadmin global
        if (defined('USUARIOS_ACCESO_TOTAL') && in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
            $resultado['tiene_acceso_total'] = true;
            self::$cache[$cacheKey] = $resultado;
            return $resultado;
        }

        // 2. Usuarios internos del sindicato: los que tienen, por alguno de sus perfiles vigentes en
        //    la empresa, la función "Ver todos los empleadores" (accion_js ver_todos_empleadores,
        //    página 1010). Se asigna desde la administración de perfiles, no por id de perfil.
        if (self::tieneFuncionVerTodos($conexion, $usuario_id, $empresa_id)) {
            $resultado['tiene_acceso_total'] = true;
            self::$cache[$cacheKey] = $resultado;
            return $resultado;
        }

        // 3. Buscar en sindi__usuarios_empleadores los empleadores autorizados y vigentes
        $sql = "SELECT empleador_id, rol
                FROM sindi__usuarios_empleadores
                WHERE usuario_id = ?
                  AND empresa_id = ?
                  AND tabla_estado_registro_id = 1
                  AND CURDATE() >= f_desde
                  AND (f_hasta IS NULL OR CURDATE() <= f_hasta)";

        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $resultado['empleadores'][(int)$row['empleador_id']] = (string)$row['rol'];
            }
            mysqli_stmt_close($stmt);
        }

        self::$cache[$cacheKey] = $resultado;
        return $resultado;
    }

    /**
     * Retorna una cláusula SQL de filtrado para usar en consultas WHERE.
     * Si tiene acceso total retorna " 1=1 " (o vacío con AND).
     * Si no tiene acceso, retorna " 1=0 " para evitar fugas.
     *
     * @param string $campoSql Nombre de la columna en la query (ej: 'e.empleador_id' o 'd.empleador_id')
     */
    public static function filtroSql(mysqli $conexion, int $usuario_id, int $empresa_id, string $campoSql = 'empleador_id'): string
    {
        $info = self::obtenerAccesos($conexion, $usuario_id, $empresa_id);
        if ($info['tiene_acceso_total']) {
            return " 1=1 ";
        }

        if (empty($info['empleadores'])) {
            return " 1=0 ";
        }

        $ids = array_map('intval', array_keys($info['empleadores']));
        return " {$campoSql} IN (" . implode(',', $ids) . ") ";
    }

    /**
     * Retorna lista de IDs de empleadores accesibles o null si tiene acceso total a todos.
     * Útil para poblar combos y selectores.
     *
     * @return int[]|null Null = todos los empleadores de la empresa.
     */
    public static function filtrarEmpleadores(mysqli $conexion, int $usuario_id, int $empresa_id): ?array
    {
        $info = self::obtenerAccesos($conexion, $usuario_id, $empresa_id);
        if ($info['tiene_acceso_total']) {
            return null;
        }

        return array_keys($info['empleadores']);
    }

    /**
     * Verifica si el usuario puede acceder a un empleador específico.
     */
    public static function tieneAcceso(mysqli $conexion, int $usuario_id, int $empresa_id, int $empleador_id): bool
    {
        $info = self::obtenerAccesos($conexion, $usuario_id, $empresa_id);
        if ($info['tiene_acceso_total']) {
            return true;
        }

        return isset($info['empleadores'][$empleador_id]);
    }

    /**
     * Exige acceso explícito al empleador. Lanza excepción si el acceso es denegado.
     *
     * @throws RuntimeException
     */
    public static function exigirAcceso(mysqli $conexion, int $usuario_id, int $empresa_id, int $empleador_id): void
    {
        if (!self::tieneAcceso($conexion, $usuario_id, $empresa_id, $empleador_id)) {
            error_log("[SEGURIDAD_SINDICATOS] Acceso denegado: Usuario {$usuario_id} intentó acceder a empleador {$empleador_id} en empresa {$empresa_id}");
            throw new RuntimeException("Acceso denegado al empleador solicitado (ID {$empleador_id}).");
        }
    }

    /**
     * Obtiene el rol del usuario respecto al empleador ('TITULAR', 'ESTUDIO', 'DELEGADO', 'INTERNO').
     */
    public static function obtenerRolEmpleador(mysqli $conexion, int $usuario_id, int $empresa_id, int $empleador_id): string
    {
        $info = self::obtenerAccesos($conexion, $usuario_id, $empresa_id);
        if ($info['tiene_acceso_total']) {
            return 'INTERNO';
        }

        return $info['empleadores'][$empleador_id] ?? 'NINGUNO';
    }

    /**
     * Limpia la caché en memoria (por ejemplo luego de vincular un estudio o crear autoalta).
     */
    public static function limpiarCache(): void
    {
        self::$cache = [];
    }
}
