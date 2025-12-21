<?php
// Configuración de la página
$pageTitle = "Gestión de Categorías de Productos";
$currentPage = 'productos_categorias';
$modudo_idx = 2;
// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Categorías de Productos</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Categorías</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Categoría</button>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="input-group">
                                                    <input type="text" id="filtroCategorias" class="form-control" placeholder="Buscar categorías..." />
                                                    <button class="btn btn-outline-secondary" type="button" id="btnLimpiarFiltro">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <button class="btn btn-sm btn-outline-secondary" id="btnExpandirTodo">
                                                    <i class="fas fa-expand"></i> Expandir Todo
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary" id="btnContraerTodo">
                                                    <i class="fas fa-compress"></i> Contraer Todo
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div id="arbolCategorias" class="arbol-categorias">
                                            <div class="text-center py-5">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Cargando...</span>
                                                </div>
                                                <p class="mt-2">Cargando categorías...</p>
                                            </div>
                                        </div>
                                        
                                        <div id="sinResultados" class="text-center py-4 text-muted" style="display: none;">
                                            <i class="fas fa-search fa-2x mb-2"></i>
                                            <p>No se encontraron categorías que coincidan con la búsqueda</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal -->
            <div class="modal fade" id="modalCategoria" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Categoría de Producto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCategoria">
                                <input type="hidden" id="producto_categoria_id" name="producto_categoria_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre de la Categoría *</label>
                                        <input type="text" class="form-control" id="producto_categoria_nombre" name="producto_categoria_nombre" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Categoría Padre</label>
                                        <select class="form-control" id="producto_categoria_padre_id" name="producto_categoria_padre_id">
                                            <option value="">-- Sin categoría padre (categoría principal) --</option>
                                        </select>
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

            <script>
            $(document).ready(function(){
                // Variable global para el árbol
                let arbolData = {};
                let arbolCompleto = {};
                
                // Cargar el árbol de categorías
                function cargarArbolCategorias() {
                    $('#arbolCategorias').show();
                    $('#sinResultados').hide();
                    $('#arbolCategorias').html(`
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2">Cargando categorías...</p>
                        </div>
                    `);
                    
                    $.get('productos_categorias_ajax.php?accion=listar_arbol', function(arbol) {
                        arbolData = arbol;
                        arbolCompleto = JSON.parse(JSON.stringify(arbol)); // Copia profunda
                        renderizarArbol(arbol);
                    }, 'json').fail(function() {
                        $('#arbolCategorias').html('<div class="alert alert-danger">Error al cargar las categorías</div>');
                    });
                }
                
                // Filtrar categorías
                function filtrarCategorias(termino) {
                    termino = termino.toLowerCase().trim();
                    
                    if (termino === '') {
                        renderizarArbol(arbolCompleto);
                        return;
                    }
                    
                    // Filtrar el árbol
                    const arbolFiltrado = filtrarArbol(arbolCompleto, termino);
                    
                    if (Object.keys(arbolFiltrado).length === 0) {
                        $('#arbolCategorias').hide();
                        $('#sinResultados').show();
                    } else {
                        $('#arbolCategorias').show();
                        $('#sinResultados').hide();
                        renderizarArbol(arbolFiltrado);
                    }
                }
                
                // Función recursiva para filtrar el árbol
                function filtrarArbol(arbol, termino) {
                    const resultado = {};
                    
                    for (const id in arbol) {
                        const nodo = arbol[id];
                        const nombre = nodo.producto_categoria_nombre.toLowerCase();
                        
                        // Si el nodo coincide con el término o tiene hijos que coinciden
                        if (nombre.includes(termino) || tieneHijosQueCoinciden(nodo.hijos, termino)) {
                            resultado[id] = {
                                ...nodo,
                                hijos: filtrarArbol(nodo.hijos || {}, termino)
                            };
                        }
                    }
                    
                    return resultado;
                }
                
                // Verificar si hay hijos que coinciden con el término
                function tieneHijosQueCoinciden(hijos, termino) {
                    if (!hijos) return false;
                    
                    for (const id in hijos) {
                        const hijo = hijos[id];
                        if (hijo.producto_categoria_nombre.toLowerCase().includes(termino) || 
                            tieneHijosQueCoinciden(hijo.hijos, termino)) {
                            return true;
                        }
                    }
                    
                    return false;
                }
                
                // Renderizar el árbol
                function renderizarArbol(arbol) {
                    arbolData = arbol;
                    
                    if (Object.keys(arbol).length === 0) {
                        $('#arbolCategorias').html('<div class="text-center py-4 text-muted">No hay categorías</div>');
                        return;
                    }
                    
                    let html = '<ul class="list-group arbol-list">';
                    
                    for (const id in arbol) {
                        html += renderizarNodo(arbol[id], 0);
                    }
                    
                    html += '</ul>';
                    $('#arbolCategorias').html(html);
                    
                    // Agregar event listeners para expandir/contraer
                    $('.nodo-toggle').click(function() {
                        const nodoId = $(this).data('nodo-id');
                        const $hijos = $(`#hijos-${nodoId}`);
                        
                        if ($hijos.is(':visible')) {
                            $hijos.hide();
                            $(this).find('i').removeClass('fa-folder-open').addClass('fa-folder');
                        } else {
                            $hijos.show();
                            $(this).find('i').removeClass('fa-folder').addClass('fa-folder-open');
                        }
                    });
                    
                    // Resaltar texto de búsqueda
                    const termino = $('#filtroCategorias').val().toLowerCase().trim();
                    if (termino) {
                        $('.categoria-nombre').each(function() {
                            const texto = $(this).text();
                            const textoOriginal = texto;
                            const textoLower = texto.toLowerCase();
                            const index = textoLower.indexOf(termino);
                            
                            if (index !== -1) {
                                const textoResaltado = 
                                    textoOriginal.substring(0, index) +
                                    '<mark>' + textoOriginal.substring(index, index + termino.length) + '</mark>' +
                                    textoOriginal.substring(index + termino.length);
                                
                                $(this).html(textoResaltado);
                            }
                        });
                    }
                }
                
                // Renderizar un nodo del árbol
                function renderizarNodo(nodo, nivel) {
                    const tieneHijos = nodo.hijos && Object.keys(nodo.hijos).length > 0;
                    const sangria = nivel * 30;
                    
                    let html = `
                        <li class="list-group-item arbol-item" style="padding-left: ${sangria}px">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                    `;
                    
                    if (tieneHijos) {
                        html += `
                            <button class="btn btn-sm btn-outline-secondary me-2 nodo-toggle" data-nodo-id="${nodo.producto_categoria_id}">
                                <i class="fas fa-folder"></i>
                            </button>
                        `;
                    } else {
                        html += `
                            <span class="me-4" style="width: 30px"></span>
                        `;
                    }
                    
                    html += `
                        <span class="categoria-nombre">${nodo.producto_categoria_nombre}</span>
                        </div>
                        <div class="acciones-nodo">
                            <button class="btn btn-sm btn-primary btnEditar" data-id="${nodo.producto_categoria_id}" title="Editar">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btnEliminar" data-id="${nodo.producto_categoria_id}" title="Eliminar">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    `;
                    
                    if (tieneHijos) {
                        html += `<div id="hijos-${nodo.producto_categoria_id}" class="mt-2" style="display: none;">`;
                        html += '<ul class="list-group">';
                        
                        for (const hijoId in nodo.hijos) {
                            html += renderizarNodo(nodo.hijos[hijoId], nivel + 1);
                        }
                        
                        html += '</ul>';
                        html += '</div>';
                    }
                    
                    html += '</li>';
                    return html;
                }
                
                // Event listener para el filtro
                $('#filtroCategorias').on('input', function() {
                    const termino = $(this).val();
                    filtrarCategorias(termino);
                });
                
                // Limpiar filtro
                $('#btnLimpiarFiltro').click(function() {
                    $('#filtroCategorias').val('');
                    filtrarCategorias('');
                });
                
                // Expandir todo
                $('#btnExpandirTodo').click(function() {
                    $('.nodo-toggle').each(function() {
                        const nodoId = $(this).data('nodo-id');
                        $(`#hijos-${nodoId}`).show();
                        $(this).find('i').removeClass('fa-folder').addClass('fa-folder-open');
                    });
                });
                
                // Contraer todo
                $('#btnContraerTodo').click(function() {
                    $('.nodo-toggle').each(function() {
                        const nodoId = $(this).data('nodo-id');
                        $(`#hijos-${nodoId}`).hide();
                        $(this).find('i').removeClass('fa-folder-open').addClass('fa-folder');
                    });
                });
                
                // Cargar categorías padre disponibles
                function cargarCategoriasPadre(excluirId = null) {
                    var url = 'productos_categorias_ajax.php?accion=listar_todas_categorias';
                    if (excluirId) {
                        url += '&excluir_id=' + excluirId;
                    }
                    
                    $.get(url, function(categorias) {
                        var select = $('#producto_categoria_padre_id');
                        select.empty().append('<option value="">-- Sin categoría padre (categoría principal) --</option>');
                        
                        $.each(categorias, function(index, categoria) {
                            select.append($('<option>', {
                                value: categoria.producto_categoria_id,
                                text: categoria.producto_categoria_nombre
                            }));
                        });
                    }, 'json');
                }

                $('#btnNuevo').click(function(){
                    $('#formCategoria')[0].reset();
                    $('#producto_categoria_id').val('');
                    $('#modalLabel').text('Nueva Categoría');
                    cargarCategoriasPadre();
                    var modal = new bootstrap.Modal(document.getElementById('modalCategoria'));
                    modal.show();
                });

                // Editar categoría
                $(document).on('click', '.btnEditar', function(){
                    const id = $(this).data('id');
                    
                    $.get('productos_categorias_ajax.php', {accion: 'obtener', producto_categoria_id: id}, function(res){
                        if(res){
                            $('#producto_categoria_id').val(res.producto_categoria_id);
                            $('#producto_categoria_nombre').val(res.producto_categoria_nombre);
                            
                            // Cargar categorías excluyendo la actual para evitar ciclos
                            cargarCategoriasPadre(res.producto_categoria_id);
                            setTimeout(function() {
                                $('#producto_categoria_padre_id').val(res.producto_categoria_padre_id);
                            }, 300);
                            
                            $('#modalLabel').text('Editar Categoría');
                            var modal = new bootstrap.Modal(document.getElementById('modalCategoria'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Eliminar categoría
                $(document).on('click', '.btnEliminar', function(){
                    const id = $(this).data('id');
                    
                    Swal.fire({
                        title: '¿Eliminar categoría?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('productos_categorias_ajax.php', {
                                accion: 'eliminar', 
                                producto_categoria_id: id
                            }, function(res){
                                if(res.resultado){
                                    cargarArbolCategorias();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Categoría eliminada correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar la categoría"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formCategoria');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#producto_categoria_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        producto_categoria_id: id,
                        producto_categoria_nombre: $('#producto_categoria_nombre').val(),
                        producto_categoria_padre_id: $('#producto_categoria_padre_id').val()
                    };

                    $.ajax({
                        url: 'productos_categorias_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                cargarArbolCategorias();
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalCategoria'));
                                modal.hide();
                                
                                $('#formCategoria')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Categoría actualizada correctamente" : "Categoría creada correctamente",
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
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "Error de conexión con el servidor"
                            });
                        }
                    });
                });
                
                // Cargar inicialmente el árbol
                cargarArbolCategorias();
                
                // Permitir buscar con Enter
                $('#filtroCategorias').keypress(function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                    }
                });
            });
            </script>
            <style>
            .arbol-categorias {
                max-height: 600px;
                overflow-y: auto;
            }
            
            .arbol-list {
                border: none;
            }
            
            .arbol-item {
                border-left: none;
                border-right: none;
                border-radius: 0;
                transition: background-color 0.2s;
            }
            
            .arbol-item:hover {
                background-color: #f8f9fa;
            }
            
            .nodo-toggle {
                width: 30px;
                height: 30px;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .categoria-nombre {
                font-weight: 500;
            }
            
            .acciones-nodo {
                opacity: 0.3;
                transition: opacity 0.2s;
            }
            
            .arbol-item:hover .acciones-nodo {
                opacity: 1;
            }
            
            mark {
                background-color: #ffeb3b;
                padding: 0;
            }
            
            #sinResultados {
                border: 2px dashed #dee2e6;
                border-radius: 8px;
            }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>