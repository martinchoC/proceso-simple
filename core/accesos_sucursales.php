<?php
declare(strict_types=1);

/**
 * core/accesos_sucursales.php
 * Motor de resolución y verificación de accesos transversales a Sucursales, Bocas,
 * Puntos de Venta y Tipos de Comprobante según los perfiles asignados y excepciones.
 * 
 * Jerarquía de permisos:
 * Empresa (sucursal_id IS NULL) -> Sucursal -> Boca -> Punto de Venta -> Tipo Comprobante.
 * 
 * Regla de acceso:
 * - Sin filas de acceso para los perfiles del usuario = ACCESO DENEGADO (salvo superadmin USUARIOS_ACCESO_TOTAL).
 * - Excepciones individuales: PERMITE agrega alcance, DENIEGA tiene máxima prioridad.
 */

require_once __DIR__ . '/permisos.php';

class AccesosSucursalesHelper
{
    private static array $cache = [];

    /**
     * Obtiene la estructura completa de accesos resueltos para el usuario y empresa.
     * 
     * Retorna array:
     * [
     *   'tiene_acceso_total' => bool,
     *   'sucursales' => [sucursal_id => true],
     *   'bocas' => [boca_id => true],
     *   'bocas_por_sucursal' => [sucursal_id => [boca_id => true]],
     *   'puntos_venta' => [punto_venta_id => true],
     *   'puntos_venta_por_sucursal' => [sucursal_id => [punto_venta_id => true]],
     *   'comprobantes_tipos' => [comprobante_tipo_id => true],
     *   'predeterminados' => [
     *       'sucursal_id' => ?int,
     *       'boca_id' => ?int,
     *       'punto_venta_id' => ?int,
     *   ]
     * ]
     */
    /**
     * Acceso total a las sucursales de $empresa_id para $usuario_id, SOLO en memoria y durante este request.
     * Es para el estudio contable cuando opera sobre la empresa de un cliente que no tiene perfiles (nivel FISCAL):
     * quien llama tiene que haber verificado antes la vinculación vigente con escritura del área
     * (vinculacionesVerificarPermiso(..., true)) y la función de su propia página. No toca la base ni la sesión.
     * Hoy lo usa solo modules/estudio_contable/importar_arca_ajax.php.
     */
    public static function accesoDelegadoPorVinculacion(int $usuario_id, int $empresa_id): void
    {
        self::$cache["{$usuario_id}_{$empresa_id}"] = [
            'tiene_acceso_total' => true, 'sucursales' => [], 'bocas' => [], 'bocas_por_sucursal' => [],
            'puntos_venta' => [], 'puntos_venta_por_sucursal' => [], 'comprobantes_tipos' => [],
            'predeterminados' => ['sucursal_id' => null, 'boca_id' => null, 'punto_venta_id' => null],
        ];
    }

    public static function obtenerAccesos(mysqli $conexion, int $usuario_id, int $empresa_id): array
    {
        $cacheKey = "{$usuario_id}_{$empresa_id}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $resultado = [
            'tiene_acceso_total' => false,
            'sucursales' => [],
            'bocas' => [],
            'bocas_por_sucursal' => [],
            'puntos_venta' => [],
            'puntos_venta_por_sucursal' => [],
            'comprobantes_tipos' => [],
            'predeterminados' => [
                'sucursal_id' => null,
                'boca_id' => null,
                'punto_venta_id' => null,
            ]
        ];

        // 1. Bypass para usuarios con acceso total configurados en core/permisos.php
        if (defined('USUARIOS_ACCESO_TOTAL') && in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
            $resultado['tiene_acceso_total'] = true;
            self::$cache[$cacheKey] = $resultado;
            return $resultado;
        }

