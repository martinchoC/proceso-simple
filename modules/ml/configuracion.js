/**
 * Lógica JavaScript para Configuración y Opciones del Grupo (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/configuracion.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 308;

    let permiteModificar = false;

    // =========================================================================
    // 1. CARGA DE GRUPOS
    // =========================================================================
    function cargarGrupos() {
        $.ajax({
            url: 'configuracion_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    permiteModificar = !!res.data.permite_modificar;
                    const grupos = res.data.grupos || [];
                    let optGrupos = '';
                    grupos.forEach(function (g, idx) {
                        const sel = idx === 0 ? 'selected' : '';
                        optGrupos += `<option value="${g.grupo_id}" ${sel}>${g.nombre}</option>`;
                    });
                    $('#filtroGrupo').html(optGrupos);
                    cargarOpciones();
                }
            }
        });
    }

    // =========================================================================
    // 2. CARGA DE LAS 10 OPCIONES
    // =========================================================================
    function cargarOpciones() {
        const grupoId = $('#filtroGrupo').val();
        if (!grupoId) return;

        $('#cuerpoOpciones').html('<tr><td colspan="5" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando opciones...</td></tr>');

        $.ajax({
            url: 'configuracion_ajax.php',
            type: 'GET',
            data: { accion: 'listar_opciones', grupo_id: grupoId, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let html = '';
                    res.data.forEach(function (opc, index) {
                        const valorActualId = parseInt(opc.valor_actual_id || 0, 10);
                        let selectValores = `<select class="form-select form-select-sm select-opcion-valor" data-opcion-id="${opc.opcion_id}" ${!permiteModificar ? 'disabled' : ''}>`;
                        
                        opc.valores_disponibles.forEach(function (v) {
                            const selected = parseInt(v.opcion_valor_id, 10) === valorActualId ? 'selected' : '';
                            const badgeDef = parseInt(v.es_defecto, 10) === 1 ? ' (Por Defecto)' : '';
                            selectValores += `<option value="${v.opcion_valor_id}" ${selected}>${v.nombre}${badgeDef}</option>`;
                        });
                        selectValores += `</select>`;

                        const fechaVigencia = opc.vigente_desde ? `<span class="badge bg-light text-dark border font-monospace">${opc.vigente_desde}</span>` : '<span class="text-muted">-</span>';
                        
                        const btnGuardar = permiteModificar
                            ? `<button type="button" class="btn btn-sm btn-outline-primary btn-guardar-opcion" data-opcion-id="${opc.opcion_id}" title="Aplicar nueva vigencia"><i class="fas fa-save me-1"></i>Aplicar</button>`
                            : `<span class="badge bg-secondary">Solo lectura</span>`;

                        html += `
                            <tr>
                                <td class="text-center fw-bold text-muted">${index + 1}</td>
                                <td>
                                    <span class="fw-semibold text-dark">${opc.opcion_nombre}</span>
                                    <br><small class="text-muted font-monospace">${opc.opcion_codigo}</small>
                                </td>
                                <td>${selectValores}</td>
                                <td class="text-center">${fechaVigencia}</td>
                                <td class="text-center">${btnGuardar}</td>
                            </tr>
                        `;
                    });

                    $('#cuerpoOpciones').html(html);
                } else {
                    $('#cuerpoOpciones').html('<tr><td colspan="5" class="text-center py-3 text-danger">No se pudieron cargar las opciones.</td></tr>');
                }
            }
        });
    }

    // =========================================================================
    // 3. EVENTOS
    // =========================================================================
    $('#filtroGrupo').on('change', function () {
        cargarOpciones();
    });

    $('#btnRecargarOpciones').on('click', function () {
        cargarOpciones();
    });

    $(document).on('click', '.btn-guardar-opcion', function () {
        const opcionId = $(this).data('opcion-id');
        const grupoId = $('#filtroGrupo').val();
        const select = $(`select[data-opcion-id="${opcionId}"]`);
        const valorId = select.val();
        const valorTexto = select.find('option:selected').text();
        const boton = $(this);

        Swal.fire({
            title: '¿Actualizar directriz corporativa?',
            html: `Se fijará como valor activo:<br><strong>${valorTexto}</strong><br><small class="text-muted">Se cerrará la vigencia actual y comenzará a regir desde hoy.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplicar directriz',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                boton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: 'configuracion_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'guardar_opcion',
                        grupo_id: grupoId,
                        opcion_id: opcionId,
                        opcion_valor_id: valorId,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        boton.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Aplicar');
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Directriz Actualizada', text: res.message, timer: 1500, showConfirmButton: false });
                            cargarOpciones();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        boton.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Aplicar');
                        Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
                    }
                });
            }
        });
    });

    // INICIALIZACIÓN
    cargarGrupos();
});
