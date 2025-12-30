<?php
// Configuración de la página
$pageTitle = "Gestión de Marcas";
$currentPage = 'marcas';
$modudo_idx = 2;
$pagina_idx = 43; // ID de página para funciones

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

// Empresa fija por ahora (se cambiará por GET en el futuro)
$empresa_fija = 2;
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Gestión de Marcas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Marcas</li>
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
                        <!-- Tarjeta de información de empresa -->
                        <div class="card card-info mb-3">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-building me-2"></i>
                                        <span class="fw-bold">Empresa:</span>
                                        <span id="nombreEmpresa">Cargando...</span>
                                        <input type="hidden" id="empresa_id" value="<?php echo $empresa_fija; ?>">
                                    </div>
                                    <div class="text-muted">
                                        <small>ID: <?php echo $empresa_fija; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                      
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Listado de Marcas</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <button class="btn btn-primary" id="btnNuevo">
                                                <i class="fas fa-plus me-2"></i>Nueva Marca
                                            </button>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-outline-secondary" id="btnRefresh">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table id="tablaMarcas" class="table table-striped table-bordered table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th width="80">ID</th>
                                                        <th>Nombre de la Marca</th>
                                                        <th width="100">Estado</th>
                                                        <th width="200" class="text-center">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Los datos se cargarán dinámicamente -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

