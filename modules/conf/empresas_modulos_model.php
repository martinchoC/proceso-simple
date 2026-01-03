<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerEmpresasModulos($conexion) {
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

function obtenerEmpresas($conexion) {
    $sql = "SELECT empresa_id, empresa FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerModulos($conexion) {
    $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function existeEmpresaModulo($conexion, $empresa_id, $modulo_id) {
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    
    $sql = "SELECT empresa_modulo_id FROM conf__empresas_modulos 
            WHERE empresa_id = $empresa_id AND modulo_id = $modulo_id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_num_rows($res) > 0;
}

function existeEmpresaModuloEditando($conexion, $empresa_id, $modulo_id, $excluir_id) {
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

function agregarEmpresaModulo($conexion, $data) {
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

function editarEmpresaModulo($conexion, $id, $data) {
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

function cambiarEstadoEmpresaModulo($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__empresas_modulos SET tabla_estado_registro_id = $nuevo_estado WHERE empresa_modulo_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerEmpresaModuloPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__empresas_modulos WHERE empresa_modulo_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

// NUEVAS FUNCIONES PARA COPIAR PERFILES

function verificarPerfilesExistentes($conexion, $empresa_id, $modulo_id) {
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    
    // Verificar perfiles genéricos del módulo
    $sql_perfiles_base = "SELECT p.perfil_id, p.perfil_nombre 
                          FROM conf__perfiles p
                          WHERE p.modulo_id = $modulo_id 
                          AND p.tabla_estado_registro_id = 1
                          ORDER BY p.perfil_nombre";
    $res = mysqli_query($conexion, $sql_perfiles_base);
    $perfiles_base = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $perfiles_base[] = $row;
    }
    
    // Verificar qué perfiles ya están copiados para esta empresa
    $perfiles_copiados = [];
    $perfiles_faltantes = [];
    
    foreach ($perfiles_base as $perfil) {
        $perfil_id_base = $perfil['perfil_id'];
        
        // Verificar si este perfil base ya existe para la empresa
        $sql_existe = "SELECT empresa_perfil_id, empresa_perfil_nombre 
                      FROM conf__empresas_perfiles 
                      WHERE empresa_id = $empresa_id 
                      AND modulo_id = $modulo_id
                      AND perfil_id_base = $perfil_id_base";
        $res_existe = mysqli_query($conexion, $sql_existe);
        
        if (mysqli_num_rows($res_existe) > 0) {
            $row = mysqli_fetch_assoc($res_existe);
            $perfiles_copiados[] = [
                'perfil_id_base' => $perfil_id_base,
                'perfil_nombre' => $perfil['perfil_nombre'],
                'empresa_perfil_nombre' => $row['empresa_perfil_nombre'],
                'empresa_perfil_id' => $row['empresa_perfil_id']
            ];
        } else {
            $perfiles_faltantes[] = [
                'perfil_id_base' => $perfil_id_base,
                'perfil_nombre' => $perfil['perfil_nombre']
            ];
        }
    }
    
    return [
        'perfiles_base_total' => count($perfiles_base),
        'perfiles_copiados_total' => count($perfiles_copiados),
        'perfiles_faltantes_total' => count($perfiles_faltantes),
        'perfiles_copiados' => $perfiles_copiados,
        'perfiles_faltantes' => $perfiles_faltantes
    ];
}

function existePerfilBaseEnEmpresa($conexion, $empresa_id, $modulo_id, $perfil_id_base) {
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

function copiarPerfilesModulo($conexion, $empresa_id, $modulo_id) {
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    
    mysqli_begin_transaction($conexion);
    
    try {
        // 1. Obtener perfiles genéricos del módulo que aún no están copiados
        $sql_perfiles = "SELECT p.* FROM conf__perfiles p
                        WHERE p.modulo_id = $modulo_id 
                        AND p.tabla_estado_registro_id = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM conf__empresas_perfiles ep
                            WHERE ep.empresa_id = $empresa_id 
                            AND ep.modulo_id = $modulo_id
                            AND ep.perfil_id_base = p.perfil_id
                        )";
        $res_perfiles = mysqli_query($conexion, $sql_perfiles);
        
        $perfiles_copiados = 0;
        $funciones_copiadas = 0;
        $perfiles_omitidos = 0;
        
        while ($perfil = mysqli_fetch_assoc($res_perfiles)) {
            $perfil_id_base = $perfil['perfil_id'];
            
            // Verificar nuevamente por si acaso (doble validación)
            if (existePerfilBaseEnEmpresa($conexion, $empresa_id, $modulo_id, $perfil_id_base)) {
                $perfiles_omitidos++;
                continue; // Saltar este perfil, ya existe
            }
            
            // 2. Insertar perfil en la tabla de empresas
            $sql_insert_perfil = "INSERT INTO conf__empresas_perfiles 
                                 (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id)
                                 VALUES ($empresa_id, $modulo_id, $perfil_id_base, 
                                         '" . mysqli_real_escape_string($conexion, $perfil['perfil_nombre']) . "', 1)";
            
            if (!mysqli_query($conexion, $sql_insert_perfil)) {
                throw new Exception("Error al copiar perfil '{$perfil['perfil_nombre']}': " . mysqli_error($conexion));
            }
            
            $nuevo_perfil_id = mysqli_insert_id($conexion);
            $perfiles_copiados++;
            
            // 3. Obtener funciones del perfil base
            $sql_funciones = "SELECT * FROM conf__perfiles_funciones 
                             WHERE perfil_id = $perfil_id_base 
                             AND asignado = 1";
            $res_funciones = mysqli_query($conexion, $sql_funciones);
            
            while ($funcion = mysqli_fetch_assoc($res_funciones)) {
                // Verificar que no exista ya esta función para este perfil de empresa
                $sql_check_funcion = "SELECT empresa_perfil_funcion_id 
                                     FROM conf__empresas_perfiles_funciones 
                                     WHERE empresa_id = $empresa_id 
                                     AND empresa_perfil_id = $nuevo_perfil_id
                                     AND pagina_funcion_id = " . intval($funcion['pagina_funcion_id']);
                $res_check = mysqli_query($conexion, $sql_check_funcion);
                
                if (mysqli_num_rows($res_check) == 0) {
                    $sql_insert_funcion = "INSERT INTO conf__empresas_perfiles_funciones 
                                          (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
                                          VALUES ($empresa_id, $nuevo_perfil_id, 
                                                  " . intval($funcion['pagina_funcion_id']) . ", 1)";
                    
                    if (!mysqli_query($conexion, $sql_insert_funcion)) {
                        throw new Exception("Error al copiar función para perfil '{$perfil['perfil_nombre']}': " . mysqli_error($conexion));
                    }
                    
                    $funciones_copiadas++;
                }
            }
        }
        
        mysqli_commit($conexion);
        
        return [
            'resultado' => true,
            'mensaje' => "Proceso de copia completado",
            'perfiles_copiados' => $perfiles_copiados,
            'perfiles_omitidos' => $perfiles_omitidos,
            'funciones_copiadas' => $funciones_copiadas
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return [
            'resultado' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>