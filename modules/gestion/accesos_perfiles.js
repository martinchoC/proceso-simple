/**
 * accesos_perfiles.js
 * ABM de accesos por perfil. Requiere jQuery + DataTables (línea 1.x, confirmado
 * por consola que el sistema no carga DT 2.x) + SweetAlert2 ya cargados por el
 * template AdminLTE del sistema.
 */
(function () {
    'use strict';

    const AJAX_URL = 'accesos_perfiles_ajax.php';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let tabla = null;
    let perfilActual = null;
    let sucursalActual = null;

    async function llamar(accion, params = {}) {
        const body = new FormData();
        body.append('accion', accion);
        body.append('csrf_token', csrfToken);
        Object.entries(params).forEach(([clave, valor]) => {
            if (valor !== null && valor !== undefined && valor !== '') {
                body.append(clave, valor);
            }
        });

        const resp = await fetch(AJAX_URL, { method: 'POST', body });
        let json;
        try {
            json = await resp.json();
        } catch {
            throw new Error('Respuesta inválida del servidor');
        }
        if (!resp.ok || !json.ok) {
            throw new Error(json.error || 'Error inesperado');
        }
        return json;
    }

    function opcion(valor, texto) {
        const o = document.createElement('option');
        o.value = valor;
        o.textContent = texto; // textContent, nunca innerHTML: evita XSS con nombres cargados por usuarios
        return o;
    }

    function mostrarError(err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
    }

    async function cargarPerfiles() {
        const { data } = await llamar('listar_perfiles');
        const sel = document.getElementById('selPerfil');
        sel.innerHTML = '<option value="">Seleccione un perfil...</option>';
        data.forEach(p => sel.appendChild(
            opcion(p.empresa_perfil_id, `${p.empresa_perfil_nombre}${p.perfil_nombre ? ' (' + p.perfil_nombre + ')' : ''}`)
        ));
    }

    async function cargarSucursales() {
        const { data } = await llamar('listar_sucursales');
        const sel = document.getElementById('selSucursal');
        sel.innerHTML = '<option value="">Seleccione...</option>';
        data.forEach(s => sel.appendChild(opcion(s.sucursal_id, s.sucursal_nombre)));
    }

    async function cargarPuntosVenta(sucursalId) {
        const selPv = document.getElementById('selPuntoVenta');
        const selTipo = document.getElementById('selTipoComprobante');
        selPv.innerHTML = '<option value="">Todos los puntos de venta</option>';
        selTipo.innerHTML = '<option value="">Todos los tipos habilitados</option>';
        selTipo.disabled = true;

        if (!sucursalId) {
            selPv.disabled = true;
            return;
        }
        const { data } = await llamar('listar_puntos_venta', { sucursal_id: sucursalId });
        data.forEach(pv => selPv.appendChild(opcion(pv.punto_venta_id, pv.nombre)));
        selPv.disabled = false;
    }

    async function cargarTiposComprobante(sucursalId, puntoVentaId) {
        const selTipo = document.getElementById('selTipoComprobante');
        selTipo.innerHTML = '<option value="">Todos los tipos habilitados</option>';

        if (!puntoVentaId) {
            selTipo.disabled = true;
            return;
        }
        const { data } = await llamar('listar_tipos_comprobante', {
            sucursal_id: sucursalId,
            punto_venta_id: puntoVentaId
        });
        data.forEach(t => selTipo.appendChild(opcion(t.comprobante_tipo_id, `${t.codigo} - ${t.comprobante_tipo}`)));
        selTipo.disabled = false;
    }

    function inicializarTabla() {
        tabla = $('#tblGrants').DataTable({
            data: [],
            columns: [
                { data: 'sucursal_nombre' },
                { data: 'punto_venta_nombre', defaultContent: 'Todos' },
                { data: 'comprobante_tipo', defaultContent: 'Todos' },
                {
                    data: 'perfil_sucursal_id',
                    orderable: false,
                    render: (id) => `<button type="button" class="btn btn-danger btn-xs btn-eliminar" data-id="${id}"><i class="fas fa-trash"></i></button>`
                }
            ],
            paging: true,
            info: false
        });

        $('#tblGrants tbody').on('click', '.btn-eliminar', async function () {
            const id = $(this).data('id');
            const confirmado = await Swal.fire({
                title: '¿Eliminar este acceso?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar'
            });
            if (!confirmado.isConfirmed) return;

            try {
                await llamar('eliminar_grant', {
                    empresa_perfil_id: perfilActual,
                    perfil_sucursal_id: id
                });
                await recargarGrants();
                Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1200, showConfirmButton: false });
            } catch (e) {
                mostrarError(e);
            }
        });
    }

    async function recargarGrants() {
        if (!perfilActual) return;
        const { data } = await llamar('listar_grants', { empresa_perfil_id: perfilActual });
        tabla.clear().rows.add(data).draw();
    }

    document.addEventListener('DOMContentLoaded', () => {
        inicializarTabla();
        cargarPerfiles().catch(mostrarError);

        document.getElementById('selPerfil').addEventListener('change', async function () {
            perfilActual = this.value || null;
            document.getElementById('cardAgregar').style.display = perfilActual ? '' : 'none';
            document.getElementById('btnAgregarGrant').disabled = !perfilActual;

            if (perfilActual) {
                try {
                    await cargarSucursales();
                    await recargarGrants();
                } catch (e) {
                    mostrarError(e);
                }
            } else {
                tabla.clear().draw();
            }
        });

        document.getElementById('selSucursal').addEventListener('change', function () {
            sucursalActual = this.value || null;
            cargarPuntosVenta(sucursalActual).catch(mostrarError);
        });

        document.getElementById('selPuntoVenta').addEventListener('change', function () {
            cargarTiposComprobante(sucursalActual, this.value || null).catch(mostrarError);
        });

        document.getElementById('btnAgregarGrant').addEventListener('click', async function () {
            const sucursalId = document.getElementById('selSucursal').value;
            const puntoVentaId = document.getElementById('selPuntoVenta').value;
            const comprobanteTipoId = document.getElementById('selTipoComprobante').value;

            if (!sucursalId) {
                Swal.fire({ icon: 'warning', title: 'Falta la sucursal' });
                return;
            }

            try {
                await llamar('agregar_grant', {
                    empresa_perfil_id: perfilActual,
                    sucursal_id: sucursalId,
                    punto_venta_id: puntoVentaId,
                    comprobante_tipo_id: comprobanteTipoId
                });
                await recargarGrants();
                Swal.fire({ icon: 'success', title: 'Acceso agregado', timer: 1200, showConfirmButton: false });
            } catch (e) {
                mostrarError(e);
            }
        });
    });
})();
