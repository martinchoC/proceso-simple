import json

with open('scratch/system_info.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

pags = [p for p in d['paginas'] if str(p.get('modulo_id')) == '2']
pags_by_id = {p['pagina_id']: p for p in pags}

# Find top-level menus (padre_id is None, 0, or not in pags)
top_level = [p for p in pags if not p.get('padre_id') or p.get('padre_id') == '0' or p.get('padre_id') not in pags_by_id]
children_map = {}
for p in pags:
    pid = p.get('padre_id')
    if pid:
        children_map.setdefault(pid, []).append(p)

print(f"=== ARBOL DE PAGINAS DE GESTION (Total: {len(pags)}) ===")
def print_tree(p, depth=0):
    indent = "  " * depth
    pag_id = p.get('pagina_id')
    name = p.get('pagina', '')
    url = p.get('url', '')
    print(f"{indent}[{pag_id:>3}] {name:<40} (url: {url})")
    for child in children_map.get(pag_id, []):
        print_tree(child, depth + 1)

for top in top_level:
    print_tree(top)
