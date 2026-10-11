<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerLocalidades($conexion)
{
    $sql = "SELECT localidad_id, localidad FROM conf__localidades ORDER BY localidad";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
    }
    return $data;
}

function obtenerDocumentosTipos($conexion)
{
    $sql = "SELECT documento_tipo_id, documento_tipo 
            FROM conf__documento_tipos 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY documento_tipo_id ASC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
    }
    return $data;
}

function obtenerempresas($conexion)
{
    $sql = "SELECT e.*, l.localidad, dt.documento_tipo,
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   reg.iibb_regimen_codigo, reg.iibb_regimen_nombre, reg.descripcion as iibb_regimen_descripcion,
                   sed.codigo_comarb, sed.jurisdiccion_sede, sed.organismo as jurisdiccion_sede_organismo,
                   ep.ingresos_brutos_nro, ep.inicio_actividades, ep.leyenda_fiscal, ep.f_desde as parametro_f_desde, ep.empresa_parametro_id,
                   COALESCE(ep.iibb_regimen_id, e.iibb_regimen_id, 1) as iibb_regimen_id,
                   COALESCE(ep.jurisdiccion_sede_id, e.jurisdiccion_sede_id, 2) as jurisdiccion_sede_id,
                   ter.tabla_estado_registro, col.bg_clase, col.text_clase
            FROM conf__empresas e
            LEFT JOIN conf__localidades l ON e.localidad_id = l.localidad_id
            LEFT JOIN conf__documento_tipos dt ON e.documento_tipo_id = dt.documento_tipo_id
            LEFT JOIN gestion__condiciones_fiscales cf ON e.condicion_fiscal_id = cf.condicion_fiscal_id
            LEFT JOIN gestion__empresas_parametros ep ON ep.empresa_parametro_id = (
                SELECT ep2.empresa_parametro_id 
                FROM gestion__empresas_parametros ep2 
                WHERE ep2.empresa_id = e.empresa_id AND ep2.f_hasta IS NULL AND ep2.tabla_estado_registro_id = 1 
                ORDER BY ep2.f_desde DESC, ep2.empresa_parametro_id DESC LIMIT 1
            )
            LEFT JOIN gestion__iibb_regimenes reg ON COALESCE(ep.iibb_regimen_id, e.iibb_regimen_id, 1) = reg.iibb_regimen_id
            LEFT JOIN gestion__iibb_jurisdicciones_sedes sed ON COALESCE(ep.jurisdiccion_sede_id, e.jurisdiccion_sede_id, 2) = sed.jurisdiccion_sede_id
            LEFT JOIN conf__tablas_estados_registros ter ON e.tabla_estado_registro_id = ter.tabla_estado_registro_id
            LEFT JOIN conf__colores col ON ter.color_id = col.color_id
            ORDER BY e.empresa_id DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
    }
    return $data;
}

function obtenerCondicionesFiscales($conexion)
{
    $sql = "SELECT condicion_fiscal_id, condicion_fiscal, condicion_fiscal_codigo
            FROM gestion__condiciones_fiscales
            WHERE tabla_estado_registro_id = 1
            ORDER BY condicion_fiscal ASC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
    }
    return $data;
}

function obtenerIibbRegimenes($conexion)
{
    $sql = "SELECT iibb_regimen_id, iibb_regimen_codigo, iibb_regimen_nombre, descripcion
            FROM gestion__iibb_regimenes
            WHERE tabla_estado_registro_id = 1
            ORDER BY orden ASC, iibb_regimen_id ASC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
    }
    return $data;
}

function obtenerJurisdiccionesSedes($conexion)
{
    $sql = "SELECT jurisdiccion_sede_id, codigo_comarb, jurisdiccion_sede, organismo, provincia_id
            FROM gestion__iibb_jurisdicciones_sedes
            WHERE tabla_estado_registro_id = 1
            ORDER BY CAST(codigo_comarb AS UNSIGNED), codigo_comarb ASC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
    }
    return $data;
}

