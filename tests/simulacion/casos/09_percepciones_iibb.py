"""Percepciones de IIBB practicadas (docs/gestion/09_percepciones_iibb.md).

Usa la config de Río Negro de la empresa 2 (script 20261010_percepciones_iibb_03_config_rio_negro.sql, se crea
inactiva): en la COPIA se habilita y se completan los datos que en producción faltan (N° de agente, régimen SIRCAR).
Clientes: 417 (sucursal 79 en Río Negro, condición IIBB Local sin sede), 535 (sin condición IIBB; en la copia se
lleva su sucursal 124 a Viedma), 313 (sucursal 52 en Chubut).
"""
from simlib import *

P1 = 1529
CLI_RN, SUC_RN = 417, 79
CLI_NI, SUC_NI = 535, 124
CLI_FUERA, SUC_FUERA = 313, 52
CTX = {}
PERIODO = HOY[:7]

cfg = uno("SELECT cfg.empresa_impuesto_config_id id, cfg.cont_cuenta_id FROM gestion__empresas_impuestos_config cfg "
          "JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id "
          "WHERE cfg.empresa_id = 2 AND cfg.impuesto_tipo_id = 6 AND j.jurisdiccion_codigo = 'DGR_RN'")
JUR_RN = int(val("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = 'DGR_RN'"))
SEDE_RN = int(val("SELECT jurisdiccion_sede_id FROM gestion__iibb_jurisdicciones_sedes WHERE codigo_comarb = '916'"))
VIEDMA = int(val("SELECT localidad_id FROM conf__localidades WHERE provincia_id = 24 AND localidad = 'VIEDMA' LIMIT 1"))

# Solo en la copia: ARCA deshabilitado (la base local lo tiene habilitado en homologación; sin llamadas externas)
sql("UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id = 2")
# Solo en la copia: habilitar la config y completar datos pendientes
sql(f"UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id = 1, f_desde = '2026-01-01', "
    f"numero_agente = '107999999', codigo_regimen_ddjj = '11', arca_tributo_id = 7 WHERE empresa_impuesto_config_id = {cfg['id']}")
sql(f"UPDATE gestion__entidades_sucursales SET localidad_id = {VIEDMA} WHERE sucursal_id = {SUC_NI}")


def estado_vf(fid):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id={fid}"))


def fila_vf(fid):
    return uno(f"SELECT vf.importe_neto, vf.importe_iva, vf.importe_otros_impuestos, vf.importe_total, vf.comprobante_id, "
               f"c.importe_total comp_total, c.importe_otros_impuestos comp_otros FROM gestion__ventas_facturas vf "
               f"JOIN gestion__comprobantes c ON c.comprobante_id = vf.comprobante_id WHERE vf.venta_factura_id = {fid}")


def percepciones(comp_id):
    return sql(f"SELECT * FROM gestion__comprobantes_impuestos WHERE comprobante_id = {comp_id} AND impuesto_tipo_id = 6 AND tabla_estado_registro_id = 1")


def vf_alta(tipo, entidad, sucursal, lineas, **extra):
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=tipo, entidad_id=entidad, entidad_sucursal_id=sucursal, f_emision=HOY, f_vto=HOY, moneda_id=1,
             tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0, observaciones='SIM percepciones',
             detalles=[dict(producto_id=p, cantidad=c, precio_unitario=pr, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)
                       for p, c, pr in lineas], **extra))
    fid = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
    return r, fid


def vf_confirmar(fid):
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js='confirma', empresa_idx=2, pagina_idx=56))
    if ok_resp(r) and estado_vf(fid) == 4:
        r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js='aprobada', empresa_idx=2, pagina_idx=56))
    return r


# ---------------------------------------------------------------------------------------------
caso('Caso 40 - Cliente Local sin alta en Río Negro: excluido en la 985/2018 (sin percepción)')
r, f0 = vf_alta(4, CLI_RN, SUC_RN, [(P1, 1, 1000)])
check(ok_resp(r) and f0, 'alta factura A', err(r))
if f0:
    v = fila_vf(f0)
    check(num(v['importe_otros_impuestos']) == 0 and abs(num(v['importe_total']) - 1210) < 0.01, 'sin percepción, total 1210', str(v))
    check(not percepciones(v['comprobante_id']), 'sin filas en comprobantes_impuestos')

