import pathlib
B = pathlib.Path(r"c:\xampp\htdocs\web_procesosimple\casalucho\modules\gestion_normal")
R = {
 "bocas.js": [("const pagina_idx = 71;", "const pagina_idx = 571;")],
 "depositos.js": [("const pagina_idx = 71;", "const pagina_idx = 571;")],
 "cobranzas.js": [("PAGINA_ID : 105;", "PAGINA_ID : 605;")],
 "cont_tipos_asientos.js": [("pagina_id : 87;", "pagina_id : 587;")],
 "empresas_cuentas_conceptos.js": [("const pagina_idx = 101;", "const pagina_idx = 601;")],
 "medios_pago.js": [("window.PAGINA_ID : 120;", "window.PAGINA_ID : 620;")],
 "ordenes_compra.js": [("const pagina_idx = 51;", "const pagina_idx = 551;")],
 "plan_cuentas.js": [("pagina_idx : 68;", "pagina_idx : 568;")],
 "productos_costos.js": [("const pagina_id = 81;", "const pagina_id = 581;")],
 "puntos_venta.js": [("const pagina_idx = 70;", "const pagina_idx = 570;")],
 "stock_ajustes.js": [("const pagina_idx = 77;", "const pagina_idx = 577;")],
 "ventas_pedidos.js": [("var REMITOS_PAGINA_IDX = 88;", "var REMITOS_PAGINA_IDX = 588;")],
 "ventas_pedidos_gestion.js": [("var REMITOS_PAGINA_IDX = 88;", "var REMITOS_PAGINA_IDX = 588;")],
 "ventas_remitos.js": [("pagina_idx: 56", "pagina_idx: 556")],
 "ventas_remitos_pendientes_facturacion.js": [("pagina_idx: 56", "pagina_idx: 556")],
}
for f, reps in R.items():
    p = B / f
    s = p.read_bytes().decode("utf-8")
    for a, b in reps:
        n = s.count(a)
        s = s.replace(a, b)
        print(f, a, "->", b, n)
    p.write_bytes(s.encode("utf-8"))