function agregarempresa($conexion, $data, $usuario_id = null)
{
    // Validar campos obligatorios
    if (empty($data['empresa']) || empty($data['documento_numero']) || empty($data['localidad_id'])) {
        return false;
    }
    $empresa = mysqli_real_escape_string($conexion, trim($data['empresa']));
    $documento_tipo_id = intval($data['documento_tipo_id'] ?? 1);
    $documento_numero = mysqli_real_escape_string($conexion, trim($data['documento_numero']));
    $telefono = mysqli_real_escape_string($conexion, trim($data['telefono'] ?? ''));
    $domicilio = mysqli_real_escape_string($conexion, trim($data['domicilio'] ?? ''));
    $localidad_id = is_numeric($data['localidad_id']) ? intval($data['localidad_id']) : 'NULL';
    $condicion_fiscal_id = (!empty($data['condicion_fiscal_id']) && is_numeric($data['condicion_fiscal_id'])) ? intval($data['condicion_fiscal_id']) : 'NULL';
    $iibb_regimen_id = (!empty($data['iibb_regimen_id']) && is_numeric($data['iibb_regimen_id'])) ? intval($data['iibb_regimen_id']) : 1;
    $jurisdiccion_sede_id = (!empty($data['jurisdiccion_sede_id']) && is_numeric($data['jurisdiccion_sede_id'])) ? intval($data['jurisdiccion_sede_id']) : 2;
    $email = mysqli_real_escape_string($conexion, trim($data['email'] ?? ''));
    $base_conf = is_numeric($data['base_conf'] ?? null) ? $data['base_conf'] : 'NULL';
    $estado_registro_id = intval($data['tabla_estado_registro_id'] ?? ($data['estado_registro_id'] ?? 1));

    $sql = "INSERT INTO conf__empresas (empresa, documento_tipo_id, documento_numero, telefono, domicilio, localidad_id, condicion_fiscal_id, iibb_regimen_id, jurisdiccion_sede_id, email, base_conf, tabla_estado_registro_id) 
            VALUES ('$empresa', '$documento_tipo_id', '$documento_numero', '$telefono', '$domicilio', $localidad_id, $condicion_fiscal_id, $iibb_regimen_id, $jurisdiccion_sede_id, '$email', $base_conf, $estado_registro_id)";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return false;
    }

    $nueva_empresa_id = mysqli_insert_id($conexion);

    // Si se asignó condición fiscal o datos fiscales, registrar en gestion__empresas_parametros
    if ($condicion_fiscal_id !== 'NULL' && $nueva_empresa_id > 0) {
        $user_id_val = !empty($usuario_id) ? intval($usuario_id) : 'NULL';
        
        $leyenda = '';
        $cfRes = mysqli_query($conexion, "SELECT condicion_fiscal FROM gestion__condiciones_fiscales WHERE condicion_fiscal_id = $condicion_fiscal_id");
        if ($cfRes && $cfRow = mysqli_fetch_assoc($cfRes)) {
            $leyenda = 'IVA ' . $cfRow['condicion_fiscal'];
        }

        $ib_val = !empty($data['ingresos_brutos_nro']) ? mysqli_real_escape_string($conexion, trim($data['ingresos_brutos_nro'])) : $documento_numero;
        $ia_val = !empty($data['inicio_actividades']) ? "'" . mysqli_real_escape_string($conexion, trim($data['inicio_actividades'])) . "'" : 'NULL';
        $lf_val = !empty($data['leyenda_fiscal']) ? mysqli_real_escape_string($conexion, trim($data['leyenda_fiscal'])) : mysqli_real_escape_string($conexion, $leyenda);

        $sqlParam = "INSERT INTO gestion__empresas_parametros 
            (empresa_id, condicion_fiscal_id, ingresos_brutos_nro, iibb_regimen_id, jurisdiccion_sede_id, inicio_actividades, leyenda_fiscal, f_desde, f_hasta, observaciones, usuario_id, tabla_estado_registro_id, created_at)
            VALUES ($nueva_empresa_id, $condicion_fiscal_id, '$ib_val', $iibb_regimen_id, $jurisdiccion_sede_id, $ia_val, '$lf_val', CURDATE(), NULL, 'Parámetros iniciales generados desde ABM Empresas', $user_id_val, 1, NOW())";
        mysqli_query($conexion, $sqlParam);
    }

    return true;
}

