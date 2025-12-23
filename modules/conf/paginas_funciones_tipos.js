// Funciones JavaScript para la gestión de funciones por tipo de tabla

// Inicializar DataTable
function inicializarDataTable() {
    return $('#tablaFunciones').DataTable({
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'portrait',
                pageSize: 'A4',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            }
        ],
        ajax: {
            url: 'paginas_funciones_tipos_ajax.php',
            type: 'GET',
            data: function(d) {
                return {
                    accion: 'listar',
                    tabla_tipo_id: $('#filtro_tabla_tipo').val(),
                    estado_registro: $('#filtro_estado').val()
                };
            },
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar funciones...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron funciones",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ funciones",
            "infoEmpty": "Mostrando 0 a 0 de 0 funciones",
            "infoFiltered": "(filtrado de _MAX_ funciones totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { 
                data: 'pagina_funcion_id',
                className: "text-center"
            },
            { 
                data: 'tabla_tipo',
                render: function(data, type, row) {
                    return data ? `<strong>${data}</strong>` : '<span class="text-danger">No definido</span>';
                }
            },
            { 
                data: null,
                render: function(data) {
                    var iconoHtml = '';
                    var colorStyle = '';
                    var colorBadge = '';
                    
                    // Icono
                    if (data.icono_clase) {
                        iconoHtml = `<i class="${data.icono_clase} me-2"></i>`;
                    }
                    
                    // Color
                    if (data.color_clase) {
                        colorStyle = `style="color: ${data.color_clase};"`;
                        colorBadge = `<span class="color-badge" style="background-color: ${data.color_clase};"></span>`;
                    } else if (data.nombre_color) {
                        colorBadge = `<span class="color-badge bg-${data.bg_clase || 'secondary'}"></span>`;
                    }
                    
                    return `<div class="funcion-con-icono" ${colorStyle}>
                                ${colorBadge}
                                ${iconoHtml}
                                <div>
                                    <strong>${data.nombre_funcion}</strong>
                                    ${data.descripcion ? 
                                        `<br><small class="text-muted">${data.descripcion}</small>` : ''}
                                    ${data.funcion_estandar_nombre ? 
                                        `<br><small class="badge bg-info">${data.funcion_estandar_nombre}</small>` : ''}
                                </div>
                            </div>`;
                }
            },
            { 
                data: 'accion_js',
                render: function(data) {
                    return data ? `<code class="bg-light p-1 rounded">${data}</code>` : 
                                 '<span class="text-muted fst-italic">No definida</span>';
                }
            },
            { 
                data: null,
                render: function(data) {
                    var origenHtml = data.estado_origen_nombre ? 
                        `${data.estado_origen_nombre} <small class="text-muted">(${data.codigo_origen || data.valor_origen})</small>` : 
                        `Estado: ${data.tabla_estado_registro_origen_id}`;
                    
                    var destinoHtml = data.estado_destino_nombre ? 
                        `${data.estado_destino_nombre} <small class="text-muted">(${data.codigo_destino || data.valor_destino})</small>` : 
                        `Estado: ${data.tabla_estado_registro_destino_id}`;
                    
                    return `<div class="estado-origen-destino">
                                <span class="estado-origen" title="Estado origen">${origenHtml}</span>
                                <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                <span class="estado-destino" title="Estado destino">${destinoHtml}</span>
                            </div>`;
                }
            },
            { 
                data: 'orden',
                className: "text-center",
                render: function(data) {
                    return `<span class="badge bg-secondary rounded-pill">${data}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var estadoTexto = data.tabla_estado_registro_id == 1 ? 
                        '<span class="badge bg-success badge-estado">Activo</span>' : 
                        '<span class="badge bg-secondary badge-estado">Inactivo</span>';
                    
                    var botonEstado = 
                        `<div class="form-check form-switch d-inline-block">
                            <input class="form-check-input toggle-estado"
                                type="checkbox" 
                                data-funcion-id="${data.pagina_funcion_id}" 
                                ${data.tabla_estado_registro_id == 1 ? 'checked' : ''}>
                        </div>`;
                    
                    return `<div class="d-flex flex-column align-items-center">                                            
                                ${botonEstado}
                                <small class="mt-1">${estadoTexto}</small>
                            </div>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var botonEditar = data.tabla_estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-primary btnEditar" title="Editar">
                            <i class="fa fa-edit"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                            <i class="fa fa-edit"></i>
                         </button>`;
                    
                    var botonEliminar = data.tabla_estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                            <i class="fa fa-trash"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-secondary" title="Eliminar no disponible" disabled>
                            <i class="fa fa-trash"></i>
                         </button>`;
                    
                    var botonInfo = data.funcion_estandar_id > 1 ? 
                        `<button class="btn btn-sm btn-info btnVerEst" title="Ver función estándar">
                            <i class="fa fa-info-circle"></i>
                         </button>` : '';
                    
                    return `<div class="d-flex align-items-center justify-content-center gap-2">
                                ${botonEditar} ${botonInfo} ${botonEliminar}
                            </div>`;
                }
            }
        ],
        createdRow: function(row, data, dataIndex) {
            if (data.tabla_estado_registro_id != 1) {
                $(row).addClass('table-secondary');
                $(row).find('td').css('color', '#6c757d');
            }
        },
        drawCallback: function() {
            // Eventos para los botones de la tabla
            bindTableEvents();
        }
    });
}

