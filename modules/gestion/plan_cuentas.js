/**
 * plan_cuentas.js
 * Gestión del Plan de Cuentas con jerarquía expandible/contraíble
 * VERSIÓN CON FILTRO JERÁRQUICO - Selección de cuenta raíz y todas sus subcuentas
 */

$(document).ready(function () {
    // Variables de contexto MULTIEMPRESA
    const empresa_id = 2; // ID de empresa fijo, se puede modificar según necesidad
    const pagina_idx = 68; // ID de página para plan de cuentas

    // Variables para mantener el estado del DataTable y jerarquía
    var tabla;
    var currentPage = 0;
    var currentSearch = '';
    
    // Estructura jerárquica
    var cuentaTree = []; // Array de cuentas raíz con sus hijos anidados
    var cuentaMap = {}; // Mapa de todas las cuentas por ID
    var expandedNodes = {}; // Estado de expansión
    
    // Filtro jerárquico
    var cuentaRaizFiltro = ''; // ID de la cuenta raíz seleccionada para filtrar
    var todasLasCuentas = []; // Array con todas las cuentas para el select

    // Función para construir el árbol jerárquico
    function construirArbolJerarquico(cuentas) {
        if (!cuentas || !Array.isArray(cuentas)) {
            console.error('No hay cuentas para construir el árbol');
            return [];
        }
        
        cuentaMap = {};
        var raices = [];
        todasLasCuentas = cuentas; // Guardar para el filtro
        
        // Primero, mapear todas las cuentas por ID
        cuentas.forEach(function(cuenta) {
            if (!cuenta || !cuenta.cont_cuenta_id) return;
            
            cuentaMap[cuenta.cont_cuenta_id] = {
                ...cuenta,
                children: [],
                level: cuenta.nivel || 1
            };
        });
        
        // Luego, construir las relaciones padre-hijo
        cuentas.forEach(function(cuenta) {
            if (!cuenta || !cuenta.cont_cuenta_id) return;
            
            var cuentaId = cuenta.cont_cuenta_id;
            var padreId = cuenta.cuenta_padre_id;
            
            if (padreId && cuentaMap[padreId]) {
                // Tiene padre, agregarlo como hijo
                cuentaMap[padreId].children.push(cuentaMap[cuentaId]);
            } else {
                // Es raíz (sin padre)
                raices.push(cuentaMap[cuentaId]);
            }
        });
        
        // Ordenar raíces y sus hijos por código
        function ordenarPorCodigo(a, b) {
            return (a.codigo || '').localeCompare(b.codigo || '', undefined, {numeric: true});
        }
        
        raices.sort(ordenarPorCodigo);
        
        function ordenarHijos(nodo) {
            if (nodo && nodo.children && nodo.children.length > 0) {
                nodo.children.sort(ordenarPorCodigo);
                nodo.children.forEach(ordenarHijos);
            }
        }
        
        raices.forEach(ordenarHijos);
        
        console.log('Árbol construido:', raices.length, 'raíces');
        return raices;
    }

    // Función para llenar el select de filtro jerárquico (SOLO CUENTAS TÍTULO - es_imputable = 0)
    function llenarSelectFiltroJerarquico() {
        var $select = $('#filtroCuentaRaiz');
        $select.empty().append('<option value="">-- Mostrar todas las cuentas --</option>');
        
        if (!cuentaTree || cuentaTree.length === 0) {
            console.log('No hay cuentas para llenar el filtro');
            return;
        }
        
        // Función recursiva para agregar opciones con sangría (solo cuentas título)
        function agregarOpcionesRecursivo(nodos, nivel = 0) {
            if (!nodos || !Array.isArray(nodos)) return;
            
            nodos.forEach(function(nodo) {
                if (!nodo || !nodo.cont_cuenta_id) return;
                
                // SOLO AGREGAR CUENTAS TÍTULO (es_imputable = 0)
                if (nodo.es_imputable == 0) {
                    var prefix = ' '.repeat(nivel);
                    var selected = (cuentaRaizFiltro && parseInt(cuentaRaizFiltro) === parseInt(nodo.cont_cuenta_id)) ? 'selected' : '';
                    
                    // Determinar íconos según naturaleza
                    var naturalezaIcon = '';
                    if (nodo.naturaleza === 'DEUDORA' || nodo.naturaleza === 'D') {
                        naturalezaIcon = '🔴';
                    } else if (nodo.naturaleza === 'ACREEDORA' || nodo.naturaleza === 'H') {
                        naturalezaIcon = '🟢';
                    }
                    
                    var tipoIcon = '📌'; // Siempre será título
                    
                    $select.append(`<option value="${nodo.cont_cuenta_id}" ${selected}>${prefix} ${tipoIcon} ${nodo.codigo} - ${nodo.nombre} (${naturalezaIcon})</option>`);
                }
                
                // Siempre recorrer hijos, independientemente de si el padre es título o imputable
                if (nodo.children && nodo.children.length > 0) {
                    agregarOpcionesRecursivo(nodo.children, nivel + 1);
                }
            });
        }
        
        agregarOpcionesRecursivo(cuentaTree);
        
        // Si no hay cuentas título, mostrar mensaje
        if ($select.find('option').length === 1) { // Solo la opción por defecto
            $select.append('<option value="" disabled>-- No hay cuentas título disponibles --</option>');
        }
    }

    // Función para obtener todos los IDs descendientes de una cuenta
    function obtenerDescendientes(cuentaId, incluirRaiz = true) {
        if (!cuentaId) return [];
        
        var ids = incluirRaiz ? [parseInt(cuentaId)] : [];
        var cuenta = cuentaMap[parseInt(cuentaId)];
        
        if (cuenta && cuenta.children) {
            function recorrerHijos(hijos) {
                if (!hijos || !Array.isArray(hijos)) return;
                
                hijos.forEach(function(hijo) {
                    if (!hijo || !hijo.cont_cuenta_id) return;
                    
                    ids.push(parseInt(hijo.cont_cuenta_id));
                    if (hijo.children && hijo.children.length > 0) {
                        recorrerHijos(hijo.children);
                    }
                });
            }
            recorrerHijos(cuenta.children);
        }
        
        return ids;
    }

    // Función para aplicar el filtro jerárquico
    function aplicarFiltroJerarquico() {
        cuentaRaizFiltro = $('#filtroCuentaRaiz').val();
        
        if (!tabla) return;
        
        if (!cuentaRaizFiltro) {
            // Mostrar todas las cuentas
            tabla.rows().every(function() {
                $(this.node()).show();
            });
            
            // Quitar clase de resaltado
            $('.cuenta-filtrada').removeClass('cuenta-filtrada');
            
            // Reaplicar visibilidad jerárquica
            aplicarVisibilidadJerarquica();
            return;
        }
        
        // Obtener todos los IDs de la cuenta seleccionada y sus descendientes
        var idsPermitidos = obtenerDescendientes(parseInt(cuentaRaizFiltro), true);
        
        // Ocultar/Mostrar filas según si pertenecen al conjunto filtrado
        tabla.rows().every(function(rowIdx, tableLoop, rowLoop) {
            var data = this.data();
            var node = this.node();
            
            if (data && idsPermitidos.includes(parseInt(data.cont_cuenta_id))) {
                $(node).show();
                // Resaltar la cuenta raíz filtrada
                if (parseInt(data.cont_cuenta_id) === parseInt(cuentaRaizFiltro)) {
                    $(node).addClass('cuenta-filtrada');
                } else {
                    $(node).removeClass('cuenta-filtrada');
                }
            } else {
                $(node).hide();
            }
        });
        
        // Reaplicar visibilidad jerárquica sobre las filas visibles
        aplicarVisibilidadJerarquica();
    }

    // Función para limpiar el filtro
    function limpiarFiltro() {
        $('#filtroCuentaRaiz').val('');
        cuentaRaizFiltro = '';
        $('.cuenta-filtrada').removeClass('cuenta-filtrada');
        aplicarFiltroJerarquico();
    }

    // Función para aplanar el árbol para DataTable
    function aplanarArbol(nodos, nivel = 1, resultado = []) {
        if (!nodos || !Array.isArray(nodos)) return resultado;
        
        nodos.forEach(function(nodo) {
            if (!nodo) return;
            
            resultado.push({
                ...nodo,
                displayNivel: nivel,
                tieneHijos: nodo.children && nodo.children.length > 0
            });
            
            if (nodo.children && nodo.children.length > 0) {
                aplanarArbol(nodo.children, nivel + 1, resultado);
            }
        });
        return resultado;
    }

    // Función para inicializar DataTable
    function inicializarDataTable() {
        // Destruir DataTable existente si hay uno
        if ($.fn.DataTable.isDataTable('#tablaPlanCuentas')) {
            $('#tablaPlanCuentas').DataTable().destroy();
            $('#tablaPlanCuentas tbody').empty();
        }

        // Configuración de DataTable
        tabla = $('#tablaPlanCuentas').DataTable({
            ajax: {
                url: 'plan_cuentas_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_id: empresa_id,
                    pagina_idx: pagina_idx
                },
                dataSrc: function(json) {
                    console.log('Datos recibidos del servidor:', json);
                    
                    if (!json || !Array.isArray(json)) {
                        console.error('Error: Los datos recibidos no son un array', json);
                        return [];
                    }
                    
                    if (json.length === 0) {
                        console.warn('No se encontraron cuentas para la empresa', empresa_id);
                    }
                    
                    // Construir árbol jerárquico
                    cuentaTree = construirArbolJerarquico(json);
                    
                    // INICIALIZAR: raíces expandidas, hijos contraídos
                    expandedNodes = {};
                    json.forEach(function(item) {
                        if (item && item.cont_cuenta_id) {
                            if (!item.cuenta_padre_id) {
                                expandedNodes[item.cont_cuenta_id] = true; // Raíces expandidas
                            } else {
                                expandedNodes[item.cont_cuenta_id] = false; // Hijos contraídos
                            }
                        }
                    });
                    
                    // Llenar el select de filtro jerárquico (solo cuentas título)
                    llenarSelectFiltroJerarquico();
                    
                    // Aplanar para DataTable
                    var datosAplanados = aplanarArbol(cuentaTree);
                    console.log('Datos aplanados:', datosAplanados.length, 'registros');
                    return datosAplanados;
                }
            },
            stateSave: true,
            stateSaveParams: function (settings, data) {
                data.page = currentPage;
                data.expandedNodes = expandedNodes;
                data.cuentaRaizFiltro = cuentaRaizFiltro;
                if (currentSearch && currentSearch !== '') {
                    data.search = { search: currentSearch };
                } else {
                    data.search = { search: '' };
                }
                data.order = [];
                return data;
            },
            stateLoadParams: function (settings, data) {
                if (data.page !== undefined) currentPage = data.page;
                if (data.expandedNodes) expandedNodes = data.expandedNodes;
                if (data.cuentaRaizFiltro) {
                    cuentaRaizFiltro = data.cuentaRaizFiltro;
                    $('#filtroCuentaRaiz').val(cuentaRaizFiltro);
                }
                if (data.search && data.search.search !== undefined) {
                    currentSearch = (data.search.search === '-1' || data.search.search === '') ? '' : data.search.search;
                }
                data.search = { search: currentSearch };
                data.order = [];
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                 '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            ordering: false,
            columns: [
                { data: 'cont_cuenta_id', className: 'text-center fw-bold' },
                { data: 'codigo', className: 'text-center' },
                { 
                    data: null,
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return row.codigo + ' - ' + row.nombre;
                        }
                        
                        var tieneHijos = row.tieneHijos;
                        var isExpanded = expandedNodes[row.cont_cuenta_id] !== false;
                        
                        var claseNivel = 'cuenta-nivel-' + (row.displayNivel || 1);
                        var claseTipo = row.es_imputable == 1 ? 'cuenta-imputable' : 'cuenta-titulo';
                        var icono = row.es_imputable == 1 ? '<i class="fas fa-check-circle text-success me-1"></i>' : '<i class="fas fa-tag text-secondary me-1"></i>';
                        
                        var expandControl = '';
                        if (tieneHijos) {
                            expandControl = `<span class="expand-control" data-id="${row.cont_cuenta_id}">
                                <i class="fas fa-chevron-${isExpanded ? 'down' : 'right'}"></i>
                            </span>`;
                        } else {
                            expandControl = '<span class="no-expand"><i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i></span>';
                        }
                        
                        return `<div class="cuenta-item ${claseNivel} ${claseTipo}" data-id="${row.cont_cuenta_id}">
                            ${expandControl}${icono}${row.codigo} - ${row.nombre}
                        </div>`;
                    }
                },
                { 
                    data: 'naturaleza',
                    className: 'text-center',
                    render: function (data) {
                        if (data === 'DEUDORA' || data === 'D') return '<span class="badge bg-danger">DEUDORA</span>';
                        if (data === 'ACREEDORA' || data === 'H') return '<span class="badge bg-success">ACREEDORA</span>';
                        return data;
                    }
                },
                { data: 'nivel', className: 'text-center' },
                { 
                    data: 'es_imputable',
                    className: 'text-center',
                    render: function (data) {
                        return data == 1 ? 
                            '<span class="badge bg-primary"><i class="fas fa-check"></i> Imputable</span>' : 
                            '<span class="badge bg-secondary"><i class="fas fa-times"></i> Título</span>';
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function (data) {
                        if (!data || !data.estado_registro) return '<span class="fw-medium">Sin estado</span>';
                        return `<span class="fw-medium">${data.estado_registro}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function (data, type, row) {
                        if (type === 'export') return '';
                        
                        var botones = '';
                        if (data && data.length > 0) {
                            var editarBoton = '';
                            var otrosBotones = '';
                            
                            data.forEach(boton => {
                                var claseBoton = 'btn-sm me-1 ';
                                claseBoton += boton.bg_clase || boton.color_clase || 'btn-outline-primary';
                                
                                var titulo = boton.descripcion || boton.nombre_funcion;
                                var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                var esConfirmable = boton.es_confirmable || 0;
                                
                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                       title="${titulo}" 
                                       data-id="${row.cont_cuenta_id}" 
                                       data-accion="${boton.accion_js || boton.nombre_funcion.toLowerCase()}"
                                       data-confirmable="${esConfirmable}"
                                       data-codigo="${row.codigo}"
                                       data-nombre="${row.nombre}">
                                    ${icono}
                                </button>`;
                                
                                if (boton.accion_js === 'editar' || boton.nombre_funcion.toLowerCase() === 'editar') {
                                    editarBoton = botonHtml;
                                } else {
                                    otrosBotones += botonHtml;
                                }
                            });
                            
                            botones = editarBoton + otrosBotones;
                        } else {
                            botones = '<span class="text-muted small">Sin acciones</span>';
                        }
                        
                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json',
            },
            order: [],
            responsive: true,
            createdRow: function (row, data, dataIndex) {
                // Marcar filas ocultas según estado de expansión
                if (data.cuenta_padre_id && !isParentExpanded(data.cuenta_padre_id)) {
                    $(row).addClass('hidden-row');
                }
                
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary');
                }
            },
            drawCallback: function() {
                // Reaplicar visibilidad después de cada dibujo
                aplicarVisibilidadJerarquica();
                // Aplicar filtro jerárquico
                aplicarFiltroJerarquico();
            },
            initComplete: function () {
                console.log('DataTable inicializado correctamente');
                $(tabla.table().container()).on('page.dt', function () { currentPage = tabla.page(); });
                
                // Eventos para expansión/contracción - USAR DELEGACIÓN DE EVENTOS
                $('#tablaPlanCuentas tbody').on('click', '.expand-control', function(e) {
                    e.stopPropagation();
                    var cuentaId = $(this).data('id');
                    toggleExpand(cuentaId);
                });
                
                // Evento para filtro jerárquico
                $('#filtroCuentaRaiz').on('change', function() {
                    aplicarFiltroJerarquico();
                });
                
                // Evento para botón limpiar filtro
                $('#btnLimpiarFiltro').on('click', function() {
                    limpiarFiltro();
                });
            }
        });
        
        inicializarEventos();
    }

    // Función para verificar si un padre está expandido (recursivo hacia arriba)
    function isParentExpanded(padreId) {
        if (!padreId) return true;
        
        // Verificar si el padre está expandido
        var padreExpandido = expandedNodes[padreId];
        
        if (padreExpandido === undefined || padreExpandido === true) {
            // Verificar el padre del padre usando el mapa
            var padreData = cuentaMap[padreId];
            if (padreData && padreData.cuenta_padre_id) {
                return isParentExpanded(padreData.cuenta_padre_id);
            }
            return true;
        }
        
        return false;
    }

    // Alternar expansión/contracción de un nodo
    function toggleExpand(cuentaId) {
        // Cambiar estado
        expandedNodes[cuentaId] = !expandedNodes[cuentaId];
        
        // Actualizar ícono
        var control = $(`.expand-control[data-id="${cuentaId}"] i`);
        if (control.length) {
            control.removeClass('fa-chevron-right fa-chevron-down')
                   .addClass(expandedNodes[cuentaId] ? 'fa-chevron-down' : 'fa-chevron-right');
        }
        
        // Aplicar visibilidad a los hijos
        aplicarVisibilidadJerarquica();
    }

    // Aplicar visibilidad según nodos expandidos
    function aplicarVisibilidadJerarquica() {
        if (!tabla) return;
        
        tabla.rows().every(function(rowIdx, tableLoop, rowLoop) {
            var data = this.data();
            var node = this.node();
            
            if (data && data.cuenta_padre_id) {
                var deberiaMostrarse = isParentExpanded(data.cuenta_padre_id);
                
                if (!deberiaMostrarse) {
                    $(node).addClass('hidden-row');
                } else {
                    $(node).removeClass('hidden-row');
                }
            }
        });
    }

    // EXPANDIR TODO
    function expandAll() {
        // Establecer todos los nodos con hijos como expandidos
        Object.keys(cuentaMap).forEach(function(key) {
            var cuenta = cuentaMap[key];
            if (cuenta && cuenta.children && cuenta.children.length > 0) {
                expandedNodes[key] = true;
            }
        });
        
        // Actualizar todos los íconos
        $('.expand-control i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
        
        // Mostrar todas las filas
        $('.hidden-row').removeClass('hidden-row');
    }

    // CONTRAER TODO
    function collapseAll() {
        // Contraer todos los nodos que tienen hijos
        Object.keys(cuentaMap).forEach(function(key) {
            var cuenta = cuentaMap[key];
            if (cuenta && cuenta.children && cuenta.children.length > 0) {
                expandedNodes[key] = false;
            }
        });
        
        // Actualizar íconos
        $('.expand-control').each(function() {
            var id = $(this).data('id');
            if (expandedNodes[id] === false) {
                $(this).find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
            }
        });
        
        // Aplicar visibilidad (ocultar hijos)
        aplicarVisibilidadJerarquica();
    }

    function inicializarEventos() {
        $('#btnRecargar').off('click').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            var savedState = { page: tabla.page(), search: tabla.search() };
            
            tabla.ajax.reload(function () {
                if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            }, false);
        });
        
        // Eventos para botones de expandir/contraer todo
        $('#btnExpandAll').off('click').on('click', function() {
            expandAll();
        });
        
        $('#btnCollapseAll').off('click').on('click', function() {
            collapseAll();
        });
    }

    // Cargar botón Agregar
    function cargarBotonAgregar() {
        $.get('plan_cuentas_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_idx: pagina_idx
        }, function (botonAgregar) {
            var colorClase = botonAgregar?.bg_clase || botonAgregar?.color_clase || 'btn-primary';
            var icono = botonAgregar?.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '<i class="fas fa-plus me-1"></i>';
            var texto = botonAgregar?.nombre_funcion || 'Agregar Cuenta';
            
            $('#contenedor-boton-agregar').html(
                `<button type="button" class="btn ${colorClase}" id="btnNuevo">${icono}${texto}</button>`
            );
        }, 'json').fail(function () {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar Cuenta</button>'
            );
        });
    }

    // Cargar cuentas padre para el select del modal
    function cargarCuentasPadre(excluirId = null, selectedId = null) {
        $.get('plan_cuentas_ajax.php', {
            accion: 'obtener_cuentas_padre',
            empresa_id: empresa_id,
            excluir_id: excluirId || 0
        }, function (cuentas) {
            var $select = $('#cuenta_padre_id');
            $select.empty().append('<option value="">-- Ninguna (Cuenta Raíz) --</option>');
            
            cuentas.forEach(function (cuenta) {
                var prefix = ' '.repeat(cuenta.nivel);
                var selected = (selectedId && selectedId == cuenta.cont_cuenta_id) ? 'selected' : '';
                
                // Determinar ícono según naturaleza
                var naturalezaBadge = '';
                if (cuenta.naturaleza === 'DEUDORA' || cuenta.naturaleza === 'D') {
                    naturalezaBadge = '🔴';
                } else if (cuenta.naturaleza === 'ACREEDORA' || cuenta.naturaleza === 'H') {
                    naturalezaBadge = '🟢';
                }
                
                var tipo = cuenta.es_imputable == 1 ? '📝' : '📌';
                $select.append(`<option value="${cuenta.cont_cuenta_id}" ${selected}>${prefix} ${tipo} ${cuenta.codigo} - ${cuenta.nombre} (${naturalezaBadge})</option>`);
            });
            
            actualizarNivelPorPadre();
        }, 'json');
    }

    function actualizarNivelPorPadre() {
        var padreId = $('#cuenta_padre_id').val();
        
        if (!padreId) {
            $('#nivel').val(1);
            $('#nivelDisplay').text(1);
            return;
        }
        
        $.get('plan_cuentas_ajax.php', {
            accion: 'obtener_nivel_padre',
            cuenta_padre_id: padreId
        }, function (response) {
            if (response.nivel !== undefined) {
                var nuevoNivel = response.nivel + 1;
                $('#nivel').val(nuevoNivel);
                $('#nivelDisplay').text(nuevoNivel);
            }
        }, 'json');
    }

    // Eventos del modal
    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nueva Cuenta Contable');
        cargarCuentasPadre();
        var modal = new bootstrap.Modal(document.getElementById('modalCuenta'));
        modal.show();
        $('#codigo').focus();
    });

    $('#cuenta_padre_id').on('change', function () {
        actualizarNivelPorPadre();
    });

    // Botones de acción
    $(document).on('click', '.btn-accion', function () {
        var cuentaId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var codigo = $(this).data('codigo');
        var nombre = $(this).data('nombre');
        var descripcion = codigo + ' - ' + nombre;

        if (accionJs === 'editar') {
            cargarCuentaParaEditar(cuentaId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> la cuenta <strong>"${descripcion}"</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarAccion(cuentaId, accionJs, descripcion);
                }
            });
        } else {
            ejecutarAccion(cuentaId, accionJs, descripcion);
        }
    });

    function ejecutarAccion(cuentaId, accionJs, descripcion) {
        var savedState = { page: tabla.page(), search: tabla.search() };

        $.post('plan_cuentas_ajax.php', {
            accion: 'ejecutar_accion',
            cont_cuenta_id: cuentaId,
            accion_js: accionJs,
            empresa_id: empresa_id,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function () {
                    if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                    if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                    
                    aplicarVisibilidadJerarquica();
                    
                    tabla.rows().every(function (rowIdx, tableLoop, rowLoop) {
                        var data = this.data();
                        if (data.cont_cuenta_id == cuentaId) {
                            $(this.node()).addClass('table-success');
                            setTimeout(function () { $(this.node()).removeClass('table-success'); }.bind(this), 2000);
                        }
                    });

                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Cuenta "${descripcion}" actualizada correctamente`,
                        timer: 1500,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false
                    });
                }, false);
            } else {
                Swal.fire({ icon: "error", title: "Error", text: res.error || `Error al ${accionJs} la cuenta` });
            }
        }, 'json');
    }

    function cargarCuentaParaEditar(cuentaId) {
        $.get('plan_cuentas_ajax.php', {
            accion: 'obtener',
            cont_cuenta_id: cuentaId,
            empresa_id: empresa_id
        }, function (res) {
            if (res && res.cont_cuenta_id) {
                resetModal();
                $('#cont_cuenta_id').val(res.cont_cuenta_id);
                $('#codigo').val(res.codigo);
                $('#nombre').val(res.nombre);
                
                // La naturaleza viene como D o H, convertir a DEUDORA/ACREEDORA para el select
                var naturaleza = res.naturaleza;
                
                if (naturaleza === 'D') {
                    $('#naturaleza').val('DEUDORA');
                } else if (naturaleza === 'H') {
                    $('#naturaleza').val('ACREEDORA');
                } else {
                    $('#naturaleza').val(naturaleza);
                }
                
                $('#orden').val(res.orden || 0);
                $('#es_imputable').val(res.es_imputable);
                
                cargarCuentasPadre(res.cont_cuenta_id, res.cuenta_padre_id);
                
                setTimeout(function () {
                    $('#cuenta_padre_id').val(res.cuenta_padre_id || '');
                    $('#nivel').val(res.nivel || 1);
                    $('#nivelDisplay').text(res.nivel || 1);
                }, 300);
                
                $('#modalLabel').text('Editar Cuenta Contable');
                var modal = new bootstrap.Modal(document.getElementById('modalCuenta'));
                modal.show();
            } else {
                Swal.fire({ icon: "error", title: "Error", text: "Error al obtener datos de la cuenta" });
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar cuenta:', error);
            console.error('Respuesta del servidor:', xhr.responseText);
            Swal.fire({ icon: "error", title: "Error", text: "Error de conexión al cargar la cuenta" });
        });
    }

    function resetModal() {
        $('#formCuenta')[0].reset();
        $('#cont_cuenta_id').val('');
        $('#nivel').val(1);
        $('#nivelDisplay').text(1);
        $('#formCuenta').removeClass('was-validated');
        cargarCuentasPadre();
    }

    // Guardar cuenta
    $('#btnGuardar').click(function () {
        var form = document.getElementById('formCuenta');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        var id = $('#cont_cuenta_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var codigo = $('#codigo').val().trim();
        var nombre = $('#nombre').val().trim();
        var naturaleza = $('#naturaleza').val();
        var cuenta_padre_id = $('#cuenta_padre_id').val() || null;
        var nivel = $('#nivel').val() || 1;
        var orden = $('#orden').val() || 0;
        var es_imputable = $('#es_imputable').val();

        if (!codigo || !nombre || !naturaleza) return;

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var savedState = { page: tabla.page(), search: tabla.search() };

        $.ajax({
            url: 'plan_cuentas_ajax.php',
            type: 'POST',
            data: {
                accion: accionBackend,
                cont_cuenta_id: id,
                codigo: codigo,
                nombre: nombre,
                naturaleza: naturaleza,
                cuenta_padre_id: cuenta_padre_id,
                nivel: nivel,
                orden: orden,
                es_imputable: es_imputable,
                empresa_id: empresa_id,
                pagina_idx: pagina_idx
            },
            success: function (res) {
                if (res.resultado) {
                    tabla.ajax.reload(function () {
                        if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                        if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                        
                        btnGuardar.prop('disabled', false).html(originalText);
                        
                        Swal.fire({
                            icon: "success",
                            title: "¡Guardado!",
                            text: "Cuenta guardada correctamente",
                            timer: 1500,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false
                        });

                        var modalEl = document.getElementById('modalCuenta');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        modal.hide();
                    }, false);
                } else {
                    btnGuardar.prop('disabled', false).html(originalText);
                    Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al guardar los datos" });
                }
            },
            error: function () {
                btnGuardar.prop('disabled', false).html(originalText);
                Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor" });
            }
        });
    });

    // Inicializar
    inicializarDataTable();
    cargarBotonAgregar();
});