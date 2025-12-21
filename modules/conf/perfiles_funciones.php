<?php
// Configuración de la página
$pageTitle = "Asignación de Funciones a Perfiles";
$currentPage = 'perfiles_funciones';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Asignación de Funciones</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Asignación de Funciones</li>
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
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Filtros</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="selectModulo">Módulo:</label>
                                            <select class="form-control" id="selectModulo">
                                                <option value="">Seleccione un módulo</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label for="selectPerfil">Perfil:</label>
                                            <select class="form-control" id="selectPerfil" disabled>
                                                <option value="">Seleccione un perfil</option>
                                            </select>
                                        </div>
                                        <div class="mt-3">
                                            <button id="btnCargar" class="btn btn-primary w-100" disabled>
                                                <i class="fas fa-sync"></i> Cargar Funciones
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Funciones por Página</h3>
                                        <div class="card-tools">
                                            <span class="badge bg-info" id="contadorFunciones">0 funciones</span>
                                        </div>
                                    </div>
                                    <div class="card-body p-3">
                                        <div id="infoSeleccion" class="alert alert-info d-none mb-3 p-2">
                                            <i class="fas fa-info-circle"></i> 
                                            <span id="textoInfo">Seleccione un módulo y perfil para ver las funciones</span>
                                        </div>
                                        <div id="contenedorPaginas" class="compact-view"></div>
                                        <div id="sinResultados" class="alert alert-warning d-none mt-3">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            No se encontraron páginas para el módulo seleccionado.
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
</main>

<style>
.pagina-item {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    margin-bottom: 15px;
    background: #fff;
    transition: margin-left 0.3s ease;
}

.pagina-hijo {
    border-left: 3px solid #007bff;
    padding-left: 15px;
}

.hijos-container {
    margin-left: 20px;
    border-left: 2px solid #dee2e6;
    padding-left: 15px;
}
.pagina-header {
    background-color: #f8f9fa;
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
    font-weight: bold;
    display: flex;
    justify-content: between;
    align-items: center;
}
.pagina-body {
    padding: 15px;
}
.funciones-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}
.funcion-item {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 10px 15px;
    background: #fff;
    min-width: 200px;
    flex: 1;
    max-width: 300px;
    transition: all 0.2s;
}
.funcion-asignada {
    background-color: #d4edda;
    border-color: #c3e6cb;
}
.toggle-switch {
    cursor: pointer;
    transform: scale(1.2);
}
.funcion-info {
    margin-bottom: 8px;
}
.funcion-nombre {
    font-weight: bold;
    margin-bottom: 3px;
}
.funcion-descripcion {
    font-size: 0.85rem;
    color: #6c757d;
}

.hijos-anidados {
    margin-left: 20px;
}

.pagina-descripcion {
    font-size: 0.9rem;
    color: #6c757d;
    font-style: italic;
    border-bottom: 1px dashed #dee2e6;
    padding-bottom: 10px;
}

.pagina-sin-funciones {
    opacity: 0.8;
    background-color: #f8f9fa;
}

.pagina-sin-funciones .pagina-header {
    background-color: #e9ecef;
}

.pagina-body {
    padding: 15px;
}

.funciones-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.funcion-item {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 10px 15px;
    background: #fff;
    min-width: 200px;
    flex: 1;
    max-width: 300px;
    transition: all 0.2s;
}

