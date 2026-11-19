import pathlib, re
G = pathlib.Path(r"c:\xampp\htdocs\web_procesosimple\casalucho\modules\gestion\ventas_remitos_model.php")
N = pathlib.Path(r"c:\xampp\htdocs\web_procesosimple\casalucho\modules\gestion_normal\ventas_remitos_model.php")
g = G.read_bytes().decode("utf-8")
n = N.read_bytes().decode("utf-8")

def block(src, start_marker, end_marker):
    i = src.index(start_marker)
    j = src.index(end_marker, i)
    return src[i:j]

# 1) auditoría de alta -> normal
alta_g = block(g, "        if (function_exists('auditoria_registrar_proceso_multitabla')) {\r\n            $destinatarioNombre", "        return ['resultado' => true, 'venta_remito_id' => $venta_remito_id, 'comprobante_id' => $comprobante_id];")
anchor_alta = "        mysqli_commit($conexion);\r\n        return ['resultado' => true, 'venta_remito_id' => $venta_remito_id, 'comprobante_id' => $comprobante_id];"
assert n.count(anchor_alta) == 1, n.count(anchor_alta)
n = n.replace(anchor_alta, "        mysqli_commit($conexion);\r\n\r\n" + alta_g + "        return ['resultado' => true, 'venta_remito_id' => $venta_remito_id, 'comprobante_id' => $comprobante_id];")

# 2) auditoría de transición -> normal
trans_g = block(g, "        if (function_exists('auditoria_registrar_proceso_multitabla')) {\r\n            $accionAudit", "        $mensaje = 'Estado actualizado correctamente';")
anchor_tr = "        mysqli_commit($conexion);\r\n\r\n        $mensaje = 'Estado actualizado correctamente';"
assert n.count(anchor_tr) == 1, n.count(anchor_tr)
n = n.replace(anchor_tr, "        mysqli_commit($conexion);\r\n\r\n" + trans_g + "        $mensaje = 'Estado actualizado correctamente';")

# 3) require auditoria
if "core/auditoria.php" not in n:
    n = n.replace("require_once __DIR__ . '/../../core/vendedores.php';\r\n", "require_once __DIR__ . '/../../core/vendedores.php';\r\nrequire_once __DIR__ . '/../../core/auditoria.php';\r\n", 1)

# 4) chequeo factura al anular -> gestion
chk = block(n, "        // Anular un remito confirmado: solo si ninguna factura", "        if ($libera_pedido) {")
anchor_g = "        $libera_pedido = in_array($codigo_destino, ['CANCELADO', 'ANULADO', 'ELIMINADO'], true);\r\n        if ($libera_pedido) {"
if "Anular un remito confirmado" not in g:
    assert g.count(anchor_g) == 1, g.count(anchor_g)
    g = g.replace(anchor_g, "        $libera_pedido = in_array($codigo_destino, ['CANCELADO', 'ANULADO', 'ELIMINADO'], true);\r\n" + chk + "        if ($libera_pedido) {")

G.write_bytes(g.encode("utf-8"))
N.write_bytes(n.encode("utf-8"))
print("ok")
