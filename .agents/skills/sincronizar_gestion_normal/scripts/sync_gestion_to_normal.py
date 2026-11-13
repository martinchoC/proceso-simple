#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de sincronización de modules/gestion a modules/gestion_normal.
Reglas:
1. gestion_normal NO utiliza compatibilidad de productos (marcas, modelos, submodelos, productos_generales).
2. productos.php, productos_model.php, productos_ajax.php, productos.js y listas_precios_productos*
   en gestion_normal mantienen su arquitectura propia sin compatibilidad (no se sobreescriben ciegamente).
3. Transforma identificadores de módulo:
   - modulo_id = 2 -> modulo_id = 5
   - $modudo_idx = 2 -> $modudo_idx = 5
   - pagina_idx / pagina_id se mapean al offset de Módulo 5 (+500 según conf__paginas)
   - Rutas 'modules/gestion/' -> 'modules/gestion_normal/'
   - Breadcrumb 'Gestión' -> 'Gestión Normal'
"""

import os
import re
import sys
import difflib
import argparse

WORKSPACE_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..', '..', '..'))
GESTION_DIR = os.path.join(WORKSPACE_ROOT, 'modules', 'gestion')
GESTION_NORMAL_DIR = os.path.join(WORKSPACE_ROOT, 'modules', 'gestion_normal')

# Archivos exclusivos de compatibilidad que NO deben existir en gestion_normal
COMPATIBILIDAD_FILES = {
    'marcas.php', 'marcas_ajax.php', 'marcas_model.php',
    'modelos.php', 'modelos_ajax.php', 'modelos_model.php',
    'submodelos.php', 'submodelos_ajax.php', 'submodelos_model.php',
    'productos_generales.js', 'productos_generales.php', 'productos_generales_ajax.php', 'productos_generales_model.php'
}

# Archivos de productos con implementación desacoplada sin compatibilidad en gestion_normal
PRODUCTOS_SPECIAL_FILES = {
    'productos.php', 'productos_model.php', 'productos_ajax.php', 'productos.js',
    # Lista de precios sin marca/modelo/submodelo y con filtro producto / servicio (2026-10-25)
    'listas_precios_productos.php', 'listas_precios_productos_model.php', 'listas_precios_productos_ajax.php'
}

# Mapeo conocido de IDs de páginas (conf__paginas Módulo 2 -> Módulo 5)
PAGINAS_OFFSET = 500

def transform_content(content: str, rel_path: str) -> str:
    """Aplica las transformaciones de gestion (modulo 2) a gestion_normal (modulo 5)."""
    text = content

    # 1. Modulo IDs
    text = re.sub(r'(\$modudo_idx\s*=\s*)2(\s*;)', r'\g<1>5\2', text)
    text = re.sub(r'(\$modulo_idx\s*=\s*)2(\s*;)', r'\g<1>5\2', text)
    text = re.sub(r'(\$modulo_id\s*=\s*isset\([^)]+\)\s*\?[^:]+:\s*)2(\s*;)', r'\g<1>5\2', text)
    text = re.sub(r'(\$modulo_id\s*=\s*)2(\s*;)', r'\g<1>5\2', text)
    text = re.sub(r'(\$modulo_id\s*=\s*)2(\s*[,)])', r'\g<1>5\2', text)
    text = re.sub(r"('modulo_id'\s*=>\s*)2", r"\g<1>5", text)
    text = re.sub(r'("modulo_id"\s*=>\s*)2', r'\g<1>5', text)
    text = re.sub(r'(ep\.modulo_id\s*=\s*)2\b', r'\g<1>5', text)
    # SQL (p.modulo_id=2, modulo_id = 2), links JS (&modulo_id=2) y pagina_id_por_url($conexion, 2, ...)
    text = re.sub(r'(\bmodulo_id\s*=\s*)2\b', r'\g<1>5', text)
    text = re.sub(r'(\bpagina_id_por_url\(\s*\$\w+\s*,\s*)2(\s*,)', r'\g<1>5\2', text)
    # permisos_pagina_modulo($conn, 2, $pagina, 'url.php') (core/permisos.php, 2026-11-05)
    text = re.sub(r'(\bpermisos_pagina_modulo\(\s*\$\w+\s*,\s*)2(\s*,)', r'\g<1>5\2', text)
    # Constantes de módulo (const COB_MODULO_ID = 2;)
    text = re.sub(r'(\bconst\s+\w*MODULO_ID\s*=\s*)2(\s*;)', r'\g<1>5\2', text)

    # 2. Rutas a módulos
    text = text.replace('modules/gestion/', 'modules/gestion_normal/')
    text = text.replace('modules\\gestion\\', 'modules\\gestion_normal\\')

    # 3. Breadcrumbs y títulos
    text = re.sub(
        r'(<li class="breadcrumb-item"><a href="#">)Gestión(</a></li>)',
        r'\g<1>Gestión Normal\2',
        text
    )
    text = re.sub(
        r'(<small class="text-muted">)Sistema Declarativo Multiempresa(</small>)',
        r'\g<1>Gestión Normal Multiempresa\2',
        text
    )

    # 4. Mapeo de pagina_idx fijos en vistas PHP:
    def map_pagina_idx(m):
        prefix = m.group(1)
        val = int(m.group(2))
        suffix = m.group(3) if len(m.groups()) >= 3 else ''
        # 0 = "sin página" (p. ej. el default de pagina_id_por_url): no se desplaza
        if 0 < val < 500:
            val += PAGINAS_OFFSET
        return f"{prefix}{val}{suffix}"

    text = re.sub(r'(\$pagina_idx\s*=\s*)(\d+)(\s*;)', map_pagina_idx, text)
    text = re.sub(r"('pagina_idx'\s*=>\s*)(\d+)", map_pagina_idx, text)
    text = re.sub(r'("pagina_idx"\s*=>\s*)(\d+)', map_pagina_idx, text)
    text = re.sub(r"(\bpagina_id_por_url\([^,]+,\s*[^,]+,\s*[^,]+,\s*)(\d+)(\))", map_pagina_idx, text)

    # 5. Comentarios que mencionen Módulo 2 -> Módulo 5
    text = re.sub(r'\bMódulo 2: Gestión de Empresas\b', 'Módulo 5: Gestión Normal', text)
    text = re.sub(r'\bmódulo 2\b', 'módulo 5', text)

    return text

def should_skip(rel_path: str) -> bool:
    rel_lower = rel_path.lower()
    if rel_lower in COMPATIBILIDAD_FILES:
        return True
    if any(p in rel_lower for p in ['copy', '.log', '.txt', 'exports', 'imagenes', 'manual']):
        return True
    return False

def compare_and_sync(apply_changes: bool = False, target_file: str = None):
    print("=" * 80)
    print(f"SINCRONIZADOR GESTIÓN -> GESTIÓN NORMAL ({'APLICANDO' if apply_changes else 'MODO DRY-RUN'})")
    print(f"Origen:  {GESTION_DIR}")
    print(f"Destino: {GESTION_NORMAL_DIR}")
    print("=" * 80)

    stats = {
        'identical': 0,
        'updated': 0,
        'created': 0,
        'skipped_compat': 0,
        'skipped_special': 0,
        'errors': 0
    }

    files_to_process = []
    if target_file:
        files_to_process.append(target_file)
    else:
        for root, dirs, files in os.walk(GESTION_DIR):
            dirs[:] = [d for d in dirs if d not in ('exports', 'imagenes', 'manual', 'views')]
            for f in files:
                rel = os.path.relpath(os.path.join(root, f), GESTION_DIR)
                files_to_process.append(rel)

    for rel in sorted(files_to_process):
        if should_skip(rel):
            stats['skipped_compat'] += 1
            continue

        if rel in PRODUCTOS_SPECIAL_FILES:
            stats['skipped_special'] += 1
            continue

        src_path = os.path.join(GESTION_DIR, rel)
        dst_path = os.path.join(GESTION_NORMAL_DIR, rel)

        if not os.path.exists(src_path):
            print(f"[!] Archivo fuente no existe: {rel}")
            stats['errors'] += 1
            continue

        try:
            with open(src_path, 'r', encoding='utf-8', errors='ignore') as f:
                src_content = f.read()
        except Exception as e:
            print(f"[ERROR] Leyendo fuente {rel}: {e}")
            stats['errors'] += 1
            continue

        transformed = transform_content(src_content, rel)

        dst_exists = os.path.exists(dst_path)
        dst_content = None
        if dst_exists:
            try:
                with open(dst_path, 'r', encoding='utf-8', errors='ignore') as f:
                    dst_content = f.read()
            except Exception as e:
                print(f"[ERROR] Leyendo destino {rel}: {e}")

        # Normalizar saltos de línea para comparar
        t_norm = transformed.replace('\r\n', '\n')
        d_norm = dst_content.replace('\r\n', '\n') if dst_content is not None else None

        if d_norm == t_norm:
            stats['identical'] += 1
            continue

        action = 'CREAR' if not dst_exists else 'MODIFICAR'
        diff_lines = 0
        if dst_content is not None:
            diff = list(difflib.unified_diff(d_norm.splitlines(), t_norm.splitlines()))
            diff_lines = len(diff)

        print(f"[{action}] {rel:45} ({diff_lines} líneas de diff)")

        if apply_changes:
            os.makedirs(os.path.dirname(dst_path), exist_ok=True)
            with open(dst_path, 'w', encoding='utf-8', newline='\n') as f:
                f.write(transformed)
            if not dst_exists:
                stats['created'] += 1
            else:
                stats['updated'] += 1
        else:
            if not dst_exists:
                stats['created'] += 1
            else:
                stats['updated'] += 1

    print("\n" + "=" * 80)
    print("RESUMEN DE EJECUCIÓN:")
    print(f"  Archivos idénticos:                    {stats['identical']}")
    print(f"  Archivos a actualizar / actualizados:   {stats['updated']}")
    print(f"  Archivos nuevos a crear / creados:      {stats['created']}")
    print(f"  Archivos omitidos por compatibilidad:  {stats['skipped_compat']}")
    print(f"  Archivos especiales (productos):       {stats['skipped_special']}")
    print(f"  Errores:                               {stats['errors']}")
    print("=" * 80)

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="Sincronizador gestion -> gestion_normal")
    parser.add_argument('--apply', action='store_true', help="Aplica los cambios en los archivos destino")
    parser.add_argument('--file', type=str, default=None, help="Sincroniza solo un archivo relativo")
    args = parser.parse_args()

    compare_and_sync(apply_changes=args.apply, target_file=args.file)
