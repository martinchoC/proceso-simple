"""Percepción de IVA practicada (etapa 7 de _local/docs/gestion/10_retenciones_percepciones_relevamiento.md).

En la copia se da de alta una config de PRUEBA del impuesto 22 para la empresa 2 (alícuota inventada para el caso,
no normativa): responsable inscripto 3 %, monotributista sin configurar. Se verifica el cálculo en la factura
(junto con las percepciones IIBB), el asiento, la exclusión del cliente, el libro y el registro, el cierre del
período y la liquidación en Control Fiscal. ARCA deshabilitado en la copia.
"""
from simlib import *

for _s in ('20261015_retenciones_practicadas_01_padron.sql', '20261016_retenciones_practicadas_02_registro.sql',
           '20261016_retenciones_practicadas_03_numeracion_jurisdiccion.sql', '20261016_retenciones_practicadas_04_regimenes.sql',
           '20261016_cierres_fiscales_01_estructura.sql', '20261016_cierres_fiscales_02_registro_inscripciones.sql',
           '20261016_cierres_fiscales_03_liquidacion_retenciones.sql', '20261016_retenciones_sufridas_02_certificados.sql',
           '20261016_percepcion_iva_01.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))
sql("UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id IN (2, 6)")
sql("UPDATE gestion__impuestos_periodos_cierres SET tabla_estado_registro_id = 38 WHERE empresa_id IN (2, 6)")

PIVA = 22
PER = HOY[:7]
CLI_RI = 313
CLI_MT = int(val("SELECT e.entidad_id FROM gestion__entidades e JOIN gestion__entidades_condiciones_fiscales ecf ON ecf.entidad_id = e.entidad_id "
                 "AND ecf.tabla_estado_registro_id = 1 AND ecf.f_hasta IS NULL WHERE e.empresa_id = 2 AND EXISTS (SELECT 1 FROM gestion__entidades_roles xr JOIN gestion__roles_entidades xc ON xc.rol_entidad_id = xr.rol_entidad_id AND xc.rol_entidad_codigo = 'CLI' WHERE xr.entidad_id = e.entidad_id AND xr.f_baja IS NULL AND xr.tabla_estado_registro_id = 1) "
                 "AND ecf.condicion_fiscal_id = 3 AND LENGTH(e.cuit) = 11 ORDER BY e.entidad_id LIMIT 1") or 0)
CTA = int(val("SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE empresa_id = 2 AND codigo = '2.1.04.05'"))   # SICORE a Depositar
ORG = int(val("SELECT entidad_id FROM gestion__entidades WHERE empresa_id = 2 AND EXISTS (SELECT 1 FROM gestion__entidades_roles xr JOIN gestion__roles_entidades xc ON xc.rol_entidad_id = xr.rol_entidad_id AND xc.rol_entidad_codigo = 'PRO' WHERE xr.entidad_id = gestion__entidades.entidad_id AND xr.f_baja IS NULL AND xr.tabla_estado_registro_id = 1) AND tabla_estado_registro_id = 1 ORDER BY entidad_id LIMIT 1"))


def cf(params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', 2)
    return ajax('cierres_fiscales_ajax.php', p, method)


def factura(entidad, precio=1000, obs='SIM perc IVA'):
    """Factura A confirmada y aprobada (estado 5). Devuelve (venta_factura_id, comprobante_id, respuesta)."""
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=4, entidad_id=entidad, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
             descuento_general_pct=0, observaciones=obs,
             detalles=[dict(producto_id=1529, cantidad=1, precio_unitario=precio, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
    vf = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
    if not vf:
        return None, 0, r
    for acc in ('confirma', 'aprobada'):
        est = int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {vf}") or 0)
        if est == 5:
            break
        r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js=acc, empresa_idx=2, pagina_idx=56))
        if not ok_resp(r):
            break
    comp = int(val(f"SELECT comprobante_id FROM gestion__ventas_facturas WHERE venta_factura_id = {vf}") or 0)
    return vf, comp, r


def perc_iva(comp):
    return uno(f"SELECT * FROM gestion__comprobantes_impuestos WHERE comprobante_id = {int(comp or 0)} AND impuesto_tipo_id = {PIVA} "
               f"AND tabla_estado_registro_id = 1")


def vf_estado(vf):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {int(vf or 0)}") or 0)


# ---------------------------------------------------------------------------------------------
caso('Caso V1 - Configuración de prueba del agente de percepción de IVA')
check(val(f"SELECT impuesto_tipo FROM gestion__impuestos_tipos WHERE impuesto_tipo_id = {PIVA}") == 'Percepción IVA Efectuada',
      'el script dio de alta el impuesto 22 en el catálogo global')
sql(f"DELETE FROM gestion__empresas_impuestos_config_subgrupos WHERE empresa_impuesto_config_id IN "
    f"(SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config WHERE empresa_id = 2 AND impuesto_tipo_id = {PIVA})")
sql(f"DELETE FROM gestion__empresas_impuestos_config WHERE empresa_id = 2 AND impuesto_tipo_id = {PIVA}")
sql(f"INSERT INTO gestion__empresas_impuestos_config (empresa_id, impuesto_tipo_id, jurisdiccion_id, cont_cuenta_id, base_calculo, alicuota, "
    f"f_desde, tipo_calculo, tabla_estado_registro_id, arca_tributo_id) VALUES (2, {PIVA}, 1, {CTA}, 'NETO_GRAVADO', 0, '2026-01-01', 'regla', 1, 99)")
CFG = int(val(f"SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config WHERE empresa_id = 2 AND impuesto_tipo_id = {PIVA}"))
sql(f"INSERT INTO gestion__empresas_impuestos_config_subgrupos (empresa_impuesto_config_id, comprobante_subgrupo_id, tabla_estado_registro_id) "
    f"SELECT {CFG}, comprobante_subgrupo_id, 1 FROM gestion__empresas_impuestos_config_subgrupos WHERE empresa_impuesto_config_id = 25 AND tabla_estado_registro_id = 1")
r = ajax('empresas_impuestos_config_ajax.php', dict(accion='obtener_agente', config_id=CFG, empresa_idx=2), 'GET')
sits = [s['situacion'] for s in (r.get('data') or {}).get('situaciones', [])] if ok_resp(r) else []
check(ok_resp(r) and r['data']['es_percepcion_iva'] and sits == ['RESPONSABLE_INSCRIPTO', 'NO_CATEGORIZADO', 'MONOTRIBUTO', 'EXENTO', 'CONSUMIDOR_FINAL'],
      'la solapa Agente de Percepción ofrece las situaciones frente al IVA', str(sits))
situaciones = [dict(situacion=s, activa=1 if s == 'RESPONSABLE_INSCRIPTO' else 0, alicuota=3 if s == 'RESPONSABLE_INSCRIPTO' else 0,
                    requiere_territorio=0, declarar_si_cero=0, tipo_registro_ddjj='', leyenda='Percepción IVA RG SIM' if s == 'RESPONSABLE_INSCRIPTO' else '')
               for s in sits]
r = ajax('empresas_impuestos_config_ajax.php', dict(accion='guardar_agente', config_id=CFG, empresa_idx=2, numero_agente='SIM-IVA',
         criterio_territorial='ENTREGA', base_calculo='NETO_GRAVADO', minimo_imponible=0, importe_minimo_percepcion=0, arca_tributo_id=99,
         codigo_jurisdiccion_cm='', formato_padron='', formato_ddjj='', codigo_regimen_ddjj='', situaciones=json.dumps(situaciones)))
check(ok_resp(r), 'guardada: responsable inscripto 3 % (valor de prueba), el resto no percibe', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso V2 - Factura A a un responsable inscripto: percepción, total y asiento')
vf, comp, r = factura(CLI_RI)
p = perc_iva(comp)
check(vf and vf_estado(vf) == 5, 'factura confirmada', err(r))
check(p and abs(num(p['base_imponible']) - 1000) < 0.01 and abs(num(p['alicuota']) - 3) < 0.0001 and abs(num(p['importe']) - 30) < 0.01
      and p['situacion'] == 'RESPONSABLE_INSCRIPTO' and p['leyenda'] == 'Percepción IVA RG SIM',
      'percepción de IVA: base 1.000 (neto gravado), 3 %, $30', str(p))
f = uno(f"SELECT importe_otros_impuestos, importe_total FROM gestion__ventas_facturas WHERE venta_factura_id = {int(vf or 0)}")
otras = num(val(f"SELECT IFNULL(SUM(importe),0) FROM gestion__comprobantes_impuestos WHERE comprobante_id = {comp} AND tabla_estado_registro_id = 1"))
check(f and abs(num(f['importe_otros_impuestos']) - otras) < 0.01 and abs(num(f['importe_total']) - (1210 + otras)) < 0.01,
      'otros impuestos y total incluyen la percepción (y las de IIBB si las hay)', str(f))
a = num(val(f"SELECT IFNULL(SUM(d.importe_local),0) FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
            f"WHERE a.comprobante_id = {comp} AND a.tabla_estado_registro_id NOT IN (2,4,6,38) AND d.cuenta_id = {CTA}"))
check(abs(a + 30) < 0.01, 'asiento: Haber $30 en la cuenta de la config', str(a))

# ---------------------------------------------------------------------------------------------
caso('Caso V3 - Monotributista (situación sin configurar) y exclusión del cliente')
if CLI_MT:
    # Vista previa del cálculo (sin grabar): la factura completa pediría la sucursal del cliente para IIBB Río Negro
    det = json.dumps([dict(producto_id=1529, cantidad=1, precio_unitario=1000, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)])
    r = ajax('ventas_facturas_ajax.php', dict(accion='calcular_percepciones', empresa_idx=2, pagina_idx=56, comprobante_tipo_id=4, entidad_id=CLI_MT,
             sucursal_id=1, f_emision=HOY, detalles=det))
    r_ri = ajax('ventas_facturas_ajax.php', dict(accion='calcular_percepciones', empresa_idx=2, pagina_idx=56, comprobante_tipo_id=4, entidad_id=CLI_RI,
                sucursal_id=1, f_emision=HOY, detalles=det))
    check(ok_resp(r) and not any(int(x.get('impuesto_tipo_id', 0)) == PIVA for x in r.get('percepciones', []))
          and any(int(x.get('impuesto_tipo_id', 0)) == PIVA and abs(x['importe'] - 30) < 0.01 for x in r_ri.get('percepciones', [])),
          'vista previa: al monotributista no se le percibe (situación no configurada), al inscripto sí', str(r)[:300])
r = ajax('percepciones_iibb_clientes_ajax.php', dict(accion='datos', entidad_id=CLI_RI, empresa_idx=2), 'GET')
jurs = [j for j in (r.get('data') or {}).get('jurisdicciones_provinciales', [])] if ok_resp(r) else []
check(any(int(j['jurisdiccion_id']) == 1 and j['jurisdiccion_nombre'].startswith('IVA') for j in jurs),
      'el bloque de exclusiones del cliente ofrece "IVA - AFIP Nación"', str(jurs[:2]))
r = ajax('percepciones_iibb_clientes_ajax.php', dict(accion='agregar_exclusion', entidad_id=CLI_RI, empresa_idx=2, jurisdiccion_id=1,
         porcentaje_exclusion=50, f_desde='2026-01-01', f_hasta='', numero_certificado='SIM-EXCL-IVA', observaciones='SIM'))
excl = uno(f"SELECT impuesto_tipo_id, sentido FROM gestion__entidades_iibb_exclusiones WHERE entidad_id = {CLI_RI} AND numero_certificado = 'SIM-EXCL-IVA' "
           f"AND tabla_estado_registro_id = 1 ORDER BY entidad_iibb_exclusion_id DESC LIMIT 1")
check(ok_resp(r) and excl and int(excl['impuesto_tipo_id'] or 0) == PIVA and excl['sentido'] == 'PERCEPCION',
      'certificado de exclusión del 50 % cargado como percepción de IVA', err(r) + str(excl))
vf3, comp3, r = factura(CLI_RI, obs='SIM perc IVA excl')
p3 = perc_iva(comp3)
check(p3 and abs(num(p3['alicuota']) - 1.5) < 0.0001 and abs(num(p3['importe']) - 15) < 0.01, 'con la exclusión: 1,5 % y $15', str(p3))
sql(f"UPDATE gestion__entidades_iibb_exclusiones SET tabla_estado_registro_id = 2 WHERE entidad_id = {CLI_RI} AND numero_certificado = 'SIM-EXCL-IVA'")

# ---------------------------------------------------------------------------------------------
caso('Caso V4 - Libro IVA, registro y cuadre')
r = ajax('iva_posiciones_ajax.php', dict(accion='libro', empresa_idx=2, periodo=PER, libro='VENTAS'), 'GET')
col = next((c for c in (r.get('data') or {}).get('columnas', []) if c['clave'].startswith(f'perc_{PIVA}_')), None) if ok_resp(r) else None
fila = next((x for x in r['data']['filas'] if col and abs(x['valores'].get(col['clave'], 0) - 30) < 0.01), None) if col else None
check(col and col['titulo'].startswith('Percepción IVA Efectuada') and fila and abs(fila['valores']['control']) < 0.05,
      'libro de ventas: columna "Percepción IVA Efectuada" con los $30 y control en cero', str(col))
r = cf(dict(accion='registro', desde=PER, hasta=PER, circuito='PERC_PRACTICADA', impuesto_tipo_id=PIVA), 'GET')
check(ok_resp(r) and len(r['data']['filas']) >= 2 and abs(sum(x['importe'] for x in r['data']['filas']) - 45) < 0.01,
      'registro: percepciones practicadas de IVA del mes ($30 + $15)', err(r))
r = cf(dict(accion='cuadre', desde=PER, hasta=PER), 'GET')
dif = [x for x in (r.get('data') or {}).get('diferencias', []) if 'IVA Efectuada' in x['impuestos']] if ok_resp(r) else None
check(ok_resp(r) and dif == [], 'cuadre: la percepción de IVA está contabilizada en su cuenta por el mismo importe', str(dif))

# ---------------------------------------------------------------------------------------------
caso('Caso V5 - Cierre del período y liquidación')
r = cf(dict(accion='cerrar', impuesto_tipo_id=PIVA, jurisdiccion_id=0, periodo=PER, observaciones='SIM'))
cierre = (r.get('data') or {}).get('cierre_id') if ok_resp(r) else None
check(cierre, 'la percepción de IVA es una obligación que se puede cerrar', err(r))
r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
         comprobante_tipo_id=4, entidad_id=CLI_RI, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
         descuento_general_pct=0, observaciones='SIM perc IVA cerrado',
         detalles=[dict(producto_id=1529, cantidad=1, precio_unitario=1000, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
vf4 = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf4, accion_js='confirma', empresa_idx=2, pagina_idx=56))
check(not ok_resp(r) and 'cerrado' in err(r) and vf_estado(vf4) == 3, 'con el período cerrado no se confirma la factura', err(r))
cf(dict(accion='reabrir', cierre_id=cierre, motivo='SIM'))
r = cf(dict(accion='liquidacion_resumen', periodo=PER), 'GET')
fl = next((x for x in (r.get('data') or {}).get('filas', []) if x['impuesto_tipo_id'] == PIVA), None) if ok_resp(r) else None
check(fl and fl['pendientes'] >= 2 and abs(fl['importe_pendiente'] - 45) < 0.01 and fl['tiene_cuenta'],
      'liquidaciones: percepción de IVA con $45 pendientes', str(fl))
r = cf(dict(accion='liquidar_retenciones', periodo=PER, impuesto_tipo_id=PIVA, jurisdiccion_id=0, entidad_id=ORG, sucursal_id=1, f_vto=HOY, nro_ddjj='SIM'))
liq = (r.get('data') or {}).get('comprobante_id') if ok_resp(r) else None
check(liq and abs(r['data']['total'] - 45) < 0.01 and int(val(f"SELECT COUNT(*) FROM gestion__comprobantes_impuestos WHERE liquidacion_comprobante_id = {liq}")) >= 2,
      'I_LIQ por $45 y percepciones marcadas como declaradas', err(r))
r = cf(dict(accion='liquidacion_detalle', comprobante_id=liq), 'GET')
check(ok_resp(r) and len(r['data']) >= 2 and all(x['comprobante'] for x in r['data']), 'detalle de la liquidación con sus facturas', err(r))
r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js='anular', empresa_idx=2, pagina_idx=56))
check(not ok_resp(r) and vf_estado(vf) == 5, 'una factura con la percepción declarada no se anula', err(r))
r = cf(dict(accion='anular_liquidacion_retenciones', comprobante_id=liq, motivo='SIM'))
check(ok_resp(r) and int(val(f"SELECT COUNT(*) FROM gestion__comprobantes_impuestos WHERE liquidacion_comprobante_id = {int(liq or 0)}")) == 0,
      'anular la liquidación deja las percepciones pendientes otra vez', err(r))
sql(f"UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id = 2 WHERE empresa_impuesto_config_id = {CFG}")
