"""Cierre de Períodos Contables (página 134 / 634): mes de cierre del ejercicio en Parámetros de Empresa (se pasa a
junio) y ejercicio contable que define (rango, ejercicio actual, irregular al cambiar el mes de cierre), cerrar un mes
(con aviso de asientos en borrador), cerrar todo el ejercicio,
rechazos (repetido, futuro, reabrir sin motivo, cierre de otra empresa), y el bloqueo: con el mes cerrado no se agrega,
edita, elimina, confirma ni cambia el detalle de un asiento manual de ese mes, y tesoreriaGenerarAsiento (el de cobranzas,
pagos, liquidación de IVA y transferencias) no genera un asiento con fecha en ese mes. Al reabrir se vuelve a poder. En gestion (empresa 2) y
gestion_normal (empresa 6). Aplica en la copia 20261028_cont_periodos_cierres_01_estructura.sql y
20261011_iva_posiciones_01.sql (idempotentes) y deshabilita ARCA.
"""
import datetime as _dt
from simlib import *

for _s in ('20261011_iva_posiciones_01.sql', '20261028_cont_periodos_cierres_01_estructura.sql',
           '20261030_cont_periodos_cierres_02_mes_cierre_ejercicio.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))
# Solo en la copia: sin ARCA, sin cierres contables ni liquidaciones de IVA previas
sql("UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id IN (2, 6)")
sql("UPDATE gestion__iva_posiciones SET tabla_estado_registro_id = 38 WHERE empresa_id IN (2, 6)")
sql("UPDATE gestion__cont_periodos_cierres SET tabla_estado_registro_id = 38 WHERE empresa_id IN (2, 6)")

_hoy = _dt.date.fromisoformat(HOY)
_a = _hoy.replace(day=1) - _dt.timedelta(days=1)
ANIO_A, MES_A = _a.year, _a.month
PER_A = _a.strftime('%Y-%m')
FECHA_A = _a.replace(day=10).isoformat()
_f = _hoy.replace(day=28) + _dt.timedelta(days=10)
# Ejercicio con cierre en junio: el N va del 01/07/N-1 al 30/06/N
MES_CIERRE = 6
ej_de = lambda d: d.year if d.month <= MES_CIERRE else d.year + 1
EJ_ACT = ej_de(_hoy)
EJ_A = ej_de(_a)
EJ_ANT = EJ_ACT - 1

ENTORNOS = [dict(modulo='gestion', empresa=2, pagina_as=84)]
if val("SELECT COUNT(*) FROM conf__empresas WHERE empresa_id = 6"):
    ENTORNOS.append(dict(modulo='gestion_normal', empresa=6, pagina_as=584))


def cpc(e, params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', e['empresa'])
    return ajax('cont_periodos_cierres_ajax.php', p, method, modulo=e['modulo'], empresa_id=e['empresa'])


def asi(e, params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', e['empresa'])
    return ajax('cont_asientos_ajax.php', p, method, modulo=e['modulo'], empresa_id=e['empresa'])


RAIZ = os.path.abspath(os.path.join(AQUI, '..', '..', '..')).replace(chr(92), '/')


def generar_asiento_tesoreria(modulo, empresa, sucursal, cuenta, fecha):
    """Llama a tesoreriaGenerarAsiento (asiento sin comprobante) sobre la copia; devuelve 'OK <id>' o el error."""
    php = f"""<?php
define('DB_NAME', '{DB}');
set_error_handler(function ($no, $str) {{ return strpos($str, 'already defined') !== false; }});
require_once '{RAIZ}/.env';
require_once '{RAIZ}/db.php';
session_start();
$_SESSION['usuario_id'] = 1;
require_once '{RAIZ}/modules/{modulo}/tesoreria_core_model.php';
try {{
    $id = tesoreriaGenerarAsiento($conn, ['empresa_id' => {int(empresa)}, 'sucursal_id' => {int(sucursal)}, 'sin_comprobante' => true,
        'f_asiento' => '{fecha}', 'descripcion' => 'SIM cierre de períodos', 'usuario_id' => 1],
        [['cuenta_id' => {int(cuenta)}, 'debe' => 10], ['cuenta_id' => {int(cuenta)}, 'haber' => 10]]);
    echo 'OK ' . $id;
}} catch (Throwable $e) {{
    echo 'ERROR ' . $e->getMessage();
}}
"""
    with tempfile.NamedTemporaryFile('w', suffix='.php', delete=False, encoding='utf-8') as f:
        f.write(php)
        ruta = f.name
    try:
        r = subprocess.run([PHP, ruta], capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=120)
    finally:
        os.unlink(ruta)
    return (r.stdout.strip() or r.stderr.strip())


def cerrado_msg(r):
    return 'período contable' in err(r) and 'cerrado' in err(r)


for e in ENTORNOS:
    caso(f"Cierre de períodos contables - {e['modulo']} (empresa {e['empresa']})")
    emp = e['empresa']
    suc = uno(f"SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = {emp} ORDER BY sucursal_id LIMIT 1")
    dep = uno(f"SELECT boca_id AS deposito_id FROM gestion__bocas WHERE empresa_id = {emp} ORDER BY boca_id LIMIT 1")
    mon = val(f"SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {emp} AND es_moneda_base = 1 LIMIT 1")
    cta = val(f"SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE empresa_id = {emp} AND es_imputable = 1 "
              f"AND tabla_estado_registro_id = 1 ORDER BY cont_cuenta_id LIMIT 1")
    if not (suc and dep and mon and cta):
        brecha(f"Empresa {emp} sin sucursal / depósito / moneda base / cuenta imputable", 'no se prueba el bloqueo de asientos')
        continue
    base_asiento = dict(sucursal_id=suc['sucursal_id'], deposito_id=dep['deposito_id'], moneda_id=mon, tipo_cambio=1,
                        descripcion='SIM cierre de períodos')

    # Mes de cierre del ejercicio: junio, desde Parámetros de Empresa (edición de la vigencia abierta)
    pid = val(f"SELECT empresa_parametro_id FROM gestion__empresas_parametros WHERE empresa_id = {emp} AND f_hasta IS NULL "
              f"AND tabla_estado_registro_id = 1 ORDER BY f_desde DESC LIMIT 1")
    par = ajax('empresas_parametros_ajax.php', dict(accion='obtener_parametro', empresa_parametro_id=pid), 'GET', modulo=e['modulo'], empresa_id=emp)
    datos = {k: ('' if v is None else v) for k, v in par.items() if k in (
        'empresa_parametro_id', 'empresa_id', 'condicion_fiscal_id', 'punto_venta_defecto_id', 'lista_precio_defecto_id',
        'condicion_pago_defecto_id', 'ingresos_brutos_nro', 'iibb_regimen_id', 'jurisdiccion_sede_id', 'inicio_actividades',
        'leyenda_fiscal', 'arca_habilitado', 'arca_integracion_empresa_id', 'compra_actualiza_lista_proveedor',
        'compra_actualiza_listas_venta', 'ia_lectura_habilitada', 'ia_lectura_tope_diario', 'f_desde', 'f_hasta', 'observaciones')}
    r = ajax('empresas_parametros_ajax.php', dict(datos, accion='guardar', mes_cierre_ejercicio=13), 'POST', modulo=e['modulo'], empresa_id=emp)
    check(not ok_resp(r) and 'entre 1 y 12' in err(r), 'Parámetros de Empresa: rechaza un mes de cierre inválido', err(r))
    r = ajax('empresas_parametros_ajax.php', dict(datos, accion='guardar', mes_cierre_ejercicio=MES_CIERRE), 'POST', modulo=e['modulo'], empresa_id=emp)
    check(ok_resp(r) and val(f"SELECT mes_cierre_ejercicio FROM gestion__empresas_parametros WHERE empresa_parametro_id = {pid}") == str(MES_CIERRE),
          'Parámetros de Empresa: guarda el mes de cierre del ejercicio (junio)', err(r))

    r = cpc(e, dict(accion='maestros'), 'GET')
    check(ok_resp(r) and r['data']['boton_cerrar'] and r['data']['boton_reabrir'], 'Maestros: botones Cerrar y Reabrir', err(r))
    check(ok_resp(r) and r['data']['mes_cierre'] == MES_CIERRE and r['data']['ejercicio_actual'] == EJ_ACT
          and r['data']['ejercicios'][0]['ejercicio'] == EJ_ACT,
          f'Maestros: cierre en junio, ejercicio actual {EJ_ACT}', json.dumps(r.get('data', {}).get('ejercicios', [])[:2]))
    r = cpc(e, dict(accion='resumen', ejercicio=EJ_ANT), 'GET')
    ej = r['data']['ejercicio'] if ok_resp(r) else {}
    check(ej.get('desde') == f'{EJ_ANT - 1}-07-01' and ej.get('hasta') == f'{EJ_ANT}-06-30' and len(r['data']['meses']) == 12
          and r['data']['meses'][0]['periodo'] == f'{EJ_ANT - 1}-07' and not ej.get('irregular'),
          f'Ejercicio {EJ_ANT}: del 01/07/{EJ_ANT - 1} al 30/06/{EJ_ANT}, 12 meses', json.dumps(ej))

    # Asiento manual en borrador en el mes A (antes de cerrar)
    r = asi(e, dict(base_asiento, accion='agregar_asiento', fecha=FECHA_A))
    check(ok_resp(r), 'Asiento manual en el mes a cerrar (borrador)', err(r))
    asiento_a = int(r.get('cont_asiento_id') or 0) if ok_resp(r) else 0

    # Cerrar con borradores: pide confirmación y no cierra
    r = cpc(e, dict(accion='cerrar', anio=ANIO_A, mes=MES_A, observaciones='SIM'))
    check(ok_resp(r) and r['data'].get('requiere_confirmacion') and r['data']['borradores'],
          'Cerrar con asientos en borrador pide confirmación', json.dumps(r)[:300])
    check(int(val(f"SELECT COUNT(*) FROM gestion__cont_periodos_cierres WHERE empresa_id = {emp} AND anio = {ANIO_A} "
                  f"AND mes = {MES_A} AND tabla_estado_registro_id = 5")) == 0, 'Sin confirmar no se cerró')
    r = cpc(e, dict(accion='cerrar', anio=ANIO_A, mes=MES_A, observaciones='SIM', forzar=1))
    check(ok_resp(r) and r['data']['cerrados'] == [dict(anio=ANIO_A, mes=MES_A)], f'Cierra {PER_A} confirmando', err(r))

    r = cpc(e, dict(accion='cerrar', anio=ANIO_A, mes=MES_A, forzar=1))
    check(not ok_resp(r) and 'ya está cerrado' in err(r), 'Rechaza cerrar dos veces el mismo mes', err(r))
    r = cpc(e, dict(accion='cerrar', anio=_f.year, mes=_f.month, forzar=1))
    check(not ok_resp(r) and 'futuro' in err(r), 'Rechaza cerrar un mes futuro', err(r))

    r = cpc(e, dict(accion='resumen', ejercicio=EJ_A), 'GET')
    mes = next((m for m in r['data']['meses'] if m['periodo'] == PER_A), None) if ok_resp(r) else None
    check(mes and mes['cerrado'] and mes['asientos'] >= 1, 'Resumen del ejercicio: el mes figura cerrado con sus asientos', json.dumps(mes))
    r = cpc(e, dict(accion='listar', ejercicio=EJ_A), 'GET')
    cierre = next((c for c in (r.get('data') or []) if int(c['mes']) == MES_A), None) if ok_resp(r) else None
    check(cierre is not None, 'Listado: el cierre aparece', err(r))
    cierre_id = int(cierre['cierre_id']) if cierre else 0

    # Bloqueos sobre asientos manuales
    r = asi(e, dict(base_asiento, accion='agregar_asiento', fecha=FECHA_A))
    check(not ok_resp(r) and cerrado_msg(r) and f'(ejercicio {EJ_A})' in err(r),
          'Bloquea agregar un asiento con fecha en el mes cerrado (el mensaje dice el ejercicio)', err(r))
    if asiento_a:
        r = asi(e, dict(base_asiento, accion='editar_asiento', id=asiento_a, fecha=HOY))
        check(not ok_resp(r) and cerrado_msg(r), 'Bloquea editar (y sacar del mes) un asiento del mes cerrado', err(r))
        r = asi(e, dict(accion='agregar_detalle', cont_asiento_id=asiento_a, cuenta_id=cta, importe_local_debe=100, importe_local_haber=0))
        check(not ok_resp(r) and cerrado_msg(r), 'Bloquea agregar una línea al asiento', err(r))
        r = asi(e, dict(accion='eliminar_asiento', id=asiento_a))
        check(not ok_resp(r) and cerrado_msg(r), 'Bloquea eliminar el asiento', err(r))
        r = asi(e, dict(accion='ejecutar_accion', id=asiento_a, accion_js='confirmar', pagina_id=e['pagina_as']))
        check(not ok_resp(r) and cerrado_msg(r), 'Bloquea confirmar el asiento', err(r))
    # Un asiento de otro mes no se toca, salvo que se lo quiera llevar al mes cerrado
    r = asi(e, dict(base_asiento, accion='agregar_asiento', fecha=HOY))
    check(ok_resp(r), 'Asiento en un mes abierto: se agrega', err(r))
    asiento_hoy = int(r.get('cont_asiento_id') or 0) if ok_resp(r) else 0
    if asiento_hoy:
        r = asi(e, dict(base_asiento, accion='editar_asiento', id=asiento_hoy, fecha=FECHA_A))
        check(not ok_resp(r) and cerrado_msg(r), 'Bloquea mover un asiento al mes cerrado', err(r))
        r = asi(e, dict(accion='eliminar_asiento', id=asiento_hoy))
        check(ok_resp(r), 'Asiento de un mes abierto: se elimina', err(r))

    # Asiento automático: tesoreriaGenerarAsiento (cobranzas, pagos, liquidación de IVA, transferencias) con fecha
    # en el mes cerrado no escribe nada; en un mes abierto sí
    antes = int(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE empresa_id = {emp}"))
    salida = generar_asiento_tesoreria(e['modulo'], emp, suc['sucursal_id'], cta, FECHA_A)
    check('período contable' in salida and 'cerrado' in salida, 'tesoreriaGenerarAsiento: bloquea un asiento del mes cerrado', salida[:300])
    check(int(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE empresa_id = {emp}")) == antes, 'y no escribe el asiento')
    salida = generar_asiento_tesoreria(e['modulo'], emp, suc['sucursal_id'], cta, HOY)
    check(salida.startswith('OK'), 'tesoreriaGenerarAsiento: en un mes abierto genera el asiento', salida[:300])

    # Reabrir
    r = cpc(e, dict(accion='reabrir', cierre_id=cierre_id, motivo=''))
    check(not ok_resp(r) and 'motivo' in err(r), 'Reabrir exige motivo', err(r))
    otra = next((x for x in ENTORNOS if x['empresa'] != emp), None)
    if otra:
        r = cpc(otra, dict(accion='reabrir', cierre_id=cierre_id, motivo='SIM otra empresa'))
        check(not ok_resp(r) and 'no encontrado' in err(r), 'No reabre el cierre de otra empresa', err(r))
    r = cpc(e, dict(accion='reabrir', cierre_id=cierre_id, motivo='SIM ajuste'))
    check(ok_resp(r), f'Reabre {PER_A}', err(r))
    fila = uno(f"SELECT tabla_estado_registro_id, motivo_reapertura, usuario_reapertura_id FROM gestion__cont_periodos_cierres WHERE cierre_id = {cierre_id}")
    check(fila and fila['tabla_estado_registro_id'] == '38' and fila['motivo_reapertura'] == 'SIM ajuste' and fila['usuario_reapertura_id'] == '1',
          'El cierre queda Reabierto con motivo y usuario (historial)', json.dumps(fila))
    r = cpc(e, dict(accion='reabrir', cierre_id=cierre_id, motivo='otra vez'))
    check(not ok_resp(r) and 'reabierto' in err(r), 'No reabre dos veces', err(r))
    if asiento_a:
        r = asi(e, dict(accion='eliminar_asiento', id=asiento_a))
        check(ok_resp(r), 'Reabierto: el asiento del mes se puede eliminar', err(r))

    # Cerrar el ejercicio anterior completo (julio a junio): cierra sus 12 meses y no repite los cerrados
    sql(f"INSERT INTO gestion__cont_periodos_cierres (empresa_id, anio, mes, usuario_id) VALUES ({emp}, {EJ_ANT - 1}, 9, 1)")
    r = cpc(e, dict(accion='cerrar', ejercicio=EJ_ANT, mes=0, forzar=1))
    check(ok_resp(r) and len(r['data']['cerrados']) == 11 and r['data']['ya_cerrados'] == [dict(anio=EJ_ANT - 1, mes=9)]
          and r['data']['cerrados'][0] == dict(anio=EJ_ANT - 1, mes=7) and r['data']['cerrados'][-1] == dict(anio=EJ_ANT, mes=6),
          f'Cierra el ejercicio {EJ_ANT} completo, de julio a junio (11 meses nuevos, 1 ya cerrado)', json.dumps(r)[:300])
    n = int(val(f"SELECT COUNT(*) FROM gestion__cont_periodos_cierres WHERE empresa_id = {emp} AND anio * 100 + mes "
                f"BETWEEN {EJ_ANT - 1}07 AND {EJ_ANT}06 AND tabla_estado_registro_id = 5"))
    check(n == 12, 'Un cierre activo por mes del ejercicio', str(n))
    check(int(val(f"SELECT COUNT(*) FROM gestion__cont_periodos_cierres WHERE empresa_id = {emp} AND anio * 100 + mes "
                  f"IN ({EJ_ANT - 1}06, {EJ_ANT}07) AND tabla_estado_registro_id = 5")) == 0, 'No cierra meses de otros ejercicios')
    r = cpc(e, dict(accion='cerrar', ejercicio=EJ_ANT, mes=0, forzar=1))
    check(not ok_resp(r) and 'No quedan meses' in err(r), 'Ejercicio ya cerrado: no hay nada que cerrar', err(r))
    r = cpc(e, dict(accion='listar', ejercicio=EJ_ANT), 'GET')
    check(ok_resp(r) and len(r['data']) == 12 and all(c['ejercicio'] == EJ_ANT for c in r['data']),
          'Listado del ejercicio: sus 12 cierres', str(len(r.get('data') or [])))

    # Cambio del mes de cierre (nueva vigencia desde enero del año que viene, cierre en diciembre): el ejercicio
    # en curso se alarga hasta diciembre y queda irregular
    sql(f"INSERT INTO gestion__empresas_parametros (empresa_id, condicion_fiscal_id, f_desde, mes_cierre_ejercicio, observaciones, tabla_estado_registro_id) "
        f"SELECT empresa_id, condicion_fiscal_id, '{EJ_ACT}-01-01', 12, 'SIM cambio de cierre', 1 FROM gestion__empresas_parametros WHERE empresa_parametro_id = {pid}")
    r = cpc(e, dict(accion='resumen', ejercicio=EJ_ACT), 'GET')
    ej = r['data']['ejercicio'] if ok_resp(r) else {}
    check(ej.get('desde') == f'{EJ_ACT - 1}-07-01' and ej.get('hasta') == f'{EJ_ACT}-12-31' and ej.get('irregular')
          and len(r['data']['meses']) == 18,
          f'Cambio a cierre en diciembre: ejercicio {EJ_ACT} irregular del 01/07/{EJ_ACT - 1} al 31/12/{EJ_ACT} (18 meses)', json.dumps(ej))
    sql(f"DELETE FROM gestion__empresas_parametros WHERE empresa_id = {emp} AND observaciones = 'SIM cambio de cierre'")

    # Seguridad: sin la función (usuario sin perfil en la empresa)
    r = ajax('cont_periodos_cierres_ajax.php', dict(accion='cerrar', anio=ANIO_A, mes=MES_A, empresa_idx=emp), 'POST',
             usuario_id=999999, modulo=e['modulo'], empresa_id=emp)
    check(not ok_resp(r), 'Usuario sin acceso a la empresa: rechazado', err(r))