<!-- Modal para agregar/editar marcas -->
<div class="modal fade" id="modalMarca" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalLabel">Nueva Marca</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formMarca">
                    <input type="hidden" id="marca_id" name="marca_id">
                    <input type="hidden" id="empresa_id_form" name="empresa_id" value="<?php echo $empresa_fija; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="marca_nombre" class="form-label">Nombre de la Marca *</label>
                            <input type="text" class="form-control" id="marca_nombre" name="marca_nombre" 
                                   placeholder="Ingrese el nombre de la marca" required>
                            <div class="invalid-feedback">El nombre de la marca es obligatorio</div>
                            <div class="form-text">Ingrese un nombre descriptivo para la marca</div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="tabla_estado_registro_id" 
                                       name="tabla_estado_registro_id" value="1" checked>
                                <label class="form-check-label" for="tabla_estado_registro_id">
                                    Marca activa
                                </label>
                            </div>
                            <div class="form-text">Desactive esta opción para deshabilitar la marca sin eliminarla</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" id="btnGuardar" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    // Variables globales
    var tabla;
    var empresa_id = $('#empresa_id').val();
    var pagina_idx = <?php echo $pagina_idx; ?>;
    var cacheFunciones = {}; // Cache para funciones por estado
    
    // Cargar nombre de la empresa
    function cargarNombreEmpresa() {
        $.ajax({
            url: 'marcas_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_empresa', empresa_id: empresa_id },
            dataType: 'json',
            success: function(res) {
                if (res && res.empresa) {
                    $('#nombreEmpresa').text(res.empresa);
                } else {
                    $('#nombreEmpresa').text('Empresa #' + empresa_id);
                }
            },
            error: function() {
                $('#nombreEmpresa').text('Empresa #' + empresa_id);
            }
        });
    }
    
    // Obtener funciones para un estado específico
    function obtenerFuncionesPorEstado(estado_registro_id) {
        // Verificar cache primero
        if (cacheFunciones[estado_registro_id]) {
            return Promise.resolve(cacheFunciones[estado_registro_id]);
        }
        
        return new Promise((resolve, reject) => {
            $.ajax({
                url: 'marcas_ajax.php',
                type: 'GET',
                data: { 
                    accion: 'obtener_funciones_estado',
                    estado_registro_id: estado_registro_id
                },
                dataType: 'json',
                success: function(funciones) {
                    cacheFunciones[estado_registro_id] = funciones || [];
                    resolve(funciones || []);
                },
                error: function() {
                    console.error('Error al cargar funciones para estado:', estado_registro_id);
                    resolve([]);
                }
            });
        });
    }
    
    // Generar HTML de botones de acciones
    function generarBotonesAcciones(marca, funciones) {
        if (!funciones || funciones.length === 0) {
            return '<span class="text-muted">Sin acciones</span>';
        }
        
        var html = '<div class="btn-group" role="group">';
        
        funciones.forEach(function(funcion) {
            var icono = funcion.nombre_icono || 'fa-cog';
            var color = funcion.color_hex || '#6c757d';
            var titulo = funcion.nombre_funcion || funcion.funcion_estandar_nombre || 'Acción';
            var claseBoton = 'btn-' + (funcion.color_nombre || 'secondary');
            var accionJS = funcion.accion_js || '';
            var estadoDestino = funcion.tabla_estado_registro_destino_id;
            
            // Determinar si el botón debe estar deshabilitado
            var disabled = '';
            if (funcion.funcion_estandar_id == 1 && marca.tabla_estado_registro_id != 1) {
                disabled = 'disabled';
            }
            
            html += `<button class="btn btn-sm ${claseBoton} btnAccion me-1" 
                            title="${titulo}" 
                            data-marca-id="${marca.marca_id}"
                            data-funcion-id="${funcion.funcion_estandar_id}"
                            data-estado-origen="${marca.tabla_estado_registro_id}"
                            data-estado-destino="${estadoDestino}"
                            data-accion-js="${accionJS}"
                            data-nombre-marca="${marca.marca_nombre}"
                            ${disabled}>
                        <i class="fas ${icono}"></i>
                     </button>`;
        });
        
        html += '</div>';
        return html;
    }
    
    // Inicializar DataTable
    function inicializarTabla() {
        tabla = $('#tablaMarcas').DataTable({
            pageLength: 25,
            lengthMenu: [25, 50, 100, 200],
            dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
            ajax: {
                url: 'marcas_ajax.php',
                type: 'GET',
                data: { 
                    accion: 'listar',
                    empresa_id: empresa_id
                },
                dataSrc: ''
            },
            language: {
                "search": "Buscar:",
                "searchPlaceholder": "Buscar marcas...",
                "lengthMenu": "Mostrar _MENU_ registros por página",
                "zeroRecords": "No se encontraron marcas",
                "info": "Mostrando _START_ a _END_ de _TOTAL_ marcas",
                "infoEmpty": "Mostrando 0 a 0 de 0 marcas",
                "infoFiltered": "(filtrado de _MAX_ marcas totales)",
                "paginate": {
                    "first": "Primero",
                    "last": "Último",
                    "next": "Siguiente",
                    "previous": "Anterior"
                }
            },
            columns: [
                { 
                    data: 'marca_id',
                    className: 'text-center fw-bold'
                },
                { 
                    data: 'marca_nombre',
                    render: function(data, type, row) {
                        return '<span class="fw-medium">' + data + '</span>';
                    }
                },
                { 
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function(data) {
                        if (data == 1) {
                            return '<span class="badge bg-success">Activo</span>';
                        } else {
                            return '<span class="badge bg-danger">Inactivo</span>';
                        }
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        // Cargar funciones para este estado
                        var estadoId = row.tabla_estado_registro_id;
                        
                        // Devolver contenedor temporal
                        var containerId = 'acciones-' + row.marca_id;
                        return '<div id="' + containerId + '"><i class="fas fa-spinner fa-spin"></i></div>';
                    },
                    createdCell: function(cell, cellData, rowData, rowIndex, colIndex) {
                        // Cargar funciones dinámicamente para esta celda
                        var containerId = 'acciones-' + rowData.marca_id;
                        var estadoId = rowData.tabla_estado_registro_id;
                        
                        obtenerFuncionesPorEstado(estadoId).then(function(funciones) {
                            $('#' + containerId).html(generarBotonesAcciones(rowData, funciones));
                        });
                    }
                }
            ],
            drawCallback: function(settings) {
                // Actualizar contador
                var api = this.api();
                var total = api.data().count();
                $('#totalMarcas').text(total);
            }
        });
    }
    
    // Función para limpiar y mostrar modal
    function mostrarModal(esNuevo, datos = null) {
        $('#formMarca')[0].reset();
        $('#formMarca').removeClass('was-validated');
        
        if (esNuevo) {
            $('#modalLabel').text('Nueva Marca');
            $('#marca_id').val('');
            $('#tabla_estado_registro_id').prop('checked', true);
        } else {
            $('#modalLabel').text('Editar Marca');
            $('#marca_id').val(datos.marca_id);
            $('#marca_nombre').val(datos.marca_nombre);
            $('#tabla_estado_registro_id').prop('checked', datos.tabla_estado_registro_id == 1);
        }
        
        var modal = new bootstrap.Modal(document.getElementById('modalMarca'));
        modal.show();
        
        // Enfocar el primer campo
        setTimeout(function() {
            $('#marca_nombre').focus();
        }, 500);
    }
    
    // Manejar clic en botones de acción
    $(document).on('click', '.btnAccion', function() {
        var $btn = $(this);
        var marca_id = $btn.data('marca-id');
        var funcion_id = $btn.data('funcion-id');
        var estado_origen = $btn.data('estado-origen');
        var estado_destino = $btn.data('estado-destino');
        var accion_js = $btn.data('accion-js');
        var nombre_marca = $btn.data('nombre-marca');
        
        // Si tiene acción JS personalizada, ejecutarla
        if (accion_js) {
            try {
                eval(accion_js);
            } catch (e) {
                console.error('Error ejecutando acción JS:', e);
            }
            return;
        }
        
        // Para función de editar (ID 1)
        if (funcion_id == 1) {
            // Obtener datos de la marca
            $.ajax({
                url: 'marcas_ajax.php',
                type: 'GET',
                data: { accion: 'obtener', marca_id: marca_id },
                dataType: 'json',
                success: function(marca) {
                    if (marca) {
                        mostrarModal(false, marca);
                    }
                }
            });
            return;
        }
        
        // Para otras funciones, mostrar confirmación
        var tituloAccion = $btn.attr('title') || 'Acción';
        
        Swal.fire({
            title: `¿Ejecutar ${tituloAccion}?`,
            html: `<p>¿Estás seguro de querer ${tituloAccion.toLowerCase()} la marca?</p>
                  <div class="alert alert-info mt-2">
                      <strong>${nombre_marca}</strong>
                  </div>`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#28a745",
            cancelButtonColor: "#6c757d",
            confirmButtonText: `Sí, ${tituloAccion.toLowerCase()}`,
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                // Ejecutar acción
                $.ajax({
                    url: 'marcas_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'ejecutar_accion',
                        marca_id: marca_id,
                        funcion_estandar_id: funcion_id,
                        nuevo_estado: estado_destino
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.resultado) {
                            // Recargar tabla
                            tabla.ajax.reload(null, false);
                            
                            // Limpiar cache para el estado anterior
                            delete cacheFunciones[estado_origen];
                            
                            Swal.fire({
                                icon: "success",
                                title: "¡Éxito!",
                                text: `${tituloAccion} ejecutada correctamente`,
                                showConfirmButton: false,
                                timer: 1500
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || `Error al ejecutar ${tituloAccion.toLowerCase()}`
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error de conexión con el servidor"
                        });
                    }
                });
            }
        });
    });
    
    // Botón Nuevo
    $('#btnNuevo').click(function() {
        mostrarModal(true);
    });
    
    // Botón Refresh
    $('#btnRefresh').click(function() {
        // Limpiar cache
        cacheFunciones = {};
        tabla.ajax.reload();
        $(this).find('i').addClass('fa-spin');
        setTimeout(function() {
            $('#btnRefresh i').removeClass('fa-spin');
        }, 500);
    });
    
    // Guardar marca
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formMarca');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#marca_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            marca_id: id,
            empresa_id: $('#empresa_id_form').val(),
            marca_nombre: $('#marca_nombre').val(),
            tabla_estado_registro_id: $('#tabla_estado_registro_id').is(':checked') ? 1 : 0
        };
        
        // Deshabilitar botón durante la operación
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Procesando...');
        
        $.ajax({
            url: 'marcas_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html(originalText);
                
                if (res.resultado) {
                    // Cerrar modal
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalMarca'));
                    modal.hide();
                    
                    // Limpiar cache de funciones
                    cacheFunciones = {};
                    
                    // Recargar tabla
                    tabla.ajax.reload(null, false);
                    
                    // Mostrar mensaje de éxito
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Marca actualizada correctamente" : "Marca creada correctamente",
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
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error de conexión con el servidor"
                });
            }
        });
    });
    
    // Cargar datos al iniciar
    cargarNombreEmpresa();
    inicializarTabla();
    
    // Evento al cerrar modal
    $('#modalMarca').on('hidden.bs.modal', function() {
        $('#formMarca')[0].reset();
        $('#formMarca').removeClass('was-validated');
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>