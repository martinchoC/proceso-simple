<?php
/**
 * Vista: ABM de Roles de Entidades y Cuentas Contables por Empresa
 * Módulo: Gestión - Seteo Empresas (padre_id = 37, orden = 431)
 * Página ID: 119
 */

$pageTitle = "Entidades - Roles y Cuentas Contables";
$currentPage = 'empresas_roles_cuentas';
$modulo_idx = 2;
$pagina_idx = 119;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id_activa = intval($_SESSION['empresa_id'] ?? 0);
?>

<main class="app-main">
    <!-- Header -->
    <?php ui_encabezado('Roles y cuentas de entidades','fas fa-users-cog', 'Definición de roles activos por empresa y su cuenta contable genérica por defecto para asientos', ['Seteo Empresas']); ?>

    <!-- Contenido -->
    <div class="app-content">
        <div class="container-fluid">
            
            <!-- Barra superior: Selector de Empresa y Botón Nuevo Rol -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row align-items-center g-2">
                        <div class="col-auto">
                            <label for="selectEmpresaFiltro" class="col-form-label fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa Activa:
                            </label>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <select class="form-select form-select-sm" id="selectEmpresaFiltro">
                                <option value="<?= $empresa_id_activa ?>">Empresa actual (ID: <?= $empresa_id_activa ?>)</option>
                            </select>
                        </div>
                        <div class="col-auto ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarTabla" title="Recargar tabla">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevoRolEmpresa" data-funcion="agregar">
                                <i class="fas fa-plus-circle me-1"></i>Dar de Alta Rol para la Empresa
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Principal con DataTable -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0 fs-6 fw-bold text-dark">
                        <i class="fas fa-list-check me-2 text-info"></i>Roles Configurados y Cuentas Imputables
                    </h6>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="badgeTotalRoles">0 roles</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablaRolesEmpresa" class="table table-hover table-striped table-bordered align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th width="100" class="text-center">Código</th>
                                    <th width="180">Rol de Entidad</th>
                                    <th>Descripción del Rol</th>
                                    <th>Cuenta Contable Genérica Asociada</th>
                                    <th width="100" class="text-center">Estado</th>
                                    <th width="120" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Cargando roles de la empresa...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light py-2 small text-muted">
                    <i class="fas fa-info-circle text-primary me-1"></i>
                    La cuenta contable definida aquí se precarga como valor genérico al asignar este rol en el ABM de 
                    <strong>Entidades</strong>, <strong>Clientes</strong> o <strong>Proveedores</strong>. 
                    Luego, para cada entidad puntual, dicha cuenta puede modificarse según corresponda.
                </div>
            </div>

        </div>
    </div>

    <!-- Modal: Dar de Alta Rol para la Empresa -->
    <div class="modal fade" id="modalAltaRol" tabindex="-1" aria-labelledby="modalAltaRolLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content shadow border-0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6 fw-bold" id="modalAltaRolLabel">
                        <i class="fas fa-plus-circle me-2"></i>Dar de Alta Rol y Cuenta Genérica para la Empresa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formAltaRol" class="needs-validation" novalidate>
                    <div class="modal-body py-3">
                        
                        <!-- Toggle: Seleccionar existente o Crear nuevo -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <label class="form-label fw-bold text-dark d-block mb-2">
                                <i class="fas fa-tags me-1 text-primary"></i>Origen del Rol:
                            </label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipo_alta_rol" id="tipoAltaExistente" value="existente" checked>
                                <label class="form-check-label fw-bold" for="tipoAltaExistente">Seleccionar rol del catálogo general</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipo_alta_rol" id="tipoAltaNuevo" value="nuevo">
                                <label class="form-check-label fw-bold text-primary" for="tipoAltaNuevo">
                                    <i class="fas fa-magic me-1"></i>Crear un nuevo tipo de rol
                                </label>
                            </div>
                        </div>

                        <!-- Panel 1: Seleccionar del catálogo -->
                        <div id="seccionRolExistente" class="mb-3">
                            <label for="selectRolEntidad" class="form-label fw-bold text-dark">
                                <i class="fas fa-user-tag text-secondary me-1"></i>Rol de Entidad <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="selectRolEntidad" required>
                                <option value="">Seleccione un rol disponible...</option>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar un rol.</div>
                            <div class="form-text small" id="descripcionRolSeleccionado"></div>
                        </div>

                        <!-- Panel 2: Crear nuevo rol -->
                        <div id="seccionRolNuevo" class="mb-3" style="display:none;">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label for="nuevo_rol_codigo" class="form-label fw-bold text-dark">
                                        Código Corto <span class="text-danger">*</span> <small class="text-muted">(ej: COM, ASOC)</small>
                                    </label>
                                    <input type="text" class="form-control text-uppercase font-monospace" id="nuevo_rol_codigo" maxlength="20" placeholder="Ej: COM">
                                </div>
                                <div class="col-md-8 mb-2">
                                    <label for="nuevo_rol_nombre" class="form-label fw-bold text-dark">
                                        Nombre del Rol <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="nuevo_rol_nombre" maxlength="50" placeholder="Ej: Comisionista / Intermediario">
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="nuevo_rol_descripcion" class="form-label fw-bold text-dark">
                                        Descripción o Finalidad
                                    </label>
                                    <input type="text" class="form-control" id="nuevo_rol_descripcion" maxlength="200" placeholder="Ej: Intermediario comercial para liquidación de comisiones">
                                </div>
                            </div>
                        </div>

                        <!-- Selector de Cuenta Contable Genérica -->
                        <div class="mb-3">
                            <label for="selectCuentaContableAlta" class="form-label fw-bold text-dark">
                                <i class="fas fa-book text-success me-1"></i>Cuenta Contable Genérica (por defecto) <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="selectCuentaContableAlta" required>
                                <option value="">Cargando cuentas contables...</option>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar una cuenta contable genérica.</div>
                            <div class="form-text small text-muted">
                                Esta es la cuenta contable de imputación (ej. Deudores por Ventas, Proveedores Nacionales, Impuestos, Anticipos, etc.) que se sugerirá al dar de alta el rol a una entidad.
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarAltaRol">
                            <i class="fas fa-save me-1"></i>Guardar Rol y Cuenta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Cuenta Contable de Rol -->
    <div class="modal fade" id="modalEditarRol" tabindex="-1" aria-labelledby="modalEditarRolLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow border-0">
                <div class="modal-header bg-info text-white py-2">
                    <h5 class="modal-title fs-6 fw-bold" id="modalEditarRolLabel">
                        <i class="fas fa-edit me-2"></i>Editar Cuenta Contable Genérica
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formEditarRol" class="needs-validation" novalidate>
                    <input type="hidden" id="edit_empresa_rol_cuenta_id" value="">
                    <div class="modal-body py-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Rol:</label>
                            <div class="p-2 bg-light rounded border d-flex align-items-center justify-content-between">
                                <span class="fw-bold" id="edit_rol_nombre">-</span>
                                <span class="badge bg-primary font-monospace" id="edit_rol_codigo">-</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="selectCuentaContableEdit" class="form-label fw-bold text-dark">
                                <i class="fas fa-book text-success me-1"></i>Cuenta Contable Genérica <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="selectCuentaContableEdit" required>
                                <option value="">Seleccionar cuenta...</option>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar una cuenta contable.</div>
                        </div>

                        <div class="mb-2">
                            <label for="selectEstadoEdit" class="form-label fw-bold text-dark">Estado del Rol en la Empresa:</label>
                            <select class="form-select form-select-sm" id="selectEstadoEdit">
                                <option value="1">Activo</option>
                                <option value="2">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarEdicionRol">
                            <i class="fas fa-save me-1"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script>
