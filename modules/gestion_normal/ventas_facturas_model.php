<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/formato.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/ventas_presentaciones.php'; // venta por presentación (vpre_*): inactiva sin el indicador
require_once __DIR__ . '/../../core/canales_venta.php'; // canales de venta (cvta_*): inactivo sin el indicador
require_once __DIR__ . '/../../core/precios_venta.php'; // reglas de precio de venta (prv_*): inactivo sin el indicador
require_once __DIR__ . '/../../core/vendedores.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/../../arca/arca_lib.php';
require_once __DIR__ . '/percepciones_iibb_core_model.php';
require_once __DIR__ . '/periodos_fiscales_core_model.php';
require_once __DIR__ . '/../../core/cont_periodos.php';
$conexion = $conn;

function vfEstadoColumna($conexion) {
    $result = mysqli_query($conexion, "SHOW COLUMNS FROM conf__estados_registros");
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) $columns[] = $row['Field'];
    if (in_array('estado_registro', $columns)) return 'estado_registro';
    if (in_array('nombre_estado', $columns)) return 'nombre_estado';
    return 'descripcion';
}

function listarVentasFacturas($conexion, $empresa_id, $pagina_id) {
    $estado = vfEstadoColumna($conexion);
    $sql = "SELECT f.venta_factura_id, f.comprobante_nro, f.f_emision, f.importe_total,
                   f.entidad_id, f.consumidor_final_nombre, f.condicion_pago_id, er.$estado AS estado_registro,
                   er.codigo_estandar, f.tabla_estado_registro_id, ct.comprobante_tipo, e.entidad_nombre,
                   e.entidad_fantasia, cp.condicion_pago, f.CAE, f.f_vto_CAE
            FROM gestion__ventas_facturas f
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = f.condicion_pago_id
            WHERE f.empresa_id = ?";

    $uid = intval($_SESSION['usuario_id'] ?? 0);
    if ($uid > 0) {
        if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
            require_once __DIR__ . '/../../core/accesos_sucursales.php';
            $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $uid, $empresa_id, 'f.sucursal_id');
        }
        $sql .= vcar_sql_filtro_cliente($conexion, intval($empresa_id), $uid, 'f.entidad_id');
    }

    $sql .= " ORDER BY f.f_emision DESC, f.venta_factura_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['estado_info'] = ['estado_registro' => $row['estado_registro'] ?? 'Sin estado', 'codigo_estandar' => $row['codigo_estandar'] ?? ''];
        $row['botones'] = vfBotonesPorEstado($conexion, $pagina_id, intval($row['tabla_estado_registro_id'] ?? 0));
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// Todas las funciones (botones) configuradas para esta página, sin filtrar por estado
// origen. Base de vfBotonesPorEstado() y vfObtenerBotonAgregar().
function vfObtenerFuncionesPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfBotonesPorEstado($conexion, $pagina_id, $estado_id) {
        static $cache = [];
        $clave = $pagina_id . '|' . $estado_id;
        if (isset($cache[$clave])) return $cache[$clave];
        $sql = "SELECT pf.pagina_funcion_id, pf.pagina_id, pf.nombre_funcion, pf.accion_js, pf.descripcion,
                       pf.tabla_estado_registro_origen_id,
                       pf.tabla_estado_registro_destino_id,
                       i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
                        FROM conf__paginas_funciones pf
                        LEFT JOIN conf__iconos i ON i.icono_id = pf.icono_id
                        LEFT JOIN conf__colores c ON c.color_id = pf.color_id
                        WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ?
                            AND pf.tabla_estado_registro_id = 1
                        ORDER BY pf.orden";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'ii', $pagina_id, $estado_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['es_confirmable'] = intval($row['tabla_estado_registro_destino_id']) !== intval($estado_id) ? 1 : 0;
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        // Además del estado, solo las funciones asignadas al perfil del usuario
        return $cache[$clave] = permisos_filtrar_funciones($conexion, $rows);
}

// Función con origen_id = 0 (creación) — mismo patrón que obtenerBotonAgregar() de ventas_pedidos_model.php.
function vfObtenerBotonAgregar($conexion, $pagina_id) {
    $funciones = permisos_filtrar_funciones($conexion, vfObtenerFuncionesPagina($conexion, $pagina_id));
    if (permisos_sin_funcion_creacion($funciones)) {
        return null;
    }
    foreach ($funciones as $funcion) {
        if (intval($funcion['tabla_estado_registro_origen_id']) === 0) {
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? 'agregar',
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion']
            ];
        }
    }
    return [
        'nombre_funcion' => 'Nueva Factura',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => '',
        'text_clase' => ''
    ];
}

function vfObtenerCondicionCliente($conexion, $entidad_id, $empresa_id) {
    $sql = "SELECT e.entidad_id, e.entidad_nombre,
                   ecc.condicion_pago_id, ecc.lista_precio_id, ecc.cliente_descuento_general,
                   cp.condicion_pago, cp.dias_primer_vencimiento,
                   lp.lista_precio_nombre,
                   cf.condicion_fiscal_id, cf.condicion_fiscal, cf.condicion_fiscal_codigo
            FROM gestion__entidades e
            LEFT JOIN gestion__entidades_condiciones_clientes ecc
                   ON ecc.entidad_condicion_cliente_id = (
                        SELECT ecc2.entidad_condicion_cliente_id
                        FROM gestion__entidades_condiciones_clientes ecc2
                        WHERE ecc2.entidad_id = e.entidad_id
                          AND ecc2.tabla_estado_registro_id = 1
                          AND ecc2.f_desde <= CURDATE()
                          AND (ecc2.f_hasta IS NULL OR ecc2.f_hasta >= CURDATE())
                        ORDER BY ecc2.f_desde DESC, ecc2.entidad_condicion_cliente_id DESC
                        LIMIT 1
                   )
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = ecc.condicion_pago_id
            LEFT JOIN gestion__listas_precios lp ON lp.lista_precio_id = ecc.lista_precio_id
            LEFT JOIN gestion__entidades_condiciones_fiscales ecf
                   ON ecf.entidad_condicion_fiscal_id = (
                        SELECT ecf2.entidad_condicion_fiscal_id
                        FROM gestion__entidades_condiciones_fiscales ecf2
                        WHERE ecf2.entidad_id = e.entidad_id
                          AND ecf2.tabla_estado_registro_id = 1
                          AND (ecf2.f_desde IS NULL OR ecf2.f_desde <= CURDATE())
                          AND (ecf2.f_hasta IS NULL OR ecf2.f_hasta >= CURDATE())
                        ORDER BY ecf2.f_desde DESC, ecf2.entidad_condicion_fiscal_id DESC
                        LIMIT 1
                   )
            LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = ecf.condicion_fiscal_id
            WHERE e.entidad_id = ? AND e.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'ii', $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($row) {
        if (empty($row['condicion_fiscal_id'])) {
            $row['condicion_fiscal_id'] = 2;
            $row['condicion_fiscal'] = 'Consumidor Final';
            $row['condicion_fiscal_codigo'] = 'CF';
        }
    }
    return cvta_completar_condicion($conexion, intval($entidad_id), $row ?: null, intval($empresa_id)); // canales de venta: lista unificada (sin el indicador, igual que antes)
}

// Condición comercial de un consumidor final (factura sin cliente), con la misma forma que
// vfObtenerCondicionCliente(): lista "Mostrador" de la empresa (por código, no por id), sin
// descuento general, y la condición de pago de contado. Devuelve null si la empresa no
// tiene la lista Mostrador activa.
const VF_LISTA_PRECIO_CODIGO_MOSTRADOR = 'MOST';

function vfObtenerCondicionConsumidorFinal($conexion, $empresa_id) {
    $codigo = VF_LISTA_PRECIO_CODIGO_MOSTRADOR;
    $sql = "SELECT lp.lista_precio_id, lp.lista_precio_nombre,
                   cp.condicion_pago_id, cp.condicion_pago
            FROM gestion__listas_precios lp
            LEFT JOIN gestion__condiciones_pago cp
                   ON cp.condicion_pago_id = (SELECT cp2.condicion_pago_id
                                              FROM gestion__condiciones_pago cp2
                                              WHERE cp2.empresa_id = lp.empresa_id AND cp2.tipo = 'CONTADO'
                                                AND cp2.tabla_estado_registro_id = 1
                                              ORDER BY cp2.orden LIMIT 1)
            WHERE lp.empresa_id = ? AND lp.lista_precio_codigo = ? AND lp.tabla_estado_registro_id = 1
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'is', $empresa_id, $codigo);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) return null;
    $row['cliente_descuento_general'] = 0;
    $row['dias_primer_vencimiento'] = 0;
    $row['es_consumidor_final'] = 1;
    $row['condicion_fiscal_id'] = 2;
    $row['condicion_fiscal'] = 'Consumidor Final';
    $row['condicion_fiscal_codigo'] = 'CF';
    return $row;
}

// Condición a aplicar según el destinatario: cliente (entidad_id > 0) o consumidor final (0).
function vfObtenerCondicionDestinatario($conexion, $entidad_id, $empresa_id) {
    $entidad_id = intval($entidad_id);
    return $entidad_id > 0
        ? vfObtenerCondicionCliente($conexion, $entidad_id, $empresa_id)
        : vfObtenerCondicionConsumidorFinal($conexion, $empresa_id);
}

// Destinatario de la factura: cliente (entidad_id > 0, con o sin sucursal) o consumidor
// final (entidad_id vacío + consumidor_final_nombre obligatorio, entidad_id se guarda NULL).
// Mismo criterio que resolverDestinatarioRemito() en ventas_remitos_model.php.
function vfResolverDestinatario($data) {
    $entidad_id = intval($data['entidad_id'] ?? 0);
    if ($entidad_id > 0) {
        return [
            'entidad_id' => $entidad_id,
            'entidad_sucursal_id' => intval(($data['entidad_sucursal_id'] ?? null) ?: 0),
            'consumidor_final_nombre' => null
        ];
    }
    $nombre = trim($data['consumidor_final_nombre'] ?? '');
    if ($nombre === '') return ['error' => 'Seleccione un cliente o indique el nombre del consumidor final.'];
    if (mb_strlen($nombre) > 150) return ['error' => 'El nombre del consumidor final no puede superar los 150 caracteres.'];
    return ['entidad_id' => null, 'entidad_sucursal_id' => 0, 'consumidor_final_nombre' => $nombre];
}

function vfClientes($conexion, $empresa_id) {
        $sql = "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia,
                                     es.sucursal_id AS entidad_sucursal_id, es.sucursal_nombre
                        FROM gestion__entidades e
                        LEFT JOIN gestion__entidades_sucursales es
                                ON es.entidad_id = e.entidad_id
                             AND es.empresa_id = e.empresa_id
                             AND es.tabla_estado_registro_id = 1
                        WHERE e.empresa_id = ? AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI') . "
                            AND e.tabla_estado_registro_id = 1"
                        . vcar_sql_filtro_cliente($conexion, intval($empresa_id), intval($_SESSION['usuario_id'] ?? 0), 'e.entidad_id') . "
                        ORDER BY e.entidad_nombre, es.sucursal_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfPuntosVenta($conexion, $empresa_id) {
    $sql = "SELECT pv.punto_venta_id, pv.sucursal_id, pv.nombre, pv.codigo_fiscal, pv.sistema_emision,
                   s.sucursal_nombre
            FROM gestion__puntos_venta pv
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = pv.sucursal_id
            WHERE pv.empresa_id = ? AND pv.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre, pv.nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $rows = AccesosSucursalesHelper::filtrarPuntosVenta($conexion, $empresa_id, $rows);
    }

    return $rows;
}

/**
 * Obtiene la relación fiscal (cruce emisor vs receptor) desde gestion__comprobantes_relacion_fiscal.
 * Emisor: conf__empresas.condicion_fiscal_id
 * Receptor: gestion__entidades_condiciones_fiscales o 2 (Consumidor Final)
 * $fecha (Y-m-d): condiciones vigentes a esa fecha en vez de hoy; el emisor sale entonces de la vigencia de
 * gestion__empresas_parametros a esa fecha (y si no tiene condición, de conf__empresas). La usa la impresión.
 */
function vfObtenerRelacionFiscal($conexion, $empresa_id, $entidad_id = null, $fecha = null) {
    $fecha = !empty($fecha) ? substr((string) $fecha, 0, 10) : null;
    // 1. Condición fiscal emisor (empresa)
    $emisor_cond_id = 0;
    if ($fecha !== null) {
        $stmt = mysqli_prepare($conexion, "SELECT condicion_fiscal_id FROM gestion__empresas_parametros
                                           WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                             AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
                                           ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'iss', $empresa_id, $fecha, $fecha);
        mysqli_stmt_execute($stmt);
        $emisor_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $emisor_cond_id = intval($emisor_row['condicion_fiscal_id'] ?? 0);
    }
    if ($emisor_cond_id <= 0) {
        $stmt = mysqli_prepare($conexion, "SELECT condicion_fiscal_id FROM conf__empresas WHERE empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
        mysqli_stmt_execute($stmt);
        $emisor_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $emisor_cond_id = intval($emisor_row['condicion_fiscal_id'] ?? 1) ?: 1;
    }

    // 2. Condición fiscal receptor (cliente o consumidor final)
    $receptor_cond_id = 2; // Default Consumidor Final
    $receptor_cond_nombre = 'Consumidor Final';
    $receptor_cond_codigo = 'CF';

    if ($entidad_id !== null && $entidad_id !== '' && $entidad_id !== 'CF' && intval($entidad_id) > 0) {
        $stmt2 = mysqli_prepare($conexion, "
            SELECT ecf.condicion_fiscal_id, cf.condicion_fiscal, cf.condicion_fiscal_codigo
            FROM gestion__entidades_condiciones_fiscales ecf
            INNER JOIN gestion__condiciones_fiscales cf ON ecf.condicion_fiscal_id = cf.condicion_fiscal_id
            WHERE ecf.entidad_id = ?
              AND ecf.tabla_estado_registro_id = 1
              AND (ecf.f_desde IS NULL OR ecf.f_desde <= COALESCE(?, CURDATE()))
              AND (ecf.f_hasta IS NULL OR ecf.f_hasta >= COALESCE(?, CURDATE()))
            ORDER BY ecf.f_desde DESC, ecf.entidad_condicion_fiscal_id DESC
            LIMIT 1
        ");
        $eid = intval($entidad_id);
        mysqli_stmt_bind_param($stmt2, 'iss', $eid, $fecha, $fecha);
        mysqli_stmt_execute($stmt2);
        $rec = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
        mysqli_stmt_close($stmt2);
        if ($rec) {
            $receptor_cond_id = intval($rec['condicion_fiscal_id']);
            $receptor_cond_nombre = $rec['condicion_fiscal'];
            $receptor_cond_codigo = $rec['condicion_fiscal_codigo'];
        }
    }

    // 3. Cruce en gestion__comprobantes_relacion_fiscal
    $stmt3 = mysqli_prepare($conexion, "
        SELECT rf.relacion_fiscal_id, rf.comprobante_fiscal_id, rf.discrimina_iva,
               cf.comprobante_fiscal, cf.letra
        FROM gestion__comprobantes_relacion_fiscal rf
        LEFT JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = rf.comprobante_fiscal_id
        WHERE rf.condicion_fiscal_emisor_id = ?
          AND rf.condicion_fiscal_receptor_id = ?
          AND rf.tabla_estado_registro_id = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt3, 'ii', $emisor_cond_id, $receptor_cond_id);
    mysqli_stmt_execute($stmt3);
    $rel = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3));
    mysqli_stmt_close($stmt3);

    return [
        'emisor_condicion_fiscal_id' => $emisor_cond_id,
        'receptor_condicion_fiscal_id' => $receptor_cond_id,
        'receptor_condicion_fiscal_nombre' => $receptor_cond_nombre,
        'receptor_condicion_fiscal_codigo' => $receptor_cond_codigo,
        'relacion' => $rel
    ];
}

// Mismo criterio que obtenerComprobantesTipos (pedidos) / obtenerComprobantesTiposRemitos:
// pagina_id -> conf__paginas.tabla_id -> subgrupos con esa tabla_id -> tipos, intersectado
// con lo habilitado para el PV. Sin el filtro por subgrupo aparecían también los tipos de
// compras (C_FA, C_NCA...) y de stock/pedidos habilitados en el PV, duplicando "Factura A", etc.
// Si el punto de venta tiene asociados tipos con comprobante_fiscal_id > 0 y se proporciona entidad_id,
// se ajusta según la relación fiscal emisor / receptor.
// $solo_raiz: excluye los tipos con al menos un padre en gestion__comprobantes_tipos_dependencias
// (NC/ND), que no pueden iniciar un circuito. Lo piden las pantallas que facturan desde un remito.
function vfTiposPorPuntoVenta($conexion, $empresa_id, $punto_venta_id, $pagina_id, $entidad_id = null, $solo_raiz = false) {
    if (empty($punto_venta_id)) return [];

    $stmt = mysqli_prepare($conexion, "SELECT tabla_id FROM conf__paginas WHERE pagina_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $pagina = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $tabla_id = intval($pagina['tabla_id'] ?? 0);
    if (empty($tabla_id)) {
        error_log("vfTiposPorPuntoVenta: sin tabla_id configurado para pagina_id=$pagina_id");
        return [];
    }

    $sql = "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.letra, ct.codigo,
                   ct.comprobante_fiscal_id
            FROM gestion__comprobantes_tipos ct
            INNER JOIN gestion__comprobantes_subgrupos cs
                ON cs.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
                AND cs.tabla_estado_registro_id = 1
                AND cs.tabla_id = ?
                AND cs.empresa_id IN (0, ?)
            INNER JOIN gestion__puntos_venta_comprobantes pvc
                ON pvc.comprobante_tipo_id = ct.comprobante_tipo_id
                AND pvc.punto_venta_id = ?
                AND pvc.empresa_id = ?
                AND pvc.tabla_estado_registro_id = 1
            WHERE ct.tabla_estado_registro_id = 1
              AND ct.empresa_id IN (0, ?)";
    if ($solo_raiz) {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos_dependencias ctd
                                   WHERE ctd.comprobante_tipo_id = ct.comprobante_tipo_id
                                     AND ctd.empresa_id IN (0, ?)
                                     AND ctd.tabla_estado_registro_id = 1)";
    }
    $sql .= " ORDER BY ct.orden, ct.comprobante_tipo";
    $stmt = mysqli_prepare($conexion, $sql);
    // tabla_id, empresa (subgrupo), punto_venta_id, empresa (pvc), empresa (tipo) [, empresa (dependencias)]
    if ($solo_raiz) {
        mysqli_stmt_bind_param($stmt, 'iiiiii', $tabla_id, $empresa_id, $punto_venta_id, $empresa_id, $empresa_id, $empresa_id);
    } else {
        mysqli_stmt_bind_param($stmt, 'iiiii', $tabla_id, $empresa_id, $punto_venta_id, $empresa_id, $empresa_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    $tiene_fiscales = false;
    while ($row = mysqli_fetch_assoc($result)) {
        if (intval($row['comprobante_fiscal_id'] ?? 0) > 0) {
            $tiene_fiscales = true;
        }
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    // Ajustar en función de la condición fiscal cuando el PV tiene comprobantes fiscales asociados
    if ($tiene_fiscales && $entidad_id !== null && $entidad_id !== '') {
        $relFiscal = vfObtenerRelacionFiscal($conexion, $empresa_id, $entidad_id);
        $letraRequerida = $relFiscal['relacion']['letra'] ?? null;
        $fiscalIdRequerido = intval($relFiscal['relacion']['comprobante_fiscal_id'] ?? 0);
        $discriminaIva = intval($relFiscal['relacion']['discrimina_iva'] ?? 0);

        $filtrados = [];
        foreach ($rows as $r) {
            $cfId = intval($r['comprobante_fiscal_id'] ?? 0);
            if ($cfId > 0) {
                if ($letraRequerida && strtoupper($r['letra'] ?? '') === strtoupper($letraRequerida)) {
                    $r['es_sugerido'] = ($cfId === $fiscalIdRequerido) ? 1 : 0;
                    $r['discrimina_iva'] = $discriminaIva;
                    $r['receptor_condicion_fiscal'] = $relFiscal['receptor_condicion_fiscal_nombre'];
                    $filtrados[] = $r;
                }
            } else {
                $filtrados[] = $r;
            }
        }
        return $filtrados;
    }

    return $rows;
}

// Un tipo dependiente (NC/ND: tiene padre en gestion__comprobantes_tipos_dependencias) no puede
// emitirse desde un remito: necesita un comprobante previo, no inicia el circuito.
// Devuelve el mensaje de error, o null si el tipo es válido para esas líneas.
function vfValidarTipoRaizDesdeRemito($conexion, $empresa_id, $tipo_id, $detalles) {
    $desdeRemito = false;
    foreach ($detalles as $d) {
        if (intval($d['venta_remito_detalle_id'] ?? 0) > 0) { $desdeRemito = true; break; }
    }
    if (!$desdeRemito) return null;

    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM gestion__comprobantes_tipos_dependencias
                                       WHERE comprobante_tipo_id = ? AND empresa_id IN (0, ?)
                                         AND tabla_estado_registro_id = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $tipo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $esDependiente = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $esDependiente
        ? 'Una nota de crédito o débito no puede emitirse desde un remito: debe asociarse a un comprobante previo.'
        : null;
}

// $excluir_factura_id: al editar una factura ya guardada, sus propias líneas consumidas
// de remito no deben contar como "ya facturadas" para esta misma pantalla — si no,
// una línea 100% tomada por esta factura desaparecería del listado de pendientes al
// reabrirla. Se descuenta lo que YA consumió esa factura (propia.cantidad_propia) del
// cantidad_facturada global antes de calcular el pendiente. En alta (id=0) no afecta nada.
// $venta_remito_id_filtro: si viene > 0, acota el resultado a las líneas pendientes de ESE
// remito puntual (usado por la solapa "Facturar" del modal de ventas_remitos.php — ahí ya se
// sabe de qué remito se trata, no hace falta traer el resto de los pendientes del cliente).
// $entidad_id = 0: remitos a consumidor final (vr.entidad_id NULL), de cualquier nombre —
// por eso se devuelve también vr.consumidor_final_nombre, para distinguirlos en pantalla.
function vfRemitosPendientes($conexion, $empresa_id, $entidad_id, $entidad_sucursal_id = 0, $excluir_factura_id = 0, $venta_remito_id_filtro = 0) {
    $sql = "SELECT vr.venta_remito_id, vr.comprobante_nro, vr.f_emision, vr.consumidor_final_nombre,
                   ct.comprobante_tipo, pv.nombre AS punto_venta_nombre,
                   rd.venta_remito_detalle_id, rd.venta_pedido_detalle_id,
                   rd.producto_id, p.producto_codigo, p.producto_nombre,
                   rd.cantidad, rd.precio_unitario_bruto, rd.descuento_general_pct,
                   rd.descuento_general, rd.precio_unitario_neto, rd.iva_alicuota_id,
                   rd.iva_porcentaje, rd.iva_importe, rd.importe_linea,
                   COALESCE(rd.facturado, 0) AS facturado,
                   COALESCE(propia.cantidad_propia, 0) AS cantidad_propia,
                   GREATEST(0, COALESCE(rd.cantidad_facturada, 0) - COALESCE(propia.cantidad_propia, 0)) AS cantidad_facturada,
                   (rd.cantidad - GREATEST(0, COALESCE(rd.cantidad_facturada, 0) - COALESCE(propia.cantidad_propia, 0))) AS cantidad_pendiente_facturar
            FROM gestion__ventas_remitos vr
            INNER JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_id = vr.venta_remito_id
            INNER JOIN gestion__productos p ON p.producto_id = rd.producto_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vr.comprobante_tipo_id
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = vr.comprobante_pv
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = vr.tabla_estado_registro_id
            LEFT JOIN (
                SELECT venta_remito_detalle_id, SUM(cantidad) AS cantidad_propia
                FROM gestion__ventas_facturas_detalles
                WHERE venta_factura_id = ? AND venta_remito_detalle_id IS NOT NULL
                GROUP BY venta_remito_detalle_id
            ) propia ON propia.venta_remito_detalle_id = rd.venta_remito_detalle_id
                        WHERE vr.empresa_id = ? AND COALESCE(vr.entidad_id, 0) = ?
                            AND (? = 0 OR vr.entidad_sucursal_id = ?)
                            AND (? = 0 OR vr.venta_remito_id = ?)
              -- Facturado también, si la línea es de la factura que se edita (al guardarla el remito pudo quedar Facturado)
              AND (er.codigo_estandar IN ('CONFIRMADO', 'PEND_FACT', 'FACT_PARC') OR COALESCE(propia.cantidad_propia, 0) > 0)
              AND (rd.cantidad - GREATEST(0, COALESCE(rd.cantidad_facturada, 0) - COALESCE(propia.cantidad_propia, 0))) > 0
              AND rd.tabla_estado_registro_id = 1
            ORDER BY vr.f_emision, vr.venta_remito_id, rd.venta_remito_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'iiiiiii', $excluir_factura_id, $empresa_id, $entidad_id, $entidad_sucursal_id, $entidad_sucursal_id, $venta_remito_id_filtro, $venta_remito_id_filtro);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    // Venta por presentación: la presentación de cada línea de remito (solo con el indicador)
    if (vpre_activo($conexion, (int) $empresa_id, 'remito')) {
        $rows = vpre_agregar_fotos($conexion, 'remito', $rows);
    }
    return $rows;
}

// Copia de condicionPalabraBusquedaProducto() de productos_model.php (sin la columna de
// estado) + p.producto_codigo. Se duplica para no incluir productos_model.php acá; si
// cambia la regla allá, replicarla. Palabras 100% numéricas se buscan en la
// compatibilidad como TOKEN completo: "18" no debe matchear "2018".
function vfCondicionPalabraBusquedaProducto($palabra) {
    $palabra_like = '%' . $palabra . '%';
    if (ctype_digit($palabra)) {
        $compat_condition = "CONCAT(' ', p.compatibilidad_busqueda, ' ') LIKE ?";
        $compat_param = '% ' . $palabra . ' %';
    } else {
        $compat_condition = "p.compatibilidad_busqueda LIKE ?";
        $compat_param = $palabra_like;
    }
    $condiciones = ["p.producto_codigo LIKE ?", "p.producto_nombre LIKE ?", "p.codigo_barras LIKE ?", "p.producto_descripcion LIKE ?", $compat_condition];
    $params = [$palabra_like, $palabra_like, $palabra_like, $palabra_like, $compat_param];
    return ["(" . implode(" OR ", $condiciones) . ")", $params, str_repeat("s", count($params))];
}

// $entidad_id = 0: consumidor final (lista Mostrador, ver vfObtenerCondicionConsumidorFinal()).
function vfBuscarProductos($conexion, $empresa_id, $entidad_id, $q) {
    // Se listan todos los productos activos de la empresa, estén o no en la lista de
    // precios del destinatario: sin precio en la lista (o sin lista) van con importe 0.
    $cond = vfObtenerCondicionDestinatario($conexion, $entidad_id, $empresa_id);
    $lista = intval($cond['lista_precio_id'] ?? 0);
    // Misma búsqueda de texto que el listado de productos.php (términos entre comillas
    // = frase completa, el resto se parte por espacios, AND entre términos), más el
    // código de producto. Se muestra compatibilidad_texto, la versión legible de
    // compatibilidad_busqueda.
    $palabras = [];
    if (preg_match_all('/"([^"]*)"|(\S+)/', trim($q), $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $termino = trim(isset($match[2]) ? $match[2] : $match[1]);
            if (strlen($termino) > 0) $palabras[] = $termino;
        }
    }
    if (!$palabras) return [];
    $where_palabras = '';
    $tipos = 'iii';
    $params = [$lista, $empresa_id, $empresa_id];
    foreach ($palabras as $palabra) {
        [$condicion, $params_palabra, $tipos_palabra] = vfCondicionPalabraBusquedaProducto($palabra);
        $where_palabras .= " AND $condicion";
        array_push($params, ...$params_palabra);
        $tipos .= $tipos_palabra;
    }
    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                   p.compatibilidad_texto,
                   COALESCE(lp.precio_final, 0) AS precio_unitario, p.iva_alicuota_id,
                   COALESCE(iva.porcentaje, 0) AS iva_porcentaje
            FROM gestion__productos p
            LEFT JOIN gestion__listas_precios_productos lp
                   ON lp.producto_id = p.producto_id AND lp.lista_precio_id = ?
                  AND lp.empresa_id = ? AND lp.tabla_estado_registro_id = 1
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON iva.iva_alicuota_id = p.iva_alicuota_id
            WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1
              $where_palabras
            ORDER BY p.producto_codigo LIMIT 20";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    // Venta por presentación: ofertas por producto (sin el indicador, las filas tal cual)
    // en_lista: canales de venta, aviso "sin precio en la lista del cliente" (sin el indicador no se agrega)
    return cvta_marcar_en_lista($conexion, (int) $empresa_id, (int) $lista, vpre_agregar_ofertas($conexion, (int) $empresa_id, $lista, $rows, 0, 'factura'));
}

function vfEstadoInicial($conexion, $pagina_id) {
    $sql = "SELECT ter.estado_registro_id
            FROM conf__tablas_estados_registros ter
            INNER JOIN conf__paginas pg ON pg.tabla_id = ter.tabla_id
            WHERE pg.pagina_id = ? AND ter.es_inicial = 1 LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['estado_registro_id'] ?? 3);
}

function vfObtenerProximoNumero($conexion, $empresa_id, $punto_venta_id, $comprobante_tipo_id) {
    $sql = "SELECT numerador_id, ultimo_numero
            FROM gestion__comprobantes_numeradores
            WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?
            FOR UPDATE";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('No se pudo preparar el numerador: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $punto_venta_id, $comprobante_tipo_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row) {
        $numero = intval($row['ultimo_numero']) + 1;
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_numeradores SET ultimo_numero = ? WHERE numerador_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $numero, $row['numerador_id']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('No se pudo actualizar el numerador.');
        mysqli_stmt_close($stmt);
        return $numero;
    }

    $numero = 1;
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_numeradores (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iiii', $empresa_id, $punto_venta_id, $comprobante_tipo_id, $numero);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('No se pudo crear el numerador.');
    mysqli_stmt_close($stmt);
    return $numero;
}

// tabla_id de conf__tablas asociada a esta página, usado por syncComprobante() para
// identificar el origen del comprobante. Copia funcional de obtenerTablaOrigenPorPagina()
// tal como está en ventas_pedidos_model.php / facturas_proveedores_model.php.
function obtenerTablaOrigenPorPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT tabla_id FROM conf__paginas WHERE pagina_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta tabla_id: " . mysqli_error($conexion));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ($row && !empty($row['tabla_id'])) ? (int)$row['tabla_id'] : null;
}