function editarempresa($conexion, $id, $data, $usuario_id = null)
{
    // Validar campos obligatorios
    if (empty($data['empresa']) || empty($data['documento_numero']) || empty($data['localidad_id'])) {
        return false;
    }
    $id = intval($id);

    // Obtener datos previos para detectar cambios en la condición fiscal
    $empresaPrevia = obtenerempresaPorId($conexion, $id);
    $cfAnterior = !empty($empresaPrevia['condicion_fiscal_id']) ? intval($empresaPrevia['condicion_fiscal_id']) : null;
    $cfNueva = (!empty($data['condicion_fiscal_id']) && is_numeric($data['condicion_fiscal_id'])) ? intval($data['condicion_fiscal_id']) : null;

    $empresa = mysqli_real_escape_string($conexion, trim($data['empresa']));
    $documento_tipo_id = intval($data['documento_tipo_id'] ?? 1);
    $documento_numero = mysqli_real_escape_string($conexion, trim($data['documento_numero']));
    $telefono = mysqli_real_escape_string($conexion, trim($data['telefono'] ?? ''));
    $domicilio = mysqli_real_escape_string($conexion, trim($data['domicilio'] ?? ''));
    $localidad_id = is_numeric($data['localidad_id']) ? intval($data['localidad_id']) : 'NULL';
    $condicion_sql = ($cfNueva !== null) ? $cfNueva : 'NULL';
    $iibb_regimen_id = (!empty($data['iibb_regimen_id']) && is_numeric($data['iibb_regimen_id'])) ? intval($data['iibb_regimen_id']) : 1;
    $jurisdiccion_sede_id = (!empty($data['jurisdiccion_sede_id']) && is_numeric($data['jurisdiccion_sede_id'])) ? intval($data['jurisdiccion_sede_id']) : 2;
    $email = mysqli_real_escape_string($conexion, trim($data['email'] ?? ''));
    $base_conf = is_numeric($data['base_conf'] ?? null) ? $data['base_conf'] : 'NULL';
    $estado_registro_id = intval($data['tabla_estado_registro_id'] ?? ($data['estado_registro_id'] ?? 1));

    $sql = "UPDATE conf__empresas SET
        empresa='$empresa',
        documento_tipo_id='$documento_tipo_id',
        documento_numero='$documento_numero',
        telefono='$telefono',
        domicilio='$domicilio',
        localidad_id=$localidad_id,
        condicion_fiscal_id=$condicion_sql,
        iibb_regimen_id=$iibb_regimen_id,
        jurisdiccion_sede_id=$jurisdiccion_sede_id,
        email='$email',
        base_conf=$base_conf,
        tabla_estado_registro_id=$estado_registro_id
        WHERE empresa_id=$id";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return false;
    }

    $user_id_val = !empty($usuario_id) ? intval($usuario_id) : 'NULL';
    $hoy = date('Y-m-d');
    $fechaCierre = date('Y-m-d', strtotime('-1 day'));

    // Buscar si existe un parámetro vigente actualmente
    $sqlVigente = "SELECT * FROM gestion__empresas_parametros 
                   WHERE empresa_id = $id AND f_hasta IS NULL AND tabla_estado_registro_id = 1 
                   ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1";
    $resVig = mysqli_query($conexion, $sqlVigente);
    $paramVigente = ($resVig && mysqli_num_rows($resVig) > 0) ? mysqli_fetch_assoc($resVig) : null;

    $ib_input = isset($data['ingresos_brutos_nro']) ? mysqli_real_escape_string($conexion, trim($data['ingresos_brutos_nro'])) : null;
    $ia_input = isset($data['inicio_actividades']) && !empty($data['inicio_actividades']) ? "'" . mysqli_real_escape_string($conexion, trim($data['inicio_actividades'])) . "'" : (isset($data['inicio_actividades']) ? 'NULL' : null);
    $lf_input = isset($data['leyenda_fiscal']) ? mysqli_real_escape_string($conexion, trim($data['leyenda_fiscal'])) : null;

    // CASO 1: Hubo cambio en la condición fiscal
    if ($cfNueva !== $cfAnterior) {
        if ($paramVigente) {
            // Cerrar el parámetro anterior
            $cierreReal = ($paramVigente['f_desde'] >= $hoy) ? $hoy : $fechaCierre;
            $sqlCerrar = "UPDATE gestion__empresas_parametros 
                          SET f_hasta = '$cierreReal', updated_at = NOW() 
                          WHERE empresa_parametro_id = {$paramVigente['empresa_parametro_id']}";
            mysqli_query($conexion, $sqlCerrar);

            // Heredar configuración previa si no se proporcionó una nueva
            $pv = !empty($paramVigente['punto_venta_defecto_id']) ? intval($paramVigente['punto_venta_defecto_id']) : 'NULL';
            $lp = !empty($paramVigente['lista_precio_defecto_id']) ? intval($paramVigente['lista_precio_defecto_id']) : 'NULL';
            $cp = !empty($paramVigente['condicion_pago_defecto_id']) ? intval($paramVigente['condicion_pago_defecto_id']) : 'NULL';
            $ib = ($ib_input !== null && $ib_input !== '') ? "'$ib_input'" : (!empty($paramVigente['ingresos_brutos_nro']) ? "'" . mysqli_real_escape_string($conexion, $paramVigente['ingresos_brutos_nro']) . "'" : (!empty($documento_numero) ? "'$documento_numero'" : 'NULL'));
            $inicioAct = ($ia_input !== null) ? $ia_input : (!empty($paramVigente['inicio_actividades']) ? "'" . mysqli_real_escape_string($conexion, $paramVigente['inicio_actividades']) . "'" : 'NULL');
        } else {
            $pv = 'NULL';
            $lp = 'NULL';
            $cp = 'NULL';
            $ib = ($ib_input !== null && $ib_input !== '') ? "'$ib_input'" : (!empty($documento_numero) ? "'$documento_numero'" : 'NULL');
            $inicioAct = ($ia_input !== null) ? $ia_input : 'NULL';
        }

        // Si la nueva condición fiscal no es nula, crear nuevo registro vigente
        if ($cfNueva !== null) {
            $leyenda = '';
            $cfRes = mysqli_query($conexion, "SELECT condicion_fiscal FROM gestion__condiciones_fiscales WHERE condicion_fiscal_id = $cfNueva");
            if ($cfRes && $cfRow = mysqli_fetch_assoc($cfRes)) {
                $leyenda = 'IVA ' . $cfRow['condicion_fiscal'];
            }
            $leyendaFinal = ($lf_input !== null && $lf_input !== '') ? $lf_input : mysqli_real_escape_string($conexion, $leyenda);

            $sqlNuevo = "INSERT INTO gestion__empresas_parametros 
                (empresa_id, condicion_fiscal_id, punto_venta_defecto_id, lista_precio_defecto_id, condicion_pago_defecto_id, 
                 ingresos_brutos_nro, iibb_regimen_id, jurisdiccion_sede_id, inicio_actividades, leyenda_fiscal, f_desde, f_hasta, observaciones, usuario_id, tabla_estado_registro_id, created_at)
                VALUES 
                ($id, $cfNueva, $pv, $lp, $cp, $ib, $iibb_regimen_id, $jurisdiccion_sede_id, $inicioAct, '$leyendaFinal', '$hoy', NULL, 
                 'Cambio de condición fiscal registrado desde ABM Empresas', $user_id_val, 1, NOW())";
            mysqli_query($conexion, $sqlNuevo);
        }
    } 
    // CASO 2: La condición fiscal no cambió, pero se editaron campos fiscales (IIBB, Inicio actividades, Leyenda)
    else {
        if ($paramVigente) {
            $updates = [];
            if ($ib_input !== null) {
                $updates[] = "ingresos_brutos_nro = " . ($ib_input !== '' ? "'$ib_input'" : 'NULL');
            }
            $updates[] = "iibb_regimen_id = $iibb_regimen_id";
            $updates[] = "jurisdiccion_sede_id = $jurisdiccion_sede_id";
            if ($ia_input !== null) {
                $updates[] = "inicio_actividades = $ia_input";
            }
            if ($lf_input !== null) {
                $updates[] = "leyenda_fiscal = " . ($lf_input !== '' ? "'$lf_input'" : 'NULL');
            }
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $sqlUpd = "UPDATE gestion__empresas_parametros SET " . implode(', ', $updates) . " WHERE empresa_parametro_id = {$paramVigente['empresa_parametro_id']}";
                mysqli_query($conexion, $sqlUpd);
            }
        } elseif ($cfNueva !== null) {
            // No existía registro de parámetros pero la empresa tiene condición fiscal
            $ib = ($ib_input !== null && $ib_input !== '') ? "'$ib_input'" : (!empty($documento_numero) ? "'$documento_numero'" : 'NULL');
            $ia = ($ia_input !== null) ? $ia_input : 'NULL';
            $lf = ($lf_input !== null && $lf_input !== '') ? "'$lf_input'" : 'NULL';
            $sqlNuevo = "INSERT INTO gestion__empresas_parametros 
                (empresa_id, condicion_fiscal_id, ingresos_brutos_nro, iibb_regimen_id, jurisdiccion_sede_id, inicio_actividades, leyenda_fiscal, f_desde, f_hasta, observaciones, usuario_id, tabla_estado_registro_id, created_at)
                VALUES ($id, $cfNueva, $ib, $iibb_regimen_id, $jurisdiccion_sede_id, $ia, $lf, '$hoy', NULL, 'Parámetros iniciales generados desde ABM Empresas', $user_id_val, 1, NOW())";
            mysqli_query($conexion, $sqlNuevo);
        }
    }

    return true;
}

