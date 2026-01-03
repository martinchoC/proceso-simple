<?php
// Configuración de la página
require_once "conexion.php";

$pageTitle = "Gestión de Ubicaciones de Sucursales";
$currentPage = 'sucursales_ubicaciones';
$modudo_idx = 2;
$pagina_idx = 37; // ✅ ID de página para ubicaciones de sucursales

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-location-dot me-2"></i>Gestión de Ubicaciones de Sucursales
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="sucursales.php">Sucursales</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Ubicaciones</li>
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
                                        <!-- Filtros -->
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="filterSucursal" class="form-label">Filtrar por Sucursal</label>
                                                <select class="form-select form-select-sm" id="filterSucursal">
                                                    <option value="">Todas las sucursales</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="filterEstado" class="form-label">Filtrar por Estado</label>
                                                <select class="form-select form-select-sm" id="filterEstado">
                                                    <option value="">Todos los estados</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="filterBusqueda" class="form-label">Búsqueda general</label>
                                                <input type="text" class="form-control form-control-sm" id="filterBusqueda" placeholder="Buscar...">
                                            </div>
                                        </div>

                                        <!-- DataTable -->
                                        <table id="tablaSucursalesUbicaciones" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th>Sucursal</th>
                                                    <th width="120">Sección</th>
                                                    <th width="120">Estantería</th>
                                                    <th width="120">Estante</th>
                                                    <th width="150">Descripción</th>
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

            <!-- Modal para crear/editar ubicación -->
            <div class="modal fade" id="modalSucursalUbicacion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Ubicación de Sucursal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formSucursalUbicacion" class="needs-validation" novalidate>
                                <input type="hidden" id="sucursal_ubicacion_id" name="sucursal_ubicacion_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sucursal_id" class="form-label">Sucursal *</label>
                                        <select class="form-select" id="sucursal_id" name="sucursal_id" required>
                                            <option value="">Seleccionar sucursal...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar una sucursal</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="seccion" class="form-label">Sección *</label>
                                        <input type="text" class="form-control" id="seccion" name="seccion" 
                                               maxlength="50" required>
                                        <div class="invalid-feedback">La sección es obligatoria</div>
                                        <div class="form-text">Máximo 50 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="estanteria" class="form-label">Estantería *</label>
                                        <input type="text" class="form-control" id="estanteria" name="estanteria" 
                                               maxlength="50" required>
                                        <div class="invalid-feedback">La estantería es obligatoria</div>
                                        <div class="form-text">Máximo 50 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="estante" class="form-label">Estante *</label>
                                        <input type="text" class="form-control" id="estante" name="estante" 
                                               maxlength="50" required>
                                        <div class="invalid-feedback">El estante es obligatorio</div>
                                        <div class="form-text">Máximo 50 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="estado_registro_id" class="form-label">Estado</label>
                                        <select class="form-select" id="estado_registro_id" name="estado_registro_id">
                                            <option value="">Seleccionar estado...</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="descripcion" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                                  maxlength="255" rows="3"></textarea>
                                        <div class="form-text">Máximo 255 caracteres</div>
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
        const pagina_idx = <?php echo $pagina_idx; ?>; // 37
        
        // Variables para mantener el estado de filtros
        let currentFilters = {
            sucursal: '',
            estado: '',
            busqueda: ''
        };
        
        // Cargar sucursales para el select
        function cargarSucursales(selectedId = null) {
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener_sucursales_activas',
                empresa_idx: empresa_idx
            }, function(sucursales){
                var select = $('#sucursal_id');
                var filterSelect = $('#filterSucursal');
                
                // Select del formulario
                select.empty();
                select.append('<option value="">Seleccionar sucursal...</option>');
                
                // Select del filtro
                filterSelect.empty();
                filterSelect.append('<option value="">Todas las sucursales</option>');
                
                $.each(sucursales, function(index, sucursal){
                    var optionText = sucursal.sucursal_nombre;
                    if (sucursal.localidad) {
                        optionText += ` (${sucursal.localidad})`;
                    }
                    
                    // Para el formulario
                    var selected = (selectedId && sucursal.sucursal_id == selectedId) ? 'selected' : '';
                    select.append(`<option value="${sucursal.sucursal_id}" ${selected}>${optionText}</option>`);
                    
                    // Para el filtro
                    filterSelect.append(`<option value="${sucursal.sucursal_id}">${optionText}</option>`);
                });
            }, 'json');
        }
        
        // Cargar estados para el select
        function cargarEstados(selectedId = null) {
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener_estados_registro'
            }, function(estados){
                var select = $('#estado_registro_id');
                var filterSelect = $('#filterEstado');
                
                // Select del formulario
                select.empty();
                select.append('<option value="">Seleccionar estado...</option>');
                
                // Select del filtro
                filterSelect.empty();
                filterSelect.append('<option value="">Todos los estados</option>');
                
                $.each(estados, function(index, estado){
                    // Para el formulario
                    var selected = (selectedId && estado.estado_registro_id == selectedId) ? 'selected' : '';
                    select.append(`<option value="${estado.estado_registro_id}" ${selected}>${estado.estado_registro}</option>`);
                    
                    // Para el filtro
                    filterSelect.append(`<option value="${estado.estado_registro_id}">${estado.estado_registro}</option>`);
                });
            }, 'json');
        }
        
        // Configuración de DataTable con exportación
        var tabla = $('#tablaSucursalesUbicaciones').DataTable({
            ajax: {
                url: 'sucursales_ubicaciones_ajax.php',
                type: 'GET',
                data: function(d) {
                    return {
                        accion: 'listar',
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx,
                        filter_sucursal: currentFilters.sucursal,
                        filter_estado: currentFilters.estado,
                        filter_busqueda: currentFilters.busqueda
                    };
                },
                dataSrc: ''
            },
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                 '<"row mt-3"<"col-sm-12"B>>',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel me-1"></i>Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: ':visible',
                        modifier: {
                            page: 'all'
                        }
                    },
                    title: 'Sucursales_Ubicaciones'
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf me-1"></i>PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: ':visible',
                        modifier: {
                            page: 'all'
                        }
                    },
                    title: 'Sucursales_Ubicaciones',
                    orientation: 'landscape',
                    pageSize: 'A4'
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv me-1"></i>CSV',
                    className: 'btn btn-info btn-sm',
                    exportOptions: {
                        columns: ':visible',
                        modifier: {
                            page: 'all'
                        }
                    },
                    title: 'Sucursales_Ubicaciones'
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print me-1"></i>Imprimir',
                    className: 'btn btn-secondary btn-sm',
                    exportOptions: {
                        columns: ':visible',
                        modifier: {
                            page: 'all'
                        }
                    },
                    title: 'Sucursales_Ubicaciones'
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns me-1"></i>Columnas',
                    className: 'btn btn-warning btn-sm'
                }
            ],
            columns: [
                { 
                    data: 'sucursal_ubicacion_id',
                    className: 'text-center fw-bold',
                    render: function(data) {
                        return `<span class="fw-medium">${data}</span>`;
                    }
                },
                { 
                    data: 'sucursal_info',
                    render: function(data) {
                        if (!data) return '<span class="text-muted fst-italic">No especificada</span>';
                        
                        var localidad = data.localidad ? 
                            `<div class="text-muted small"><i class="fas fa-map-marker-alt fa-xs me-1"></i>${data.localidad}</div>` : '';
                        
                        return `<div class="fw-medium">${data.sucursal_nombre}</div>${localidad}`;
                    }
                },
                { 
                    data: 'seccion',
                    render: function(data) {
                        return data ? `<div class="fw-medium">${data}</div>` : 
                            '<span class="text-muted fst-italic">No especificada</span>';
                    }
                },
                { 
                    data: 'estanteria',
                    render: function(data) {
                        return data ? `<div class="fw-medium">${data}</div>` : 
                            '<span class="text-muted fst-italic">No especificada</span>';
                    }
                },
                { 
                    data: 'estante',
                    render: function(data) {
                        return data ? `<div class="fw-medium">${data}</div>` : 
                            '<span class="text-muted fst-italic">No especificado</span>';
                    }
                },
                { 
                    data: 'descripcion',
                    render: function(data) {
                        return data ? `<div class="small">${data}</div>` : 
                            '<span class="text-muted fst-italic">Sin descripción</span>';
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
                                       data-id="${row.sucursal_ubicacion_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-info="${row.seccion} - ${row.estanteria} - ${row.estante}">
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
            order: [[0, 'desc']],
            responsive: true,
            stateSave: true, // ✅ Mantiene el estado de la tabla (filtros, paginación)
            stateDuration: -1, // ✅ Persiste en localStorage (siempre)
            createdRow: function(row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                    $(row).addClass('table-warning');
                }
            },
            initComplete: function() {
                // Aplicar filtros guardados del stateSave
                this.api().state.loaded(function(state) {
                    if (state && state.search) {
                        $('#filterBusqueda').val(state.search.search);
                        currentFilters.busqueda = state.search.search;
                    }
                });
            }
        });

        // Cargar botón Agregar dinámicamente
        function cargarBotonAgregar() {
            $.get('sucursales_ubicaciones_ajax.php', {
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
                        '<i class="fas fa-plus me-1"></i>Agregar Ubicación</button>'
                    );
                }
            }, 'json').fail(function() {
                // Si falla, usar botón por defecto
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Agregar Ubicación</button>'
                );
            });
        }

        // Manejadores de filtros
        $('#filterSucursal').change(function() {
            currentFilters.sucursal = $(this).val();
            tabla.ajax.reload();
        });

        $('#filterEstado').change(function() {
            currentFilters.estado = $(this).val();
            tabla.ajax.reload();
        });

        $('#filterBusqueda').on('keyup', function() {
            currentFilters.busqueda = $(this).val();
            clearTimeout($(this).data('timeout'));
            
            // Debounce para evitar múltiples llamadas
            $(this).data('timeout', setTimeout(function() {
                tabla.ajax.reload();
            }, 500));
        });

        // Manejador para botón "Agregar"
        $(document).on('click', '#btnNuevo', function(){
            resetModal();
            $('#modalLabel').text('Nueva Ubicación');
            cargarSucursales();
            cargarEstados();
            
            var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
            modal.show();
            $('#seccion').focus();
        });

        // Manejador para botones de acción dinámicos
        $(document).on('click', '.btn-accion', function(){
            var sucursalUbicacionId = $(this).data('id');
            var accionJs = $(this).data('accion');
            var confirmable = $(this).data('confirmable');
            var info = $(this).data('info');
            
            if (accionJs === 'editar') {
                cargarUbicacionParaEditar(sucursalUbicacionId);
            } else if (confirmable == 1) {
                Swal.fire({
                    title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                    html: `¿Está seguro de <strong>${accionJs}</strong> la ubicación <strong>"${info}"</strong>?`,
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
                        ejecutarAccion(sucursalUbicacionId, accionJs, info);
                    }
                });
            } else {
                ejecutarAccion(sucursalUbicacionId, accionJs, info);
            }
        });

        // Función para ejecutar cualquier acción del backend
        function ejecutarAccion(sucursalUbicacionId, accionJs, info) {
            $.post('sucursales_ubicaciones_ajax.php', {
                accion: 'ejecutar_accion',
                sucursal_ubicacion_id: sucursalUbicacionId,
                accion_js: accionJs,
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            }, function(res){
                if (res.success) {
                    tabla.ajax.reload();
                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Ubicación "${info}" actualizada correctamente`,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || `Error al ${accionJs} la ubicación`,
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para cargar ubicación en modal de edición
        function cargarUbicacionParaEditar(sucursalUbicacionId) {
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener', 
                sucursal_ubicacion_id: sucursalUbicacionId,
                empresa_idx: empresa_idx
            }, function(res){
                if(res && res.sucursal_ubicacion_id){
                    resetModal();
                    $('#sucursal_ubicacion_id').val(res.sucursal_ubicacion_id);
                    $('#seccion').val(res.seccion || '');
                    $('#estanteria').val(res.estanteria || '');
                    $('#estante').val(res.estante || '');
                    $('#descripcion').val(res.descripcion || '');
                    
                    cargarSucursales(res.sucursal_id);
                    cargarEstados(res.tabla_estado_registro_id);
                    
                    $('#modalLabel').text('Editar Ubicación');
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
                    modal.show();
                    
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al obtener datos de la ubicación",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para resetear el modal
        function resetModal() {
            $('#formSucursalUbicacion')[0].reset();
            $('#sucursal_ubicacion_id').val('');
            $('#formSucursalUbicacion').removeClass('was-validated');
        }

        // Validación del formulario
        $('#btnGuardar').click(function(){
            var form = document.getElementById('formSucursalUbicacion');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            
            var id = $('#sucursal_ubicacion_id').val();
            var accionBackend = id ? 'editar' : 'agregar';
            var sucursalId = $('#sucursal_id').val();
            var seccion = $('#seccion').val().trim();
            var estanteria = $('#estanteria').val().trim();
            var estante = $('#estante').val().trim();
            
            if (!sucursalId) {
                $('#sucursal_id').addClass('is-invalid');
                return false;
            }
            
            if (!seccion) {
                $('#seccion').addClass('is-invalid');
                return false;
            }
            
            if (!estanteria) {
                $('#estanteria').addClass('is-invalid');
                return false;
            }
            
            if (!estante) {
                $('#estante').addClass('is-invalid');
                return false;
            }

            var btnGuardar = $(this);
            var originalText = btnGuardar.html();
            btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
            
            $.ajax({
                url: 'sucursales_ubicaciones_ajax.php',
                type: 'POST',
                data: {
                    accion: accionBackend,
                    sucursal_ubicacion_id: id,
                    sucursal_id: sucursalId,
                    seccion: seccion,
                    estanteria: estanteria,
                    estante: estante,
                    descripcion: $('#descripcion').val().trim(),
                    estado_registro_id: $('#estado_registro_id').val() || 1,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                success: function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        var modalEl = document.getElementById('modalSucursalUbicacion');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        
                        btnGuardar.prop('disabled', false).html(originalText);
                        
                        Swal.fire({                    
                            icon: "success",
                            title: "¡Guardado!",
                            text: "Ubicación guardada correctamente",
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
        cargarSucursales();
        cargarEstados();
        cargarBotonAgregar();
    });
    </script>
    <!-- DataTables Buttons y Exportación -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>