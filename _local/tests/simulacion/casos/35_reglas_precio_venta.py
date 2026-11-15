from simlib import *

# Escalas por cantidad, promociones y descuento del vendedor (doc 22, paso 4; empresa 2, módulo 2).
# Necesita en la copia: 20261116_canales_venta_01 y 20261117_reglas_precio_venta_01 / _02_pagina (--scripts).
# Prende el indicador solo en la copia.
#   python _local/tests/simulacion/run.py 35_reglas_precio_venta.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261116_canales_venta_01.sql _local/sql/mantenimiento/20261117_reglas_precio_venta_01.sql \
#       _local/sql/mantenimiento/20261117_reglas_precio_venta_02_pagina.sql

CLI = 313              # ABEJER WALTER - lista 1 (General)
P1, P2 = 1529, 1878
LISTA = 1
VEND, PERFIL_VEND = 8, 12   # usuario con el perfil "Pedidos" de la empresa 2
CTX = {}


def estructura_ok():
    return int(val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
                   "AND TABLE_NAME IN ('gestion__precios_reglas_venta', 'gestion__ventas_descuentos_topes')")) == 2 \
        and int(val("SELECT COUNT(*) FROM conf__paginas WHERE pagina_id = 146")) == 1


def pg(params, method='POST', usuario_id=1):
    return ajax('precios_reglas_venta_ajax.php', dict(params, empresa_idx=2), method, usuario_id=usuario_id)


# ---------------------------------------------------------------------------------------------
caso('Reglas de precio: preparación (indicador, solo en la copia)')
if not estructura_ok():
    brecha('faltan los scripts de reglas de precio en la copia', 'correr con --scripts 20261116 / 20261117_01 / 20261117_02')
else:
    sql("UPDATE conf__empresas SET usa_reglas_precio_venta = 1 WHERE empresa_id = 2")
    CTX['ok'] = True
    check(int(val("SELECT usa_reglas_precio_venta FROM conf__empresas WHERE empresa_id = 2")) == 1, 'indicador prendido en la copia')