// Cargar combos de filtros y formulario
function cargarCombos() {
    // Cargar tipos de tabla
    $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener_tipos_tabla'}, function(res){
        var html = '<option value="">Todos los tipos</option>';
        res.forEach(function(tipo){
            html += `<option value="${tipo.tabla_tipo_id}">${tipo.tabla_tipo}</option>`;
        });
        $('#filtro_tabla_tipo').html(html);
        $('#tabla_id').html('<option value="">Seleccionar tipo</option>' + html.replace('Todos los tipos', ''));
    }, 'json');
    
    // Cargar estados registro
    $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener_estados'}, function(res){
        var htmlOrigen = '<option value="">Seleccionar estado</option>';
        var htmlDestino = '<option value="">Seleccionar estado</option>';
        
        res.forEach(function(estado){
            var optionText = estado.estado_registro;
            if (estado.codigo_estandar) {
                optionText += ` (${estado.codigo_estandar})`;
            } else if (estado.valor_estandar !== null) {
                optionText += ` (${estado.valor_estandar})`;
            }
            
            var option = `<option value="${estado.estado_registro_id}">${optionText}</option>`;
            htmlOrigen += option;
            htmlDestino += option;
        });
        
        $('#tabla_estado_registro_origen_id').html(htmlOrigen);
        $('#tabla_estado_registro_destino_id').html(htmlDestino);
    }, 'json');
    
    // Cargar iconos
    $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener_iconos'}, function(res){
        var html = '<option value="">Sin icono</option>';
        res.forEach(function(icono){
            html += `<option value="${icono.icono_id}" data-clase="${icono.icono_clase}">
                        <i class="${icono.icono_clase}"></i> ${icono.icono_nombre}
                     </option>`;
        });
        $('#icono_id').html(html);
        
        // Aplicar iconos a las opciones
        $('#icono_id option').each(function() {
            var $option = $(this);
            var iconoClase = $option.data('clase');
            if (iconoClase) {
                $option.html(`<i class="${iconoClase} me-2"></i> ${$option.text().split(' ').slice(1).join(' ')}`);
            }
        });
    }, 'json');
    
    // Cargar colores
    $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener_colores'}, function(res){
        var html = '<option value="">Predeterminado</option>';
        res.forEach(function(color){
            var style = color.color_clase ? `style="color: ${color.color_clase}"` : '';
            html += `<option value="${color.color_id}" ${style}>
                        <span class="color-badge" style="background-color: ${color.color_clase || '#6c757d'};"></span>
                        ${color.nombre_color}
                     </option>`;
        });
        $('#color_id').html(html);
        
        // Aplicar estilos a las opciones
        $('#color_id option').each(function() {
            var $option = $(this);
            var contenido = $option.html();
            if (contenido.includes('color-badge')) {
                $option.html(contenido);
            }
        });
    }, 'json');
}

