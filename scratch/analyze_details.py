import json
import os
import glob

with open('scratch/system_info.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

print("=== PAGINAS DE GESTION (MODULO 2) ===")
gestion_pags = [p for p in d['paginas'] if str(p.get('modulo_id')) == '2']
print(f"Total paginas gestion: {len(gestion_pags)}")
for p in gestion_pags:
    padre = p.get('padre_id') or '-'
    pag_name = p.get('pagina', '')
    url = p.get('url', '')
    pag_id = p.get('pagina_id', '')
    print(f"ID: {pag_id:>3} | Padre: {str(padre):>4} | {pag_name:<40} | URL: {url}")

print("\n=== ARCHIVOS EN modules/gestion/ ===")
php_files = glob.glob('modules/gestion/*.php')
print(f"Total PHP files in modules/gestion: {len(php_files)}")

# Check groups of files: page.php, page_ajax.php, page_model.php, page.js
bases = set()
for f in os.listdir('modules/gestion'):
    if f.endswith('.php') or f.endswith('.js'):
        base = f.replace('_ajax.php', '').replace('-ajax.php', '').replace('_model.php', '').replace('.php', '').replace('.js', '')
        bases.add(base)

print(f"Unique screen bases in modules/gestion: {len(bases)}")

print("\n=== COMPROBANTES SUBGRUPOS Y TIPOS ===")
tipos = d['comprobantes_tipos']
print(f"Total comprobantes tipos: {len(tipos)}")
for t in tipos[:25]:
    print(f"ID: {t['comprobante_tipo_id']:>2} | Cod: {t.get('codigo',''):<6} | Nombre: {t.get('comprobante_tipo',''):<30} | Signo: {t.get('signo','')} | Grupo: {t.get('comprobante_grupo_id','')} | Subgrupo: {t.get('comprobante_subgrupo_id','')}")

print("\n=== TABLAS DE GESTION Y SUS CLAVES ===")
gestion_tables = [t for t in d['all_tables'].keys() if t.startswith('gestion__')]
for gt in sorted(gestion_tables):
    cols = [c['name'] for c in d['all_tables'][gt]]
    has_empresa = 'empresa_id' in cols or 'empresa_idx' in cols
    has_estado = 'tabla_estado_registro_id' in cols or 'estado_id' in cols
    has_comp = 'comprobante_id' in cols
    flags = []
    if has_empresa: flags.append('empresa')
    if has_estado: flags.append('estado')
    if has_comp: flags.append('comprobante')
    print(f"  {gt:<42} ({len(cols):2} cols) [{', '.join(flags)}]")
