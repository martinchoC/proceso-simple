<?php
/**
 * Asistente IA: herramientas que la IA puede usar. Las de consulta leen la base filtrando siempre por la empresa
 * de la sesión y por los accesos por sucursal del usuario; las "proponer_*" NO graban: validan, calculan precios y
 * totales y dejan una propuesta en gestion__ia_asistente_propuestas que el usuario confirma en pantalla
 * (asis_confirmar_propuesta, asistente_service.php).
 *
 * Usan los modelos del módulo activo (ventas_facturas_model.php y ventas_remitos_model.php de modules/gestion o
 * modules/gestion_normal), que incluye asistente_service.php según $ctx['modulo_dir'].
 *
 * $ctx: ['c' => mysqli, 'empresa_id', 'usuario_id', 'modulo_id', 'modulo_dir',
 *        'paginas' => ['facturas', 'remitos', 'clientes', 'proveedores'], 'conversacion_id']
 */

/** Definiciones en JSON Schema filtradas por los permisos del usuario (las convierte cada proveedor). */
function asis_herramientas(array $ctx = []): array
{
    $item = [
        'type' => 'object',
        'properties' => [
            'producto_id' => ['type' => 'integer', 'description' => 'Id devuelto por buscar_productos (no hace falta en una línea de remito)'],
            'venta_remito_detalle_id' => ['type' => 'integer', 'description' => 'Solo facturas: línea de un remito pendiente '
                . '(remitos_pendientes_facturar). Producto, precio, descuento e IVA salen del remito y la factura descuenta lo pendiente'],
            'cantidad' => ['type' => 'number', 'description' => 'En una línea de remito, omitir = todo lo pendiente'],
            'precio_unitario' => ['type' => 'number', 'description' => 'Sin IVA. Omitir para usar el de la lista de precios del cliente'],
            'descuento_pct' => ['type' => 'number', 'description' => 'Omitir para usar el descuento general del cliente'],
            'leyenda' => ['type' => 'string', 'description' => 'Texto opcional debajo del producto (solo facturas)'],
        ],
        'required' => [],
    ];
    $comprobante = [
        'cliente_id' => ['type' => 'integer', 'description' => 'entidad_id del cliente; 0 = consumidor final'],
        'consumidor_final_nombre' => ['type' => 'string', 'description' => 'Solo si cliente_id = 0'],
        'entidad_sucursal_id' => ['type' => 'integer', 'description' => 'Sucursal del cliente; omitir = la primera'],
        'punto_venta_id' => ['type' => 'integer', 'description' => 'Id devuelto por opciones_comprobante'],
        'comprobante_tipo_id' => ['type' => 'integer', 'description' => 'Id devuelto por opciones_comprobante'],
        'fecha' => ['type' => 'string', 'description' => 'AAAA-MM-DD; omitir = hoy'],
        'observaciones' => ['type' => 'string'],
        'items' => ['type' => 'array', 'items' => $item],
    ];
    $factura = $comprobante + [
        'remitos' => ['type' => 'array', 'items' => ['type' => 'integer'],
                      'description' => 'venta_remito_id de remitos pendientes (remitos_pendientes_facturar): factura todo lo pendiente de cada uno'],
    ];
    $base = [
        [
            'name' => 'buscar_entidades',
            'description' => 'Busca clientes, proveedores, empleados/socios u organismos de la empresa por nombre, nombre de fantasía o CUIT/CUIL. '
                . 'Devuelve hasta 10 con su condición frente al IVA, roles y sucursales.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'texto' => ['type' => 'string', 'description' => 'Parte del nombre o el CUIT/CUIL (con o sin guiones)'],
                'rol' => ['type' => 'string', 'enum' => ['cliente', 'proveedor', 'empleado', 'organismo', 'cualquiera']],
            ], 'required' => ['texto', 'rol']],
        ],
        [
            'name' => 'buscar_productos',
            'description' => 'Busca productos activos de la empresa por código o palabras del nombre (todas deben aparecer). '
                . 'Devuelve hasta 20 con el precio de la lista del cliente (sin IVA) y la alícuota de IVA. '
                . 'Si no encuentra, probá con menos palabras o sinónimos.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'texto' => ['type' => 'string'],
                'cliente_id' => ['type' => 'integer', 'description' => 'Para el precio de su lista; 0 = consumidor final'],
            ], 'required' => ['texto']],
        ],
        [
            'name' => 'opciones_comprobante',
            'description' => 'Puntos de venta que el usuario puede usar y, para cada uno, los tipos de comprobante habilitados '
                . 'para ese cliente (en facturas, solo la letra que corresponde a su condición fiscal).',
            'input_schema' => ['type' => 'object', 'properties' => [
                'clase' => ['type' => 'string', 'enum' => ['factura', 'remito']],
                'cliente_id' => ['type' => 'integer', 'description' => '0 = consumidor final'],
            ], 'required' => ['clase']],
        ],
        [
            'name' => 'proponer_factura_venta',
            'description' => 'Arma una factura de venta para que el usuario la revise y la confirme en pantalla (no graba nada). '
                . 'Devuelve el detalle calculado o los errores a corregir.',
            'input_schema' => ['type' => 'object', 'properties' => $factura,
                               'required' => ['cliente_id', 'punto_venta_id', 'comprobante_tipo_id']],
        ],
        [
            'name' => 'remitos_pendientes_facturar',
            'description' => 'Remitos del cliente (confirmados) con mercadería pendiente de facturar: número, fecha, líneas con lo pendiente '
                . '(venta_remito_detalle_id, producto, cantidad, precio y descuento del remito) y total estimado sin IVA.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cliente_id' => ['type' => 'integer', 'description' => '0 = remitos a consumidor final'],
                'entidad_sucursal_id' => ['type' => 'integer', 'description' => 'Omitir = todas las sucursales del cliente'],
            ], 'required' => ['cliente_id']],
        ],
        [
            'name' => 'ofrecer_opciones',
            'description' => 'Muestra en pantalla botones para que el usuario elija (remitos, puntos de venta, clientes, productos, tipos...). '
                . 'Después de llamarla, terminá el turno con la pregunta: la elección llega como el próximo mensaje del usuario.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'pregunta' => ['type' => 'string'],
                'opciones' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'texto' => ['type' => 'string', 'description' => 'Lo que se ve en el botón y lo que se envía al elegirlo'],
                    'detalle' => ['type' => 'string', 'description' => 'Línea secundaria opcional (fecha, importe, sucursal...)'],
                ], 'required' => ['texto']]],
                'multiple' => ['type' => 'boolean', 'description' => 'true = puede elegir varias (p. ej. varios remitos)'],
            ], 'required' => ['pregunta', 'opciones']],
        ],
        [
            'name' => 'proponer_remito_venta',
            'description' => 'Arma un remito de venta (entrega de mercadería, descuenta stock al confirmarlo) para que el usuario '
                . 'lo revise y lo confirme en pantalla (no graba nada).',
            'input_schema' => ['type' => 'object', 'properties' => $comprobante,
                               'required' => ['cliente_id', 'punto_venta_id', 'comprobante_tipo_id', 'items']],
        ],
        [
            'name' => 'buscar_facturas_emitidas',
            'description' => 'Facturas de venta emitidas (no borradores, eliminadas ni anuladas; sin notas de crédito / débito) de un período '
                . 'y/o de un cliente: número, tipo, cliente, fecha, total y cantidad de líneas. Hasta 100, de la más vieja a la más nueva.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cliente_id' => ['type' => 'integer', 'description' => 'Omitir = todos los clientes; 0 = solo consumidor final'],
                'fecha_desde' => ['type' => 'string', 'description' => 'AAAA-MM-DD'],
                'fecha_hasta' => ['type' => 'string', 'description' => 'AAAA-MM-DD'],
            ]],
        ],
        [
            'name' => 'proponer_copia_facturas',
            'description' => 'Arma copias de facturas emitidas (mismo cliente, sucursal, punto de venta, tipo, productos, cantidades y descuentos) '
                . 'con los precios incrementados en un porcentaje, para que el usuario las revise y confirme en pantalla (no graba nada). '
                . 'Indicá las facturas por id (buscar_facturas_emitidas) o por período y/o cliente. Como mucho 50 por propuesta.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'venta_factura_ids' => ['type' => 'array', 'items' => ['type' => 'integer'],
                                        'description' => 'Ids de buscar_facturas_emitidas. Si se dan, se ignoran cliente y fechas'],
                'cliente_id' => ['type' => 'integer', 'description' => 'Sin ids: omitir = todos los clientes del período'],
                'fecha_desde' => ['type' => 'string', 'description' => 'Sin ids: AAAA-MM-DD'],
                'fecha_hasta' => ['type' => 'string', 'description' => 'Sin ids: AAAA-MM-DD'],
                'incremento_pct' => ['type' => 'number', 'description' => 'Porcentaje sobre el precio sin IVA de cada línea (10 = +10 %; 0 = mismo precio; negativo = rebaja)'],
                'fecha' => ['type' => 'string', 'description' => 'Fecha de emisión de las copias, AAAA-MM-DD; omitir = hoy'],
                'redondeo' => ['type' => 'number', 'description' => 'Redondear el precio unitario a múltiplos de este valor (0.01, 1, 10, 100); omitir = centavos'],
                'reemplazos_texto' => ['type' => 'array', 'description' => 'Reemplazos en las leyendas de las líneas y en las observaciones '
                    . '(p. ej. "octubre 2026" por "noviembre 2026" en abonos mensuales)',
                    'items' => ['type' => 'object', 'properties' => [
                        'buscar' => ['type' => 'string'], 'reemplazar' => ['type' => 'string'],
                    ], 'required' => ['buscar', 'reemplazar']]],
            ], 'required' => ['incremento_pct']],
        ],
        [
            'name' => 'consultar_padron_arca',
            'description' => 'Consulta el padrón de ARCA (constancia de inscripción) por CUIT: razón social, tipo de persona, '
                . 'condición frente al IVA (con su condicion_fiscal_id), domicilio fiscal con la localidad_id del sistema, actividad '
                . 'y si ya existe en la empresa. Usala para dar de alta con solo el CUIT o para completar lo que falte.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cuit' => ['type' => 'string', 'description' => '11 dígitos, con o sin guiones'],
            ], 'required' => ['cuit']],
        ],
        [
            'name' => 'catalogos_alta_entidad',
            'description' => 'Catálogos para dar de alta un cliente o proveedor: condiciones frente al IVA, regímenes y '
                . 'jurisdicciones de Ingresos Brutos, condiciones de pago y listas de precios de la empresa.',
            'input_schema' => ['type' => 'object', 'properties' => new stdClass()],
        ],
        [
            'name' => 'buscar_localidades',
            'description' => 'Busca localidades por nombre y/o código postal (y opcionalmente provincia).',
            'input_schema' => ['type' => 'object', 'properties' => [
                'texto' => ['type' => 'string', 'description' => 'Nombre de la localidad'],
                'cp' => ['type' => 'integer', 'description' => 'Código postal numérico (los 4 dígitos)'],
                'provincia' => ['type' => 'string'],
            ]],
        ],
        [
            'name' => 'proponer_alta_entidad',
            'description' => 'Arma el alta de un cliente, proveedor y/o empleado/socio para que el usuario la confirme en pantalla (no graba nada). '
                . 'Si el CUIT ya existe en la empresa, propone agregarle el rol que le falta.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'es_cliente' => ['type' => 'boolean'],
                'es_proveedor' => ['type' => 'boolean'],
                'es_empleado' => ['type' => 'boolean', 'description' => 'true para dar de alta como Empleado / Socio (rol EMP)'],
                'cont_cuenta_emp_id' => ['type' => 'integer', 'description' => 'Solo con es_empleado: cuenta contable imputable opcional para el rol EMP (ej: Cuenta Particular del socio)'],
                'razon_social' => ['type' => 'string', 'description' => 'Apellido y nombre o denominación, como figura en ARCA'],
                'nombre_fantasia' => ['type' => 'string'],
                'cuit' => ['type' => 'string', 'description' => '11 dígitos'],
                'domicilio' => ['type' => 'string', 'description' => 'Calle y número (domicilio fiscal)'],
                'localidad_id' => ['type' => 'integer', 'description' => 'Id devuelto por buscar_localidades'],
                'condicion_fiscal_id' => ['type' => 'integer', 'description' => 'Condición frente al IVA (catalogos_alta_entidad)'],
                'regimen_iibb_id' => ['type' => 'integer', 'description' => 'Régimen de Ingresos Brutos; omitir si no se sabe'],
                'numero_iibb' => ['type' => 'string'],
                'jurisdiccion_sede_id' => ['type' => 'integer'],
                'condicion_pago_id' => ['type' => 'integer', 'description' => 'Omitir = la primera de la empresa'],
                'lista_precio_id' => ['type' => 'integer', 'description' => 'Solo clientes; omitir = sin lista'],
                'telefono' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'observaciones' => ['type' => 'string'],
            ], 'required' => ['es_cliente', 'es_proveedor', 'razon_social', 'cuit']],
        ],
    ];

    // Filtrar herramientas según los permisos reales del perfil del usuario
    if (!empty($ctx)) {
        $p_fact = asis_puede($ctx, 'facturas', 'agregar');
        $p_rem  = asis_puede($ctx, 'remitos', 'agregar');
        $p_comp = asis_puede($ctx, 'facturas_proveedores', 'agregar') || asis_puede($ctx, 'ordenes_compra', 'agregar');
        $p_ent  = asis_puede($ctx, 'clientes', 'agregar') || asis_puede($ctx, 'proveedores', 'agregar') || asis_puede($ctx, 'obligaciones_pago', 'agregar');
        $permitidas = [
            'buscar_entidades'            => true,
            'ofrecer_opciones'            => true,
            'buscar_productos'            => $p_fact || $p_rem || $p_comp,
            'opciones_comprobante'        => $p_fact || $p_rem,
            'proponer_factura_venta'      => $p_fact,
            'remitos_pendientes_facturar' => $p_fact,
            'buscar_facturas_emitidas'    => $p_fact,
            'proponer_copia_facturas'     => $p_fact,
            'proponer_remito_venta'       => $p_rem,
            'consultar_padron_arca'       => $p_ent,
            'catalogos_alta_entidad'      => $p_ent,
            'buscar_localidades'          => $p_ent,
            'proponer_alta_entidad'       => $p_ent,
        ];
        $base = array_values(array_filter($base, fn($h) => !empty($permitidas[$h['name']])));
        if (function_exists('asis_herramientas_contab')) {
            $base = array_merge($base, asis_herramientas_contab($ctx));
        }
        if (function_exists('asis_herramientas_teso_ext')) {
            $base = array_merge($base, asis_herramientas_teso_ext($ctx));
        }
        if (function_exists('asis_herramientas_compras_bco')) {
            $base = array_merge($base, asis_herramientas_compras_bco($ctx));
        }
    }
    return $base;
}

