<?php
// Configuración de la página
$pageTitle = "Funciones por Perfil";
$currentPage = 'empresas_perfiles_funciones';
$modudo_idx = 1;
$pagina_idx = 62; // conf__paginas
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<style>
    .epf-filtros .form-label { font-size: .8rem; color: #6c757d; margin-bottom: .15rem; }
    .epf-ayuda { font-size: .85rem; }
    .epf-carpeta { font-weight: 600; color: #495057; margin: 1.1rem 0 .4rem; padding-bottom: .2rem; border-bottom: 1px solid #dee2e6; }
    .epf-carpeta small { font-weight: 400; color: #adb5bd; }
    .epf-pagina { border: 1px solid #dee2e6; border-radius: .4rem; margin-bottom: .5rem; background: #fff; }
    .epf-pagina-cab { display: flex; align-items: center; gap: .6rem; padding: .45rem .75rem; cursor: pointer; user-select: none; }
    .epf-pagina-cab:hover { background: #f8f9fa; }
    .epf-pagina-cab .epf-flecha { width: 1rem; color: #6c757d; transition: transform .15s; }
    .epf-pagina.abierta .epf-flecha { transform: rotate(90deg); }
    .epf-pagina-nombre { font-weight: 600; }
    .epf-pagina-url { color: #adb5bd; font-size: .8rem; }
    .epf-contador { margin-left: auto; white-space: nowrap; font-size: .78rem; color: #6c757d; }
    .epf-pagina-cuerpo { display: none; border-top: 1px solid #dee2e6; padding: .5rem .5rem .5rem 2.25rem; }
    .epf-pagina.abierta .epf-pagina-cuerpo { display: block; }
    .epf-tabla { margin: 0; font-size: .85rem; width: auto; min-width: 100%; }
    .epf-tabla th { background: #f8f9fa; font-weight: 600; font-size: .76rem; color: #6c757d; vertical-align: bottom; }
    .epf-tabla td { vertical-align: middle; }
    /* Estado y función quedan fijos a la izquierda al desplazar los perfiles */
    .epf-tabla .epf-col-estado { position: sticky; left: 0; z-index: 2; background: #fff; width: 170px; min-width: 170px; max-width: 170px; }
    .epf-tabla .epf-col-funcion { position: sticky; left: 170px; z-index: 2; background: #fff; width: 220px; min-width: 220px; max-width: 220px; }
    .epf-tabla th.epf-col-estado, .epf-tabla th.epf-col-funcion { background: #f8f9fa; z-index: 3; }
    .epf-tabla .epf-col-pasa { min-width: 140px; white-space: nowrap; }
    .epf-tabla tr.epf-grupo-ini td { border-top: 2px solid #ced4da; }
    .epf-funcion-nombre { font-weight: 600; }
    .epf-funcion-desc { color: #6c757d; font-size: .75rem; }
    .epf-estado { display: inline-block; padding: .1rem .5rem; border-radius: 1rem; background: #e9ecef; color: #343a40; font-size: .78rem; font-weight: 600; }
    .epf-estado.alta { background: #d1e7dd; color: #0f5132; }
    .epf-estado.siempre { background: #cfe2ff; color: #084298; }
    .epf-pasa { color: #6c757d; font-size: .78rem; }
    .epf-celda { text-align: center; min-width: 92px; }
    .epf-celda input { transform: scale(1.15); cursor: pointer; }
    .epf-celda.on { background: #f0f8f3; }
    .epf-celda.cambio { background: #fff3cd !important; }
    .epf-th-perfil { text-align: center; min-width: 92px; max-width: 130px; white-space: normal; line-height: 1.15; }
    .epf-th-perfil .epf-th-nombre { display: block; color: #343a40; }
    .epf-th-perfil .epf-th-empresa { display: block; font-weight: 400; font-size: .7rem; color: #adb5bd; }
    .epf-th-perfil .form-check-input { margin: .25rem 0 0; }
    .epf-barra-guardar { position: sticky; bottom: 0; z-index: 20; background: #fff; border-top: 2px solid #ffc107; padding: .6rem 1rem; display: none; box-shadow: 0 -2px 8px rgba(0,0,0,.08); }
    .epf-barra-guardar.visible { display: flex; align-items: center; gap: .5rem; }
    .epf-ocultar-desc .epf-funcion-desc { display: none; }
</style>

<main class="app-main">
    <?php ui_encabezado('Funciones por perfil', 'bi bi-shield-check', 'Qué botones puede usar cada perfil de empresa en cada página, según el estado en que está el registro. Es lo que lee el sistema para mostrar los botones y autorizar las acciones.', ['Empresas y accesos']); ?>

    <div class="app-content">
        <div class="container-fluid">

            <div class="card mb-3">
                <div class="card-body epf-filtros">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label" for="selectModulo">Módulo</label>
                            <select class="form-select form-select-sm" id="selectModulo">
                                <option value="">Elegí un módulo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="selectEmpresa">Empresa</label>
                            <select class="form-select form-select-sm" id="selectEmpresa">
                                <option value="">Todas las empresas</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filtroTexto">Buscar</label>
                            <input type="text" class="form-control form-control-sm" id="filtroTexto" placeholder="Página, función o estado...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filtroAsignacion">Mostrar</label>
                            <select class="form-select form-select-sm" id="filtroAsignacion">
                                <option value="">Todas las funciones</option>
                                <option value="dif">Solo donde los perfiles difieren</option>
                                <option value="si">Permitidas a algún perfil</option>
                                <option value="no">No permitidas a algún perfil</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h3 class="card-title mb-0 me-2" id="tituloVista">Funciones por estado del registro</h3>
                        <div class="ms-auto d-flex flex-wrap gap-1">
                            <button class="btn btn-sm btn-outline-secondary" id="btnExpandirTodo" title="Abrir todas las páginas"><i class="fas fa-expand"></i> Abrir</button>
                            <button class="btn btn-sm btn-outline-secondary" id="btnContraerTodo" title="Cerrar todas las páginas"><i class="fas fa-compress"></i> Cerrar</button>
                            <button class="btn btn-sm btn-outline-secondary" id="btnToggleDescripciones"><i class="fas fa-align-left"></i> Descripciones</button>
                            <button class="btn btn-sm btn-outline-info" id="btnHeredarTodas" title="Asigna a un perfil todas las funciones del módulo que todavía no tiene (se guarda en el momento)"><i class="fas fa-copy"></i> Heredar todas</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border epf-ayuda py-2 mb-3">
                        <i class="fas fa-info-circle text-info"></i>
                        Para cada página, los estados del registro en el orden del circuito y, en cada uno, qué botones puede usar cada perfil (una columna por perfil).
                        La flecha indica a qué estado pasa el registro.
                        <span class="epf-estado siempre">Siempre</span> = no depende del estado (consultas, importaciones, etc.);
                        <span class="epf-estado alta">Alta</span> = al crear un registro nuevo.
                        La casilla del encabezado de cada perfil permite o quita todo lo de esa página.
                    </div>

                    <div id="contenedorMatriz">
                        <div class="text-center text-muted py-5">Elegí un módulo.</div>
                    </div>
                </div>
                <div class="epf-barra-guardar" id="barraGuardar">
                    <i class="fas fa-exclamation-circle text-warning"></i>
                    <span id="textoCambios">Hay cambios sin guardar</span>
                    <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnDescartar">Descartar</button>
                    <button class="btn btn-sm btn-success" id="btnGuardar"><i class="fas fa-save"></i> Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
$(document).ready(function() {
    var AJAX = 'empresas_perfiles_funciones_ajax.php';
    var EMPRESA_ACTIVA = <?= (int)($current_empresa_id ?? 0) ?>;

    var perfilesDatos = {};   // empresa_perfil_id -> datos del perfil
    var perfiles = [];        // ids de los perfiles mostrados (columnas)
    var paginas = [];         // del módulo, con sus funciones
    var original = {};        // perfil -> {funcion_id: true}
    var actual = {};          // perfil -> {funcion_id: true}
    var abiertas = {};        // pagina_id -> true
    var moduloMostrado = null;
    var filtrosPrevios = {modulo: '', empresa: ''};

    function esc(t) {
        return $('<div>').text(t == null ? '' : String(t)).html();
    }

    // ---------------------------------------------------------------- combos
    $.get(AJAX, {accion: 'obtener_modulos'}, function(res) {
        $.each(res || [], function(i, m) {
            $('#selectModulo').append($('<option>', {value: m.modulo_id, text: m.modulo}));
        });
    }, 'json');

    $.get(AJAX, {accion: 'obtener_empresas'}, function(res) {
        $.each(res || [], function(i, e) {
            $('#selectEmpresa').append($('<option>', {value: e.empresa_id, text: e.empresa_nombre}));
        });
        if (EMPRESA_ACTIVA && $('#selectEmpresa option[value="' + EMPRESA_ACTIVA + '"]').length) {
            $('#selectEmpresa').val(String(EMPRESA_ACTIVA));
        }
        filtrosPrevios.empresa = $('#selectEmpresa').val();
    }, 'json');

    // Al cambiar módulo o empresa se cargan todos sus perfiles, uno por columna
    $('#selectModulo, #selectEmpresa').on('change', function() {
        var $sel = $(this);
        confirmarDescarte(function() {
            filtrosPrevios = {modulo: $('#selectModulo').val(), empresa: $('#selectEmpresa').val()};
            cargarTodo();
        }, function() {
            $('#selectModulo').val(filtrosPrevios.modulo);
            $('#selectEmpresa').val(filtrosPrevios.empresa);
        });
    });

    function cargarTodo() {
        var modulo = $('#selectModulo').val();
        if (!modulo) {
            paginas = []; perfiles = [];
            $('#contenedorMatriz').html('<div class="text-center text-muted py-5">Elegí un módulo.</div>');
            actualizarBarra();
            return;
        }
        $('#contenedorMatriz').html('<div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>');
        var params = {accion: 'obtener_empresas_perfiles_por_modulo', modulo_id: modulo};
        if ($('#selectEmpresa').val()) { params.empresa_id = $('#selectEmpresa').val(); }
        $.get(AJAX, params, function(res) {
            perfilesDatos = {};
            perfiles = [];
            $.each(res || [], function(i, p) {
                perfilesDatos[p.empresa_perfil_id] = p;
                perfiles.push(parseInt(p.empresa_perfil_id, 10));
            });
            if (!perfiles.length) {
                paginas = [];
                $('#contenedorMatriz').html('<div class="text-center text-muted py-5">No hay perfiles de este módulo'
                    + ($('#selectEmpresa').val() ? ' en la empresa elegida' : '') + '.</div>');
                actualizarBarra();
                return;
            }
            cargarMatriz(modulo);
        }, 'json');
    }

    function cargarMatriz(modulo) {
        $.get(AJAX, {accion: 'obtener_matriz', modulo_id: modulo, perfil_ids: perfiles.join(',')}, function(res) {
            if (!res || !res.success) {
                $('#contenedorMatriz').html('<div class="alert alert-danger">' + esc(res && res.message || 'No se pudo cargar') + '</div>');
                return;
            }
            if (moduloMostrado !== modulo) { abiertas = {}; }
            moduloMostrado = modulo;
            paginas = res.data.paginas;
            original = {}; actual = {};
            $.each(perfiles, function(i, p) {
                original[p] = {}; actual[p] = {};
                $.each(res.data.asignaciones[p] || [], function(j, f) { original[p][f] = true; actual[p][f] = true; });
            });
            $('#tituloVista').text('Funciones por estado del registro · ' + perfiles.length + (perfiles.length === 1 ? ' perfil' : ' perfiles'));
            render();
        }, 'json').fail(function() {
            $('#contenedorMatriz').html('<div class="alert alert-danger">No se pudo cargar. Probá de nuevo.</div>');
        });
    }

    function nombrePerfil(id) {
        var p = perfilesDatos[id];
        return p ? p.empresa_perfil_nombre : ('Perfil ' + id);
    }

    function nombrePerfilCompleto(id) {
        var p = perfilesDatos[id];
        return p ? p.empresa_perfil_nombre + ' (' + p.empresa_nombre + ')' : ('Perfil ' + id);
    }

    // ---------------------------------------------------------------- armado
    // Orden de los estados: primero lo que no depende del estado (Siempre), después el alta
    // y después los estados en el orden del circuito de la tabla (conf__tablas_estados_registros.orden)
    function pesoEstado(f) {
        if (f.origen_id === 0) { return f.destino_id === 0 ? -2 : -1; }
        return f.origen_orden;
    }

    function agruparPorEstado(funciones) {
        var bloques = [], porClave = {};
        $.each(funciones, function(i, f) {
            var clave = f.origen_id === 0 ? (f.destino_id === 0 ? 'S' : 'A') : 'E' + f.origen_id;
            if (!porClave[clave]) {
                porClave[clave] = {muestra: f, peso: pesoEstado(f), filas: []};
                bloques.push(porClave[clave]);
            }
            porClave[clave].filas.push(f);
        });
        $.each(bloques, function(i, b) {
            b.filas.sort(function(a, c) { return a.orden - c.orden || String(a.nombre_funcion).localeCompare(c.nombre_funcion); });
        });
        bloques.sort(function(a, b) { return a.peso - b.peso; });
        return bloques;
    }

    function chipEstado(f) {
        if (f.origen_id === 0) {
            return f.destino_id === 0 ? '<span class="epf-estado siempre">Siempre</span>' : '<span class="epf-estado alta">Alta</span>';
        }
        return '<span class="epf-estado">' + esc(f.origen_nombre || ('Estado ' + f.origen_id)) + '</span>';
    }

    function textoPasa(f) {
        if (!f.destino_id || f.destino_id === f.origen_id) { return '<span class="epf-pasa">—</span>'; }
        return '<span class="epf-pasa"><i class="fas fa-arrow-right"></i> ' + esc(f.destino_nombre || ('Estado ' + f.destino_id)) + '</span>';
    }

    function filaVisible(f, texto, filtro, pagina) {
        if (filtro) {
            var marcas = perfiles.map(function(p) { return !!actual[p][f.pagina_funcion_id]; });
            if (filtro === 'si' && marcas.indexOf(true) < 0) { return false; }
            if (filtro === 'no' && marcas.indexOf(false) < 0) { return false; }
            if (filtro === 'dif' && (marcas.indexOf(true) < 0 || marcas.indexOf(false) < 0)) { return false; }
        }
        if (texto) {
            var heno = [pagina.pagina_nombre, pagina.ruta, f.nombre_funcion, f.accion_js, f.origen_nombre, f.destino_nombre,
                        f.origen_id === 0 ? (f.destino_id === 0 ? 'siempre' : 'alta') : ''].join(' ').toLowerCase();
            if (heno.indexOf(texto) < 0) { return false; }
        }
        return true;
    }

    function render() {
        if (!paginas.length) { return; }
        var texto = $('#filtroTexto').val().trim().toLowerCase();
        var filtro = $('#filtroAsignacion').val();
        var hijos = {}, ids = {};
        $.each(paginas, function(i, p) { ids[p.pagina_id] = true; });
        $.each(paginas, function(i, p) {
            var padre = ids[p.padre_id] ? p.padre_id : 0;
            (hijos[padre] = hijos[padre] || []).push(p);
        });

        var html = '';
        var totalFilas = 0;

        function nodo(p, camino) {
            var salida = '';
            if (p.funciones.length) {
                var bloque = tarjetaPagina(p, camino, texto, filtro);
                if (bloque.filas) { totalFilas += bloque.filas; salida += bloque.html; }
            }
            var sub = '';
            $.each(hijos[p.pagina_id] || [], function(i, h) { sub += nodo(h, camino.concat([p.pagina_nombre])); });
            if (sub && !p.funciones.length) {
                salida += '<div class="epf-carpeta" style="margin-left:' + (camino.length * 1.25) + 'rem">'
                        + '<i class="fas fa-folder-open text-warning"></i> ' + esc(p.pagina_nombre)
                        + (camino.length ? ' <small>' + esc(camino.join(' / ')) + '</small>' : '') + '</div>';
            }
            return salida + sub;
        }
        $.each(hijos[0] || [], function(i, p) { html += nodo(p, []); });

        if (!totalFilas) {
            html = '<div class="text-center text-muted py-5">'
                 + (texto || filtro ? 'Nada coincide con los filtros.' : 'El módulo no tiene funciones configuradas.') + '</div>';
        }
        $('#contenedorMatriz').html(html);
        actualizarBarra();
    }

    function tarjetaPagina(p, camino, texto, filtro) {
        var cuerpo = '', filas = 0, estados = 0;
        $.each(agruparPorEstado(p.funciones), function(bi, b) {
            var visibles = b.filas.filter(function(f) { return filaVisible(f, texto, filtro, p); });
            if (!visibles.length) { return; }
            estados++;
            $.each(visibles, function(fi, f) {
                cuerpo += '<tr' + (fi === 0 ? ' class="epf-grupo-ini"' : '') + '>';
                if (fi === 0) {
                    cuerpo += '<td class="epf-col-estado" rowspan="' + visibles.length + '">' + chipEstado(b.muestra) + '</td>';
                }
                cuerpo += '<td class="epf-col-funcion"' + (f.descripcion ? ' title="' + esc(f.descripcion) + '"' : '') + '>'
                        + (f.icono ? '<i class="' + esc(f.icono) + ' text-muted me-1"></i>' : '')
                        + '<span class="epf-funcion-nombre">' + esc(f.nombre_funcion) + '</span>'
                        + (f.descripcion ? '<div class="epf-funcion-desc">' + esc(f.descripcion) + '</div>' : '')
                        + '</td>'
                        + '<td class="epf-col-pasa">' + textoPasa(f) + '</td>';
                $.each(perfiles, function(pi, perfil) {
                    var fid = f.pagina_funcion_id;
                    var clase = (actual[perfil][fid] ? ' on' : '') + (!!actual[perfil][fid] !== !!original[perfil][fid] ? ' cambio' : '');
                    cuerpo += '<td class="epf-celda epf-marca' + clase + '">'
                            + '<input type="checkbox" class="form-check-input epf-check" data-perfil="' + perfil + '" data-fid="' + fid + '"'
                            + (actual[perfil][fid] ? ' checked' : '') + ' title="' + esc(nombrePerfilCompleto(perfil)) + ': ' + esc(f.nombre_funcion) + '"></td>';
                });
                cuerpo += '</tr>';
                filas++;
            });
        });
        if (!filas) { return {html: '', filas: 0}; }

        var variasEmpresas = !$('#selectEmpresa').val();
        var th = '';
        $.each(perfiles, function(pi, perfil) {
            th += '<th class="epf-th-perfil" title="' + esc(nombrePerfilCompleto(perfil)) + '">'
                + '<span class="epf-th-nombre">' + esc(nombrePerfil(perfil)) + '</span>'
                + (variasEmpresas && perfilesDatos[perfil] ? '<span class="epf-th-empresa">' + esc(perfilesDatos[perfil].empresa_nombre) + '</span>' : '')
                + '<input type="checkbox" class="form-check-input epf-check-pagina" data-perfil="' + perfil + '" title="Permitir / quitar todo lo de esta página">'
                + '</th>';
        });

        var abierta = abiertas[p.pagina_id] || !!texto;
        var html = '<div class="epf-pagina' + (abierta ? ' abierta' : '') + '" data-pagina="' + p.pagina_id + '"'
                 + ' style="margin-left:' + (camino.length * 1.25) + 'rem">'
                 + '<div class="epf-pagina-cab">'
                 + '<i class="fas fa-chevron-right epf-flecha"></i>'
                 + '<span class="epf-pagina-nombre">' + esc(p.pagina_nombre) + '</span>'
                 + '<span class="epf-pagina-url">' + esc(p.ruta || '') + '</span>'
                 + '<span class="epf-contador">' + estados + (estados === 1 ? ' estado' : ' estados') + ' · ' + filas + (filas === 1 ? ' función' : ' funciones') + '</span>'
                 + '</div>'
                 + '<div class="epf-pagina-cuerpo"><div class="table-responsive"><table class="table table-sm table-bordered epf-tabla">'
                 + '<thead><tr><th class="epf-col-estado">Cuando el registro está en</th><th class="epf-col-funcion">Puede</th>'
                 + '<th class="epf-col-pasa">Pasa a</th>' + th + '</tr></thead>'
                 + '<tbody>' + cuerpo + '</tbody></table></div></div></div>';
        return {html: html, filas: filas};
    }

    // Casillas de "toda la página" de cada perfil (marcada, vacía o a medias)
    function actualizarPagina($pag) {
        $pag.find('.epf-check-pagina').each(function() {
            var perfil = $(this).data('perfil');
            var checks = $pag.find('.epf-check[data-perfil="' + perfil + '"]');
            var marcadas = checks.filter(':checked').length;
            this.checked = checks.length > 0 && marcadas === checks.length;
            this.indeterminate = marcadas > 0 && marcadas < checks.length;
        });
    }

    function contarCambios() {
        var n = 0;
        $.each(perfiles, function(i, perfil) {
            var todos = $.extend({}, original[perfil], actual[perfil]);
            $.each(todos, function(fid) { if (!!actual[perfil][fid] !== !!original[perfil][fid]) { n++; } });
        });
        return n;
    }

    function actualizarBarraSolo() {
        var n = contarCambios();
        $('#textoCambios').text(n === 1 ? 'Hay 1 cambio sin guardar' : 'Hay ' + n + ' cambios sin guardar');
        $('#barraGuardar').toggleClass('visible', n > 0);
    }

    function actualizarBarra() {
        $('.epf-pagina').each(function() { actualizarPagina($(this)); });
        actualizarBarraSolo();
    }

    function marcar(perfil, fid, valor, $check) {
        if (valor) { actual[perfil][fid] = true; } else { delete actual[perfil][fid]; }
        $check.prop('checked', valor).closest('.epf-marca')
            .toggleClass('cambio', !!valor !== !!original[perfil][fid])
            .toggleClass('on', !!valor);
    }

    // ---------------------------------------------------------------- eventos
    var $cont = $('#contenedorMatriz');

    $cont.on('click', '.epf-pagina-cab', function() {
        var $pag = $(this).closest('.epf-pagina').toggleClass('abierta');
        abiertas[$pag.data('pagina')] = $pag.hasClass('abierta');
    });

    $cont.on('change', '.epf-check', function() {
        marcar($(this).data('perfil'), $(this).data('fid'), this.checked, $(this));
        actualizarPagina($(this).closest('.epf-pagina'));
        actualizarBarraSolo();
    });

    $cont.on('change', '.epf-check-pagina', function() {
        var valor = this.checked, perfil = $(this).data('perfil');
        var $pag = $(this).closest('.epf-pagina');
        $pag.find('.epf-check[data-perfil="' + perfil + '"]').each(function() {
            marcar(perfil, $(this).data('fid'), valor, $(this));
        });
        actualizarPagina($pag);
        actualizarBarraSolo();
    });

    var temporizador = null;
    $('#filtroTexto').on('input', function() {
        clearTimeout(temporizador);
        temporizador = setTimeout(render, 250);
    });
    $('#filtroAsignacion').on('change', render);

    $('#btnExpandirTodo').on('click', function() {
        $('.epf-pagina').addClass('abierta').each(function() { abiertas[$(this).data('pagina')] = true; });
    });
    $('#btnContraerTodo').on('click', function() {
        $('.epf-pagina').removeClass('abierta');
        abiertas = {};
    });
    $('#btnToggleDescripciones').on('click', function() {
        $cont.toggleClass('epf-ocultar-desc');
    });

    $('#btnDescartar').on('click', function() {
        $.each(perfiles, function(i, p) { actual[p] = $.extend({}, original[p]); });
        render();
    });

    $('#btnGuardar').on('click', function() {
        var cambios = {}, n = 0;
        $.each(perfiles, function(i, perfil) {
            var asignar = [], quitar = [];
            var todos = $.extend({}, original[perfil], actual[perfil]);
            $.each(todos, function(fid) {
                if (actual[perfil][fid] && !original[perfil][fid]) { asignar.push(parseInt(fid, 10)); }
                if (!actual[perfil][fid] && original[perfil][fid]) { quitar.push(parseInt(fid, 10)); }
            });
            if (asignar.length || quitar.length) { cambios[perfil] = {asignar: asignar, quitar: quitar}; n += asignar.length + quitar.length; }
        });
        if (!n) { return; }
        var $btn = $(this).prop('disabled', true);
        $.post(AJAX, {accion: 'guardar_cambios', cambios: JSON.stringify(cambios)}, function(res) {
            if (res && res.success) {
                Swal.fire({icon: 'success', title: 'Guardado', text: res.message, timer: 1600, showConfirmButton: false});
                cargarMatriz(moduloMostrado);
            } else {
                Swal.fire({icon: 'error', title: 'No se pudo guardar', text: (res && res.message) || 'Probá de nuevo.'});
            }
        }, 'json').fail(function() {
            Swal.fire({icon: 'error', title: 'No se pudo guardar', text: 'Error de conexión. Probá de nuevo.'});
        }).always(function() { $btn.prop('disabled', false); });
    });

    // Heredar: se elige un perfil, porque ahora se muestran todos
    $('#btnHeredarTodas').on('click', function() {
        if (!perfiles.length) {
            Swal.fire({icon: 'warning', title: 'Elegí un módulo', text: 'Primero elegí un módulo para ver sus perfiles.'});
            return;
        }
        confirmarDescarte(function() {
            var opciones = {};
            $.each(perfiles, function(i, p) { opciones[p] = nombrePerfilCompleto(p); });
            Swal.fire({
                title: 'Heredar todas las funciones',
                text: 'Se asignan al perfil todas las funciones del módulo que todavía no tiene (en empresas de servicios, sin las páginas de bienes). Se guarda en el momento.',
                input: 'select', inputOptions: opciones, inputPlaceholder: 'Elegí el perfil',
                showCancelButton: true, confirmButtonText: 'Heredar', cancelButtonText: 'Cancelar',
                inputValidator: function(v) { return v ? null : 'Elegí un perfil.'; }
            }).then(function(r) {
                if (!r.isConfirmed) { return; }
                var perfil = parseInt(r.value, 10);
                $.post(AJAX, {
                    accion: 'heredar_paginas_funciones_desde_perfil_base',
                    empresa_perfil_id: perfil, perfil_id_base: 0,
                    empresa_id: perfilesDatos[perfil] ? perfilesDatos[perfil].empresa_id : 0
                }, function(res) {
                    Swal.fire({icon: 'success', title: 'Listo', text: (res && res.mensaje) || 'Funciones heredadas.', timer: 1800, showConfirmButton: false});
                }, 'json').always(function() { cargarMatriz(moduloMostrado); });
            });
        });
    });

    function confirmarDescarte(seguir, cancelar) {
        if (!contarCambios()) { seguir(); return; }
        Swal.fire({
            title: 'Hay cambios sin guardar', text: 'Si seguís, se pierden.', icon: 'warning',
            showCancelButton: true, confirmButtonText: 'Seguir sin guardar', cancelButtonText: 'Volver'
        }).then(function(r) {
            if (r.isConfirmed) { seguir(); } else if (cancelar) { cancelar(); }
        });
    }

    window.addEventListener('beforeunload', function(e) {
        if (paginas.length && contarCambios()) { e.preventDefault(); e.returnValue = ''; }
    });
});
</script>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
