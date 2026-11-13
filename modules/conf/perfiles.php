<?php
// Configuración de la página
$pageTitle = "Perfiles";
$currentPage = 'perfiles';
$modudo_idx = 1;
$pagina_idx = 19; // conf__paginas
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<style>
    .prf-filtros .form-label { font-size: .8rem; color: #6c757d; margin-bottom: .15rem; }
    .prf-modulo { margin: 1.25rem 0 .6rem; display: flex; align-items: baseline; gap: .5rem; border-bottom: 1px solid #dee2e6; padding-bottom: .3rem; }
    .prf-modulo h5 { margin: 0; font-weight: 600; }
    .prf-modulo small { color: #6c757d; }
    .prf-card { border: 1px solid #dee2e6; border-radius: .5rem; background: #fff; height: 100%; display: flex; flex-direction: column; transition: box-shadow .15s; }
    .prf-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.08); }
    .prf-card.inactivo { opacity: .6; background: #f8f9fa; }
    .prf-card-cuerpo { padding: .8rem .9rem .6rem; flex: 1; }
    .prf-nombre { font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
    .prf-id { color: #adb5bd; font-size: .72rem; font-weight: 400; margin-left: auto; }
    .prf-datos { display: flex; gap: 1rem; margin-top: .6rem; }
    .prf-dato { line-height: 1.1; }
    .prf-dato b { display: block; font-size: 1.15rem; }
    .prf-dato span { font-size: .72rem; color: #6c757d; }
    .prf-card-pie { border-top: 1px solid #f1f3f5; padding: .45rem .6rem; display: flex; gap: .3rem; }
    .prf-card-pie .btn { font-size: .78rem; }
    .prf-vacio { color: #adb5bd; font-size: .85rem; padding: .5rem 0; }
</style>

<main class="app-main">
    <?php ui_encabezado('Perfiles base', 'bi bi-person-badge', 'Plantillas de perfil por módulo. Al habilitar un módulo en una empresa se copian como perfiles de esa empresa, que después se ajustan en Funciones por perfil.', ['Empresas y accesos']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-3">
                <div class="card-body prf-filtros">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="filtroTexto">Buscar</label>
                            <input type="text" class="form-control form-control-sm" id="filtroTexto" placeholder="Nombre del perfil...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filtroModulo">Módulo</label>
                            <select class="form-select form-select-sm" id="filtroModulo">
                                <option value="">Todos los módulos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" id="filtroInactivos">
                                <label class="form-check-label small" for="filtroInactivos">Mostrar inactivos</label>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-sm btn-primary w-100" id="btnNuevo"><i class="fas fa-plus"></i> Nuevo perfil</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="contenedorPerfiles">
                <div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
            </div>
        </div>
    </div>
</main>

<!-- Modal -->
<div class="modal fade" id="modalPerfil" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Perfil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formPerfil" novalidate>
            <input type="hidden" id="perfil_id" name="perfil_id" />
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="modulo_id">Módulo *</label>
                    <select class="form-select" id="modulo_id" name="modulo_id" required>
                        <option value="">Elegí un módulo</option>
                    </select>
                    <div class="form-text" id="ayudaModulo"></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="perfil_nombre">Nombre *</label>
                    <input type="text" class="form-control" id="perfil_nombre" name="perfil_nombre" maxlength="100" required/>
                </div>
                <div class="col-12">
                    <label class="form-label" for="tipo_operacion">Tipo de empresa</label>
                    <select class="form-select" id="tipo_operacion" name="tipo_operacion">
                        <option value="TODAS">Todas las empresas</option>
                        <option value="BIENES">Solo empresas que venden bienes</option>
                        <option value="SERVICIOS">Solo empresas de servicios</option>
                    </select>
                    <div class="form-text">Al copiar el perfil a una empresa de servicios se omiten las páginas de bienes (stock, remitos, depósitos).</div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="perfil_activo" checked>
                        <label class="form-check-label" for="perfil_activo">Perfil activo</label>
                    </div>
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button id="btnGuardar" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    var AJAX = 'perfiles_ajax.php';
    var modulos = [];
    var perfiles = [];
    var modal = new bootstrap.Modal(document.getElementById('modalPerfil'));

    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function num(n) { return window.Fmt ? Fmt.numero(n, 0) : n; }
    function plural(n, uno, varios) { return n === 1 ? uno : varios; }

    $.get(AJAX, {accion: 'obtener_modulos'}, function(res) {
        modulos = res || [];
        $.each(modulos, function(i, m) {
            $('#filtroModulo, #modulo_id').append($('<option>', {value: m.modulo_id, text: m.modulo}));
        });
        cargar();
    }, 'json');

    function cargar() {
        $.get(AJAX, {accion: 'listar'}, function(res) {
            perfiles = res || [];
            render();
        }, 'json').fail(function() {
            $('#contenedorPerfiles').html('<div class="alert alert-danger">No se pudieron cargar los perfiles.</div>');
        });
    }

    function render() {
        var texto = $('#filtroTexto').val().trim().toLowerCase();
        var modulo = $('#filtroModulo').val();
        var inactivos = $('#filtroInactivos').is(':checked');
        var html = '';

        $.each(modulos, function(i, m) {
            if (modulo && String(m.modulo_id) !== modulo) { return; }
            var delModulo = perfiles.filter(function(p) { return p.modulo_id === parseInt(m.modulo_id, 10); });
            var activos = delModulo.filter(function(p) { return p.tabla_estado_registro_id === 1; }).length;
            var visibles = delModulo.filter(function(p) {
                return (inactivos || p.tabla_estado_registro_id === 1)
                    && (!texto || p.perfil_nombre.toLowerCase().indexOf(texto) >= 0);
            });
            if (texto && !visibles.length) { return; }

            html += '<div class="prf-modulo"><h5>' + esc(m.modulo) + '</h5><small>' + activos + ' '
                  + plural(activos, 'perfil activo', 'perfiles activos') + '</small></div>';
            if (!visibles.length) {
                html += '<div class="prf-vacio">Sin perfiles base. Usá <strong>Nuevo perfil</strong> para crear uno.</div>';
                return;
            }
            html += '<div class="row g-2 row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4">';
            $.each(visibles, function(j, p) { html += tarjeta(p); });
            html += '</div>';
        });

        $('#contenedorPerfiles').html(html || '<div class="text-center text-muted py-5">Nada coincide con los filtros.</div>');
    }

    function tarjeta(p) {
        var activo = p.tabla_estado_registro_id === 1;
        var badges = '';
        if (p.tipo_operacion === 'BIENES') { badges += '<span class="badge bg-warning text-dark" title="Solo empresas que venden bienes">Bienes</span>'; }
        if (p.tipo_operacion === 'SERVICIOS') { badges += '<span class="badge bg-info text-dark" title="Solo empresas de servicios">Servicios</span>'; }
        if (!activo) { badges += '<span class="badge bg-secondary">Inactivo</span>'; }
        if (activo && !p.funciones) { badges += '<span class="badge bg-danger" title="El perfil no tiene funciones: al copiarlo, sus usuarios no verán nada">Sin funciones</span>'; }

        var urlFunciones = 'perfiles_funciones.php?ver_modulo=' + p.modulo_id + '&ver_perfil=' + p.perfil_id;
        return '<div class="col"><div class="prf-card' + (activo ? '' : ' inactivo') + '">'
             + '<div class="prf-card-cuerpo">'
             + '<div class="prf-nombre"><i class="fas fa-user-shield text-primary"></i>' + esc(p.perfil_nombre) + badges
             + '<span class="prf-id">#' + p.perfil_id + '</span></div>'
             + '<div class="prf-datos">'
             + '<div class="prf-dato"><b>' + num(p.funciones) + '</b><span>' + plural(p.funciones, 'función', 'funciones') + '</span></div>'
             + '<div class="prf-dato"><b>' + num(p.paginas) + '</b><span>' + plural(p.paginas, 'página', 'páginas') + '</span></div>'
             + '<div class="prf-dato" title="' + p.perfiles_empresa + ' ' + plural(p.perfiles_empresa, 'perfil de empresa copiado', 'perfiles de empresa copiados') + ' de este">'
             + '<b>' + num(p.empresas) + '</b><span>' + plural(p.empresas, 'empresa lo usa', 'empresas lo usan') + '</span></div>'
             + '</div></div>'
             + '<div class="prf-card-pie">'
             + '<a class="btn btn-sm btn-outline-primary" href="' + urlFunciones + '"><i class="fas fa-key"></i> Funciones</a>'
             + '<button class="btn btn-sm btn-outline-secondary btnEditar ms-auto" data-id="' + p.perfil_id + '" title="Editar"><i class="fas fa-pencil-alt"></i></button>'
             + (activo
                 ? '<button class="btn btn-sm btn-outline-danger btnEstado" data-id="' + p.perfil_id + '" data-estado="2" title="Inactivar"><i class="fas fa-ban"></i></button>'
                 : '<button class="btn btn-sm btn-outline-success btnEstado" data-id="' + p.perfil_id + '" data-estado="1" title="Activar"><i class="fas fa-check"></i></button>')
             + '</div></div></div>';
    }

    var temporizador = null;
    $('#filtroTexto').on('input', function() { clearTimeout(temporizador); temporizador = setTimeout(render, 200); });
    $('#filtroModulo, #filtroInactivos').on('change', render);

    // ---------------------------------------------------------------- alta / edición
    function abrirModal(p) {
        var form = document.getElementById('formPerfil');
        form.reset();
        form.classList.remove('was-validated');
        $('#perfil_id').val(p ? p.perfil_id : '');
        $('#modulo_id').val(p ? p.modulo_id : ($('#filtroModulo').val() || ''));
        $('#perfil_nombre').val(p ? p.perfil_nombre : '');
        $('#tipo_operacion').val(p ? (p.tipo_operacion || 'TODAS') : 'TODAS');
        $('#perfil_activo').prop('checked', p ? p.tabla_estado_registro_id === 1 : true);
        // Con funciones asignadas no se cambia de módulo (quedarían funciones de otro módulo)
        $('#modulo_id').prop('disabled', !!(p && p.funciones > 0));
        $('#ayudaModulo').text(p && p.funciones > 0 ? 'No se puede cambiar: el perfil ya tiene funciones asignadas.' : '');
        $('#modalLabel').text(p ? 'Editar perfil' : 'Nuevo perfil');
        modal.show();
    }

    $('#btnNuevo').on('click', function() { abrirModal(null); });

    $('#contenedorPerfiles').on('click', '.btnEditar', function() {
        var id = parseInt($(this).data('id'), 10);
        abrirModal(perfiles.find(function(p) { return p.perfil_id === id; }));
    });

    $('#btnGuardar').on('click', function() {
        var form = document.getElementById('formPerfil');
        if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
        var id = $('#perfil_id').val();
        var $btn = $(this).prop('disabled', true);
        $.post(AJAX, {
            accion: id ? 'editar' : 'agregar',
            perfil_id: id,
            modulo_id: $('#modulo_id').val(),
            perfil_nombre: $('#perfil_nombre').val().trim(),
            tipo_operacion: $('#tipo_operacion').val(),
            tabla_estado_registro_id: $('#perfil_activo').is(':checked') ? 1 : 2
        }, function(res) {
            if (res && res.success) {
                modal.hide();
                Swal.fire({icon: 'success', title: 'Listo', text: res.message, timer: 1400, showConfirmButton: false});
                cargar();
            } else {
                Swal.fire({icon: 'error', title: 'No se pudo guardar', text: (res && res.message) || 'Probá de nuevo.'});
            }
        }, 'json').fail(function() {
            Swal.fire({icon: 'error', title: 'No se pudo guardar', text: 'Error de conexión. Probá de nuevo.'});
        }).always(function() { $btn.prop('disabled', false); });
    });

    // ---------------------------------------------------------------- activar / inactivar
    $('#contenedorPerfiles').on('click', '.btnEstado', function() {
        var id = parseInt($(this).data('id'), 10), estado = parseInt($(this).data('estado'), 10);
        var p = perfiles.find(function(x) { return x.perfil_id === id; });
        var activar = estado === 1;
        Swal.fire({
            title: (activar ? '¿Activar ' : '¿Inactivar ') + (p ? '"' + p.perfil_nombre + '"' : 'el perfil') + '?',
            text: activar ? 'Vuelve a estar disponible para copiar a las empresas.'
                          : 'Deja de ofrecerse al copiar perfiles a una empresa. Los perfiles de empresa que ya se copiaron no cambian.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: activar ? 'Sí, activar' : 'Sí, inactivar', cancelButtonText: 'Cancelar'
        }).then(function(r) {
            if (!r.isConfirmed) { return; }
            $.post(AJAX, {accion: 'cambiar_estado', perfil_id: id, nuevo_estado: estado}, function(res) {
                if (res && res.success) { cargar(); }
                else { Swal.fire({icon: 'error', title: 'No se pudo cambiar', text: (res && res.message) || 'Probá de nuevo.'}); }
            }, 'json');
        });
    });
});
</script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
