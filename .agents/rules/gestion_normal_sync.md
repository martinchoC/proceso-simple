# Regla de Sincronización entre Gestión y Gestión Normal

Esta regla aplica a todo cambio realizado en el módulo `modules/gestion/` o `modules/gestion_normal/`:

1. **Gestión (`modules/gestion`) vs Gestión Normal (`modules/gestion_normal`)**:
   - `modules/gestion` corresponde al **Módulo 2** (contiene compatibilidad de productos: marcas, modelos, submodelos, repuestos).
   - `modules/gestion_normal` corresponde al **Módulo 5** (gestión comercial y contable idéntica pero **SIN compatibilidad de productos**).

2. **Doble Mantenimiento Obligatorio**:
   - Cada vez que se corrija un bug, se agregue un endpoint AJAX, se modifique un modelo o una vista en `modules/gestion/` que no sea exclusivo de compatibilidad de productos, debe reflejarse idénticamente en `modules/gestion_normal/`.
   - Se debe ajustar `$modudo_idx = 5`, `'modulo_id' => 5`, y el ID de página (`pagina_idx = base + 500`).

3. **Exclusiones de Compatibilidad**:
   - En `modules/gestion_normal` **NO deben crearse ni importarse**:
     * `marcas.*`
     * `modelos.*`
     * `submodelos.*`
     * `productos_generales.*`
   - La pantalla de `productos.*` en `gestion_normal` no debe contener campos, filtros ni pestañas de compatibilidad vehicular.

4. **Uso del Script de Sincronización**:
   - Para verificar o sincronizar masivamente, ejecutar:
     `python .agents/skills/sincronizar_gestion_normal/scripts/sync_gestion_to_normal.py`
