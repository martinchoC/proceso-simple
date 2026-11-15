<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/perfiles_predefinidos.php';
require_once __DIR__ . '/../../core/auditoria.php';
require_once __DIR__ . '/../../core/empresa_logo.php';
require_once __DIR__ . '/../../core/ia_empresa.php';
$conexion = $conn;

/** Clave de la IA tal como la escribió el usuario: vacía (= se conserva) o sin espacios y hasta 300 caracteres. */
function conf_ia_clave_valida(string $clave): bool
{
    return $clave === '' || (strlen($clave) <= 300 && !preg_match('/\s/', $clave));
}

function conf_empresa_columna_existe(mysqli $conexion, string $columna): bool
{
    static $cols = null;
    if ($cols === null) {
        $cols = [];
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM conf__empresas");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $cols[$row['Field']] = true;
            }
        }
    }
    return isset($cols[$columna]);
}

function param_empresa_columna_existe(mysqli $conexion, string $columna): bool
{
    static $colsParam = null;
    if ($colsParam === null) {
        $colsParam = [];
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $colsParam[$row['Field']] = true;
            }
        }
    }
    return isset($colsParam[$columna]);
}

function obtenerProvincias($conexion)
{
    $sql = "SELECT provincia_id, pais_id, provincia 
            FROM conf__provincias 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY provincia ASC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
    }
    return $data;
}

function obtenerLocalidades($conexion, $provincia_id = 0)
{
    $provincia_id = intval($provincia_id);
    if ($provincia_id <= 0) {
        return [];
    }

    $sql = "SELECT l.localidad_id, l.localidad, l.cp, l.provincia_id
            FROM conf__localidades l
            WHERE l.provincia_id = ?
            AND l.tabla_estado_registro_id IN (1, 2)
            ORDER BY l.localidad ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, "i", $provincia_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
    }
    mysqli_stmt_close($stmt);
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
    $sql = "SELECT e.*, l.localidad, l.cp, l.provincia_id, p.provincia, dt.documento_tipo,
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   reg.iibb_regimen_codigo, reg.iibb_regimen_nombre, reg.descripcion as iibb_regimen_descripcion,
                   sed.codigo_comarb, sed.jurisdiccion_sede, sed.organismo as jurisdiccion_sede_organismo,
                   ep.ingresos_brutos_nro, ep.inicio_actividades, ep.leyenda_fiscal, ep.f_desde as parametro_f_desde, ep.empresa_parametro_id,
                   COALESCE(ep.iibb_regimen_id, e.iibb_regimen_id, 1) as iibb_regimen_id,
                   COALESCE(ep.jurisdiccion_sede_id, e.jurisdiccion_sede_id, 2) as jurisdiccion_sede_id,
                   ter.tabla_estado_registro, col.bg_clase, col.text_clase
            FROM conf__empresas e
            LEFT JOIN conf__localidades l ON e.localidad_id = l.localidad_id
            LEFT JOIN conf__provincias p ON l.provincia_id = p.provincia_id
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

