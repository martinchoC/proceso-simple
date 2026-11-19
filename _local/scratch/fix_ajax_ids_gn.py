import pathlib, re
B = pathlib.Path(r"c:\xampp\htdocs\web_procesosimple\casalucho\modules\gestion_normal")
R = {
 "bocas_ajax.php": [("$pagina_id = intval($_POST['pagina_idx'] ?? 0);", "$pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()")],
 "depositos_ajax.php": [("$pagina_id = intval($_POST['pagina_idx'] ?? 0);", "$pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()")],
 "puntos_venta_ajax.php": [("$pagina_id = intval($_POST['pagina_idx'] ?? 0);", "$pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()")],
 "stock_ajustes_ajax.php": [("$pagina_id = intval($_POST['pagina_idx'] ?? 0);", "$pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()")],
 "ventas_pedidos_ajax.php": [
    ("$pagina_id = intval($_GET['pagina_idx'] ?? 0);", "$pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()"),
    ("$pagina_id = intval($_POST['pagina_idx'] ?? 0);", "$pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()"),
    ("$pagina_idx_remitos = intval($_GET['pagina_idx_remitos'] ?? 88);",
     "$pagina_idx_remitos = permisos_pagina_modulo($conexion, 5, intval($_GET['pagina_idx_remitos'] ?? 0), 'ventas_remitos.php');"),
    ("?? $_POST['pagina_idx'] ?? 65);", "?? $_POST['pagina_idx'] ?? 0);"),
 ],
 "ventas_remitos_ajax.php": [
    ("$pagina_id = intval($_GET['pagina_idx'] ?? 0);", "$pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()"),
    ("$pagina_id = intval($_POST['pagina_idx'] ?? 0);", "$pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()"),
 ],
 "productos_costos_ajax.php": [("$pagina_id = intval($_POST['pagina_id'] ?? 0);", "$pagina_id = (int)$pagina_id; // validada por permisos_pagina_modulo()")],
 "listas_precios_ajax.php": [("$pagina_id = intval($_POST['pagina_id'] ?? 0);", "$pagina_id = (int)$pagina_id; // validada por permisos_pagina_modulo()")],
 "cont_asientos_ajax.php": [
    ("$pagina_id = intval($_POST['pagina_id'] ?? $pagina_id);", "$pagina_id = (int)$pagina_id; // validada por permisos_pagina_modulo()"),
    ("$pagina_id = intval($_GET['pagina_id'] ?? $_POST['pagina_id'] ?? 84);", "$pagina_id = (int)$pagina_id; // validada por permisos_pagina_modulo()"),
 ],
 "comprobantes_grupos_ajax.php": [("$pagina_subgrupos_idx = 66;", "$pagina_subgrupos_idx = 566;")],
}
for f, reps in R.items():
    p = B / f
    s = p.read_bytes().decode("utf-8")
    for a, b in reps:
        n = s.count(a)
        s = s.replace(a, b)
        print(f, n, a[:60])
    p.write_bytes(s.encode("utf-8"))