$(document).ready(function () {
    var tablaRoles;
    var empresaActualId = <?= $empresa_id_activa ?>;
    var catalogoCuentas = [];
    var catalogoRolesDisponibles = [];

    // Cargar selector de empresas si hay acceso
    function cargarEmpresasFiltro() {
        $.get('empresas_parametros_ajax.php', { accion: 'obtener_empresas' }, function (res) {
            if (res && res.resultado && res.data && res.data.length > 0) {
                var select = $('#selectEmpresaFiltro');
                select.empty();
                res.data.forEach(function (emp) {
                    var selected = (parseInt(emp.empresa_id) === empresaActualId) ? 'selected' : '';
                    select.append(`<option value="${emp.empresa_id}" ${selected}>${emp.nombre || emp.razon_social} (ID: ${emp.empresa_id})</option>`);
                });
            }
        }, 'json');
    }
    cargarEmpresasFiltro();

    $('#selectEmpresaFiltro').on('change', function () {
        empresaActualId = parseInt($(this).val()) || empresaActualId;
        cargarTablaRoles();
        cargarCuentasContables();
    });

    // Cargar Plan de Cuentas para los selects
    function cargarCuentasContables(callback) {
        return $.get('empresas_roles_cuentas_ajax.php', {
            accion: 'obtener_cuentas_contables',
            empresa_idx: empresaActualId
        }, function (cuentas) {
            catalogoCuentas = Array.isArray(cuentas) ? cuentas : [];
            var opciones = '<option value="">Seleccione una cuenta contable imputable...</option>';
            catalogoCuentas.forEach(function (c) {
                var prefix = '';
                for (var i = 1; i < c.nivel; i++) {
                    prefix += '&nbsp;&nbsp;';
                }
                opciones += `<option value="${c.cont_cuenta_id}">${prefix}${c.descripcion_completa}</option>`;
            });
            $('#selectCuentaContableAlta').html(opciones);
            $('#selectCuentaContableEdit').html(opciones);
            if (typeof callback === 'function') {
                callback(catalogoCuentas);
            }
        }, 'json');
    }
    cargarCuentasContables();

    // Inicializar DataTable
    function inicializarTablaRoles() {
        tablaRoles = $('#tablaRolesEmpresa').DataTable({
            ajax: {
                url: 'empresas_roles_cuentas_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_roles_empresa';
                    d.empresa_idx = empresaActualId;
                },
                dataSrc: function (json) {
                    var lista = (json && json.data) ? json.data : [];
                    $('#badgeTotalRoles').text(lista.length + ' rol' + (lista.length === 1 ? '' : 'es'));
                    return lista;
                }
            },
            columns: [
                {
                    data: 'rol_entidad_codigo',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-secondary-subtle text-dark border font-monospace px-2 py-1 fs-7">${data}</span>`;
                    }
                },
                {
                    data: 'rol_entidad_nombre',
                    render: function (data) {
                        return `<span class="fw-bold text-dark"><i class="fas fa-tag text-primary me-2"></i>${data}</span>`;
                    }
                },
                {
                    data: 'rol_entidad_descripcion',
                    render: function (data) {
                        return data ? `<span class="text-muted small">${data}</span>` : '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: 'cuenta_descripcion',
                    render: function (data, type, row) {
                        if (row.cont_cuenta_id) {
                            return `<span class="fw-semibold text-success"><i class="fas fa-book me-1"></i>${row.cuenta_codigo} - ${row.cuenta_nombre}</span>`;
                        }
                        return `<span class="text-muted fst-italic">Sin cuenta asignada</span>`;
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (data) {
                        if (parseInt(data) === 1) {
                            return '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Activo</span>';
                        }
                        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fas fa-times-circle me-1"></i>Inactivo</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        var id = row.empresa_rol_cuenta_id;
                        var btnEditar = `<button type="button" class="btn btn-xs btn-outline-primary btn-editar-rol me-1" data-funcion="editar" data-id="${id}" title="Editar cuenta"><i class="fas fa-edit"></i></button>`;
                        var btnToggle = '';
                        if (parseInt(row.tabla_estado_registro_id) === 1) {
                            btnToggle = `<button type="button" class="btn btn-xs btn-outline-danger btn-cambiar-estado" data-funcion="inactivar" data-id="${id}" data-estado="2" title="Inhabilitar"><i class="fas fa-ban"></i></button>`;
                        } else {
                            btnToggle = `<button type="button" class="btn btn-xs btn-outline-success btn-cambiar-estado" data-funcion="inactivar" data-id="${id}" data-estado="1" title="Habilitar"><i class="fas fa-check"></i></button>`;
                        }
                        return `<div class="btn-group">${btnEditar}${btnToggle}</div>`;
                    }
                }
            ],
            language: {
                url: "<?= asset_local('js/datatables-i18n-1.13.6/es-ES.json') ?>"
            },
            responsive: true,
            pageLength: 25,
            order: [[0, 'asc']]
        });
    }

    inicializarTablaRoles();

    function cargarTablaRoles() {
        if (tablaRoles) {
            tablaRoles.ajax.reload(null, false);
        }
    }

    $('#btnRecargarTabla').on('click', function () {
        cargarTablaRoles();
        cargarCuentasContables();
    });

    // Cargar roles disponibles al abrir modal de alta
    function cargarRolesDisponibles() {
        $.get('empresas_roles_cuentas_ajax.php', {
            accion: 'obtener_roles_disponibles',
            empresa_idx: empresaActualId
        }, function (roles) {
            catalogoRolesDisponibles = Array.isArray(roles) ? roles : [];
            var select = $('#selectRolEntidad');
            select.empty().append('<option value="">Seleccione un rol disponible...</option>');
            catalogoRolesDisponibles.forEach(function (r) {
                select.append(`<option value="${r.rol_entidad_id}" data-desc="${r.rol_entidad_descripcion || ''}">${r.rol_entidad_nombre} (${r.rol_entidad_codigo})</option>`);
            });
        }, 'json');
    }

    $('#selectRolEntidad').on('change', function () {
        var opt = $(this).find('option:selected');
        var desc = opt.data('desc');
        $('#descripcionRolSeleccionado').text(desc ? desc : '');
    });

    // Abrir Modal de Alta
    $('#btnNuevoRolEmpresa').on('click', function () {
        $('#formAltaRol')[0].reset();
        $('#formAltaRol').removeClass('was-validated');
        $('#tipoAltaExistente').prop('checked', true);
        $('#seccionRolExistente').show();
        $('#seccionRolNuevo').hide();
        $('#selectRolEntidad').prop('required', true);
        $('#nuevo_rol_codigo, #nuevo_rol_nombre').prop('required', false);
        $('#descripcionRolSeleccionado').text('');
        cargarRolesDisponibles();
        cargarCuentasContables();
        var modal = new bootstrap.Modal(document.getElementById('modalAltaRol'));
        modal.show();
    });

    // Alternar tipo de alta (existente vs nuevo)
    $('input[name="tipo_alta_rol"]').on('change', function () {
        if ($(this).val() === 'nuevo') {
            $('#seccionRolExistente').slideUp(150);
            $('#seccionRolNuevo').slideDown(150);
            $('#selectRolEntidad').prop('required', false);
            $('#nuevo_rol_codigo, #nuevo_rol_nombre').prop('required', true);
        } else {
            $('#seccionRolNuevo').slideUp(150);
            $('#seccionRolExistente').slideDown(150);
            $('#selectRolEntidad').prop('required', true);
            $('#nuevo_rol_codigo, #nuevo_rol_nombre').prop('required', false);
        }
    });

    // Enviar Alta de Rol
    $('#formAltaRol').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var esNuevo = $('input[name="tipo_alta_rol"]:checked').val() === 'nuevo';
        var btn = $('#btnGuardarAltaRol');
        var origText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'empresas_roles_cuentas_ajax.php',
            type: 'POST',
            data: {
                accion: 'guardar_rol_empresa',
                empresa_idx: empresaActualId,
                rol_entidad_id: $('#selectRolEntidad').val(),
                cont_cuenta_id: $('#selectCuentaContableAlta').val(),
                crear_nuevo_rol: esNuevo ? 1 : 0,
                nuevo_rol_codigo: $('#nuevo_rol_codigo').val().trim().toUpperCase(),
                nuevo_rol_nombre: $('#nuevo_rol_nombre').val().trim(),
                nuevo_rol_descripcion: $('#nuevo_rol_descripcion').val().trim()
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(origText);
                if (res && res.resultado) {
                    bootstrap.Modal.getInstance(document.getElementById('modalAltaRol')).hide();
                    cargarTablaRoles();
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: res.mensaje || 'Rol y cuenta guardados con éxito',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire('Error', (res && res.error) || 'Error al guardar el rol', 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html(origText);
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Abrir Modal de Edición
    $(document).on('click', '.btn-editar-rol', function () {
        var id = $(this).data('id');

        function abrirModalEdicion(d) {
            $('#edit_empresa_rol_cuenta_id').val(d.empresa_rol_cuenta_id);
            $('#edit_rol_nombre').text(d.rol_entidad_nombre);
            $('#edit_rol_codigo').text(d.rol_entidad_codigo);
            $('#selectCuentaContableEdit').val(d.cont_cuenta_id || '');
            $('#selectEstadoEdit').val(d.tabla_estado_registro_id || 1);
            $('#formEditarRol').removeClass('was-validated');
            var modal = new bootstrap.Modal(document.getElementById('modalEditarRol'));
            modal.show();
        }

        $.get('empresas_roles_cuentas_ajax.php', {
            accion: 'obtener_rol_empresa',
            empresa_rol_cuenta_id: id,
            empresa_idx: empresaActualId
        }, function (res) {
            if (res && res.resultado && res.data) {
                var d = res.data;
                if (!catalogoCuentas || catalogoCuentas.length === 0) {
                    cargarCuentasContables(function () {
                        abrirModalEdicion(d);
                    });
                } else {
                    abrirModalEdicion(d);
                }
            } else {
                Swal.fire('Error', (res && res.error) || 'No se pudieron obtener los datos', 'error');
            }
        }, 'json');
    });

    // Enviar Edición
    $('#formEditarRol').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var btn = $('#btnGuardarEdicionRol');
        var origText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...');

        $.ajax({
            url: 'empresas_roles_cuentas_ajax.php',
            type: 'POST',
            data: {
                accion: 'editar_rol_empresa',
                empresa_idx: empresaActualId,
                empresa_rol_cuenta_id: $('#edit_empresa_rol_cuenta_id').val(),
                cont_cuenta_id: $('#selectCuentaContableEdit').val(),
                tabla_estado_registro_id: $('#selectEstadoEdit').val()
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(origText);
                if (res && res.resultado) {
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarRol')).hide();
                    cargarTablaRoles();
                    Swal.fire({
                        icon: 'success',
                        title: '¡Actualizado!',
                        text: res.mensaje || 'Cuenta contable actualizada con éxito',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire('Error', (res && res.error) || 'Error al actualizar', 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html(origText);
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Cambiar Estado (Inhabilitar / Habilitar)
    $(document).on('click', '.btn-cambiar-estado', function () {
        var id = $(this).data('id');
        var nuevoEstado = $(this).data('estado');
        var accionTxt = (nuevoEstado === 2) ? 'inhabilitar' : 'habilitar';

        Swal.fire({
            title: `¿Desea ${accionTxt} este rol?`,
            text: (nuevoEstado === 2) 
                ? 'El rol dejará de sugerirse por defecto para nuevas entidades en esta empresa.' 
                : 'El rol volverá a estar disponible con su cuenta contable genérica.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: (nuevoEstado === 2) ? '#d33' : '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Sí, ${accionTxt}`,
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'empresas_roles_cuentas_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'cambiar_estado_rol_empresa',
                        empresa_idx: empresaActualId,
                        empresa_rol_cuenta_id: id,
                        tabla_estado_registro_id: nuevoEstado
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res && res.resultado) {
                            cargarTablaRoles();
                            Swal.fire({
                                icon: 'success',
                                title: 'Completado',
                                text: res.mensaje,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        } else {
                            Swal.fire('Error', (res && res.error) || 'Error al cambiar estado', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