/** Ejecuta una herramienta. Devuelve el resultado (array) o lanza RuntimeException con el error para la IA. */
function asis_ejecutar_herramienta(array $ctx, string $nombre, array $e): array
{
    switch ($nombre) {
        case 'buscar_entidades':       return asis_h_buscar_entidades($ctx, $e);
        case 'buscar_productos':       return asis_h_buscar_productos($ctx, $e);
        case 'opciones_comprobante':   return asis_h_opciones_comprobante($ctx, $e);
        case 'proponer_factura_venta': return asis_h_proponer_comprobante($ctx, 'FACTURA_VENTA', $e);
        case 'proponer_remito_venta':  return asis_h_proponer_comprobante($ctx, 'REMITO_VENTA', $e);
        case 'remitos_pendientes_facturar': return asis_h_remitos_pendientes($ctx, $e);
        case 'ofrecer_opciones':       return asis_h_ofrecer_opciones($ctx, $e);
        case 'buscar_facturas_emitidas': return asis_h_facturas_emitidas($ctx, $e);
        case 'proponer_copia_facturas': return asis_h_proponer_copia_facturas($ctx, $e);
        case 'consultar_padron_arca':  return asis_h_padron_arca($ctx, $e);
        case 'catalogos_alta_entidad': return asis_h_catalogos_entidad($ctx);
        case 'buscar_localidades':     return asis_h_buscar_localidades($ctx, $e);
        case 'proponer_alta_entidad':  return asis_h_proponer_alta_entidad($ctx, $e);
    }
    if (function_exists('asis_ejecutar_herramienta_contab')) {
        $res = asis_ejecutar_herramienta_contab($ctx, $nombre, $e);
        if ($res !== null) return $res;
    }
    if (function_exists('asis_ejecutar_herramienta_teso_ext')) {
        $res = asis_ejecutar_herramienta_teso_ext($ctx, $nombre, $e);
        if ($res !== null) return $res;
    }
    if (function_exists('asis_ejecutar_herramienta_compras_bco')) {
        $res = asis_ejecutar_herramienta_compras_bco($ctx, $nombre, $e);
        if ($res !== null) return $res;
    }
    throw new RuntimeException("Herramienta desconocida: $nombre");
}

// ---------------------------------------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------------------------------------

function asis_filas(mysqli $c, string $sql, string $tipos = '', ...$params): array
{
    $stmt = mysqli_prepare($c, $sql);
    if (!$stmt) throw new RuntimeException('Error de consulta: ' . mysqli_error($c));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($res && ($f = mysqli_fetch_assoc($res))) $filas[] = $f;
    mysqli_stmt_close($stmt);
    return $filas;
}

function asis_fila(mysqli $c, string $sql, string $tipos = '', ...$params): ?array
{
    return asis_filas($c, $sql, $tipos, ...$params)[0] ?? null;
}

/** Palabras del texto, para LIKE con AND entre ellas. */
function asis_palabras(string $texto): array
{
    return array_values(array_filter(preg_split('/\s+/u', trim($texto)), fn($p) => mb_strlen($p) > 0));
}

function asis_fecha(?string $f): string
{
    $f = trim((string)$f);
    if ($f === '') return date('Y-m-d');
    $d = DateTime::createFromFormat('Y-m-d', $f);
    if (!$d || $d->format('Y-m-d') !== $f) throw new RuntimeException("La fecha \"$f\" no es válida (AAAA-MM-DD).");
    return $f;
}

// ---------------------------------------------------------------------------------------------------
// Consultas
// ---------------------------------------------------------------------------------------------------

