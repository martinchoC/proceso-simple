<?php
/**
 * Modelo para Parámetros Generales e Historial de Empresa
 * Tabla principal: gestion__empresas_parametros
 * Sincroniza: conf__empresas.condicion_fiscal_id
 */

if (!function_exists('obtenerEmpresasCatalogo')) {
    function obtenerEmpresasCatalogo($conexion) {
        $sql = "SELECT empresa_id, empresa, documento_numero 
                FROM conf__empresas 
                WHERE tabla_estado_registro_id = 1 
                ORDER BY empresa ASC";
        $res = mysqli_query($conexion, $sql);
        $data = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('obtenerCondicionesFiscalesCatalogo')) {
    function obtenerCondicionesFiscalesCatalogo($conexion) {
        $sql = "SELECT condicion_fiscal_id, condicion_fiscal, condicion_fiscal_codigo, condicion_fiscal_descripcion
                FROM gestion__condiciones_fiscales
                WHERE tabla_estado_registro_id = 1
                ORDER BY condicion_fiscal_id ASC";
        $res = mysqli_query($conexion, $sql);
        $data = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('obtenerIibbRegimenesCatalogo')) {
    function obtenerIibbRegimenesCatalogo($conexion) {
        $sql = "SELECT iibb_regimen_id, iibb_regimen_codigo, iibb_regimen_nombre, descripcion
                FROM gestion__iibb_regimenes
                WHERE tabla_estado_registro_id = 1
                ORDER BY orden ASC, iibb_regimen_id ASC";
        $res = mysqli_query($conexion, $sql);
        $data = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('obtenerJurisdiccionesSedesCatalogo')) {
    function obtenerJurisdiccionesSedesCatalogo($conexion) {
        $sql = "SELECT jurisdiccion_sede_id, codigo_comarb, jurisdiccion_sede, organismo, provincia_id
                FROM gestion__iibb_jurisdicciones_sedes
                WHERE tabla_estado_registro_id = 1
                ORDER BY CAST(codigo_comarb AS UNSIGNED), codigo_comarb ASC";
        $res = mysqli_query($conexion, $sql);
        $data = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

function obtenerPuntosVentaCatalogo($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT pv.punto_venta_id, pv.codigo_fiscal as punto_venta_numero, pv.nombre as punto_venta_descripcion, s.sucursal_nombre
            FROM gestion__puntos_venta pv
            LEFT JOIN gestion__sucursales s ON pv.sucursal_id = s.sucursal_id
            WHERE pv.empresa_id = $empresa_id AND pv.tabla_estado_registro_id = 1
            ORDER BY pv.codigo_fiscal ASC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
    }
    return $data;
}

function obtenerListasPreciosCatalogo($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT lista_precio_id, lista_precio_nombre as lista_precio, lista_precio_codigo 
            FROM gestion__listas_precios 
            WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 1 
            ORDER BY lista_precio_nombre ASC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
    }
    return $data;
}

function obtenerCondicionesPagoCatalogo($conexion, $empresa_id = null) {
    $whereEmpresa = "";
    if (!empty($empresa_id)) {
        $empresa_id = intval($empresa_id);
        $whereEmpresa = "AND (empresa_id = $empresa_id OR empresa_id IS NULL)";
    }
    $sql = "SELECT condicion_pago_id, condicion_pago, codigo 
            FROM gestion__condiciones_pago 
            WHERE tabla_estado_registro_id = 1 $whereEmpresa
            ORDER BY condicion_pago ASC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
    }
    return $data;
}

// ---------- Logo para comprobantes impresos (gestion__empresas_parametros.logo_archivo) ----------
// Archivo en uploads/empresas/ (ruta relativa a la raíz del proyecto). Lo imprime ventas_facturas_print.php.
if (!defined('EMPRESAS_LOGO_DIR_REL')) define('EMPRESAS_LOGO_DIR_REL', 'uploads/empresas');
if (!defined('EMPRESAS_LOGO_MAX_BYTES')) define('EMPRESAS_LOGO_MAX_BYTES', 1048576);

function empresasLogoColumnaExiste($conexion) {
    static $existe = null;
    if ($existe === null) {
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'logo_archivo'");
        $existe = $res && mysqli_num_rows($res) > 0;
    }
    return $existe;
}

// compra_actualiza_lista_proveedor / compra_actualiza_listas_venta (sql/mantenimiento/20261010_precios_compra_01_estructura.sql)
function empresasComprasColumnasExisten($conexion) {
    static $existe = null;
    if ($existe === null) {
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'compra_actualiza_listas_venta'");
        $existe = $res && mysqli_num_rows($res) > 0;
    }
    return $existe;
}

// Graba qué hace la confirmación de una factura de proveedor con la lista del proveedor y las de venta.
function guardarOpcionesComprasEmpresa($conexion, $empresa_parametro_id, $data) {
    if (!empresasComprasColumnasExisten($conexion)) return;
    $lista_proveedor = min(2, max(0, intval($data['compra_actualiza_lista_proveedor'] ?? 0)));
    $listas_venta = intval($data['compra_actualiza_listas_venta'] ?? 0) === 1 ? 1 : 0;
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_parametros
        SET compra_actualiza_lista_proveedor = ?, compra_actualiza_listas_venta = ?
        WHERE empresa_parametro_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $lista_proveedor, $listas_venta, $empresa_parametro_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al guardar las opciones de compras: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
}

// Valida y guarda el logo subido. Devuelve ['ruta' => 'uploads/empresas/...' | null] o ['error' => '...'].
function guardarArchivoLogoEmpresa($archivo, $empresa_id) {
    if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ruta' => null];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No se pudo recibir el archivo del logo (código ' . intval($archivo['error']) . ').'];
    }
    if ($archivo['size'] > EMPRESAS_LOGO_MAX_BYTES) {
        return ['error' => 'El logo no puede superar 1 MB.'];
    }
    // El tipo se toma del contenido, no de la extensión ni del navegador (sin SVG: puede llevar scripts)
    $info = @getimagesize($archivo['tmp_name']);
    $extensiones = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!$info || !isset($extensiones[$info['mime']])) {
        return ['error' => 'El logo debe ser una imagen PNG, JPG, GIF o WEBP.'];
    }
    $dir = __DIR__ . '/../../' . EMPRESAS_LOGO_DIR_REL;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['error' => 'No se pudo crear la carpeta de logos.'];
    }
    $nombre = 'logo_' . intval($empresa_id) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensiones[$info['mime']];
    if (!move_uploaded_file($archivo['tmp_name'], $dir . '/' . $nombre)) {
        error_log("=== guardarArchivoLogoEmpresa: no se pudo mover el archivo a $dir/$nombre ===");
        return ['error' => 'No se pudo guardar el archivo del logo.'];
    }
    return ['ruta' => EMPRESAS_LOGO_DIR_REL . '/' . $nombre];
}

