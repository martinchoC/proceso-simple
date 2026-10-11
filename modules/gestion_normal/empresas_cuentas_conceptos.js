/**
 * empresas_cuentas_conceptos.js
 *
 * Lógica frontend para Conceptos de Cuentas Contables por Empresa (D35/D36)
 * Sistema gestion_multipyme - Módulo Gestión
 */

$(document).ready(function () {
    // Parámetros de entorno desde URL o por defecto
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = urlParams.get('empresa_id') || urlParams.get('empresa_idx') || window.EMPRESA_ID;
    const pagina_idx = 101;
    const ajaxUrl = 'empresas_cuentas_conceptos_ajax.php';

    let tablaConceptos = null;
    let modalAsignar = null;

    // Inicializar Modal Bootstrap 5
    const modalEl = document.getElementById('modalAsignarCuenta');
    if (modalEl) {
        modalAsignar = new bootstrap.Modal(modalEl);
    }

    // Inicializar DataTable
    tablaConceptos = $('#tablaConceptos').DataTable({
        ajax: {
            url: ajaxUrl,
            type: 'GET',
            data: {
                accion: 'listar',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            },
            dataSrc: 'data'
        },
        columns: [
            { data: 'concepto_cod' },
            { data: 'titulo' },
            { data: 'descripcion' },
            { data: 'cuenta' },
            { data: 'estado', className: 'text-center' },
            { data: 'acciones', className: 'text-center', orderable: false }
        ],
        language: {
            processing: "Procesando...",
            search: "Buscar:",
            lengthMenu: "Mostrar _MENU_ registros",
            info: "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            infoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
            infoFiltered: "(filtrado de un total de _MAX_ registros)",
            loadingRecords: "Cargando...",
            zeroRecords: "No se encontraron resultados",
            emptyTable: "No hay conceptos contables configurados para esta empresa.",
            paginate: {
                first: "Primero",
                previous: "Anterior",
                next: "Siguiente",
                last: "Último"
            }
        },
        responsive: true,
        processing: true,
        pageLength: 10,
        dom: '<"row align-items-center mb-2"<"col-12 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>'
    });

    // Botón Recargar
    $('#btnRecargar').on('click', function () {
        if (tablaConceptos) {
            tablaConceptos.ajax.reload(null, false);
        }
    });

    // Cargar cuentas contables en el selector del modal
    function cargarCuentasSelect(cuentaSeleccionadaId) {
        return $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: {
                accion: 'obtener_cuentas',
                empresa_idx: empresa_idx
            },
            dataType: 'json'
        }).then(function (res) {
            const $select = $('#selectCuentaContable');
            $select.empty();
            $select.append('<option value="">-- Seleccionar Cuenta Contable --</option>');

            if (res.success && Array.isArray(res.data)) {
                res.data.forEach(function (c) {
                    const selected = (parseInt(c.id) === parseInt(cuentaSeleccionadaId)) ? 'selected' : '';
                    $select.append(`<option value="${c.id}" ${selected}>${c.codigo} - ${c.nombre}</option>`);
                });
            }
        });
    }

    // Abrir Modal para Asignar Cuenta
    $(document).on('click', '.btn-accion[data-accion="editar"]', function () {
        const id = $(this).data('id');
        if (!id) return;

        // Mostrar SweetAlert loader
        Swal.fire({
            title: 'Cargando concepto...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Obtener datos del concepto
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: {
                accion: 'obtener',
                id: id,
                empresa_idx: empresa_idx
            },
            dataType: 'json'
        }).done(function (res) {
            if (!res.success || !res.data) {
                Swal.fire('Error', res.error || 'No se pudo obtener la información del concepto.', 'error');
                return;
            }

            const c = res.data;
            $('#concepto_id').val(c.empresa_cuenta_concepto_id);
            $('#lblConceptoTitulo').text(c.titulo || c.concepto);
            $('#lblConceptoCodigo').text(c.concepto);
            $('#lblConceptoDescripcion').text(c.descripcion_larga || '');

            // Cargar combo de cuentas y preseleccionar
            cargarCuentasSelect(c.cont_cuenta_id).always(function () {
                Swal.close();
                if (modalAsignar) {
                    modalAsignar.show();
                }
            });
        }).fail(function (xhr) {
            Swal.fire('Error', 'Error de comunicación con el servidor: ' + (xhr.responseJSON?.error || xhr.statusText), 'error');
        });
    });

    // Guardar Asignación de Cuenta
    $('#formAsignarCuenta').on('submit', function (e) {
        e.preventDefault();

        const id = $('#concepto_id').val();
        const cont_cuenta_id = $('#selectCuentaContable').val();

        if (!id || !cont_cuenta_id) {
            Swal.fire('Validación', 'Debe seleccionar una cuenta contable.', 'warning');
            return;
        }

        const btnSubmit = $('#btnGuardarCuenta');
        btnSubmit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'guardar',
                id: id,
                cont_cuenta_id: cont_cuenta_id,
                empresa_idx: empresa_idx
            },
            dataType: 'json'
        }).done(function (res) {
            if (res.success) {
                if (modalAsignar) modalAsignar.hide();
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: res.message || 'Cuenta asignada correctamente.',
                    timer: 2000,
                    showConfirmButton: false
                });
                if (tablaConceptos) {
                    tablaConceptos.ajax.reload(null, false);
                }
            } else {
                Swal.fire('Error', res.error || 'No se pudo guardar la asignación.', 'error');
            }
        }).fail(function (xhr) {
            Swal.fire('Error', 'Error en el servidor: ' + (xhr.responseJSON?.error || xhr.statusText), 'error');
        }).always(function () {
            btnSubmit.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Guardar Asignación');
        });
    });
});
