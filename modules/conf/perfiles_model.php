<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// Perfiles base (plantillas que se copian a cada empresa como conf__empresas_perfiles).
// Inactivo = estado genérico 2.
const PERFIL_ACTIVO = 1;
const PERFIL_INACTIVO = 2;
const PERFIL_TIPOS_OPERACION = ['TODAS', 'BIENES', 'SERVICIOS'];

/**
 * Perfiles con sus indicadores: funciones y páginas asignadas, empresas que lo usan como base.
 */
function obtenerPerfiles($conexion)
{
    $sql = "SELECT p.perfil_id, p.modulo_id, p.perfil_nombre, p.tipo_operacion, p.tabla_estado_registro_id,
                   COALESCE(m.modulo, '') AS modulo,
                   COALESCE(f.funciones, 0) AS funciones, COALESCE(f.paginas, 0) AS paginas,
                   COALESCE(e.empresas, 0) AS empresas, COALESCE(e.perfiles_empresa, 0) AS perfiles_empresa
              FROM conf__perfiles p
              LEFT JOIN conf__modulos m ON m.modulo_id = p.modulo_id
              LEFT JOIN (SELECT pf.perfil_id, COUNT(*) AS funciones, COUNT(DISTINCT f.pagina_id) AS paginas
                           FROM conf__perfiles_funciones pf
                           JOIN conf__paginas_funciones f ON f.pagina_funcion_id = pf.pagina_funcion_id AND f.tabla_estado_registro_id = 1
                          WHERE pf.asignado = 1
                          GROUP BY pf.perfil_id) f ON f.perfil_id = p.perfil_id
              LEFT JOIN (SELECT perfil_id_base, COUNT(DISTINCT empresa_id) AS empresas, COUNT(*) AS perfiles_empresa
                           FROM conf__empresas_perfiles
                          WHERE tabla_estado_registro_id = 1
                          GROUP BY perfil_id_base) e ON e.perfil_id_base = p.perfil_id
             ORDER BY m.modulo_id, p.tabla_estado_registro_id, p.perfil_id";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        foreach (['perfil_id', 'modulo_id', 'tabla_estado_registro_id', 'funciones', 'paginas', 'empresas', 'perfiles_empresa'] as $k) {
            $fila[$k] = (int)$fila[$k];
        }
        $data[] = $fila;
    }
    return $data;
}

function obtenerModulos($conexion)
{
    $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo_id";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

/**
 * Próximo id libre del bloque del módulo (CLAUDE.md, "Bloques de ids"): conf 1–99, módulo N >= 2 → N01–N99.
 * Así el perfil sale con el mismo id en local y en producción.
 */
function perfilProximoId(mysqli $conexion, int $modulo_id): int
{
    $desde = $modulo_id <= 1 ? 1 : $modulo_id * 100 + 1;
    $hasta = $modulo_id <= 1 ? 99 : $modulo_id * 100 + 99;
    $stmt = mysqli_prepare($conexion, "SELECT COALESCE(MAX(perfil_id), 0) FROM conf__perfiles WHERE perfil_id BETWEEN ? AND ?");
    mysqli_stmt_bind_param($stmt, 'ii', $desde, $hasta);
    mysqli_stmt_execute($stmt);
    $max = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);
    $id = max($max + 1, $desde);
    if ($id > $hasta) {
        throw new RuntimeException("El bloque de ids de perfiles del módulo $modulo_id ($desde–$hasta) está completo.");
    }
    return $id;
}

function perfilValidar(mysqli $conexion, array $data): array
{
    $nombre = trim((string)($data['perfil_nombre'] ?? ''));
    $modulo_id = (int)($data['modulo_id'] ?? 0);
    $tipo = strtoupper((string)($data['tipo_operacion'] ?? 'TODAS'));
    $estado = (int)($data['tabla_estado_registro_id'] ?? PERFIL_ACTIVO) === PERFIL_ACTIVO ? PERFIL_ACTIVO : PERFIL_INACTIVO;
    if ($nombre === '') {
        throw new RuntimeException('Escribí el nombre del perfil.');
    }
    if ($modulo_id <= 0) {
        throw new RuntimeException('Elegí el módulo del perfil.');
    }
    if (!in_array($tipo, PERFIL_TIPOS_OPERACION, true)) {
        $tipo = 'TODAS';
    }
    return [$nombre, $modulo_id, $tipo, $estado];
}

