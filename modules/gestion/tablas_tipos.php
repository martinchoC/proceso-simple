<?php
// Configuración de la página
$pageTitle = "Gestión de Tablas";
$currentPage = 'tablas';
$modudo_idx = 1;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Tipos de Tablas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Tablas</li>
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
                                    <div class="card-body">
                                        <button class="btn btn-primary mb-3" id="btnNuevoTipo">Nuevo Tipo</button>
                                        <table id="tablaTipos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tipo de Tabla</th>
                                                    <th>Cantidad de Estados</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>                                            
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal para Tipo de Tabla -->
            <div class="modal fade" id="modalTipo" tabindex="-1" aria-labelledby="modalLabelTipo" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabelTipo">Tipo de Tabla</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formTipo">
                                <input type="hidden" id="tabla_tipo_id" name="tabla_tipo_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre del Tipo *</label>
                                        <input type="text" class="form-control" id="tabla_tipo" name="tabla_tipo" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="tabla_tabla_estado_registro_id_tipo" name="tabla_tabla_estado_registro_id" value="1" checked>
                                            <label class="form-check-label" for="tabla_tabla_estado_registro_id_tipo">Tipo activo</label>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardarTipo" class="btn btn-success">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Estados de Tipo de Tabla -->
            <div class="modal fade" id="modalEstados" tabindex="-1" aria-labelledby="modalLabelEstados" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabelEstados">Estados del Tipo: <span id="nombreTipo"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="tabla_tipo_id_estados" />
                            <button class="btn btn-primary mb-3" id="btnNuevoEstado">Nuevo Estado</button>
                            <table id="tablaEstados" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Estado</th>
                                        <th>Orden</th>
                                        <th>Es Inicial</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>                                            
                                </thead>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Estado Individual -->
            <div class="modal fade" id="modalEstado" tabindex="-1" aria-labelledby="modalLabelEstado" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabelEstado">Estado</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formEstado">
                                <input type="hidden" id="tabla_tipo_estado_id" name="tabla_tipo_estado_id" />
                                <input type="hidden" id="tabla_tipo_id_estado" name="tabla_tipo_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Estado *</label>
                                        <select class="form-control" id="tabla_tipo_estado" name="tabla_tipo_estado" required>
                                            <option value="">Seleccionar estado</option>
                                        </select>
                                        <div class="invalid-feedback">El estado es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Orden</label>
                                        <input type="number" class="form-control" id="valor" name="valor" min="1" value="1"/>
                                        <small class="text-muted">Define el orden de los estados (1, 2, 3...)</small>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="es_inicial" name="es_inicial" value="1">
                                            <label class="form-check-label" for="es_inicial">Estado inicial (primer estado por defecto)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="tabla_tabla_estado_registro_id_estado" name="tabla_tabla_estado_registro_id" value="1" checked>
                                            <label class="form-check-label" for="tabla_tabla_estado_registro_id_estado">Estado activo</label>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardarEstado" class="btn btn-success">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            // Funciones para manejar estados registros
            function cargarEstadosDisponibles(tabla_estado_registro_id_seleccionado = null) {
                $.get('tablas_tipos_ajax.php', {
                    accion: 'listar_estados_registros'
                }, function(res) {
                    if(res && res.length > 0) {
                        var select = $('#tabla_tipo_estado');
                        select.empty();
                        select.append('<option value="">Seleccionar estado</option>');
                        
                        $.each(res, function(index, estado) {
                            var selected = '';
                            if (tabla_estado_registro_id_seleccionado && estado.tabla_estado_registro_id == tabla_estado_registro_id_seleccionado) {
                                selected = 'selected';
                            }
                            select.append('<option value="' + estado.tabla_estado_registro_id + '" ' + selected + '>' + 
                                         estado.estado_registro + '</option>');
                        });
                    } else {
                        $('#tabla_tipo_estado').html('<option value="">No hay estados disponibles</option>');
                    }
                }, 'json').fail(function() {
                    $('#tabla_tipo_estado').html('<option value="">Error al cargar estados</option>');
                });
            }

            function abrirModalEstado(estadoId = null, esNuevo = true) {
                // Limpiar formulario
                $('#formEstado')[0].reset();
                
                if (esNuevo) {
                    // Nuevo estado
                    $('#tabla_tipo_estado_id').val('');
                    $('#tabla_tipo_id_estado').val($('#tabla_tipo_id_estados').val());
                    $('#valor').val(1);
                    $('#es_inicial').prop('checked', false);
                    $('#tabla_tabla_estado_registro_id_estado').prop('checked', true);
                    $('#modalLabelEstado').text('Nuevo Estado');
                    
                    // Cargar estados disponibles
                    cargarEstadosDisponibles();
                } else {
                    // Editar estado existente
                    $.get('tablas_tipos_ajax.php', {
                        accion: 'obtener_estado', 
                        tabla_tipo_estado_id: estadoId
                    }, function(res) {
                        if(res) {
                            $('#tabla_tipo_estado_id').val(res.tabla_tipo_estado_id);
                            $('#tabla_tipo_id_estado').val(res.tabla_tipo_id);
                            $('#valor').val(res.valor);
                            $('#es_inicial').prop('checked', res.es_inicial == 1);
                            $('#tabla_tabla_estado_registro_id_estado').prop('checked', res.tabla_tabla_estado_registro_id == 1);
                            $('#modalLabelEstado').text('Editar Estado');
                            
                            // Cargar estados disponibles con el actual seleccionado
                            cargarEstadosDisponibles(res.tabla_estado_registro_id);
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "No se pudo cargar los datos del estado"
                            });
                        }
                    }, 'json');
                }
                
                // Mostrar modal
                var modal = new bootstrap.Modal(document.getElementById('modalEstado'));