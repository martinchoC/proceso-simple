<?php
/**
 * empresas_cuentas_conceptos_model.php
 *
 * Modelo para la configuración de Conceptos de Cuentas Contables por Empresa (D35 / D36)
 * Sistema gestion_multipyme - Módulo Gestión
 */

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// Mapeo oficial de descripciones legibles según especificación D35 (§4.10)
const CONCEPTOS_DESCRIPCIONES = [
    'DEUDORES_VENTAS' => [
        'titulo'      => 'Deudores por Ventas',
        'subtitulo'   => 'Clientes sin cuenta contable propia',
        'descripcion' => 'Debe de las facturas de venta y Haber de los recibos de clientes que no tienen cuenta propia; ajuste de redondeo de ventas.'
    ],
    'VENTAS' => [
        'titulo'      => 'Ventas (por defecto)',
        'subtitulo'   => 'Ingreso cuando el producto no define cuenta',
        'descripcion' => 'Cuenta de ventas de un renglón cuyo producto, tipo de producto y categoría no tienen cuenta de venta.'
    ],
    'IVA_DEBITO' => [
        'titulo'      => 'IVA Débito Fiscal',
        'subtitulo'   => 'Alícuotas sin cuenta de venta',
        'descripcion' => 'IVA de las facturas de venta cuando la alícuota no tiene cuenta contable de venta.'
    ],
    'PROVEEDORES' => [
        'titulo'      => 'Proveedores',
        'subtitulo'   => 'Proveedores sin cuenta contable propia',
        'descripcion' => 'Cuenta del proveedor en órdenes de pago, devoluciones de proveedores y reembolsos de rendiciones.'
    ],
    'VALORES_CARTERA' => [
        'titulo'      => 'Valores en Cartera',
        'subtitulo'   => 'Cheques de terceros recibidos',
        'descripcion' => 'Contrapartida al depositar cheques de terceros en cartera.'
    ],
    'IVA_A_PAGAR' => [
        'titulo'      => 'IVA a Pagar',
        'subtitulo'   => 'Liquidaciones impositivas',
        'descripcion' => 'Haber de la liquidación impositiva (DDJJ) cuando no se indica otra cuenta.'
    ],
    'GASTOS_GENERALES' => [
        'titulo'      => 'Otros Gastos Generales',
        'subtitulo'   => 'Gastos sin categoría contable',
        'descripcion' => 'Devengamiento de obligaciones impositivas e ítems de rendición sin cuenta propia.'
    ],
    'MERCADERIAS' => [
        'titulo'      => 'Mercaderías',
        'subtitulo'   => 'Cuenta legacy del alta de productos',
        'descripcion' => 'Se completa en la columna cont_cuenta_id (NOT NULL) al dar de alta un producto.'
    ],
    'VALORES_A_PAGAR' => [
        'titulo'      => 'Valores a Pagar',
        'subtitulo'   => 'Cheques propios emitidos / diferidos',
        'descripcion' => 'Contrapartida contable pasiva al emitir órdenes de pago con cheques diferidos propios (D28 / D35).'
    ],
    'IVA_SALDO_FAVOR' => [
        'titulo'      => 'IVA Saldo a Favor',
        'subtitulo'   => 'Posición mensual de IVA',
        'descripcion' => 'Saldo técnico a favor que deja la liquidación de IVA del mes (página IVA - Posición Mensual) y que se consume en el siguiente.'
    ],
    'EMPLEADOS' => [
        'titulo'      => 'Empleados - Anticipos y Rendiciones',
        'subtitulo'   => 'Cuenta corriente deudora/acreedora del personal',
        'descripcion' => 'Imputación de anticipos entregados (RG_ANT) y rendiciones de gastos aprobadas pendientes de reembolso (RG_REND / RG_REEM).'
    ]
];

function obtenerDescripcionConcepto($codigo)
{
    $codigo = trim(strtoupper($codigo));
    if (isset(CONCEPTOS_DESCRIPCIONES[$codigo])) {
        return CONCEPTOS_DESCRIPCIONES[$codigo];
    }
    return [
        'titulo'      => ucwords(strtolower(str_replace('_', ' ', $codigo))),
        'subtitulo'   => 'Concepto de sistema',
        'descripcion' => 'Cuenta contable por defecto configurada para el concepto ' . $codigo
    ];
}

