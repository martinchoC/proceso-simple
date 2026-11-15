from simlib import *

# Marca comercial en el alcance de escalas y promociones (doc 25, paso 6-3; empresa 2, módulo 2).
# Necesita en la copia los scripts de reglas (20261116 / 20261117_01 / _02) y 20261120_marcas_comerciales_01.
# Prende los indicadores solo en la copia y los deja como estaban.
#   python _local/tests/simulacion/run.py 39_marca_escalas.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261116_canales_venta_01.sql _local/sql/mantenimiento/20261117_reglas_precio_venta_01.sql \
#       _local/sql/mantenimiento/20261117_reglas_precio_venta_02_pagina.sql _local/sql/mantenimiento/20261120_marcas_comerciales_01.sql

P1, P2 = 1529, 1878
CTX = {}


def pg(params, method='POST'):
    return ajax('precios_reglas_venta_ajax.php', dict(params, empresa_idx=2), method)


def prod(params, method='POST'):
    return ajax('productos_ajax.php', dict(params, empresa_idx=2, pagina_idx=40), method)


def data(r):
    return r.get('data') if isinstance(r, dict) else None


def previa(producto):
    r = pg(dict(accion='vista_previa', producto_id=producto, cantidad=1, cliente_descuento_pct=0), 'GET')
    return r, (data(r) or {})


# ---------------------------------------------------------------------------------------------
caso('Marca en escalas y promociones: preparación (indicadores, solo en la copia)')
if int(val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__precios_reglas_venta' "
           "AND COLUMN_NAME = 'marca_comercial_id'")) != 1:
    brecha('faltan los scripts de reglas y marcas en la copia', 'correr con --scripts 20261116 / 20261117_01 / _02 / 20261120')
else:
    CTX['ind'] = uno("SELECT usa_reglas_precio_venta r, usa_marcas_comerciales m FROM conf__empresas WHERE empresa_id = 2")
    CTX['marca_p1'] = val("SELECT marca_comercial_id FROM gestion__productos WHERE producto_id = %d" % P1)
    CTX['cat_p1'] = int(val("SELECT IFNULL(producto_categoria_id, 0) FROM gestion__productos WHERE producto_id = %d" % P1))
    CTX['otra_cat'] = int(val("SELECT IFNULL(MIN(producto_categoria_id), 0) FROM gestion__productos_categorias WHERE empresa_id = 2 "
                              "AND tabla_estado_registro_id = 1 AND producto_categoria_id <> %d" % CTX['cat_p1']))
    # Las reglas que ya hubiera en la copia no intervienen
    CTX['activas'] = [int(x['regla_venta_id']) for x in sql("SELECT regla_venta_id FROM gestion__precios_reglas_venta WHERE empresa_id = 2 AND tabla_estado_registro_id = 1")]
    if CTX['activas']:
        sql("UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 2 WHERE regla_venta_id IN (%s)" % ','.join(map(str, CTX['activas'])))
    sql("UPDATE conf__empresas SET usa_reglas_precio_venta = 1, usa_marcas_comerciales = 0 WHERE empresa_id = 2")
    r = pg(dict(accion='catalogos'), 'GET')
    check(ok_resp(r) and 'marcas' not in (data(r) or {}), 'sin el indicador de marcas, la pantalla no ofrece marca', str(r)[:200])
    sql("UPDATE conf__empresas SET usa_marcas_comerciales = 1 WHERE empresa_id = 2")
    suf = str(int(val("SELECT IFNULL(MAX(marca_comercial_id), 0) + 1 FROM gestion__productos_marcas")))
    r = prod(dict(accion='marcas_guardar', nombre='SIM Marca ' + suf))
    m1 = int((data(r) or {}).get('marca_comercial_id') or 0)
    r2 = prod(dict(accion='marcas_guardar', nombre='SIM Otra ' + suf))
    m2 = int((data(r2) or {}).get('marca_comercial_id') or 0)
    r3 = prod(dict(accion='marcas_asignar', marca_comercial_id=m1, productos=json.dumps([P1])))
    check(ok_resp(r) and ok_resp(r2) and ok_resp(r3) and m1 and m2, 'dos marcas y la primera puesta a P1', err(r) + err(r3))
    CTX.update(ok=bool(m1 and m2), m1=m1, m2=m2, reglas=[])

