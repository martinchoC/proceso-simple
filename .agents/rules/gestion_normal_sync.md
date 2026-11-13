# Regla de Sincronización entre Gestión y Gestión Normal

Esta regla aplica a todo cambio realizado en el módulo `modules/gestion/` o `modules/gestion_normal/`:

1. **Gestión (`modules/gestion`) vs Gestión Normal (`modules/gestion_normal`)**:
   - `modules/gestion` corresponde al **Módulo 2** (contiene compatibilidad de productos: marcas, modelos, submodelos, repuestos).
   - `modules/gestion_normal` corresponde al **Módulo 5** (gestión comercial y contable idéntica pero **SIN compatibilidad de productos**).

2. **Doble Mantenimiento Obligatorio**:
   - Cada vez que se corrija un bug, se agregue un endpoint AJAX, se modifique un modelo o una vista en `modules/gestion/` que no sea exclusivo de compatibilidad de productos, debe reflejarse idénticamente en `modules/gestion_normal/`.
   - Se debe ajustar `$modudo_idx = 5`, `'modulo_id' => 5`, `permisos_pagina_modulo($conn, 5, …)` / `pagina_id_por_url($conn, 5, …)`, y el ID de página (`pagina_idx = base + 500`). Un `2` olvidado en `permisos_pagina_modulo` del 5 hace validar botones y acciones contra la página del módulo 2 (pasó en `entidades_ajax.php` y `proveedores_ajax.php`, corregido el 2026-11-11).

3. **Exclusiones de Compatibilidad**:
   - En `modules/gestion_normal` **NO deben crearse ni importarse**:
     * `marcas.*`
     * `modelos.*`
     * `submodelos.*`
     * `productos_generales.*`
   - Las pantallas de `productos.*` y `listas_precios_productos.*` en `gestion_normal` no deben contener campos, filtros ni pestañas de compatibilidad vehicular (versión propia: el script de sincronización no las pisa).

4. **Uso del Script de Sincronización**:
   - Para verificar o sincronizar masivamente, ejecutar:
     `python .agents/skills/sincronizar_gestion_normal/scripts/sync_gestion_to_normal.py`
     (en esta máquina `python` no está en el PATH de Git Bash: usar `/c/Users/devel/anaconda3/python.exe`).
   - Sin `--apply` solo informa. Los diffs de 10–13 líneas que quedan son comentarios con el número de página o módulo; revisar los más grandes.
   - **No usar `--apply` sobre archivos donde el 5 está adelante** (borra lo que solo tiene el 5). Al 2026-11-11 hay deriva real en: `ventas_facturas.php` / `_ajax.php` (el 5 importa comprobantes emitidos de ARCA), `facturas_proveedores*` (importación ARCA solo en el 5), `stock_consulta*`, `productos_consulta*` (+ `productos_consulta_remito*`, solo en el 2), `ventas_remitos_model.php` (auditoría multitabla solo en el 2) y `ordenes_compra.js` (insignia de contratos solo en el 2). Integrar a mano.