function obtenerFuncionesPaginaConceptos($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
            AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.orden";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $funciones = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $funciones[] = $f;
    }
    mysqli_stmt_close($stmt);
    return $funciones;
}

function obtenerConceptosEmpresa($conexion, $empresa_id, $pagina_id = 101)
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT ecc.empresa_cuenta_concepto_id, ecc.empresa_id, ecc.concepto,
                   ecc.cont_cuenta_id, ecc.tabla_estado_registro_id,
                   cc.codigo AS cuenta_codigo, cc.nombre AS cuenta_nombre,
                   cc.es_imputable, cc.tabla_estado_registro_id AS cuenta_estado_id
            FROM gestion__empresas_cuentas_conceptos ecc
            LEFT JOIN gestion__cont_cuentas cc 
                 ON cc.cont_cuenta_id = ecc.cont_cuenta_id AND cc.empresa_id = ecc.empresa_id
            WHERE ecc.empresa_id = ?
            ORDER BY ecc.concepto ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerConceptosEmpresa: " . mysqli_error($conexion));
        return ['data' => []];
    }
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $funciones = obtenerFuncionesPaginaConceptos($conexion, $pagina_id);

    $datos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $id = intval($row['empresa_cuenta_concepto_id']);
        $concepto = $row['concepto'];
        $info_desc = obtenerDescripcionConcepto($concepto);

        // Cuenta asignada badge
        if (!empty($row['cont_cuenta_id']) && !empty($row['cuenta_codigo'])) {
            $cuenta_badge = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-7">
                                <i class="fas fa-check-circle me-1"></i><strong>' . htmlspecialchars($row['cuenta_codigo']) . '</strong> - ' . htmlspecialchars($row['cuenta_nombre']) . '
                             </span>';
        } else {
            $cuenta_badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-7">
                                <i class="fas fa-exclamation-triangle me-1"></i>Sin cuenta asignada (Requerida)
                             </span>';
        }

        // Estado
        $estado_badge = (intval($row['tabla_estado_registro_id']) === 1)
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>';

        // Botones de acción
        $botones_html = '<div class="btn-group btn-group-sm" role="group">';
        $botones_html .= '<button type="button" class="btn btn-sm btn-primary btn-accion" 
                            data-id="' . $id . '" 
                            data-accion="editar" 
                            title="Asignar o Modificar Cuenta Contable">
                            <i class="fas fa-edit me-1"></i>Asignar Cuenta
                          </button>';
        $botones_html .= '</div>';

        $datos[] = [
            'id'            => $id,
            'concepto_cod'  => '<code class="fw-bold text-dark fs-6">' . htmlspecialchars($concepto) . '</code>',
            'titulo'        => '<div class="fw-bold">' . htmlspecialchars($info_desc['titulo']) . '</div><small class="text-muted">' . htmlspecialchars($info_desc['subtitulo']) . '</small>',
            'descripcion'   => '<small class="text-muted">' . htmlspecialchars($info_desc['descripcion']) . '</small>',
            'cuenta'        => $cuenta_badge,
            'estado'        => $estado_badge,
            'acciones'      => $botones_html
        ];
    }
    mysqli_stmt_close($stmt);

    return ['data' => $datos];
}

function obtenerConceptoPorId($conexion, $id, $empresa_id)
{
    $id = intval($id);
    $empresa_id = intval($empresa_id);

    $sql = "SELECT ecc.empresa_cuenta_concepto_id, ecc.empresa_id, ecc.concepto,
                   ecc.cont_cuenta_id, ecc.tabla_estado_registro_id,
                   cc.codigo AS cuenta_codigo, cc.nombre AS cuenta_nombre
            FROM gestion__empresas_cuentas_conceptos ecc
            LEFT JOIN gestion__cont_cuentas cc 
                 ON cc.cont_cuenta_id = ecc.cont_cuenta_id AND cc.empresa_id = ecc.empresa_id
            WHERE ecc.empresa_cuenta_concepto_id = ? AND ecc.empresa_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($row) {
        $desc = obtenerDescripcionConcepto($row['concepto']);
        $row['titulo'] = $desc['titulo'];
        $row['subtitulo'] = $desc['subtitulo'];
        $row['descripcion_larga'] = $desc['descripcion'];
    }

    return $row;
}