caso('Marca en escalas y promociones: alta y resolución')
if CTX.get('ok'):
    m1, m2 = CTX['m1'], CTX['m2']
    r = pg(dict(accion='catalogos'), 'GET')
    check(any(int(x['id']) == m1 for x in ((data(r) or {}).get('marcas') or [])), 'con marcas, la pantalla ofrece las marcas', str(r)[:200])
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Promo marca', descuento_pct=9, f_desde=HOY, marca_comercial_id=m1))
    rid = int((data(r) or {}).get('regla_venta_id') or 0)
    CTX['reglas'].append(rid)
    check(ok_resp(r) and int(val("SELECT IFNULL(marca_comercial_id, 0) FROM gestion__precios_reglas_venta WHERE regla_venta_id = %d" % rid)) == m1,
          'alta de una promoción para toda la marca', err(r))
    r, d = previa(P1)
    check(num(d.get('auto_pct')) == 9 and int(d.get('regla_id') or 0) == rid, 'un producto de la marca recibe la promoción', str(r)[:300])
    r, d = previa(P2)
    check(int(d.get('regla_id') or 0) != rid, 'un producto de otra marca no', str(r)[:300])
    r = pg(dict(accion='listar', estado=1), 'GET')
    regla = next((x for x in ((data(r) or {}).get('reglas') or []) if int(x['regla_venta_id']) == rid), {})
    check('Marca SIM Marca' in (regla.get('alcance_texto') or ''), 'el alcance dice la marca', str(regla)[:200])

    # Marca + categoría: tienen que coincidir las dos
    if CTX['cat_p1']:
        r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Marca y categoría', descuento_pct=11, f_desde=HOY,
                    producto_categoria_id=CTX['cat_p1'], marca_comercial_id=m1))
        r_ok = int((data(r) or {}).get('regla_venta_id') or 0)
        CTX['reglas'].append(r_ok)
        r, d = previa(P1)
        check(num(d.get('auto_pct')) == 11 and int(d.get('regla_id') or 0) == r_ok, 'marca y categoría del producto: aplica', str(r)[:300])
        sql("UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 2 WHERE regla_venta_id = %d" % r_ok)
    if CTX['otra_cat']:
        r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Marca otra categoría', descuento_pct=12, f_desde=HOY,
                    producto_categoria_id=CTX['otra_cat'], marca_comercial_id=m1))
        r_no = int((data(r) or {}).get('regla_venta_id') or 0)
        CTX['reglas'].append(r_no)
        r, d = previa(P1)
        check(int(d.get('regla_id') or 0) != r_no, 'marca del producto pero otra categoría: no aplica', str(r)[:300])
        sql("UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 2 WHERE regla_venta_id = %d" % r_no)

    # Con producto elegido, la marca no se guarda (el producto manda)
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Producto y marca', producto_id=P2, descuento_pct=4, f_desde=HOY, marca_comercial_id=m1))
    rp = int((data(r) or {}).get('regla_venta_id') or 0)
    CTX['reglas'].append(rp)
    check(ok_resp(r) and val("SELECT marca_comercial_id FROM gestion__precios_reglas_venta WHERE regla_venta_id = %d" % rp) is None,
          'con producto, la marca queda vacía', err(r))
    # Marca inactiva: no se acepta en una regla nueva; la que ya tenía la regla, sí
    prod(dict(accion='marcas_estado', marca_comercial_id=m2, estado=2))
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Inactiva', descuento_pct=4, f_desde=HOY, marca_comercial_id=m2))
    check(not ok_resp(r) and 'marca' in err(r).lower(), 'una marca inactiva se rechaza', err(r))
    prod(dict(accion='marcas_estado', marca_comercial_id=m1, estado=2))
    r = pg(dict(accion='editar', regla_venta_id=rid, tipo='PROMOCION', nombre='Promo marca', descuento_pct=9, f_desde=HOY, marca_comercial_id=m1))
    check(ok_resp(r), 'editar una regla que ya tenía la marca (ahora inactiva) se acepta', err(r))
    prod(dict(accion='marcas_estado', marca_comercial_id=m1, estado=1))
    # Sin el campo en el request (pantalla vieja / sin marcas): no se toca
    r = pg(dict(accion='editar', regla_venta_id=rid, tipo='PROMOCION', nombre='Promo marca', descuento_pct=9, f_desde=HOY))
    check(ok_resp(r) and int(val("SELECT IFNULL(marca_comercial_id, 0) FROM gestion__precios_reglas_venta WHERE regla_venta_id = %d" % rid)) == m1,
          'editar sin el campo marca no la borra', err(r))

    # Marcas apagadas: la regla con marca no toca a nadie (no se vuelve "toda la empresa")
    sql("UPDATE conf__empresas SET usa_marcas_comerciales = 0 WHERE empresa_id = 2")
    r, d = previa(P1)
    check(int(d.get('regla_id') or 0) != rid, 'con marcas apagadas, la regla de marca no aplica', str(r)[:300])
    r, d = previa(P2)
    check(int(d.get('regla_id') or 0) != rid, '... ni a los demás productos', str(r)[:300])

caso('Marca en escalas y promociones: limpieza')
if CTX.get('ind') is not None:
    if CTX.get('reglas'):
        sql("UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 2 WHERE regla_venta_id IN (%s)" % ','.join(str(x) for x in CTX['reglas'] if x))
    if CTX.get('activas'):
        sql("UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 1 WHERE regla_venta_id IN (%s)" % ','.join(map(str, CTX['activas'])))
    sql("UPDATE gestion__productos SET marca_comercial_id = %s WHERE producto_id = %d" % (CTX['marca_p1'] or 'NULL', P1))
    sql("UPDATE conf__empresas SET usa_reglas_precio_venta = %s, usa_marcas_comerciales = %s WHERE empresa_id = 2" % (CTX['ind']['r'], CTX['ind']['m']))
    check(True, 'indicadores y reglas de la copia como estaban')