        // 2. Obtener perfiles del usuario vigentes en esta empresa
        $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id);
        if (empty($perfiles)) {
            self::$cache[$cacheKey] = $resultado;
            return $resultado;
        }

        $empresaPerfilIds = array_column($perfiles, 'empresa_perfil_id');
        $placeholders = implode(',', array_fill(0, count($empresaPerfilIds), '?'));
        $types = str_repeat('i', count($empresaPerfilIds));

        // 3. Consultar reglas otorgadas en gestion__perfiles_sucursales
        $sql = "SELECT sucursal_id, boca_id, punto_venta_id, comprobante_tipo_id, es_predeterminado
                FROM gestion__perfiles_sucursales
                WHERE empresa_id = ?
                  AND empresa_perfil_id IN ($placeholders)
                  AND tabla_estado_registro_id = 1";

        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            $bindParams = array_merge([$empresa_id], $empresaPerfilIds);
            $bindTypes = 'i' . $types;
            mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($res)) {
                $sucursalId = $row['sucursal_id'] !== null ? (int)$row['sucursal_id'] : null;
                $bocaId = $row['boca_id'] !== null ? (int)$row['boca_id'] : null;
                $pvId = $row['punto_venta_id'] !== null ? (int)$row['punto_venta_id'] : null;
                $tipoId = $row['comprobante_tipo_id'] !== null ? (int)$row['comprobante_tipo_id'] : null;

                if ($sucursalId === null) {
                    $resultado['tiene_acceso_total'] = true;
                } else {
                    $resultado['sucursales'][$sucursalId] = true;
                    if ($bocaId !== null) {
                        $resultado['bocas'][$bocaId] = true;
                        $resultado['bocas_por_sucursal'][$sucursalId][$bocaId] = true;
                    }
                    if ($pvId !== null) {
                        $resultado['puntos_venta'][$pvId] = true;
                        $resultado['puntos_venta_por_sucursal'][$sucursalId][$pvId] = true;
                    }
                    if ($tipoId !== null) {
                        $resultado['comprobantes_tipos'][$tipoId] = true;
                    }
                }

                if ((int)$row['es_predeterminado'] === 1) {
                    if ($sucursalId !== null && $resultado['predeterminados']['sucursal_id'] === null) {
                        $resultado['predeterminados']['sucursal_id'] = $sucursalId;
                    }
                    if ($bocaId !== null && $resultado['predeterminados']['boca_id'] === null) {
                        $resultado['predeterminados']['boca_id'] = $bocaId;
                    }
                    if ($pvId !== null && $resultado['predeterminados']['punto_venta_id'] === null) {
                        $resultado['predeterminados']['punto_venta_id'] = $pvId;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }

        // 4. Aplicar excepciones individuales de gestion__usuarios_sucursales_excepciones
        $sqlEx = "SELECT sucursal_id, boca_id, punto_venta_id, comprobante_tipo_id, tipo_excepcion
                  FROM gestion__usuarios_sucursales_excepciones
                  WHERE empresa_id = ?
                    AND usuario_id = ?
                    AND tabla_estado_registro_id = 1
                    AND fecha_desde <= CURDATE()
                    AND (fecha_hasta IS NULL OR fecha_hasta >= CURDATE())";

        $stmtEx = mysqli_prepare($conexion, $sqlEx);
        if ($stmtEx) {
            mysqli_stmt_bind_param($stmtEx, 'ii', $empresa_id, $usuario_id);
            mysqli_stmt_execute($stmtEx);
            $resEx = mysqli_stmt_get_result($stmtEx);

            $denegaciones = [];
            while ($rowEx = mysqli_fetch_assoc($resEx)) {
                $tipoEx = $rowEx['tipo_excepcion'];
                $sId = $rowEx['sucursal_id'] !== null ? (int)$rowEx['sucursal_id'] : null;
                $bId = $rowEx['boca_id'] !== null ? (int)$rowEx['boca_id'] : null;
                $pId = $rowEx['punto_venta_id'] !== null ? (int)$rowEx['punto_venta_id'] : null;
                $tId = $rowEx['comprobante_tipo_id'] !== null ? (int)$rowEx['comprobante_tipo_id'] : null;

                if ($tipoEx === 'PERMITE') {
                    if ($sId === null) {
                        $resultado['tiene_acceso_total'] = true;
                    } else {
                        $resultado['sucursales'][$sId] = true;
                        if ($bId !== null) {
                            $resultado['bocas'][$bId] = true;
                            $resultado['bocas_por_sucursal'][$sId][$bId] = true;
                        }
                        if ($pId !== null) {
                            $resultado['puntos_venta'][$pId] = true;
                            $resultado['puntos_venta_por_sucursal'][$sId][$pId] = true;
                        }
                        if ($tId !== null) {
                            $resultado['comprobantes_tipos'][$tId] = true;
                        }
                    }
                } elseif ($tipoEx === 'DENIEGA') {
                    $denegaciones[] = [
                        'sucursal_id' => $sId,
                        'boca_id' => $bId,
                        'punto_venta_id' => $pId,
                        'comprobante_tipo_id' => $tId,
                    ];
                }
            }
            mysqli_stmt_close($stmtEx);

            // Aplicar DENIEGA (anulación explícita)
            foreach ($denegaciones as $d) {
                if ($d['sucursal_id'] === null) {
                    $resultado['tiene_acceso_total'] = false;
                    $resultado['sucursales'] = [];
                    $resultado['bocas'] = [];
                    $resultado['puntos_venta'] = [];
                    $resultado['comprobantes_tipos'] = [];
                    break;
                }
                if ($d['boca_id'] === null && $d['punto_venta_id'] === null && $d['comprobante_tipo_id'] === null) {
                    unset($resultado['sucursales'][$d['sucursal_id']]);
                    unset($resultado['bocas_por_sucursal'][$d['sucursal_id']]);
                    unset($resultado['puntos_venta_por_sucursal'][$d['sucursal_id']]);
                }
                if ($d['boca_id'] !== null) {
                    unset($resultado['bocas'][$d['boca_id']]);
                    if (isset($resultado['bocas_por_sucursal'][$d['sucursal_id']][$d['boca_id']])) {
                        unset($resultado['bocas_por_sucursal'][$d['sucursal_id']][$d['boca_id']]);
                    }
                }
                if ($d['punto_venta_id'] !== null) {
                    unset($resultado['puntos_venta'][$d['punto_venta_id']]);
                    if (isset($resultado['puntos_venta_por_sucursal'][$d['sucursal_id']][$d['punto_venta_id']])) {
                        unset($resultado['puntos_venta_por_sucursal'][$d['sucursal_id']][$d['punto_venta_id']]);
                    }
                }
                if ($d['comprobante_tipo_id'] !== null) {
                    unset($resultado['comprobantes_tipos'][$d['comprobante_tipo_id']]);
                }
            }
        }

        self::$cache[$cacheKey] = $resultado;
        return $resultado;
    }

    /**
     * Valida si el usuario tiene acceso a una sucursal dada.
     */
    public static function tieneAccesoSucursal(mysqli $conexion, int $usuario_id, int $empresa_id, int $sucursal_id): bool
    {
        $accesos = self::obtenerAccesos($conexion, $usuario_id, $empresa_id);
        if ($accesos['tiene_acceso_total']) {
            return true;
        }
        return isset($accesos['sucursales'][$sucursal_id]);
    }

    /**
     * Valida si el usuario tiene acceso a un punto de venta dado.
     */
    public static function tieneAccesoPuntoVenta(mysqli $conexion, int $usuario_id, int $empresa_id, int $punto_venta_id): bool
    {
        $accesos = self::obtenerAccesos($conexion, $usuario_id, $empresa_id);
        if ($accesos['tiene_acceso_total']) {
            return true;
        }
        if (!empty($accesos['puntos_venta'])) {
            return isset($accesos['puntos_venta'][$punto_venta_id]);
        }
        // Si no está restringido a PVs específicos, chequear si pertenece a una sucursal permitida
        $stmt = mysqli_prepare($conexion, "SELECT sucursal_id FROM gestion__puntos_venta WHERE punto_venta_id = ? AND empresa_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $punto_venta_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                $sid = (int)$row['sucursal_id'];
                mysqli_stmt_close($stmt);
                return isset($accesos['sucursales'][$sid]);
            }
            mysqli_stmt_close($stmt);
        }
        return false;
    }

    /**
     * Lanza excepción si el usuario no tiene permisos sobre la sucursal.
     * @throws RuntimeException
     */
    public static function exigirAccesoSucursal(mysqli $conexion, int $empresa_id, int $sucursal_id, ?int $usuario_id = null): void
    {
        $uid = $usuario_id ?? (int)($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0) {
            throw new RuntimeException('Sesión de usuario no válida');
        }
        if (!self::tieneAccesoSucursal($conexion, $uid, $empresa_id, $sucursal_id)) {
            throw new RuntimeException('No tiene permisos de acceso a la sucursal seleccionada');
        }
    }

    /**
     * Lanza excepción si el usuario no tiene permisos sobre el punto de venta.
     * @throws RuntimeException
     */
    public static function exigirAccesoPuntoVenta(mysqli $conexion, int $empresa_id, int $punto_venta_id, ?int $usuario_id = null): void
    {
        $uid = $usuario_id ?? (int)($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0) {
            throw new RuntimeException('Sesión de usuario no válida');
        }
        if (!self::tieneAccesoPuntoVenta($conexion, $uid, $empresa_id, $punto_venta_id)) {
            throw new RuntimeException('No tiene permisos de acceso al punto de venta seleccionado');
        }
    }

    /**
     * Genera una cláusula SQL WHERE para filtrar sucursales según los permisos.
     * Ejemplo: AccesosSucursalesHelper::filtroSqlSucursal($conexion, $uid, $eid, 's.sucursal_id')
     * Retorna: " AND s.sucursal_id IN (1, 2) " o "" si tiene acceso total o " AND 1=0 " si no tiene ninguno.
     */
    public static function filtroSqlSucursal(mysqli $conexion, int $usuario_id, int $empresa_id, string $columnaSucursal): string
    {
        $accesos = self::obtenerAccesos($conexion, $usuario_id, $empresa_id);
        if ($accesos['tiene_acceso_total']) {
            return "";
        }
        if (empty($accesos['sucursales'])) {
            return " AND 1=0 ";
        }
        $ids = implode(',', array_map('intval', array_keys($accesos['sucursales'])));
        return " AND {$columnaSucursal} IN ({$ids}) ";
    }

    /**
     * Filtra una lista de sucursales según los permisos del usuario.
     * @param array $sucursales Lista con clave 'sucursal_id'
     * @param ?int $usuario_id Si es null, toma $_SESSION['usuario_id']
     */
    public static function filtrarSucursales(mysqli $conexion, int $empresa_id, array $sucursales, ?int $usuario_id = null): array
    {
        $uid = $usuario_id ?? (int)($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0) return $sucursales;

        $acc = self::obtenerAccesos($conexion, $uid, $empresa_id);
        if ($acc['tiene_acceso_total']) return $sucursales;

        return array_values(array_filter($sucursales, function ($s) use ($acc) {
            $sid = (int)($s['sucursal_id'] ?? 0);
            return isset($acc['sucursales'][$sid]);
        }));
    }

    /**
     * Filtra una lista de bocas según los permisos del usuario.
     * @param array $bocas Lista con clave 'boca_id' y opcionalmente 'sucursal_id'
     */
    public static function filtrarBocas(mysqli $conexion, int $empresa_id, array $bocas, ?int $usuario_id = null): array
    {
        $uid = $usuario_id ?? (int)($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0) return $bocas;

        $acc = self::obtenerAccesos($conexion, $uid, $empresa_id);
        if ($acc['tiene_acceso_total']) return $bocas;

        return array_values(array_filter($bocas, function ($b) use ($acc) {
            $bid = (int)($b['boca_id'] ?? 0);
            $sid = (int)($b['sucursal_id'] ?? 0);
            if (!empty($acc['bocas'])) {
                return isset($acc['bocas'][$bid]);
            }
            if ($sid > 0 && isset($acc['sucursales'][$sid])) {
                return true;
            }
            return false;
        }));
    }

    /**
     * Filtra una lista de puntos de venta según los permisos del usuario.
     * @param array $puntosVenta Lista con clave 'punto_venta_id'
     */
    public static function filtrarPuntosVenta(mysqli $conexion, int $empresa_id, array $puntosVenta, ?int $usuario_id = null): array
    {
        $uid = $usuario_id ?? (int)($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0) return $puntosVenta;

        $acc = self::obtenerAccesos($conexion, $uid, $empresa_id);
        if ($acc['tiene_acceso_total']) return $puntosVenta;

        return array_values(array_filter($puntosVenta, function ($pv) use ($acc) {
            $pvid = (int)($pv['punto_venta_id'] ?? 0);
            $sid = (int)($pv['sucursal_id'] ?? 0);
            if (!empty($acc['puntos_venta'])) {
                return isset($acc['puntos_venta'][$pvid]);
            }
            if ($sid > 0 && isset($acc['sucursales'][$sid])) {
                return true;
            }
            return false;
        }));
    }
}

