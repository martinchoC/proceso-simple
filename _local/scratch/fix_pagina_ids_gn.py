"""Corrige pagina_id por defecto (IDs del módulo 2) y fija $modudo_idx = 5 en páginas de gestion_normal."""
import re, pathlib

BASE = pathlib.Path(r"c:\xampp\htdocs\web_procesosimple\casalucho\modules\gestion_normal")

# archivo -> (id_mod2, id_mod5)
DEFAULTS = {
    "comprobantes_relacion_fiscal.php": (110, 610),
    "cont_asientos.php": (84, 584),
    "cont__mayores.php": (85, 585),
    "impuestos_tipos.php": (72, 572),
    "iva_alicuotas.php": (65, 565),
    "listas_precios.php": (53, 553),
    "ventas_remitos_pendientes_facturacion.php": (364, 864),
    "facturas_proveedores.php": (57, 557),
    "ventas_facturas.php": (56, 556),
}

def fix_file(path: pathlib.Path):
    src = path.read_bytes().decode("utf-8")
    orig = src
    name = path.name
    if name in DEFAULTS:
        a, b = DEFAULTS[name]
        src = re.sub(r"(\$pagina_idx?\s*=\s*isset\(\$_GET\['pagina_id'\]\)\s*\?\s*intval\(\$_GET\['pagina_id'\]\)\s*:\s*)%d;" % a,
                     r"\g<1>%d;" % b, src, count=1)
    if name == "productos_costos.php":
        src = src.replace("$pagina_id = 81;", "$pagina_id = 581;", 1)
    if not re.search(r"\$modudo_idx\s*=", src) and "header1.php" in src:
        m = re.search(r"require_once __DIR__ \. '/../../db\.php';\r?\n", src)
        nl = "\r\n" if "\r\n" in src else "\n"
        if m:
            src = src[:m.end()] + "$modudo_idx = 5; // gestion_normal" + nl + src[m.end():]
        else:
            src = src.replace("<?php" + nl, "<?php" + nl + "$modudo_idx = 5; // gestion_normal" + nl, 1)
    if src != orig:
        path.write_bytes(src.encode("utf-8"))
        print("fixed", name)

for p in sorted(BASE.glob("*.php")):
    if re.search(r"_(ajax|model)\.php$", p.name):
        continue
    fix_file(p)
