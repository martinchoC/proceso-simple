<?php
/**
 * core/auditoria.php
 * Sistema centralizado de Logs de Auditoría con particionamiento anual automático.
 * 
 * Características:
 *  - Tablas anuales dinámicas: conf__auditoria_YYYY (ej: conf__auditoria_2026, conf__auditoria_2027).
 *  - Creación automática al cambiar de año: si la tabla del año en curso no existe,
 *    el sistema la genera en milisegundos con su estructura DDL e índices optimizados.
 *  - Caché estática por request: evita ejecutar comprobaciones DDL repetitivas en cada log.
 *  - Trazabilidad forense: IP de origen (con soporte proxy), User Agent, URL, método HTTP,
 *    usuario, empresa, módulo, página, tabla afectada y diff de datos (antes/después en JSON).
 *  - Tolerancia a fallos: un fallo en auditoría nunca interrumpe la operación del usuario,
 *    pero queda registrado en el log del sistema.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Obtiene el nombre sanitizado de la tabla de auditoría para un año determinado.
 */
function auditoria_obtener_nombre_tabla(?int $anio = null): string
{
    $anioValido = ($anio !== null && $anio >= 2000 && $anio <= 2100) ? $anio : (int) date('Y');
    return "conf__auditoria_" . $anioValido;
}

/**
 * Verifica si la tabla anual existe; si no existe, la crea automáticamente.
 * Utiliza caché estática en memoria para no impactar el rendimiento en peticiones sucesivas.
 */