function agregarempresa($conexion, $data, $usuario_id = null, &$nueva_empresa_id = 0)
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
    // BIENES / SERVICIOS / MIXTA (lista blanca de core/perfiles_predefinidos.php)
    $tipo_operacion = strtoupper(trim((string) ($data['tipo_operacion'] ?? '')));
    if (!isset(TIPOS_OPERACION[$tipo_operacion])) {
        $tipo_operacion = 'MIXTA';
    }
    $nivel_gestion = (isset($data['nivel_gestion']) && $data['nivel_gestion'] === 'SIMPLIFICADA') ? 'SIMPLIFICADA' : 'COMPLETA';
    $formato_fecha = !empty($data['formato_fecha']) ? "'" . mysqli_real_escape_string($conexion, trim($data['formato_fecha'])) . "'" : "'d/m/Y'";
    $separador_miles = !empty($data['separador_miles']) ? "'" . mysqli_real_escape_string($conexion, trim($data['separador_miles'])) . "'" : "'PUNTO'";
    $separador_decimal = !empty($data['separador_decimal']) ? "'" . mysqli_real_escape_string($conexion, trim($data['separador_decimal'])) . "'" : "'COMA'";
    $decimales_stock = is_numeric($data['decimales_stock'] ?? null) ? intval($data['decimales_stock']) : 2;
    $usa_presentaciones = !empty($data['usa_presentaciones']) ? 1 : 0;
    // Vender por presentación exige usar presentaciones
    $usa_venta_presentaciones = ($usa_presentaciones === 1 && !empty($data['usa_venta_presentaciones'])) ? 1 : 0;
    $usa_canales_venta = !empty($data['usa_canales_venta']) ? 1 : 0;
    $es_principal = !empty($data['es_principal']) ? 1 : 0;

    $cols = ['empresa', 'documento_tipo_id', 'documento_numero', 'telefono', 'domicilio', 'localidad_id', 'condicion_fiscal_id', 'email', 'tabla_estado_registro_id'];
    $vals = ["'$empresa'", "$documento_tipo_id", "'$documento_numero'", "'$telefono'", "'$domicilio'", "$localidad_id", "$condicion_fiscal_id", "'$email'", "$estado_registro_id"];

    if (conf_empresa_columna_existe($conexion, 'es_principal')) {
        $cols[] = 'es_principal';
        $vals[] = "$es_principal";
    }

    if (conf_empresa_columna_existe($conexion, 'iibb_regimen_id')) {
        $cols[] = 'iibb_regimen_id';
        $vals[] = "$iibb_regimen_id";
    }
    if (conf_empresa_columna_existe($conexion, 'jurisdiccion_sede_id')) {
        $cols[] = 'jurisdiccion_sede_id';
        $vals[] = "$jurisdiccion_sede_id";
    }
    if (conf_empresa_columna_existe($conexion, 'base_conf')) {
        $cols[] = 'base_conf';
        $vals[] = "$base_conf";
    }
    if (conf_empresa_columna_existe($conexion, 'tipo_operacion')) {
        $cols[] = 'tipo_operacion';
        $vals[] = "'$tipo_operacion'";
    }
    if (conf_empresa_columna_existe($conexion, 'nivel_gestion')) {
        $cols[] = 'nivel_gestion';
        $vals[] = "'$nivel_gestion'";
    }
    if (conf_empresa_columna_existe($conexion, 'formato_fecha')) {
        $cols[] = 'formato_fecha';
        $vals[] = "$formato_fecha";
    }
    if (conf_empresa_columna_existe($conexion, 'separador_miles')) {
        $cols[] = 'separador_miles';
        $vals[] = "$separador_miles";
    }
    if (conf_empresa_columna_existe($conexion, 'separador_decimal')) {
        $cols[] = 'separador_decimal';
        $vals[] = "$separador_decimal";
    }
    if (conf_empresa_columna_existe($conexion, 'decimales_stock')) {
        $cols[] = 'decimales_stock';
        $vals[] = "$decimales_stock";
    }
    if (conf_empresa_columna_existe($conexion, 'usa_presentaciones')) {
        $cols[] = 'usa_presentaciones';
        $vals[] = "$usa_presentaciones";
    }
    if (conf_empresa_columna_existe($conexion, 'usa_venta_presentaciones')) {
        $cols[] = 'usa_venta_presentaciones';
        $vals[] = "$usa_venta_presentaciones";
    }
    if (conf_empresa_columna_existe($conexion, 'usa_canales_venta')) {
        $cols[] = 'usa_canales_venta';
        $vals[] = "$usa_canales_venta";
    }

    $sql = "INSERT INTO conf__empresas (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return false;
    }

    $nueva_empresa_id = intval(mysqli_insert_id($conexion));

    if ($es_principal === 1 && conf_empresa_columna_existe($conexion, 'es_principal')) {
        mysqli_query($conexion, "UPDATE conf__empresas SET es_principal = 0 WHERE empresa_id != $nueva_empresa_id");
    }

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

        $colsP = ['empresa_id', 'condicion_fiscal_id', 'ingresos_brutos_nro', 'inicio_actividades', 'leyenda_fiscal', 'f_desde', 'f_hasta', 'observaciones', 'usuario_id', 'tabla_estado_registro_id', 'created_at'];
        $valsP = ["$nueva_empresa_id", "$condicion_fiscal_id", "'$ib_val'", "$ia_val", "'$lf_val'", "CURDATE()", "NULL", "'Parámetros iniciales generados desde ABM Empresas'", "$user_id_val", "1", "NOW()"];
        if (param_empresa_columna_existe($conexion, 'iibb_regimen_id')) {
            $colsP[] = 'iibb_regimen_id';
            $valsP[] = "$iibb_regimen_id";
        }
        if (param_empresa_columna_existe($conexion, 'jurisdiccion_sede_id')) {
            $colsP[] = 'jurisdiccion_sede_id';
            $valsP[] = "$jurisdiccion_sede_id";
        }

        $sqlParam = "INSERT INTO gestion__empresas_parametros (" . implode(', ', $colsP) . ") VALUES (" . implode(', ', $valsP) . ")";
        mysqli_query($conexion, $sqlParam);
    }

    // Uso de la IA (core/ia_empresa.php): la clave no va a la auditoría
    if (array_key_exists('ia_habilitada', $data)) {
        ia_empresa_guardar($conexion, $nueva_empresa_id, !empty($data['ia_habilitada']), (string)($data['ia_api_key'] ?? ''), false);
    }
    $data_auditoria = $data;
    unset($data_auditoria['ia_api_key'], $data_auditoria['ia_api_key_quitar']);

    auditoria_registrar_alta($conexion, 'conf__empresas', $nueva_empresa_id, $data_auditoria, "Alta de empresa '{$empresa}' (Documento: {$documento_numero})");

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
    // Si no viene (o no es válido) se conserva el tipo de operación actual.
    $tipo_operacion = strtoupper(trim((string) ($data['tipo_operacion'] ?? '')));
    $tipo_operacion_sql = isset(TIPOS_OPERACION[$tipo_operacion]) ? "'$tipo_operacion'" : 'tipo_operacion';
    $nivel_gestion = (isset($data['nivel_gestion']) && $data['nivel_gestion'] === 'SIMPLIFICADA') ? 'SIMPLIFICADA' : 'COMPLETA';
    $formato_fecha = !empty($data['formato_fecha']) ? "'" . mysqli_real_escape_string($conexion, trim($data['formato_fecha'])) . "'" : 'formato_fecha';
    $separador_miles = !empty($data['separador_miles']) ? "'" . mysqli_real_escape_string($conexion, trim($data['separador_miles'])) . "'" : 'separador_miles';
    $separador_decimal = !empty($data['separador_decimal']) ? "'" . mysqli_real_escape_string($conexion, trim($data['separador_decimal'])) . "'" : 'separador_decimal';
    $decimales_stock = is_numeric($data['decimales_stock'] ?? null) ? intval($data['decimales_stock']) : 'decimales_stock';
    // Sin el campo (llamadas que no son la pantalla de Empresas) se conserva
    $usa_presentaciones = isset($data['usa_presentaciones']) ? (!empty($data['usa_presentaciones']) ? 1 : 0) : 'usa_presentaciones';
    // Igual; apagar presentaciones apaga también la venta por presentación
    if ($usa_presentaciones === 0) {
        $usa_venta_presentaciones = 0;
    } elseif (isset($data['usa_venta_presentaciones'])) {
        $usa_venta_presentaciones = !empty($data['usa_venta_presentaciones']) ? 1 : 0;
    } else {
        $usa_venta_presentaciones = 'usa_venta_presentaciones';
    }
    // Sin el campo se conserva
    $usa_canales_venta = isset($data['usa_canales_venta']) ? (!empty($data['usa_canales_venta']) ? 1 : 0) : 'usa_canales_venta';
    $es_principal = isset($data['es_principal']) ? (!empty($data['es_principal']) ? 1 : 0) : null;

    $updates = [
        "empresa='$empresa'",
        "documento_tipo_id='$documento_tipo_id'",
        "documento_numero='$documento_numero'",
        "telefono='$telefono'",
        "domicilio='$domicilio'",
        "localidad_id=$localidad_id",
        "condicion_fiscal_id=$condicion_sql",
        "email='$email'",
        "tabla_estado_registro_id=$estado_registro_id"
    ];
    if (conf_empresa_columna_existe($conexion, 'iibb_regimen_id')) {
        $updates[] = "iibb_regimen_id=$iibb_regimen_id";
    }
    if (conf_empresa_columna_existe($conexion, 'jurisdiccion_sede_id')) {
        $updates[] = "jurisdiccion_sede_id=$jurisdiccion_sede_id";
    }
    if (conf_empresa_columna_existe($conexion, 'base_conf')) {
        $updates[] = "base_conf=$base_conf";
    }
    if (conf_empresa_columna_existe($conexion, 'tipo_operacion')) {
        $updates[] = "tipo_operacion=$tipo_operacion_sql";
    }
    if (conf_empresa_columna_existe($conexion, 'nivel_gestion')) {
        $updates[] = "nivel_gestion='$nivel_gestion'";
    }
    if (conf_empresa_columna_existe($conexion, 'formato_fecha')) {
        $updates[] = "formato_fecha=$formato_fecha";
    }
    if (conf_empresa_columna_existe($conexion, 'separador_miles')) {
        $updates[] = "separador_miles=$separador_miles";
    }
    if (conf_empresa_columna_existe($conexion, 'separador_decimal')) {
        $updates[] = "separador_decimal=$separador_decimal";
    }
    if (conf_empresa_columna_existe($conexion, 'decimales_stock')) {
        $updates[] = "decimales_stock=$decimales_stock";
    }
    if (conf_empresa_columna_existe($conexion, 'usa_presentaciones')) {
        $updates[] = "usa_presentaciones=$usa_presentaciones";
    }
    if (conf_empresa_columna_existe($conexion, 'usa_venta_presentaciones')) {
        $updates[] = "usa_venta_presentaciones=$usa_venta_presentaciones";
    }
    if (conf_empresa_columna_existe($conexion, 'usa_canales_venta')) {
        $updates[] = "usa_canales_venta=$usa_canales_venta";
    }
    if ($es_principal !== null && conf_empresa_columna_existe($conexion, 'es_principal')) {
        $updates[] = "es_principal=$es_principal";
    }

    $sql = "UPDATE conf__empresas SET " . implode(', ', $updates) . " WHERE empresa_id=$id";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        return false;
    }

    if ($es_principal === 1 && conf_empresa_columna_existe($conexion, 'es_principal')) {
        mysqli_query($conexion, "UPDATE conf__empresas SET es_principal = 0 WHERE empresa_id != $id");
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

            $colsP = ['empresa_id', 'condicion_fiscal_id', 'punto_venta_defecto_id', 'lista_precio_defecto_id', 'condicion_pago_defecto_id', 'ingresos_brutos_nro', 'inicio_actividades', 'leyenda_fiscal', 'f_desde', 'f_hasta', 'observaciones', 'usuario_id', 'tabla_estado_registro_id', 'created_at'];
            $valsP = ["$id", "$cfNueva", "$pv", "$lp", "$cp", "$ib", "$inicioAct", "'$leyendaFinal'", "'$hoy'", "NULL", "'Cambio de condición fiscal registrado desde ABM Empresas'", "$user_id_val", "1", "NOW()"];
            if (param_empresa_columna_existe($conexion, 'iibb_regimen_id')) {
                $colsP[] = 'iibb_regimen_id';
                $valsP[] = "$iibb_regimen_id";
            }
            if (param_empresa_columna_existe($conexion, 'jurisdiccion_sede_id')) {
                $colsP[] = 'jurisdiccion_sede_id';
                $valsP[] = "$jurisdiccion_sede_id";
            }

            $sqlNuevo = "INSERT INTO gestion__empresas_parametros (" . implode(', ', $colsP) . ") VALUES (" . implode(', ', $valsP) . ")";
            mysqli_query($conexion, $sqlNuevo);
        }
    } 
    // CASO 2: La condición fiscal no cambió, pero se editaron campos fiscales (IIBB, Inicio actividades, Leyenda)
    else {
        if ($paramVigente) {
            $updatesP = [];
            if ($ib_input !== null) {
                $updatesP[] = "ingresos_brutos_nro = " . ($ib_input !== '' ? "'$ib_input'" : 'NULL');
            }
            if (param_empresa_columna_existe($conexion, 'iibb_regimen_id')) {
                $updatesP[] = "iibb_regimen_id = $iibb_regimen_id";
            }
            if (param_empresa_columna_existe($conexion, 'jurisdiccion_sede_id')) {
                $updatesP[] = "jurisdiccion_sede_id = $jurisdiccion_sede_id";
            }
            if ($ia_input !== null) {
                $updatesP[] = "inicio_actividades = $ia_input";
            }
            if ($lf_input !== null) {
                $updatesP[] = "leyenda_fiscal = " . ($lf_input !== '' ? "'$lf_input'" : 'NULL');
            }
            if (!empty($updatesP)) {
                $updatesP[] = "updated_at = NOW()";
                $sqlUpd = "UPDATE gestion__empresas_parametros SET " . implode(', ', $updatesP) . " WHERE empresa_parametro_id = {$paramVigente['empresa_parametro_id']}";
                mysqli_query($conexion, $sqlUpd);
            }
        } elseif ($cfNueva !== null) {
            // No existía registro de parámetros pero la empresa tiene condición fiscal
            $ib = ($ib_input !== null && $ib_input !== '') ? "'$ib_input'" : (!empty($documento_numero) ? "'$documento_numero'" : 'NULL');
            $ia = ($ia_input !== null) ? $ia_input : 'NULL';
            $lf = ($lf_input !== null && $lf_input !== '') ? "'$lf_input'" : 'NULL';
            
            $colsP = ['empresa_id', 'condicion_fiscal_id', 'ingresos_brutos_nro', 'inicio_actividades', 'leyenda_fiscal', 'f_desde', 'f_hasta', 'observaciones', 'usuario_id', 'tabla_estado_registro_id', 'created_at'];
            $valsP = ["$id", "$cfNueva", "$ib", "$ia", "$lf", "'$hoy'", "NULL", "'Parámetros iniciales generados desde ABM Empresas'", "$user_id_val", "1", "NOW()"];
            if (param_empresa_columna_existe($conexion, 'iibb_regimen_id')) {
                $colsP[] = 'iibb_regimen_id';
                $valsP[] = "$iibb_regimen_id";
            }
            if (param_empresa_columna_existe($conexion, 'jurisdiccion_sede_id')) {
                $colsP[] = 'jurisdiccion_sede_id';
                $valsP[] = "$jurisdiccion_sede_id";
            }

            $sqlNuevo = "INSERT INTO gestion__empresas_parametros (" . implode(', ', $colsP) . ") VALUES (" . implode(', ', $valsP) . ")";
            mysqli_query($conexion, $sqlNuevo);
        }
    }

    // Uso de la IA (core/ia_empresa.php): antes de la auditoría para que el cambio quede registrado
    if (array_key_exists('ia_habilitada', $data)) {
        ia_empresa_guardar($conexion, $id, !empty($data['ia_habilitada']), (string)($data['ia_api_key'] ?? ''), !empty($data['ia_api_key_quitar']));
    }

    $empresaNueva = obtenerempresaPorId($conexion, $id);
    auditoria_registrar_modificacion($conexion, 'conf__empresas', $id, $empresaPrevia ?: [], $empresaNueva ?: [], "Modificación de empresa '{$empresa}' (ID #{$id})");

    return true;
}