function eliminarempresa($conexion, $id)
{
    $id = intval($id);
    $sql = "DELETE FROM conf__empresas WHERE empresa_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerempresaPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT e.*, l.localidad, dt.documento_tipo,
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   reg.iibb_regimen_codigo, reg.iibb_regimen_nombre, reg.descripcion as iibb_regimen_descripcion,
                   sed.codigo_comarb, sed.jurisdiccion_sede, sed.organismo as jurisdiccion_sede_organismo,
                   ep.ingresos_brutos_nro, ep.inicio_actividades, ep.leyenda_fiscal, ep.f_desde as parametro_f_desde, ep.empresa_parametro_id,
                   COALESCE(ep.iibb_regimen_id, e.iibb_regimen_id, 1) as iibb_regimen_id,
                   COALESCE(ep.jurisdiccion_sede_id, e.jurisdiccion_sede_id, 2) as jurisdiccion_sede_id
            FROM conf__empresas e
            LEFT JOIN conf__localidades l ON e.localidad_id = l.localidad_id
            LEFT JOIN conf__documento_tipos dt ON e.documento_tipo_id = dt.documento_tipo_id
            LEFT JOIN gestion__condiciones_fiscales cf ON e.condicion_fiscal_id = cf.condicion_fiscal_id
            LEFT JOIN gestion__empresas_parametros ep ON ep.empresa_parametro_id = (
                SELECT ep2.empresa_parametro_id 
                FROM gestion__empresas_parametros ep2 
                WHERE ep2.empresa_id = e.empresa_id AND ep2.f_hasta IS NULL AND ep2.tabla_estado_registro_id = 1 
                ORDER BY ep2.f_desde DESC, ep2.empresa_parametro_id DESC LIMIT 1
            )
            LEFT JOIN gestion__iibb_regimenes reg ON COALESCE(ep.iibb_regimen_id, e.iibb_regimen_id, 1) = reg.iibb_regimen_id
            LEFT JOIN gestion__iibb_jurisdicciones_sedes sed ON COALESCE(ep.jurisdiccion_sede_id, e.jurisdiccion_sede_id, 2) = sed.jurisdiccion_sede_id
            WHERE e.empresa_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