// Logo como data URI para la vista previa (vacío si no hay o el archivo no está).
function logoEmpresaDataUri($ruta_relativa) {
    if (empty($ruta_relativa)) return '';
    $base = realpath(__DIR__ . '/../../' . EMPRESAS_LOGO_DIR_REL);
    $archivo = realpath(__DIR__ . '/../../' . $ruta_relativa);
    if (!$base || !$archivo || strpos($archivo, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($archivo)) return '';
    $info = @getimagesize($archivo);
    if (!$info) return '';
    return 'data:' . $info['mime'] . ';base64,' . base64_encode(file_get_contents($archivo));
}

// Último logo cargado para la empresa (lo hereda una vigencia nueva que no sube otro).
function obtenerUltimoLogoEmpresa($conexion, $empresa_id) {
    if (!empresasLogoColumnaExiste($conexion)) return null;
    $stmt = mysqli_prepare($conexion, "SELECT logo_archivo FROM gestion__empresas_parametros
                                       WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                         AND logo_archivo IS NOT NULL AND logo_archivo <> ''
                                       ORDER BY (f_hasta IS NULL) DESC, f_desde DESC, empresa_parametro_id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row['logo_archivo'] ?? null;
}

function obtenerParametrosVigentesEmpresa($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT ep.*, 
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   reg.iibb_regimen_codigo, reg.iibb_regimen_nombre, reg.descripcion as iibb_regimen_descripcion,
                   sed.codigo_comarb, sed.jurisdiccion_sede, sed.organismo as jurisdiccion_sede_organismo,
                   pv.codigo_fiscal as punto_venta_numero, pv.nombre as punto_venta_descripcion,
                   lp.lista_precio_nombre as lista_precio,
                   cp.condicion_pago,
                   e.empresa as empresa_nombre, e.documento_numero as empresa_cuit,
                   ter.tabla_estado_registro as estado_registro,
                   col.bg_clase
            FROM gestion__empresas_parametros ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN gestion__condiciones_fiscales cf ON ep.condicion_fiscal_id = cf.condicion_fiscal_id
            LEFT JOIN gestion__iibb_regimenes reg ON ep.iibb_regimen_id = reg.iibb_regimen_id
            LEFT JOIN gestion__iibb_jurisdicciones_sedes sed ON ep.jurisdiccion_sede_id = sed.jurisdiccion_sede_id
            LEFT JOIN gestion__puntos_venta pv ON ep.punto_venta_defecto_id = pv.punto_venta_id
            LEFT JOIN gestion__listas_precios lp ON ep.lista_precio_defecto_id = lp.lista_precio_id
            LEFT JOIN gestion__condiciones_pago cp ON ep.condicion_pago_defecto_id = cp.condicion_pago_id
            LEFT JOIN conf__tablas_estados_registros ter ON ep.tabla_estado_registro_id = ter.tabla_estado_registro_id
            LEFT JOIN conf__colores col ON ter.color_id = col.color_id
            WHERE ep.empresa_id = $empresa_id 
              AND ep.f_hasta IS NULL 
              AND ep.tabla_estado_registro_id = 1
            ORDER BY ep.f_desde DESC, ep.empresa_parametro_id DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $row['logo_data'] = logoEmpresaDataUri($row['logo_archivo'] ?? '');
        return $row;
    }
    return null;
}

function obtenerHistorialParametrosEmpresa($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT ep.*, 
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   reg.iibb_regimen_codigo, reg.iibb_regimen_nombre,
                   sed.codigo_comarb, sed.jurisdiccion_sede, sed.organismo as jurisdiccion_sede_organismo,
                   pv.codigo_fiscal as punto_venta_numero, pv.nombre as punto_venta_descripcion,
                   lp.lista_precio_nombre as lista_precio,
                   cp.condicion_pago,
                   ter.tabla_estado_registro as estado_registro,
                   col.bg_clase
            FROM gestion__empresas_parametros ep
            INNER JOIN gestion__condiciones_fiscales cf ON ep.condicion_fiscal_id = cf.condicion_fiscal_id
            LEFT JOIN gestion__iibb_regimenes reg ON ep.iibb_regimen_id = reg.iibb_regimen_id
            LEFT JOIN gestion__iibb_jurisdicciones_sedes sed ON ep.jurisdiccion_sede_id = sed.jurisdiccion_sede_id
            LEFT JOIN gestion__puntos_venta pv ON ep.punto_venta_defecto_id = pv.punto_venta_id
            LEFT JOIN gestion__listas_precios lp ON ep.lista_precio_defecto_id = lp.lista_precio_id
            LEFT JOIN gestion__condiciones_pago cp ON ep.condicion_pago_defecto_id = cp.condicion_pago_id
            LEFT JOIN conf__tablas_estados_registros ter ON ep.tabla_estado_registro_id = ter.tabla_estado_registro_id
            LEFT JOIN conf__colores col ON ter.color_id = col.color_id
            WHERE ep.empresa_id = $empresa_id
            ORDER BY ep.f_desde DESC, ep.empresa_parametro_id DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $row['estado_info'] = [
                'estado_registro' => $row['estado_registro'],
                'bg_clase' => $row['bg_clase']
            ];
            $data[] = $row;
        }
    }
    return $data;
}

function obtenerParametroEmpresaPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT ep.*, cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   reg.iibb_regimen_codigo, reg.iibb_regimen_nombre,
                   sed.codigo_comarb, sed.jurisdiccion_sede, sed.organismo as jurisdiccion_sede_organismo
            FROM gestion__empresas_parametros ep
            INNER JOIN gestion__condiciones_fiscales cf ON ep.condicion_fiscal_id = cf.condicion_fiscal_id
            LEFT JOIN gestion__iibb_regimenes reg ON ep.iibb_regimen_id = reg.iibb_regimen_id
            LEFT JOIN gestion__iibb_jurisdicciones_sedes sed ON ep.jurisdiccion_sede_id = sed.jurisdiccion_sede_id
            WHERE ep.empresa_parametro_id = $id";
    $res = mysqli_query($conexion, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $row['logo_data'] = logoEmpresaDataUri($row['logo_archivo'] ?? '');
        return $row;
    }
    return null;
}

function guardarParametrosEmpresa($conexion, $data, $usuario_id = null) {
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $condicion_fiscal_id = intval($data['condicion_fiscal_id'] ?? 0);
    $punto_venta_id = !empty($data['punto_venta_defecto_id']) ? intval($data['punto_venta_defecto_id']) : null;
    $lista_precio_id = !empty($data['lista_precio_defecto_id']) ? intval($data['lista_precio_defecto_id']) : null;
    $condicion_pago_id = !empty($data['condicion_pago_defecto_id']) ? intval($data['condicion_pago_defecto_id']) : null;
    $ingresos_brutos = !empty($data['ingresos_brutos_nro']) ? trim($data['ingresos_brutos_nro']) : null;
    $iibb_regimen_id = !empty($data['iibb_regimen_id']) ? intval($data['iibb_regimen_id']) : 1;
    $jurisdiccion_sede_id = !empty($data['jurisdiccion_sede_id']) ? intval($data['jurisdiccion_sede_id']) : 2;
    $inicio_act = !empty($data['inicio_actividades']) ? trim($data['inicio_actividades']) : null;
    $leyenda_fiscal = !empty($data['leyenda_fiscal']) ? trim($data['leyenda_fiscal']) : null;
    // 1 = las facturas fiscales se confirman recién con el CAE de ARCA (ventas_facturas)
    $arca_habilitado = !empty($data['arca_habilitado']) ? 1 : 0;
    $f_desde = !empty($data['f_desde']) ? trim($data['f_desde']) : null;
    $f_hasta = !empty($data['f_hasta']) ? trim($data['f_hasta']) : null;
    $observaciones = !empty($data['observaciones']) ? trim($data['observaciones']) : null;
    $cerrar_anterior = !empty($data['cerrar_anterior']) ? 1 : 0;
    $empresa_parametro_id = intval($data['empresa_parametro_id'] ?? 0);
    $user_id = $usuario_id ? intval($usuario_id) : 'NULL';
    // Logo: ruta del archivo ya subido (guardarArchivoLogoEmpresa) o quitar_logo = 1
    $logo_nuevo = !empty($data['logo_archivo']) ? $data['logo_archivo'] : null;
    $quitar_logo = !empty($data['quitar_logo']);
    $con_logo = empresasLogoColumnaExiste($conexion);

    if (empty($empresa_id)) {
        return ['resultado' => false, 'error' => 'Debe especificar la empresa.'];
    }
    if (empty($condicion_fiscal_id)) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una condición fiscal.'];
    }
    if (empty($f_desde)) {
        return ['resultado' => false, 'error' => 'La fecha de inicio de vigencia es obligatoria.'];
    }
    if (!empty($f_hasta) && $f_hasta < $f_desde) {
        return ['resultado' => false, 'error' => 'La fecha hasta no puede ser menor a la fecha desde.'];
    }

    mysqli_begin_transaction($conexion);

    try {
        if ($empresa_parametro_id > 0) {
            // Edición de un registro existente
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_parametros SET
                condicion_fiscal_id = ?,
                punto_venta_defecto_id = ?,
                lista_precio_defecto_id = ?,
                condicion_pago_defecto_id = ?,
                ingresos_brutos_nro = ?,
                iibb_regimen_id = ?,
                jurisdiccion_sede_id = ?,
                inicio_actividades = ?,
                leyenda_fiscal = ?,
                arca_habilitado = ?,
                f_desde = ?,
                f_hasta = ?,
                observaciones = ?
                WHERE empresa_parametro_id = ?");
            mysqli_stmt_bind_param($stmt, "iiiisiississsi", 
                $condicion_fiscal_id, 
                $punto_venta_id, 
                $lista_precio_id, 
                $condicion_pago_id, 
                $ingresos_brutos, 
                $iibb_regimen_id,
                $jurisdiccion_sede_id,
                $inicio_act, 
                $leyenda_fiscal, 
                $arca_habilitado, 
                $f_desde, 
                $f_hasta, 
                $observaciones, 
                $empresa_parametro_id
            );
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al actualizar parámetros: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            if ($con_logo && ($logo_nuevo !== null || $quitar_logo)) {
                $stmtLogo = mysqli_prepare($conexion, "UPDATE gestion__empresas_parametros SET logo_archivo = ? WHERE empresa_parametro_id = ? AND empresa_id = ?");
                mysqli_stmt_bind_param($stmtLogo, "sii", $logo_nuevo, $empresa_parametro_id, $empresa_id);
                if (!mysqli_stmt_execute($stmtLogo)) {
                    throw new Exception("Error al actualizar el logo: " . mysqli_stmt_error($stmtLogo));
                }
                mysqli_stmt_close($stmtLogo);
            }

            guardarOpcionesComprasEmpresa($conexion, $empresa_parametro_id, $data);

            // Si este registro es o quedó como vigente (f_hasta IS NULL), sincronizar conf__empresas
            if (empty($f_hasta)) {
                $syncStmt = mysqli_prepare($conexion, "UPDATE conf__empresas SET condicion_fiscal_id = ?, iibb_regimen_id = ?, jurisdiccion_sede_id = ? WHERE empresa_id = ?");
                mysqli_stmt_bind_param($syncStmt, "iiii", $condicion_fiscal_id, $iibb_regimen_id, $jurisdiccion_sede_id, $empresa_id);
                mysqli_stmt_execute($syncStmt);
                mysqli_stmt_close($syncStmt);
            }

            mysqli_commit($conexion);
            return ['resultado' => true, 'mensaje' => 'Parámetros actualizados correctamente.', 'empresa_parametro_id' => $empresa_parametro_id];
        } else {
            // Alta de nueva vigencia de parámetros: si no se sube otro logo, hereda el último
            if ($con_logo && $logo_nuevo === null && !$quitar_logo) {
                $logo_nuevo = obtenerUltimoLogoEmpresa($conexion, $empresa_id);
            }
            if ($cerrar_anterior == 1 && empty($f_hasta)) {
                // Cerrar cualquier parámetro previo que estuviera abierto (f_hasta IS NULL)
                $fechaCierre = date('Y-m-d', strtotime($f_desde . ' -1 day'));
                $stmtCerrar = mysqli_prepare($conexion, "UPDATE gestion__empresas_parametros 
                    SET f_hasta = ? 
                    WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1");
                mysqli_stmt_bind_param($stmtCerrar, "si", $fechaCierre, $empresa_id);
                mysqli_stmt_execute($stmtCerrar);
                mysqli_stmt_close($stmtCerrar);
            }

            $stmtIns = mysqli_prepare($conexion, "INSERT INTO gestion__empresas_parametros 
                (empresa_id, condicion_fiscal_id, punto_venta_defecto_id, lista_precio_defecto_id, condicion_pago_defecto_id, ingresos_brutos_nro, iibb_regimen_id, jurisdiccion_sede_id, inicio_actividades, leyenda_fiscal, arca_habilitado, f_desde, f_hasta, observaciones, usuario_id, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            mysqli_stmt_bind_param($stmtIns, "iiiiisiississsi", 
                $empresa_id, 
                $condicion_fiscal_id, 
                $punto_venta_id, 
                $lista_precio_id, 
                $condicion_pago_id, 
                $ingresos_brutos, 
                $iibb_regimen_id,
                $jurisdiccion_sede_id,
                $inicio_act, 
                $leyenda_fiscal, 
                $arca_habilitado, 
                $f_desde, 
                $f_hasta, 
                $observaciones, 
                $usuario_id
            );
            if (!mysqli_stmt_execute($stmtIns)) {
                throw new Exception("Error al insertar parámetros: " . mysqli_stmt_error($stmtIns));
            }
            $nuevoId = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtIns);

            if ($con_logo && $logo_nuevo !== null) {
                $stmtLogo = mysqli_prepare($conexion, "UPDATE gestion__empresas_parametros SET logo_archivo = ? WHERE empresa_parametro_id = ?");
                mysqli_stmt_bind_param($stmtLogo, "si", $logo_nuevo, $nuevoId);
                if (!mysqli_stmt_execute($stmtLogo)) {
                    throw new Exception("Error al guardar el logo: " . mysqli_stmt_error($stmtLogo));
                }
                mysqli_stmt_close($stmtLogo);
            }

            guardarOpcionesComprasEmpresa($conexion, $nuevoId, $data);

            // Sincronizar conf__empresas SOLO si la nueva vigencia es la activa (f_hasta IS NULL)
            if (empty($f_hasta)) {
                $syncStmt = mysqli_prepare($conexion, "UPDATE conf__empresas SET condicion_fiscal_id = ?, iibb_regimen_id = ?, jurisdiccion_sede_id = ? WHERE empresa_id = ?");
                mysqli_stmt_bind_param($syncStmt, "iiii", $condicion_fiscal_id, $iibb_regimen_id, $jurisdiccion_sede_id, $empresa_id);
                mysqli_stmt_execute($syncStmt);
                mysqli_stmt_close($syncStmt);
            }

            mysqli_commit($conexion);
            return ['resultado' => true, 'mensaje' => 'Nueva vigencia de parámetros registrada con éxito.', 'empresa_parametro_id' => $nuevoId];
        }
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function cambiarEstadoParametroEmpresa($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);

    $param = obtenerParametroEmpresaPorId($conexion, $id);
    if (!$param) {
        return ['resultado' => false, 'error' => 'Registro no encontrado.'];
    }

    $sql = "UPDATE gestion__empresas_parametros SET tabla_estado_registro_id = $nuevo_estado WHERE empresa_parametro_id = $id";
    if (mysqli_query($conexion, $sql)) {
        // Si se inhabilitó el registro vigente, o si se habilitó uno vigente, recalcular conf__empresas
        if (empty($param['f_hasta'])) {
            $vigente = obtenerParametrosVigentesEmpresa($conexion, $param['empresa_id']);
            $cfId = $vigente ? intval($vigente['condicion_fiscal_id']) : 'NULL';
            $regId = ($vigente && !empty($vigente['iibb_regimen_id'])) ? intval($vigente['iibb_regimen_id']) : 'NULL';
            $sedeId = ($vigente && !empty($vigente['jurisdiccion_sede_id'])) ? intval($vigente['jurisdiccion_sede_id']) : 'NULL';
            mysqli_query($conexion, "UPDATE conf__empresas SET condicion_fiscal_id = $cfId, iibb_regimen_id = $regId, jurisdiccion_sede_id = $sedeId WHERE empresa_id = {$param['empresa_id']}");
        }
        return ['resultado' => true, 'mensaje' => 'Estado actualizado correctamente.'];
    }
    return ['resultado' => false, 'error' => 'Error al actualizar estado en la base de datos.'];
}

// ---------- Configuración de impresión (gestion__empresas_impresion, ver core/impresion.php) ----------
// Una fila por empresa y subgrupo de comprobante (0 = general). No se versiona por vigencia.
require_once __DIR__ . '/../../core/impresion.php';

function obtenerSubgruposImpresion($conexion, $empresa_id) {
    $stmt = mysqli_prepare($conexion, "SELECT s.comprobante_subgrupo_id, s.comprobante_subgrupo, g.comprobante_grupo
                                       FROM gestion__comprobantes_subgrupos s
                                       LEFT JOIN gestion__comprobantes_grupos g ON g.comprobante_grupo_id = s.comprobante_grupo_id
                                       WHERE s.empresa_id IN (0, ?) AND s.tabla_estado_registro_id = 1
                                       ORDER BY g.comprobante_grupo, s.orden, s.comprobante_subgrupo");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

function listarConfiguracionesImpresion($conexion, $empresa_id) {
    if (!impresion_tabla_existe($conexion)) return [];
    $stmt = mysqli_prepare($conexion, "SELECT ei.*, s.comprobante_subgrupo
                                       FROM gestion__empresas_impresion ei
                                       LEFT JOIN gestion__comprobantes_subgrupos s ON s.comprobante_subgrupo_id = ei.comprobante_subgrupo_id
                                       WHERE ei.empresa_id = ?
                                       ORDER BY ei.comprobante_subgrupo_id");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['formato_nombre'] = IMPRESION_FORMATOS[$row['formato']] ?? $row['formato'];
        $row['fuente'] = impresion_normalizar($row)['fuente'];
        $row['fuente_nombre'] = IMPRESION_FUENTES[$row['fuente']][0];
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

function guardarConfiguracionImpresion($conexion, $empresa_id, $data, $usuario_id = null) {
    if (!impresion_tabla_existe($conexion)) {
        return ['resultado' => false, 'error' => 'Falta la tabla gestion__empresas_impresion (script sql/mantenimiento/20261010_empresas_impresion_01_tabla.sql).'];
    }
    $empresa_id = intval($empresa_id);
    $cfg = impresion_normalizar($data);
    $id = $cfg['empresa_impresion_id'];
    $subgrupo_id = $cfg['comprobante_subgrupo_id'];
    $color = $cfg['color_acento'] !== '' ? $cfg['color_acento'] : null;
    $pie = $cfg['texto_pie'] !== '' ? $cfg['texto_pie'] : null;
    $uid = $usuario_id ? intval($usuario_id) : null;

    if ($subgrupo_id > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT 1 FROM gestion__comprobantes_subgrupos WHERE comprobante_subgrupo_id = ? AND empresa_id IN (0, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $subgrupo_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$ok) return ['resultado' => false, 'error' => 'El tipo de comprobante elegido no es de esta empresa.'];
    }

    // Una sola configuración por empresa + subgrupo (uk_empresa_impresion)
    $stmt = mysqli_prepare($conexion, "SELECT empresa_impresion_id FROM gestion__empresas_impresion
                                       WHERE empresa_id = ? AND comprobante_subgrupo_id = ? AND empresa_impresion_id <> ?");
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $subgrupo_id, $id);
    mysqli_stmt_execute($stmt);
    $dup = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($dup) {
        return ['resultado' => false, 'error' => $subgrupo_id > 0
            ? 'Ya hay una configuración para ese tipo de comprobante: edítela.'
            : 'Ya hay una configuración general para la empresa: edítela.'];
    }

    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_impresion SET
                comprobante_subgrupo_id = ?, formato = ?, copias_defecto = ?, mostrar_logo = ?, mostrar_codigo = ?,
                mostrar_bonificacion = ?, mostrar_sucursal_cliente = ?, texto_pie = ?, color_acento = ?, usuario_id = ?
            WHERE empresa_impresion_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "isiiiiissiii", $subgrupo_id, $cfg['formato'], $cfg['copias_defecto'], $cfg['mostrar_logo'],
            $cfg['mostrar_codigo'], $cfg['mostrar_bonificacion'], $cfg['mostrar_sucursal_cliente'], $pie, $color, $uid, $id, $empresa_id);
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__empresas_impresion
                (empresa_id, comprobante_subgrupo_id, formato, copias_defecto, mostrar_logo, mostrar_codigo,
                 mostrar_bonificacion, mostrar_sucursal_cliente, texto_pie, color_acento, usuario_id, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "iisiiiiissi", $empresa_id, $subgrupo_id, $cfg['formato'], $cfg['copias_defecto'], $cfg['mostrar_logo'],
            $cfg['mostrar_codigo'], $cfg['mostrar_bonificacion'], $cfg['mostrar_sucursal_cliente'], $pie, $color, $uid);
    }
    if (!mysqli_stmt_execute($stmt)) {
        error_log("=== guardarConfiguracionImpresion empresa $empresa_id: " . mysqli_stmt_error($stmt) . " ===");
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'No se pudo guardar la configuración de impresión.'];
    }
    if ($id === 0) $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    // Tipografía (script 20261010_empresas_impresion_02_fuente.sql): solo si la columna ya existe
    $res_col = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_impresion LIKE 'fuente'");
    if ($res_col && mysqli_num_rows($res_col) > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_impresion SET fuente = ? WHERE empresa_impresion_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "sii", $cfg['fuente'], $id, $empresa_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    return ['resultado' => true, 'mensaje' => 'Configuración de impresión guardada.', 'empresa_impresion_id' => $id];
}

function cambiarEstadoConfiguracionImpresion($conexion, $empresa_id, $id, $nuevo_estado) {
    $nuevo_estado = intval($nuevo_estado) === 1 ? 1 : 2;
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_impresion SET tabla_estado_registro_id = ? WHERE empresa_impresion_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) >= 0;
    mysqli_stmt_close($stmt);
    return $ok ? ['resultado' => true, 'mensaje' => 'Estado actualizado.'] : ['resultado' => false, 'error' => 'No se pudo cambiar el estado.'];
}