# ---------------------------------------------------------------------------------------------
caso('Caso 41 - Alta en Río Negro (jurisdicción CM cargada desde el cliente): 1 % sobre el neto')
cond = val(f"SELECT entidad_condicion_iibb_id FROM gestion__entidades_condiciones_iibb WHERE entidad_id = {CLI_RN} AND tabla_estado_registro_id = 1 ORDER BY f_desde DESC LIMIT 1")
r = ajax('percepciones_iibb_clientes_ajax.php', dict(accion='agregar_jurisdiccion', entidad_id=CLI_RN, entidad_condicion_iibb_id=cond, jurisdiccion_sede_id=SEDE_RN, empresa_idx=2))
check(ok_resp(r), 'agrega Río Negro como jurisdicción de alta del cliente', err(r))
r = ajax('percepciones_iibb_clientes_ajax.php', dict(accion='datos', entidad_id=CLI_RN, empresa_idx=2), 'GET')
check(ok_resp(r) and any(j['codigo_comarb'] == '916' for j in r['data']['jurisdicciones']), 'el cliente muestra la jurisdicción 916', err(r))

prev = ajax('ventas_facturas_ajax.php', dict(accion='calcular_percepciones', empresa_idx=2, pagina_idx=56, comprobante_tipo_id=4, entidad_id=CLI_RN,
            entidad_sucursal_id=SUC_RN, sucursal_id=1, f_emision=HOY,
            detalles=[dict(producto_id=P1, cantidad=2, precio_unitario=1000, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
check(ok_resp(prev) and abs(num(prev.get('total')) - 20) < 0.01, 'vista previa: percepción 20,00 sobre neto 2000', str(prev)[:300])

r, fa = vf_alta(4, CLI_RN, SUC_RN, [(P1, 2, 1000)])
check(ok_resp(r) and fa, 'alta factura A con percepción', err(r))
if fa:
    CTX['fa'] = fa
    v = fila_vf(fa)
    check(abs(num(v['importe_otros_impuestos']) - 20) < 0.01 and abs(num(v['importe_total']) - 2440) < 0.01,
          'borrador: otros impuestos 20, total 2420 + 20', str(v))
    check(abs(num(v['comp_total']) - 2440) < 0.01, 'espejo en gestion__comprobantes con el total', str(v))
    r = vf_confirmar(fa)
    check(ok_resp(r) and estado_vf(fa) == 5, 'confirmar y aprobar -> 5', err(r))
    v = fila_vf(fa)
    ps = percepciones(v['comprobante_id'])
    check(len(ps) == 1 and ps[0]['situacion'] == 'INSCRIPTO_JURISDICCION' and abs(num(ps[0]['importe']) - 20) < 0.01
          and abs(num(ps[0]['base_imponible']) - 2000) < 0.01 and int(ps[0]['jurisdiccion_id']) == JUR_RN,
          'percepción grabada: base 2000, 1 %, 20, Río Negro', str(ps))
    det = sql(f"SELECT d.cuenta_id, d.importe_local FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
              f"WHERE a.comprobante_id = {v['comprobante_id']}")
    check(any(int(d['cuenta_id']) == int(cfg['cont_cuenta_id']) and abs(num(d['importe_local']) + 20) < 0.01 for d in det),
          'asiento: Haber 20 en Percepciones IIBB Río Negro a Depositar', str(det))
    check(abs(sum(num(d['importe_local']) for d in det)) < 0.01, 'asiento balanceado', str(det))
    r = ajax('ventas_facturas_ajax.php', dict(accion='obtener', venta_factura_id=fa, empresa_idx=2, pagina_idx=56), 'GET')
    check(ok_resp(r) and len(r['data'].get('percepciones') or []) == 1, 'obtener devuelve las percepciones (pantalla e impresión)', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 42 - Entrega fuera de Río Negro: sin percepción')
r, ff = vf_alta(4, CLI_FUERA, SUC_FUERA, [(P1, 1, 1000)])
if ff:
    check(num(fila_vf(ff)['importe_otros_impuestos']) == 0, 'cliente con sucursal en Chubut: sin percepción')

# ---------------------------------------------------------------------------------------------
caso('Caso 43 - No inscripto con entrega en Río Negro: 0 % pero queda registrada para la DDJJ')
r, fn = vf_alta(4, CLI_NI, SUC_NI, [(P1, 1, 500)])
check(ok_resp(r) and fn, 'alta factura', err(r))
if fn:
    vf_confirmar(fn)
    v = fila_vf(fn)
    ps = percepciones(v['comprobante_id'])
    check(len(ps) == 1 and ps[0]['situacion'] == 'NO_INSCRIPTO' and num(ps[0]['importe']) == 0 and abs(num(v['importe_total']) - 605) < 0.01,
          'fila NO_INSCRIPTO con importe 0, total sin cambios', str(ps) + ' ' + str(v))

# ---------------------------------------------------------------------------------------------
caso('Caso 44 - NC asociada: revierte con la alícuota de la factura')
fa = CTX.get('fa')
if fa:
    comp_fa = fila_vf(fa)['comprobante_id']
    r, nc = vf_alta(7, CLI_RN, SUC_RN, [(P1, 1, 500)], comprobante_asociado_id=comp_fa)
    check(ok_resp(r) and nc, 'alta NC A asociada', err(r))
    if nc:
        CTX['nc'] = nc
        r = vf_confirmar(nc)
        v = fila_vf(nc)
        ps = percepciones(v['comprobante_id'])
        check(ok_resp(r) and len(ps) == 1 and abs(num(ps[0]['importe']) - 5) < 0.01 and abs(num(v['importe_total']) - 610) < 0.01,
              'NC: percepción 5 (1 % de 500), total 605 + 5', err(r) + ' ' + str(ps) + ' ' + str(v))
        det = sql(f"SELECT d.cuenta_id, d.importe_local FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
                  f"WHERE a.comprobante_id = {v['comprobante_id']}")
        check(any(int(d['cuenta_id']) == int(cfg['cont_cuenta_id']) and abs(num(d['importe_local']) - 5) < 0.01 for d in det),
              'asiento de la NC: Debe 5 en la cuenta a depositar', str(det))

# ---------------------------------------------------------------------------------------------
caso('Caso 45 - Certificado de exclusión 100 %: sin percepción')
r = ajax('percepciones_iibb_clientes_ajax.php', dict(accion='agregar_exclusion', entidad_id=CLI_RN, jurisdiccion_id=JUR_RN, porcentaje_exclusion=100,
         numero_certificado='SIM-1', f_desde=HOY, empresa_idx=2))
check(ok_resp(r), 'carga del certificado', err(r))
r, fx = vf_alta(4, CLI_RN, SUC_RN, [(P1, 1, 1000)])
if fx:
    check(num(fila_vf(fx)['importe_otros_impuestos']) == 0, 'con certificado 100 %: sin percepción', str(fila_vf(fx)))
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fx, accion_js='eliminar', empresa_idx=2, pagina_idx=56))
excl = val(f"SELECT entidad_iibb_exclusion_id FROM gestion__entidades_iibb_exclusiones WHERE entidad_id = {CLI_RN} AND numero_certificado = 'SIM-1'")
r = ajax('percepciones_iibb_clientes_ajax.php', dict(accion='inhabilitar_exclusion', id=excl, empresa_idx=2))
check(ok_resp(r), 'inhabilitar el certificado', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 46 - Sin sucursal del cliente: el borrador se guarda con aviso y no se confirma')
r, fs = vf_alta(4, CLI_RN, '', [(P1, 1, 1000)])
check(ok_resp(r) and fs and 'sucursal' in (r.get('message') or ''), 'borrador guardado con aviso de sucursal', err(r) + ' ' + str(r.get('message')))
if fs:
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fs, accion_js='confirma', empresa_idx=2, pagina_idx=56))
    check(not ok_resp(r) and estado_vf(fs) == 3, 'confirmar se rechaza y queda en Borrador', err(r))
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fs, accion_js='eliminar', empresa_idx=2, pagina_idx=56))
    check(ok_resp(r), 'eliminar el borrador', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 47 - DDJJ: listado, archivo SIRCAR y liquidación (I_LIQ)')
r = ajax('percepciones_iibb_ajax.php', dict(accion='listar', jurisdiccion_id=JUR_RN, periodo=PERIODO, empresa_idx=2), 'GET')
check(ok_resp(r), 'listar período', err(r))
if ok_resp(r):
    s = r['data']['resumen']
    check(abs(num(s['percibido']) - 15) < 0.01 and abs(num(s['pendiente']) - 15) < 0.01 and s['cantidad'] >= 3,
          'neto del período 20 - 5 + 0 = 15', str(s))
r = ajax('percepciones_iibb_ajax.php', dict(accion='exportar', jurisdiccion_id=JUR_RN, periodo=PERIODO, empresa_idx=2), 'GET')
raw = r.get('_raw', '') if isinstance(r, dict) else ''
lineas = [l for l in raw.splitlines() if l.strip()]
check(len(lineas) >= 3 and all(len(l.split(',')) == 11 for l in lineas), 'archivo SIRCAR: un renglón por percepción, 11 campos', raw[:400])
check(lineas and any(l.split(',')[1] == '102' for l in lineas) and all(len(l.split(',')) == 11 and l.split(',')[9] == '011' and l.split(',')[10] == '916' for l in lineas),
      'NC con tipo 102, régimen 011 y jurisdicción 916', raw[:400])
org = val("SELECT entidad_id FROM gestion__entidades WHERE empresa_id = 2 AND es_proveedor = 1 AND tabla_estado_registro_id = 1 LIMIT 1")
r = ajax('percepciones_iibb_ajax.php', dict(accion='liquidar', jurisdiccion_id=JUR_RN, periodo=PERIODO, entidad_id=org, sucursal_id=1, f_vto=HOY, empresa_idx=2))
check(ok_resp(r) and abs(num(r['data']['total']) - 15) < 0.01, 'liquidación por 15', err(r))
if ok_resp(r):
    liq = r['data']['comprobante_id']
    check(val(f"SELECT COUNT(*) FROM gestion__comprobantes_impuestos WHERE liquidacion_comprobante_id = {liq}") == str(r['data']['registros']),
          'percepciones marcadas como declaradas')
    c = uno(f"SELECT c.importe_total, c.importe_pendiente, ct.codigo FROM gestion__comprobantes c JOIN gestion__comprobantes_tipos ct "
            f"ON ct.comprobante_tipo_id = c.comprobante_tipo_id WHERE c.comprobante_id = {liq}")
    check(c and c['codigo'] == 'I_LIQ' and abs(num(c['importe_pendiente']) - 15) < 0.01, 'I_LIQ con saldo 15 para pagar', str(c))
    det = sql(f"SELECT d.cuenta_id, d.importe_local FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id WHERE a.comprobante_id = {liq}")
    check(det and all(int(d['cuenta_id']) == int(cfg['cont_cuenta_id']) for d in det) and abs(sum(num(d['importe_local']) for d in det)) < 0.01,
          'asiento de la I_LIQ: Debe y Haber en la cuenta a depositar (no crea gasto)', str(det))
    r = ajax('percepciones_iibb_ajax.php', dict(accion='liquidar', jurisdiccion_id=JUR_RN, periodo=PERIODO, entidad_id=org, sucursal_id=1, f_vto=HOY, empresa_idx=2))
    check(not ok_resp(r) and 'pendientes' in err(r), 'segunda liquidación rechazada (nada pendiente)', err(r))
nc = CTX.get('nc')
if nc:
    comp_nc = fila_vf(nc)['comprobante_id']
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=nc, accion_js='anular', empresa_idx=2, pagina_idx=56))
    check(len(percepciones(comp_nc)) == 1, 'la percepción declarada de la NC sigue activa aunque se intente anular', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 48 - Padrón SIRCIP: con padrón manda la alícuota del padrón; dígito 2 = falta de alta; dígito 3 = no se percibe')
sql(f"UPDATE gestion__empresas_impuestos_config SET formato_padron = 'SIRCIP' WHERE empresa_impuesto_config_id = {cfg['id']}")
sql(f"INSERT INTO gestion__empresas_impuestos_config_situaciones (empresa_impuesto_config_id, situacion, requiere_territorio, alicuota, tipo_registro_ddjj, declarar_si_cero, leyenda, tabla_estado_registro_id) "
    f"VALUES ({cfg['id']}, 'PADRON', 0, 0, 1, 1, NULL, 1) ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1")
sql(f"INSERT INTO gestion__padrones_iibb_importaciones (empresa_id, jurisdiccion_id, formato, periodo, archivo_nombre, archivo_hash) "
    f"VALUES (2, {JUR_RN}, 'SIRCIP', '{PERIODO.replace('-', '')}', 'sim.txt', 'x')")
imp = val("SELECT MAX(padron_importacion_id) FROM gestion__padrones_iibb_importaciones")
cuit = val(f"SELECT REPLACE(cuit, '-', '') FROM gestion__entidades WHERE entidad_id = {CLI_FUERA}")
sql(f"INSERT INTO gestion__padrones_iibb (empresa_id, jurisdiccion_id, padron_importacion_id, periodo, cuit, alicuota_percepcion, letra_alicuota, crc, situacion_jurisdiccion) "
    f"VALUES (2, {JUR_RN}, {imp}, '{PERIODO.replace('-', '')}', '{cuit}', 2.5, 'S', 42, 1)")
r, fp = vf_alta(4, CLI_FUERA, SUC_FUERA, [(P1, 1, 1000)])
if fp:
    ps = percepciones(fila_vf(fp)['comprobante_id'])
    check(len(ps) == 1 and ps[0]['situacion'] == 'PADRON' and abs(num(ps[0]['importe']) - 25) < 0.01 and ps[0]['padron_crc'] == '42',
          'en padrón (dígito 1): 2,5 % aunque la entrega sea fuera de Río Negro, con el CRC', str(ps))
sql(f"UPDATE gestion__padrones_iibb SET situacion_jurisdiccion = 3 WHERE cuit = '{cuit}'")
r, fp3 = vf_alta(4, CLI_FUERA, SUC_FUERA, [(P1, 1, 1000)])
if fp3:
    check(not percepciones(fila_vf(fp3)['comprobante_id']), 'dígito 3 (sin sobre alícuota): no se percibe')