// Copia funcional de syncComprobante() tal como está en ventas_pedidos_model.php /
// facturas_proveedores_model.php. Se duplica aquí a propósito para no introducir un
// include cruzado entre módulos; si se resuelve extraer un helper común, esta función
// debería eliminarse de acá.
function syncComprobante($conexion, $data, $tabla_origen_id) {
    if (empty($tabla_origen_id)) {
        error_log("ERROR: tabla_origen_id no proporcionado");
        return null;
    }

    $empresa_id = intval($data['empresa_id'] ?? 0);
    $sucursal_id = !empty($data['sucursal_id']) ? intval($data['sucursal_id']) : null;
    $comprobante_pv = intval($data['comprobante_pv'] ?? 0);
    $comprobante_tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
    $comprobante_nro = intval($data['comprobante_nro'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $entidad_sucursal_id = !empty($data['entidad_sucursal_id']) ? intval($data['entidad_sucursal_id']) : null;
    $f_emision = $data['f_emision'] ?? date('Y-m-d');
    $f_contabilidad = $data['f_contabilidad'] ?? $f_emision;
    $f_vto = $data['f_vto'] ?? null;
    $moneda_id = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0);
    $tipo_cambio = floatval($data['tipo_cambio'] ?? 1.0);
    $registro_origen_id = intval($data['registro_origen_id'] ?? 0);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 3);
    $usuario_id = intval($data['usuario_id'] ?? 0);
    $observaciones = trim($data['observaciones'] ?? '');

    $importe_neto = floatval($data['importe_neto'] ?? 0);
    $descuento_general = floatval($data['descuento_general'] ?? 0);
    $importe_no_gravado = floatval($data['importe_no_gravado'] ?? 0);
    $importe_exento = floatval($data['importe_exento'] ?? 0);
    $importe_iva = floatval($data['importe_iva'] ?? 0);
    $importe_otros_impuestos = floatval($data['importe_otros_impuestos'] ?? 0);
    $importe_total = floatval($data['importe_total'] ?? 0);

    $importe_bruto = $importe_neto + $descuento_general;

    if ($registro_origen_id <= 0) {
        error_log("ERROR: registro_origen_id inválido: $registro_origen_id");
        return null;
    }

    $sql_check = "SELECT comprobante_id FROM gestion__comprobantes
                  WHERE tabla_origen_id = ? AND registro_origen_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        error_log("Error preparando SELECT: " . mysqli_error($conexion));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "ii", $tabla_origen_id, $registro_origen_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existe = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $comprobante_id = null;

    if ($existe) {
        $comprobante_id = $existe['comprobante_id'];
        $importe_pendiente = $importe_total;

        // C5 / D2 / §2.4: No pisar importe_pendiente si el comprobante ya tiene imputaciones vigentes
        $sql_imp = "SELECT 
                        COALESCE(SUM(CASE WHEN ci.comp_destino_id = ? THEN ci.importe_imputado ELSE 0 END), 0) AS imp_destino,
                        COALESCE(SUM(CASE WHEN ci.comp_origen_id = ? THEN ci.importe_origen ELSE 0 END), 0) AS imp_origen,
                        COUNT(*) AS total_imputaciones
                    FROM gestion__comprobantes_imputaciones ci
                    JOIN conf__estados_registros er ON er.estado_registro_id = ci.tabla_estado_registro_id
                    WHERE (ci.comp_destino_id = ? OR ci.comp_origen_id = ?)
                      AND er.codigo_estandar = 'CONFIRMADO'";
        $stmt_imp = mysqli_prepare($conexion, $sql_imp);
        if ($stmt_imp) {
            mysqli_stmt_bind_param($stmt_imp, "iiii", $comprobante_id, $comprobante_id, $comprobante_id, $comprobante_id);
            mysqli_stmt_execute($stmt_imp);
            $res_imp = mysqli_stmt_get_result($stmt_imp);
            if ($row_imp = mysqli_fetch_assoc($res_imp)) {
                if (intval($row_imp['total_imputaciones']) > 0) {
                    $imputado_total = floatval($row_imp['imp_destino']) + floatval($row_imp['imp_origen']);
                    $importe_pendiente = max(0.0, round($importe_total - $imputado_total, 2));
                }
            }
            mysqli_stmt_close($stmt_imp);
        }

        // Estado derivado del saldo (mismo criterio que estadoComprobantePorSaldo() en
        // tesoreria_core_model.php): un comprobante confirmado con imputaciones vigentes queda en
        // Cancelado parcial (39) / Cancelado total (40) y no vuelve a Confirmado al re-sincronizar.
        if ($tabla_estado_registro_id === 5 && $importe_pendiente < $importe_total - 0.001) {
            $tabla_estado_registro_id = ($importe_pendiente <= 0.001) ? 40 : 39;
        }

        $sql_update = "UPDATE gestion__comprobantes SET
                            empresa_id = ?,
                            sucursal_id = ?,
                            comprobante_pv = ?,
                            comprobante_tipo_id = ?,
                            comprobante_nro = ?,
                            entidad_id = ?,
                            entidad_sucursal_id = ?,
                            f_emision = ?,
                            f_contabilidad = ?,
                            f_vto = ?,
                            moneda_id = ?,
                            tipo_cambio = ?,
                            importe_bruto = ?,
                            descuento_general = ?,
                            importe_no_gravado = ?,
                            importe_exento = ?,
                            importe_neto = ?,
                            importe_iva = ?,
                            importe_otros_impuestos = ?,
                            importe_total = ?,
                            importe_pendiente = ?,
                            tabla_estado_registro_id = ?,
                            observaciones = ?,
                            usuario_modificacion_id = ?
                        WHERE comprobante_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            error_log("Error preparando UPDATE: " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param($stmt, "iiiiiiisssiddddddddddisii",
            $empresa_id, $sucursal_id, $comprobante_pv,
            $comprobante_tipo_id, $comprobante_nro,
            $entidad_id, $entidad_sucursal_id,
            $f_emision, $f_contabilidad, $f_vto,
            $moneda_id, $tipo_cambio,
            $importe_bruto, $descuento_general,
            $importe_no_gravado, $importe_exento,
            $importe_neto, $importe_iva, $importe_otros_impuestos,
            $importe_total,
            $importe_pendiente,
            $tabla_estado_registro_id, $observaciones,
            $usuario_id,
            $comprobante_id
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error ejecutando UPDATE: " . mysqli_stmt_error($stmt));
            return null;
        }
        mysqli_stmt_close($stmt);
    } else {
        $sql_insert = "INSERT INTO gestion__comprobantes (
                            empresa_id, sucursal_id, comprobante_pv,
                            comprobante_tipo_id, comprobante_nro,
                            entidad_id, entidad_sucursal_id,
                            f_emision, f_contabilidad, f_vto,
                            moneda_id, tipo_cambio,
                            importe_bruto, descuento_general,
                            importe_no_gravado, importe_exento,
                            importe_neto, importe_iva, importe_otros_impuestos,
                            importe_total, importe_pendiente,
                            tabla_origen_id, registro_origen_id,
                            tabla_estado_registro_id,
                            usuario_id,
                            observaciones
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt) {
            error_log("Error preparando INSERT: " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param($stmt, "iiiiiiisssiddddddddddiiiis",
            $empresa_id, $sucursal_id, $comprobante_pv,
            $comprobante_tipo_id, $comprobante_nro,
            $entidad_id, $entidad_sucursal_id,
            $f_emision, $f_contabilidad, $f_vto,
            $moneda_id, $tipo_cambio,
            $importe_bruto, $descuento_general,
            $importe_no_gravado, $importe_exento,
            $importe_neto, $importe_iva, $importe_otros_impuestos,
            $importe_total,
            $importe_total,
            $tabla_origen_id, $registro_origen_id,
            $tabla_estado_registro_id,
            $usuario_id,
            $observaciones
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error ejecutando INSERT: " . mysqli_stmt_error($stmt));
            return null;
        }
        $comprobante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
    }

    // Signo del comprobante según su tipo ('-' = nota de crédito): la columna quedaba siempre en 1.
    $stmt_signo = mysqli_prepare($conexion, "UPDATE gestion__comprobantes c
                                             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                                             SET c.signo = IF(ct.signo = '-', -1, 1)
                                             WHERE c.comprobante_id = ?");
    mysqli_stmt_bind_param($stmt_signo, "i", $comprobante_id);
    mysqli_stmt_execute($stmt_signo);
    mysqli_stmt_close($stmt_signo);

    return $comprobante_id;
}

// Revierte el consumo que una línea de factura hizo sobre una línea de remito
// (usado al editar o al eliminar una factura, para no dejar remitos "trabados"
// como facturados por una factura descartada/modificada).
function vfLiberarDetalleRemito($conexion, $remito_detalle_id, $cantidad) {
    $remito_detalle_id = intval($remito_detalle_id);
    $cantidad = floatval($cantidad);
    if (!$remito_detalle_id || $cantidad <= 0) return;

    $stmt = mysqli_prepare($conexion, "SELECT cantidad, cantidad_facturada FROM gestion__ventas_remitos_detalles WHERE venta_remito_detalle_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'i', $remito_detalle_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) return;

    $nueva_cantidad_facturada = max(0, floatval($row['cantidad_facturada']) - $cantidad);
    $facturado = ($nueva_cantidad_facturada >= floatval($row['cantidad'])) ? 1 : 0;

    $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_remitos_detalles SET cantidad_facturada = ?, facturado = ? WHERE venta_remito_detalle_id = ?");
    mysqli_stmt_bind_param($stmt, 'dii', $nueva_cantidad_facturada, $facturado, $remito_detalle_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function vfRecalcularEstadoRemito($conexion, $remito_id) {
    $remito_id = intval($remito_id);
    if (!$remito_id) return;

    $stmt = mysqli_prepare($conexion, "SELECT tabla_estado_registro_id FROM gestion__ventas_remitos WHERE venta_remito_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $remito_id);
    mysqli_stmt_execute($stmt);
    $rem = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$rem) return;
    $estado_actual = intval($rem['tabla_estado_registro_id']);
    // No tocar si es Borrador (3) o Eliminado (6)
    if (!in_array($estado_actual, [5, 12, 13, 15])) return;

    $sql = "SELECT COUNT(*) as total_lineas,
                   SUM(CASE WHEN cantidad_facturada >= cantidad THEN 1 ELSE 0 END) as lineas_completas,
                   SUM(cantidad) as total_cant,
                   SUM(cantidad_facturada) as total_facturada
            FROM gestion__ventas_remitos_detalles
            WHERE venta_remito_id = ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $remito_id);
    mysqli_stmt_execute($stmt);
    $calc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $total_lineas = intval($calc['total_lineas'] ?? 0);
    $lineas_completas = intval($calc['lineas_completas'] ?? 0);
    $total_facturada = floatval($calc['total_facturada'] ?? 0);

    if ($total_lineas === 0 || $total_facturada <= 0.0001) {
        $nuevo_estado = 13; // Pendiente de Facturación
    } elseif ($lineas_completas >= $total_lineas) {
        $nuevo_estado = 12; // Facturado
    } else {
        $nuevo_estado = 15; // Facturación Parcial
    }

    if ($nuevo_estado !== $estado_actual) {
        $upd = mysqli_prepare($conexion, "UPDATE gestion__ventas_remitos SET tabla_estado_registro_id = ? WHERE venta_remito_id = ?");
        mysqli_stmt_bind_param($upd, 'ii', $nuevo_estado, $remito_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        // Espejo del remito en gestion__comprobantes (mismo criterio que ejecutarTransicionEstadoRemito)
        $upd = mysqli_prepare($conexion, "UPDATE gestion__comprobantes c
            JOIN gestion__ventas_remitos r ON r.venta_remito_id = c.registro_origen_id
            SET c.tabla_estado_registro_id = r.tabla_estado_registro_id, c.comprobante_nro = r.comprobante_nro
            WHERE r.venta_remito_id = ?
              AND c.tabla_origen_id = (SELECT t.tabla_id FROM conf__tablas t WHERE t.tabla_nombre = 'gestion__ventas_remitos')");
        mysqli_stmt_bind_param($upd, 'i', $remito_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
}

function vfLiberarRemitosDeFactura($conexion, $venta_factura_id) {
    $venta_factura_id = intval($venta_factura_id);
    $sql = "SELECT DISTINCT rd.venta_remito_id, d.venta_remito_detalle_id, d.cantidad
            FROM gestion__ventas_facturas_detalles d
            INNER JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_detalle_id = d.venta_remito_detalle_id
            WHERE d.venta_factura_id = ? AND d.venta_remito_detalle_id IS NOT NULL";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $venta_factura_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    $remitoIds = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
        $remitoIds[intval($row['venta_remito_id'])] = true;
    }
    mysqli_stmt_close($stmt);

    foreach ($rows as $row) {
        vfLiberarDetalleRemito($conexion, $row['venta_remito_detalle_id'], floatval($row['cantidad']));
    }

    foreach (array_keys($remitoIds) as $remitoId) {
        vfRecalcularEstadoRemito($conexion, $remitoId);
    }
}

function vfActualizarEstadoRemitosDeFactura($conexion, $venta_factura_id) {
    $venta_factura_id = intval($venta_factura_id);
    $sql = "SELECT DISTINCT rd.venta_remito_id
            FROM gestion__ventas_facturas_detalles d
            INNER JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_detalle_id = d.venta_remito_detalle_id
            WHERE d.venta_factura_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $venta_factura_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $remitoIds = [];
    while ($row = mysqli_fetch_assoc($result)) $remitoIds[] = intval($row['venta_remito_id']);
    mysqli_stmt_close($stmt);

    foreach ($remitoIds as $remitoId) {
        vfRecalcularEstadoRemito($conexion, $remitoId);
    }
}

// Datos mínimos de un remito para precargar el alta de factura desde el deep-link que manda
// ventas_remitos.php (botón "Facturado"/"Facturado Parcial"). Filtra por empresa_id: no alcanza
// con conocer el venta_remito_id de otra empresa para leer a qué cliente pertenece.
function vfObtenerRemitoInfo($conexion, $empresa_id, $venta_remito_id) {
    $venta_remito_id = intval($venta_remito_id);
    $sql = "SELECT venta_remito_id, entidad_id, entidad_sucursal_id, consumidor_final_nombre, comprobante_nro
            FROM gestion__ventas_remitos
            WHERE venta_remito_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $venta_remito_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// Facturas que ya tienen alguna línea originada en este remito (para la solapa "Facturar" del
// modal de ventas_remitos.php — mostrar el historial y dejar confirmar/ver desde ahí, sin ir a
// buscarlas a Facturación). Trae los mismos botones por estado que usa el listado normal.
function vfObtenerFacturasDeRemito($conexion, $empresa_id, $venta_remito_id, $pagina_id) {
    $venta_remito_id = intval($venta_remito_id);
    $estado = vfEstadoColumna($conexion);
    $sql = "SELECT DISTINCT f.venta_factura_id, f.comprobante_nro, f.f_emision, f.importe_total,
                   f.tabla_estado_registro_id, er.$estado AS estado_registro, er.codigo_estandar,
                   ct.comprobante_tipo
            FROM gestion__ventas_facturas_detalles d
            INNER JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_detalle_id = d.venta_remito_detalle_id
            INNER JOIN gestion__ventas_facturas f ON f.venta_factura_id = d.venta_factura_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
            WHERE rd.venta_remito_id = ? AND f.empresa_id = ? AND f.tabla_estado_registro_id != 6
            ORDER BY f.venta_factura_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $venta_remito_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['botones'] = vfBotonesPorEstado($conexion, $pagina_id, intval($row['tabla_estado_registro_id']));
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfObtenerVentaFacturaPorId($conexion, $id, $empresa_id, $pagina_id = 0) {
    $id = intval($id);
    $estado = vfEstadoColumna($conexion);
    $sql = "SELECT f.*, er.$estado AS estado_registro, er.codigo_estandar,
                   ct.comprobante_tipo, ct.letra, ct.comprobante_fiscal_id,
                   e.entidad_nombre, e.entidad_fantasia,
                   cp.condicion_pago,
                   pv.nombre AS punto_venta_nombre, pv.sucursal_id AS pv_sucursal_id
            FROM gestion__ventas_facturas f
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = f.condicion_pago_id
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = f.punto_venta_id
            WHERE f.venta_factura_id = ? AND f.empresa_id = ?"
            . vcar_sql_filtro_cliente($conexion, intval($empresa_id), intval($_SESSION['usuario_id'] ?? 0), 'f.entidad_id');
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$factura) return null;

    $sql_det = "SELECT d.*, p.producto_codigo, p.producto_nombre,
                       vr.comprobante_nro AS remito_comprobante_nro, vr.venta_remito_id AS remito_id_origen
                FROM gestion__ventas_facturas_detalles d
                LEFT JOIN gestion__productos p ON p.producto_id = d.producto_id
                LEFT JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_detalle_id = d.venta_remito_detalle_id
                LEFT JOIN gestion__ventas_remitos vr ON vr.venta_remito_id = rd.venta_remito_id
                WHERE d.venta_factura_id = ?
                ORDER BY d.venta_factura_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql_det);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $detalles = [];
    while ($d = mysqli_fetch_assoc($result)) $detalles[] = $d;
    mysqli_stmt_close($stmt);

    $factura['detalles'] = $detalles;
    $factura['percepciones'] = intval($factura['comprobante_id']) > 0 ? perIibbDelComprobante($conexion, intval($factura['comprobante_id'])) : [];
    $factura['botones'] = vfBotonesPorEstado($conexion, $pagina_id, intval($factura['tabla_estado_registro_id']));
    return $factura;
}

// Calcula bruto/descuento/neto/iva/línea de cada detalle (misma fórmula que guardarVentaFactura())
// y acumula los totales de cabecera. Devuelve [$detalles_calculados, $totales].
function vfCalcularDetallesFactura($detalles, $comprobante_fiscal_id) {
    $subtotal = $descuentos = $iva = $bruto = $total = 0;
    foreach ($detalles as &$d) {
        $cantidad = floatval($d['cantidad']);
        $precio = floatval($d['precio_unitario']);
        $descPct = floatval($d['descuento_general_pct'] ?? 0);
        $brutoLinea = $cantidad * $precio;
        $desc = $brutoLinea * $descPct / 100;
        $neto = $brutoLinea - $desc;
        $ivaPorcentaje = $comprobante_fiscal_id === 0 ? 0 : floatval($d['iva_porcentaje'] ?? 0);
        $ivaImporte = $neto * $ivaPorcentaje / 100;
        $linea = $neto + $ivaImporte;
        $d['iva_porcentaje'] = $ivaPorcentaje;
        $d['_bruto'] = $brutoLinea; $d['_desc'] = $desc; $d['_neto'] = $neto; $d['_iva'] = $ivaImporte; $d['_linea'] = $linea;
        $bruto += $brutoLinea; $descuentos += $desc; $subtotal += $neto; $iva += $ivaImporte; $total += $linea;
    }
    unset($d);
    // La cabecera se guarda en centavos: el total tiene que ser neto + IVA ya redondeados (sumar sin
    // redondear dejaba, p. ej., 69.610,443 + 14.618,193 -> ,44 + ,19 contra un total de ,64, y ARCA y el
    // asiento lo rechazaban). El centavo de diferencia queda en el total, o sea en la deuda del cliente.
    $bruto = round($bruto, 2);
    $subtotal = round($subtotal, 2);
    $descuentos = round($bruto - $subtotal, 2);
    $iva = round($iva, 2);
    $total = round($subtotal + $iva, 2);
    return [$detalles, ['bruto' => $bruto, 'descuentos' => $descuentos, 'subtotal' => $subtotal, 'iva' => $iva, 'total' => $total]];
}

// Inserta las líneas de detalle ya calculadas y aplica el consumo de remito correspondiente
// (misma lógica que el bloque de detalle de guardarVentaFactura()).
function vfInsertarDetallesFactura($conexion, $factura_id, $detalles) {
    $sqlDet = "INSERT INTO gestion__ventas_facturas_detalles
        (venta_factura_id, producto_id, leyenda, venta_remito_detalle_id, venta_pedido_detalle_id, cantidad,
         precio_unitario, descuento_general_pct, descuento_general, precio_unitario_neto, importe_neto,
         iva_alicuota_id, porcentaje_iva, importe_iva, importe_no_gravado, importe_exento, importe_linea, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 1)";
    $remitoIds = [];
    foreach ($detalles as $d) {
        $stmt = mysqli_prepare($conexion, $sqlDet);
        $producto = intval($d['producto_id']);
        // Leyenda del ítem: texto libre que se imprime debajo del producto (NULL = sin leyenda)
        $leyenda = mb_substr(trim((string)($d['leyenda'] ?? '')), 0, 1000);
        $leyenda = $leyenda === '' ? null : $leyenda;
        $remitoDetalle = intval($d['venta_remito_detalle_id'] ?? 0) ?: null;
        $pedidoDetalle = intval($d['venta_pedido_detalle_id'] ?? 0) ?: null;
        $cantidad = floatval($d['cantidad']); $precio = floatval($d['precio_unitario']); $descPct = floatval($d['descuento_general_pct'] ?? 0);
        $desc = $d['_desc']; $netoUnit = $d['_neto'] / max($cantidad, 0.0001); $neto = $d['_neto'];
        $ivaId = intval($d['iva_alicuota_id'] ?? 1); $ivaPct = floatval($d['iva_porcentaje'] ?? 0); $ivaLinea = $d['_iva']; $linea = $d['_linea'];
        // Venta por presentación: una línea de remito en cajas se factura de a cajas enteras
        if ($remitoDetalle) {
            $error_presentacion = vpre_validar_heredada($conexion, 'remito', $remitoDetalle, $cantidad);
            if ($error_presentacion !== null) throw new Exception($error_presentacion);
        }
        mysqli_stmt_bind_param($stmt, 'iisiiddddddiddd', $factura_id, $producto, $leyenda, $remitoDetalle, $pedidoDetalle, $cantidad, $precio, $descPct, $desc, $netoUnit, $neto, $ivaId, $ivaPct, $ivaLinea, $linea);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        // Venta por presentación: hereda la del remito (o la del pedido), o guarda la de la línea directa
        $factura_detalle_id_vpre = (int) mysqli_insert_id($conexion);
        if ($remitoDetalle) {
            vpre_heredar($conexion, 'factura', $factura_detalle_id_vpre, 'remito', $remitoDetalle, $cantidad);
        } elseif ($pedidoDetalle) {
            vpre_heredar($conexion, 'factura', $factura_detalle_id_vpre, 'pedido', $pedidoDetalle, $cantidad);
        } elseif (!empty($d['_vpre'])) {
            vpre_guardar_foto($conexion, 'factura', $factura_detalle_id_vpre, $d['_vpre']);
        }
        // Reglas de precio de venta: hereda la regla del remito (o del pedido), o graba la de la línea directa
        if ($remitoDetalle) {
            prv_heredar($conexion, 'factura', $factura_detalle_id_vpre, 'remito', $remitoDetalle);
        } elseif ($pedidoDetalle) {
            prv_heredar($conexion, 'factura', $factura_detalle_id_vpre, 'pedido', $pedidoDetalle);
        } elseif (!empty($d['_prv'])) {
            prv_guardar_linea($conexion, 'factura', $factura_detalle_id_vpre, $d['_prv'], $d['_prv']['_aprobado_por'] ?? null);
        }

        if ($remitoDetalle) {
            // "facturado" es un flag booleano (0/1), no el id de la factura — bindearlo con
            // $factura_id (como estaba antes) dejaba ese id guardado ahí en vez de 1.
            $upd = mysqli_prepare($conexion, "UPDATE gestion__ventas_remitos_detalles SET facturado = IF(cantidad_facturada + ? >= cantidad, 1, 0), cantidad_facturada = cantidad_facturada + ? WHERE venta_remito_detalle_id = ? AND cantidad_facturada + ? <= cantidad");
            mysqli_stmt_bind_param($upd, 'ddid', $cantidad, $cantidad, $remitoDetalle, $cantidad);
            if (!mysqli_stmt_execute($upd) || mysqli_stmt_affected_rows($upd) !== 1) {
                throw new Exception('La cantidad seleccionada supera el pendiente del remito.');
            }
            mysqli_stmt_close($upd);

            $stmtRem = mysqli_prepare($conexion, "SELECT venta_remito_id FROM gestion__ventas_remitos_detalles WHERE venta_remito_detalle_id = ?");
            mysqli_stmt_bind_param($stmtRem, 'i', $remitoDetalle);
            mysqli_stmt_execute($stmtRem);
            $rRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtRem));
            mysqli_stmt_close($stmtRem);
            if ($rRow) $remitoIds[intval($rRow['venta_remito_id'])] = true;
        }
    }

    foreach (array_keys($remitoIds) as $rId) {
        vfRecalcularEstadoRemito($conexion, $rId);
    }
}

/* =========================================================================
 * PERCEPCIONES DE IIBB PRACTICADAS (_local/docs/gestion/09_percepciones_iibb.md)
 * Se calculan con percepciones_iibb_core_model.php al guardar/editar el borrador y se recalculan al
 * confirmar (padrón del período de f_emision). Quedan en gestion__comprobantes_impuestos y suman
 * importe_otros_impuestos al total. Corren dentro de la transacción del llamador.
 * ========================================================================= */

// Detalle de la factura en el formato que espera perIibbCalcular().
function vfPercepcionesDetalles($conexion, $venta_factura_id) {
    $stmt = mysqli_prepare($conexion, "SELECT d.importe_neto, d.importe_iva, a.es_exento, a.es_no_gravado
                                       FROM gestion__ventas_facturas_detalles d
                                       LEFT JOIN gestion__impuestos__iva_alicuotas a ON a.iva_alicuota_id = d.iva_alicuota_id
                                       WHERE d.venta_factura_id = ? AND d.tabla_estado_registro_id <> 6");
    mysqli_stmt_bind_param($stmt, 'i', $venta_factura_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

// Recalcula y graba las percepciones de la factura, ajusta importe_otros_impuestos / importe_total y
// re-sincroniza gestion__comprobantes. Devuelve ['factura' => fila actualizada, 'avisos' => [...]].
// $estricto = false (borrador): un dato faltante (sucursal del cliente, cuenta) se avisa y no frena el guardado;
// true (confirmar): lanza Exception.
function vfPercepcionesRecalcular($conexion, $empresa_id, $venta_factura_id, $pagina_id, $estricto = false) {
    $venta_factura_id = intval($venta_factura_id);
    $stmt = mysqli_prepare($conexion, "SELECT * FROM gestion__ventas_facturas WHERE venta_factura_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$f) throw new Exception('Factura no encontrada.');

    try {
        $calc = perCalcularPracticadas($conexion, $empresa_id, $f, vfPercepcionesDetalles($conexion, $venta_factura_id));
    } catch (Exception $e) {
        if ($estricto) throw $e;
        $calc = ['percepciones' => [], 'total' => 0.0, 'avisos' => [$e->getMessage() . ' (se exige al confirmar)']];
    }
    $comprobante_id = intval($f['comprobante_id']);
    if ($comprobante_id > 0) perIibbGuardar($conexion, $comprobante_id, $calc['percepciones']);

    $otros_ant = floatval($f['importe_otros_impuestos']);
    $otros = round($calc['total'], 2);
    // Total = componentes (neto incluye exento y no gravado): también corrige el centavo de redondeo
    // de las facturas guardadas antes de que la cabecera se redondeara.
    $total = round(floatval($f['importe_neto']) + floatval($f['importe_iva']) + $otros, 2);
    if (abs($otros - $otros_ant) > 0.004 || abs($total - floatval($f['importe_total'])) > 0.004) {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET importe_otros_impuestos = ?, importe_total = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ddi', $otros, $total, $venta_factura_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        $f['importe_otros_impuestos'] = $otros;
        $f['importe_total'] = $total;
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        syncComprobante($conexion, vfDatosComprobanteFactura($f, $empresa_id, intval($f['tabla_estado_registro_id'])), $tabla_origen_id);
    }
    return ['factura' => $f, 'percepciones' => $calc['percepciones'], 'avisos' => $calc['avisos']];
}

// Ajuste de redondeo de la cabecera: si importe_total difiere en menos de $1 de neto + IVA + otros
// impuestos (neto incluye exento y no gravado), el total pasa a ser esa suma y se re-sincroniza el
// espejo en gestion__comprobantes. La diferencia queda en la deuda del cliente. Una diferencia mayor
// no se toca: la frenan los controles de ARCA / del asiento. Devuelve la fila actualizada.
function vfCuadrarTotalFactura($conexion, $empresa_id, array $f, $pagina_id) {
    $total = round(floatval($f['importe_neto']) + floatval($f['importe_iva']) + floatval($f['importe_otros_impuestos']), 2);
    $dif = round($total - floatval($f['importe_total']), 2);
    if (abs($dif) < 0.005 || abs($dif) >= 1.0) return $f;

    $id = intval($f['venta_factura_id']);
    error_log("=== vfCuadrarTotalFactura ID: $id total " . $f['importe_total'] . " -> $total ===");
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET importe_total = ? WHERE venta_factura_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'dii', $total, $id, $empresa_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    $f['importe_total'] = $total;

    if (intval($f['comprobante_id'] ?? 0) > 0) {
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        syncComprobante($conexion, vfDatosComprobanteFactura($f, $empresa_id, intval($f['tabla_estado_registro_id'])), $tabla_origen_id);
    }
    return $f;
}

// Vista previa para la pantalla (no graba nada): mismos datos que agregar/editar.
function vfPercepcionesPreview($conexion, $empresa_id, $data) {
    $tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
    $tipo = perIibbTipoComprobante($conexion, $empresa_id, $tipo_id);
    if (!$tipo || empty($data['detalles'])) return ['success' => true, 'percepciones' => [], 'total' => 0, 'avisos' => []];
    list($detalles) = vfCalcularDetallesFactura($data['detalles'], intval($tipo['comprobante_fiscal_id']));
    $ids = array_values(array_unique(array_map(function ($d) { return intval($d['iva_alicuota_id'] ?? 0); }, $detalles)));
    $flags = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = mysqli_prepare($conexion, "SELECT iva_alicuota_id, es_exento, es_no_gravado FROM gestion__impuestos__iva_alicuotas WHERE iva_alicuota_id IN ($in)");
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids)), ...$ids);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) $flags[intval($r['iva_alicuota_id'])] = $r;
        mysqli_stmt_close($stmt);
    }
    $lineas = array_map(function ($d) use ($flags) {
        $f = $flags[intval($d['iva_alicuota_id'] ?? 0)] ?? [];
        return ['importe_neto' => $d['_neto'], 'importe_iva' => $d['_iva'],
                'es_exento' => $f['es_exento'] ?? 0, 'es_no_gravado' => $f['es_no_gravado'] ?? 0];
    }, $detalles);
    $destinatario = vfResolverDestinatario($data);
    $cab = [
        'comprobante_tipo_id' => $tipo_id,
        'entidad_id' => intval($destinatario['entidad_id'] ?? 0),
        'entidad_sucursal_id' => intval($destinatario['entidad_sucursal_id'] ?? 0),
        'sucursal_id' => intval($data['sucursal_id'] ?? 0),
        'f_emision' => ($data['f_emision'] ?? null) ?: date('Y-m-d'),
        'destino_bien_uso' => intval($data['destino_bien_uso'] ?? 0),
        'comprobante_asociado_id' => intval($data['comprobante_asociado_id'] ?? 0),
    ];
    try {
        $calc = perCalcularPracticadas($conexion, $empresa_id, $cab, $lineas);
        return ['success' => true, 'percepciones' => $calc['percepciones'], 'total' => $calc['total'], 'avisos' => $calc['avisos']];
    } catch (Exception $e) {
        return ['success' => true, 'percepciones' => [], 'total' => 0, 'avisos' => [$e->getMessage()]];
    }
}

function vfGuardarDestinoBienUso($conexion, $empresa_id, $venta_factura_id, $valor) {
    $valor = intval($valor) ? 1 : 0;
    $venta_factura_id = intval($venta_factura_id);
    $empresa_id = intval($empresa_id);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET destino_bien_uso = ? WHERE venta_factura_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'iii', $valor, $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function guardarVentaFactura($conexion, $empresa_id, $pagina_id, $data) {
    $detalles = $data['detalles'] ?? [];
    $destinatario = vfResolverDestinatario($data);
    if (isset($destinatario['error'])) return ['success' => false, 'error' => $destinatario['error']];
    try {
        vcar_exigir_cliente($conexion, intval($empresa_id), intval($_SESSION['usuario_id'] ?? 0), intval($destinatario['entidad_id'] ?? 0), $data['f_emision'] ?? null);
    } catch (InvalidArgumentException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
    if (count($detalles) === 0) return ['success' => false, 'error' => 'Agregue al menos un producto.'];
    mysqli_begin_transaction($conexion);
    try {
        vcar_exigir_cliente($conexion, intval($empresa_id), intval($_SESSION['usuario_id'] ?? 0), intval($destinatario['entidad_id'] ?? 0), $data['f_emision'] ?? null);
        $cond = vfObtenerCondicionDestinatario($conexion, intval($destinatario['entidad_id']), $empresa_id);
        // Canales de venta: la sucursal tiene que vender al canal del cliente (sin el indicador, no hace nada)
        cvta_exigir_venta_sucursal($conexion, intval($empresa_id), intval($data['sucursal_id'] ?? 0), intval($destinatario['entidad_id'] ?? 0));
        // Reglas de precio de venta: regla y descuento manual de las líneas sin pedido ni remito de origen; el manual
        // no puede superar el tope del usuario (sin el indicador, nada)
        if (isset($detalles) && is_array($detalles)) {
            $detalles = prv_preparar_lineas($conexion, intval($empresa_id), [
                'entidad_id' => intval($destinatario['entidad_id'] ?? 0), 'sucursal_id' => intval($data['sucursal_id'] ?? 0),
                'fecha' => $data['f_emision'] ?? null, 'usuario_id' => intval($_SESSION['usuario_id'] ?? 0),
            ], $detalles, 'factura', true);
        }
        // Consumidor final: siempre contado (no tiene cuenta corriente), sin importar lo que mande el navegador.
        $condicion_pago_id = $destinatario['entidad_id']
            ? intval(($data['condicion_pago_id'] ?? null) ?: ($cond['condicion_pago_id'] ?? 0))
            : intval($cond['condicion_pago_id'] ?? 0);
        $stmt_tipo = mysqli_prepare($conexion, "SELECT comprobante_fiscal_id, letra FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? AND empresa_id IN (0, ?) LIMIT 1");
        $tipo_id = intval($data['comprobante_tipo_id']);
        mysqli_stmt_bind_param($stmt_tipo, 'ii', $tipo_id, $empresa_id);
        mysqli_stmt_execute($stmt_tipo);
        $tipo_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tipo));
        mysqli_stmt_close($stmt_tipo);
        if (!$tipo_row) return ['success' => false, 'error' => 'Tipo de comprobante inválido.'];
        $errTipoRaiz = vfValidarTipoRaizDesdeRemito($conexion, $empresa_id, $tipo_id, $detalles);
        if ($errTipoRaiz) { mysqli_rollback($conexion); return ['success' => false, 'error' => $errTipoRaiz]; }
        $comprobante_fiscal_id = intval($tipo_row['comprobante_fiscal_id'] ?? 0);
        $punto_venta_id = intval($data['punto_venta_id']);

        // Validación fiscal: si el punto de venta tiene tipos con comprobante_fiscal_id > 0
        $tipos_pv_todos = vfTiposPorPuntoVenta($conexion, $empresa_id, $punto_venta_id, $pagina_id);
        $tiene_fiscales = false;
        foreach ($tipos_pv_todos as $tp) {
            if (intval($tp['comprobante_fiscal_id'] ?? 0) > 0) {
                $tiene_fiscales = true;
                break;
            }
        }
        if ($tiene_fiscales) {
            $entidad_id_val = $destinatario['entidad_id'] ?: 'CF';
            $relFiscal = vfObtenerRelacionFiscal($conexion, $empresa_id, $entidad_id_val);
            $letraEsperada = $relFiscal['relacion']['letra'] ?? null;
            $tipoLetra = strtoupper($tipo_row['letra'] ?? '');
            if ($comprobante_fiscal_id === 0 || ($letraEsperada && $tipoLetra !== strtoupper($letraEsperada))) {
                return [
                    'success' => false,
                    'error' => "El tipo de comprobante no corresponde a la condición fiscal del cliente (" . ($relFiscal['receptor_condicion_fiscal_nombre'] ?? '') . "). Se requiere comprobante tipo " . ($letraEsperada ?: '') . "."
                ];
            }
        }
        $estado = vfEstadoInicial($conexion, $pagina_id);
        $f_emision = ($data['f_emision'] ?? null) ?: date('Y-m-d');
        $f_vto = ($data['f_vto'] ?? null) ?: null;
        $punto_venta_id = intval($data['punto_venta_id']);
        // La numeración fiscal NO se asigna acá: se asigna recién cuando la factura sale de
        // Borrador (acción 'confirma', ver vfEjecutarTransicionEstado), igual que en
        // ventas_pedidos. Si se numerara al alta, cada borrador descartado dejaría un
        // hueco en la numeración correlativa.
        $comprobante_nro = 0;
        $tipo_cambio = floatval(($data['tipo_cambio'] ?? null) ?: 1);

        // Venta por presentación: líneas sin remito en cajas -> unidad base (sin el indicador, no hace nada)
        $detalles = vpre_preparar_lineas($conexion, intval($empresa_id),
            (!empty($destinatario['entidad_id']) && vpre_activo($conexion, intval($empresa_id), 'factura')) ? lpre_lista_cliente($conexion, intval($empresa_id), intval($destinatario['entidad_id'])) : 0,
            $detalles, 'factura');
        list($detalles, $tot) = vfCalcularDetallesFactura($detalles, $comprobante_fiscal_id);
        $bruto = $tot['bruto']; $descuentos = $tot['descuentos']; $subtotal = $tot['subtotal']; $iva = $tot['iva']; $total = $tot['total'];

        $sql = "INSERT INTO gestion__ventas_facturas
                (empresa_id, sucursal_id, comprobante_tipo_id, punto_venta_id, comprobante_nro,
                 comprobante_id, entidad_id, entidad_sucursal_id, consumidor_final_nombre, condicion_pago_id, f_emision,
                 f_contabilidad, f_vto, moneda_id, tipo_cambio, importe_bruto, descuento_general_pct,
                 descuento_general, importe_neto, importe_exento, importe_no_gravado, importe_iva,
                 importe_otros_impuestos, importe_total, observaciones, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        $sucursal = intval($data['sucursal_id']); $tipo = $tipo_id; $pv = $punto_venta_id;
        $nro = $comprobante_nro; $entidad = $destinatario['entidad_id']; $entidadSucursal = $destinatario['entidad_sucursal_id'];
        $consumidorFinal = $destinatario['consumidor_final_nombre'];
        $moneda = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0); $pct = floatval(($data['descuento_general_pct'] ?? null) ?: 0); $obs = trim($data['observaciones'] ?? '');
        mysqli_stmt_bind_param($stmt, 'iiiiiiisisssidddddddsi', $empresa_id, $sucursal, $tipo, $pv, $nro, $entidad, $entidadSucursal, $consumidorFinal, $condicion_pago_id, $f_emision, $f_emision, $f_vto, $moneda, $tipo_cambio, $bruto, $pct, $descuentos, $subtotal, $iva, $total, $obs, $estado);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        $factura_id = mysqli_insert_id($conexion); mysqli_stmt_close($stmt);

        // Sincronizar con gestion__comprobantes (mismo patrón que ventas_pedidos/facturas_proveedores):
        // alimenta cuenta corriente y contabilidad. comprobante_nro va en 0 hasta que se confirme.
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        $comprobante_data = [
            'empresa_id' => $empresa_id, 'sucursal_id' => $sucursal, 'comprobante_pv' => $pv,
            'comprobante_tipo_id' => $tipo, 'comprobante_nro' => $nro,
            'entidad_id' => $entidad, 'entidad_sucursal_id' => $entidadSucursal,
            'f_emision' => $f_emision, 'f_contabilidad' => $f_emision, 'f_vto' => $f_vto,
            'moneda_id' => $moneda, 'tipo_cambio' => $tipo_cambio,
            'registro_origen_id' => $factura_id, 'tabla_estado_registro_id' => $estado,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0, 'observaciones' => $obs,
            'importe_neto' => $subtotal, 'descuento_general' => $descuentos,
            'importe_no_gravado' => 0, 'importe_exento' => 0,
            'importe_iva' => $iva, 'importe_otros_impuestos' => 0, 'importe_total' => $total
        ];
        $comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) throw new Exception('No se pudo sincronizar el comprobante.');
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_id = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $factura_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        vfInsertarDetallesFactura($conexion, $factura_id, $detalles);
        vfGuardarComprobanteAsociado($conexion, $empresa_id, $factura_id, $tipo_id, intval($destinatario['entidad_id'] ?? 0),
                                     intval($data['comprobante_asociado_id'] ?? 0));
        vfGuardarDestinoBienUso($conexion, $empresa_id, $factura_id, $data['destino_bien_uso'] ?? 0);
        $perc = vfPercepcionesRecalcular($conexion, $empresa_id, $factura_id, $pagina_id);

        mysqli_commit($conexion);
        return ['success' => true, 'venta_factura_id' => $factura_id,
                'message' => 'Factura guardada correctamente.' . ($perc['avisos'] ? ' ' . implode(' ', $perc['avisos']) : '')];
    } catch (Exception $e) { mysqli_rollback($conexion); return ['success' => false, 'error' => $e->getMessage()]; }
}

function vfEditarVentaFactura($conexion, $id, $empresa_id, $pagina_id, $data) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__ventas_facturas WHERE venta_factura_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$factura) return ['success' => false, 'error' => 'Factura no encontrada.'];
    try {
        vcar_exigir_cliente($conexion, intval($empresa_id), intval($_SESSION['usuario_id'] ?? 0), intval($factura['entidad_id'] ?? 0));
    } catch (InvalidArgumentException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    $estado_actual = intval($factura['tabla_estado_registro_id']);
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = ? AND accion_js = 'editar' AND tabla_estado_registro_origen_id = ? AND tabla_estado_registro_id = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $pagina_id, $estado_actual);
    mysqli_stmt_execute($stmt);
    $permitido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$permitido) return ['success' => false, 'error' => 'La factura no se puede editar en su estado actual.'];

    $detalles = $data['detalles'] ?? [];
    $destinatario = vfResolverDestinatario($data);
    if (isset($destinatario['error'])) return ['success' => false, 'error' => $destinatario['error']];
    if (count($detalles) === 0) {
        return ['success' => false, 'error' => 'Agregue al menos un producto.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // Libera lo que esta factura tenía consumido de los remitos antes de recalcular,
        // así el detalle nuevo (que puede repetir las mismas líneas con otra cantidad) no
        // choca contra su propio consumo anterior.
        vfLiberarRemitosDeFactura($conexion, $id);

        $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__ventas_facturas_detalles WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $cond = vfObtenerCondicionDestinatario($conexion, intval($destinatario['entidad_id']), $empresa_id);
        // Canales de venta: la sucursal tiene que vender al canal del cliente (sin el indicador, no hace nada)
        cvta_exigir_venta_sucursal($conexion, intval($empresa_id), intval($data['sucursal_id'] ?? 0), intval($destinatario['entidad_id'] ?? 0));
        // Reglas de precio de venta: regla y descuento manual de las líneas sin pedido ni remito de origen; el manual
        // no puede superar el tope del usuario (sin el indicador, nada)
        if (isset($detalles) && is_array($detalles)) {
            $detalles = prv_preparar_lineas($conexion, intval($empresa_id), [
                'entidad_id' => intval($destinatario['entidad_id'] ?? 0), 'sucursal_id' => intval($data['sucursal_id'] ?? 0),
                'fecha' => $data['f_emision'] ?? null, 'usuario_id' => intval($_SESSION['usuario_id'] ?? 0),
            ], $detalles, 'factura', true);
        }
        $condicion_pago_id = $destinatario['entidad_id']
            ? intval(($data['condicion_pago_id'] ?? null) ?: ($cond['condicion_pago_id'] ?? 0))
            : intval($cond['condicion_pago_id'] ?? 0);
        $tipo_id = intval($data['comprobante_tipo_id']);
        $stmt_tipo = mysqli_prepare($conexion, "SELECT comprobante_fiscal_id, letra FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? AND empresa_id IN (0, ?) LIMIT 1");
        mysqli_stmt_bind_param($stmt_tipo, 'ii', $tipo_id, $empresa_id);
        mysqli_stmt_execute($stmt_tipo);
        $tipo_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tipo));
        mysqli_stmt_close($stmt_tipo);
        if (!$tipo_row) { mysqli_rollback($conexion); return ['success' => false, 'error' => 'Tipo de comprobante inválido.']; }
        $errTipoRaiz = vfValidarTipoRaizDesdeRemito($conexion, $empresa_id, $tipo_id, $detalles);
        if ($errTipoRaiz) { mysqli_rollback($conexion); return ['success' => false, 'error' => $errTipoRaiz]; }
        $comprobante_fiscal_id = intval($tipo_row['comprobante_fiscal_id'] ?? 0);
        $punto_venta_id = intval($data['punto_venta_id']);

        // Validación fiscal: si el punto de venta tiene tipos con comprobante_fiscal_id > 0
        $tipos_pv_todos = vfTiposPorPuntoVenta($conexion, $empresa_id, $punto_venta_id, $pagina_id);
        $tiene_fiscales = false;
        foreach ($tipos_pv_todos as $tp) {
            if (intval($tp['comprobante_fiscal_id'] ?? 0) > 0) {
                $tiene_fiscales = true;
                break;
            }
        }
        if ($tiene_fiscales) {
            $entidad_id_val = $destinatario['entidad_id'] ?: 'CF';
            $relFiscal = vfObtenerRelacionFiscal($conexion, $empresa_id, $entidad_id_val);
            $letraEsperada = $relFiscal['relacion']['letra'] ?? null;
            $tipoLetra = strtoupper($tipo_row['letra'] ?? '');
            if ($comprobante_fiscal_id === 0 || ($letraEsperada && $tipoLetra !== strtoupper($letraEsperada))) {
                mysqli_rollback($conexion);
                return [
                    'success' => false,
                    'error' => "El tipo de comprobante no corresponde a la condición fiscal del cliente (" . ($relFiscal['receptor_condicion_fiscal_nombre'] ?? '') . "). Se requiere comprobante tipo " . ($letraEsperada ?: '') . "."
                ];
            }
        }

        $f_emision = ($data['f_emision'] ?? null) ?: date('Y-m-d');
        $f_vto = ($data['f_vto'] ?? null) ?: null;
        $punto_venta_id = intval($data['punto_venta_id']);
        $tipo_cambio = floatval(($data['tipo_cambio'] ?? null) ?: 1);

        // Venta por presentación: líneas sin remito en cajas -> unidad base (sin el indicador, no hace nada)
        $detalles = vpre_preparar_lineas($conexion, intval($empresa_id),
            (!empty($destinatario['entidad_id']) && vpre_activo($conexion, intval($empresa_id), 'factura')) ? lpre_lista_cliente($conexion, intval($empresa_id), intval($destinatario['entidad_id'])) : 0,
            $detalles, 'factura');
        list($detalles, $tot) = vfCalcularDetallesFactura($detalles, $comprobante_fiscal_id);
        $bruto = $tot['bruto']; $descuentos = $tot['descuentos']; $subtotal = $tot['subtotal']; $iva = $tot['iva']; $total = $tot['total'];

        $sucursal = intval($data['sucursal_id']); $entidad = $destinatario['entidad_id']; $entidadSucursal = $destinatario['entidad_sucursal_id'];
        $consumidorFinal = $destinatario['consumidor_final_nombre'];
        $moneda = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0); $pct = floatval(($data['descuento_general_pct'] ?? null) ?: 0); $obs = trim($data['observaciones'] ?? '');

        $sql = "UPDATE gestion__ventas_facturas SET
                    sucursal_id = ?, comprobante_tipo_id = ?, punto_venta_id = ?, entidad_id = ?, entidad_sucursal_id = ?,
                    consumidor_final_nombre = ?,
                    condicion_pago_id = ?, f_emision = ?, f_contabilidad = ?, f_vto = ?, moneda_id = ?, tipo_cambio = ?,
                    importe_bruto = ?, descuento_general_pct = ?, descuento_general = ?, importe_neto = ?, importe_iva = ?,
                    importe_total = ?, observaciones = ?
                WHERE venta_factura_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param(
            $stmt, 'iiiiisisssidddddddsii',
            $sucursal, $tipo_id, $punto_venta_id, $entidad, $entidadSucursal,
            $consumidorFinal, $condicion_pago_id, $f_emision, $f_emision, $f_vto, $moneda, $tipo_cambio,
            $bruto, $pct, $descuentos, $subtotal, $iva,
            $total, $obs, $id, $empresa_id
        );
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        vfInsertarDetallesFactura($conexion, $id, $detalles);

        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        $comprobante_data = [
            'empresa_id' => $empresa_id, 'sucursal_id' => $sucursal, 'comprobante_pv' => $punto_venta_id,
            'comprobante_tipo_id' => $tipo_id, 'comprobante_nro' => intval($factura['comprobante_nro']),
            'entidad_id' => $entidad, 'entidad_sucursal_id' => $entidadSucursal,
            'f_emision' => $f_emision, 'f_contabilidad' => $f_emision, 'f_vto' => $f_vto,
            'moneda_id' => $moneda, 'tipo_cambio' => $tipo_cambio,
            'registro_origen_id' => $id, 'tabla_estado_registro_id' => $estado_actual,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0, 'observaciones' => $obs,
            'importe_neto' => $subtotal, 'descuento_general' => $descuentos,
            'importe_no_gravado' => 0, 'importe_exento' => 0,
            'importe_iva' => $iva, 'importe_otros_impuestos' => 0, 'importe_total' => $total
        ];
        syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        vfGuardarComprobanteAsociado($conexion, $empresa_id, $id, $tipo_id, intval($destinatario['entidad_id'] ?? 0),
                                     intval($data['comprobante_asociado_id'] ?? 0));
        vfGuardarDestinoBienUso($conexion, $empresa_id, $id, $data['destino_bien_uso'] ?? 0);
        // El UPDATE de arriba recalculó el total sin percepciones: se vuelven a sumar sobre ese total
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET importe_otros_impuestos = 0 WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $perc = vfPercepcionesRecalcular($conexion, $empresa_id, $id, $pagina_id);

        mysqli_commit($conexion);
        return ['success' => true, 'venta_factura_id' => $id,
                'message' => 'Factura actualizada correctamente.' . ($perc['avisos'] ? ' ' . implode(' ', $perc['avisos']) : '')];
    } catch (Exception $e) { mysqli_rollback($conexion); return ['success' => false, 'error' => $e->getMessage()]; }
}

