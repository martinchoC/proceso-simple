<?php
// Configuración de la página
$pageTitle = "Gestión de Funciones por Tipo de Tabla";
$currentPage = 'paginas_funciones';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Funciones por Tipo de Tabla</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Funciones Tablas</li>
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
                        <!-- Filtros -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label>Tipo de Tabla</label>
                                <select class="form-control" id="filtro_tabla_tipo">
                                    <option value="">Todos los tipos</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Estado</label>
                                <select class="form-control" id="filtro_estado">
                                    <option value="">Todos</option>
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button class="btn btn-secondary w-100" id="btnLimpiarFiltros">Limpiar Filtros</button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card">                                
                                    <div class="card-header">
                                        <h3 class="card-title">Funciones Estándar por Tipo de Tabla</h3>
                                        <div class="card-tools">
                                            <button class="btn btn-success" id="btnNuevoFuncion">
                                                <i class="fas fa-plus"></i> Nueva Función
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <table id="tablaFunciones" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tipo Tabla</th>
                                                    <th>Función</th>
                                                    <th>Acción JS</th>
                                                    <th>Estado Origen → Destino</th>
                                                    <th>Orden</th>
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

            <!-- Modal Principal -->
            <div class="modal fade" id="modalFuncion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Nueva Función</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formFuncion">
                                <input type="hidden" id="pagina_funcion_id" name="pagina_funcion_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Tipo de Tabla *</label>
                                        <select class="form-control" id="tabla_id" name="tabla_id" required>
                                            <option value="">Seleccionar tipo</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un tipo de tabla</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label>Icono</label>
                                        <select class="form-control" id="icono_id" name="icono_id">
                                            <option value="">Sin icono</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label>Color</label>
                                        <select class="form-control" id="color_id" name="color_id">
                                            <option value="1">Predeterminado</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label>Función Estándar</label>
                                        <select class="form-control" id="funcion_estandar_id" name="funcion_estandar_id">
                                            <option value="1">Personalizada</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <label>Nombre Función *</label>
                                        <input type="text" class="form-control" id="nombre_funcion" name="nombre_funcion" required />
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <label>Acción JavaScript</label>
                                        <input type="text" class="form-control" id="accion_js" name="accion_js" placeholder="nombreFuncion()" />
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <label>Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label>Estado Origen *</label>
                                        <select class="form-control" id="tabla_estado_registro_origen_id" name="tabla_estado_registro_origen_id" required>
                                            <option value="">Seleccionar estado</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un estado origen</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label>Estado Destino *</label>
                                        <select class="form-control" id="tabla_estado_registro_destino_id" name="tabla_estado_registro_destino_id" required>
                                            <option value="">Seleccionar estado</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un estado destino</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label>Orden</label>
                                        <input type="number" class="form-control" id="orden" name="orden" min="0" value="0" />
                                        <small class="text-muted">Menor número = mayor prioridad</small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check mt-4 pt-3">
                                            <input class="form-check-input" type="checkbox" id="estado_registro" name="estado_registro" checked>
                                            <label class="form-check-label" for="estado_registro">
                                                Activo
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardar" class="btn btn-success">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="paginas_funciones_tipos.js"></script>
            <script>
            $(document).ready(function(){
                // Cargar combos
                cargarCombos();
                
                // Inicializar DataTable
                var tabla = inicializarDataTable();
                
                // Eventos
                $('#btnNuevoFuncion').click(abrirModalNuevo);
                $('#btnGuardar').click(guardarFuncion);
                $('#btnLimpiarFiltros').click(limpiarFiltros);
                
                // Aplicar filtros al cambiar
                $('#filtro_tabla_tipo, #filtro_estado').change(function(){
                    aplicarFiltros(tabla);
                });
                
                // Cargar funciones estándar al cambiar tipo de tabla
                $('#tabla_id').change(function(){
                    if($(this).val()) {
                        cargarFuncionesEstandar($(this).val());
                    }
                });
            });
            </script>
            <style>
            .badge-estado {
                font-size: 0.75rem;
                padding: 0.25em 0.6em;
            }
            .estado-origen-destino {
                background: #f8f9fa;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 0.9rem;
                display: inline-flex;
                align-items: center;
            }
            .estado-origen {
                color: #6c757d;
                font-weight: bold;
            }
            .estado-destino {
                color: #28a745;
                font-weight: bold;
            }
            .funcion-con-icono {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .color-badge {
                display: inline-block;
                width: 15px;
                height: 15px;
                border-radius: 3px;
                margin-right: 5px;
                vertical-align: middle;
            }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>