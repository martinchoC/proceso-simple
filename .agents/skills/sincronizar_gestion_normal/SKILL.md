---
name: sincronizar-gestion-normal
description: >-
  Revisa y sincroniza de forma idéntica todos los módulos y archivos de modules/gestion hacia modules/gestion_normal, asegurando que gestion_normal no utilice la compatibilidad de productos (omitiendo marcas, modelos, submodelos, productos_generales y componentes de compatibilidad).
---

# Sincronización de Gestión a Gestión Normal

Este flujo de trabajo permite mantener `modules/gestion_normal` idéntico y actualizado con respecto a `modules/gestion`, aplicando las transformaciones requeridas para el Módulo 5 y omitiendo por diseño todo lo relacionado a la compatibilidad vehicular de repuestos/productos.

## Diferencias Estructurales

Aspecto | `modules/gestion` | `modules/gestion_normal`
---|---|---
**ID de Módulo** | `2` | `5`
**Páginas (`conf__paginas`)** | `ID base` (ej. 40, 56, 105) | `ID base + 500` (ej. 540, 556, 605)
**Compatibilidad de Productos** | Incluye marcas, modelos, submodelos, productos generales | **NO SE UTILIZA**. Excluido por diseño.
**Archivos de Productos** | Monolíticos con compatibilidad (tabs vehicular) | Modulares desacoplados sin compatibilidad (`productos.php`, `productos.js`, `productos_ajax.php`, `productos_model.php`)
**Rutas relativas** | `modules/gestion/` | `modules/gestion_normal/`
**Breadcrumbs / Menú** | `Gestión` | `Gestión Normal`

## Archivos Excluidos de Sincronización

Los siguientes archivos pertenecen exclusivamente al subsistema de compatibilidad y **NUNCA** deben propagarse a `gestion_normal`:
- `marcas.php`, `marcas_ajax.php`, `marcas_model.php`
- `modelos.php`, `modelos_ajax.php`, `modelos_model.php`
- `submodelos.php`, `submodelos_ajax.php`, `submodelos_model.php`
- `productos_generales.js`, `productos_generales.php`, `productos_generales_ajax.php`, `productos_generales_model.php`

## Archivos con Implementación Específica (Productos)

- `productos.php`, `productos.js`, `productos_ajax.php`, `productos_model.php` cuentan con una versión propia en `gestion_normal` simplificada y sin los selects/tabs de marcas/modelos/submodelos. No deben sobreescribirse ciegamente desde `gestion`. Cualquier ajuste funcional común debe integrarse manualmente preservando la ausencia de compatibilidad.

## Ejecución del Script Automatizado

El script se encuentra en:
`[.agents/skills/sincronizar_gestion_normal/scripts/sync_gestion_to_normal.py](file:///c:/xampp/htdocs/casalucho/.agents/skills/sincronizar_gestion_normal/scripts/sync_gestion_to_normal.py)`

### 1. Simulación (Dry-Run)
Para analizar qué archivos difieren o requieren actualización sin modificar nada:
```powershell
python .agents/skills/sincronizar_gestion_normal/scripts/sync_gestion_to_normal.py
```

### 2. Sincronizar un archivo específico
```powershell
python .agents/skills/sincronizar_gestion_normal/scripts/sync_gestion_to_normal.py --file cobranzas_print.php --apply
```

### 3. Sincronizar todos los archivos
```powershell
python .agents/skills/sincronizar_gestion_normal/scripts/sync_gestion_to_normal.py --apply
```
⚠️ No usar `--apply` masivo mientras haya archivos donde el 5 está adelante (lista en `.agents/rules/gestion_normal_sync.md`): los pisa y se pierde lo que solo tiene el 5. Sincronizar de a un archivo con `--file`, después de leer su diff.

Transformaciones que aplica el script (2026-11-11): `$modudo_idx`, `'modulo_id' => 2`, `modulo_id = 2` en SQL / links, `pagina_id_por_url($c, 2, …)`, `permisos_pagina_modulo($c, 2, …)`, `const *MODULO_ID = 2;`, rutas `modules/gestion/` y `pagina_idx` fijos 1–499 → + 500 (el 0 queda en 0). No traduce comentarios con el número de página: esos diffs de 10–13 líneas son esperables.

### 4. Validación Sintáctica Posterior
Verificar la sintaxis de los archivos PHP modificados:
```powershell
& "C:\xampp\php\php.exe" -l modules/gestion_normal/<archivo>.php
```