function perfilNombreRepetido(mysqli $conexion, int $modulo_id, string $nombre, int $excluir_id): bool
{
    $stmt = mysqli_prepare($conexion,
        "SELECT 1 FROM conf__perfiles WHERE modulo_id = ? AND LOWER(TRIM(perfil_nombre)) = LOWER(?) AND perfil_id <> ?");
    mysqli_stmt_bind_param($stmt, 'isi', $modulo_id, $nombre, $excluir_id);
    mysqli_stmt_execute($stmt);
    $hay = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $hay;
}

function agregarPerfil($conexion, $data): array
{
    try {
        [$nombre, $modulo_id, $tipo, $estado] = perfilValidar($conexion, $data);
        if (perfilNombreRepetido($conexion, $modulo_id, $nombre, 0)) {
            throw new RuntimeException('Ya hay un perfil con ese nombre en el módulo.');
        }
        $id = perfilProximoId($conexion, $modulo_id);
        $stmt = mysqli_prepare($conexion,
            "INSERT INTO conf__perfiles (perfil_id, modulo_id, perfil_nombre, tipo_operacion, tabla_estado_registro_id) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iissi', $id, $modulo_id, $nombre, $tipo, $estado);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => true, 'data' => ['perfil_id' => $id], 'message' => 'Perfil creado'];
    } catch (Throwable $e) {
        error_log('=== agregarPerfil: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function editarPerfil($conexion, $id, $data): array
{
    try {
        $id = (int)$id;
        [$nombre, $modulo_id, $tipo, $estado] = perfilValidar($conexion, $data);
        $actual = obtenerPerfilPorId($conexion, $id);
        if (!$actual) {
            throw new RuntimeException('El perfil no existe.');
        }
        // Cambiar de módulo dejaría sus funciones (de páginas del módulo anterior) colgadas
        if ((int)$actual['modulo_id'] !== $modulo_id && (int)$actual['funciones'] > 0) {
            throw new RuntimeException('El perfil ya tiene funciones asignadas: no se puede cambiar de módulo.');
        }
        if (perfilNombreRepetido($conexion, $modulo_id, $nombre, $id)) {
            throw new RuntimeException('Ya hay un perfil con ese nombre en el módulo.');
        }
        $stmt = mysqli_prepare($conexion,
            "UPDATE conf__perfiles SET modulo_id = ?, perfil_nombre = ?, tipo_operacion = ?, tabla_estado_registro_id = ? WHERE perfil_id = ?");
        mysqli_stmt_bind_param($stmt, 'issii', $modulo_id, $nombre, $tipo, $estado, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Perfil guardado'];
    } catch (Throwable $e) {
        error_log('=== editarPerfil: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function cambiarEstadoPerfil($conexion, $id, $nuevo_estado): array
{
    $id = (int)$id;
    $estado = (int)$nuevo_estado === PERFIL_ACTIVO ? PERFIL_ACTIVO : PERFIL_INACTIVO;
    $stmt = mysqli_prepare($conexion, "UPDATE conf__perfiles SET tabla_estado_registro_id = ? WHERE perfil_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $estado, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok ? ['success' => true, 'message' => $estado === PERFIL_ACTIVO ? 'Perfil activado' : 'Perfil inactivado']
               : ['success' => false, 'message' => 'No se pudo cambiar el estado.'];
}

function obtenerPerfilPorId($conexion, $id)
{
    $id = (int)$id;
    $stmt = mysqli_prepare($conexion,
        "SELECT p.*, (SELECT COUNT(*) FROM conf__perfiles_funciones pf WHERE pf.perfil_id = p.perfil_id AND pf.asignado = 1) AS funciones
           FROM conf__perfiles p WHERE p.perfil_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: null;
}