// Cargar funciones estándar por tipo
function cargarFuncionesEstandar(tipo_id) {
    $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener_funciones_estandar', tipo_id: tipo_id}, function(res){
        var html = '<option value="">Personalizada</option>';
        res.forEach(function(funcion){
            var desc = funcion.descripcion ? funcion.descripcion.substring(0, 50) + '...' : '';
            html += `<option value="${funcion.funcion_estandar_id}" 
                        data-descripcion="${funcion.descripcion || ''}" 
                        data-accion="${funcion.accion_js || ''}"
                        title="${desc}">
                        ${funcion.nombre}
                     </option>`;
        });
        $('#funcion_estandar_id').html(html);
        
        // Auto-completar cuando se selecciona una función estándar
        $('#funcion_estandar_id').change(function(){
            var selected = $(this).find('option:selected');
            var descripcion = selected.data('descripcion');
            var accion = selected.data('accion');
            
            if (descripcion && !$('#descripcion').val()) {
                $('#descripcion').val(descripcion);
            }
            if (accion && !$('#accion_js').val()) {
                $('#accion_js').val(accion);
            }
        });
    }, 'json');
}

// Abrir modal para nueva función
function abrirModalNuevo() {
    $('#formFuncion')[0].reset();
    $('#pagina_funcion_id').val('');
    $('#modalLabel').text('Nueva Función');
    $('#estado_registro').prop('checked', true);
    $('#orden').val(0);
    $('#tabla_estado_registro_origen_id').val('');
    $('#tabla_estado_registro_destino_id').val('');
    $('#icono_id').val('');
    $('#color_id').val('');
    $('#funcion_estandar_id').val('');
    
    // Cargar funciones estándar si ya hay un tipo seleccionado
    if ($('#tabla_id').val()) {
        cargarFuncionesEstandar($('#tabla_id').val());
    }
    
    var modal = new bootstrap.Modal(document.getElementById('modalFuncion'));
    modal.show();
}

