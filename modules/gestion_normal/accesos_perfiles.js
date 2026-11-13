/**
 * accesos_perfiles.js
 * Lógica cliente para administración de accesos por perfil
 */
(function () {
    'use strict';

    const AJAX_URL = 'accesos_perfiles_ajax.php';
    const urlParams = new URLSearchParams(window.location.search);
    const empresaId = urlParams.get('empresa_id') || String(window.ACCESOS_EMPRESA_ID || '');

    let tabla = null;
    let perfilActual = null;

    async function llamar(accion, params = {}) {
        const body = new FormData();
        body.append('accion', accion);
        if (empresaId) body.append('empresa_id', empresaId);

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
        if (!resp.ok || !json.success) {
            throw new Error(json.message || json.error || 'Error inesperado');
        }
        return json;
    }

    function opcion(valor, texto) {
        const o = document.createElement('option');
        o.value = valor;
        o.textContent = texto;
        return o;
    }

    function mostrarError(err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message || err });
    }

    async function cargarPerfiles() {
        const { data } = await llamar('listar_perfiles');
        const sel = document.getElementById('selPerfil');
        sel.innerHTML = '<option value="">Seleccione un perfil...</option>';
        data.forEach(p => {
            const extra = p.perfil_nombre ? ` (${p.perfil_nombre})` : '';
            sel.appendChild(opcion(p.empresa_perfil_id, `${p.empresa_perfil_nombre}${extra}`));
        });
    }

    async function cargarSucursales() {
        const { data } = await llamar('listar_sucursales');
        const sel = document.getElementById('selSucursal');
        sel.innerHTML = '<option value="">Todas las Sucursales (Acceso Total)</option>';
        data.forEach(s => sel.appendChild(opcion(s.sucursal_id, s.sucursal_nombre)));
    }

    async function cargarBocas(sucursalId) {
        const selBoca = document.getElementById('selBoca');
        selBoca.innerHTML = '<option value="">Todas las Bocas de la Sucursal</option>';

        if (!sucursalId) {
            selBoca.disabled = true;
            return;
        }

        const { data } = await llamar('listar_bocas', { sucursal_id: sucursalId });
        data.forEach(b => {
            const tag = b.es_deposito == 1 ? ' [Depósito]' : '';
            selBoca.appendChild(opcion(b.boca_id, `${b.boca_nombre}${tag}`));
        });
        selBoca.disabled = false;
    }

    async function cargarPuntosVenta(sucursalId, bocaId) {
        const selPv = document.getElementById('selPuntoVenta');
        selPv.innerHTML = '<option value="">Todos los Puntos de Venta</option>';

        if (!sucursalId) {
            selPv.disabled = true;
            return;
        }

        const { data } = await llamar('listar_puntos_venta', {
            sucursal_id: sucursalId,
            boca_id: bocaId || null
        });
        data.forEach(pv => selPv.appendChild(opcion(pv.punto_venta_id, pv.nombre)));
        selPv.disabled = false;
    }

    async function cargarTiposComprobante(sucursalId, puntoVentaId) {
        const selTipo = document.getElementById('selTipoComprobante');
        selTipo.innerHTML = '<option value="">Todos los Tipos Habilitados</option>';

        if (!puntoVentaId) {
            selTipo.disabled = true;
            return;
        }

        const { data } = await llamar('listar_tipos_comprobante', {
            sucursal_id: sucursalId,
            punto_venta_id: puntoVentaId
        });
        data.forEach(t => {
            const letra = t.letra ? ` (${t.letra})` : '';
            selTipo.appendChild(opcion(t.comprobante_tipo_id, `${t.codigo} - ${t.comprobante_tipo}${letra}`));
        });
        selTipo.disabled = false;
    }

    function inicializarTabla() {
        tabla = $('#tblGrants').DataTable({
            data: [],
            dom: '<"row align-items-center mb-2"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center mt-2"<"col-12 col-md-6"i><"col-12 col-md-6 d-flex justify-content-end"p>>',
            buttons: [],
            columns: [
                {
                    data: 'empresa_perfil_nombre',
                    render: (data) => `<span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">${data || 'Sin Perfil'}</span>`
                },
                {
                    data: 'sucursal_nombre',
                    render: (data, type, row) => {
                        if (!row.sucursal_id) {
                            return `<span class="badge bg-success"><i class="fas fa-globe me-1"></i>Acceso Total</span>`;
                        }
                        return `<strong>${data}</strong>`;
                    }
                },
                {
                    data: 'boca_nombre',
                    render: (data) => data ? `<i class="fas fa-store text-muted me-1"></i>${data}` : `<span class="text-muted fst-italic">Todas</span>`
                },
                {
                    data: 'punto_venta_nombre',
                    render: (data) => data ? `<i class="fas fa-cash-register text-muted me-1"></i>${data}` : `<span class="text-muted fst-italic">Todos</span>`
                },
                {
                    data: 'comprobante_tipo',
                    render: (data, type, row) => data ? `<span class="badge bg-secondary">${row.comprobante_codigo} - ${data}</span>` : `<span class="text-muted fst-italic">Todos</span>`
                },
                {
                    data: 'es_predeterminado',
                    className: 'text-center',
                    render: (data) => data == 1 ? `<i class="fas fa-check-circle text-success fs-6" title="Predeterminado"></i>` : `<span class="text-muted">-</span>`
                },
                {
                    data: 'perfil_sucursal_id',
                    orderable: false,
                    className: 'text-center',
                    render: (id, type, row) => `<button type="button" class="btn btn-outline-danger btn-xs btn-eliminar" data-funcion="inactivar" data-id="${id}" data-perfil-id="${row.empresa_perfil_id}" title="Eliminar regla"><i class="fas fa-trash"></i></button>`
                }
            ],
            language: {
                emptyTable: "No hay reglas de acceso configuradas.",
                search: "Buscar:",
                lengthMenu: "Mostrar _MENU_",
                info: "Mostrando _START_ a _END_ de _TOTAL_ reglas",
                paginate: { next: "Sig.", previous: "Ant." }
            },
            paging: true,
            info: true,
            searching: true
        });

        $('#tblGrants tbody').on('click', '.btn-eliminar', async function () {
            const id = $(this).data('id');
            const targetPerfilId = $(this).data('perfil-id') || perfilActual;
            const confirmado = await Swal.fire({
                title: '¿Revocar este acceso?',
                text: 'El perfil dejará de tener alcance sobre esta selección.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, revocar',
                cancelButtonText: 'Cancelar'
            });
            if (!confirmado.isConfirmed) return;

            try {
                await llamar('eliminar_grant', {
                    empresa_perfil_id: targetPerfilId,
                    perfil_sucursal_id: id
                });
                await recargarGrants();
                Swal.fire({ icon: 'success', title: 'Acceso revocado', timer: 1200, showConfirmButton: false });
            } catch (e) {
                mostrarError(e);
            }
        });
    }

    async function recargarGrants() {
        const titulo = document.getElementById('tituloGrants');
        const selPerfil = document.getElementById('selPerfil');
        const perfilNombre = selPerfil.selectedIndex > 0 ? selPerfil.options[selPerfil.selectedIndex].text : '';

        if (perfilActual) {
            if (titulo) titulo.innerHTML = `<i class="fas fa-list-check me-1"></i>Alcance para: <strong>${perfilNombre}</strong>`;
            const { data } = await llamar('listar_grants', { empresa_perfil_id: perfilActual });
            tabla.clear().rows.add(data).draw();
        } else {
            if (titulo) titulo.innerHTML = `<i class="fas fa-list-check me-1"></i>Accesos por Perfil (Todos los perfiles)`;
            const { data } = await llamar('listar_grants');
            tabla.clear().rows.add(data).draw();
        }
    }

    function resetearControlesCascada() {
        document.getElementById('selSucursal').value = '';
        document.getElementById('selBoca').innerHTML = '<option value="">Todas las Bocas de la Sucursal</option>';
        document.getElementById('selBoca').disabled = true;
        document.getElementById('selPuntoVenta').innerHTML = '<option value="">Todos los Puntos de Venta</option>';
        document.getElementById('selPuntoVenta').disabled = true;
        document.getElementById('selTipoComprobante').innerHTML = '<option value="">Todos los Tipos Habilitados</option>';
        document.getElementById('selTipoComprobante').disabled = true;
        document.getElementById('chkPredeterminado').checked = false;
    }

    document.addEventListener('DOMContentLoaded', () => {
        inicializarTabla();
        cargarPerfiles().catch(mostrarError);
        recargarGrants().catch(mostrarError); // Muestra todos los accesos al cargar la página

        document.getElementById('selPerfil').addEventListener('change', async function () {
            perfilActual = this.value || null;
            document.getElementById('cardAgregar').style.display = perfilActual ? '' : 'none';

            if (perfilActual) {
                try {
                    await cargarSucursales();
                    resetearControlesCascada();
                    await recargarGrants();
                } catch (e) {
                    mostrarError(e);
                }
            } else {
                await recargarGrants(); // Muestra todos los accesos si no se elige uno
            }
        });

        document.getElementById('btnRecargarGrants').addEventListener('click', () => {
            recargarGrants().catch(mostrarError);
        });

        document.getElementById('selSucursal').addEventListener('change', async function () {
            const sucursalId = this.value || null;
            await cargarBocas(sucursalId);
            await cargarPuntosVenta(sucursalId, null);
            document.getElementById('selTipoComprobante').innerHTML = '<option value="">Todos los Tipos Habilitados</option>';
            document.getElementById('selTipoComprobante').disabled = true;
        });

        document.getElementById('selBoca').addEventListener('change', async function () {
            const sucursalId = document.getElementById('selSucursal').value || null;
            const bocaId = this.value || null;
            await cargarPuntosVenta(sucursalId, bocaId);
            document.getElementById('selTipoComprobante').innerHTML = '<option value="">Todos los Tipos Habilitados</option>';
            document.getElementById('selTipoComprobante').disabled = true;
        });

        document.getElementById('selPuntoVenta').addEventListener('change', async function () {
            const sucursalId = document.getElementById('selSucursal').value || null;
            const pvId = this.value || null;
            await cargarTiposComprobante(sucursalId, pvId);
        });

        document.getElementById('btnAgregarGrant').addEventListener('click', async function () {
            if (!perfilActual) {
                Swal.fire({ icon: 'warning', title: 'Seleccione un perfil primero' });
                return;
            }

            const sucursalId = document.getElementById('selSucursal').value;
            const bocaId = document.getElementById('selBoca').value;
            const puntoVentaId = document.getElementById('selPuntoVenta').value;
            const comprobanteTipoId = document.getElementById('selTipoComprobante').value;
            const esPredeterminado = document.getElementById('chkPredeterminado').checked ? 1 : 0;

            try {
                await llamar('agregar_grant', {
                    empresa_perfil_id: perfilActual,
                    sucursal_id: sucursalId,
                    boca_id: bocaId,
                    punto_venta_id: puntoVentaId,
                    comprobante_tipo_id: comprobanteTipoId,
                    es_predeterminado: esPredeterminado
                });
                resetearControlesCascada();
                await recargarGrants();
                Swal.fire({ icon: 'success', title: 'Acceso otorgado', timer: 1200, showConfirmButton: false });
            } catch (e) {
                mostrarError(e);
            }
        });
    });
})();