function vfValidarComprobantesAnterioresConCae($conexion, $empresa_id, $punto_venta_id, $comprobante_tipo_id, $venta_factura_id, $comprobante_nro) {
    $sql = "SELECT venta_factura_id, comprobante_nro, f_emision
            FROM gestion__ventas_facturas
            WHERE empresa_id = ?
              AND punto_venta_id = ?
              AND comprobante_tipo_id = ?
              AND tabla_estado_registro_id <> 6
              AND (CAE IS NULL OR TRIM(CAE) = '')
              AND venta_factura_id <> ?
              AND (
                  (? > 0 AND comprobante_nro > 0 AND comprobante_nro < ?)
                  OR (? <= 0 AND venta_factura_id < ?)
                  OR (comprobante_nro = 0 AND venta_factura_id < ?)
              )
            ORDER BY comprobante_nro ASC, venta_factura_id ASC
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return;

    $nro_check = intval($comprobante_nro);
    $id_check = intval($venta_factura_id);

    mysqli_stmt_bind_param($stmt, 'iiiiiiiii', 
        $empresa_id, $punto_venta_id, $comprobante_tipo_id, $id_check,
        $nro_check, $nro_check,
        $nro_check, $id_check,
        $id_check
    );
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $prev = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($prev) {
        $nro_str = intval($prev['comprobante_nro']) > 0 
            ? "N° " . emp_numero_comprobante($conexion, $empresa_id, $punto_venta_id, $prev['comprobante_nro'])
            : "ID #" . $prev['venta_factura_id'];
        throw new Exception("No se puede confirmar este comprobante porque existe un comprobante anterior ($nro_str) que no tiene CAE asignado. Debe autorizar/confirmar los comprobantes anteriores primero.");
    }
}