function eliminarempresa($conexion, $id)
{
    $id = intval($id);
    $empresaPrevia = obtenerempresaPorId($conexion, $id);
    $sql = "DELETE FROM conf__empresas WHERE empresa_id = $id";
    $res = mysqli_query($conexion, $sql);
    if ($res) {
        auditoria_registrar_baja($conexion, 'conf__empresas', $id, $empresaPrevia, "Eliminación de empresa #{$id}");
    }
    return $res;
}

function obtenerempresaPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT e.*, l.localidad, l.cp, l.provincia_id, p.provincia, dt.documento_tipo,
                   cf.condicion_fiscal, cf.condicion_fiscal_codigo,
                   reg.iibb_regimen_codigo, reg.iibb_regimen_nombre, reg.descripcion as iibb_regimen_descripcion,
                   sed.codigo_comarb, sed.jurisdiccion_sede, sed.organismo as jurisdiccion_sede_organismo,
                   ep.ingresos_brutos_nro, ep.inicio_actividades, ep.leyenda_fiscal, ep.f_desde as parametro_f_desde, ep.empresa_parametro_id,
                   COALESCE(ep.iibb_regimen_id, e.iibb_regimen_id, 1) as iibb_regimen_id,
                   COALESCE(ep.jurisdiccion_sede_id, e.jurisdiccion_sede_id, 2) as jurisdiccion_sede_id
            FROM conf__empresas e
            LEFT JOIN conf__localidades l ON e.localidad_id = l.localidad_id
            LEFT JOIN conf__provincias p ON l.provincia_id = p.provincia_id
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
    $fila = mysqli_fetch_assoc($res);
    if ($fila) {
        $fila['logo_data'] = !empty($fila['logo_archivo']) ? emp_logo_data_uri($fila['logo_archivo']) : '';
        // Uso de la IA (core/ia_empresa.php): la clave nunca sale al navegador, solo si está y sus últimos 4 caracteres
        unset($fila['ia_api_key_cifrada']);
        $fila['ia'] = ia_empresa_estado($conexion, $id);
    }
    return $fila;
}

