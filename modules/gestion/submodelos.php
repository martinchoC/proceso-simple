<?php
// Configuración de la página
require_once __DIR__ . '/../../conexion.php';

$pageTitle = "Gestión de Submodelos";
$currentPage = 'submodelos';
$modudo_idx = 2;
$pagina_idx = 42; // ID de página para submodelos (ajustar según tu BD)

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-layer-group me-2"></i>Gestión de Submodelos
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Submodelos</li>
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
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <!-- DataTable -->
                                        <table id="tablaSubmodelos" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th>Nombre del Submodelo</th>
                                                    <th width="150">Modelo</th>
                                                    <th width="150">Marca</th>
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

            <!-- Modal para crear/editar submodelo -->
            <div class="modal fade" id="modalSubmodelo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Submodelo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formSubmodelo" class="needs-validation" novalidate>
                                <input type="hidden" id="submodelo_id" name="submodelo_id" />
                                <div class="mb-3">
                                    <label for="modelo_id" class="form-label">Modelo *</label>
                                    <select class="form-select" id="modelo_id" name="modelo_id" required>
                                        <option value="">Seleccionar modelo...</option>
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar un modelo</div>
                                </div>
                                <div class="mb-3">
                                    <label for="submodelo_nombre" class="form-label">Nombre del Submodelo *</label>
                                    <input type="text" class="form-control" id="submodelo_nombre" name="submodelo_nombre" 
                                           maxlength="100" required>
                                    <div class="invalid-feedback">El nombre del submodelo es obligatorio</div>
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
    $(document).ready(function(){
        // Variables de contexto MULTIEMPRESA
        const empresa_idx = 2;
        const pagina_idx = <?php echo $pagina_idx; ?>;
        
        // Cargar modelos para el select
        function cargarModelos(selectedId = null) {
            $.get('submodelos_ajax.php', {
                accion: 'obtener_modelos_activos',
                empresa_idx: empresa_idx
            }, function(modelos){
                var select = $('#modelo_id');
                select.empty();
                select.append('<option value="">Seleccionar modelo...</option>');
                
                $.each(modelos, function(index, modelo){
                    var selected = (selectedId && modelo.modelo_id == selectedId) ? 'selected' : '';
                    select.append(`<option value="${modelo.modelo_id}" ${selected}>${modelo.marca_nombre} - ${modelo.modelo_nombre}</option>`);
                });
            }, 'json');
        }
        
        // Configuración de DataTable
        var tabla = $('#tablaSubmodelos').DataTable({
            ajax: {
                url: 'submodelos_ajax.php',
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
                    data: 'submodelo_id',
                    className: 'text-center fw-bold',
                    render: function(data) {
                        return `<span class="fw-medium">${data}</span>`;
                    }
                },
                { 
                    data: 'submodelo_nombre',
                    render: function(data, type, row) {
                        return `<div class="fw-medium">${data}</div>`;
                    }
                },
                { 
                    data: 'modelo_nombre',
                    render: function(data, type, row) {
                        return `<div class="fw-medium">${data}</div>`;
                    }
                },
                { 
                    data: 'marca_nombre',
                    render: function(data, type, row) {
                        return `<div class="fw-medium">${data}</div>`;
                    }
                },
                { 
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data) {
                        if (!data || !data.estado_registro) {
                            return '<span class="fw-medium">Sin estado</span>';
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
                    render: function(data, type, row) {
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
                                       data-id="${row.submodelo_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-submodelo-nombre="${row.submodelo_nombre}">
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
            createdRow: function(row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                    $(row).addClass('table-warning');
                }
            }
        });

        // Cargar botón Agregar dinámicamente
        function cargarBotonAgregar() {
            $.get('submodelos_ajax.php', {
                accion: 'obtener_boton_agregar',
                pagina_idx: pagina_idx
            }, function(botonAgregar){
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
                        '<i class="fas fa-plus me-1"></i>Agregar Submodelo</button>'
                    );
                }
            }, 'json');
        }

        // Manejador para botón "Agregar"
        $(document).on('click', '#btnNuevo', function(){
            resetModal();
            $('#modalLabel').text('Nuevo Submodelo');
            cargarModelos();
            
            var modal = new bootstrap.Modal(document.getElementById('modalSubmodelo'));
            modal.show();
            $('#submodelo_nombre').focus();
        });

        // Manejador para botones de acción dinámicos
        $(document).on('click', '.btn-accion', function(){
            var submodeloId = $(this).data('id');
            var accionJs = $(this).data('accion');
            var confirmable = $(this).data('confirmable');
            var submodeloNombre = $(this).data('submodelo-nombre');
            
            if (accionJs === 'editar') {
                cargarSubmodeloParaEditar(submodeloId);
            } else if (confirmable == 1) {
                Swal.fire({
                    title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                    html: `¿Está seguro de <strong>${accionJs}</strong> el submodelo <strong>"${submodeloNombre}"</strong>?`,
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
                        ejecutarAccion(submodeloId, accionJs, submodeloNombre);
                    }
                });
            } else {
                ejecutarAccion(submodeloId, accionJs, submodeloNombre);
            }
        });

        // Función para ejecutar cualquier acción del backend
        function ejecutarAccion(submodeloId, accionJs, submodeloNombre) {
            $.post('submodelos_ajax.php', {
                accion: 'ejecutar_accion',
                submodelo_id: submodeloId,
                accion_js: accionJs,
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            }, function(res){
                if (res.success) {
                    tabla.ajax.reload();
                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Submodelo "${submodeloNombre}" actualizado correctamente`,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || `Error al ${accionJs} el submodelo`,
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para cargar submodelo en modal de edición
        function cargarSubmodeloParaEditar(submodeloId) {
            $.get('submodelos_ajax.php', {
                accion: 'obtener', 
                submodelo_id: submodeloId,
                empresa_idx: empresa_idx
            }, function(res){
                if(res && res.submodelo_id){
                    resetModal();
                    $('#submodelo_id').val(res.submodelo_id);
                    $('#submodelo_nombre').val(res.submodelo_nombre);
                    cargarModelos(res.modelo_id);
                    $('#modalLabel').text('Editar Submodelo');
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalSubmodelo'));
                    modal.show();
                    
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al obtener datos del submodelo",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para resetear el modal
        function resetModal() {
            $('#formSubmodelo')[0].reset();
            $('#submodelo_id').val('');
            $('#formSubmodelo').removeClass('was-validated');
        }

        // Validación del formulario
        $('#btnGuardar').click(function(){
            var form = document.getElementById('formSubmodelo');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            
            var id = $('#submodelo_id').val();
            var accionBackend = id ? 'editar' : 'agregar';
            var submodeloNombre = $('#submodelo_nombre').val().trim();
            var modeloId = $('#modelo_id').val();
            
            if (!submodeloNombre) {
                $('#submodelo_nombre').addClass('is-invalid');
                return false;
            }
            
            if (!modeloId) {
                $('#modelo_id').addClass('is-invalid');
                return false;
            }

            var btnGuardar = $(this);
            var originalText = btnGuardar.html();
            btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
            
            $.ajax({
                url: 'submodelos_ajax.php',
                type: 'POST',
                data: {
                    accion: accionBackend,
                    submodelo_id: id,
                    submodelo_nombre: submodeloNombre,
                    modelo_id: modeloId,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                success: function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        var modalEl = document.getElementById('modalSubmodelo');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        
                        btnGuardar.prop('disabled', false).html(originalText);
                        
                        Swal.fire({                    
                            icon: "success",
                            title: "¡Guardado!",
                            text: "Submodelo guardado correctamente",
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
                error: function() {
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
        $('#btnRecargar').click(function(){
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            tabla.ajax.reload(function(){
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