// Guardar función (crear o editar)
function guardarFuncion() {
    var form = document.getElementById('formFuncion');
    
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    
    var id = $('#pagina_funcion_id').val();
    var accion = id ? 'editar' : 'agregar';
    var formData = $('#formFuncion').serializeArray();
    
    // Agregar estado del checkbox
    formData.push({
        name: 'estado_registro',
        value: $('#estado_registro').is(':checked') ? 1 : 0
    });
    
    if (id) {
        formData.push({name: 'pagina_funcion_id', value: id});
    }
    
    formData.push({name: 'accion', value: accion});
    
    $.ajax({
        url: 'paginas_funciones_tipos_ajax.php',
        type: 'POST',
        data: $.param(formData),
        dataType: 'json',
        success: function(res) {
            if(res.resultado) {
                var tabla = $('#tablaFunciones').DataTable();
                tabla.ajax.reload(null, false);
                
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalFuncion'));
                modal.hide();
                
                $('#formFuncion')[0].reset();
                form.classList.remove('was-validated');
                
                Swal.fire({
                    icon: "success",
                    title: "¡Éxito!",
                    text: id ? "Función actualizada correctamente" : "Función creada correctamente",
                    showConfirmButton: false,
                    timer: 1500
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || "Error al guardar los datos"
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "Error de conexión con el servidor: " + error
            });
        }
    });
}

// Aplicar filtros a la tabla
function aplicarFiltros(tabla) {
    tabla.ajax.reload();
}

// Limpiar filtros
function limpiarFiltros() {
    $('#filtro_tabla_tipo, #filtro_estado').val('');
    var tabla = $('#tablaFunciones').DataTable();
    tabla.ajax.reload();
}

// Vincular eventos de la tabla
function bindTableEvents() {
    // Cambiar estado
    $(document).on('change', '.toggle-estado', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var funcionId = $(this).data('funcion-id');
        var isChecked = $(this).is(':checked');
        var nuevoEstado = isChecked ? 1 : 0;
        var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';
        
        Swal.fire({
            title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} función?`,
            text: `Está a punto de ${accionTexto} esta función`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Sí, ${accionTexto}`,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('paginas_funciones_tipos_ajax.php', {
                    accion: 'cambiar_estado', 
                    pagina_funcion_id: funcionId,
                    nuevo_estado: nuevoEstado
                }, function(res){
                    if(res.resultado){
                        var tabla = $('#tablaFunciones').DataTable();
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: `Función ${accionTexto}da correctamente`,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        $(this).prop('checked', !isChecked);
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionTexto} la función`
                        });
                    }
                }, 'json');
            } else {
                $(this).prop('checked', !isChecked);
            }
        });
    });
    
    // Editar función
    $(document).on('click', '.btnEditar', function(){
        var tabla = $('#tablaFunciones').DataTable();
        var data = tabla.row($(this).parents('tr')).data();
        
        if (data.tabla_estado_registro_id != 1) {
            Swal.fire({
                icon: "warning",
                title: "Función inactiva",
                text: "No se puede editar una función inactiva. Active la función primero.",
                showConfirmButton: false,
                timer: 2000
            });
            return false;
        }
        
        $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener', pagina_funcion_id: data.pagina_funcion_id}, function(res){
            if(res){
                // Llenar el formulario
                $('#pagina_funcion_id').val(res.pagina_funcion_id);
                $('#tabla_id').val(res.tabla_id);
                $('#nombre_funcion').val(res.nombre_funcion);
                $('#accion_js').val(res.accion_js || '');
                $('#descripcion').val(res.descripcion || '');
                $('#tabla_estado_registro_origen_id').val(res.tabla_estado_registro_origen_id);
                $('#tabla_estado_registro_destino_id').val(res.tabla_estado_registro_destino_id);
                $('#orden').val(res.orden);
                $('#estado_registro').prop('checked', res.tabla_estado_registro_id == 1);
                
                // Cargar iconos y seleccionar el correcto
                $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener_iconos'}, function(iconos){
                    var html = '<option value="">Sin icono</option>';
                    iconos.forEach(function(icono){
                        var selected = icono.icono_id == res.icono_id ? 'selected' : '';
                        var iconHtml = `<i class="${icono.icono_clase} me-2"></i> ${icono.icono_nombre}`;
                        html += `<option value="${icono.icono_id}" ${selected} data-clase="${icono.icono_clase}">${iconHtml}</option>`;
                    });
                    $('#icono_id').html(html);
                }, 'json');
                
                // Cargar colores y seleccionar el correcto
                $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener_colores'}, function(colores){
                    var html = '<option value="">Predeterminado</option>';
                    colores.forEach(function(color){
                        var selected = color.color_id == res.color_id ? 'selected' : '';
                        var colorBadge = color.color_clase ? 
                            `<span class="color-badge" style="background-color: ${color.color_clase};"></span>` : 
                            `<span class="color-badge bg-secondary"></span>`;
                        html += `<option value="${color.color_id}" ${selected}>${colorBadge} ${color.nombre_color}</option>`;
                    });
                    $('#color_id').html(html);
                }, 'json');
                
                // Cargar funciones estándar para este tipo y seleccionar
                if (res.tabla_id) {
                    cargarFuncionesEstandar(res.tabla_id);
                    setTimeout(function() {
                        $('#funcion_estandar_id').val(res.funcion_estandar_id || '');
                    }, 300);
                }
                
                $('#modalLabel').text('Editar Función');
                var modal = new bootstrap.Modal(document.getElementById('modalFuncion'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la función"
                });
            }
        }, 'json');
    });
    
    // Eliminar función
    $(document).on('click', '.btnEliminar', function(){
        var tabla = $('#tablaFunciones').DataTable();
        var data = tabla.row($(this).parents('tr')).data();
        
        Swal.fire({
            title: '¿Eliminar función?',
            html: `<p>Esta acción eliminará permanentemente la función:</p>
                   <p><strong>${data.nombre_funcion}</strong></p>
                   <p class="text-danger">Esta acción no se puede deshacer</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('paginas_funciones_tipos_ajax.php', {
                    accion: 'eliminar', 
                    pagina_funcion_id: data.pagina_funcion_id
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Eliminada!",
                            text: "Función eliminada correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar la función"
                        });
                    }
                }, 'json');
            }
        });
    });
    
    // Ver función estándar
    $(document).on('click', '.btnVerEst', function(){
        var tabla = $('#tablaFunciones').DataTable();
        var data = tabla.row($(this).parents('tr')).data();
        
        if (data.funcion_estandar_nombre) {
            $.get('paginas_funciones_tipos_ajax.php', {accion: 'obtener_funciones_estandar'}, function(res){
                var funcion = res.find(f => f.nombre == data.funcion_estandar_nombre);
                if (funcion) {
                    Swal.fire({
                        title: funcion.nombre,
                        html: `<p><strong>Descripción:</strong> ${funcion.descripcion || 'No disponible'}</p>
                               <p><strong>Acción JS:</strong> <code>${funcion.accion_js || 'No definida'}</code></p>`,
                        icon: 'info',
                        confirmButtonText: 'Cerrar',
                        width: '600px'
                    });
                }
            }, 'json');
        }
    });
}