<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Tipos de Sucursales";
$currentPage = 'sucursales_tipos';
$modudo_idx = 2;
$pagina_idx = 43; // ID de página para tipos de sucursales (ajustar según tu BD)

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-store-alt me-2"></i>Tipos de Sucursales
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Sucursales</li>
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
                                        <table id="tablaSucursalesTipos" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="200">Tipo de Sucursal</th>
                                                    <th>Descripción</th>
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

            <!-- Modal para crear/editar tipo de sucursal -->
            <div class="modal fade" id="modalSucursalTipo" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Tipo de Sucursal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formSucursalTipo" class="needs-validation" novalidate>
                                <input type="hidden" id="sucursal_tipo_id" name="sucursal_tipo_id" />
                                <div class="mb-3">
                                    <label for="sucursal_tipo" class="form-label">Tipo de Sucursal *</label>
                                    <input type="text" class="form-control" id="sucursal_tipo" name="sucursal_tipo"
                                        maxlength="50" required>
                                    <div class="invalid-feedback">El tipo de sucursal es obligatorio</div>
                                    <div class="form-text">Máximo 50 caracteres</div>
                                </div>
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" maxlength="150"
                                        rows="3"></textarea>
                                    <div class="form-text">Máximo 150 caracteres</div>
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
            var tabla = $('#tablaSucursalesTipos').DataTable({
                ajax: {
                    url: 'sucursales_tipos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar',
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataSrc: ''
                },
                pageLength: 50, // REQUISITO: 50 registros iniciales
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]], // REQUISITO: paginación completa
                columns: [
                    {
                        data: 'sucursal_tipo_id',
                        className: 'text-center fw-bold',
                        render: function (data) {
                            return `<span class="fw-medium">${data}</span>`;
                        }
                    },
                    {
                        data: 'sucursal_tipo',
                        render: function (data, type, row) {
                            return `<div class="fw-medium">${data}</div>`;
                        }
                    },
                    {
                        data: 'descripcion',
                        render: function (data, type, row) {
                            if (!data || data.trim() === '') {
                                return '<span class="text-muted fst-italic">Sin descripción</span>';
                            }
                            return `<div class="text-muted small">${data}</div>`;
                        }
                    },
                    {
                        data: 'estado_info',
                        className: 'text-center',
                        render: function (data) {
                            if (!data || !data.estado_registro) {
                                return '<span class="badge bg-secondary">Sin estado</span>';
                            }

                            // Aplicar colores desde BD o usar default

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
                                       data-id="${row.sucursal_tipo_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-sucursal-tipo="${row.sucursal_tipo}">
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
                $.get('sucursales_tipos_ajax.php', {
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
                            '<i class="fas fa-plus me-1"></i>Agregar Tipo</button>'
                        );
                    }
                }, 'json');
            }

            // Manejador para botón "Agregar"
            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nuevo Tipo de Sucursal');

                var modal = new bootstrap.Modal(document.getElementById('modalSucursalTipo'));
                modal.show();
                $('#sucursal_tipo').focus();
            });

            // Manejador para botones de acción dinámicos
            $(document).on('click', '.btn-accion', function () {
                var sucursalTipoId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var sucursalTipo = $(this).data('sucursal-tipo');

                if (accionJs === 'editar') {
                    cargarSucursalTipoParaEditar(sucursalTipoId);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> el tipo de sucursal <strong>"${sucursalTipo}"</strong>?`,
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
                            ejecutarAccion(sucursalTipoId, accionJs, sucursalTipo);
                        }
                    });
                } else {
                    ejecutarAccion(sucursalTipoId, accionJs, sucursalTipo);
                }
            });

            // Función para ejecutar cualquier acción del backend
            function ejecutarAccion(sucursalTipoId, accionJs, sucursalTipo) {
                $.post('sucursales_tipos_ajax.php', {
                    accion: 'ejecutar_accion',
                    sucursal_tipo_id: sucursalTipoId,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                            text: res.message || `Tipo de sucursal "${sucursalTipo}" actualizado correctamente`,
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionJs} el tipo de sucursal`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para cargar tipo de sucursal en modal de edición
            function cargarSucursalTipoParaEditar(sucursalTipoId) {
                $.get('sucursales_tipos_ajax.php', {
                    accion: 'obtener',
                    sucursal_tipo_id: sucursalTipoId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.sucursal_tipo_id) {
                        resetModal();
                        $('#sucursal_tipo_id').val(res.sucursal_tipo_id);
                        $('#sucursal_tipo').val(res.sucursal_tipo);
                        $('#descripcion').val(res.descripcion || '');
                        $('#modalLabel').text('Editar Tipo de Sucursal');

                        var modal = new bootstrap.Modal(document.getElementById('modalSucursalTipo'));
                        modal.show();

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del tipo de sucursal",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para resetear el modal
            function resetModal() {
                $('#formSucursalTipo')[0].reset();
                $('#sucursal_tipo_id').val('');
                $('#formSucursalTipo').removeClass('was-validated');
            }

            // Validación del formulario
            $('#btnGuardar').click(function () {
                var form = document.getElementById('formSucursalTipo');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#sucursal_tipo_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var sucursalTipo = $('#sucursal_tipo').val().trim();
                var descripcion = $('#descripcion').val().trim();

                if (!sucursalTipo) {
                    $('#sucursal_tipo').addClass('is-invalid');
                    return false;
                }

                if (sucursalTipo.length > 50) {
                    $('#sucursal_tipo').addClass('is-invalid');
                    return false;
                }

                if (descripcion.length > 150) {
                    $('#descripcion').addClass('is-invalid');
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'sucursales_tipos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        sucursal_tipo_id: id,
                        sucursal_tipo: sucursalTipo,
                        descripcion: descripcion,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    success: function (res) {
                        if (res.resultado) {
                            tabla.ajax.reload();
                            var modalEl = document.getElementById('modalSucursalTipo');
                            var modal = bootstrap.Modal.getInstance(modalEl);

                            btnGuardar.prop('disabled', false).html(originalText);

                            Swal.fire({
                                icon: "success",
                                title: "¡Guardado!",
                                text: "Tipo de sucursal guardado correctamente",
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