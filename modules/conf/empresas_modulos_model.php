<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/perfiles_predefinidos.php';
$conexion = $conn;

function obtenerEmpresasModulos($conexion)
{
    $sql = "SELECT em.*, e.empresa, m.modulo 
            FROM conf__empresas_modulos em
            LEFT JOIN conf__empresas e ON em.empresa_id = e.empresa_id
            LEFT JOIN conf__modulos m ON em.modulo_id = m.modulo_id
            ORDER BY e.empresa, m.modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresas($conexion)
{
    $sql = "SELECT empresa_id, empresa FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerModulos($conexion)
{
    $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function existeEmpresaModulo($conexion, $empresa_id, $modulo_id)
{
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);

    $sql = "SELECT empresa_modulo_id FROM conf__empresas_modulos 
            WHERE empresa_id = $empresa_id AND modulo_id = $modulo_id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_num_rows($res) > 0;
}

function existeEmpresaModuloEditando($conexion, $empresa_id, $modulo_id, $excluir_id)
{
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    $excluir_id = intval($excluir_id);

    $sql = "SELECT empresa_modulo_id FROM conf__empresas_modulos 
            WHERE empresa_id = $empresa_id 
            AND modulo_id = $modulo_id
            AND empresa_modulo_id != $excluir_id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_num_rows($res) > 0;
}

function obtenerModuloConflictoExcluyente($conexion, $empresa_id, $modulo_id, $excluir_empresa_modulo_id = 0)
{
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    $excluir_empresa_modulo_id = intval($excluir_empresa_modulo_id);

    // Consulta la tabla relacional conf__modulos_incompatibles
    $sql = "SELECT m.modulo, mi.motivo
            FROM conf__empresas_modulos em
            INNER JOIN conf__modulos_incompatibles mi 
                ON ( (mi.modulo_id_1 = $modulo_id AND mi.modulo_id_2 = em.modulo_id) 
                  OR (mi.modulo_id_2 = $modulo_id AND mi.modulo_id_1 = em.modulo_id) )
                AND mi.tabla_estado_registro_id = 1
            INNER JOIN conf__modulos m ON m.modulo_id = em.modulo_id
            WHERE em.empresa_id = $empresa_id 
              AND em.tabla_estado_registro_id = 1
              AND em.empresa_modulo_id != $excluir_empresa_modulo_id
            LIMIT 1";

    $res = mysqli_query($conexion, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row['modulo'];
    }
    return null;
}

function agregarEmpresaModulo($conexion, $data)
{
    if (empty($data['empresa_id']) || empty($data['modulo_id'])) {
        return false;
    }

    $empresa_id = intval($data['empresa_id']);
    $modulo_id = intval($data['modulo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id) 
            VALUES ($empresa_id, $modulo_id, $tabla_estado_registro_id)";

    return mysqli_query($conexion, $sql);
}

function editarEmpresaModulo($conexion, $id, $data)
{
    if (empty($data['empresa_id']) || empty($data['modulo_id'])) {
        return false;
    }

    $id = intval($id);
    $empresa_id = intval($data['empresa_id']);
    $modulo_id = intval($data['modulo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__empresas_modulos SET
            empresa_id = $empresa_id,
            modulo_id = $modulo_id,
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE empresa_modulo_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoEmpresaModulo($conexion, $id, $nuevo_estado)
{
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);

    $sql = "UPDATE conf__empresas_modulos SET tabla_estado_registro_id = $nuevo_estado WHERE empresa_modulo_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerEmpresaModuloPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM conf__empresas_modulos WHERE empresa_modulo_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

// NUEVAS FUNCIONES PARA COPIAR PERFILES

// Perfiles base del módulo que corresponden al tipo de operación de la empresa (BIENES / SERVICIOS /
// MIXTA) y cuáles ya tiene copiados. Ver core/perfiles_predefinidos.php.
function verificarPerfilesExistentes($conexion, $empresa_id, $modulo_id)
{
    $perfiles_copiados = [];
    $perfiles_faltantes = [];

    foreach (perfiles_base_para_empresa($conexion, intval($empresa_id), intval($modulo_id)) as $perfil) {
        if ($perfil['empresa_perfil_id'] !== null) {
            $perfiles_copiados[] = [
                'perfil_id_base' => $perfil['perfil_id'],
                'perfil_nombre' => $perfil['perfil_nombre'],
                'empresa_perfil_nombre' => $perfil['perfil_nombre'],
                'empresa_perfil_id' => $perfil['empresa_perfil_id']
            ];
        } else {
            $perfiles_faltantes[] = [
                'perfil_id_base' => $perfil['perfil_id'],
                'perfil_nombre' => $perfil['perfil_nombre']
            ];
        }
    }

    return [
        'perfiles_base_total' => count($perfiles_copiados) + count($perfiles_faltantes),
        'perfiles_copiados_total' => count($perfiles_copiados),
        'perfiles_faltantes_total' => count($perfiles_faltantes),
        'perfiles_copiados' => $perfiles_copiados,
        'perfiles_faltantes' => $perfiles_faltantes
    ];
}

function existePerfilBaseEnEmpresa($conexion, $empresa_id, $modulo_id, $perfil_id_base)
{
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    $perfil_id_base = intval($perfil_id_base);

    $sql = "SELECT empresa_perfil_id FROM conf__empresas_perfiles 
            WHERE empresa_id = $empresa_id 
            AND modulo_id = $modulo_id
            AND perfil_id_base = $perfil_id_base";
    $res = mysqli_query($conexion, $sql);
    return mysqli_num_rows($res) > 0;
}

// Copia a la empresa los perfiles base del módulo que le faltan, según su tipo de operación; en una
// empresa SERVICIOS sin las funciones de páginas de bienes. Ver core/perfiles_predefinidos.php.
function copiarPerfilesModulo($conexion, $empresa_id, $modulo_id)
{
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);

    mysqli_begin_transaction($conexion);

    try {
        $perfiles_copiados = 0;
        $perfiles_omitidos = 0;
        $funciones_copiadas = 0;

        foreach (perfiles_base_para_empresa($conexion, $empresa_id, $modulo_id) as $perfil) {
            if ($perfil['empresa_perfil_id'] !== null) {
                $perfiles_omitidos++;
                continue;
            }
            $r = perfil_base_copiar_a_empresa($conexion, $empresa_id, $modulo_id, $perfil['perfil_id']);
            $perfiles_copiados++;
            $funciones_copiadas += $r['funciones'];
        }

        mysqli_commit($conexion);

        return [
            'resultado' => true,
            'mensaje' => "Proceso de copia completado",
            'perfiles_copiados' => $perfiles_copiados,
            'perfiles_omitidos' => $perfiles_omitidos,
            'funciones_copiadas' => $funciones_copiadas
        ];

    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'resultado' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>