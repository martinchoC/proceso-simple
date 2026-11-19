"""Auditoría estática profunda de modules/gestion_normal."""
import pathlib, re

BASE = pathlib.Path(r"c:\xampp\htdocs\web_procesosimple\casalucho\modules\gestion_normal")

print("=== 1. MODALES modal-dialog-scrollable CON <form> SIN FLEX/OVERFLOW ===")
modal_issues = []
for p in sorted(BASE.glob("*.php")):
    txt = p.read_text(encoding="utf-8", errors="replace")
    if "modal-dialog-scrollable" not in txt:
        continue
    for m in re.finditer(r"modal-dialog-scrollable", txt):
        chunk = txt[m.start():m.start() + 1400]
        mc = chunk.find("modal-content")
        mb = chunk.find("modal-body")
        if mc < 0 or mb < 0 or mb < mc:
            continue
        between = chunk[mc:mb]
        mf = re.search(r"<form\b[^>]*>", between)
        if mf:
            tag = mf.group(0)
            if "d-flex" not in tag or "overflow-hidden" not in tag:
                ln = txt[:m.start() + mc + mf.start()].count("\n") + 1
                modal_issues.append((p.name, ln, tag))
                print(f"  {p.name}:{ln}: {tag}")
print(f"Total modales con <form> sin flex-column+overflow-hidden: {len(modal_issues)}")

print("\n=== 2. ACCIONES AJAX LLAMADAS DESDE .JS QUE NO EXISTEN EN SU _AJAX.PHP ===")
missing_actions = []
for js in sorted(BASE.glob("*.js")):
    ajax_php = BASE / (js.stem + "_ajax.php")
    if not ajax_php.exists():
        continue
    php_txt = ajax_php.read_text(encoding="utf-8", errors="replace")
    # Si delega a core/, leer también el archivo de core
    m_core = re.search(r"require_once\s+__DIR__\s*\.\s*'/../../(core/[^']+\.php)'", php_txt)
    if m_core:
        cp = BASE.parent.parent / m_core.group(1)
        if cp.exists():
            php_txt += "\n" + cp.read_text(encoding="utf-8", errors="replace")
    cases = set(re.findall(r"case\s+['\"]([a-zA-Z0-9_]+)['\"]\s*:", php_txt))
    if not cases:
        continue
    js_txt = js.read_text(encoding="utf-8", errors="replace")
    # Buscar llamadas al propio ajax: accion=xxx o accion: 'xxx'
    own_name = ajax_php.name
    # Buscar patrones accion: 'foo' solo si el archivo solo llama a su propio ajax o en contexto cercano
    called = set()
    for m in re.finditer(re.escape(own_name) + r"[^;]{0,300}", js_txt):
        seg = m.group(0)
        for a in re.findall(r"accion\s*[:=]\s*['\"]([a-zA-Z0-9_]+)['\"]", seg):
            called.add(a)
        for a in re.findall(r"accion=([a-zA-Z0-9_]+)", seg):
            called.add(a)
    diff = sorted(called - cases)
    if diff:
        missing_actions.append((js.name, own_name, diff))
        print(f"  {js.name} -> {own_name}: {diff}")
print(f"Total archivos con posibles acciones faltantes: {len(missing_actions)}")

print("\n=== 3. REFERENCIAS A /modules/gestion/ EN gestion_normal ===")
for ext in ("*.php", "*.js"):
    for p in sorted(BASE.glob(ext)):
        txt = p.read_text(encoding="utf-8", errors="replace")
        for m in re.finditer(r"modules/gestion/", txt):
            ln = txt[:m.start()].count("\n") + 1
            line = txt.splitlines()[ln - 1].strip()
            print(f"  {p.name}:{ln}: {line[:120]}")
