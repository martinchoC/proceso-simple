"""Prueba global de Gestión Normal — Nivel 1 (OP01–OP04): circuito base y una retención por recibo.

Requiere el nivel 0 (salidas/ctx_global_normal.json). Diseño: _local/docs/pruebas/prueba_global_gestion_normal.md (§5, N1).
Factura A de 100.000 + IVA 21 % (121.000) al cliente agente de retención, cobrada con:
OP01 efectivo; OP02 transferencia + retención de IVA 2.100; OP03 efectivo + Ganancias 2.000; OP04 efectivo + IIBB local 3.000.
"""
import random

from globlib import *

ctx, EMP = empresas_ctx()
SUF = f'{HOY.replace("-", "")}{random.randint(100, 999)}'
NETO, TOTAL = 100000.0, 121000.0

for k, e in EMP.items():
    cli = e['clientes']['AGENTE']
    prod = e['productos']['SRV-ABONO' if k == 'S' else 'BIE-001']
    baja_stock = k == 'B' and val(f"SELECT impacta_stock FROM gestion__comprobantes_tipos WHERE empresa_id = {e['empresa_id']} AND codigo = 'V_FA'") == '1'
    jur_local = jur(e['provincia_local'])
    ops = ctx.setdefault('ops', {}).setdefault(k, {})

    def una(op, dia, medios_dinero, rets):
        """Factura + recibo que la cancela: medios_dinero = (efectivo, banco); rets = [(codigo, importe, cert, jur)]."""
        caso(f'N1 {op} [{k}]')
        s0 = stock(prod, e['boca_id'], e['empresa_id'])
        f = venta(e, cli, [(prod, 1, NETO)], fecha_op(dia))
        check(abs(f['total'] - TOTAL) < 0.01, f'[{k}] {op}: total de la factura {TOTAL}', f"{f['total']} {err(f['r'])}")
        if not f.get('comp_id'):
            return
        tes0 = saldos_tes(e)
        efe, ban = medios_dinero
        medios = ([m_efectivo(e, efe)] if efe else []) + ([m_transfer(e, ban)] if ban else []) + \
                 [m_ret(e, c, i, cert, jur=j) for c, i, cert, j in rets]
        r = recibo(e, cli, medios, [(f['comp_id'], TOTAL)], fecha_op(dia))
        verificar_operacion(e, op, factura=f, rec=r, efectivo=efe, banco=ban, retenciones=rets, saldo_esperado=0, tes0=tes0,
                            stock_esperado={prod: s0 - 1} if baja_stock else ({prod: s0} if k == 'B' else None))
        ops[op] = dict(factura=f['fid'], factura_comp=f['comp_id'], cobranza=r.get('cob'), recibo_comp=r.get('comp_id'),
                       certs=[cert for _, _, cert, _ in rets])

    una('OP01', 2, (TOTAL, 0), [])
    una('OP02', 3, (0, TOTAL - 2100), [('RET_IVA', 2100.0, f'S{k}-IVA-{SUF}', None)])
    una('OP03', 4, (TOTAL - 2000, 0), [('RET_GAN', 2000.0, f'S{k}-GAN-{SUF}', None)])
    una('OP04', 5, (TOTAL - 3000, 0), [('RET_IIBB', 3000.0, f'S{k}-IIBB-{SUF}', jur_local)])

    caso(f'N1 cierre del nivel [{k}]')
    check(ctacte_cliente(e, cli) == 0, f'[{k}] cuenta corriente del cliente en 0 después de OP01–OP04', f'{ctacte_cliente(e, cli)}')

ctx_guardar(ctx)