function asis_h_buscar_entidades(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $empresa_id = intval($ctx['empresa_id']);
    $texto = trim((string)($e['texto'] ?? ''));
    if ($texto === '') throw new RuntimeException('Indicá qué buscar.');
    $rol = (string)($e['rol'] ?? 'cualquiera');
    $where = '';
    $tipos = 'i';
    $params = [$empresa_id];
    $digitos = preg_replace('/\D/', '', $texto);
    if (strlen($digitos) >= 7 && strlen($digitos) === strlen(preg_replace('/[\s\-.]/', '', $texto))) {
        $where .= ' AND CAST(e.cuit AS CHAR) LIKE ?';
        $tipos .= 's';
        $params[] = '%' . $digitos . '%';
    } else {
        foreach (asis_palabras($texto) as $p) {
            $where .= ' AND (e.entidad_nombre LIKE ? OR e.entidad_fantasia LIKE ?)';
            $tipos .= 'ss';
            $params[] = '%' . $p . '%';
            $params[] = '%' . $p . '%';
        }
    }
    if ($rol === 'cliente') $where .= ' AND ' . ent_sql_tiene_rol('e.entidad_id', 'CLI');
    if ($rol === 'proveedor') $where .= ' AND ' . ent_sql_tiene_rol('e.entidad_id', 'PRO');
    if ($rol === 'empleado') $where .= ' AND ' . ent_sql_tiene_rol('e.entidad_id', 'EMP');
    if ($rol === 'organismo') $where .= ' AND ' . ent_sql_tiene_rol('e.entidad_id', 'ORG');
    $filas = asis_filas($c, "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit, e.domicilio_legal,
                   IF(" . ent_sql_tiene_rol('e.entidad_id', 'CLI') . ", 1, 0) AS es_cliente,
                   IF(" . ent_sql_tiene_rol('e.entidad_id', 'PRO') . ", 1, 0) AS es_proveedor,
                   IF(" . ent_sql_tiene_rol('e.entidad_id', 'EMP') . ", 1, 0) AS es_empleado,
                   IF(" . ent_sql_tiene_rol('e.entidad_id', 'ORG') . ", 1, 0) AS es_organismo,
                   (SELECT cf.condicion_fiscal FROM gestion__entidades_condiciones_fiscales ecf
                      JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = ecf.condicion_fiscal_id
                     WHERE ecf.entidad_id = e.entidad_id AND ecf.tabla_estado_registro_id = 1
                       AND (ecf.f_desde IS NULL OR ecf.f_desde <= CURDATE()) AND (ecf.f_hasta IS NULL OR ecf.f_hasta >= CURDATE())
                     ORDER BY ecf.f_desde DESC LIMIT 1) AS condicion_iva
            FROM gestion__entidades e
            WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 $where
            ORDER BY e.entidad_nombre LIMIT 10", $tipos, ...$params);
    foreach ($filas as &$f) {
        $f['condicion_iva'] = $f['condicion_iva'] ?? 'Sin cargar (se toma como Consumidor Final)';
        $f['sucursales'] = asis_filas($c, "SELECT sucursal_id AS entidad_sucursal_id, sucursal_nombre, sucursal_direccion
                FROM gestion__entidades_sucursales WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                ORDER BY sucursal_numero, sucursal_id", 'ii', intval($f['entidad_id']), $empresa_id);
    }
    unset($f);
    return ['entidades' => $filas, 'cantidad' => count($filas)];
}

function asis_h_buscar_productos(array $ctx, array $e): array
{
    $texto = trim((string)($e['texto'] ?? ''));
    if ($texto === '') throw new RuntimeException('Indicá qué producto buscar.');
    $cliente_id = intval($e['cliente_id'] ?? 0);
    if ($cliente_id > 0) asis_cliente($ctx, $cliente_id);
    $filas = vfBuscarProductos($ctx['c'], intval($ctx['empresa_id']), $cliente_id, $texto);
    $out = [];
    foreach ($filas as $p) {
        $out[] = ['producto_id' => intval($p['producto_id']), 'codigo' => $p['producto_codigo'], 'nombre' => $p['producto_nombre'],
                  'precio_lista_sin_iva' => round(floatval($p['precio_unitario']), 2), 'iva_porcentaje' => floatval($p['iva_porcentaje'])];
    }
    return ['productos' => $out, 'cantidad' => count($out),
            'nota' => count($out) === 20 ? 'Hay más resultados: afiná la búsqueda.' : ''];
}

/** Cliente activo de la empresa con rol CLI, o error. */
function asis_cliente(array $ctx, int $cliente_id): array
{
    $f = asis_fila($ctx['c'], "SELECT e.entidad_id, e.entidad_nombre FROM gestion__entidades e
            WHERE e.entidad_id = ? AND e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI'),
            'ii', $cliente_id, intval($ctx['empresa_id']));
    if (!$f) throw new RuntimeException("El cliente $cliente_id no existe en la empresa o no está activo como cliente.");
    return $f;
}

/** PV accesibles por el usuario con los tipos habilitados para el cliente. */
function asis_puntos_venta(array $ctx, string $clase, int $cliente_id): array
{
    $c = $ctx['c'];
    $empresa_id = intval($ctx['empresa_id']);
    $out = [];
    if ($clase === 'remito') {
        foreach (obtenerPuntosVentaDepositoRemitos($c, $empresa_id) as $pv) {
            $tipos = obtenerComprobantesTiposRemitos($c, $empresa_id, $ctx['paginas']['remitos'], intval($pv['punto_venta_id']));
            if (!$tipos) continue;
            $out[] = ['punto_venta_id' => intval($pv['punto_venta_id']), 'nombre' => $pv['punto_venta_nombre'],
                      'numero' => $pv['punto_venta_codigo'],
                      'tipos' => array_map(fn($t) => ['comprobante_tipo_id' => intval($t['comprobante_tipo_id']),
                                                       'nombre' => $t['comprobante_tipo'], 'letra' => $t['letra']], $tipos)];
        }
        return $out;
    }
    $entidad_param = $cliente_id > 0 ? $cliente_id : 'CF';
    foreach (vfPuntosVenta($c, $empresa_id) as $pv) {
        $tipos = vfTiposPorPuntoVenta($c, $empresa_id, intval($pv['punto_venta_id']), $ctx['paginas']['facturas'], $entidad_param, true);
        if (!$tipos) continue;
        $out[] = ['punto_venta_id' => intval($pv['punto_venta_id']), 'nombre' => $pv['nombre'], 'numero' => $pv['codigo_fiscal'],
                  'sucursal_id' => intval($pv['sucursal_id']), 'sucursal' => $pv['sucursal_nombre'],
                  'tipos' => array_map(fn($t) => ['comprobante_tipo_id' => intval($t['comprobante_tipo_id']), 'nombre' => $t['comprobante_tipo'],
                                                   'letra' => $t['letra'], 'fiscal' => intval($t['comprobante_fiscal_id'] ?? 0) > 0,
                                                   'sugerido' => !empty($t['es_sugerido'])], $tipos)];
    }
    return $out;
}

function asis_h_opciones_comprobante(array $ctx, array $e): array
{
    $clase = ($e['clase'] ?? '') === 'remito' ? 'remito' : 'factura';
    $cliente_id = intval($e['cliente_id'] ?? 0);
    $cliente = $cliente_id > 0 ? asis_cliente($ctx, $cliente_id) : null;
    $pvs = asis_puntos_venta($ctx, $clase, $cliente_id);
    $res = ['clase' => $clase, 'puntos_venta' => $pvs];
    if ($clase === 'factura') {
        $rel = vfObtenerRelacionFiscal($ctx['c'], intval($ctx['empresa_id']), $cliente_id > 0 ? $cliente_id : 'CF');
        $res['condicion_fiscal_cliente'] = $rel['receptor_condicion_fiscal_nombre'] ?? null;
        $res['letra_que_corresponde'] = $rel['relacion']['letra'] ?? null;
    }
    if (!$pvs) {
        $res['aviso'] = 'El usuario no tiene puntos de venta habilitados para ' . ($clase === 'remito' ? 'remitos' : 'facturas')
                      . ($cliente ? ' de este cliente' : '') . '.';
        return $res;
    }
    // Qué punto de venta usar: el único, el por defecto de la empresa (Parámetros de Empresa) o que elija el usuario
    $defecto = asis_fila($ctx['c'], "SELECT punto_venta_defecto_id FROM gestion__empresas_parametros
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND f_desde <= CURDATE() AND (f_hasta IS NULL OR f_hasta >= CURDATE())
            ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1", 'i', intval($ctx['empresa_id']));
    $defecto_id = intval($defecto['punto_venta_defecto_id'] ?? 0);
    $con_sugerido = array_values(array_filter($pvs, fn($p) => (bool)array_filter($p['tipos'], fn($t) => !empty($t['sugerido']))));
    if (count($pvs) === 1) {
        $res['punto_venta_a_usar'] = $pvs[0]['punto_venta_id'];
        $res['indicacion'] = 'Hay un solo punto de venta: usalo.';
    } elseif ($defecto_id && in_array($defecto_id, array_column($pvs, 'punto_venta_id'), true)) {
        $res['punto_venta_a_usar'] = $defecto_id;
        $res['indicacion'] = 'Usá el punto de venta por defecto de la empresa (punto_venta_a_usar) salvo que el usuario haya pedido otro.';
    } else {
        $res['indicacion'] = 'Hay ' . count($pvs) . ' puntos de venta posibles y la empresa no tiene uno por defecto: si el usuario no dijo cuál, '
            . 'ANTES de proponer mostráselos con ofrecer_opciones (texto "PV <numero> - <nombre>", detalle con la sucursal y los tipos'
            . ($con_sugerido ? ', primero los que tienen el tipo sugerido' : '') . ') y esperá su elección.';
    }
    return $res;
}

function asis_h_catalogos_entidad(array $ctx): array
{
    $c = $ctx['c'];
    $empresa_id = intval($ctx['empresa_id']);
    return [
        'condiciones_iva' => asis_filas($c, "SELECT condicion_fiscal_id, condicion_fiscal FROM gestion__condiciones_fiscales
                WHERE tabla_estado_registro_id = 1 ORDER BY condicion_fiscal_id"),
        'regimenes_iibb' => asis_filas($c, "SELECT iibb_regimen_id AS regimen_iibb_id, iibb_regimen_nombre FROM gestion__iibb_regimenes
                WHERE tabla_estado_registro_id = 1 ORDER BY orden"),
        'jurisdicciones_sede_iibb' => asis_filas($c, "SELECT jurisdiccion_sede_id, jurisdiccion_sede FROM gestion__iibb_jurisdicciones_sedes
                WHERE tabla_estado_registro_id = 1 ORDER BY jurisdiccion_sede"),
        'condiciones_pago' => asis_filas($c, "SELECT condicion_pago_id, condicion_pago, tipo FROM gestion__condiciones_pago
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY orden, condicion_pago", 'i', $empresa_id),
        'listas_precios' => asis_filas($c, "SELECT lista_precio_id, lista_precio_nombre FROM gestion__listas_precios
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY lista_precio_nombre", 'i', $empresa_id),
    ];
}

function asis_h_padron_arca(array $ctx, array $e): array
{
    require_once __DIR__ . '/../arca_padron_service.php';
    $d = arca_padron_consultar_erp($ctx['c'], intval($ctx['empresa_id']), (string)($e['cuit'] ?? ''));
    unset($d['impuestos']);
    return $d;
}

function asis_h_buscar_localidades(array $ctx, array $e): array
{
    $where = '';
    $tipos = '';
    $params = [];
    foreach (asis_palabras((string)($e['texto'] ?? '')) as $p) {
        $where .= ' AND l.localidad LIKE ?';
        $tipos .= 's';
        $params[] = '%' . $p . '%';
    }
    if (intval($e['cp'] ?? 0) > 0) {
        $where .= ' AND l.cp = ?';
        $tipos .= 'i';
        $params[] = intval($e['cp']);
    }
    if (trim((string)($e['provincia'] ?? '')) !== '') {
        $where .= ' AND p.provincia LIKE ?';
        $tipos .= 's';
        $params[] = '%' . trim((string)$e['provincia']) . '%';
    }
    if ($where === '') throw new RuntimeException('Indicá la localidad o el código postal.');
    $filas = asis_filas($ctx['c'], "SELECT l.localidad_id, l.localidad, l.cp, p.provincia
            FROM conf__localidades l LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
            WHERE l.tabla_estado_registro_id = 1 $where ORDER BY l.localidad LIMIT 15", $tipos, ...$params);
    return ['localidades' => $filas, 'cantidad' => count($filas)];
}

/** "Remito de Salida A 0003-00000012" por venta_remito_id (número con el código fiscal del PV). */
function asis_rotulos_remitos(array $ctx, array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $r = asis_fila($ctx['c'], "SELECT vr.comprobante_pv, vr.comprobante_nro, ct.comprobante_tipo FROM gestion__ventas_remitos vr
                LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vr.comprobante_tipo_id
                WHERE vr.venta_remito_id = ? AND vr.empresa_id = ?", 'ii', intval($id), intval($ctx['empresa_id']));
        if ($r) $out[intval($id)] = trim(($r['comprobante_tipo'] ?: 'Remito') . ' '
            . emp_numero_comprobante($ctx['c'], intval($ctx['empresa_id']), intval($r['comprobante_pv']), intval($r['comprobante_nro'])));
    }
    return $out;
}

function asis_h_remitos_pendientes(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $empresa_id = intval($ctx['empresa_id']);
    $cliente_id = intval($e['cliente_id'] ?? 0);
    if ($cliente_id > 0) asis_cliente($ctx, $cliente_id);
    $filas = vfRemitosPendientes($c, $empresa_id, $cliente_id, intval($e['entidad_sucursal_id'] ?? 0));
    $remitos = [];
    foreach ($filas as $rd) {
        $id = intval($rd['venta_remito_id']);
        if (!isset($remitos[$id])) {
            $remitos[$id] = ['venta_remito_id' => $id, 'fecha' => $rd['f_emision'], 'consumidor_final' => $rd['consumidor_final_nombre'],
                             'lineas' => [], 'total_estimado_sin_iva' => 0];
        }
        $pend = floatval($rd['cantidad_pendiente_facturar']);
        $neto = $pend * floatval($rd['precio_unitario_bruto']) * (1 - floatval($rd['descuento_general_pct']) / 100);
        $remitos[$id]['lineas'][] = ['venta_remito_detalle_id' => intval($rd['venta_remito_detalle_id']), 'producto' => $rd['producto_nombre'],
            'codigo' => $rd['producto_codigo'], 'cantidad_remitida' => floatval($rd['cantidad']), 'pendiente' => $pend,
            'precio_sin_iva' => round(floatval($rd['precio_unitario_bruto']), 2), 'descuento_pct' => floatval($rd['descuento_general_pct'])];
        $remitos[$id]['total_estimado_sin_iva'] = round($remitos[$id]['total_estimado_sin_iva'] + $neto, 2);
    }
    $rotulos = asis_rotulos_remitos($ctx, array_keys($remitos));
    foreach ($remitos as $id => &$r) {
        $r['numero'] = $rotulos[$id] ?? '';
        // Ya con el formato de la empresa, para mostrarlos tal cual en las opciones
        $r['fecha_mostrar'] = fmt_fecha($r['fecha']);
        $r['total_mostrar'] = fmt_moneda($r['total_estimado_sin_iva']) . ' + IVA';
    }
    unset($r);
    return ['remitos' => array_values($remitos), 'cantidad' => count($remitos)];
}

/** Botones de elección en pantalla: se registran como mensaje OPCIONES (asistente-ia.js los dibuja). */
function asis_h_ofrecer_opciones(array $ctx, array $e): array
{
    $pregunta = mb_substr(trim((string)($e['pregunta'] ?? '')), 0, 500);
    $opciones = [];
    foreach (array_slice((array)($e['opciones'] ?? []), 0, 30) as $o) {
        $texto = mb_substr(trim((string)($o['texto'] ?? '')), 0, 200);
        if ($texto !== '') $opciones[] = ['texto' => $texto, 'detalle' => mb_substr(trim((string)($o['detalle'] ?? '')), 0, 200)];
    }
    if ($pregunta === '' || count($opciones) < 2) throw new RuntimeException('Hacen falta una pregunta y al menos dos opciones.');
    asis_registrar_mensaje($ctx, 'OPCIONES', $pregunta, ['datos' => ['opciones' => $opciones, 'multiple' => !empty($e['multiple'])]]);
    return ['mostradas' => count($opciones), 'nota' => 'Terminá el turno con la pregunta; la elección llega en el próximo mensaje.'];
}

// ---------------------------------------------------------------------------------------------------
// Propuestas: factura / remito
// ---------------------------------------------------------------------------------------------------

/**
 * Valida y calcula un comprobante. Lo usan la propuesta y la confirmación (que revalida con los datos guardados).
 * Devuelve los datos normalizados para grabar + el resumen para mostrar. Lanza RuntimeException con todos los errores.
 */
function asis_validar_comprobante(array $ctx, string $tipo, array $e): array
{
    $c = $ctx['c'];
    $empresa_id = intval($ctx['empresa_id']);
    $clase = $tipo === 'REMITO_VENTA' ? 'remito' : 'factura';
    $errores = [];

    $cliente_id = intval($e['cliente_id'] ?? 0);
    $cliente_nombre = '';
    $consumidor = null;
    if ($cliente_id > 0) {
        $cliente_nombre = asis_cliente($ctx, $cliente_id)['entidad_nombre'];
    } else {
        $consumidor = trim((string)($e['consumidor_final_nombre'] ?? '')) ?: 'Consumidor Final';
        $cliente_nombre = $consumidor;
    }

    $suc_cliente_id = intval($e['entidad_sucursal_id'] ?? 0);
    $suc_cliente = '';
    if ($cliente_id > 0) {
        $sucs = asis_filas($c, "SELECT sucursal_id, sucursal_nombre FROM gestion__entidades_sucursales
                WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_numero, sucursal_id",
                'ii', $cliente_id, $empresa_id);
        $elegida = null;
        foreach ($sucs as $s) {
            if ($suc_cliente_id === 0 || intval($s['sucursal_id']) === $suc_cliente_id) { $elegida = $s; break; }
        }
        if ($suc_cliente_id > 0 && !$elegida) $errores[] = 'La sucursal del cliente no es válida.';
        $suc_cliente_id = $elegida ? intval($elegida['sucursal_id']) : 0;
        $suc_cliente = $elegida['sucursal_nombre'] ?? '';
    }

    // Punto de venta y tipo, contra lo que el usuario puede usar
    $pv_id = intval($e['punto_venta_id'] ?? 0);
    $tipo_id = intval($e['comprobante_tipo_id'] ?? 0);
    $pv = null;
    $tipo_cmp = null;
    $pv_alternativas = [];   // PV donde también está ese tipo: la tarjeta deja cambiarlo antes de confirmar
    foreach (asis_puntos_venta($ctx, $clase, $cliente_id) as $p) {
        if (in_array($tipo_id, array_column($p['tipos'], 'comprobante_tipo_id'), true)) {
            $pv_alternativas[] = ['punto_venta_id' => $p['punto_venta_id'],
                                  'texto' => 'PV ' . $p['numero'] . ' - ' . $p['nombre'] . (!empty($p['sucursal']) ? ' (' . $p['sucursal'] . ')' : '')];
        }
        if ($p['punto_venta_id'] !== $pv_id) continue;
        $pv = $p;
        foreach ($p['tipos'] as $t) if ($t['comprobante_tipo_id'] === $tipo_id) $tipo_cmp = $t;
    }
    if (!$pv) $errores[] = 'El punto de venta no existe o el usuario no lo puede usar (consultá opciones_comprobante).';
    elseif (!$tipo_cmp) $errores[] = 'Ese tipo de comprobante no está habilitado en el punto de venta para este cliente (consultá opciones_comprobante).';

    $fecha = asis_fecha($e['fecha'] ?? null);

    // Precios: lista y descuento general del cliente (o consumidor final), como el formulario
    $cond = vfObtenerCondicionDestinatario($c, $cliente_id, $empresa_id) ?: [];
    $lista_id = intval($cond['lista_precio_id'] ?? 0);
    $desc_cliente = floatval($cond['cliente_descuento_general'] ?? 0);
    $items = is_array($e['items'] ?? null) ? $e['items'] : [];
    // Remitos pendientes del cliente (solo facturas): por línea o el remito entero
    $pend = [];
    if ($clase === 'factura') {
        foreach (vfRemitosPendientes($c, $empresa_id, $cliente_id) as $rd) $pend[intval($rd['venta_remito_detalle_id'])] = $rd;
        $dadas = array_map(fn($it) => intval($it['venta_remito_detalle_id'] ?? 0), $items);
        foreach ((array)($e['remitos'] ?? []) as $rid) {
            $lineas_remito = array_filter($pend, fn($rd) => intval($rd['venta_remito_id']) === intval($rid));
            if (!$lineas_remito) { $errores[] = "El remito $rid no tiene nada pendiente de facturar para este cliente."; continue; }
            foreach ($lineas_remito as $rdid => $rd) if (!in_array($rdid, $dadas, true)) $items[] = ['venta_remito_detalle_id' => $rdid];
        }
    } elseif (array_filter($items, fn($it) => !empty($it['venta_remito_detalle_id']))) {
        $errores[] = 'Un remito no puede llevar líneas de otro remito.';
    }
    $rotulos = asis_rotulos_remitos($ctx, array_values(array_unique(array_map(fn($rd) => intval($rd['venta_remito_id']), $pend))));
    $usadas = [];
    if (!$items) $errores[] = 'Agregá al menos un producto.';
    if (count($items) > 100) $errores[] = 'Como mucho 100 líneas por comprobante.';
    $lineas = [];
    foreach ($items as $n => $it) {
        $nl = $n + 1;
        $rdid = intval($it['venta_remito_detalle_id'] ?? 0);
        if ($rdid > 0) {
            $rd = $pend[$rdid] ?? null;
            if (!$rd) { $errores[] = "Línea $nl: la línea de remito $rdid no está pendiente de facturar para este cliente."; continue; }
            if (isset($usadas[$rdid])) { $errores[] = "Línea $nl: la línea de remito $rdid está repetida."; continue; }
            $usadas[$rdid] = true;
            $pendiente = floatval($rd['cantidad_pendiente_facturar']);
            $cant = isset($it['cantidad']) && $it['cantidad'] !== null && $it['cantidad'] !== '' ? floatval($it['cantidad']) : $pendiente;
            if ($cant <= 0 || $cant > $pendiente + 0.0001) {
                $errores[] = "Línea $nl ({$rd['producto_nombre']}): la cantidad tiene que ser mayor a 0 y como mucho $pendiente (pendiente del remito).";
                continue;
            }
            $lineas[] = [
                'producto_id' => intval($rd['producto_id']), 'producto_codigo' => $rd['producto_codigo'], 'producto_nombre' => $rd['producto_nombre'],
                'cantidad' => $cant, 'precio_unitario' => round(floatval($rd['precio_unitario_bruto']), 4),
                'descuento_pct' => floatval($rd['descuento_general_pct']),
                'iva_alicuota_id' => intval($rd['iva_alicuota_id'] ?: 1), 'iva_porcentaje' => floatval($rd['iva_porcentaje']),
                'leyenda' => mb_substr(trim((string)($it['leyenda'] ?? '')), 0, 1000),
                'venta_remito_detalle_id' => $rdid, 'venta_pedido_detalle_id' => intval($rd['venta_pedido_detalle_id'] ?? 0) ?: null,
                'remito' => $rotulos[intval($rd['venta_remito_id'])] ?? ('Remito ' . $rd['comprobante_nro']),
                'sin_precio' => false, 'precio_manual' => false,
            ];
            continue;
        }
        $pid = intval($it['producto_id'] ?? 0);
        $cant = floatval($it['cantidad'] ?? 0);
        $p = asis_fila($c, "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, p.iva_alicuota_id,
                       COALESCE(iva.porcentaje, 0) AS iva_porcentaje, lp.precio_final
                FROM gestion__productos p
                LEFT JOIN gestion__impuestos__iva_alicuotas iva ON iva.iva_alicuota_id = p.iva_alicuota_id
                LEFT JOIN gestion__listas_precios_productos lp ON lp.producto_id = p.producto_id AND lp.lista_precio_id = ?
                      AND lp.empresa_id = p.empresa_id AND lp.tabla_estado_registro_id = 1
                WHERE p.producto_id = ? AND p.empresa_id = ? AND p.tabla_estado_registro_id = 1",
                'iii', $lista_id, $pid, $empresa_id);
        if (!$p) { $errores[] = "Línea $nl: el producto $pid no existe o está inactivo."; continue; }
        if ($cant <= 0) { $errores[] = "Línea $nl ({$p['producto_nombre']}): la cantidad tiene que ser mayor a 0."; continue; }
        $precio_dado = isset($it['precio_unitario']) && $it['precio_unitario'] !== null && $it['precio_unitario'] !== '';
        $precio = $precio_dado ? floatval($it['precio_unitario']) : floatval($p['precio_final'] ?? 0);
        if ($precio < 0) { $errores[] = "Línea $nl: el precio no puede ser negativo."; continue; }
        $desc = isset($it['descuento_pct']) && $it['descuento_pct'] !== null && $it['descuento_pct'] !== '' ? floatval($it['descuento_pct']) : $desc_cliente;
        if ($desc < 0 || $desc > 100) { $errores[] = "Línea $nl: el descuento tiene que estar entre 0 y 100."; continue; }
        $lineas[] = [
            'producto_id' => intval($p['producto_id']), 'producto_codigo' => $p['producto_codigo'], 'producto_nombre' => $p['producto_nombre'],
            'cantidad' => $cant, 'precio_unitario' => round($precio, 4), 'descuento_pct' => $desc,
            'iva_alicuota_id' => intval($p['iva_alicuota_id'] ?: 1), 'iva_porcentaje' => floatval($p['iva_porcentaje']),
            'leyenda' => mb_substr(trim((string)($it['leyenda'] ?? '')), 0, 1000),
            'sin_precio' => !$precio_dado && $p['precio_final'] === null,
            'precio_manual' => $precio_dado,
        ];
    }
    if ($errores) throw new RuntimeException(implode(' ', $errores));

    // Totales con el mismo cálculo de la factura (el remito no discrimina por letra: lleva el IVA del producto)
    $fiscal = $clase === 'factura' ? (!empty($tipo_cmp['fiscal']) ? 1 : 0) : 1;
    $calc = array_map(fn($l) => ['cantidad' => $l['cantidad'], 'precio_unitario' => $l['precio_unitario'],
                                 'descuento_general_pct' => $l['descuento_pct'], 'iva_porcentaje' => $l['iva_porcentaje']], $lineas);
    [$calc, $tot] = vfCalcularDetallesFactura($calc, $fiscal);
    foreach ($lineas as $i => &$l) {
        $l['neto'] = round($calc[$i]['_neto'], 2);
        $l['iva'] = round($calc[$i]['_iva'], 2);
        $l['total'] = round($calc[$i]['_linea'], 2);
    }
    unset($l);

    $avisos = [];
    foreach ($lineas as $l) {
        if ($l['sin_precio']) $avisos[] = "{$l['producto_nombre']} no tiene precio en la lista del cliente: va en 0, indicá el precio.";
    }
    return [
        'tipo' => $tipo,
        'cliente_id' => $cliente_id, 'cliente' => $cliente_nombre, 'consumidor_final_nombre' => $consumidor,
        'entidad_sucursal_id' => $suc_cliente_id, 'sucursal_cliente' => $suc_cliente,
        'punto_venta_id' => $pv_id, 'punto_venta' => $pv['numero'] . ' - ' . $pv['nombre'], 'sucursal_id' => intval($pv['sucursal_id'] ?? 0),
        'pv_alternativas' => count($pv_alternativas) > 1 ? $pv_alternativas : [],
        'comprobante_tipo_id' => $tipo_id, 'comprobante_tipo' => $tipo_cmp['nombre'], 'letra' => $tipo_cmp['letra'],
        'fecha' => $fecha, 'observaciones' => mb_substr(trim((string)($e['observaciones'] ?? '')), 0, 1000),
        'lineas' => $lineas, 'totales' => ['neto' => $tot['subtotal'], 'descuentos' => $tot['descuentos'], 'iva' => $tot['iva'], 'total' => $tot['total']],
        'avisos' => $avisos,
    ];
}

function asis_h_proponer_comprobante(array $ctx, string $tipo, array $e): array
{
    $datos = asis_validar_comprobante($ctx, $tipo, $e);
    $id = asis_guardar_propuesta($ctx, $tipo, $datos);
    return ['propuesta_id' => $id, 'estado' => 'Esperando que el usuario la confirme en pantalla',
            'resumen' => ['cliente' => $datos['cliente'], 'comprobante' => $datos['comprobante_tipo'], 'punto_venta' => $datos['punto_venta'],
                          'lineas' => array_map(fn($l) => "{$l['cantidad']} x {$l['producto_nombre']} a {$l['precio_unitario']}"
                                                      . (!empty($l['remito']) ? " ({$l['remito']})" : ''), $datos['lineas']),
                          'total' => $datos['totales']['total']],
            'avisos' => $datos['avisos']];
}

// ---------------------------------------------------------------------------------------------------
// Propuesta: alta de cliente / proveedor
// ---------------------------------------------------------------------------------------------------

function asis_validar_alta_entidad(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $empresa_id = intval($ctx['empresa_id']);
    $errores = [];
    $es_cliente = !empty($e['es_cliente']);
    $es_proveedor = !empty($e['es_proveedor']);
    $es_empleado = !empty($e['es_empleado']);
    if (!$es_cliente && !$es_proveedor && !$es_empleado) $errores[] = 'Indicá si es cliente, proveedor, empleado/socio o una combinación.';

    $cta_emp_id = intval($e['cont_cuenta_emp_id'] ?? 0);
    $cta_emp_txt = '';
    if ($es_empleado && $cta_emp_id > 0) {
        $ce = asis_fila($c, "SELECT codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $cta_emp_id, $empresa_id);
        if (!$ce || intval($ce['es_imputable']) !== 1) $errores[] = 'La cuenta contable para el rol Empleado/Socio no existe o no es imputable.';
        else $cta_emp_txt = $ce['codigo'] . ' - ' . $ce['nombre'];
    }

    $razon = trim(preg_replace('/\s+/u', ' ', (string)($e['razon_social'] ?? '')));
    if ($razon === '') $errores[] = 'Falta la razón social.';
    if (mb_strlen($razon) > 255) $errores[] = 'La razón social supera los 255 caracteres.';
    $cuit = preg_replace('/\D/', '', (string)($e['cuit'] ?? ''));
    if (!fl_cuit_valido($cuit)) $errores[] = "El CUIT $cuit no es válido (dígito verificador).";
    elseif ($cuit === emp_cuit($c, $empresa_id)) $errores[] = 'Ese CUIT es el de la propia empresa.';

    $existente = $cuit !== '' ? asis_fila($c, "SELECT e.entidad_id, e.entidad_nombre,
                IF(" . ent_sql_tiene_rol('e.entidad_id', 'CLI') . ", 1, 0) AS es_cliente,
                IF(" . ent_sql_tiene_rol('e.entidad_id', 'PRO') . ", 1, 0) AS es_proveedor,
                IF(" . ent_sql_tiene_rol('e.entidad_id', 'EMP') . ", 1, 0) AS es_empleado
            FROM gestion__entidades e WHERE e.empresa_id = ? AND e.cuit = ? ORDER BY e.tabla_estado_registro_id, e.entidad_id LIMIT 1",
            'is', $empresa_id, $cuit) : null;
    if ($existente && (!$es_cliente || $existente['es_cliente']) && (!$es_proveedor || $existente['es_proveedor']) && (!$es_empleado || $existente['es_empleado'])) {
        $errores[] = "Ya existe {$existente['entidad_nombre']} (id {$existente['entidad_id']}) con ese CUIT y esos roles: no hace falta el alta.";
    }
    if (!$existente) {
        $dup = asis_fila($c, "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND LOWER(entidad_nombre) = LOWER(?) LIMIT 1",
                         'is', $empresa_id, $razon);
        if ($dup) $errores[] = "Ya hay otra entidad con el nombre \"$razon\" (id {$dup['entidad_id']}) y otro CUIT: revisalo.";
    }

    $localidad = null;
    if (intval($e['localidad_id'] ?? 0) > 0) {
        $localidad = asis_fila($c, "SELECT l.localidad_id, l.localidad, l.cp, p.provincia FROM conf__localidades l
                LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id WHERE l.localidad_id = ?", 'i', intval($e['localidad_id']));
        if (!$localidad) $errores[] = 'La localidad no existe (usá buscar_localidades).';
    }
    $cond_iva = null;
    if (intval($e['condicion_fiscal_id'] ?? 0) > 0) {
        $cond_iva = asis_fila($c, "SELECT condicion_fiscal_id, condicion_fiscal FROM gestion__condiciones_fiscales
                WHERE condicion_fiscal_id = ? AND tabla_estado_registro_id = 1", 'i', intval($e['condicion_fiscal_id']));
        if (!$cond_iva) $errores[] = 'La condición frente al IVA no es válida.';
    }
    $regimen = null;
    if (intval($e['regimen_iibb_id'] ?? 0) > 0) {
        $regimen = asis_fila($c, "SELECT iibb_regimen_id, iibb_regimen_nombre FROM gestion__iibb_regimenes
                WHERE iibb_regimen_id = ? AND tabla_estado_registro_id = 1", 'i', intval($e['regimen_iibb_id']));
        if (!$regimen) $errores[] = 'El régimen de IIBB no es válido.';
    }
    $sede = null;
    if (intval($e['jurisdiccion_sede_id'] ?? 0) > 0) {
        $sede = asis_fila($c, "SELECT jurisdiccion_sede_id, jurisdiccion_sede FROM gestion__iibb_jurisdicciones_sedes
                WHERE jurisdiccion_sede_id = ? AND tabla_estado_registro_id = 1", 'i', intval($e['jurisdiccion_sede_id']));
        if (!$sede) $errores[] = 'La jurisdicción sede de IIBB no es válida.';
    }
    $numero_iibb = mb_substr(trim((string)($e['numero_iibb'] ?? '')), 0, 50);
    if (!$regimen && ($numero_iibb !== '' || $sede)) $errores[] = 'Para cargar el N° de IIBB o la jurisdicción sede indicá el régimen de IIBB.';

    $cp_id = intval($e['condicion_pago_id'] ?? 0);
    $cond_pago = $cp_id > 0
        ? asis_fila($c, "SELECT condicion_pago_id, condicion_pago FROM gestion__condiciones_pago
                WHERE condicion_pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $cp_id, $empresa_id)
        : asis_fila($c, "SELECT condicion_pago_id, condicion_pago FROM gestion__condiciones_pago
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY orden, condicion_pago_id LIMIT 1", 'i', $empresa_id);
    if (!$cond_pago) $errores[] = $cp_id > 0 ? 'La condición de pago no es de la empresa.' : 'La empresa no tiene condiciones de pago.';
    $lista = null;
    if ($es_cliente && intval($e['lista_precio_id'] ?? 0) > 0) {
        $lista = asis_fila($c, "SELECT lista_precio_id, lista_precio_nombre FROM gestion__listas_precios
                WHERE lista_precio_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', intval($e['lista_precio_id']), $empresa_id);
        if (!$lista) $errores[] = 'La lista de precios no es de la empresa.';
    }
    $email = trim((string)($e['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El email $email no es válido.";
    if ($errores) throw new RuntimeException(implode(' ', $errores));

    $fantasia = trim((string)($e['nombre_fantasia'] ?? '')) ?: $razon;
    return [
        'tipo' => 'ALTA_ENTIDAD',
        'entidad_existente_id' => $existente ? intval($existente['entidad_id']) : 0,
        'entidad_existente' => $existente['entidad_nombre'] ?? null,
        'es_cliente' => $es_cliente, 'es_proveedor' => $es_proveedor, 'es_empleado' => $es_empleado,
        'cont_cuenta_emp_id' => $cta_emp_id, 'cuenta_emp_texto' => $cta_emp_txt,
        'razon_social' => $razon, 'nombre_fantasia' => mb_substr($fantasia, 0, 255), 'cuit' => $cuit,
        'tipo_persona' => in_array(substr($cuit, 0, 2), ['30', '33', '34'], true) ? 'Persona Jurídica' : 'Persona Física',
        'entidad_tipo_id' => in_array(substr($cuit, 0, 2), ['30', '33', '34'], true) ? 2 : 1,
        'domicilio' => mb_substr(trim((string)($e['domicilio'] ?? '')), 0, 150),
        'localidad_id' => $localidad ? intval($localidad['localidad_id']) : null,
        'localidad' => $localidad ? trim("{$localidad['localidad']} ({$localidad['cp']}), {$localidad['provincia']}") : null,
        'condicion_fiscal_id' => $cond_iva ? intval($cond_iva['condicion_fiscal_id']) : 0,
        'condicion_fiscal' => $cond_iva['condicion_fiscal'] ?? null,
        'regimen_iibb_id' => $regimen ? intval($regimen['iibb_regimen_id']) : 0, 'regimen_iibb' => $regimen['iibb_regimen_nombre'] ?? null,
        'numero_iibb' => $numero_iibb,
        'jurisdiccion_sede_id' => $sede ? intval($sede['jurisdiccion_sede_id']) : 0, 'jurisdiccion_sede' => $sede['jurisdiccion_sede'] ?? null,
        'condicion_pago_id' => intval($cond_pago['condicion_pago_id']), 'condicion_pago' => $cond_pago['condicion_pago'],
        'lista_precio_id' => $lista ? intval($lista['lista_precio_id']) : 0, 'lista_precio' => $lista['lista_precio_nombre'] ?? null,
        'telefono' => mb_substr(trim((string)($e['telefono'] ?? '')), 0, 50),
        'email' => mb_substr($email, 0, 150),
        'observaciones' => mb_substr(trim((string)($e['observaciones'] ?? '')), 0, 2000),
        'avisos' => array_values(array_filter([
            !$cond_iva ? 'Sin condición frente al IVA: se la va a tomar como Consumidor Final.' : null,
            !$localidad ? 'Sin localidad.' : null,
        ])),
    ];
}

function asis_h_proponer_alta_entidad(array $ctx, array $e): array
{
    $datos = asis_validar_alta_entidad($ctx, $e);
    $id = asis_guardar_propuesta($ctx, 'ALTA_ENTIDAD', $datos);
    return ['propuesta_id' => $id, 'estado' => 'Esperando que el usuario la confirme en pantalla',
            'modo' => $datos['entidad_existente_id'] ? 'Agregar rol a ' . $datos['entidad_existente'] : 'Alta nueva',
            'avisos' => $datos['avisos']];
}

// ---------------------------------------------------------------------------------------------------
// Copia de facturas emitidas (con incremento de precios)
// ---------------------------------------------------------------------------------------------------

const ASIS_COPIA_MAX = 50;

/**
 * Facturas de venta emitidas de la empresa a las que el usuario tiene acceso por sucursal: sin borradores, inactivas,
 * eliminadas ni anuladas, y solo tipos raíz (sin NC / ND, que necesitan un comprobante asociado).
 * $f: cliente_id (null = todos, 0 = consumidor final), fecha_desde, fecha_hasta; o $ids.
 */
function asis_facturas_emitidas(array $ctx, array $f, ?array $ids, int $limite): array
{
    $c = $ctx['c'];
    $empresa_id = intval($ctx['empresa_id']);
    $where = '';
    $tipos = 'ii';
    $params = [$empresa_id, $empresa_id];
    if ($ids !== null) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (!$ids) return [];
        $where .= ' AND vf.venta_factura_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $tipos .= str_repeat('i', count($ids));
        array_push($params, ...$ids);
    } else {
        if (array_key_exists('cliente_id', $f) && $f['cliente_id'] !== null) {
            if (intval($f['cliente_id']) > 0) {
                $where .= ' AND vf.entidad_id = ?';
                $tipos .= 'i';
                $params[] = intval($f['cliente_id']);
            } else {
                $where .= ' AND (vf.entidad_id IS NULL OR vf.entidad_id = 0)';
            }
        }
        if (!empty($f['fecha_desde'])) { $where .= ' AND vf.f_emision >= ?'; $tipos .= 's'; $params[] = $f['fecha_desde']; }
        if (!empty($f['fecha_hasta'])) { $where .= ' AND vf.f_emision <= ?'; $tipos .= 's'; $params[] = $f['fecha_hasta']; }
    }
    $where .= AccesosSucursalesHelper::filtroSqlSucursal($c, intval($ctx['usuario_id']), $empresa_id, 'vf.sucursal_id');
    $tipos .= 'i';
    $params[] = $limite;
    $filas = asis_filas($c, "SELECT vf.venta_factura_id, vf.punto_venta_id, vf.comprobante_nro, vf.comprobante_tipo_id, ct.comprobante_tipo, ct.letra,
                   COALESCE(vf.entidad_id, 0) AS entidad_id, vf.entidad_sucursal_id, vf.consumidor_final_nombre,
                   COALESCE(e.entidad_nombre, NULLIF(vf.consumidor_final_nombre, ''), 'Consumidor Final') AS cliente,
                   vf.f_emision, vf.importe_total, vf.observaciones, vf.moneda_id,
                   (SELECT COUNT(*) FROM gestion__ventas_facturas_detalles d
                     WHERE d.venta_factura_id = vf.venta_factura_id AND d.tabla_estado_registro_id = 1) AS lineas
            FROM gestion__ventas_facturas vf
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vf.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = vf.entidad_id AND e.empresa_id = vf.empresa_id
            WHERE vf.empresa_id = ? AND vf.tabla_estado_registro_id NOT IN (2, 3, 6, 38)
              AND NOT EXISTS (SELECT 1 FROM gestion__comprobantes_tipos_dependencias ctd
                               WHERE ctd.comprobante_tipo_id = vf.comprobante_tipo_id AND ctd.empresa_id IN (0, ?)
                                 AND ctd.tabla_estado_registro_id = 1)
              $where
            ORDER BY vf.f_emision, vf.venta_factura_id LIMIT ?", $tipos, ...$params);
    foreach ($filas as &$r) {
        $r['numero'] = trim($r['comprobante_tipo'] . ' ' . emp_numero_comprobante($c, $empresa_id, intval($r['punto_venta_id']), intval($r['comprobante_nro'])));
    }
    unset($r);
    return $filas;
}

/** Filtros de período / cliente de la entrada de la IA, validados. */
function asis_copia_filtros(array $ctx, array $e): array
{
    $f = ['cliente_id' => null, 'fecha_desde' => null, 'fecha_hasta' => null];
    if (array_key_exists('cliente_id', $e) && $e['cliente_id'] !== null && $e['cliente_id'] !== '') {
        $f['cliente_id'] = intval($e['cliente_id']);
        if ($f['cliente_id'] > 0) asis_cliente($ctx, $f['cliente_id']);
    }
    foreach (['fecha_desde', 'fecha_hasta'] as $k) {
        if (trim((string)($e[$k] ?? '')) !== '') $f[$k] = asis_fecha($e[$k]);
    }
    if ($f['fecha_desde'] && $f['fecha_hasta'] && $f['fecha_desde'] > $f['fecha_hasta']) {
        throw new RuntimeException('La fecha desde es posterior a la fecha hasta.');
    }
    return $f;
}

function asis_h_facturas_emitidas(array $ctx, array $e): array
{
    $f = asis_copia_filtros($ctx, $e);
    if ($f['cliente_id'] === null && !$f['fecha_desde'] && !$f['fecha_hasta']) {
        throw new RuntimeException('Indicá el período o el cliente.');
    }
    $filas = asis_facturas_emitidas($ctx, $f, null, 101);
    $out = [];
    foreach (array_slice($filas, 0, 100) as $r) {
        $out[] = ['venta_factura_id' => intval($r['venta_factura_id']), 'numero' => $r['numero'], 'cliente' => $r['cliente'],
                  'cliente_id' => intval($r['entidad_id']), 'fecha' => $r['f_emision'], 'fecha_mostrar' => fmt_fecha($r['f_emision']),
                  'total' => round(floatval($r['importe_total']), 2), 'total_mostrar' => fmt_moneda($r['importe_total']),
                  'lineas' => intval($r['lineas'])];
    }
    return ['facturas' => $out, 'cantidad' => count($out), 'total' => round(array_sum(array_column($out, 'total')), 2),
            'nota' => count($filas) > 100 ? 'Hay más de 100: acotá el período o el cliente.' : ''];
}

/** Precio con incremento, redondeado a múltiplos de $paso (como mínimo, a centavos). */
function asis_precio_incrementado(float $precio, float $pct, float $paso): float
{
    $nuevo = $precio * (1 + $pct / 100);
    if ($paso > 0.01) return round(round($nuevo / $paso) * $paso, 2);
    return round($nuevo, 2);
}

function asis_aplicar_reemplazos(string $texto, array $reemplazos): string
{
    foreach ($reemplazos as $r) {
        if ($r['buscar'] !== '') $texto = str_ireplace($r['buscar'], $r['reemplazar'], $texto);
    }
    return $texto;
}

/**
 * Tipo para la copia en el mismo PV: el de la original si sigue habilitado para el cliente (su letra puede haber cambiado);
 * si no, el sugerido del PV con la misma condición fiscal. Devuelve [tipo_id, aviso] o lanza RuntimeException.
 */
function asis_copia_tipo(array $ctx, array $orig, array &$cache): array
{
    $cli = intval($orig['entidad_id']);
    if (!isset($cache[$cli])) $cache[$cli] = asis_puntos_venta($ctx, 'factura', $cli);
    $pv = null;
    foreach ($cache[$cli] as $p) if ($p['punto_venta_id'] === intval($orig['punto_venta_id'])) $pv = $p;
    if (!$pv) throw new RuntimeException('no tenés acceso a su punto de venta o ya no tiene facturas habilitadas');
    $tipo_id = intval($orig['comprobante_tipo_id']);
    if (in_array($tipo_id, array_column($pv['tipos'], 'comprobante_tipo_id'), true)) return [$tipo_id, null];
    $fiscal_orig = (bool)asis_fila($ctx['c'], "SELECT 1 AS x FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? AND comprobante_fiscal_id > 0",
                                   'i', $tipo_id);
    $candidatos = array_values(array_filter($pv['tipos'], fn($t) => $t['fiscal'] === $fiscal_orig));
    usort($candidatos, fn($a, $b) => intval(!empty($b['sugerido'])) <=> intval(!empty($a['sugerido'])));
    if (!$candidatos) throw new RuntimeException("el tipo {$orig['comprobante_tipo']} ya no corresponde al cliente y el punto de venta no tiene otro");
    return [$candidatos[0]['comprobante_tipo_id'],
            "Sale como {$candidatos[0]['nombre']} (la original era {$orig['comprobante_tipo']}: cambió la condición fiscal del cliente)."];
}

/**
 * Valida y calcula las copias. Cada copia es un comprobante como el de proponer_factura_venta (asis_validar_comprobante),
 * que la confirmación vuelve a validar con sus datos ya resueltos. Las que no se pueden copiar van a "omitidas" con el motivo.
 */
function asis_validar_copia_facturas(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $empresa_id = intval($ctx['empresa_id']);
    $pct = floatval($e['incremento_pct'] ?? 0);
    if ($pct <= -100 || $pct > 1000) throw new RuntimeException('El incremento tiene que estar entre -99 % y 1000 %.');
    $paso = floatval($e['redondeo'] ?? 0);
    if ($paso < 0 || $paso > 100000) throw new RuntimeException('El redondeo no es válido.');
    $fecha = asis_fecha($e['fecha'] ?? null);
    $reemplazos = [];
    foreach (array_slice((array)($e['reemplazos_texto'] ?? []), 0, 20) as $r) {
        $b = mb_substr((string)($r['buscar'] ?? ''), 0, 200);
        if (trim($b) !== '') $reemplazos[] = ['buscar' => $b, 'reemplazar' => mb_substr((string)($r['reemplazar'] ?? ''), 0, 200)];
    }

    $ids = is_array($e['venta_factura_ids'] ?? null) && $e['venta_factura_ids'] ? $e['venta_factura_ids'] : null;
    $filtros = $ids === null ? asis_copia_filtros($ctx, $e) : [];
    if ($ids === null && $filtros['cliente_id'] === null && !$filtros['fecha_desde'] && !$filtros['fecha_hasta']) {
        throw new RuntimeException('Indicá las facturas, el período o el cliente.');
    }
    if ($ids !== null && count($ids) > ASIS_COPIA_MAX) throw new RuntimeException('Como mucho ' . ASIS_COPIA_MAX . ' facturas por propuesta.');
    $origenes = asis_facturas_emitidas($ctx, $filtros, $ids, ASIS_COPIA_MAX + 1);
    if (!$origenes) throw new RuntimeException('No hay facturas emitidas que copiar con esos datos.');
    if (count($origenes) > ASIS_COPIA_MAX) {
        throw new RuntimeException('Hay más de ' . ASIS_COPIA_MAX . ' facturas: acotá el período o el cliente, o armá varias propuestas.');
    }
    if ($ids !== null) {
        $faltan = array_diff(array_unique(array_map('intval', $ids)), array_map('intval', array_column($origenes, 'venta_factura_id')));
        if ($faltan) throw new RuntimeException('Estas facturas no existen, no están emitidas o no tenés acceso: ' . implode(', ', $faltan) . '.');
    }

    $moneda_base = emp_moneda_base_id($c, $empresa_id);
    $cache_pv = [];
    $facturas = [];
    $omitidas = [];
    foreach ($origenes as $o) {
        return ['origen_id' => intval($o['venta_factura_id']), 'origen_numero' => $o['numero'], 'origen_fecha' => $o['f_emision'],
                 'origen_total' => round(floatval($o['importe_total']), 2)];
        try {
            if ($o['moneda_id'] && intval($o['moneda_id']) !== $moneda_base) throw new RuntimeException('está en otra moneda');
            [$tipo_id, $aviso_tipo] = asis_copia_tipo($ctx, $o, $cache_pv);
            $det = asis_filas($c, "SELECT producto_id, cantidad, precio_unitario, descuento_general_pct, leyenda, venta_remito_detalle_id
                    FROM gestion__ventas_facturas_detalles WHERE venta_factura_id = ? AND tabla_estado_registro_id = 1
                    ORDER BY venta_factura_detalle_id", 'i', intval($o['venta_factura_id']));
            if (!$det) throw new RuntimeException('no tiene líneas');
            $items = array_map(fn($d) => [
                'producto_id' => intval($d['producto_id']), 'cantidad' => floatval($d['cantidad']),
                'precio_unitario' => asis_precio_incrementado(floatval($d['precio_unitario']), $pct, $paso),
                'descuento_pct' => floatval($d['descuento_general_pct']),
                'leyenda' => asis_aplicar_reemplazos((string)$d['leyenda'], $reemplazos),
            ], $det);
            $v = asis_validar_comprobante($ctx, 'FACTURA_VENTA', [
                'cliente_id' => intval($o['entidad_id']), 'consumidor_final_nombre' => $o['consumidor_final_nombre'],
                'entidad_sucursal_id' => intval($o['entidad_sucursal_id']), 'punto_venta_id' => intval($o['punto_venta_id']),
                'comprobante_tipo_id' => $tipo_id, 'fecha' => $fecha,
                'observaciones' => asis_aplicar_reemplazos((string)$o['observaciones'], $reemplazos), 'items' => $items,
            ]);
            $v['pv_alternativas'] = [];   // en la copia el PV es el de la original: no se elige en la tarjeta
            if ($aviso_tipo) $v['avisos'][] = $aviso_tipo;
            if (array_filter($det, fn($d) => intval($d['venta_remito_detalle_id']) > 0)) {
                $v['avisos'][] = 'Las líneas que venían de remitos se copian como productos sueltos (no descuentan remitos).';
            }
            // Posible duplicado: el cliente ya tiene una factura de ese tipo en el mes de la copia
            if (intval($o['entidad_id']) > 0) {
                $dup = asis_fila($c, "SELECT COUNT(*) AS n FROM gestion__ventas_facturas WHERE empresa_id = ? AND entidad_id = ? AND comprobante_tipo_id = ?
                        AND tabla_estado_registro_id NOT IN (6, 38) AND venta_factura_id <> ?
                        AND f_emision >= DATE_FORMAT(?, '%Y-%m-01') AND f_emision <= LAST_DAY(?)",
                        'iiiiss', $empresa_id, intval($o['entidad_id']), $tipo_id, intval($o['venta_factura_id']), $fecha, $fecha);
                if (intval($dup['n'] ?? 0) > 0) {
                    $v['avisos'][] = "El cliente ya tiene {$dup['n']} factura(s) de este tipo en el mes de la copia: revisá que no se duplique.";
                }
            }
            $facturas[] = $base + $v;
        } catch (RuntimeException $ex) {
            $omitidas[] = $base + ['cliente' => $o['cliente'], 'motivo' => $ex->getMessage()];
        }
    }
    if (!$facturas) {
        throw new RuntimeException('No se puede copiar ninguna: '
            . implode(' ', array_map(fn($x) => "{$x['origen_numero']} ({$x['cliente']}): {$x['motivo']}.", $omitidas)));
    }
    return [
        'tipo' => 'COPIA_FACTURAS', 'incremento_pct' => $pct, 'redondeo' => $paso, 'fecha' => $fecha, 'reemplazos' => $reemplazos,
        'facturas' => $facturas, 'omitidas' => $omitidas,
        'totales' => ['cantidad' => count($facturas), 'origen' => round(array_sum(array_column($facturas, 'origen_total')), 2),
                      'total' => round(array_sum(array_map(fn($f) => $f['totales']['total'], $facturas)), 2)],
        'avisos' => [],
    ];
}

function asis_h_proponer_copia_facturas(array $ctx, array $e): array
{
    $datos = asis_validar_copia_facturas($ctx, $e);
    $id = asis_guardar_propuesta($ctx, 'COPIA_FACTURAS', $datos);
    $avisos = [];
    foreach ($datos['facturas'] as $f) foreach ($f['avisos'] as $a) $avisos[] = "{$f['origen_numero']}: $a";
    return ['propuesta_id' => $id, 'estado' => 'Esperando que el usuario la confirme en pantalla',
            'resumen' => ['facturas' => $datos['totales']['cantidad'], 'incremento_pct' => $datos['incremento_pct'], 'fecha' => $datos['fecha'],
                          'total_original' => $datos['totales']['origen'], 'total_nuevo' => $datos['totales']['total']],
            'omitidas' => array_map(fn($x) => "{$x['origen_numero']} ({$x['cliente']}): {$x['motivo']}", $datos['omitidas']),
            'avisos' => array_slice($avisos, 0, 30)];
}