function guardarLogoEmpresa($conexion, $empresa_id, $ruta, $quitar_logo = false)
{
    $empresa_id = intval($empresa_id);
    if ($empresa_id <= 0) return;
    if ($quitar_logo) {
        emp_logo_establecer($conexion, $empresa_id, null);
    } elseif (!empty($ruta)) {
        emp_logo_establecer($conexion, $empresa_id, $ruta);
    } else {
        $actual = emp_logo_actual($conexion, $empresa_id);
        if ($actual && emp_logo_columna_existe($conexion, 'gestion__empresas_parametros', 'logo_archivo')) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_parametros SET logo_archivo = ?
                                               WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1");
            mysqli_stmt_bind_param($stmt, 'si', $actual, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

function cambiarEstadoEmpresa($conexion, $id, $nuevo_estado, $usuario_id = null)
{
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    if ($id <= 0) {
        return ['success' => false, 'resultado' => false, 'message' => 'ID de empresa inválido.'];
    }
    $empresaPrevia = obtenerempresaPorId($conexion, $id);
    if (!$empresaPrevia) {
        return ['success' => false, 'resultado' => false, 'message' => 'Empresa no encontrada.'];
    }
    $sql = "UPDATE conf__empresas SET tabla_estado_registro_id = $nuevo_estado WHERE empresa_id = $id";
    $res = mysqli_query($conexion, $sql);
    if ($res) {
        $accionTxt = $nuevo_estado === 1 ? 'Activación' : 'Inhabilitación';
        auditoria_registrar_modificacion($conexion, 'conf__empresas', $id, $empresaPrevia, array_merge($empresaPrevia, ['tabla_estado_registro_id' => $nuevo_estado]), "$accionTxt de empresa '{$empresaPrevia['empresa']}' (ID #{$id})");
        return [
            'success' => true,
            'resultado' => true,
            'message' => $nuevo_estado === 1 ? 'Empresa activada correctamente.' : 'Empresa inhabilitada correctamente.'
        ];
    }
    return ['success' => false, 'resultado' => false, 'message' => 'Error en la base de datos al cambiar el estado.'];
}

function marcarEmpresaPrincipal($conexion, $id, $usuario_id = null)
{
    $id = intval($id);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'ID de empresa inválido.'];
    }
    if (!conf_empresa_columna_existe($conexion, 'es_principal')) {
        return ['success' => false, 'message' => 'La columna es_principal no existe en la base de datos.'];
    }
    $empresa = obtenerempresaPorId($conexion, $id);
    if (!$empresa) {
        return ['success' => false, 'message' => 'Empresa no encontrada.'];
    }

    mysqli_query($conexion, "UPDATE conf__empresas SET es_principal = 0");
    $res = mysqli_query($conexion, "UPDATE conf__empresas SET es_principal = 1 WHERE empresa_id = $id");
    if ($res) {
        auditoria_registrar_modificacion(
            $conexion,
            'conf__empresas',
            $id,
            ['es_principal' => $empresa['es_principal'] ?? 0],
            ['es_principal' => 1],
            "Se marcó la empresa '{$empresa['empresa']}' como principal del sistema"
        );
        return [
            'success' => true,
            'message' => "La empresa '{$empresa['empresa']}' fue establecida como principal del sistema."
        ];
    }
    return ['success' => false, 'message' => 'Error al actualizar la base de datos.'];
}

