/**
 * modules/sindicatos/ddjj.js
 * Lógica frontend interactiva para Declaraciones Juradas (Página 1020).
 */

$(document).ready(function () {
    let tabla;
    let empleadosMemoria = [];
    let sucursalesCache = [];

    // Sucursales activas del empleador para el combo de la nómina
    function cargarSucursales(empleadorId) {
        sucursalesCache = [];
        const sel = $('#emp_sucursal_id');
        sel.find('option:not(:first)').remove();
        if (!empleadorId) return;
        $.get('ddjj_ajax.php', { accion: 'combo_sucursales', empleador_id: empleadorId }, function (res) {
            sucursalesCache = (res && res.data) || [];
            sucursalesCache.forEach(function (s) {
                sel.append($('<option>', { value: s.sucursal_id, text: 'N° ' + s.sucursal_numero + ' - ' + s.sucursal_nombre }));
            });
            renderizarTablaNomina();
        }, 'json');
    }

    function nombreSucursal(id) {
        const s = sucursalesCache.find(x => parseInt(x.sucursal_id, 10) === parseInt(id, 10));
        return s ? 'N° ' + s.sucursal_numero + ' ' + s.sucursal_nombre : (id ? 'Suc. ' + id : '<span class="text-muted">Automática</span>');
    }

    $(document).on('change', '#empleador_id', function () {
        cargarSucursales($(this).val());
    });
    let categoriasCache = [];

    // Inicializar DataTable
    tabla = $('#tablaDdjj').DataTable({
        ajax: {
            url: 'ddjj_ajax.php',
            type: 'GET',
            data: function (d) {
                d.accion = 'listar';
                d.empleador_id = $('#filtroEmpleador').val();
                d.periodo = $('#filtroPeriodo').val();
            }
        },
        columns: [
            {
                data: 'periodo',
                render: function (data) {
                    if (!data) return '-';
                    const s = String(data);
                    return s.substring(0, 4) + '/' + s.substring(4, 6);
                }
            },
            {
                data: 'secuencia',
                render: function (data) {
                    const sec = parseInt(data, 10);
                    return sec === 0
                        ? '<span class="badge bg-light text-dark border">Original</span>'
                        : '<span class="badge bg-info text-dark">Rect. ' + sec + '</span>';
                }
            },
            {
                data: null,
                render: function (data) {
                    return '<strong>' + (data.empleador_nombre || '-') + '</strong><br><small class="text-muted">CUIT: ' + (data.empleador_cuit || '-') + '</small>';
                }
            },
            {
                data: 'tipo',
                render: function (data) {
                    return data === 'OFICIO' ? '<span class="badge bg-danger">Oficio</span>' : '<span class="badge bg-primary">Empleador</span>';
                }
            },
            {
                data: 'origen_ingreso',
                render: function (data) {
                    return '<span class="badge bg-secondary">' + (data || 'MANUAL') + '</span>';
                }
            },
            {
                data: 'f_vto',
                render: function (data) {
                    if (!data) return '-';
                    const parts = data.split('-');
                    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : data;
                }
            },
            {
                data: 'cantidad_empleados',
                className: 'text-center',
                render: function (data) {
                    return '<span class="badge bg-dark rounded-pill">' + (data || 0) + '</span>';
                }
            },
            {
                data: 'total',
                className: 'text-end fw-bold',
                render: function (data) {
                    return '$ ' + parseFloat(data || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            },
            {
                data: 'estado_nombre',
                render: function (data, type, row) {
                    let badgeClass = 'bg-secondary';
                    const id = parseInt(row.tabla_estado_registro_id, 10);
                    if (id === 3) badgeClass = 'bg-warning text-dark';
                    else if (id === 28) badgeClass = 'bg-success';
                    else if (id === 2) badgeClass = 'bg-secondary';
                    else if (id === 38) badgeClass = 'bg-danger';

                    return '<span class="badge ' + badgeClass + '">' + (data || 'Borrador') + '</span>';
                }
            },
            {
                data: 'codigo_verificacion',
                render: function (data) {
                    return data ? '<code>' + data + '</code>' : '-';
                }
            },
            {
                data: 'acciones',
                orderable: false,
                className: 'text-center',
                // Botones del motor de estados (conf__paginas_funciones de la página 1020), ya filtrados
                // por el estado del registro y los permisos del usuario en el servidor
                render: function (data) {
                    return data || '';
                }
            }
        ],
        order: [[0, 'desc'], [1, 'desc']]
    });

    // Cargar botón de agregar según permisos
    $.get('ddjj_ajax.php', { accion: 'obtener_boton_agregar' }, function (res) {
        if (res && (res.tiene_permiso || res.nombre_funcion)) {
            const texto = res.nombre_funcion || res.texto || 'Nueva DDJJ';
            $('#contenedor-boton-agregar').html(
                `<button type="button" class="btn btn-primary btn-sm" id="btnNuevaDdjj">
                    <i class="fas fa-plus me-1"></i>${texto}
                </button>`
            );
        }
    }, 'json');

    // Cargar combos
    function cargarCombos() {
        $.get('ddjj_ajax.php', { accion: 'combo_empleadores' }, function (res) {
            if (res.success && res.data) {
                let optionsFiltro = '<option value="">-- Todos los empleadores autorizados --</option>';
                let optionsModal = '<option value="">-- Seleccionar empleador --</option>';
                res.data.forEach(function (e) {
                    const opt = `<option value="${e.empleador_id}">${e.cuit} - ${e.razon_social}</option>`;
                    optionsFiltro += opt;
                    optionsModal += opt;
                });
                $('#filtroEmpleador').html(optionsFiltro);
                $('#empleador_id').html(optionsModal);
            }
        }, 'json');

        $.get('ddjj_ajax.php', { accion: 'combo_categorias' }, function (res) {
            if (res.success && res.data) {
                categoriasCache = res.data;
                let opts = '<option value="">-- Seleccionar categoría --</option>';
                res.data.forEach(function (c) {
                    opts += `<option value="${c.categoria_id}">${c.codigo} - ${c.nombre}</option>`;
                });
                $('#emp_categoria_id').html(opts);
            }
        }, 'json');
    }
    cargarCombos();

    // Filtros
    $('#filtroEmpleador, #filtroPeriodo').on('change keyup', function () {
        tabla.ajax.reload();
    });
    $('#btnRecargar').on('click', function () {
        tabla.ajax.reload();
    });

    // Abrir modal nueva DDJJ
    $(document).on('click', '#btnNuevaDdjj', function () {
        $('#formDdjj')[0].reset();
        $('#ddjj_id').val('0');
        $('#secuencia').val('0 (Original)');
        $('#badgeOrigen').text('MANUAL');
        $('#empleador_id').prop('disabled', false);
        $('#periodo').prop('disabled', false);

        // Preseleccionar empleador si estaba seleccionado en el filtro superior
        const empFiltro = $('#filtroEmpleador').val();
        if (empFiltro) {
            $('#empleador_id').val(empFiltro).trigger('change');
        } else {
            cargarSucursales(null);
        }

        // Período actual o filtrado por defecto AAAAMM
        const perFiltro = $('#filtroPeriodo').val();
        if (perFiltro && perFiltro.length === 6) {
            $('#periodo').val(perFiltro);
        } else {
            const hoy = new Date();
            const a = hoy.getFullYear();
            const m = String(hoy.getMonth() + 1).padStart(2, '0');
            $('#periodo').val(`${a}${m}`);
        }

        empleadosMemoria = [];
        renderizarTablaNomina();
        $('#modalDdjj').modal('show');
    });

    // Renderizar nómina de empleados
    function renderizarTablaNomina() {
        const tbody = $('#tbodyNomina');
        tbody.empty();
        $('#contadorEmpleados').text(empleadosMemoria.length);

        if (empleadosMemoria.length === 0) {
            tbody.append('<tr><td colspan="9" class="text-center text-muted py-4">No hay empleados cargados en la nómina.</td></tr>');
            $('#totalPreview').text('$ 0,00');
            $('#resumenConceptos').html('<span class="text-muted">Cálculo de aportes por concepto al guardar o previsualizar.</span>');
            return;
        }

        let totalEst = 0;
        empleadosMemoria.forEach(function (e, index) {
            const catNombre = obtenerNombreCategoria(e.categoria_id);
            const r1 = parseFloat(e.rem_1 || 0);
            const r2 = parseFloat(e.rem_2 || 0);
            totalEst += (r1 + r2) * 0.04; // Estimación rápida de aportes

            const tr = `
                <tr>
                    <td><code>${e.cuil}</code></td>
                    <td><strong>${e.nombre}</strong></td>
                    <td><small>${nombreSucursal(e.sucursal_id)}</small></td>
                    <td><small>${catNombre}</small></td>
                    <td class="text-end">$ ${r1.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-end">$ ${r2.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-center">${e.media_jornada ? '<span class="badge bg-warning text-dark">Sí</span>' : '<span class="badge bg-light text-muted border">No</span>'}</td>
                    <td class="text-center">${e.afiliado ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-light text-muted border">No</span>'}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-link text-primary p-0 me-2 btnEditarFila" data-index="${index}"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 btnEliminarFila" data-index="${index}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            tbody.append(tr);
        });

        $('#totalPreview').text('$ ' + totalEst.toLocaleString('es-AR', { minimumFractionDigits: 2 }));
    }

    function obtenerNombreCategoria(catId) {
        const id = parseInt(catId, 10);
        const f = categoriasCache.find(c => parseInt(c.categoria_id, 10) === id);
        return f ? `${f.codigo} - ${f.nombre}` : `Cat. ${id}`;
    }

    // Modal agregar empleado individual
    $('#btnAgregarEmpleado').on('click', function () {
        $('#formEmpleadoIndividual')[0].reset();
        $('#emp_indice').val('-1');
        $('#emp_sucursal_id').val('');
        $('#modalEmpleado').modal('show');
    });

    $('#btnAceptarEmpleado').on('click', function () {
        const cuil = $('#emp_cuil').val().replace(/\D/g, '');
        const nombre = $('#emp_nombre').val().trim();
        const catId = parseInt($('#emp_categoria_id').val(), 10);
        const rem1 = parseFloat($('#emp_rem1').val() || 0);
        const rem2 = parseFloat($('#emp_rem2').val() || 0);
        const mediaJor = $('#emp_media_jornada').is(':checked') ? 1 : 0;
        const afiliado = $('#emp_afiliado').is(':checked') ? 1 : 0;
        const indice = parseInt($('#emp_indice').val(), 10);

        if (cuil.length !== 11) {
            Swal.fire('Atención', 'El CUIL debe tener exactamente 11 dígitos numéricos.', 'warning');
            return;
        }
        if (!nombre) {
            Swal.fire('Atención', 'El nombre es obligatorio.', 'warning');
            return;
        }
        if (!catId) {
            Swal.fire('Atención', 'Seleccione una categoría del CCT.', 'warning');
            return;
        }

        const previo = indice >= 0 ? (empleadosMemoria[indice] || {}) : {};
        const empObj = {
            ...previo,
            sucursal_id: $('#emp_sucursal_id').val() ? parseInt($('#emp_sucursal_id').val(), 10) : null,
            cuil: cuil,
            nombre: nombre,
            categoria_id: catId,
            rem_1: rem1,
            rem_2: rem2,
            rem_3: 0.0,
            media_jornada: mediaJor,
            jubilado: previo.jubilado || 0,
            afiliado: afiliado
        };

        if (indice >= 0) {
            empleadosMemoria[indice] = empObj;
        } else {
            empleadosMemoria.push(empObj);
        }

        $('#modalEmpleado').modal('hide');
        renderizarTablaNomina();
    });

    $(document).on('click', '.btnEditarFila', function () {
        const index = parseInt($(this).data('index'), 10);
        const e = empleadosMemoria[index];
        if (!e) return;

        $('#emp_indice').val(index);
        $('#emp_cuil').val(e.cuil);
        $('#emp_nombre').val(e.nombre);
        $('#emp_categoria_id').val(e.categoria_id);
        $('#emp_sucursal_id').val(e.sucursal_id || '');
        $('#emp_rem1').val(e.rem_1);
        $('#emp_rem2').val(e.rem_2);
        $('#emp_media_jornada').prop('checked', !!e.media_jornada);
        $('#emp_afiliado').prop('checked', !!e.afiliado);
        $('#modalEmpleado').modal('show');
    });

    $(document).on('click', '.btnEliminarFila', function () {
        const index = parseInt($(this).data('index'), 10);
        empleadosMemoria.splice(index, 1);
        renderizarTablaNomina();
    });

    // Modal Importar Archivo
    $('#btnImportarModal').on('click', function () {
        $('#formImportador')[0].reset();
        $('#alertaImportacion').addClass('d-none').empty();
        $('#modalImportar').modal('show');
    });

    $('#btnEjecutarImportacion').on('click', function () {
        const fileInput = document.getElementById('archivoNomina');
        if (!fileInput.files.length) {
            Swal.fire('Atención', 'Seleccione un archivo para importar.', 'warning');
            return;
        }

        const btn = $(this);
        const orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Procesando archivo...');

        const fd = new FormData(document.getElementById('formImportador'));
        fd.append('accion', 'importar_archivo');

        $.ajax({
            url: 'ddjj_ajax.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(orig);
                if (res.success && res.empleados) {
                    empleadosMemoria = res.empleados;
                    renderizarTablaNomina();
                    $('#badgeOrigen').text($('#tipoFormato').val() === 'F931_SICOSS' ? 'F931' : 'PLANILLA');
                    $('#modalImportar').modal('hide');

                    Swal.fire({
                        icon: 'success',
                        title: 'Importación Completada',
                        text: `Se importaron ${res.aceptadas} empleados correctamente.`
                    });
                } else {
                    $('#alertaImportacion').removeClass('d-none alert-success').addClass('alert-danger')
                        .html(res.message || 'No se pudieron procesar las filas del archivo.');
                }
            },
            error: function () {
                btn.prop('disabled', false).html(orig);
                Swal.fire('Error', 'Error de comunicación al subir el archivo.', 'error');
            }
        });
    });

    // Guardar Borrador
    $('#btnGuardarBorrador').on('click', function () {
        const empId = $('#empleador_id').val();
        const per = $('#periodo').val();

        if (!empId) {
            Swal.fire('Atención', 'Seleccione el empleador aportante.', 'warning');
            return;
        }
        if (!per || per.length !== 6) {
            Swal.fire('Atención', 'Ingrese un período válido en formato AAAAMM (ej: 202610).', 'warning');
            return;
        }
        if (empleadosMemoria.length === 0) {
            Swal.fire('Atención', 'Debe agregar al menos un empleado en la nómina.', 'warning');
            return;
        }

        const btn = $(this);
        const orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        const cabecera = {
            ddjj_id: parseInt($('#ddjj_id').val(), 10) || 0,
            empleador_id: parseInt(empId, 10),
            periodo: parseInt(per, 10),
            tipo: 'EMPLEADOR',
            origen_ingreso: $('#badgeOrigen').text()
        };

        $.post('ddjj_ajax.php', {
            accion: 'guardar_borrador',
            cabecera: JSON.stringify(cabecera),
            nomina: JSON.stringify(empleadosMemoria)
        }, function (res) {
            btn.prop('disabled', false).html(orig);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Borrador Guardado',
                    text: res.message
                });
                $('#modalDdjj').modal('hide');
                tabla.ajax.reload();
            } else {
                Swal.fire('Error', res.message || 'No se pudo guardar el borrador.', 'error');
            }
        }, 'json').fail(function () {
            btn.prop('disabled', false).html(orig);
            Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
        });
    });

    // Acciones de fila
    $(document).on('click', '.btn-accion', function () {
        const accion = $(this).data('accion');
        const id = $(this).data('id');

        if (accion === 'editar') {
            $.get('ddjj_ajax.php', { accion: 'obtener', id: id }, function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    $('#ddjj_id').val(d.ddjj_id);
                    $('#empleador_id').val(d.empleador_id).prop('disabled', true);
                    cargarSucursales(d.empleador_id);
                    $('#periodo').val(d.periodo).prop('disabled', true);
                    $('#secuencia').val(d.secuencia > 0 ? `Rectificativa ${d.secuencia}` : '0 (Original)');
                    $('#badgeOrigen').text(d.origen_ingreso || 'MANUAL');

                    empleadosMemoria = [];
                    (d.nomina || []).forEach(function (e) {
                        empleadosMemoria.push({
                            cuil: e.cuil,
                            nombre: e.empleado_nombre,
                            categoria_id: parseInt(e.categoria_id, 10),
                            rem_1: parseFloat(e.rem_1),
                            rem_2: parseFloat(e.rem_2),
                            rem_3: parseFloat(e.rem_3 || 0),
                            media_jornada: parseInt(e.media_jornada, 10),
                            jubilado: parseInt(e.jubilado, 10),
                            afiliado: parseInt(e.afiliado, 10),
                            sucursal_id: e.sucursal_id ? parseInt(e.sucursal_id, 10) : null,
                            obra_social_id: e.obra_social_id || null,
                            novedad_id: e.novedad_id || null,
                            baja_motivo_id: e.baja_motivo_id || null,
                            f_baja: e.f_baja || null
                        });
                    });

                    renderizarTablaNomina();
                    $('#modalDdjj').modal('show');
                }
            }, 'json');
        } else if (accion === 'confirmar') {
            Swal.fire({
                title: '¿Confirmar y Presentar DDJJ?',
                html: 'Al presentar la declaración jurada se emitirán formalmente los comprobantes de deuda y asientos contables correspondientes.<br><br><strong>Esta acción no puede deshacerse.</strong>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Sí, Presentar Ahora',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Presentando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                    $.post('ddjj_ajax.php', { accion: 'confirmar', id: id }, function (res) {
                        if (res.success) {
                            tabla.ajax.reload();
                            mostrarConstancia(id);
                        } else {
                            Swal.fire('Error', res.message || 'No se pudo confirmar la presentación.', 'error');
                        }
                    }, 'json');
                }
            });
        } else if (accion === 'rectificar') {
            Swal.fire({
                title: '¿Iniciar Rectificativa?',
                text: 'Se abrirá una nueva secuencia que revertirá la DDJJ anterior al confirmarse.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Sí, Iniciar Rectificativa',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('ddjj_ajax.php', { accion: 'iniciar_rectificativa', id: id }, function (res) {
                        if (res.success) {
                            tabla.ajax.reload();
                            Swal.fire('Rectificativa Iniciada', res.message, 'success');
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        } else if (accion === 'constancia' || accion === 'ver') {
            mostrarConstancia(id);
        } else if (accion === 'anular') {
            Swal.fire({
                title: '¿Anular Borrador?',
                text: 'Esta acción eliminará el borrador en curso.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, Anular',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('ddjj_ajax.php', { accion: 'anular', id: id }, function (res) {
                        if (res.success) {
                            tabla.ajax.reload();
                            Swal.fire('Anulado', res.message, 'success');
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        }
    });

    // Mostrar Constancia Oficial
    function mostrarConstancia(id) {
        $.get('ddjj_ajax.php', { accion: 'obtener', id: id }, function (res) {
            if (res.success && res.data) {
                const d = res.data;
                const periodoFmt = String(d.periodo).substring(0, 4) + '/' + String(d.periodo).substring(4, 6);

                let conceptosHtml = '';
                (d.conceptos || []).forEach(function (c) {
                    conceptosHtml += `
                        <tr>
                            <td>${c.concepto_descripcion}</td>
                            <td class="text-center">${c.cantidad_empleados}</td>
                            <td class="text-end">$ ${parseFloat(c.base_total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                            <td class="text-end fw-bold">$ ${parseFloat(c.importe_total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                        </tr>
                    `;
                });

                const html = `
                    <div class="border p-4 bg-white shadow-sm" id="imprimibleConstancia">
                        <div class="text-center border-bottom pb-3 mb-3">
                            <h4 class="fw-bold mb-1">CONSTANCIA OFICIAL DE PRESENTACIÓN</h4>
                            <p class="text-muted mb-0">Declaración Jurada y Aportes Sindicales - CCT 130/75</p>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <p class="mb-1"><strong>Empleador:</strong> ${d.empleador_nombre}</p>
                                <p class="mb-1"><strong>CUIT:</strong> ${d.empleador_cuit}</p>
                                <p class="mb-1"><strong>Actividad:</strong> ${d.rubro || 'Comercio'}</p>
                            </div>
                            <div class="col-6 text-end">
                                <p class="mb-1"><strong>Período:</strong> ${periodoFmt}</p>
                                <p class="mb-1"><strong>Secuencia:</strong> ${d.secuencia === 0 ? 'Original (0)' : 'Rectificativa (' + d.secuencia + ')'}</p>
                                <p class="mb-1"><strong>Fecha de Presentación:</strong> ${d.f_presentacion || '-'}</p>
                            </div>
                        </div>
                        <table class="table table-bordered table-sm mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Concepto Liquidado</th>
                                    <th class="text-center">Cant. Empleados</th>
                                    <th class="text-end">Base Imponible</th>
                                    <th class="text-end">Importe Determinado</th>
                                </tr>
                            </thead>
                            <tbody>${conceptosHtml}</tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="3" class="text-end">TOTAL LIQUIDADO:</td>
                                    <td class="text-end text-primary fs-5">$ ${parseFloat(d.total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="border-top pt-3 text-center">
                            <p class="small text-muted mb-1">CÓDIGO ÚNICO DE VERIFICACIÓN ELECTRÓNICA</p>
                            <h3 class="font-monospace fw-bold text-dark tracking-wide">${d.codigo_verificacion}</h3>
                            <small class="text-muted">Esta constancia acredita formalmente la presentación de la declaración jurada mensual.</small>
                        </div>
                    </div>
                `;

                $('#cuerpoConstancia').html(html);
                $('#modalConstancia').modal('show');
            }
        }, 'json');
    }

    $('#btnImprimirConstancia').on('click', function () {
        const contenido = document.getElementById('imprimibleConstancia').innerHTML;
        const w = window.open('', '_blank');
        w.document.write(`
            <html>
                <head>
                    <title>Constancia DDJJ</title>
                    <link rel="stylesheet" href="${window.APP_ASSETS_URL}css/bootstrap-5.3.2/bootstrap.min.css">
                    <style>body { font-family: sans-serif; padding: 2rem; }</style>
                </head>
                <body>${contenido}</body>
            </html>
        `);
        w.document.close();
        w.focus();
        setTimeout(function () { w.print(); }, 500);
    });
});