// $medios_contado: medios de pago del cobro de contado a consumidor final (acción 'aprobada'); null = efectivo.
function vfEjecutarTransicionEstado($conexion, $venta_factura_id, $accion_js, $empresa_id, $pagina_id, $medios_contado = null) {
    $venta_factura_id = intval($venta_factura_id);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT * FROM gestion__ventas_facturas WHERE venta_factura_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$factura) return ['success' => false, 'error' => 'Factura no encontrada.'];
    try {
        vcar_exigir_cliente($conexion, intval($empresa_id), intval($_SESSION['usuario_id'] ?? 0), intval($factura['entidad_id'] ?? 0));
    } catch (InvalidArgumentException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    $estado_actual_id = intval($factura['tabla_estado_registro_id']);

    $sql_funcion = "SELECT * FROM conf__paginas_funciones
                    WHERE pagina_id = ? AND tabla_estado_registro_origen_id = ? AND accion_js = ? AND tabla_estado_registro_id = 1
                    LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    mysqli_stmt_bind_param($stmt, 'iis', $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $funcion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$funcion) return ['success' => false, 'error' => 'Acción no permitida para el estado actual.'];

    $estado_destino_id = intval($funcion['tabla_estado_registro_destino_id']);
    if ($estado_destino_id === $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    // Períodos fiscales cerrados (Tesorería > Cierres Fiscales): sacar la factura de los libros se controla acá;
    // entrarla, antes de pedir el CAE o ya con las percepciones calculadas (más abajo).
    $pf_computa_antes = in_array($estado_actual_id, PF_ESTADOS_VENTAS, true);
    $pf_computa_despues = in_array($estado_destino_id, PF_ESTADOS_VENTAS, true);
    try {
        if ($pf_computa_antes && !$pf_computa_despues) {
            pfExigirDocumentoAbierto($conexion, $empresa_id, 'VENTA', $venta_factura_id, 'anular ni revertir el comprobante');
            cont_periodo_exigir_comprobante($conexion, $empresa_id, intval($factura['comprobante_id'] ?? 0), 'anular ni revertir el comprobante');
        }
        if (!$pf_computa_antes && $pf_computa_despues && $accion_js === 'aprobada') {
            pfExigirDocumentoAbierto($conexion, $empresa_id, 'VENTA', $venta_factura_id, 'aprobar el comprobante');
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    // Medios del cobro de contado: se validan antes de pedir el CAE, para no autorizar en ARCA
    // una factura cuyo cobro después falla por un dato mal cargado.
    if ($medios_contado !== null && $accion_js === 'aprobada' && empty($factura['entidad_id'])) {
        $val = vfValidarMediosCobro($conexion, $empresa_id, $medios_contado, true);
        if (isset($val['error'])) return ['success' => false, 'error' => $val['error']];
        if (abs($val['total'] - round(floatval($factura['importe_total']), 2)) > VF_CONTADO_TOLERANCIA) {
            return ['success' => false, 'error' => 'Los medios de pago ($' . fmt_numero($val['total'], 2) . ') tienen que sumar el total de la factura ($' . fmt_numero(floatval($factura['importe_total']), 2) . ').'];
        }
    }

    // Comprobante fiscal (A/B...) con ARCA habilitado en Parámetros de Empresa: no queda Confirmado
    // hasta tener CAE, y el número lo da ARCA. Sin ARCA habilitado sigue el circuito manual.
    $tipo_fiscal = vfComprobanteFiscalArca($conexion, intval($factura['comprobante_tipo_id']), $empresa_id);
    $fiscal = ($tipo_fiscal && vfArcaHabilitado($conexion, $empresa_id)) ? $tipo_fiscal : null;
    if ($fiscal && $accion_js === 'aprobada') {
        $res_arca = vfArcaAutorizarFactura($conexion, $factura, $fiscal, $empresa_id, $pagina_id);
        if (!empty($res_arca['success'])) {
            mysqli_begin_transaction($conexion);
            try {
                $post = vfPostConfirmacion($conexion, $venta_factura_id, $empresa_id, intval($_SESSION['usuario_id'] ?? 0), $medios_contado);
                mysqli_commit($conexion);
                if (!empty($post['mensajes'])) $res_arca['message'] = ($res_arca['message'] ?? '') . ' ' . implode(' ', $post['mensajes']);
            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $res_arca['message'] = ($res_arca['message'] ?? '') . ' ATENCIÓN: autorizada, pero falló el cobro/aplicación automática: ' . $e->getMessage();
            }
            $res_arca['notas_credito_disponibles'] = vfNotasCreditoDisponiblesFactura($conexion, $venta_factura_id, $empresa_id);
        }
        return $res_arca;
    }
    if ($accion_js === 'borrador') {
        return vfVolverABorrador($conexion, $factura, $tipo_fiscal, $fiscal !== null, $estado_destino_id, $empresa_id, $pagina_id);
    }

    mysqli_begin_transaction($conexion);
    try {
        if ($accion_js === 'eliminar' || $accion_js === 'anular' || $estado_destino_id === 6) {
            if (intval($factura['comprobante_id']) > 0) perIibbAnularDelComprobante($conexion, intval($factura['comprobante_id']));
            vfLiberarRemitosDeFactura($conexion, $venta_factura_id);
            $stmt_det_del = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas_detalles SET tabla_estado_registro_id = 6 WHERE venta_factura_id = ?");
            mysqli_stmt_bind_param($stmt_det_del, 'i', $venta_factura_id);
            mysqli_stmt_execute($stmt_det_del);
            mysqli_stmt_close($stmt_det_del);
        }

        $numero_asignado = null;
        if ($accion_js === 'confirma' || $accion_js === 'aprobada') {
            if (empty($factura['comprobante_nro']) || intval($factura['comprobante_nro']) <= 0) {
                if (empty($factura['punto_venta_id']) || empty($factura['comprobante_tipo_id'])) {
                    throw new Exception('La factura no tiene punto de venta o tipo de comprobante asignado.');
                }
                $numero_asignado = vfObtenerProximoNumero($conexion, $empresa_id, intval($factura['punto_venta_id']), intval($factura['comprobante_tipo_id']));
                $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_nro = ? WHERE venta_factura_id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $numero_asignado, $venta_factura_id);
                if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                $factura['comprobante_nro'] = $numero_asignado;
            } else {
                $numero_asignado = intval($factura['comprobante_nro']);
            }

            // Centavo de redondeo entre el total y sus componentes: se ajusta el total antes de validar
            $factura = vfCuadrarTotalFactura($conexion, $empresa_id, $factura, $pagina_id);

            if ($fiscal) {
                $error_arca = vfArcaDatosFactura($conexion, $factura, $fiscal, $empresa_id)['error'];
                if ($error_arca !== null) throw new Exception($error_arca);
            }

            // Correlatividad de CAE: solo si el comprobante se autoriza en ARCA (tipo fiscal con ARCA habilitado).
            // Sin ARCA, o en comprobantes no fiscales (X), nunca hay CAE y bloquearía todo lo posterior.
            if ($fiscal) {
                vfValidarComprobantesAnterioresConCae(
                    $conexion,
                    $empresa_id,
                    intval($factura['punto_venta_id']),
                    intval($factura['comprobante_tipo_id']),
                    $venta_factura_id,
                    $numero_asignado
                );
            }

            vfActualizarEstadoRemitosDeFactura($conexion, $venta_factura_id);
        }

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET tabla_estado_registro_id = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $estado_destino_id, $venta_factura_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        // Percepciones IIBB: se recalculan con el padrón vigente al confirmar (la fiscal con ARCA ya no se
        // recalcula al aprobar: a ARCA va lo que quedó acá).
        $avisos_percepcion = [];
        if ($accion_js === 'confirma' || $accion_js === 'aprobada') {
            $perc = vfPercepcionesRecalcular($conexion, $empresa_id, $venta_factura_id, $pagina_id, true);
            $factura['importe_otros_impuestos'] = $perc['factura']['importe_otros_impuestos'];
            $factura['importe_total'] = $perc['factura']['importe_total'];
            $avisos_percepcion = $perc['avisos'];
        }

        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');

        $comprobante_data = [
            'empresa_id' => $empresa_id, 'sucursal_id' => $factura['sucursal_id'], 'comprobante_pv' => $factura['punto_venta_id'],
            'comprobante_tipo_id' => $factura['comprobante_tipo_id'], 'comprobante_nro' => $factura['comprobante_nro'],
            'entidad_id' => $factura['entidad_id'], 'entidad_sucursal_id' => $factura['entidad_sucursal_id'],
            'f_emision' => $factura['f_emision'], 'f_contabilidad' => $factura['f_contabilidad'], 'f_vto' => $factura['f_vto'],
            'moneda_id' => $factura['moneda_id'], 'tipo_cambio' => $factura['tipo_cambio'],
            'registro_origen_id' => $venta_factura_id, 'tabla_estado_registro_id' => $estado_destino_id,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0, 'observaciones' => $factura['observaciones'],
            'importe_neto' => floatval($factura['importe_neto']), 'descuento_general' => floatval($factura['descuento_general']),
            'importe_no_gravado' => floatval($factura['importe_no_gravado']), 'importe_exento' => floatval($factura['importe_exento']),
            'importe_iva' => floatval($factura['importe_iva']), 'importe_otros_impuestos' => floatval($factura['importe_otros_impuestos']),
            'importe_total' => floatval($factura['importe_total'])
        ];
        $comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) throw new Exception('No se pudo sincronizar el comprobante.');
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_id = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $venta_factura_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        vfSincronizarStockFactura($conexion, $factura, $comprobante_id, $estado_destino_id, $tabla_origen_id, $empresa_id);

        // Generar asiento contable automático al confirmar la factura (la fiscal, recién con el CAE)
        if ($accion_js === 'confirma' && !$fiscal) {
            $res_asiento = vfGenerarAsientoContableFactura($conexion, $venta_factura_id, $empresa_id, true);
            if (!$res_asiento['success']) {
                throw new Exception('Error generando asiento contable: ' . ($res_asiento['error'] ?? $res_asiento['message'] ?? 'Error desconocido'));
            }
        }

        if (!$pf_computa_antes && ($pf_computa_despues || $estado_destino_id === 4)) {
            pfExigirDocumentoAbierto($conexion, $empresa_id, 'VENTA', $venta_factura_id, 'confirmar el comprobante');
        }

        // Confirmada: aplicación de la NC a su comprobante asociado / cobro de contado a consumidor final
        $post = ['mensajes' => []];
        if ($estado_destino_id === 5) {
            $post = vfPostConfirmacion($conexion, $venta_factura_id, $empresa_id, intval($_SESSION['usuario_id'] ?? 0), $medios_contado);
        }

        mysqli_commit($conexion);
        $mensaje = 'Estado actualizado correctamente';
        if ($numero_asignado) $mensaje .= ' - Número asignado: ' . $numero_asignado;
        if ($accion_js === 'confirma' && $fiscal) $mensaje .= '. Queda pendiente de ARCA: al aprobarla se solicita el CAE y se numera.';
        if (!empty($post['mensajes'])) $mensaje .= '. ' . implode(' ', $post['mensajes']);
        if ($avisos_percepcion) $mensaje .= '. ' . implode(' ', $avisos_percepcion);
        return ['success' => true, 'message' => $mensaje, 'comprobante_nro' => $numero_asignado,
                'notas_credito_disponibles' => $estado_destino_id === 5 ? vfNotasCreditoDisponiblesFactura($conexion, $venta_factura_id, $empresa_id) : null];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log('ERROR en vfEjecutarTransicionEstado: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Lleva el libro de stock al estado de la factura/NC (ver stock_core_model.php): el tipo dice si mueve
// y en qué sentido, el estado si ya está aplicado. La boca es la del punto de venta. Una línea que viene
// de un remito solo mueve lo que ese remito no movió. Corre dentro de la transacción del llamador.
function vfSincronizarStockFactura($conexion, $factura, $comprobante_id, $estado_id, $tabla_origen_id, $empresa_id) {
    require_once __DIR__ . '/stock_core_model.php';
    $venta_factura_id = intval($factura['venta_factura_id']);
    $punto_venta_id = intval($factura['punto_venta_id']);

    $stmt = mysqli_prepare($conexion, "SELECT boca_id FROM gestion__puntos_venta WHERE punto_venta_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $punto_venta_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $pv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $tabla_remitos_id = stockTablaIdPorNombre($conexion, 'gestion__ventas_remitos');
    $stmt = mysqli_prepare($conexion, "SELECT venta_factura_detalle_id, producto_id, cantidad, venta_remito_detalle_id
                                       FROM gestion__ventas_facturas_detalles
                                       WHERE venta_factura_id = ? AND tabla_estado_registro_id <> 6");
    mysqli_stmt_bind_param($stmt, 'i', $venta_factura_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lineas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lineas[] = [
            'registro_detalle_id' => intval($row['venta_factura_detalle_id']),
            'producto_id' => intval($row['producto_id']),
            'cantidad' => floatval($row['cantidad']),
            'antecesor_tabla_id' => $tabla_remitos_id,
            'antecesor_detalle_id' => intval($row['venta_remito_detalle_id'] ?? 0),
        ];
    }
    mysqli_stmt_close($stmt);

    return stockSincronizarComprobante($conexion, [
        'empresa_id' => intval($empresa_id),
        'comprobante_id' => intval($comprobante_id),
        'comprobante_tipo_id' => intval($factura['comprobante_tipo_id']),
        'tabla_origen_id' => intval($tabla_origen_id),
        'registro_origen_id' => $venta_factura_id,
        'estado_registro_id' => intval($estado_id),
        'boca_id' => intval($pv['boca_id'] ?? 0),
        'usuario_id' => $_SESSION['usuario_id'] ?? null,
        'observaciones' => 'Factura N° ' . $punto_venta_id . '-' . intval($factura['comprobante_nro']),
    ], $lineas);
}

function vfGenerarAsientoContableFactura($conexion, $venta_factura_id, $empresa_id, $en_transaccion = false) {
    $venta_factura_id = intval($venta_factura_id);
    $empresa_id = intval($empresa_id);

    try {
        // 1. Obtener cabecera de la factura
        $sql = "SELECT f.*,
                       ct.comprobante_tipo, ct.letra, ct.signo AS tipo_signo,
                       e.entidad_nombre, " . ent_sql_cuenta_rol('e.entidad_id', 'CLI') . " AS cont_cuenta_id_cliente,
                       pv.nombre AS pv_nombre
                FROM gestion__ventas_facturas f
                LEFT JOIN gestion__comprobantes_tipos ct ON f.comprobante_tipo_id = ct.comprobante_tipo_id
                LEFT JOIN gestion__entidades e ON f.entidad_id = e.entidad_id
                LEFT JOIN gestion__puntos_venta pv ON f.punto_venta_id = pv.punto_venta_id
                WHERE f.venta_factura_id = ? AND f.empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) throw new Exception('Error preparando consulta de factura: ' . mysqli_error($conexion));
        mysqli_stmt_bind_param($stmt, "ii", $venta_factura_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$factura) {
            return ['success' => false, 'error' => 'Factura no encontrada.'];
        }

        $comprobante_id = intval($factura['comprobante_id'] ?? 0);
        if ($comprobante_id <= 0) {
            return ['success' => false, 'error' => 'La factura no tiene comprobante fiscal sincronizado.'];
        }

        // 2. Armar descripción del asiento
        $comprobante_tipo = $factura['comprobante_tipo'] ?? 'Factura';
        $pv = str_pad(emp_pv_numero($conexion, $empresa_id, $factura['punto_venta_id'] ?? 0), 4, '0', STR_PAD_LEFT);
        $nro = str_pad(intval($factura['comprobante_nro'] ?? 0), 8, '0', STR_PAD_LEFT);
        $cliente = !empty($factura['entidad_id'])
            ? trim($factura['entidad_nombre'] ?? 'Cliente')
            : trim('Consumidor Final ' . ($factura['consumidor_final_nombre'] ?? ''));
        $fecha_emision = date('d/m/Y', strtotime($factura['f_emision']));
        $descripcion = "$comprobante_tipo $pv-$nro - $cliente - $fecha_emision";

        // 3. Cuenta deudora del cliente (DEBE)
        $cuenta_cliente_id = intval($factura['cont_cuenta_id_cliente'] ?? 0);
        if ($cuenta_cliente_id <= 0) {
            // Sin cuenta propia del cliente: Deudores por Ventas de la empresa (concepto DEUDORES_VENTAS o parámetros contables)
            $cuenta_cliente_id = emp_cuenta_id($conexion, $empresa_id, 'DEUDORES_VENTAS', 'cuenta_deudores_ventas_id');
        }

        // 4. Obtener detalle de productos
        $sql_detalles = "SELECT d.*, 
                                p.producto_codigo, p.producto_nombre, p.cont_cuenta_venta_id,
                                pt.cont_cuenta_venta_default_id,
                                pc.cont_cuenta_ingreso_id,
                                iva.cont_cuenta_venta_id AS iva_cont_cuenta_venta_id
                         FROM gestion__ventas_facturas_detalles d
                         LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
                         LEFT JOIN gestion__productos_tipos pt ON p.producto_tipo_id = pt.producto_tipo_id
                         LEFT JOIN gestion__productos_categorias pc ON p.producto_categoria_id = pc.producto_categoria_id
                         LEFT JOIN gestion__impuestos__iva_alicuotas iva ON d.iva_alicuota_id = iva.iva_alicuota_id
                         WHERE d.venta_factura_id = ?
                         ORDER BY d.venta_factura_detalle_id";
        $stmt = mysqli_prepare($conexion, $sql_detalles);
        if (!$stmt) throw new Exception('Error preparando consulta de detalles: ' . mysqli_error($conexion));
        mysqli_stmt_bind_param($stmt, "i", $venta_factura_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $detalles = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $detalles[] = $r;
        }
        mysqli_stmt_close($stmt);

        if (empty($detalles)) {
            return ['success' => false, 'error' => 'La factura no posee líneas de detalle.'];
        }

        $movimientos = [];
        $total_debe = 0.0;
        $total_haber = 0.0;

        // Movimiento 1: CLIENTE (DEBE +)
        $total_factura = floatval($factura['importe_total'] ?? 0);
        $movimientos[] = [
            'cuenta_id' => $cuenta_cliente_id,
            'importe' => $total_factura,
            'descripcion' => 'Cliente: ' . $cliente
        ];
        $total_debe += $total_factura;

        // Movimiento 2: VENTAS POR PRODUCTO (HABER -)
        $iva_agrupado = [];
        foreach ($detalles as $d) {
            $cuenta_venta_id = intval($d['cont_cuenta_venta_id'] ?? 0);
            if ($cuenta_venta_id <= 0) $cuenta_venta_id = intval($d['cont_cuenta_venta_default_id'] ?? 0);
            if ($cuenta_venta_id <= 0) $cuenta_venta_id = intval($d['cont_cuenta_ingreso_id'] ?? 0);
            if ($cuenta_venta_id <= 0) $cuenta_venta_id = emp_cuenta_id($conexion, $empresa_id, 'VENTAS', 'cuenta_ventas_defecto_id');

            $neto = floatval($d['importe_neto'] ?? 0);
            if ($neto > 0) {
                $descProd = 'Venta - ' . trim(($d['producto_codigo'] ?? '') . ' ' . ($d['producto_nombre'] ?? ''));
                $movimientos[] = [
                    'cuenta_id' => $cuenta_venta_id,
                    'producto_id' => intval($d['producto_id'] ?? 0) ?: null,
                    'importe' => -$neto,
                    'descripcion' => $descProd
                ];
                $total_haber += $neto;
            }

            // No Gravado / Exento
            $no_grav = floatval($d['importe_no_gravado'] ?? 0);
            if ($no_grav > 0) {
                $movimientos[] = [
                    'cuenta_id' => $cuenta_venta_id,
                    'producto_id' => intval($d['producto_id'] ?? 0) ?: null,
                    'importe' => -$no_grav,
                    'descripcion' => 'Venta No Gravado - ' . ($d['producto_nombre'] ?? '')
                ];
                $total_haber += $no_grav;
            }
            $exento = floatval($d['importe_exento'] ?? 0);
            if ($exento > 0) {
                $movimientos[] = [
                    'cuenta_id' => $cuenta_venta_id,
                    'producto_id' => intval($d['producto_id'] ?? 0) ?: null,
                    'importe' => -$exento,
                    'descripcion' => 'Venta Exento - ' . ($d['producto_nombre'] ?? '')
                ];
                $total_haber += $exento;
            }

            // IVA acumulado
            $iva = floatval($d['importe_iva'] ?? 0);
            if ($iva > 0) {
                $alicuota_id = intval($d['iva_alicuota_id'] ?? 1);
                $cta_iva = intval($d['iva_cont_cuenta_venta_id'] ?? 0);
                if ($cta_iva <= 0) $cta_iva = emp_cuenta_id($conexion, $empresa_id, 'IVA_DEBITO', 'cuenta_iva_debito_id');
                $pct = floatval($d['porcentaje_iva'] ?? 0);

                if (!isset($iva_agrupado[$alicuota_id])) {
                    $iva_agrupado[$alicuota_id] = [
                        'cuenta_id' => $cta_iva,
                        'porcentaje' => $pct,
                        'importe' => 0.0
                    ];
                }
                $iva_agrupado[$alicuota_id]['importe'] += $iva;
            }
        }

        // Movimiento 3: IVA DÉBITO FISCAL (HABER -)
        foreach ($iva_agrupado as $alicuota_id => $info) {
            $movimientos[] = [
                'cuenta_id' => $info['cuenta_id'],
                'importe' => -$info['importe'],
                'descripcion' => 'IVA Débito Fiscal ' . number_format($info['porcentaje'], 1) . '%'
            ];
            $total_haber += $info['importe'];
        }

        // Movimiento 4: PERCEPCIONES A DEPOSITAR (HABER -): IIBB una línea por jurisdicción, IVA una línea
        foreach (perIibbDelComprobante($conexion, $comprobante_id) as $p) {
            $imp = round(floatval($p['importe']), 2);
            if ($imp <= 0) continue;
            if (intval($p['cont_cuenta_id']) <= 0) throw new Exception($p['descripcion'] . ': falta la cuenta contable en Impuestos Adic. - Conf.');
            $movimientos[] = [
                'cuenta_id' => intval($p['cont_cuenta_id']),
                'importe' => -$imp,
                'descripcion' => $p['descripcion'] . ' ' . number_format(floatval($p['alicuota']), 2, ',', '.') . '%'
            ];
            $total_haber += $imp;
        }

        // Control de balance y ajuste de redondeo (< $1.00): todo contra la cuenta del cliente
        $diferencia = round($total_debe - $total_haber, 2);

        if (abs($diferencia) > 0.0001 && abs($diferencia) < 1.0) {
            $ajustado = false;
            foreach ($movimientos as &$m) {
                if ($m['cuenta_id'] == $cuenta_cliente_id) {
                    $m['importe'] = round($m['importe'] - $diferencia, 2);
                    $total_debe = round($total_debe - $diferencia, 2);
                    $ajustado = true;
                    break;
                }
            }
            unset($m);

            if (!$ajustado) {
                $movimientos[] = [
                    'cuenta_id' => $cuenta_cliente_id,
                    'importe' => -$diferencia,
                    'descripcion' => 'Ajuste redondeo - Cliente: ' . $cliente
                ];
                if ($diferencia > 0) {
                    $total_haber = round($total_haber + $diferencia, 2);
                } else {
                    $total_debe = round($total_debe + abs($diferencia), 2);
                }
            }
            $diferencia = round($total_debe - $total_haber, 2);
        }

        // Control final de balance
        if (abs($total_debe - $total_haber) > 0.001) {
            return [
                'success' => false,
                'error' => "Asiento no balanceado: Debe=" . fmt_numero($total_debe, 2) . ", Haber=" . fmt_numero($total_haber, 2) . ", diferencia=" . fmt_numero($total_debe - $total_haber, 2)
            ];
        }

        // Nota de crédito (signo '-' del tipo): el asiento es el inverso del de la factura
        // (Deudores al Haber; Ventas e IVA Débito al Debe).
        if (($factura['tipo_signo'] ?? '+') === '-') {
            foreach ($movimientos as &$m) {
                $m['importe'] = -$m['importe'];
            }
            unset($m);
        }

        // Período contable abierto, para la fecha del asiento y para el asiento anterior del comprobante
        cont_periodo_exigir_abierto($conexion, $empresa_id, $factura['f_emision'], 'generar el asiento del comprobante');
        cont_periodo_exigir_comprobante($conexion, $empresa_id, $comprobante_id, 'modificar el asiento del comprobante');

        if (!$en_transaccion) {
            mysqli_begin_transaction($conexion);
        }

        // Todas las cuentas tienen que ser del plan de cuentas de la empresa
        emp_validar_cuentas_asiento($conexion, $empresa_id, array_column($movimientos, 'cuenta_id'));

        try {
            $timestamp = strtotime($factura['f_emision']);
            $anio = intval(date('Y', $timestamp));
            $mes = intval(date('m', $timestamp));
            $estado_asiento = 3; // Registrado / Borrador contable
            $sucursal_id = intval($factura['sucursal_id'] ?? 0);
            $deposito_id = emp_boca_id($conexion, $empresa_id, $sucursal_id); // deposito_id de los asientos es la boca
            $entidad_id = intval($factura['entidad_id'] ?? 0);
            $moneda_id = emp_moneda_id($conexion, $empresa_id, $factura['moneda_id'] ?? 0);
            $tipo_cambio = floatval($factura['tipo_cambio'] ?? 1.0);
            $usuario_id = intval($_SESSION['usuario_id'] ?? 0);
            $tipo_asiento_id = emp_tipo_asiento_id($conexion, $empresa_id, 'AUT');

            // Verificar si ya existe asiento para este comprobante
            $sql_check = "SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_check);
            mysqli_stmt_bind_param($stmt, "i", $comprobante_id);
            mysqli_stmt_execute($stmt);
            $res_check = mysqli_stmt_get_result($stmt);
            $row_check = mysqli_fetch_assoc($res_check);
            mysqli_stmt_close($stmt);

            $asiento_id = $row_check ? intval($row_check['cont_asiento_id']) : null;
            $existe = !empty($asiento_id);

            if ($existe) {
                // UPDATE cabecera
                $sql_upd = "UPDATE gestion__cont_asientos
                            SET sucursal_id = ?, deposito_id = ?, entidad_id = ?,
                                f_asiento = ?, anio = ?, mes = ?, descripcion = ?,
                                moneda_id = ?, tipo_cambio = ?, usuario_modificacion_id = ?,
                                tabla_estado_registro_id = ?
                            WHERE cont_asiento_id = ?";
                $stmt = mysqli_prepare($conexion, $sql_upd);
                if (!$stmt) throw new Exception(mysqli_error($conexion));
                mysqli_stmt_bind_param(
                    $stmt, "iiisiisidiii",
                    $sucursal_id, $deposito_id, $entidad_id,
                    $factura['f_emision'], $anio, $mes, $descripcion,
                    $moneda_id, $tipo_cambio, $usuario_id,
                    $estado_asiento, $asiento_id
                );
                if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);

                // Borrar detalles anteriores
                $stmt_del = mysqli_prepare($conexion, "DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = ?");
                mysqli_stmt_bind_param($stmt_del, "i", $asiento_id);
                mysqli_stmt_execute($stmt_del);
                mysqli_stmt_close($stmt_del);
            } else {
                // INSERT cabecera
                $sql_ins = "INSERT INTO gestion__cont_asientos
                            (empresa_id, sucursal_id, deposito_id, comprobante_id, entidad_id,
                             cont_tipo_asiento_id, f_asiento, anio, mes, descripcion,
                             moneda_id, tipo_cambio, usuario_creacion_id, tabla_estado_registro_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conexion, $sql_ins);
                if (!$stmt) throw new Exception(mysqli_error($conexion));
                mysqli_stmt_bind_param(
                    $stmt, "iiiiiisiisidii",
                    $empresa_id, $sucursal_id, $deposito_id, $comprobante_id, $entidad_id, $tipo_asiento_id,
                    $factura['f_emision'], $anio, $mes, $descripcion,
                    $moneda_id, $tipo_cambio, $usuario_id, $estado_asiento
                );
                if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
                $asiento_id = mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt);
            }

            // Insertar líneas de detalle. entidad_id (el cliente) va en todos los renglones;
            // producto_id solo en los renglones de venta de un producto (NULL en cliente/IVA).
            $entidad_det_id = $entidad_id ?: null;
            $sql_det_ins = "INSERT INTO gestion__cont_asientos_detalles
                            (cont_asiento_id, cuenta_id, entidad_id, producto_id, importe_local, importe_ifrs, importe_impositivo,
                             moneda_id, tipo_cambio, descripcion, tipo, estado, comprobante_id)
                            VALUES (?, ?, ?, ?, ?, 0.00, 0.00, ?, ?, ?, 'A', 'activo', ?)";
            foreach ($movimientos as $m) {
                $stmt = mysqli_prepare($conexion, $sql_det_ins);
                if (!$stmt) throw new Exception(mysqli_error($conexion));
                $producto_det_id = $m['producto_id'] ?? null;
                mysqli_stmt_bind_param(
                    $stmt, "iiiididsi",
                    $asiento_id,
                    $m['cuenta_id'],
                    $entidad_det_id,
                    $producto_det_id,
                    $m['importe'],
                    $moneda_id,
                    $tipo_cambio,
                    $m['descripcion'],
                    $comprobante_id
                );
                if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
            }

            if (!$en_transaccion) {
                mysqli_commit($conexion);
            }

            return [
                'success' => true,
                'asiento_id' => $asiento_id,
                'message' => $existe ? 'Asiento contable actualizado correctamente.' : 'Asiento contable generado correctamente.'
            ];
        } catch (Exception $e) {
            if (!$en_transaccion) {
                mysqli_rollback($conexion);
            }
            throw $e;
        }
    } catch (Exception $e) {
        error_log("ERROR en vfGenerarAsientoContableFactura: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function vfObtenerAsientoFactura($conexion, $venta_factura_id, $empresa_id) {
    $venta_factura_id = intval($venta_factura_id);
    $empresa_id = intval($empresa_id);

    $sql = "SELECT a.cont_asiento_id, a.empresa_id, a.sucursal_id, a.deposito_id,
                   a.comprobante_id, a.entidad_id, a.cont_tipo_asiento_id, a.f_asiento,
                   a.anio, a.mes, a.descripcion, a.moneda_id, a.tipo_cambio,
                   ta.cont_tipo_asiento, er.estado_registro
            FROM gestion__cont_asientos a
            INNER JOIN gestion__ventas_facturas f ON f.comprobante_id = a.comprobante_id
            LEFT JOIN gestion__cont_tipos_asientos ta ON ta.cont_tipo_asiento_id = a.cont_tipo_asiento_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = a.tabla_estado_registro_id
            WHERE f.venta_factura_id = ? AND f.empresa_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $asiento = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$asiento) return null;

    $sql_det = "SELECT d.cont_asiento_id, d.cuenta_id, d.importe_local, d.descripcion,
                       d.tipo, d.estado,
                       c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre,
                       IF(d.importe_local > 0, d.importe_local, 0) AS debe,
                       IF(d.importe_local < 0, ABS(d.importe_local), 0) AS haber
                FROM gestion__cont_asientos_detalles d
                LEFT JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = d.cuenta_id
                WHERE d.cont_asiento_id = ?
                ORDER BY d.importe_local DESC, d.cuenta_id ASC";
    $stmt = mysqli_prepare($conexion, $sql_det);
    if (!$stmt) return $asiento;
    mysqli_stmt_bind_param($stmt, "i", $asiento['cont_asiento_id']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $detalles = [];
    $total_debe = 0;
    $total_haber = 0;
    while ($r = mysqli_fetch_assoc($res)) {
        $total_debe += floatval($r['debe']);
        $total_haber += floatval($r['haber']);
        $detalles[] = $r;
    }
    mysqli_stmt_close($stmt);

    $asiento['detalles'] = $detalles;
    $asiento['total_debe'] = $total_debe;
    $asiento['total_haber'] = $total_haber;
    return $asiento;
}

// ---------------------------------------------------------------------------------------------
// Cobro desde la factura (solapa "Medios de Pago"): emite un Recibo de Cobro (RCX) con punto de
// venta, confirmado e imputado a esta factura. Usa cobranzas_model.php (lo incluye el _ajax) para
// que el recibo sea el mismo que uno cargado en cobranzas.php: numeración, movimientos de
// tesorería, asiento, cheques en cartera y estado derivado de la factura (39 / 40) vía
// recalcularEstadoDestino(). La función 'cobrar' de la página (_local/sql/mantenimiento/
// 20261007_ventas_facturas_cobro_01_funciones.sql) define desde qué estados se puede cobrar.
// ---------------------------------------------------------------------------------------------

// Página de cobranzas: su tabla (275) define qué puntos de venta / tipos emiten recibos.
const VF_PAGINA_COBRANZAS = 105;

function vfFuncionPermitida($conexion, $pagina_id, $estado_id, $accion_js) {
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM conf__paginas_funciones
                                       WHERE pagina_id = ? AND accion_js = ? AND tabla_estado_registro_origen_id = ?
                                         AND tabla_estado_registro_id = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'isi', $pagina_id, $accion_js, $estado_id);
    mysqli_stmt_execute($stmt);
    $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $ok;
}

// Saldo de la factura, comprobantes ya imputados (recibos, notas de crédito) y si admite un cobro nuevo.
function vfCobroFactura($conexion, $venta_factura_id, $empresa_id, $pagina_id) {
    $venta_factura_id = intval($venta_factura_id);
    $empresa_id = intval($empresa_id);
    $estado = vfEstadoColumna($conexion);

    $sql = "SELECT f.venta_factura_id, f.entidad_id, f.sucursal_id, f.punto_venta_id, f.comprobante_nro,
                   f.comprobante_id, f.importe_total, f.moneda_id, f.tabla_estado_registro_id,
                   er.$estado AS estado_registro, ct.comprobante_tipo, e.entidad_nombre,
                   c.importe_pendiente
            FROM gestion__ventas_facturas f
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
            LEFT JOIN gestion__comprobantes c ON c.comprobante_id = f.comprobante_id AND c.empresa_id = f.empresa_id
            WHERE f.venta_factura_id = ? AND f.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$f) return null;

    $comprobante_id = intval($f['comprobante_id']);
    $total = round(floatval($f['importe_total']), 2);
    $saldo = $f['importe_pendiente'] !== null ? round(floatval($f['importe_pendiente']), 2) : $total;

    $imputaciones = [];
    if ($comprobante_id > 0) {
        $sql_i = "SELECT ci.imputacion_id, ci.f_imputacion, ci.importe_imputado, ci.tabla_estado_registro_id,
                         er.$estado AS estado_registro, er.codigo_estandar,
                         " . emp_sql_pv_mostrar('co.comprobante_pv', 'co.empresa_id', 'co.tabla_origen_id') . " AS comprobante_pv, co.comprobante_nro, cto.codigo AS comprobante_codigo, cto.comprobante_tipo,
                         cob.cobranza_id,
                         (SELECT GROUP_CONCAT(mp.medio_pago ORDER BY cm.cobranza_medio_id SEPARATOR ', ')
                            FROM gestion__cobranzas_medios cm
                            JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id
                           WHERE cm.cobranza_id = cob.cobranza_id) AS medios
                  FROM gestion__comprobantes_imputaciones ci
                  JOIN gestion__comprobantes co ON co.comprobante_id = ci.comp_origen_id
                  LEFT JOIN gestion__comprobantes_tipos cto ON cto.comprobante_tipo_id = co.comprobante_tipo_id
                  LEFT JOIN gestion__cobranzas cob ON co.tabla_origen_id = ? AND cob.cobranza_id = co.registro_origen_id
                  LEFT JOIN conf__estados_registros er ON er.estado_registro_id = ci.tabla_estado_registro_id
                  WHERE ci.comp_destino_id = ? AND ci.empresa_id = ?
                  ORDER BY ci.imputacion_id";
        $stmt = mysqli_prepare($conexion, $sql_i);
        $tabla_cobranzas = TABLA_COBRANZAS_ID;
        mysqli_stmt_bind_param($stmt, 'iii', $tabla_cobranzas, $comprobante_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) $imputaciones[] = $r;
        mysqli_stmt_close($stmt);
    }

    // Consumidor final: el cobro de contado sale al aprobar; si quedó saldo (falló el cobro automático
    // después del CAE) se cobra acá, siempre por el total.
    $motivo = '';
    if (!vfFuncionPermitida($conexion, $pagina_id, intval($f['tabla_estado_registro_id']), 'cobrar')) {
        $motivo = 'La factura no admite cobros en su estado actual (' . ($f['estado_registro'] ?? 'sin estado') . '). Se cobra una vez confirmada y aprobada.';
    } elseif ($comprobante_id <= 0 || $saldo <= 0.001) {
        $motivo = 'La factura no tiene saldo pendiente de cobro.';
    }

    return [
        'venta_factura_id' => intval($f['venta_factura_id']),
        'entidad_id'       => intval($f['entidad_id']),
        'consumidor_final' => empty($f['entidad_id']),
        'entidad_nombre'   => $f['entidad_nombre'],
        'sucursal_id'      => intval($f['sucursal_id']),
        'comprobante_id'   => $comprobante_id,
        'comprobante'      => trim(($f['comprobante_tipo'] ?? 'Factura') . ' ' . emp_numero_comprobante($conexion, $empresa_id, $f['punto_venta_id'], $f['comprobante_nro'])),
        'importe_total'    => $total,
        'saldo'            => $saldo,
        'cobrado'          => round($total - $saldo, 2),
        'estado_registro'  => $f['estado_registro'],
        'puede_cobrar'     => $motivo === '',
        'motivo'           => $motivo,
        'imputaciones'     => $imputaciones
    ];
}

// Datos para armar el formulario de cobro: medios, cuentas, bancos, retenciones y puntos de venta de recibos.
function vfMaestrosCobro($conexion, $empresa_id) {
    return [
        'medios_pago'         => obtenerMediosPagoDisponibles($conexion, $empresa_id),
        'cuentas_tesoreria'   => obtenerCuentasTesoreriaEmpresa($conexion, $empresa_id),
        'bancos'              => obtenerBancosDisponibles($conexion),
        'impuestos_retencion' => obtenerImpuestosRetencion($conexion, 'COBRO'),
        'jurisdicciones'      => obtenerJurisdicciones($conexion),
        'puntos_venta'        => obtenerPuntosVentaCobranza($conexion, $empresa_id, VF_PAGINA_COBRANZAS)
    ];
}

function vfTiposReciboPorPuntoVenta($conexion, $empresa_id, $punto_venta_id) {
    return obtenerTiposComprobanteCobranza($conexion, $empresa_id, $punto_venta_id, VF_PAGINA_COBRANZAS);
}

/**
 * Valida los medios de cobro que manda la pantalla (solapa Medios de Pago o cobro de contado):
 * solo medios habilitados para cobranzas y cuentas de tesorería de esta empresa, importes > 0.
 * $consumidor_final: sin retenciones (un consumidor final no es agente de retención).
 * Devuelve ['medios' => [...], 'total' => float] o ['error' => string].
 */
function vfValidarMediosCobro($conexion, $empresa_id, $medios, $consumidor_final = false) {
    $conn = $conexion;   // el modelo hace "$conexion = $conn" al cargarse; acá se carga dentro de una función
    require_once __DIR__ . '/cobranzas_model.php';
    if (!is_array($medios) || count($medios) === 0) return ['error' => 'Agregue al menos un medio de pago.'];
    $medios_validos = array_column(obtenerMediosPagoDisponibles($conexion, $empresa_id), null, 'medio_pago_id');
    $cuentas_validas = array_column(obtenerCuentasTesoreriaEmpresa($conexion, $empresa_id), null, 'tesoreria_cuenta_id');
    $total = 0.0;
    $out = [];
    foreach (array_values($medios) as $i => $m) {
        if (!is_array($m)) return ['error' => 'Medio de pago inválido en la fila ' . ($i + 1) . '.'];
        $medio = $medios_validos[intval($m['medio_pago_id'] ?? 0)] ?? null;
        if (!$medio) return ['error' => 'Medio de pago inválido en la fila ' . ($i + 1) . '.'];
        if ($consumidor_final && ($medio['medio_pago_tipo'] ?? '') === 'RETENCION') {
            return ['error' => 'A consumidor final no se le registran retenciones (fila ' . ($i + 1) . ').'];
        }
        if (!empty($m['tesoreria_cuenta_id']) && !isset($cuentas_validas[intval($m['tesoreria_cuenta_id'])])) {
            return ['error' => 'Cuenta de tesorería inválida en la fila ' . ($i + 1) . '.'];
        }
        $importe = round(floatval($m['importe'] ?? 0), 2);
        if ($importe <= 0) return ['error' => 'Cada medio de pago debe tener un importe mayor a cero.'];
        $m['importe'] = $importe;
        $m['importe_comprobante'] = $importe;
        $out[] = $m;
        $total += $importe;
    }
    return ['medios' => $out, 'total' => round($total, 2)];
}

/**
 * ¿La acción 'aprobada' de esta factura emite el cobro de contado a consumidor final?
 * (factura / ND sin cliente, en Pendiente de aprobación / ARCA). Lo usa la pantalla para pedir
 * los medios de pago antes de aprobar.
 */
function vfCobroContadoInfo($conexion, $venta_factura_id, $empresa_id) {
    $stmt = mysqli_prepare($conexion, "SELECT vf.venta_factura_id, vf.entidad_id, vf.sucursal_id, vf.importe_total,
                                              vf.tabla_estado_registro_id, ct.signo
                                       FROM gestion__ventas_facturas vf
                                       JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vf.comprobante_tipo_id
                                       WHERE vf.venta_factura_id = ? AND vf.empresa_id = ?");
    $venta_factura_id = intval($venta_factura_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'ii', $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$f) return null;
    return [
        'requiere'    => empty($f['entidad_id']) && $f['signo'] === '+' && intval($f['tabla_estado_registro_id']) === 4,
        'total'       => round(floatval($f['importe_total']), 2),
        'sucursal_id' => intval($f['sucursal_id'])
    ];
}

function vfEmitirReciboFactura($conexion, $venta_factura_id, $empresa_id, $pagina_id, $usuario_id, $data) {
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);
    error_log("=== INICIO vfEmitirReciboFactura factura: $venta_factura_id ===");

    $cobro = vfCobroFactura($conexion, $venta_factura_id, $empresa_id, $pagina_id);
    if (!$cobro) return ['success' => false, 'message' => 'Factura no encontrada.'];
    if (!$cobro['puede_cobrar']) return ['success' => false, 'message' => $cobro['motivo']];

    $es_cf = $cobro['entidad_id'] <= 0;
    $val = vfValidarMediosCobro($conexion, $empresa_id, $data['medios'] ?? [], $es_cf);
    if (isset($val['error'])) return ['success' => false, 'message' => $val['error']];
    $medios = $val['medios'];
    $total = $val['total'];
    // Un excedente sería un anticipo del cliente: se registra desde Cobranzas, no desde la factura.
    if ($total > $cobro['saldo'] + 0.001) {
        return ['success' => false, 'message' => 'El total de los medios ($' . fmt_numero($total, 2) . ') supera el saldo de la factura ($' . fmt_numero($cobro['saldo'], 2) . ').'];
    }
    // Consumidor final: no tiene cuenta corriente, se cobra todo el saldo.
    if ($es_cf && abs($total - $cobro['saldo']) > 0.001) {
        return ['success' => false, 'message' => 'A consumidor final se cobra el total del saldo ($' . fmt_numero($cobro['saldo'], 2) . ').'];
    }

    // Punto de venta del recibo: habilitado para recibos y para el usuario (accesos por sucursal)
    $pv_id = intval($data['comprobante_pv'] ?? 0);
    $pv = null;
    foreach (obtenerPuntosVentaCobranza($conexion, $empresa_id, VF_PAGINA_COBRANZAS) as $r) {
        if (intval($r['punto_venta_id']) === $pv_id) { $pv = $r; break; }
    }
    if (!$pv) return ['success' => false, 'message' => 'Seleccione un punto de venta habilitado para emitir recibos.'];

    $tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
    $tipos = vfTiposReciboPorPuntoVenta($conexion, $empresa_id, $pv_id);
    $tipos_ids = array_map('intval', array_column($tipos, 'comprobante_tipo_id'));
    if ($tipo_id <= 0 && count($tipos_ids) > 0) $tipo_id = $tipos_ids[0];
    if (!in_array($tipo_id, $tipos_ids, true)) return ['success' => false, 'message' => 'El tipo de recibo no está habilitado en ese punto de venta.'];

    $f_emision = trim($data['f_emision'] ?? '');
    $fecha = DateTime::createFromFormat('Y-m-d', $f_emision);
    if (!$fecha || $fecha->format('Y-m-d') !== $f_emision) $f_emision = date('Y-m-d');

    $obs = 'Cobro de ' . $cobro['comprobante'];
    if (trim($data['observaciones'] ?? '') !== '') $obs .= ' - ' . trim($data['observaciones']);

    // 1) Borrador (guardarCobranzaBorrador no abre transacción propia)
    mysqli_begin_transaction($conexion);
    try {
        $cobranza_id = guardarCobranzaBorrador($conexion, [
            'empresa_id'          => $empresa_id,
            'usuario_id'          => $usuario_id,
            'entidad_id'          => $cobro['entidad_id'],
            'consumidor_final'    => $es_cf,
            'sucursal_id'         => intval($pv['sucursal_id']),
            'comprobante_pv'      => $pv_id,
            'comprobante_tipo_id' => $tipo_id,
            'f_emision'           => $f_emision,
            'f_contabilidad'      => $f_emision,
            'observaciones'       => $obs,
            'medios'              => $medios,
            'imputaciones'        => [['comprobante_destino_id' => $cobro['comprobante_id'], 'importe_imputado' => $total]]
        ]);
        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log('ERROR vfEmitirReciboFactura (borrador): ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }

    // 2) Confirmación (transacción propia: numera, imputa con lock, tesorería y asiento). Si falla,
    //    se descarta el borrador para no dejar recibos sueltos sin emitir.
    try {
        $res = confirmarCobranza($conexion, $cobranza_id, $empresa_id, $usuario_id);
    } catch (Throwable $e) {
        error_log('ERROR vfEmitirReciboFactura (confirmar cobranza ' . $cobranza_id . '): ' . $e->getMessage());
        try {
            $stmt = mysqli_prepare($conexion, "DELETE ch FROM gestion__cheques ch
                                               JOIN gestion__cobranzas c ON c.comprobante_id = ch.comp_origen_id
                                               WHERE c.cobranza_id = ? AND c.empresa_id = ? AND ch.tabla_estado_registro_id = 3");
            mysqli_stmt_bind_param($stmt, 'ii', $cobranza_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            eliminarCobranzaBorrador($conexion, $cobranza_id, $empresa_id, $usuario_id);
        } catch (Throwable $e2) {
            error_log('ERROR vfEmitirReciboFactura (descartar borrador ' . $cobranza_id . '): ' . $e2->getMessage());
        }
        return ['success' => false, 'message' => 'No se pudo emitir el recibo: ' . $e->getMessage()];
    }

    return [
        'success' => true,
        'data'    => ['cobranza_id' => $cobranza_id, 'comprobante_nro' => $res['comprobante_nro'] ?? null],
        'message' => $res['message'] ?? 'Recibo emitido.'
    ];
}

// ---------------------------------------------------------------------------------------------
// ARCA: CAE al aprobar (estado 4 "Pendiente de ARCA" -> 5 Confirmado, acción 'aprobada').
// Un comprobante fiscal (tipo con comprobante_fiscal_id > 0) no tiene número propio: 'confirma'
// lo deja en 0 y el número es el que autoriza ARCA (último autorizado + 1 del PV fiscal). Sin CAE
// la factura no pasa a Confirmado, no se numera y no genera asiento. Usa la integración WSFE
// activa de la empresa (arca_integraciones.php, página 99) y arca/arca_lib.php.
// Ante un timeout no se reintenta a ciegas: al volver a aprobar se consulta en ARCA el último
// número autorizado y, si coincide con esta factura, se toma ese CAE (conciliación).
// ---------------------------------------------------------------------------------------------

// Tipos de ARCA que se autorizan desde acá: Factura A, B y C, y sus notas de débito / crédito.
// Las notas van con <CbtesAsoc>, armado desde comprobante_asociado_id (obligatorio).
const VF_ARCA_TIPOS_FACTURA = [1, 6, 11];
const VF_ARCA_TIPOS_NOTA = [2, 3, 7, 8, 12, 13];
// condicion_fiscal_codigo (gestion__condiciones_fiscales) -> CondicionIVAReceptorId de ARCA
// (FEParamGetCondicionIvaReceptor, mismos códigos que afip__condiciones_iva.codigo_afip).
const VF_ARCA_CONDICION_IVA = ['RI' => 1, 'EX' => 4, 'CF' => 5, 'MT' => 6, 'NC' => 7, 'EXT' => 9];
// Porcentaje de IVA -> Id de alícuota de ARCA (FEParamGetTiposIva).
const VF_ARCA_ALICUOTAS = ['0.00' => 3, '2.50' => 9, '5.00' => 8, '10.50' => 4, '21.00' => 5, '27.00' => 6];
// Concepto 1 (productos): ARCA acepta fecha de comprobante hasta 5 días antes o después de hoy.
const VF_ARCA_DIAS_FECHA = 5;

// ¿La empresa consume ARCA? gestion__empresas_parametros.arca_habilitado de la vigencia abierta
// (página 111; mismo criterio de vigencia que obtenerParametrosVigentesEmpresa). Sin la columna
// (script 20261007_ventas_facturas_arca_02 sin correr) o sin vigencia: no habilitado.
function vfArcaHabilitado($conexion, $empresa_id) {
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'arca_habilitado'");
    if (!$res || mysqli_num_rows($res) === 0) return false;
    $stmt = mysqli_prepare($conexion, "SELECT arca_habilitado FROM gestion__empresas_parametros
                                       WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1
                                       ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['arca_habilitado'] ?? 0) === 1;
}

// Datos fiscales del tipo de comprobante (null si no es fiscal: Factura X y similares).
function vfComprobanteFiscalArca($conexion, $comprobante_tipo_id, $empresa_id) {
    $sql = "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, cf.comprobante_fiscal_id,
                   CAST(cf.codigo AS UNSIGNED) AS arca_tipo, cf.comprobante_fiscal, cf.letra AS fiscal_letra
            FROM gestion__comprobantes_tipos ct
            INNER JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
            WHERE ct.comprobante_tipo_id = ? AND ct.empresa_id IN (0, ?) AND ct.comprobante_fiscal_id > 0
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $comprobante_tipo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// Integración WSFE elegida en Parámetros de Empresa (arca_integracion_empresa_id de la vigencia
// abierta, script 20261018_arca_certificado_parametros_01). Sin columna o sin elegir: 0.
function vfArcaIntegracionElegida($conexion, $empresa_id) {
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'arca_integracion_empresa_id'");
    if (!$res || mysqli_num_rows($res) === 0) return 0;
    $stmt = mysqli_prepare($conexion, "SELECT arca_integracion_empresa_id FROM gestion__empresas_parametros
                                       WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1
                                       ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['arca_integracion_empresa_id'] ?? 0);
}

// Integración WSFE con la que se pide el CAE. Devuelve [cfg, error].
// La elegida en Parámetros de Empresa (certificado + homologación / producción), propia o delegada
// por otra empresa; si no se eligió ninguna, la única WSFE activa de la empresa, como antes.
function vfArcaConfiguracion($conexion, $empresa_id) {
    $elegida = vfArcaIntegracionElegida($conexion, $empresa_id);
    if ($elegida > 0) {
        $cfg = arca_obtener_configuracion_para_empresa($conexion, $elegida, $empresa_id);
        if (!$cfg) return [null, 'La integración ARCA elegida en Parámetros de Empresa no existe, es de otra empresa o su delegación no está vigente.'];
        if ($cfg['cuit'] === '') return [null, 'La empresa no tiene CUIT cargado: no se puede pedir el CAE.'];
        if ($cfg['servicio'] !== 'wsfe') return [null, 'La integración ARCA elegida en Parámetros de Empresa no es de facturación (WSFE).'];
        if (intval($cfg['tabla_estado_registro_id']) !== 1) return [null, 'La integración ARCA elegida en Parámetros de Empresa está inactiva. Actívela en Integraciones ARCA o elija otra.'];
        if (intval($cfg['ambiente_activo']) !== 1) return [null, 'El ambiente ' . $cfg['ambiente_nombre'] . ' de la integración ARCA elegida está inactivo.'];
        return [$cfg, null];
    }

    $sql = "SELECT ie.integracion_empresa_id
            FROM gestion__integraciones_empresas ie
            INNER JOIN conf__integraciones_ambientes a ON a.integracion_ambiente_id = ie.integracion_ambiente_id
            WHERE ie.empresa_id = ? AND ie.servicio = 'wsfe' AND ie.tabla_estado_registro_id = 1 AND a.activo = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ids = [];
    while ($r = mysqli_fetch_assoc($res)) $ids[] = intval($r['integracion_empresa_id']);
    mysqli_stmt_close($stmt);

    if (count($ids) === 0) return [null, 'La empresa no tiene una integración ARCA (WSFE) activa. Configúrela en Integraciones ARCA.'];
    // Con homologación y producción activas a la vez no se adivina: un CAE de producción es real.
    if (count($ids) > 1) return [null, 'La empresa tiene más de una integración ARCA (WSFE) activa. Elija con cuál se pide el CAE en Parámetros de Empresa.'];
    $cfg = arca_obtener_configuracion($conexion, $ids[0], $empresa_id);
    if (!$cfg) return [null, 'No se pudo leer la configuración de la integración ARCA.'];
    return [$cfg, null];
}

// Arma el pedido de CAE a partir de la factura. Devuelve ['datos' => [...], 'error' => null|string].
function vfArcaDatosFactura($conexion, $factura, $fiscal, $empresa_id) {
    $error = function ($m) { return ['datos' => null, 'error' => $m]; };
    $id = intval($factura['venta_factura_id']);
    $arca_tipo = intval($fiscal['arca_tipo']);

    $es_nota = in_array($arca_tipo, VF_ARCA_TIPOS_NOTA, true);
    if (!$es_nota && !in_array($arca_tipo, VF_ARCA_TIPOS_FACTURA, true)) {
        return $error($fiscal['comprobante_tipo'] . ': ese tipo de comprobante todavía no se autoriza con ARCA desde Facturas.');
    }
    if (intval($factura['moneda_id']) !== emp_moneda_por_codigo($conexion, $empresa_id, 'ARS')) return $error('ARCA: por ahora solo se autorizan comprobantes en pesos.');

    $cbtes_asoc = [];
    if ($es_nota) {
        [$asoc, $error_asoc] = vfArcaComprobanteAsociado($conexion, $factura, $fiscal, $empresa_id);
        if ($error_asoc !== null) return $error($error_asoc);
        $cbtes_asoc[] = $asoc;
    }

    $stmt = mysqli_prepare($conexion, "SELECT nombre, codigo_fiscal, sistema_emision FROM gestion__puntos_venta WHERE punto_venta_id = ? AND empresa_id = ?");
    $pv_id = intval($factura['punto_venta_id']);
    mysqli_stmt_bind_param($stmt, 'ii', $pv_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $pv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $pto_vta = intval($pv['codigo_fiscal'] ?? 0);
    if ($pto_vta <= 0) return $error('El punto de venta ' . ($pv['nombre'] ?? $pv_id) . ' no tiene código fiscal de ARCA.');

    $sistema_emision = !empty($pv['sistema_emision']) ? $pv['sistema_emision'] : 'WSFE';
    if ($sistema_emision !== 'WSFE') {
        $nombres_sistemas = [
            'RCEL' => 'Comprobantes en Línea (RCEL)',
            'FISCAL' => 'Controlador Fiscal',
            'MOVIL' => 'Facturador Móvil',
            'TALONARIO' => 'Comprobantes Manuales (Talonario)',
            'NO_FISCAL' => 'Comprobantes Internos (Sin valor fiscal)'
        ];
        $modalidad = $nombres_sistemas[$sistema_emision] ?? $sistema_emision;
        return $error('El punto de venta "' . ($pv['nombre'] ?? $pv_id) . '" (PV ' . str_pad($pto_vta, 4, '0', STR_PAD_LEFT) . ') tiene asignado el sistema de emisión "' . $modalidad . '". En ARCA, la emisión automática vía Web Service requiere exclusivamente un punto de venta dado de alta bajo la modalidad "Web Service (Factura Electrónica)". No es posible autorizar vía API con puntos de venta asignados a ' . $modalidad . '.');
    }

    // Receptor: condición IVA vigente y documento
    $entidad_id = intval($factura['entidad_id'] ?? 0);
    $rel = vfObtenerRelacionFiscal($conexion, $empresa_id, $entidad_id ?: 'CF');
    $cond_codigo = $rel['receptor_condicion_fiscal_codigo'] ?? 'CF';
    if (!isset(VF_ARCA_CONDICION_IVA[$cond_codigo])) return $error('La condición fiscal del cliente (' . $cond_codigo . ') no tiene equivalente en ARCA.');
    $doc_tipo = 99;
    $doc_nro = '0';
    if ($entidad_id > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT cuit FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $entidad_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $ent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $cuit = preg_replace('/\D/', '', (string)($ent['cuit'] ?? ''));
        if (strlen($cuit) === 11) {
            $doc_tipo = 80;
            $doc_nro = $cuit;
        }
    }
    if (in_array($arca_tipo, [1, 2, 3], true) && $doc_tipo !== 80) return $error($fiscal['comprobante_tipo'] . ' requiere que el cliente tenga CUIT cargado (11 dígitos).');

    // Importes por alícuota desde el detalle
    $sql = "SELECT d.importe_neto, d.importe_iva, d.porcentaje_iva, a.es_exento, a.es_no_gravado
            FROM gestion__ventas_facturas_detalles d
            LEFT JOIN gestion__impuestos__iva_alicuotas a ON a.iva_alicuota_id = d.iva_alicuota_id
            WHERE d.venta_factura_id = ? AND d.tabla_estado_registro_id <> 6";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $alicuotas = [];
    $exento = 0.0;
    $no_gravado = 0.0;
    while ($d = mysqli_fetch_assoc($res)) {
        if (!empty($d['es_exento'])) { $exento += floatval($d['importe_neto']); continue; }
        if (!empty($d['es_no_gravado'])) { $no_gravado += floatval($d['importe_neto']); continue; }
        $clave = number_format(floatval($d['porcentaje_iva']), 2, '.', '');
        if (!isset(VF_ARCA_ALICUOTAS[$clave])) { mysqli_stmt_close($stmt); return $error("Alícuota de IVA $clave% sin equivalente en ARCA."); }
        $aid = VF_ARCA_ALICUOTAS[$clave];
        if (!isset($alicuotas[$aid])) $alicuotas[$aid] = ['id' => $aid, 'base' => 0.0, 'importe' => 0.0];
        $alicuotas[$aid]['base'] += floatval($d['importe_neto']);
        $alicuotas[$aid]['importe'] += floatval($d['importe_iva']);
    }
    mysqli_stmt_close($stmt);
    if (empty($alicuotas) && $exento <= 0 && $no_gravado <= 0) return $error('La factura no tiene líneas de detalle.');

    // A ARCA van los importes de la cabecera (los que quedan en el comprobante y el asiento). El
    // redondeo de IVA línea por línea puede diferir en centavos: lo absorbe la alícuota principal.
    $exento = round($exento, 2);
    $no_gravado = round($no_gravado, 2);
    $total = round(floatval($factura['importe_total']), 2);
    $es_clase_c = in_array($arca_tipo, [11, 12, 13], true);
    $iva = $es_clase_c ? 0.0 : round(floatval($factura['importe_iva']), 2);
    $neto = round(floatval($factura['importe_neto']) - $exento - $no_gravado, 2);

    // Percepciones practicadas (IIBB e IVA) -> <Tributos>
    $tributos = [];
    foreach (perIibbDelComprobante($conexion, intval($factura['comprobante_id'])) as $p) {
        $imp = round(floatval($p['importe']), 2);
        if ($imp <= 0) continue;
        if (intval($p['arca_tributo_id'] ?? 0) <= 0) return $error($p['descripcion'] . ': falta el código de tributo de ARCA en Impuestos Adic. - Conf.');
        $tributos[] = ['id' => intval($p['arca_tributo_id']), 'desc' => mb_substr($p['leyenda'] ?: $p['descripcion'], 0, 80),
                       'base' => round(floatval($p['base_imponible']), 2), 'alic' => round(floatval($p['alicuota']), 2), 'importe' => $imp];
    }
    $imp_trib = round(array_sum(array_column($tributos, 'importe')), 2);
    if (abs($neto + $iva + $exento + $no_gravado + $imp_trib - $total) > 0.005) {
        return $error('Los importes de la factura no cuadran (neto + IVA + exento + no gravado + percepciones distinto del total).');
    }
    if ($es_clase_c) {
        $alicuotas = [];   // Clase C: sin IVA discriminado
    } elseif (!empty($alicuotas)) {
        $principal = null;
        foreach ($alicuotas as $aid => &$a) {
            $a['base'] = round($a['base'], 2);
            $a['importe'] = round($a['importe'], 2);
            if ($principal === null || $a['base'] > $alicuotas[$principal]['base']) $principal = $aid;
        }
        unset($a);
        $dif_base = round($neto - array_sum(array_column($alicuotas, 'base')), 2);
        $dif_iva = round($iva - array_sum(array_column($alicuotas, 'importe')), 2);
        if (abs($dif_base) > 0.05 || abs($dif_iva) > 0.05) {
            return $error('El total de la factura ($' . fmt_numero($total, 2) . ') no coincide con la suma de sus líneas: revise el detalle.');
        }
        $alicuotas[$principal]['base'] = round($alicuotas[$principal]['base'] + $dif_base, 2);
        $alicuotas[$principal]['importe'] = round($alicuotas[$principal]['importe'] + $dif_iva, 2);
    } elseif ($neto > 0 || $iva > 0) {
        return $error('La factura tiene neto gravado pero ninguna línea con alícuota de IVA.');
    }

    // Fecha dentro del rango que acepta ARCA
    $f = DateTime::createFromFormat('Y-m-d', substr((string)$factura['f_emision'], 0, 10));
    $hoy = new DateTime('today');
    if (!$f || abs(intval($hoy->diff($f->setTime(0, 0))->format('%r%a'))) > VF_ARCA_DIAS_FECHA) {
        return $error('ARCA solo acepta facturas con fecha de emisión de hasta ' . VF_ARCA_DIAS_FECHA . ' días antes o después de hoy. Vuelva la factura a Borrador y corrija la fecha.');
    }

    return ['datos' => [
        'pto_vta' => $pto_vta,
        'cbte_tipo' => $arca_tipo,
        'concepto' => 1,
        'doc_tipo' => $doc_tipo,
        'doc_nro' => $doc_nro,
        'condicion_iva_receptor_id' => VF_ARCA_CONDICION_IVA[$cond_codigo],
        'imp_total' => $total,
        'imp_neto' => round($neto, 2),
        'imp_iva' => round($iva, 2),
        'imp_op_ex' => $exento,
        'imp_tot_conc' => $no_gravado,
        'alicuotas' => array_values($alicuotas),
        'tributos' => $tributos,
        'imp_trib' => $imp_trib,
        'cbtes_asoc' => $cbtes_asoc,
        'fecha' => $f->format('Ymd')
    ], 'error' => null];
}

// <CbteAsoc> de una NC/ND: el comprobante asociado (gestion__comprobantes) con su tipo y PV de ARCA.
// Tiene que ser fiscal y, si salió por un PV de Web Service, tener CAE (si no, ARCA no lo conoce).
// Devuelve [asoc, error].
function vfArcaComprobanteAsociado($conexion, $factura, $fiscal, $empresa_id) {
    $asociado_id = intval($factura['comprobante_asociado_id'] ?? 0);
    if ($asociado_id <= 0) {
        return [null, $fiscal['comprobante_tipo'] . ': ARCA exige el comprobante asociado. Vuelva la nota a Borrador y elíjalo.'];
    }
    $sql = "SELECT c.comprobante_nro, c.f_emision, ct.comprobante_tipo, CAST(cf.codigo AS UNSIGNED) AS arca_tipo,
                   pv.codigo_fiscal, pv.sistema_emision, vf.CAE
            FROM gestion__comprobantes c
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            LEFT JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id AND ct.comprobante_fiscal_id > 0
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = c.comprobante_pv AND pv.empresa_id = c.empresa_id
            LEFT JOIN gestion__ventas_facturas vf ON vf.comprobante_id = c.comprobante_id AND vf.empresa_id = c.empresa_id
            WHERE c.comprobante_id = ? AND c.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $asociado_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $a = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$a) return [null, 'No se encontró el comprobante asociado de la nota.'];

    $pto_vta = intval($a['codigo_fiscal'] ?? 0);
    $numero = str_pad($pto_vta, 4, '0', STR_PAD_LEFT) . '-' . str_pad(intval($a['comprobante_nro']), 8, '0', STR_PAD_LEFT);
    if (intval($a['arca_tipo'] ?? 0) <= 0 || $pto_vta <= 0) {
        return [null, 'El comprobante asociado (' . $a['comprobante_tipo'] . ') no es fiscal: una nota autorizada por ARCA solo puede asociarse a un comprobante fiscal.'];
    }
    if (($a['sistema_emision'] ?: 'WSFE') === 'WSFE' && trim((string)$a['CAE']) === '') {
        return [null, 'El comprobante asociado (' . $a['comprobante_tipo'] . ' ' . $numero . ') no tiene CAE: ARCA no lo tiene registrado y rechazaría la nota.'];
    }

    $asoc = [
        'tipo' => intval($a['arca_tipo']),
        'pto_vta' => $pto_vta,
        'nro' => intval($a['comprobante_nro']),
        'fecha' => str_replace('-', '', substr((string)$a['f_emision'], 0, 10))
    ];
    $cuit = emp_cuit($conexion, $empresa_id);
    if ($cuit !== '') $asoc['cuit'] = $cuit;
    return [$asoc, null];
}

// ¿Algún comprobante de la empresa (que no sea el de esta factura) ya usa ese número?
function vfArcaNumeroOcupado($conexion, $empresa_id, $comprobante_tipo_id, $punto_venta_id, $numero, $comprobante_id_propio) {
    $sql = "SELECT 1 FROM gestion__comprobantes
            WHERE empresa_id = ? AND comprobante_tipo_id = ? AND comprobante_pv = ? AND comprobante_nro = ?
              AND comprobante_id <> ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'iiiii', $empresa_id, $comprobante_tipo_id, $punto_venta_id, $numero, $comprobante_id_propio);
    mysqli_stmt_execute($stmt);
    $ocupado = (bool) mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $ocupado;
}

// Lo que ARCA tiene autorizado con ese número, ¿es esta factura? (conciliación tras un timeout)
function vfArcaCoincide($autorizado, $datos) {
    return ($autorizado['Resultado'] ?? '') === 'A'
        && ($autorizado['CodAutorizacion'] ?? '') !== ''
        && ltrim((string)($autorizado['DocNro'] ?? ''), '0') === ltrim((string)$datos['doc_nro'], '0')
        && abs(floatval($autorizado['ImpTotal'] ?? 0) - floatval($datos['imp_total'])) < 0.01
        && (string)($autorizado['CbteFch'] ?? '') === (string)$datos['fecha'];
}

function vfArcaAutorizarFactura($conexion, $factura, $fiscal, $empresa_id, $pagina_id) {
    $id = intval($factura['venta_factura_id']);
    error_log("=== INICIO vfArcaAutorizarFactura ID: $id ===");
    if (trim((string)($factura['CAE'] ?? '')) !== '') return ['success' => false, 'error' => 'La factura ya tiene CAE.'];

    // 0. Centavo de redondeo entre el total y sus componentes (facturas guardadas antes del ajuste)
    try {
        $factura = vfCuadrarTotalFactura($conexion, $empresa_id, $factura, $pagina_id);
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    // 1. Asegurar que tenga número asignado por el sistema
    if (empty($factura['comprobante_nro']) || intval($factura['comprobante_nro']) <= 0) {
        $nro_nuevo = vfObtenerProximoNumero($conexion, $empresa_id, intval($factura['punto_venta_id']), intval($factura['comprobante_tipo_id']));
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_nro = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $nro_nuevo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $factura['comprobante_nro'] = $nro_nuevo;
    }
    $nro_sistema = intval($factura['comprobante_nro']);

    // 2. Validar que no haya comprobantes anteriores sin CAE
    try {
        vfValidarComprobantesAnterioresConCae(
            $conexion,
            $empresa_id,
            intval($factura['punto_venta_id']),
            intval($factura['comprobante_tipo_id']),
            $id,
            $nro_sistema
        );
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    [$cfg, $error] = vfArcaConfiguracion($conexion, $empresa_id);
    if ($error !== null) return ['success' => false, 'error' => $error];
    $armado = vfArcaDatosFactura($conexion, $factura, $fiscal, $empresa_id);
    if ($armado['error'] !== null) return ['success' => false, 'error' => $armado['error']];
    $datos = $armado['datos'];
    $cfg['referencia_tipo'] = 'gestion__ventas_facturas';
    $cfg['referencia_id'] = $id;
    @set_time_limit(180);

    // ARCA numera en orden: una sola autorización a la vez por PV fiscal + tipo
    $lock = 'vf_arca_' . intval($empresa_id) . '_' . $datos['pto_vta'] . '_' . $datos['cbte_tipo'];
    $stmt = mysqli_prepare($conexion, "SELECT GET_LOCK(?, 30)");
    mysqli_stmt_bind_param($stmt, 's', $lock);
    mysqli_stmt_execute($stmt);
    $tomado = intval(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0) === 1;
    mysqli_stmt_close($stmt);
    if (!$tomado) return ['success' => false, 'error' => 'Otra factura de ese punto de venta se está autorizando en ARCA. Reintente en unos segundos.'];

    try {
        // Releer bajo el lock
        $stmt = mysqli_prepare($conexion, "SELECT tabla_estado_registro_id, CAE, comprobante_nro FROM gestion__ventas_facturas WHERE venta_factura_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$actual || trim((string)$actual['CAE']) !== '') {
            return ['success' => false, 'error' => 'La factura ya tiene CAE asignado o fue modificada. Actualice la pantalla.'];
        }

        $ultimo = arca_wsfe_ultimo_autorizado($conexion, $cfg, $datos['pto_vta'], $datos['cbte_tipo']);
        if (empty($ultimo['ok'])) return ['success' => false, 'error' => 'No se pudo consultar ARCA: ' . ($ultimo['error'] ?? 'error desconocido')];
        $n = intval($ultimo['data']['CbteNro']);

        // Conciliación: si ARCA ya autorizó nuestro número de sistema
        $autorizacion = null;
        if ($n >= $nro_sistema) {
            $cons = arca_wsfe_comp_consultar($conexion, $cfg, $datos['pto_vta'], $datos['cbte_tipo'], $nro_sistema);
            if (!empty($cons['ok']) && vfArcaCoincide($cons['data'], $datos)) {
                $autorizacion = ['CbteNro' => $nro_sistema, 'CAE' => $cons['data']['CodAutorizacion'], 'CAEFchVto' => $cons['data']['FchVto'], 'conciliado' => true];
            }
        }

        if (!$autorizacion) {
            $proximo_arca = $n + 1;
            if ($nro_sistema !== $proximo_arca) {
                // El número lo da ARCA: el interno se asignó al confirmar con el numerador de ese momento (p. ej.
                // antes de corregir el último número en Puntos de Venta, o con comprobantes emitidos fuera del
                // sistema) y no se recalcula. Solo es un error si otro comprobante del sistema ya tiene ese número.
                if (vfArcaNumeroOcupado($conexion, $empresa_id, intval($factura['comprobante_tipo_id']), intval($factura['punto_venta_id']), $proximo_arca, intval($factura['comprobante_id'] ?? 0))) {
                    return ['success' => false, 'error' => 'Desincronización de numeración con ARCA: el próximo número a autorizar en ARCA es el '
                        . str_pad($datos['pto_vta'], 5, '0', STR_PAD_LEFT) . '-' . str_pad($proximo_arca, 8, '0', STR_PAD_LEFT)
                        . ', pero en el sistema ya hay otro comprobante de ese tipo con ese número. Revise los comprobantes de ese punto de venta.'];
                }
                error_log("=== vfArcaAutorizarFactura ID: $id renumera $nro_sistema -> $proximo_arca (próximo de ARCA) ===");
                // Antes de pedir el CAE: si la respuesta se pierde, el próximo intento concilia con este número
                $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_nro = ? WHERE venta_factura_id = ? AND empresa_id = ?");
                mysqli_stmt_bind_param($stmt, 'iii', $proximo_arca, $id, $empresa_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                // Las próximas facturas del punto de venta siguen desde ese número
                $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_numeradores SET ultimo_numero = GREATEST(ultimo_numero, ?)
                                                   WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?");
                $pv_id = intval($factura['punto_venta_id']);
                $tipo_id = intval($factura['comprobante_tipo_id']);
                mysqli_stmt_bind_param($stmt, 'iiii', $proximo_arca, $empresa_id, $pv_id, $tipo_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $nro_sistema = $proximo_arca;
                $factura['comprobante_nro'] = $proximo_arca;
            }
            $datos['cbte_nro_esperado'] = $nro_sistema;
            $r = arca_wsfe_solicitar_cae($conexion, $cfg, $datos);
            if (empty($r['ok'])) {
                $mensaje = $r['error'] ?? 'ARCA no autorizó la factura.';
                foreach (($r['data']['observaciones'] ?? []) as $obs) $mensaje .= ' | ' . $obs['codigo'] . ': ' . $obs['mensaje'];
                return ['success' => false, 'error' => $mensaje];
            }
            $autorizacion = $r['data'];
        }

        return vfArcaGuardarAutorizacion($conexion, $factura, $autorizacion, $datos, $empresa_id, $pagina_id);
    } finally {
        $stmt = mysqli_prepare($conexion, "SELECT RELEASE_LOCK(?)");
        mysqli_stmt_bind_param($stmt, 's', $lock);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Datos de gestion__comprobantes para syncComprobante() desde la fila de la factura.
function vfDatosComprobanteFactura($factura, $empresa_id, $estado_id) {
    return [
        'empresa_id' => $empresa_id, 'sucursal_id' => $factura['sucursal_id'], 'comprobante_pv' => $factura['punto_venta_id'],
        'comprobante_tipo_id' => $factura['comprobante_tipo_id'], 'comprobante_nro' => $factura['comprobante_nro'],
        'entidad_id' => $factura['entidad_id'], 'entidad_sucursal_id' => $factura['entidad_sucursal_id'],
        'f_emision' => $factura['f_emision'], 'f_contabilidad' => $factura['f_contabilidad'], 'f_vto' => $factura['f_vto'],
        'moneda_id' => $factura['moneda_id'], 'tipo_cambio' => $factura['tipo_cambio'],
        'registro_origen_id' => $factura['venta_factura_id'], 'tabla_estado_registro_id' => $estado_id,
        'usuario_id' => $_SESSION['usuario_id'] ?? 0, 'observaciones' => $factura['observaciones'],
        'importe_neto' => floatval($factura['importe_neto']), 'descuento_general' => floatval($factura['descuento_general']),
        'importe_no_gravado' => floatval($factura['importe_no_gravado']), 'importe_exento' => floatval($factura['importe_exento']),
        'importe_iva' => floatval($factura['importe_iva']), 'importe_otros_impuestos' => floatval($factura['importe_otros_impuestos']),
        'importe_total' => floatval($factura['importe_total'])
    ];
}

// Con el CAE en mano: número de ARCA, CAE, Confirmado, comprobante y asiento, todo junto.
function vfArcaGuardarAutorizacion($conexion, $factura, $aut, $datos, $empresa_id, $pagina_id) {
    $id = intval($factura['venta_factura_id']);
    $nro = intval($aut['CbteNro']);
    $cae = trim((string)$aut['CAE']);
    $vto_dt = DateTime::createFromFormat('Ymd', preg_replace('/\D/', '', (string)($aut['CAEFchVto'] ?? '')));
    $vto = $vto_dt ? $vto_dt->format('Y-m-d') : null;
    $numero_txt = str_pad($datos['pto_vta'], 5, '0', STR_PAD_LEFT) . '-' . str_pad($nro, 8, '0', STR_PAD_LEFT);
    $estado_confirmado = 5;

    mysqli_begin_transaction($conexion);
    try {
        $estado_origen = intval($factura['tabla_estado_registro_id']);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas
                                           SET comprobante_nro = ?, CAE = ?, f_vto_CAE = ?, tabla_estado_registro_id = ?
                                           WHERE venta_factura_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?");
        mysqli_stmt_bind_param($stmt, 'issiiii', $nro, $cae, $vto, $estado_confirmado, $id, $empresa_id, $estado_origen);
        mysqli_stmt_execute($stmt);
        $afectadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($afectadas !== 1) throw new Exception('La factura cambió de estado.');

        $factura['comprobante_nro'] = $nro;
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        $comprobante_id = syncComprobante($conexion, vfDatosComprobanteFactura($factura, $empresa_id, $estado_confirmado), $tabla_origen_id);
        if (!$comprobante_id) throw new Exception('No se pudo sincronizar el comprobante.');
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_id = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        vfSincronizarStockFactura($conexion, $factura, $comprobante_id, $estado_confirmado, $tabla_origen_id, $empresa_id);

        $res_asiento = vfGenerarAsientoContableFactura($conexion, $id, $empresa_id, true);
        if (!$res_asiento['success']) throw new Exception('Error generando asiento contable: ' . ($res_asiento['error'] ?? $res_asiento['message'] ?? 'Error desconocido'));

        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("ERROR vfArcaGuardarAutorizacion factura $id (N° $numero_txt CAE $cae): " . $e->getMessage());
        return ['success' => false, 'error' => "ARCA autorizó la factura (N° $numero_txt, CAE $cae) pero no se pudo guardar: " . $e->getMessage()
            . ' Corrija el problema y vuelva a aprobar: se recupera la misma autorización sin pedir otro CAE.'];
    }

    return [
        'success' => true,
        'comprobante_nro' => $nro,
        'cae' => $cae,
        'message' => (!empty($aut['conciliado']) ? 'Autorización recuperada de ARCA. ' : 'Factura autorizada por ARCA. ')
            . "N° $numero_txt - CAE $cae" . ($vto ? ' (vence ' . fmt_fecha($vto) . ')' : '')
    ];
}

// 4 -> 3: una factura fiscal rechazada por ARCA (o con un dato a corregir) vuelve a Borrador.
// Solo sin CAE: una factura autorizada se anula con nota de crédito. Con ARCA habilitado pierde el
// número (lo vuelve a dar ARCA); sin ARCA conserva el interno, que 'confirma' reutiliza.
function vfVolverABorrador($conexion, $factura, $tipo_fiscal, $con_arca, $estado_destino_id, $empresa_id, $pagina_id) {
    $id = intval($factura['venta_factura_id']);
    if (trim((string)($factura['CAE'] ?? '')) !== '') return ['success' => false, 'error' => 'La factura tiene CAE: no puede volver a Borrador (se anula con nota de crédito).'];
    if (!$tipo_fiscal) return ['success' => false, 'error' => 'Solo las facturas fiscales pendientes de aprobación vuelven a Borrador.'];
    $nro = $con_arca ? 0 : intval($factura['comprobante_nro']);

    mysqli_begin_transaction($conexion);
    try {
        $estado_origen = intval($factura['tabla_estado_registro_id']);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_nro = ?, tabla_estado_registro_id = ?
                                           WHERE venta_factura_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?");
        mysqli_stmt_bind_param($stmt, 'iiiii', $nro, $estado_destino_id, $id, $empresa_id, $estado_origen);
        mysqli_stmt_execute($stmt);
        $afectadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($afectadas !== 1) throw new Exception('La factura cambió de estado.');

        // Asiento generado al confirmar antes de que la factura fiscal esperara el CAE (facturas viejas)
        $comp_id = intval($factura['comprobante_id']);
        if ($comp_id > 0) {
            cont_periodo_exigir_comprobante($conexion, $empresa_id, $comp_id, 'aprobar el comprobante');
            $stmt = mysqli_prepare($conexion, "DELETE d FROM gestion__cont_asientos_detalles d
                                               JOIN gestion__cont_asientos a ON a.cont_asiento_id = d.cont_asiento_id
                                               WHERE a.comprobante_id = ? AND a.empresa_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $comp_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__cont_asientos WHERE comprobante_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $comp_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $factura['comprobante_nro'] = $nro;
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        $comprobante_id = syncComprobante($conexion, vfDatosComprobanteFactura($factura, $empresa_id, $estado_destino_id), $tabla_origen_id);
        if (!$comprobante_id) {
            throw new Exception('No se pudo sincronizar el comprobante.');
        }
        // Hoy no mueve nada (4 no tiene aplica_stock); deja el libro coherente si eso se configura distinto.
        vfSincronizarStockFactura($conexion, $factura, $comprobante_id, $estado_destino_id, $tabla_origen_id, $empresa_id);
        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'La factura volvió a Borrador: corríjala y vuelva a confirmarla.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log('ERROR vfVolverABorrador: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/* =========================================================================
 * NOTAS DE CRÉDITO Y CONTADO A CONSUMIDOR FINAL (decisión Pablo 2026-10-09)
 * _local/sql/mantenimiento/20261009_notas_credito_aplicacion_01.sql
 *
 * - La NC/ND guarda su comprobante asociado (comprobante_asociado_id). Al quedar confirmada (5), una NC
 *   cuyo asociado tiene saldo se le aplica (imputación directa NC -> comprobante, tesoreriaAplicarNotaCredito)
 *   con un recibo en $0 que documenta la aplicación.
 * - Al quedar confirmada una factura/ND de un cliente con NC con saldo, la respuesta trae
 *   notas_credito_disponibles: la pantalla ofrece imputarlas (vfAplicarNotasCredito, también con recibo en $0).
 * - Factura de contado a consumidor final: al quedar confirmada se emite un recibo automático en efectivo a la
 *   caja de la sucursal, imputado a la factura (queda cancelada).
 * ========================================================================= */

// Diferencia aceptada entre los medios del cobro de contado y el total aprobado (redondeo del total).
const VF_CONTADO_TOLERANCIA = 0.05;

/** Tipo de comprobante dependiente (NC/ND): tiene padre en gestion__comprobantes_tipos_dependencias. */
function vfTipoEsNota($conexion, $empresa_id, $tipo_id) {
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM gestion__comprobantes_tipos_dependencias
                                       WHERE comprobante_tipo_id = ? AND empresa_id IN (0, ?) AND tabla_estado_registro_id = 1 LIMIT 1");
    $tipo_id = intval($tipo_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'ii', $tipo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $es = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $es;
}

/** ¿El tipo resta (signo '-', nota de crédito)? */
function vfTipoEsCredito($conexion, $empresa_id, $tipo_id) {
    $stmt = mysqli_prepare($conexion, "SELECT signo FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? AND empresa_id = ?");
    $tipo_id = intval($tipo_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'ii', $tipo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila && in_array(trim((string)$fila['signo']), ['-', '-1'], true);
}

/**
 * Ítems del comprobante asociado a una NC (factura o ND de venta del cliente), para elegir cuáles acreditar.
 * cantidad_disponible = facturada - lo que ya acreditan otras NC vivas asociadas al mismo comprobante (por producto).
 */
function vfItemsComprobanteAsociado($conexion, $empresa_id, $entidad_id, $comprobante_id, $excluir_factura_id = 0) {
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $comprobante_id = intval($comprobante_id);
    $excluir_factura_id = intval($excluir_factura_id);
    $tabla_id = intval(obtenerTablaOrigenPorPagina($conexion, 56) ?: 80);

    $stmt = mysqli_prepare($conexion, "SELECT c.registro_origen_id FROM gestion__comprobantes c
                                       WHERE c.comprobante_id = ? AND c.empresa_id = ? AND c.entidad_id = ? AND c.tabla_origen_id = ?");
    mysqli_stmt_bind_param($stmt, 'iiii', $comprobante_id, $empresa_id, $entidad_id, $tabla_id);
    mysqli_stmt_execute($stmt);
    $origen = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$origen) return [];
    $factura_id = intval($origen['registro_origen_id']);

    $sql = "SELECT d.venta_factura_detalle_id, d.producto_id, p.producto_codigo, p.producto_nombre, d.leyenda,
                   d.cantidad, d.precio_unitario, d.descuento_general_pct, d.iva_alicuota_id, d.porcentaje_iva,
                   COALESCE(nc.acreditado, 0) AS acreditado
            FROM gestion__ventas_facturas_detalles d
            JOIN gestion__ventas_facturas f ON f.venta_factura_id = d.venta_factura_id AND f.empresa_id = ?
            LEFT JOIN gestion__productos p ON p.producto_id = d.producto_id
            LEFT JOIN (SELECT nd.producto_id, SUM(nd.cantidad) AS acreditado
                       FROM gestion__ventas_facturas n
                       JOIN gestion__ventas_facturas_detalles nd ON nd.venta_factura_id = n.venta_factura_id AND nd.tabla_estado_registro_id <> 6
                       WHERE n.empresa_id = ? AND n.comprobante_asociado_id = ? AND n.venta_factura_id <> ?
                         AND n.tabla_estado_registro_id NOT IN (6, 38)
                       GROUP BY nd.producto_id) nc ON nc.producto_id = d.producto_id
            WHERE d.venta_factura_id = ? AND d.tabla_estado_registro_id <> 6
            ORDER BY d.venta_factura_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'iiiii', $empresa_id, $empresa_id, $comprobante_id, $excluir_factura_id, $factura_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    // Lo ya acreditado de un producto se descuenta de sus líneas en orden
    $restante = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $prod = intval($r['producto_id']);
        if (!isset($restante[$prod])) $restante[$prod] = floatval($r['acreditado']);
        $cantidad = floatval($r['cantidad']);
        $usado = min($cantidad, $restante[$prod]);
        $restante[$prod] -= $usado;
        $r['cantidad'] = $cantidad;
        $r['cantidad_disponible'] = round($cantidad - $usado, 4);
        unset($r['acreditado']);
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Comprobantes de venta confirmados del cliente a los que se puede asociar una NC/ND (con su saldo). */
function vfComprobantesAsociables($conexion, $empresa_id, $entidad_id, $tipo_id = 0) {
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $tipo_id = intval($tipo_id);
    if ($entidad_id <= 0) return [];
    $tabla_id = intval(obtenerTablaOrigenPorPagina($conexion, 56) ?: 80);
    // Con tipo de NC/ND: solo los tipos que la dependencia admite como padre
    $filtro_tipo = $tipo_id > 0
        ? " AND c.comprobante_tipo_id IN (SELECT d.comprobante_tipo_padre_id FROM gestion__comprobantes_tipos_dependencias d
                                          WHERE d.comprobante_tipo_id = $tipo_id AND d.empresa_id IN (0, $empresa_id) AND d.tabla_estado_registro_id = 1)"
        : '';
    $sql = "SELECT c.comprobante_id, ct.comprobante_tipo, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv, c.comprobante_nro, c.f_emision,
                   c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id
            FROM gestion__comprobantes c
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            WHERE c.empresa_id = ? AND c.entidad_id = ? AND c.tabla_origen_id = ?
              AND c.tabla_estado_registro_id IN (5, 39, 40) $filtro_tipo
            ORDER BY c.f_emision DESC, c.comprobante_id DESC
            LIMIT 100";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $entidad_id, $tabla_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['comprobante_id'] = intval($r['comprobante_id']);
        $r['importe_total'] = floatval($r['importe_total']);
        $r['importe_pendiente'] = floatval($r['importe_pendiente']);
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Valida y guarda el comprobante asociado de una NC/ND (NULL para facturas). Corre en la transacción del alta/edición. */
function vfGuardarComprobanteAsociado($conexion, $empresa_id, $venta_factura_id, $tipo_id, $entidad_id, $comprobante_asociado_id) {
    $comprobante_asociado_id = intval($comprobante_asociado_id);
    $asociado = null;
    if ($comprobante_asociado_id > 0 && vfTipoEsNota($conexion, $empresa_id, $tipo_id)) {
        $validos = array_column(vfComprobantesAsociables($conexion, $empresa_id, $entidad_id, $tipo_id), 'comprobante_id');
        if (!in_array($comprobante_asociado_id, $validos, true)) {
            throw new Exception('El comprobante asociado no es un comprobante confirmado de este cliente compatible con el tipo de nota.');
        }
        $asociado = $comprobante_asociado_id;
    }
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_asociado_id = ? WHERE venta_factura_id = ? AND empresa_id = ?");
    $venta_factura_id = intval($venta_factura_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'iii', $asociado, $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/** Punto de venta para el recibo automático: el de la factura si emite recibos, si no el primero que los emita. */
function vfPuntoVentaRecibo($conexion, $empresa_id, $punto_venta_id) {
    $conn = $conexion;   // el modelo hace "$conexion = $conn" al cargarse; acá se carga dentro de una función
    require_once __DIR__ . '/cobranzas_model.php';
    if (!empty(obtenerTiposComprobanteCobranza($conexion, $empresa_id, $punto_venta_id, 105))) {
        return intval($punto_venta_id);
    }
    $pvs = obtenerPuntosVentaCobranza($conexion, $empresa_id, 105);
    return !empty($pvs) ? intval($pvs[0]['punto_venta_id']) : intval($punto_venta_id);
}

/**
 * Aplica NC con saldo a un comprobante de venta y emite el recibo en $0 que lo documenta.
 * $aplicaciones: [['nc_comprobante_id' => int, 'importe' => float (<= 0: todo lo posible)], ...]
 * $en_transaccion = true: dentro de la transacción del llamador (confirmación de la NC).
 */
function vfAplicarNotasCredito($conexion, $empresa_id, $destino_comprobante_id, $aplicaciones, $usuario_id, $en_transaccion = false) {
    require_once __DIR__ . '/tesoreria_core_model.php';
    $conn = $conexion;   // el modelo hace "$conexion = $conn" al cargarse; acá se carga dentro de una función
    require_once __DIR__ . '/cobranzas_model.php';
    $empresa_id = intval($empresa_id);
    $destino_comprobante_id = intval($destino_comprobante_id);
    $usuario_id = intval($usuario_id);
    if (empty($aplicaciones)) return ['success' => false, 'error' => 'No se indicaron notas de crédito a aplicar.'];

    $stmt = mysqli_prepare($conexion, "SELECT c.comprobante_id, c.entidad_id, c.sucursal_id, c.moneda_id, c.comprobante_pv, c.comprobante_nro,
                                              ct.comprobante_tipo, vf.punto_venta_id
                                       FROM gestion__comprobantes c
                                       JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                                       LEFT JOIN gestion__ventas_facturas vf ON vf.venta_factura_id = c.registro_origen_id AND vf.comprobante_id = c.comprobante_id
                                       WHERE c.comprobante_id = ? AND c.empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $destino_comprobante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $dest = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$dest) return ['success' => false, 'error' => 'Comprobante destino no encontrado.'];

    if (!$en_transaccion) mysqli_begin_transaction($conexion);
    try {
        $aplicado_total = 0.0;
        $detalle = [];
        $imputacion_ids = [];
        foreach ($aplicaciones as $a) {
            $nc_id = intval($a['nc_comprobante_id'] ?? 0);
            $imputacion_id = null;
            $importe = tesoreriaAplicarNotaCredito($conexion, $empresa_id, $nc_id, $destino_comprobante_id, floatval($a['importe'] ?? 0), $usuario_id, $imputacion_id);
            if ($importe > 0) {
                if ($imputacion_id) $imputacion_ids[] = intval($imputacion_id);
                $aplicado_total += $importe;
                $nro = vfNroComprobante($conexion, $nc_id);
                $detalle[] = $nro . ' por $' . fmt_numero($importe, 2);
            }
        }
        if ($aplicado_total <= 0) throw new Exception('No había saldo para aplicar.');

        // Recibo en $0 que documenta la aplicación
        $pv = vfPuntoVentaRecibo($conexion, $empresa_id, intval($dest['punto_venta_id'] ?: $dest['comprobante_pv']));
        $cobranza_id = guardarCobranzaBorrador($conexion, [
            'empresa_id' => $empresa_id, 'sucursal_id' => intval($dest['sucursal_id']), 'entidad_id' => intval($dest['entidad_id']),
            'comprobante_pv' => $pv, 'moneda_id' => emp_moneda_id($conexion, $empresa_id, $dest['moneda_id']), 'tipo_cambio' => 1,
            'f_emision' => date('Y-m-d'), 'usuario_id' => $usuario_id, 'recibo_cero' => true, 'medios' => [], 'imputaciones' => [],
            'observaciones' => 'Aplicación de NC a ' . $dest['comprobante_tipo'] . ' ' . str_pad(emp_pv_numero($conexion, $empresa_id, $dest['comprobante_pv']), 4, '0', STR_PAD_LEFT)
                             . '-' . str_pad($dest['comprobante_nro'], 8, '0', STR_PAD_LEFT) . ': ' . implode('; ', $detalle),
        ]);
        $rec = confirmarCobranza($conexion, $cobranza_id, $empresa_id, $usuario_id, true);

        // El recibo documenta estas imputaciones: su detalle las muestra (factura +, NC -) y anularlo las deshace
        // (gestion__comprobantes_imputaciones.documento_comprobante_id, 20261103_imputaciones_documento_recibo_01.sql)
        $stmt = mysqli_prepare($conexion, "SELECT comprobante_id FROM gestion__cobranzas WHERE cobranza_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $cobranza_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $recibo_comp_id = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['comprobante_id'] ?? 0);
        mysqli_stmt_close($stmt);
        if ($recibo_comp_id > 0) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_imputaciones SET documento_comprobante_id = ? WHERE imputacion_id = ? AND empresa_id = ?");
            foreach ($imputacion_ids as $imp_id) {
                mysqli_stmt_bind_param($stmt, 'iii', $recibo_comp_id, $imp_id, $empresa_id);
                if (!mysqli_stmt_execute($stmt)) throw new Exception('No se pudo ligar la aplicación al recibo: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }

        if (!$en_transaccion) mysqli_commit($conexion);
        return ['success' => true, 'aplicado' => round($aplicado_total, 2), 'cobranza_id' => $cobranza_id,
                'recibo_nro' => $rec['comprobante_nro'] ?? null,
                'message' => 'Notas de crédito aplicadas por $' . fmt_numero($aplicado_total, 2) . ' (recibo en $0 N° ' . ($rec['comprobante_nro'] ?? '') . ').'];
    } catch (Exception $e) {
        if (!$en_transaccion) mysqli_rollback($conexion);
        if ($en_transaccion) throw $e;
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/** "Tipo PV-NRO" de un comprobante, para textos. */
function vfNroComprobante($conexion, $comprobante_id) {
    $comprobante_id = intval($comprobante_id);
    $res = $conexion->query("SELECT ct.comprobante_tipo, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv, c.comprobante_nro FROM gestion__comprobantes c
                             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                             WHERE c.comprobante_id = $comprobante_id");
    $r = $res ? $res->fetch_assoc() : null;
    return $r ? $r['comprobante_tipo'] . ' ' . str_pad($r['comprobante_pv'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($r['comprobante_nro'], 8, '0', STR_PAD_LEFT) : "#$comprobante_id";
}

/**
 * Lo que pasa cuando un comprobante de venta queda Confirmado (5), dentro de la transacción de la confirmación:
 *  - NC con comprobante asociado con saldo: se le aplica (recibo en $0).
 *  - Factura de contado a consumidor final: recibo automático en efectivo a la caja de la sucursal.
 * Devuelve ['mensajes' => [...]]. Lanza Exception si algo falla (la confirmación se revierte).
 */
function vfPostConfirmacion($conexion, $venta_factura_id, $empresa_id, $usuario_id, $medios_contado = null) {
    require_once __DIR__ . '/tesoreria_core_model.php';
    $venta_factura_id = intval($venta_factura_id);
    $empresa_id = intval($empresa_id);
    $mensajes = [];

    $stmt = mysqli_prepare($conexion, "SELECT vf.venta_factura_id, vf.entidad_id, vf.sucursal_id, vf.punto_venta_id, vf.comprobante_id,
                                              vf.comprobante_asociado_id, vf.moneda_id, ct.signo, c.importe_total, c.importe_pendiente
                                       FROM gestion__ventas_facturas vf
                                       JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vf.comprobante_tipo_id
                                       JOIN gestion__comprobantes c ON c.comprobante_id = vf.comprobante_id
                                       WHERE vf.venta_factura_id = ? AND vf.empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $venta_factura_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$f) return ['mensajes' => $mensajes];

    // NC asociada a un comprobante con saldo
    if ($f['signo'] === '-' && intval($f['comprobante_asociado_id']) > 0) {
        $asociado = intval($f['comprobante_asociado_id']);
        $saldo_asociado = floatval($conexion->query("SELECT importe_pendiente FROM gestion__comprobantes WHERE comprobante_id = $asociado AND tabla_estado_registro_id IN (5, 39)")->fetch_assoc()['importe_pendiente'] ?? 0);
        if ($saldo_asociado > 0.001) {
            $r = vfAplicarNotasCredito($conexion, $empresa_id, $asociado,
                                       [['nc_comprobante_id' => intval($f['comprobante_id']), 'importe' => 0]], $usuario_id, true);
            $mensajes[] = $r['message'];
        } else {
            $mensajes[] = 'El comprobante asociado no tiene saldo: la nota de crédito queda con saldo a favor del cliente.';
        }
    }

    // NC a consumidor final (devolución en mostrador): lo que no se aplicó se devuelve en efectivo
    // automáticamente con una OP desde la caja de la sucursal, imputada a la NC.
    if ($f['signo'] === '-' && empty($f['entidad_id'])) {
        $nc_id = intval($f['comprobante_id']);
        $saldo_nc = round(floatval($conexion->query("SELECT importe_pendiente FROM gestion__comprobantes WHERE comprobante_id = $nc_id")->fetch_assoc()['importe_pendiente'] ?? 0), 2);
        if ($saldo_nc > 0.001) {
            $conn = $conexion;   // el modelo hace "$conexion = $conn" al cargarse; acá se carga dentro de una función
            require_once __DIR__ . '/pagos_model.php';
            $sucursal_id = intval($f['sucursal_id']);
            $caja = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas
                                      WHERE empresa_id = $empresa_id AND cuenta_tipo = 'CAJA' AND tabla_estado_registro_id = 1
                                      ORDER BY (sucursal_id = $sucursal_id) DESC, tesoreria_cuenta_id LIMIT 1")->fetch_assoc();
            $efectivo = $conexion->query("SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id = $empresa_id AND medio_pago_tipo = 'EFECTIVO' AND tabla_estado_registro_id = 1
                                          ORDER BY medio_pago_id LIMIT 1")->fetch_assoc();
            if (!$caja || !$efectivo) {
                throw new Exception('No hay caja o medio de pago en efectivo configurado para devolver el dinero de la nota de crédito a consumidor final.');
            }
            $pago_id = guardarPagoBorrador($conexion, [
                'empresa_id' => $empresa_id, 'sucursal_id' => $sucursal_id, 'entidad_id' => 0, 'consumidor_final' => true,
                'moneda_id' => emp_moneda_id($conexion, $empresa_id, $f['moneda_id']), 'tipo_cambio' => 1, 'f_emision' => date('Y-m-d'), 'usuario_id' => $usuario_id,
                'observaciones' => 'Devolución en efectivo - nota de crédito a consumidor final',
                'medios' => [['medio_pago_id' => intval($efectivo['medio_pago_id']), 'importe' => $saldo_nc, 'importe_comprobante' => $saldo_nc,
                              'moneda_id' => emp_moneda_id($conexion, $empresa_id, $f['moneda_id']), 'tipo_cambio' => 1, 'tesoreria_cuenta_id' => intval($caja['tesoreria_cuenta_id'])]],
                'imputaciones' => [['comprobante_destino_id' => $nc_id, 'importe_imputado' => $saldo_nc]],
            ]);
            $op = confirmarPago($conexion, $pago_id, $empresa_id, $usuario_id, true);
            $mensajes[] = 'Devolución en efectivo de $' . fmt_numero($saldo_nc, 2) . ' registrada en caja (OP N° ' . ($op['comprobante_nro'] ?? '') . ').';
        }
    }

    // Contado a consumidor final: recibo automático con los medios que cargó el usuario al aprobar
    // ($medios_contado, ya validados en vfEjecutarTransicionEstado) o, si no vinieron (facturación
    // de abonos del estudio, llamadas sin pantalla), en efectivo a la caja de la sucursal.
    if ($f['signo'] === '+' && empty($f['entidad_id']) && floatval($f['importe_pendiente']) > 0.001) {
        $conn = $conexion;   // el modelo hace "$conexion = $conn" al cargarse; acá se carga dentro de una función
        require_once __DIR__ . '/cobranzas_model.php';
        $sucursal_id = intval($f['sucursal_id']);
        $importe = round(floatval($f['importe_pendiente']), 2);
        $moneda_id = emp_moneda_id($conexion, $empresa_id, $f['moneda_id']);
        if ($medios_contado !== null) {
            $val = vfValidarMediosCobro($conexion, $empresa_id, $medios_contado, true);
            if (isset($val['error'])) throw new Exception($val['error']);
            $dif = round($importe - $val['total'], 2);
            if (abs($dif) > VF_CONTADO_TOLERANCIA) {
                throw new Exception('Los medios de pago ($' . fmt_numero($val['total'], 2) . ') tienen que sumar el total de la factura ($' . fmt_numero($importe, 2) . ').');
            }
            $medios = $val['medios'];
            // Centavos de redondeo del total al aprobar: los absorbe el último medio
            $ult = count($medios) - 1;
            $medios[$ult]['importe'] = round($medios[$ult]['importe'] + $dif, 2);
            $medios[$ult]['importe_comprobante'] = $medios[$ult]['importe'];
            foreach ($medios as $i => $m) {
                $medios[$i]['moneda_id'] = $moneda_id;
                $medios[$i]['tipo_cambio'] = 1;
            }
        } else {
            $caja = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas
                                      WHERE empresa_id = $empresa_id AND cuenta_tipo = 'CAJA' AND tabla_estado_registro_id = 1
                                      ORDER BY (sucursal_id = $sucursal_id) DESC, tesoreria_cuenta_id LIMIT 1")->fetch_assoc();
            $efectivo = $conexion->query("SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id = $empresa_id AND medio_pago_tipo = 'EFECTIVO' AND tabla_estado_registro_id = 1
                                          ORDER BY medio_pago_id LIMIT 1")->fetch_assoc();
            if (!$caja || !$efectivo) {
                throw new Exception('No hay caja o medio de pago en efectivo configurado para cobrar la venta de contado a consumidor final.');
            }
            $medios = [['medio_pago_id' => intval($efectivo['medio_pago_id']), 'importe' => $importe, 'importe_comprobante' => $importe,
                        'moneda_id' => $moneda_id, 'tipo_cambio' => 1, 'tesoreria_cuenta_id' => intval($caja['tesoreria_cuenta_id'])]];
        }
        $cobranza_id = guardarCobranzaBorrador($conexion, [
            'empresa_id' => $empresa_id, 'sucursal_id' => $sucursal_id, 'entidad_id' => 0, 'consumidor_final' => true,
            'comprobante_pv' => vfPuntoVentaRecibo($conexion, $empresa_id, intval($f['punto_venta_id'])),
            'moneda_id' => $moneda_id, 'tipo_cambio' => 1, 'f_emision' => date('Y-m-d'), 'usuario_id' => $usuario_id,
            'observaciones' => 'Cobro de contado - consumidor final',
            'medios' => $medios,
            'imputaciones' => [['comprobante_destino_id' => intval($f['comprobante_id']), 'importe_imputado' => $importe]],
        ]);
        $rec = confirmarCobranza($conexion, $cobranza_id, $empresa_id, $usuario_id, true);
        $mensajes[] = 'Cobro de contado registrado en caja (recibo N° ' . ($rec['comprobante_nro'] ?? '') . ').';
    }

    return ['mensajes' => $mensajes];
}

/** NC con saldo que la pantalla puede ofrecer imputar a un comprobante recién confirmado (factura o ND). */
function vfNotasCreditoDisponiblesFactura($conexion, $venta_factura_id, $empresa_id) {
    require_once __DIR__ . '/tesoreria_core_model.php';
    $venta_factura_id = intval($venta_factura_id);
    $empresa_id = intval($empresa_id);
    $f = $conexion->query("SELECT vf.comprobante_id, ct.signo, c.importe_pendiente, c.tabla_estado_registro_id
                           FROM gestion__ventas_facturas vf
                           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vf.comprobante_tipo_id
                           JOIN gestion__comprobantes c ON c.comprobante_id = vf.comprobante_id
                           WHERE vf.venta_factura_id = $venta_factura_id AND vf.empresa_id = $empresa_id")->fetch_assoc();
    if (!$f || $f['signo'] !== '+' || floatval($f['importe_pendiente']) <= 0.001 || !in_array(intval($f['tabla_estado_registro_id']), [5, 39], true)) {
        return null;
    }
    $ncs = tesoreriaNotasCreditoDisponibles($conexion, $empresa_id, intval($f['comprobante_id']));
    if (empty($ncs)) return null;
    return ['comprobante_id' => intval($f['comprobante_id']), 'saldo' => floatval($f['importe_pendiente']), 'notas' => $ncs];
}

?>