function obtenerCuentasContablesImputables($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    $sql = "SELECT cont_cuenta_id, codigo, nombre
            FROM gestion__cont_cuentas
            WHERE empresa_id = ? 
              AND es_imputable = 1 
              AND tabla_estado_registro_id = 1
            ORDER BY codigo ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cuentas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $cuentas[] = [
            'id'     => intval($r['cont_cuenta_id']),
            'codigo' => $r['codigo'],
            'nombre' => $r['nombre']
        ];
    }
    mysqli_stmt_close($stmt);
    return $cuentas;
}

function guardarCuentaConcepto($conexion, $id, $empresa_id, $cont_cuenta_id, $usuario_id)
{
    $id = intval($id);
    $empresa_id = intval($empresa_id);
    $cont_cuenta_id = intval($cont_cuenta_id);
    $usuario_id = intval($usuario_id);

    if ($id <= 0 || $empresa_id <= 0) {
        return ['success' => false, 'error' => 'Identificador de concepto o empresa inválido.'];
    }
    if ($cont_cuenta_id <= 0) {
        return ['success' => false, 'error' => 'Debe seleccionar una cuenta contable válida.'];
    }

    // Validar que la cuenta pertenezca a la empresa, sea imputable y esté activa (§4.10)
    $sql_val = "SELECT cont_cuenta_id, codigo, nombre, es_imputable, tabla_estado_registro_id
                FROM gestion__cont_cuentas
                WHERE cont_cuenta_id = ? AND empresa_id = ? LIMIT 1";
    $stmt_v = mysqli_prepare($conexion, $sql_val);
    if (!$stmt_v) return ['success' => false, 'error' => 'Error preparando validación: ' . mysqli_error($conexion)];
    mysqli_stmt_bind_param($stmt_v, "ii", $cont_cuenta_id, $empresa_id);
    mysqli_stmt_execute($stmt_v);
    $res_v = mysqli_stmt_get_result($stmt_v);
    $cta = mysqli_fetch_assoc($res_v);
    mysqli_stmt_close($stmt_v);

    if (!$cta) {
        return ['success' => false, 'error' => 'La cuenta contable seleccionada no existe en el plan de la empresa.'];
    }
    if (intval($cta['es_imputable']) !== 1) {
        return ['success' => false, 'error' => "La cuenta {$cta['codigo']} - {$cta['nombre']} es una cuenta título y no puede imputarse."];
    }
    if (intval($cta['tabla_estado_registro_id']) !== 1) {
        return ['success' => false, 'error' => "La cuenta {$cta['codigo']} - {$cta['nombre']} está inactiva."];
    }

    // Actualizar concepto
    $sql_upd = "UPDATE gestion__empresas_cuentas_conceptos 
                SET cont_cuenta_id = ?, fecha_modificacion = NOW(), usuario_modificacion_id = ?
                WHERE empresa_cuenta_concepto_id = ? AND empresa_id = ?";
    $stmt_u = mysqli_prepare($conexion, $sql_upd);
    if (!$stmt_u) return ['success' => false, 'error' => 'Error preparando actualización: ' . mysqli_error($conexion)];
    mysqli_stmt_bind_param($stmt_u, "iiii", $cont_cuenta_id, $usuario_id, $id, $empresa_id);
    $ok = mysqli_stmt_execute($stmt_u);
    $err = mysqli_stmt_error($stmt_u);
    mysqli_stmt_close($stmt_u);

    if (!$ok) {
        return ['success' => false, 'error' => 'Error guardando asignación: ' . $err];
    }

    return [
        'success' => true,
        'message' => "Cuenta {$cta['codigo']} - {$cta['nombre']} asignada exitosamente."
    ];
}
