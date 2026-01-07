<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Gestión de Grupos de Comprobantes";
$currentPage = 'comprobantes_grupos';
$modudo_idx = 2; // Ajustar según tu sistema
$pagina_idx = 46; // ID de página para grupos de comprobantes

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-invoice me-2"></i>Gestión de Grupos de Comprobantes
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Grupos de Comprobantes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                                        <div class="float-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="btnRecargar">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- DataTable -->
                                        <table id="tablaComprobantesGrupos" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th>Grupo de Comprobante</th>
                                                    <th width="120">Estado</th>
                                                    <th width="200" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal para crear/editar grupo de comprobantes -->
            <div class="modal fade" id="modalComprobanteGrupo" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Grupo de Comprobante</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formComprobanteGrupo" class="needs-validation" novalidate>
                                <input type="hidden" id="comprobante_grupo_id" name="comprobante_grupo_id" />
                                <div class="mb-3">
                                    <label for="comprobante_grupo" class="form-label">Nombre del Grupo *</label>
                                    <input type="text" class="form-control" id="comprobante_grupo"
                                        name="comprobante_grupo" maxlength="50" required>
                                    <div class="invalid-feedback">El nombre del grupo es obligatorio</div>
                                    <small class="text-muted">Máximo 50 caracteres (ej: FACTURA, NOTA DE CRÉDITO,
                                        TICKET, etc.)</small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = 2;
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Configuración de DataTable
            var tabla = $('#tablaComprobantesGrupos').DataTable({
                ajax: {
                    url: 'comprobantes_grupos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar',
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataSrc: ''
                },
                pageLength: 50,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                columns: [
                    {
                        data: 'comprobante_grupo_id',
                        className: 'text-center fw-bold',
                        render: function (data) {
                            return `<span class="fw-medium">${data}</span>`;
                        }
                    },
                    {
                        data: 'comprobante_grupo',
                        render: function (data, type, row) {
                            return `<div class="fw-medium">${data}</div>`;
                        }
                    },
                    {
                        data: 'estado_info',
                        className: 'text-center',
                        render: function (data) {
                            if (!data || !data.estado_registro) {
                                return '<span class="fw-medium">Sin estado</span>';
                            }

                            var estado = data.estado_registro;

                            return `<span class="fw-medium">
                                ${estado}
                                </span>`;
                        }
                    },
                    {
                        data: 'botones',
                        orderable: false,
                        searchable: false,
                        className: "text-center",
                        width: '200px',
                        render: function (data, type, row) {
                            var botones = '';

                            if (data && data.length > 0) {
                                var editarBoton = '';
                                var otrosBotones = '';

                                data.forEach(boton => {
                                    var claseBoton = 'btn-sm me-1 ';
                                    if (boton.bg_clase && boton.text_clase) {
                                        claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                    } else if (boton.color_clase) {
                                        claseBoton += boton.color_clase;
                                    } else {
                                        claseBoton += 'btn-outline-primary';
                                    }

                                    var titulo = boton.descripcion || boton.nombre_funcion;
                                    var accionJs = boton.accion_js;
                                    var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                    var esConfirmable = boton.es_confirmable || 0;

                                    var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                       title="${titulo}" 
                                       data-id="${row.comprobante_grupo_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-comprobante-grupo="${row.comprobante_grupo}">
                                    ${icono}
                                </button>`;

                                    if (accionJs === 'editar') {
                                        editarBoton = botonHtml;
                                    } else {
                                        otrosBotones += botonHtml;
                                    }
                                });

                                botones = editarBoton + otrosBotones;
                            } else {
                                botones = '<span class="text-muted small">Sin acciones</span>';
                            }

                            return `<div class="btn-group" role="group">${botones}</div>`;
                        }
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                order: [[1, 'asc']],
                responsive: true,
                createdRow: function (row, data, dataIndex) {
                    if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                        $(row).addClass('table-secondary');
                    } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                        $(row).addClass('table-warning');
                    }
                }
            });

            // Cargar botón Agregar dinámicamente
            function cargarBotonAgregar() {
                $.get('comprobantes_grupos_ajax.php', {
                    accion: 'obtener_boton_agregar',
                    pagina_idx: pagina_idx
                }, function (botonAgregar) {
                    if (botonAgregar && botonAgregar.nombre_funcion) {
                        var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';

                        var colorClase = 'btn-primary';
                        if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                            colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                        } else if (botonAgregar.color_clase) {
                            colorClase = botonAgregar.color_clase;
                        }

                        $('#contenedor-boton-agregar').html(
                            `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                            ${icono}${botonAgregar.nombre_funcion}
                         </button>`
                        );
                    } else {
                        $('#contenedor-boton-agregar').html(
                            '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                            '<i class="fas fa-plus me-1"></i>Agregar Grupo</button>'
                        );
                    }
                }, 'json');
            }

            // Manejador para botón "Agregar"
            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nuevo Grupo de Comprobante');

                var modal = new bootstrap.Modal(document.getElementById('modalComprobanteGrupo'));
                modal.show();
                $('#comprobante_grupo').focus();
            });

            // Manejador para botones de acción dinámicos
            $(document).on('click', '.btn-accion', function () {
                var grupoId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var grupoNombre = $(this).data('comprobante-grupo');

                if (accionJs === 'editar') {
                    cargarGrupoParaEditar(grupoId);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> el grupo <strong>"${grupoNombre}"</strong>?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionJs}`,
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true,
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            ejecutarAccion(grupoId, accionJs, grupoNombre);
                        }
                    });
                } else {
                    ejecutarAccion(grupoId, accionJs, grupoNombre);
                }
            });

            // Función para ejecutar cualquier acción del backend
            function ejecutarAccion(grupoId, accionJs, grupoNombre) {
                $.post('comprobantes_grupos_ajax.php', {
                    accion: 'ejecutar_accion',
                    comprobante_grupo_id: grupoId,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                            text: res.message || `Grupo "${grupoNombre}" actualizado correctamente`,
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionJs} el grupo`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para cargar grupo en modal de edición
            function cargarGrupoParaEditar(grupoId) {
                $.get('comprobantes_grupos_ajax.php', {
                    accion: 'obtener',
                    comprobante_grupo_id: grupoId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.comprobante_grupo_id) {
                        resetModal();
                        $('#comprobante_grupo_id').val(res.comprobante_grupo_id);
                        $('#comprobante_grupo').val(res.comprobante_grupo);
                        $('#modalLabel').text('Editar Grupo de Comprobante');

                        var modal = new bootstrap.Modal(document.getElementById('modalComprobanteGrupo'));
                        modal.show();

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del grupo",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para resetear el modal
            function resetModal() {
                $('#formComprobanteGrupo')[0].reset();
                $('#comprobante_grupo_id').val('');
                $('#formComprobanteGrupo').removeClass('was-validated');
            }

            // Validación del formulario
            $('#btnGuardar').click(function () {
                var form = document.getElementById('formComprobanteGrupo');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#comprobante_grupo_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var grupoNombre = $('#comprobante_grupo').val().trim();

                if (!grupoNombre) {
                    $('#comprobante_grupo').addClass('is-invalid');
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'comprobantes_grupos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        comprobante_grupo_id: id,
                        comprobante_grupo: grupoNombre,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    success: function (res) {
                        if (res.resultado) {
                            tabla.ajax.reload();
                            var modalEl = document.getElementById('modalComprobanteGrupo');
                            var modal = bootstrap.Modal.getInstance(modalEl);

                            btnGuardar.prop('disabled', false).html(originalText);

                            Swal.fire({
                                icon: "success",
                                title: "¡Guardado!",
                                text: "Grupo de comprobante guardado correctamente",
                                showConfirmButton: false,
                                timer: 1500,
                                toast: true,
                                position: 'top-end'
                            });
                            modal.hide();
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al guardar los datos",
                                confirmButtonText: "Entendido"
                            });
                        }
                    },
                    error: function () {
                        btnGuardar.prop('disabled', false).html(originalText);
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor",
                            confirmButtonText: "Entendido"
                        });
                    }
                });
            });

            // Botón recargar
            $('#btnRecargar').click(function () {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                tabla.ajax.reload(function () {
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                });
            });

            // Inicializar
            cargarBotonAgregar();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>