.funcion-asignada {
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.toggle-switch {
    cursor: pointer;
    transform: scale(1.2);
}

.funcion-info {
    margin-bottom: 8px;
}

.funcion-nombre {
    font-weight: bold;
    margin-bottom: 3px;
}

.funcion-descripcion {
    font-size: 0.85rem;
    color: #6c757d;
}

.sin-funciones {
    font-style: italic;
    color: #6c757d;
    padding: 10px;
}
/* Nuevos estilos para funciones compactas */
.funciones-container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.funcion-item {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 6px 10px;
    background: #f8f9fa;
    min-width: auto;
    flex: none;
    max-width: 180px;
    transition: all 0.2s;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.funcion-asignada {
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.funcion-info {
    margin-bottom: 0;
    flex-grow: 1;
}

.funcion-nombre {
    font-weight: bold;
    margin-bottom: 0;
    font-size: 0.8rem;
}

.funcion-descripcion {
    display: none; /* Ocultamos la descripción */
}

.toggle-switch {
    cursor: pointer;
    transform: scale(0.9);
    margin-left: 8px;
}

/* Estilo para alinear funciones al costado */
.pagina-con-funciones {
    display: flex;
    flex-direction: column;
}

.funciones-lateral {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

/* Para páginas con hijos, mostramos las funciones arriba */
.pagina-con-hijos .funciones-lateral {
    margin-bottom: 10px;
}

/* Ajustar el header para que ocupe todo el ancho */
.pagina-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

</style>

<script>
$(document).ready(function() {
    // Cargar módulos al iniciar
    cargarModulos();
    
    // Evento cuando cambia el módulo seleccionado
    $('#selectModulo').change(function() {
        var modulo_id = $(this).val();
        $('#selectPerfil').prop('disabled', !modulo_id);
        
        if (modulo_id) {
            cargarPerfilesPorModulo(modulo_id);
            actualizarInfoSeleccion();
        } else {
            $('#selectPerfil').empty().append('<option value="">Seleccione un perfil</option>');
            $('#btnCargar').prop('disabled', true);
            $('#contenedorPaginas').empty();
            actualizarInfoSeleccion();
        }
    });
    
    // Evento cuando cambia el perfil seleccionado
    $('#selectPerfil').change(function() {
        var perfil_id = $(this).val();
        $('#btnCargar').prop('disabled', !perfil_id);
        actualizarInfoSeleccion();
    });
    
    // Evento del botón cargar
    $('#btnCargar').click(function() {
        var modulo_id = $('#selectModulo').val();
        var perfil_id = $('#selectPerfil').val();
        
        if (modulo_id && perfil_id) {
            cargarPaginasConFunciones(modulo_id, perfil_id);
        }
    });
    
    // Función para actualizar la información de selección
    function actualizarInfoSeleccion() {
        var modulo = $('#selectModulo option:selected').text();
        var perfil = $('#selectPerfil option:selected').text();
        
        if (modulo && modulo !== "Seleccione un módulo" && perfil && perfil !== "Seleccione un perfil") {
            $('#textoInfo').html(`Módulo: <strong>${modulo}</strong> | Perfil: <strong>${perfil}</strong>`);
            $('#infoSeleccion').removeClass('d-none');
        } else {
            $('#infoSeleccion').addClass('d-none');
        }
    }
    
    // Función para cargar los módulos
    function cargarModulos() {
        $.get('perfiles_funciones_ajax.php', {accion: 'obtener_modulos'}, function(res) {
            if (res && res.length > 0) {
                $('#selectModulo').empty().append('<option value="">Seleccione un módulo</option>');
                $.each(res, function(i, modulo) {
                    $('#selectModulo').append($('<option>', {
                        value: modulo.modulo_id,
                        text: modulo.modulo
                    }));
                });
            }
        }, 'json');
    }
    
    // Función para cargar perfiles por módulo
    function cargarPerfilesPorModulo(modulo_id) {
        $.get('perfiles_funciones_ajax.php', {accion: 'obtener_perfiles_por_modulo', modulo_id: modulo_id}, function(res) {
            $('#selectPerfil').empty().append('<option value="">Seleccione un perfil</option>');
            
            if (res && res.length > 0) {
                $.each(res, function(i, perfil) {
                    $('#selectPerfil').append($('<option>', {
                        value: perfil.perfil_id,
                        text: perfil.perfil_nombre
                    }));
                });
            } else {
                $('#selectPerfil').append('<option value="">No hay perfiles para este módulo</option>');
            }
        }, 'json');
    }
    
    // Función para cargar páginas con funciones
    function cargarPaginasConFunciones(modulo_id, perfil_id) {
        $('#contenedorPaginas').html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-2">Cargando estructura de páginas...</p></div>');
        $('#sinResultados').addClass('d-none');
        
        $.get('perfiles_funciones_ajax.php', {
            accion: 'obtener_paginas_funciones', 
            modulo_id: modulo_id,
            perfil_id: perfil_id
        }, function(res) {
            
            $('#contenedorPaginas').empty();
            
            if (res && Object.keys(res).length > 0) {
                $('#sinResultados').addClass('d-none');
                
                // Contador de funciones
                let totalFunciones = 0;
                let htmlCompleto = '';
                
                // Recorrer las páginas raíz
                $.each(res, function(pagina_id, pagina) {
                    htmlCompleto += renderizarPagina(pagina, 0);
                    
                    // Contar funciones recursivamente
                    totalFunciones += contarFuncionesRecursivamente(pagina);
                });
                
                // Agregar todo el HTML al contenedor
                $('#contenedorPaginas').append(htmlCompleto);
                
                // Actualizar contador
                $('#contadorFunciones').text(totalFunciones + ' funciones');
                
                // Agregar evento a los switches
                $('.toggle-switch').change(function() {
                    var perfil_id = $('#selectPerfil').val();
                    var pagina_funcion_id = $(this).data('pagina-funcion-id');
                    var asignado = $(this).is(':checked') ? 1 : 0;
                    
                    if (perfil_id) {
                        toggleFuncionPerfil(perfil_id, pagina_funcion_id, asignado, $(this));
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Seleccione un perfil',
                            text: 'Debe seleccionar un perfil primero'
                        });
                        $(this).prop('checked', !asignado);
                    }
                });
            } else {
                $('#sinResultados').removeClass('d-none');
                $('#contadorFunciones').text('0 funciones');
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            $('#contenedorPaginas').empty();
            $('#sinResultados').removeClass('d-none');
            $('#contadorFunciones').text('0 funciones');
            console.error('Error al cargar funciones:', textStatus, errorThrown);
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudieron cargar las páginas y funciones. Verifique la conexión y vuelva a intentar.'
            });
        });
    }

// Función auxiliar para contar funciones recursivamente
function contarFuncionesRecursivamente(pagina) {
    let count = pagina.funciones ? pagina.funciones.length : 0;
    
    if (pagina.hijos && Object.keys(pagina.hijos).length > 0) {
        $.each(pagina.hijos, function(hijo_id, hijo) {
            count += contarFuncionesRecursivamente(hijo);
        });
    }
    
    return count;
}
    
    
  // Función para renderizar una página y sus hijos recursivamente
function renderizarPagina(pagina, nivel) {
    let funcionCount = pagina.funciones ? pagina.funciones.length : 0;
    let tieneFunciones = funcionCount > 0;
    let tieneHijos = pagina.hijos && Object.keys(pagina.hijos).length > 0;
    
    // Determinar la clase según el nivel
    const marginLeft = nivel > 0 ? (nivel * 20) + 'px' : '0';
    const claseSinFunciones = !tieneFunciones && !tieneHijos ? 'pagina-sin-funciones' : '';
    const claseConHijos = tieneHijos ? 'pagina-con-hijos' : '';
    
    let html = `
        <div class="pagina-item ${claseSinFunciones} ${claseConHijos}" style="margin-left: ${marginLeft}">
            <div class="pagina-header">
                <span>${pagina.pagina}</span>
                <span class="badge ${tieneFunciones ? 'bg-secondary' : 'bg-light text-dark'}">
                    ${funcionCount} ${funcionCount === 1 ? 'función' : 'funciones'}
                </span>
            </div>
    `;
    
    // Mostrar el cuerpo si hay funciones, hijos o descripción
    if (tieneFunciones || tieneHijos || (pagina.pagina_descripcion && pagina.pagina_descripcion !== '')) {
        html += `<div class="pagina-body">`;
        
        // Mostrar descripción si existe
        if (pagina.pagina_descripcion && pagina.pagina_descripcion !== '') {
            html += `<div class="pagina-descripcion mb-2">${pagina.pagina_descripcion}</div>`;
        }
        
        // Renderizar funciones si existen (ahora más compactas)
        if (tieneFunciones) {
            html += `<div class="funciones-lateral">`;
            
            $.each(pagina.funciones, function(j, funcion) {
                const asignada = funcion.asignado == 1;
                
                html += `
                    <div class="funcion-item ${asignada ? 'funcion-asignada' : ''}">
                        <div class="funcion-info">
                            <div class="funcion-nombre">${funcion.nombre_funcion}</div>
                        </div>
                        <div class="ms-2">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input toggle-switch" type="checkbox" 
                                       data-pagina-funcion-id="${funcion.pagina_funcion_id}"
                                       ${asignada ? 'checked' : ''}>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`; // Cierre de funciones-lateral
        } else if (!tieneHijos) {
            html += `<div class="sin-funciones">Esta página no tiene funciones definidas.</div>`;
        }
        
        html += `</div>`; // Cierre de pagina-body
    }
    
    html += `</div>`; // Cierre de pagina-item
    
    // Renderizar páginas hijas recursivamente
    if (tieneHijos) {
        html += `<div class="hijos-container">`;
        $.each(pagina.hijos, function(hijo_id, hijo) {
            html += renderizarPagina(hijo, nivel + 1);
        });
        html += `</div>`; // Cierre de hijos-container
    }
    
    return html;
}
    // Función para asignar/desasignar función
    function toggleFuncionPerfil(perfil_id, pagina_funcion_id, asignado, switchElement) {
        $.get('perfiles_funciones_ajax.php', {
            accion: 'toggle_funcion',
            perfil_id: perfil_id,
            pagina_funcion_id: pagina_funcion_id,
            asignado: asignado
        }, function(res) {
            if (res.resultado) {
                // Actualizar visualmente
                var funcionItem = switchElement.closest('.funcion-item');
                if (asignado) {
                    funcionItem.addClass('funcion-asignada');
                    Swal.fire({
                        icon: 'success',
                        title: 'Función asignada',
                        text: 'La función ha sido asignada al perfil correctamente',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    funcionItem.removeClass('funcion-asignada');
                    Swal.fire({
                        icon: 'info',
                        title: 'Función desasignada',
                        text: 'La función ha sido desasignada del perfil',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            } else {
                // Revertir el cambio en caso de error
                switchElement.prop('checked', !asignado);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo completar la operación'
                });
            }
        }, 'json');
    }
});
// Función temporal para depurar la estructura de datos
function debugEstructura(res) {
    console.log("Estructura completa recibida:", res);
    
    let contadorPaginas = 0;
    let contadorHijos = 0;
    let contadorFunciones = 0;
    
    $.each(res, function(pagina_id, pagina) {
        contadorPaginas++;
        contadorFunciones += pagina.funciones ? pagina.funciones.length : 0;
        
        console.log(`Página: ${pagina.pagina} (ID: ${pagina.pagina_id}), Padre: ${pagina.padre_id}, Funciones: ${pagina.funciones ? pagina.funciones.length : 0}`);
        
        if (pagina.hijos && Object.keys(pagina.hijos).length > 0) {
            console.log(`  → Tiene ${Object.keys(pagina.hijos).length} hijos:`);
            $.each(pagina.hijos, function(hijo_id, hijo) {
                contadorHijos++;
                contadorFunciones += hijo.funciones ? hijo.funciones.length : 0;
                console.log(`    - Hijo: ${hijo.pagina} (ID: ${hijo.pagina_id}), Funciones: ${hijo.funciones ? hijo.funciones.length : 0}`);
            });
        }
    });
    
    console.log(`Resumen: ${contadorPaginas} páginas raíz, ${contadorHijos} páginas hijas, ${contadorFunciones} funciones totales`);
    
    // Mostrar resumen en la interfaz
    $('#debugInfo').remove();
    $('#contenedorPaginas').before(`
        <div id="debugInfo" class="alert alert-info">
            <strong>Depuración:</strong> ${contadorPaginas} páginas raíz, ${contadorHijos} páginas hijas, ${contadorFunciones} funciones totales
        </div>
    `);
}
</script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>