function auditoria_asegurar_tabla(mysqli $conexion, int $anio): bool
{
    static $tablasAseguradas = [];

    if (isset($tablasAseguradas[$anio])) {
        return true;
    }

    $tabla = auditoria_obtener_nombre_tabla($anio);

    $sql = "CREATE TABLE IF NOT EXISTS `{$tabla}` (
        `auditoria_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `usuario_id` INT UNSIGNED NULL DEFAULT NULL,
        `usuario_nombre` VARCHAR(100) NULL DEFAULT NULL,
        `empresa_id` INT UNSIGNED NULL DEFAULT NULL,
        `modulo_id` INT UNSIGNED NULL DEFAULT NULL,
        `pagina_id` INT UNSIGNED NULL DEFAULT NULL,
        `accion` VARCHAR(50) NOT NULL,
        `tabla_id` INT UNSIGNED NULL DEFAULT NULL,
        `tabla_afectada` VARCHAR(100) NULL DEFAULT NULL,
        `registro_id` VARCHAR(100) NULL DEFAULT NULL,
        `tablas_involucradas` LONGTEXT NULL DEFAULT NULL,
        `descripcion` TEXT NULL,
        `datos_antes` LONGTEXT NULL,
        `datos_despues` LONGTEXT NULL,
        `ip_origen` VARCHAR(45) NOT NULL DEFAULT '',
        `user_agent` VARCHAR(255) NULL DEFAULT NULL,
        `url_solicitada` VARCHAR(255) NULL DEFAULT NULL,
        `metodo_http` VARCHAR(10) NULL DEFAULT 'GET',
        PRIMARY KEY (`auditoria_id`),
        KEY `idx_fecha` (`fecha_hora`),
        KEY `idx_usuario` (`usuario_id`, `fecha_hora`),
        KEY `idx_empresa` (`empresa_id`, `fecha_hora`),
        KEY `idx_modulo` (`modulo_id`, `fecha_hora`),
        KEY `idx_accion` (`accion`, `fecha_hora`),
        KEY `idx_tabla_id` (`tabla_id`),
        KEY `idx_tabla_registro` (`tabla_afectada`, `registro_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de Auditoria Anual {$anio}'";

    if (mysqli_query($conexion, $sql)) {
        // Asegurar columna tabla_id si la tabla existía previamente
        $colCheck = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tabla}` LIKE 'tabla_id'");
        if ($colCheck && mysqli_num_rows($colCheck) === 0) {
            mysqli_query($conexion, "ALTER TABLE `{$tabla}` ADD `tabla_id` INT UNSIGNED NULL DEFAULT NULL AFTER `accion`, ADD KEY `idx_tabla_id` (`tabla_id`)");
        }

        // Asegurar columna tablas_involucradas para trazabilidad multitabla
        $colCheckInv = mysqli_query($conexion, "SHOW COLUMNS FROM `{$tabla}` LIKE 'tablas_involucradas'");
        if ($colCheckInv && mysqli_num_rows($colCheckInv) === 0) {
            mysqli_query($conexion, "ALTER TABLE `{$tabla}` ADD `tablas_involucradas` LONGTEXT NULL DEFAULT NULL AFTER `registro_id`");
        }

        // Auto-reparar registros donde tabla_afectada haya quedado en '0' por el casteo de tipos
        mysqli_query($conexion, "UPDATE `{$tabla}` 
            SET `tabla_afectada` = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(`descripcion`, ' ejecutada en ', -1), ' (ID:', 1))
            WHERE (`tabla_afectada` = '0' OR `tabla_afectada` = 0) AND `descripcion` LIKE '%ejecutada en %'");

        // Auto-poblar tabla_id para registros con tabla_afectada conocida
        mysqli_query($conexion, "UPDATE `{$tabla}` a
            JOIN `conf__tablas` t ON t.`tabla_nombre` = a.`tabla_afectada`
            SET a.`tabla_id` = t.`tabla_id`
            WHERE a.`tabla_id` IS NULL AND a.`tabla_afectada` IS NOT NULL AND a.`tabla_afectada` != '0'");

        $tablasAseguradas[$anio] = true;
        return true;
    }

    error_log("ERROR_AUDITORIA: No se pudo asegurar la tabla {$tabla}: " . mysqli_error($conexion));
    return false;
}

/**
 * Resuelve la dirección IP del cliente soportando proxies inversos y balanceadores.
 */
function auditoria_obtener_ip_cliente(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',  // Proxies / Load Balancers
        'HTTP_X_REAL_IP',        // Nginx proxy
        'REMOTE_ADDR'            // Directo
    ];

    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ipList = explode(',', (string) $_SERVER[$h]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
}

/**
 * Resuelve el tabla_id a partir del nombre de la tabla consultando conf__tablas.
 * Cachea los resultados en memoria para no repetir consultas innecesarias.
 */
function auditoria_obtener_tabla_id(mysqli $conexion, string $nombreTabla): ?int
{
    static $cacheTablas = [];
    $nombreLimpio = trim($nombreTabla);
    if ($nombreLimpio === '') {
        return null;
    }
    if (array_key_exists($nombreLimpio, $cacheTablas)) {
        return $cacheTablas[$nombreLimpio];
    }

    $stmt = mysqli_prepare($conexion, "SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $nombreLimpio);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)$row['tabla_id'];
            mysqli_stmt_close($stmt);
            $cacheTablas[$nombreLimpio] = $id;
            return $id;
        }
        mysqli_stmt_close($stmt);
    }
    $cacheTablas[$nombreLimpio] = null;
    return null;
}

/**
 * Registra un evento en la tabla de auditoría del año correspondiente.
 * Si la tabla del año no existe, se crea automáticamente.
 * 
 * @param mysqli $conexion Conexión activa a la base de datos
 * @param array{
 *     accion: string,
 *     descripcion?: string|null,
 *     tabla_afectada?: string|null,
 *     registro_id?: string|int|null,
 *     datos_antes?: array|string|null,
 *     datos_despues?: array|string|null,
 *     usuario_id?: int|null,
 *     usuario_nombre?: string|null,
 *     empresa_id?: int|null,
 *     modulo_id?: int|null,
 *     pagina_id?: int|null,
 *     fecha_hora?: string|null,
 *     ip_origen?: string|null,
 *     url_solicitada?: string|null,
 *     metodo_http?: string|null
 * } $evento
 * @return bool True si se registró con éxito, false en caso de error.
 */
function auditoria_registrar(mysqli $conexion, array $evento): bool
{
    try {
        $fecha_hora = !empty($evento['fecha_hora']) ? (string)$evento['fecha_hora'] : date('Y-m-d H:i:s');
        $anio = (int) substr($fecha_hora, 0, 4);
        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) date('Y');
        }

        // 1. Asegurar que la tabla anual exista (autocreación si es nuevo año)
        if (!auditoria_asegurar_tabla($conexion, $anio)) {
            return false;
        }

        $tabla = auditoria_obtener_nombre_tabla($anio);

        // 2. Resolver datos del contexto activo si no vienen especificados
        $usuario_id = array_key_exists('usuario_id', $evento) 
            ? ($evento['usuario_id'] !== null ? (int)$evento['usuario_id'] : null)
            : (!empty($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null);

        $usuario_nombre = array_key_exists('usuario_nombre', $evento)
            ? ($evento['usuario_nombre'] !== null ? substr((string)$evento['usuario_nombre'], 0, 100) : null)
            : (!empty($_SESSION['usuario_nombre']) ? substr((string)$_SESSION['usuario_nombre'], 0, 100) : null);

        $empresa_id = array_key_exists('empresa_id', $evento)
            ? ($evento['empresa_id'] !== null ? (int)$evento['empresa_id'] : null)
            : (!empty($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : null);

        $modulo_id = array_key_exists('modulo_id', $evento)
            ? ($evento['modulo_id'] !== null ? (int)$evento['modulo_id'] : null)
            : (!empty($_SESSION['modulo_id']) ? (int)$_SESSION['modulo_id'] : null);

        $pagina_id = array_key_exists('pagina_id', $evento)
            ? ($evento['pagina_id'] !== null ? (int)$evento['pagina_id'] : null)
            : (!empty($GLOBALS['pagina_id']) ? (int)$GLOBALS['pagina_id'] : null);

        $accion = strtoupper(trim((string)($evento['accion'] ?? 'GENERAL')));
        $tabla_afectada = !empty($evento['tabla_afectada']) ? substr(trim((string)$evento['tabla_afectada']), 0, 100) : null;
        $tabla_id = array_key_exists('tabla_id', $evento) && $evento['tabla_id'] !== null
            ? (int)$evento['tabla_id']
            : (!empty($tabla_afectada) ? auditoria_obtener_tabla_id($conexion, $tabla_afectada) : null);
        $registro_id = !empty($evento['registro_id']) ? substr((string)$evento['registro_id'], 0, 100) : null;
        $descripcion = !empty($evento['descripcion']) ? (string)$evento['descripcion'] : null;

        // Procesar tablas involucradas (soporte multitabla para procesos complejos)
        $tablas_involucradas = null;
        $tablasLista = null;
        if (!empty($evento['tablas_involucradas'])) {
            $tablasLista = is_array($evento['tablas_involucradas']) 
                ? $evento['tablas_involucradas'] 
                : json_decode((string)$evento['tablas_involucradas'], true);
        } elseif (!empty($GLOBALS['__auditoria_tablas_involucradas'])) {
            $tablasLista = $GLOBALS['__auditoria_tablas_involucradas'];
        }

        if (is_array($tablasLista) && !empty($tablasLista)) {
            foreach ($tablasLista as &$item) {
                if (empty($item['tabla_id']) && !empty($item['tabla'])) {
                    $item['tabla_id'] = auditoria_obtener_tabla_id($conexion, (string)$item['tabla']);
                }
            }
            unset($item);
            $tablas_involucradas = json_encode($tablasLista, JSON_UNESCAPED_UNICODE);
        }

        // Serializar datos antes/después a JSON si son arrays
        $datos_antes = null;
        if (!empty($evento['datos_antes'])) {
            $datos_antes = is_array($evento['datos_antes']) 
                ? json_encode($evento['datos_antes'], JSON_UNESCAPED_UNICODE) 
                : (string)$evento['datos_antes'];
        }

        $datos_despues = null;
        if (!empty($evento['datos_despues'])) {
            $datos_despues = is_array($evento['datos_despues']) 
                ? json_encode($evento['datos_despues'], JSON_UNESCAPED_UNICODE) 
                : (string)$evento['datos_despues'];
        }

        $ip_origen = !empty($evento['ip_origen']) ? (string)$evento['ip_origen'] : auditoria_obtener_ip_cliente();
        $user_agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $url_solicitada = !empty($evento['url_solicitada']) 
            ? substr((string)$evento['url_solicitada'], 0, 255) 
            : substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 255);
        $metodo_http = !empty($evento['metodo_http']) 
            ? substr(strtoupper((string)$evento['metodo_http']), 0, 10) 
            : substr((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 10);

        // 3. Inserción con prepared statement
        $sql = "INSERT INTO `{$tabla}` (
            `fecha_hora`, `usuario_id`, `usuario_nombre`, `empresa_id`, `modulo_id`, `pagina_id`,
            `accion`, `tabla_id`, `tabla_afectada`, `registro_id`, `tablas_involucradas`, `descripcion`, `datos_antes`, `datos_despues`,
            `ip_origen`, `user_agent`, `url_solicitada`, `metodo_http`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("ERROR_AUDITORIA: Prepare falló en {$tabla}: " . mysqli_error($conexion));
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sisiiisissssssssss",
            $fecha_hora,
            $usuario_id,
            $usuario_nombre,
            $empresa_id,
            $modulo_id,
            $pagina_id,
            $accion,
            $tabla_id,
            $tabla_afectada,
            $registro_id,
            $tablas_involucradas,
            $descripcion,
            $datos_antes,
            $datos_despues,
            $ip_origen,
            $user_agent,
            $url_solicitada,
            $metodo_http
        );

        $resultado = mysqli_stmt_execute($stmt);
        if (!$resultado) {
            error_log("ERROR_AUDITORIA: Execute falló en {$tabla}: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        return $resultado;
    } catch (Throwable $e) {
        error_log("EXCEPCION_AUDITORIA: " . $e->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------------------------------------------
// Funciones de conveniencia (Helpers semánticos)
// ---------------------------------------------------------------------------------------------------

/**
 * Registra eventos de autenticación (login exitoso o fallido).
 */
function auditoria_login(mysqli $conexion, int $usuario_id, string $usuario_nombre, bool $exito, string $motivo = ''): bool
{
    return auditoria_registrar($conexion, [
        'accion' => $exito ? 'LOGIN_EXITOSO' : 'LOGIN_FALLIDO',
        'usuario_id' => $usuario_id > 0 ? $usuario_id : null,
        'usuario_nombre' => $usuario_nombre,
        'descripcion' => $exito 
            ? "Inicio de sesión correcto para el usuario '{$usuario_nombre}'" 
            : "Intento fallido de inicio de sesión para el usuario '{$usuario_nombre}'. Motivo: {$motivo}",
    ]);
}

/**
 * Registra cierre de sesión.
 */
function auditoria_logout(mysqli $conexion, int $usuario_id, string $usuario_nombre): bool
{
    return auditoria_registrar($conexion, [
        'accion' => 'LOGOUT',
        'usuario_id' => $usuario_id,
        'usuario_nombre' => $usuario_nombre,
        'descripcion' => "Cierre de sesión del usuario '{$usuario_nombre}'",
    ]);
}

/**
 * Registra el cambio de empresa o módulo activo del usuario.
 */
function auditoria_cambio_contexto(mysqli $conexion, int $usuario_id, int $empresa_id, int $modulo_id): bool
{
    return auditoria_registrar($conexion, [
        'accion' => 'CAMBIO_CONTEXTO',
        'usuario_id' => $usuario_id,
        'empresa_id' => $empresa_id,
        'modulo_id' => $modulo_id,
        'descripcion' => "Cambio de contexto activo a Empresa ID {$empresa_id} y Módulo ID {$modulo_id}",
    ]);
}

/**
 * Registra alertas de ciberseguridad (CSRF inválido, intentos de acceso denegado, etc.).
 */
function auditoria_seguridad(mysqli $conexion, string $accion, string $descripcion, ?int $empresa_id = null): bool
{
    return auditoria_registrar($conexion, [
        'accion' => 'SEGURIDAD_' . strtoupper($accion),
        'empresa_id' => $empresa_id,
        'descripcion' => $descripcion,
    ]);
}

/**
 * Sanitiza recursivamente un array de datos para auditoría, ofuscando contraseñas,
 * tokens o hashes sensibles.
 */
function auditoria_sanitizar_payload(mixed $data): mixed
{
    if (!is_array($data)) {
        return $data;
    }
    $clavesSensibles = [
        'password', 'password_hash', 'password_temporal', 'pass', 'clave', 
        'token', 'csrf_token', 'secret', 'secreto', 'authorization', 'api_key'
    ];
    $sanitizado = [];
    foreach ($data as $k => $v) {
        $kLower = strtolower((string)$k);
        $esSensible = false;
        foreach ($clavesSensibles as $cs) {
            if (strpos($kLower, $cs) !== false) {
                $esSensible = true;
                break;
            }
        }
        if ($esSensible) {
            $sanitizado[$k] = '********';
        } elseif (is_array($v)) {
            $sanitizado[$k] = auditoria_sanitizar_payload($v);
        } else {
            $sanitizado[$k] = $v;
        }
    }
    return $sanitizado;
}

/**
 * Registra la creación o alta de un nuevo registro en el sistema con su payload de datos.
 */
function auditoria_registrar_alta(
    mysqli $conexion, 
    string $tabla, 
    string|int $registroId, 
    array $datos, 
    ?string $descripcion = null
): bool {
    $GLOBALS['__auditoria_manual_registrada'] = true;
    $datosSanitizados = auditoria_sanitizar_payload($datos);
    $desc = $descripcion ?: "Alta de registro #{$registroId} en la tabla '{$tabla}'";
    return auditoria_registrar($conexion, [
        'accion' => 'ALTA',
        'tabla_afectada' => $tabla,
        'registro_id' => (string)$registroId,
        'datos_despues' => $datosSanitizados,
        'descripcion' => $desc,
    ]);
}

/**
 * Registra la modificación de un registro guardando la comparativa ANTES y DESPUÉS en formato JSON.
 */
function auditoria_registrar_modificacion(
    mysqli $conexion, 
    string $tabla, 
    string|int $registroId, 
    array $datosAntes, 
    array $datosDespues, 
    ?string $descripcion = null
): bool {
    $GLOBALS['__auditoria_manual_registrada'] = true;
    $antesSanitizado = auditoria_sanitizar_payload($datosAntes);
    $despuesSanitizado = auditoria_sanitizar_payload($datosDespues);
    
    // Identificar campos que cambiaron para el resumen
    $cambios = [];
    foreach ($despuesSanitizado as $k => $v) {
        if (array_key_exists($k, $antesSanitizado) && $antesSanitizado[$k] != $v) {
            $cambios[] = $k;
        }
    }
    $detalleCambios = !empty($cambios) ? " (Campos modificados: " . implode(', ', array_slice($cambios, 0, 5)) . ")" : "";
    $desc = $descripcion ?: "Modificación de registro #{$registroId} en '{$tabla}'{$detalleCambios}";

    return auditoria_registrar($conexion, [
        'accion' => 'MODIFICACION',
        'tabla_afectada' => $tabla,
        'registro_id' => (string)$registroId,
        'datos_antes' => $antesSanitizado,
        'datos_despues' => $despuesSanitizado,
        'descripcion' => $desc,
    ]);
}

/**
 * Registra la eliminación o inhabilitación de un registro, guardando su estado previo.
 */
function auditoria_registrar_baja(
    mysqli $conexion, 
    string $tabla, 
    string|int $registroId, 
    ?array $datosAntes = null, 
    ?string $descripcion = null
): bool {
    $GLOBALS['__auditoria_manual_registrada'] = true;
    $antesSanitizado = $datosAntes ? auditoria_sanitizar_payload($datosAntes) : null;
    $desc = $descripcion ?: "Baja / Inhabilitación de registro #{$registroId} en la tabla '{$tabla}'";
    return auditoria_registrar($conexion, [
        'accion' => 'BAJA',
        'tabla_afectada' => $tabla,
        'registro_id' => (string)$registroId,
        'datos_antes' => $antesSanitizado,
        'descripcion' => $desc,
    ]);
}

/**
 * Registra una operación de negocio específica (ej: APROBAR, ANULAR, LIQUIDAR, etc.).
 */
function auditoria_registrar_operacion(
    mysqli $conexion, 
    string $accion, 
    string $tabla, 
    string|int $registroId, 
    ?array $datosAntes = null, 
    ?array $datosDespues = null, 
    ?string $descripcion = null
): bool {
    $GLOBALS['__auditoria_manual_registrada'] = true;
    return auditoria_registrar($conexion, [
        'accion' => strtoupper($accion),
        'tabla_afectada' => $tabla,
        'registro_id' => (string)$registroId,
        'datos_antes' => $datosAntes ? auditoria_sanitizar_payload($datosAntes) : null,
        'datos_despues' => $datosDespues ? auditoria_sanitizar_payload($datosDespues) : null,
        'descripcion' => $descripcion ?: "Operación " . strtoupper($accion) . " sobre registro #{$registroId} en '{$tabla}'",
    ]);
}

/**
 * Agrega una tabla y su ID a la lista de entidades involucradas en la transacción/proceso en curso.
 */
function auditoria_agregar_tabla_involucrada(
    string $tabla,
    string|int $registroId,
    string $accion = 'MODIFICACION',
    ?string $rol = null,
    ?array $datos = null
): void {
    if (!isset($GLOBALS['__auditoria_tablas_involucradas']) || !is_array($GLOBALS['__auditoria_tablas_involucradas'])) {
        $GLOBALS['__auditoria_tablas_involucradas'] = [];
    }

    $GLOBALS['__auditoria_tablas_involucradas'][] = [
        'tabla' => trim($tabla),
        'registro_id' => (string)$registroId,
        'accion' => strtoupper($accion),
        'rol' => $rol ?: 'Entidad afectada',
        'datos' => $datos ? auditoria_sanitizar_payload($datos) : null,
    ];
}

/**
 * Limpia el recolector de tablas involucradas.
 */
function auditoria_limpiar_tablas_involucradas(): void {
    $GLOBALS['__auditoria_tablas_involucradas'] = [];
}

/**
 * Registra una operación compuesta que involucra múltiples tablas de base de datos
 * en un solo proceso de negocio (por ej: creación de comprobantes con renglones y stock).
 * 
 * @param mysqli $conexion
 * @param array{
 *     accion: string,
 *     descripcion: string,
 *     tabla_principal?: string,
 *     registro_id_principal?: string|int,
 *     tablas: array<array{
 *         tabla: string,
 *         registro_id: string|int,
 *         accion?: string,
 *         rol?: string,
 *         datos_antes?: array|null,
 *         datos_despues?: array|null
 *     }>,
 *     datos_antes?: array|null,
 *     datos_despues?: array|null
 * } $proceso
 */
function auditoria_registrar_proceso_multitabla(mysqli $conexion, array $proceso): bool {
    $GLOBALS['__auditoria_manual_registrada'] = true;

    $tablas = $proceso['tablas'] ?? [];
    if (empty($tablas)) {
        return false;
    }

    // Resolver catálogo tabla_id para cada una
    $tablasInvolucradas = [];
    foreach ($tablas as $t) {
        $tblNombre = trim((string)($t['tabla'] ?? ''));
        if ($tblNombre === '') continue;
        $tablasInvolucradas[] = [
            'tabla' => $tblNombre,
            'tabla_id' => auditoria_obtener_tabla_id($conexion, $tblNombre),
            'registro_id' => (string)($t['registro_id'] ?? ''),
            'accion' => strtoupper((string)($t['accion'] ?? $proceso['accion'] ?? 'MODIFICACION')),
            'rol' => $t['rol'] ?? 'Entidad del proceso',
        ];
    }

    $tablaPrincipal = !empty($proceso['tabla_principal']) 
        ? trim((string)$proceso['tabla_principal']) 
        : ($tablasInvolucradas[0]['tabla'] ?? '');
    
    $registroIdPrincipal = !empty($proceso['registro_id_principal'])
        ? (string)$proceso['registro_id_principal']
        : ($tablasInvolucradas[0]['registro_id'] ?? '');

    $datosDespues = !empty($proceso['datos_despues'])
        ? auditoria_sanitizar_payload($proceso['datos_despues'])
        : ($tablas[0]['datos_despues'] ?? null);

    $datosAntes = !empty($proceso['datos_antes'])
        ? auditoria_sanitizar_payload($proceso['datos_antes'])
        : ($tablas[0]['datos_antes'] ?? null);

    // Registrar evento maestro con la lista completa de tablas involucradas
    return auditoria_registrar($conexion, [
        'accion' => strtoupper((string)($proceso['accion'] ?? 'MODIFICACION')),
        'tabla_afectada' => $tablaPrincipal,
        'registro_id' => $registroIdPrincipal,
        'tablas_involucradas' => $tablasInvolucradas,
        'descripcion' => $proceso['descripcion'] ?? "Operación procesada con " . count($tablasInvolucradas) . " tablas afectadas",
        'datos_antes' => $datosAntes,
        'datos_despues' => $datosDespues,
    ]);
}

/**
 * Devuelve la lista de años disponibles que tienen tablas de auditoría en la base de datos.
 * Útil para selectores de reportes y tableros de auditoría.
 * 
 * @return int[] Lista de años ordenada descendentemente (ej. [2027, 2026])
 */
function auditoria_obtener_anios_disponibles(mysqli $conexion): array
{
    $anios = [];
    $res = mysqli_query($conexion, "SHOW TABLES LIKE 'conf__auditoria_%'");
    if ($res) {
        while ($row = mysqli_fetch_row($res)) {
            $nombreTabla = (string)$row[0];
            if (preg_match('/^conf__auditoria_(\d{4})$/', $nombreTabla, $m)) {
                $anios[] = (int)$m[1];
            }
        }
    }
    $anioActual = (int) date('Y');
    if (!in_array($anioActual, $anios, true)) {
        $anios[] = $anioActual;
    }
    rsort($anios);
    return $anios;
}

/**
 * Consulta registros de auditoría para un año determinado con filtros flexibles.
 * 
 * @param mysqli $conexion
 * @param int $anio Año a consultar
 * @param array $filtros [usuario_id, empresa_id, modulo_id, accion, fecha_desde, fecha_hasta, busqueda]
 * @param int $limite
 * @param int $offset
 * @return array{total: int, registros: array}
 */
function auditoria_consultar(mysqli $conexion, int $anio, array $filtros = [], int $limite = 50, int $offset = 0): array
{
    $tabla = auditoria_obtener_nombre_tabla($anio);
    auditoria_asegurar_tabla($conexion, $anio);

    $where = ["1=1"];
    $params = [];
    $types = "";

    if (!empty($filtros['usuario_id'])) {
        $where[] = "usuario_id = ?";
        $params[] = (int)$filtros['usuario_id'];
        $types .= "i";
    }

    if (!empty($filtros['empresa_id'])) {
        $where[] = "empresa_id = ?";
        $params[] = (int)$filtros['empresa_id'];
        $types .= "i";
    }

    if (!empty($filtros['modulo_id'])) {
        $where[] = "modulo_id = ?";
        $params[] = (int)$filtros['modulo_id'];
        $types .= "i";
    }

    if (!empty($filtros['accion'])) {
        $where[] = "accion = ?";
        $params[] = (string)$filtros['accion'];
        $types .= "s";
    }

    if (!empty($filtros['fecha_desde'])) {
        $where[] = "fecha_hora >= ?";
        $params[] = $filtros['fecha_desde'] . " 00:00:00";
        $types .= "s";
    }

    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "fecha_hora <= ?";
        $params[] = $filtros['fecha_hasta'] . " 23:59:59";
        $types .= "s";
    }

    if (!empty($filtros['busqueda'])) {
        $where[] = "(descripcion LIKE ? OR usuario_nombre LIKE ? OR tabla_afectada LIKE ? OR ip_origen LIKE ?)";
        $term = "%" . $filtros['busqueda'] . "%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $types .= "ssss";
    }

    $whereSql = implode(" AND ", $where);

    // Contar total
    $sqlTotal = "SELECT COUNT(*) AS total FROM `{$tabla}` WHERE {$whereSql}";
    $stmtTot = mysqli_prepare($conexion, $sqlTotal);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmtTot, $types, ...$params);
    }
    mysqli_stmt_execute($stmtTot);
    $resTot = mysqli_stmt_get_result($stmtTot);
    $total = ($r = mysqli_fetch_assoc($resTot)) ? (int)$r['total'] : 0;
    mysqli_stmt_close($stmtTot);

    // Obtener registros paginados
    $limite = max(1, min(500, $limite));
    $offset = max(0, $offset);
    $sqlData = "SELECT * FROM `{$tabla}` WHERE {$whereSql} ORDER BY fecha_hora DESC, auditoria_id DESC LIMIT ? OFFSET ?";
    
    $paramsData = $params;
    $paramsData[] = $limite;
    $paramsData[] = $offset;
    $typesData = $types . "ii";

    $stmtData = mysqli_prepare($conexion, $sqlData);
    mysqli_stmt_bind_param($stmtData, $typesData, ...$paramsData);
    mysqli_stmt_execute($stmtData);
    $resData = mysqli_stmt_get_result($stmtData);

    $registros = [];
    while ($row = mysqli_fetch_assoc($resData)) {
        $registros[] = $row;
    }
    mysqli_stmt_close($stmtData);

    return ['total' => $total, 'registros' => $registros];
}
