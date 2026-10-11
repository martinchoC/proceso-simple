<?php
declare(strict_types=1);

/**
 * core/accesos_empleadores.php
 * Motor de autorización y aislamiento multi-empleador para el Módulo 6 (Sindicatos).
 *
 * Determina a qué empleadores tiene acceso un usuario dentro de la empresa sindical:
 * - Superadmin (USUARIOS_ACCESO_TOTAL) -> acceso total a todos los empleadores.
 * - Perfiles internos del sindicato (Admin, Operador, Inspector) -> acceso total.
 * - Perfiles externos (Empleador titular, Estudio contable, Delegado) -> restringidos
 *   estrictamente a los empleadores asignados en `sindi__usuarios_empleadores`.
 */

require_once __DIR__ . '/permisos.php';

class AccesosEmpleadoresHelper
{
    private static array $cache = [];

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

        // 2. Verificar si el usuario tiene un perfil interno de sindicato en esta empresa
        $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id);
        if (!empty($perfiles)) {
            // Perfiles internos del módulo 6:
            // 1 (Superadmin), 2 (Admin parcial), 601 (Admin sindicato), 602 (Operador sindicato), 603 (Inspector)
            $perfilesInternos = [1, 2, 601, 602, 603];
            foreach ($perfiles as $p) {
                if (in_array($p['perfil_id_base'], $perfilesInternos, true)) {
                    $resultado['tiene_acceso_total'] = true;
                    self::$cache[$cacheKey] = $resultado;
                    return $resultado;
                }
            }
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
