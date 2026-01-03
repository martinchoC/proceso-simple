<?php
// Configuración de la página
require_once __DIR__ . '/../../conexion.php';

$pageTitle = "Gestión de Sucursales";
$currentPage = 'sucursales';
$modudo_idx = 2;
$pagina_idx = 36; // ✅ CORREGIDO: ID de página para sucursales

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-store me-2"></i>Gestión de Sucursales
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Sucursales</li>
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
                                        <table id="tablaSucursales" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th>Nombre</th>
                                                    <th width="150">Tipo</th>
                                                    <th width="150">Localidad</th>
                                                    <th width="120">Teléfono</th>
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

            <!-- Modal para crear/editar sucursal -->
            <div class="modal fade" id="modalSucursal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Sucursal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formSucursal" class="needs-validation" novalidate>
                                <input type="hidden" id="sucursal_id" name="sucursal_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sucursal_nombre" class="form-label">Nombre de Sucursal *</label>
                                        <input type="text" class="form-control" id="sucursal_nombre" name="sucursal_nombre" 
                                               maxlength="100" required>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                        <div class="form-text">Máximo 100 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="sucursal_tipo_id" class="form-label">Tipo de Sucursal *</label>
                                        <select class="form-select" id="sucursal_tipo_id" name="sucursal_tipo_id" required>
                                            <option value="">Seleccionar tipo...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar un tipo</div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="descripcion" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                                  maxlength="255" rows="2"></textarea>
                                        <div class="form-text">Máximo 255 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="localidad_id" class="form-label">Localidad</label>
                                        <select class="form-select" id="localidad_id" name="localidad_id">
                                            <option value="">Seleccionar localidad...</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono" 
                                               maxlength="50">
                                        <div class="form-text">Máximo 50 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="direccion" class="form-label">Dirección</label>
                                        <textarea class="form-control" id="direccion" name="direccion" 
                                                  maxlength="255" rows="2"></textarea>
                                        <div class="form-text">Máximo 255 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               maxlength="100">
                                        <div class="invalid-feedback">Formato de email inválido</div>
                                        <div class="form-text">Máximo 100 caracteres</div>
                                    </div>
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
        const pagina_idx = <?php echo $pagina_idx; ?>; // ✅ Ya usa 36
        
        // Cargar tipos de sucursales para el select
        function cargarTiposSucursales(selectedId = null) {
            $.get('sucursales_ajax.php', {
                accion: 'obtener_tipos_sucursales_activos',
                empresa_idx: empresa_idx
            }, function(tipos){
                var select = $('#sucursal_tipo_id');
                select.empty();
                select.append('<option value="">Seleccionar tipo...</option>');
                
                $.each(tipos, function(index, tipo){
                    var selected = (selectedId && tipo.sucursal_tipo_id == selectedId) ? 'selected' : '';
                    select.append(`<option value="${tipo.sucursal_tipo_id}" ${selected}>${tipo.sucursal_tipo}</option>`);
                });
            }, 'json');
        }
        
        // Cargar localidades para el select
        function cargarLocalidades(selectedId = null) {
            $.get('sucursales_ajax.php', {
                accion: 'obtener_localidades_activas'
            }, function(localidades){
                var select = $('#localidad_id');
                select.empty();
                select.append('<option value="">Seleccionar localidad...</option>');
                
                $.each(localidades, function(index, localidad){
                    var selected = (selectedId && localidad.localidad_id == selectedId) ? 'selected' : '';
                    select.append(`<option value="${localidad.localidad_id}" ${selected}>${localidad.localidad}</option>`);
                });
            }, 'json');
        }
        
        // Configuración de DataTable
        var tabla = $('#tablaSucursales').DataTable({
            ajax: {
                url: 'sucursales_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx // ✅ Envía página_id=36
                },
                dataSrc: '',
                error: function(xhr, error, thrown) {
                    console.error('Error AJAX:', error);
                    console.error('Response:', xhr.responseText);
                    Swal.fire({
                        icon: "error",
                        title: "Error de carga",
                        text: "No se pudieron cargar los datos. Verifica la consola para más detalles.",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                { 
                    data: 'sucursal_id',
                    className: 'text-center fw-bold',
                    render: function(data) {
                        return `<span class="fw-medium">${data}</span>`;
                    }
                },
                { 
                    data: 'sucursal_nombre',
                    render: function(data, type, row) {
                        var descripcion = row.descripcion ? 
                            `<div class="text-muted small">${row.descripcion}</div>` : '';
                        var direccion = row.direccion ? 
                            `<div class="text-muted small mt-1"><i class="fas fa-map-marker-alt fa-xs me-1"></i>${row.direccion}</div>` : '';
                        
                        return `<div class="fw-medium">${data}</div>${descripcion}${direccion}`;
                    }
                },
                { 
                    data: 'sucursal_tipo',
                    render: function(data, type, row) {
                        return `<div class="fw-medium">${data}</div>`;
                    }
                },
                { 
                    data: 'localidad',
                    render: function(data, type, row) {
                        return data ? `<div class="fw-medium">${data}</div>` : 
                            '<span class="text-muted fst-italic">No especificada</span>';
                    }
                },
                { 
                    data: 'telefono',
                    render: function(data, type, row) {
                        if (!data) {
                            return '<span class="text-muted fst-italic">No especificado</span>';
                        }
                        var emailHtml = row.email ? 
                            `<div class="text-muted small"><i class="fas fa-envelope fa-xs me-1"></i>${row.email}</div>` : '';
                        
                        return `<div class="fw-medium">${data}</div>${emailHtml}`;
                    }
                },
                { 
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data) {
                        if (!data || !data.estado_registro) {
                            return '<span class="badge bg-secondary">Sin estado</span>';
                        }
                        
                        var estado = data.estado_registro;                        
                        
                        return `<span class="fw-medium">${estado}</span>`;
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
                                       data-id="${row.sucursal_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-sucursal-nombre="${row.sucursal_nombre}">
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
            $.get('sucursales_ajax.php', {
                accion: 'obtener_boton_agregar',
                pagina_idx: pagina_idx // ✅ Usa página_id=36
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
                        '<i class="fas fa-plus me-1"></i>Agregar Sucursal</button>'
                    );
                }
            }, 'json').fail(function() {
                // Si falla, usar botón por defecto
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Agregar Sucursal</button>'
                );
            });
        }

        // Manejador para botón "Agregar"
        $(document).on('click', '#btnNuevo', function(){
            resetModal();
            $('#modalLabel').text('Nueva Sucursal');
            cargarTiposSucursales();
            cargarLocalidades();
            
            var modal = new bootstrap.Modal(document.getElementById('modalSucursal'));
            modal.show();
            $('#sucursal_nombre').focus();
        });

        // Manejador para botones de acción dinámicos
        $(document).on('click', '.btn-accion', function(){
            var sucursalId = $(this).data('id');
            var accionJs = $(this).data('accion');
            var confirmable = $(this).data('confirmable');
            var sucursalNombre = $(this).data('sucursal-nombre');
            
            if (accionJs === 'editar') {
                cargarSucursalParaEditar(sucursalId);
            } else if (confirmable == 1) {
                Swal.fire({
                    title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                    html: `¿Está seguro de <strong>${accionJs}</strong> la sucursal <strong>"${sucursalNombre}"</strong>?`,
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
                        ejecutarAccion(sucursalId, accionJs, sucursalNombre);
                    }
                });
            } else {
                ejecutarAccion(sucursalId, accionJs, sucursalNombre);
            }
        });

        // Función para ejecutar cualquier acción del backend
        function ejecutarAccion(sucursalId, accionJs, sucursalNombre) {
            $.post('sucursales_ajax.php', {
                accion: 'ejecutar_accion',
                sucursal_id: sucursalId,
                accion_js: accionJs,
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx // ✅ Envía página_id=36
            }, function(res){
                if (res.success) {
                    tabla.ajax.reload();
                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Sucursal "${sucursalNombre}" actualizada correctamente`,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || `Error al ${accionJs} la sucursal`,
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para cargar sucursal en modal de edición
        function cargarSucursalParaEditar(sucursalId) {
            $.get('sucursales_ajax.php', {
                accion: 'obtener', 
                sucursal_id: sucursalId,
                empresa_idx: empresa_idx
            }, function(res){
                if(res && res.sucursal_id){
                    resetModal();
                    $('#sucursal_id').val(res.sucursal_id);
                    $('#sucursal_nombre').val(res.sucursal_nombre);
                    $('#descripcion').val(res.descripcion || '');
                    $('#direccion').val(res.direccion || '');
                    $('#telefono').val(res.telefono || '');
                    $('#email').val(res.email || '');
                    
                    cargarTiposSucursales(res.sucursal_tipo_id);
                    cargarLocalidades(res.localidad_id);
                    
                    $('#modalLabel').text('Editar Sucursal');
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalSucursal'));
                    modal.show();
                    
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al obtener datos de la sucursal",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para resetear el modal
        function resetModal() {
            $('#formSucursal')[0].reset();
            $('#sucursal_id').val('');
            $('#formSucursal').removeClass('was-validated');
        }

        // Validación del formulario
        $('#btnGuardar').click(function(){
            var form = document.getElementById('formSucursal');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            
            var id = $('#sucursal_id').val();
            var accionBackend = id ? 'editar' : 'agregar';
            var sucursalNombre = $('#sucursal_nombre').val().trim();
            var sucursalTipoId = $('#sucursal_tipo_id').val();
            var email = $('#email').val().trim();
            
            if (!sucursalNombre) {
                $('#sucursal_nombre').addClass('is-invalid');
                return false;
            }
            
            if (!sucursalTipoId) {
                $('#sucursal_tipo_id').addClass('is-invalid');
                return false;
            }
            
            // Validar email si se proporcionó
            if (email && !isValidEmail(email)) {
                $('#email').addClass('is-invalid');
                return false;
            }

            var btnGuardar = $(this);
            var originalText = btnGuardar.html();
            btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
            
            $.ajax({
                url: 'sucursales_ajax.php',
                type: 'POST',
                data: {
                    accion: accionBackend,
                    sucursal_id: id,
                    sucursal_nombre: sucursalNombre,
                    descripcion: $('#descripcion').val().trim(),
                    sucursal_tipo_id: sucursalTipoId,
                    localidad_id: $('#localidad_id').val() || null,
                    direccion: $('#direccion').val().trim(),
                    telefono: $('#telefono').val().trim(),
                    email: email,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx // ✅ Envía página_id=36
                },
                success: function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        var modalEl = document.getElementById('modalSucursal');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        
                        btnGuardar.prop('disabled', false).html(originalText);
                        
                        Swal.fire({                    
                            icon: "success",
                            title: "¡Guardado!",
                            text: "Sucursal guardada correctamente",
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

        // Función para validar email
        function isValidEmail(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

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