caso('Reglas de precio: página Escalas y promociones')
if CTX.get('ok'):
    r = pg(dict(accion='agregar', tipo='ESCALA', nombre='Escala P1', producto_id=P1, f_desde=HOY,
                tramos=json.dumps([{'cantidad_desde': 24, 'descuento_pct': 5}, {'cantidad_desde': 120, 'descuento_pct': 8}])))
    check(ok_resp(r), 'alta de una escala con dos tramos', err(r))
    CTX['escala'] = (r.get('data') or {}).get('regla_venta_id') if isinstance(r, dict) else None
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Promo P1', producto_id=P1, descuento_pct=7, f_desde=HOY))
    check(ok_resp(r), 'alta de una promoción', err(r))
    CTX['promo'] = (r.get('data') or {}).get('regla_venta_id') if isinstance(r, dict) else None
    r = pg(dict(accion='agregar', tipo='ESCALA', nombre='Sin tramos', f_desde=HOY, tramos='[]'))
    check(not ok_resp(r) and 'tramo' in err(r), 'escala sin tramos se rechaza', err(r))
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Mal', descuento_pct=150, f_desde=HOY))
    check(not ok_resp(r), 'promoción de 150 % se rechaza', err(r))
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Fechas', descuento_pct=5, f_desde=HOY, f_hasta='2000-01-01'))
    check(not ok_resp(r) and 'hasta' in err(r), 'fecha hasta anterior a desde se rechaza', err(r))
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Pres ajena', producto_id=P1, presentacion_id=999999, descuento_pct=5, f_desde=HOY))
    check(not ok_resp(r), 'presentación que no es del producto se rechaza', err(r))
    r = pg(dict(accion='listar', estado=1), 'GET')
    reglas = (r.get('data') or {}).get('reglas', []) if isinstance(r, dict) else []
    esc = next((x for x in reglas if x.get('regla_venta_id') == CTX.get('escala')), None)
    check(len(reglas) == 2 and esc and len(esc.get('tramos') or []) == 2 and '30034' in (esc.get('alcance_texto') or ''),
          'el listado trae las dos reglas, los tramos y el alcance', str(r)[:300])
    r = pg(dict(accion='editar', regla_venta_id=CTX.get('escala'), tipo='ESCALA', nombre='Escala P1', producto_id=P1, f_desde=HOY,
                tramos=json.dumps([{'cantidad_desde': 24, 'descuento_pct': 5}, {'cantidad_desde': 120, 'descuento_pct': 8},
                                   {'cantidad_desde': 240, 'descuento_pct': 10}])))
    check(ok_resp(r) and int(val(f"SELECT COUNT(*) FROM gestion__precios_reglas_venta_tramos WHERE regla_venta_id = {CTX.get('escala') or 0}")) == 3,
          'editar reemplaza los tramos', err(r))

    r = pg(dict(accion='vista_previa', producto_id=P1, cantidad=24, cliente_descuento_pct=3), 'GET')
    d = r.get('data') or {} if isinstance(r, dict) else {}
    check(ok_resp(r) and num(d.get('auto_pct')) == 7 and d.get('regla_tipo') == 'PROMOCION', 'probar: 24 u → promo 7 %', str(r)[:300])
    r = pg(dict(accion='vista_previa', producto_id=P1, cantidad=240, cliente_descuento_pct=3), 'GET')
    d = r.get('data') or {} if isinstance(r, dict) else {}
    check(num(d.get('auto_pct')) == 10 and d.get('regla_tipo') == 'ESCALA', 'probar: 240 u → escala 10 %', str(r)[:300])

    r = pg(dict(accion='estado', regla_venta_id=CTX.get('promo'), estado=2))
    check(ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__precios_reglas_venta WHERE regla_venta_id = {CTX.get('promo') or 0}")) == 2,
          'inactivar la promoción (no se borra)', err(r))
    r = pg(dict(accion='vista_previa', producto_id=P1, cantidad=24, cliente_descuento_pct=3), 'GET')
    check(num((r.get('data') or {}).get('auto_pct')) == 5, 'sin la promo: escala 5 %', str(r)[:200])
    pg(dict(accion='estado', regla_venta_id=CTX.get('promo'), estado=1))

    r = pg(dict(accion='tope_guardar', empresa_perfil_id=PERFIL_VEND, tope_pct=5))
    topes = r.get('data') or [] if isinstance(r, dict) else []
    check(ok_resp(r) and any(t.get('empresa_perfil_id') == PERFIL_VEND and num(t.get('tope_pct')) == 5 for t in topes), 'tope 5 % al perfil Pedidos', err(r))
    r = pg(dict(accion='tope_guardar', empresa_perfil_id=PERFIL_VEND, tope_pct=10))
    check(ok_resp(r) and int(val(f"SELECT COUNT(*) FROM gestion__ventas_descuentos_topes WHERE empresa_perfil_id = {PERFIL_VEND} AND tabla_estado_registro_id = 2")) == 1,
          'cambiar el tope deja el anterior como historial', err(r))
    otro = int(val("SELECT MIN(empresa_perfil_id) FROM conf__empresas_perfiles WHERE empresa_id <> 2") or 0)
    r = pg(dict(accion='tope_guardar', empresa_perfil_id=otro, tope_pct=10))
    check(not ok_resp(r), 'perfil de otra empresa se rechaza', err(r))
    r = pg(dict(accion='lista_tope_guardar', lista_precio_id=LISTA, descuento_tope_pct=20))
    check(ok_resp(r) and num(val(f"SELECT descuento_tope_pct FROM gestion__listas_precios WHERE lista_precio_id = {LISTA}")) == 20, 'tope de la lista General 20 %', err(r))
