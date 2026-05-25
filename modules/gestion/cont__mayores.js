$(document).ready(function () {
    var tablaMayores;
    var tablaDetalle;
    var fechaDesde = '';
    var fechaHasta = '';
    var cuentaSeleccionada = null;
    
    // Inicializar fechas por defecto
    function inicializarFechas() {
        var hoy = new Date();
        var primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        var ultimoDia = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
        
        fechaDesde = primerDia.toISOString().split('T')[0];
        fechaHasta = ultimoDia.toISOString().split('T')[0];
        
        $('#fecha_desde').val(fechaDesde);
        $('#fecha_hasta').val(fechaHasta);
    }
    
    // Inicializar Select2 para cuentas
    function inicializarSelect2() {
        $('#cuenta_id').select2({
            theme: 'bootstrap-5',
            placeholder: 'Seleccione una cuenta',
            allowClear: true,
            ajax: {
                url: 'cont__mayores_ajax.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        accion: 'obtener_cuentas',
                        empresa_idx: empresa_id,
                        search: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(item) {
                            return {
                                id: item.cont_cuenta_id,
                                text: item.codigo + ' - ' + item.nombre
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 0
        });
        
        // Cargar todas las cuentas inicialmente
        $.get('cont__mayores_ajax.php', {
            accion: 'obtener_cuentas',
            empresa_idx: empresa_id
        }, function(data) {
            $('#cuenta_id').empty();
            $('#cuenta_id').append('<option value="">Todas las cuentas</option>');
            data.forEach(function(cuenta) {
                $('#cuenta_id').append(`<option value="${cuenta.cont_cuenta_id}">${escapeHtml(cuenta.codigo)} - ${escapeHtml(cuenta.nombre)}</option>`);
            });
            $('#cuenta_id').trigger('change');
        }, 'json');
    }
    
    // Formatear números
    function formatNumber(number, decimals = 2) {
        if (number === null || number === undefined || number === '') return '0.00';
        var num = parseFloat(number);
        if (isNaN(num)) return '0.00';
        return num.toLocaleString('es-AR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }
    
    function getNaturalezaTexto(naturaleza) {
        if (naturaleza === 'D') return '<span class="badge bg-primary">Deudora</span>';
        if (naturaleza === 'A') return '<span class="badge bg-danger">Acreedora</span>';
        return '<span class="badge bg-secondary">' + escapeHtml(naturaleza) + '</span>';
    }
    
    // Inicializar DataTable de mayores
    // Reemplazar la función inicializarDataTable con esta versión corregida:

function inicializarDataTable() {
    if ($.fn.DataTable.isDataTable('#tablaMayores')) {
        $('#tablaMayores').DataTable().destroy();
        $('#tablaMayores tbody').empty();
    }
    
    tablaMayores = $('#tablaMayores').DataTable({
        ajax: {
            url: 'cont__mayores_ajax.php',
            type: 'GET',
            data: function(d) {
                return {
                    accion: 'obtener_saldos',
                    empresa_idx: empresa_id,
                    fecha_desde: fechaDesde,
                    fecha_hasta: fechaHasta,
                    cuenta_id: cuentaSeleccionada || ''
                };
            },
            dataSrc: function(json) {
                if (json.error) {
                    console.error('Error del servidor:', json.error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: json.error,
                        confirmButtonText: 'Entendido'
                    });
                    return [];
                }
                return json;
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                console.error('Respuesta del servidor:', xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al cargar los datos del mayor. Verifique la consola para más detalles.',
                    confirmButtonText: 'Entendido'
                });
            }
        },
        // ... resto de la configuración igual
    });
}
    
    function actualizarTotales() {
        var data = tablaMayores.rows().data();
        var totalSaldoInicial = 0;
        var totalDebe = 0;
        var totalHaber = 0;
        var totalSaldoFinal = 0;
        
        for (var i = 0; i < data.length; i++) {
            totalSaldoInicial += parseFloat(data[i].saldo_inicial || 0);
            totalDebe += parseFloat(data[i].total_debe || 0);
            totalHaber += parseFloat(data[i].total_haber || 0);
            totalSaldoFinal += parseFloat(data[i].saldo_final || 0);
        }
        
        $('#total_saldo_inicial').text(formatNumber(totalSaldoInicial, 2));
        $('#total_debe').text(formatNumber(totalDebe, 2));
        $('#total_haber').text(formatNumber(totalHaber, 2));
        $('#total_saldo_final').text(formatNumber(totalSaldoFinal, 2));
    }
    
    // Inicializar DataTable de detalle
    function inicializarDataTableDetalle() {
        if ($.fn.DataTable.isDataTable('#tablaDetalleMayor')) {
            $('#tablaDetalleMayor').DataTable().destroy();
            $('#tablaDetalleMayor tbody').empty();
        }
        
        tablaDetalle = $('#tablaDetalleMayor').DataTable({
            dom: '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                { data: 'fecha' },
                { data: 'numero_asiento' },
                { data: 'comprobante' },
                { data: 'descripcion' },
                { 
                    data: 'debe',
                    className: 'text-end',
                    render: function(data) {
                        return formatNumber(data, 2);
                    }
                },
                { 
                    data: 'haber',
                    className: 'text-end',
                    render: function(data) {
                        return formatNumber(data, 2);
                    }
                },
                { 
                    data: 'saldo',
                    className: 'text-end',
                    render: function(data) {
                        let color = data >= 0 ? 'text-success' : 'text-danger';
                        return `<span class="${color} fw-bold">${formatNumber(data, 2)}</span>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'
            },
            responsive: true,
            drawCallback: function() {
                actualizarTotalesDetalle();
            }
        });
    }
    
    function actualizarTotalesDetalle() {
        var data = tablaDetalle.rows().data();
        var totalDebe = 0;
        var totalHaber = 0;
        var saldoFinal = 0;
        
        for (var i = 0; i < data.length; i++) {
            totalDebe += parseFloat(data[i].debe || 0);
            totalHaber += parseFloat(data[i].haber || 0);
            saldoFinal = parseFloat(data[i].saldo || 0);
        }
        
        $('#detalle_total_debe').text(formatNumber(totalDebe, 2));
        $('#detalle_total_haber').text(formatNumber(totalHaber, 2));
        $('#detalle_saldo_final').text(formatNumber(saldoFinal, 2));
    }
    
    // Cargar detalle de cuenta
    function cargarDetalleCuenta(cuentaId, codigo, nombre) {
        $('#cuentaSeleccionada').html(`${escapeHtml(codigo)} - ${escapeHtml(nombre)}`);
        
        $.get('cont__mayores_ajax.php', {
            accion: 'obtener_detalle',
            empresa_idx: empresa_id,
            cuenta_id: cuentaId,
            fecha_desde: fechaDesde,
            fecha_hasta: fechaHasta
        }, function(res) {
            if (res && res.detalle) {
                $('#detalle_total_debe').text(formatNumber(res.total_debe || 0, 2));
                $('#detalle_total_haber').text(formatNumber(res.total_haber || 0, 2));
                $('#detalle_saldo_final').text(formatNumber(res.saldo_final || 0, 2));
                
                if (tablaDetalle) {
                    tablaDetalle.clear();
                    tablaDetalle.rows.add(res.detalle);
                    tablaDetalle.draw();
                }
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin datos',
                    text: 'No se encontraron movimientos para esta cuenta en el período seleccionado',
                    confirmButtonText: 'Entendido'
                });
            }
        }, 'json').fail(function(xhr) {
            console.error('Error al cargar detalle:', xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar el detalle de la cuenta',
                confirmButtonText: 'Entendido'
            });
        });
    }
    
    // Eventos
    $(document).on('click', '#btnConsultar', function() {
        fechaDesde = $('#fecha_desde').val();
        fechaHasta = $('#fecha_hasta').val();
        cuentaSeleccionada = $('#cuenta_id').val() || null;
        
        if (!fechaDesde) {
            Swal.fire({
                icon: 'warning',
                title: 'Advertencia',
                text: 'La fecha desde es obligatoria',
                confirmButtonText: 'Entendido'
            });
            return;
        }
        
        if (!fechaHasta) {
            Swal.fire({
                icon: 'warning',
                title: 'Advertencia',
                text: 'La fecha hasta es obligatoria',
                confirmButtonText: 'Entendido'
            });
            return;
        }
        
        if (tablaMayores) {
            tablaMayores.ajax.reload();
        }
    });
    
    $(document).on('click', '#btnRecargar', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        if (tablaMayores) {
            tablaMayores.ajax.reload(function() {
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            });
        } else {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }
    });
    
    $(document).on('click', '.btn-ver-detalle', function() {
        var cuentaId = $(this).data('id');
        var codigo = $(this).data('codigo');
        var nombre = $(this).data('nombre');
        
        inicializarDataTableDetalle();
        cargarDetalleCuenta(cuentaId, codigo, nombre);
        
        var modal = new bootstrap.Modal(document.getElementById('modalDetalleMayor'));
        modal.show();
    });
    
    $(document).on('click', '#btnExportarDetalle', function() {
        if (tablaDetalle && tablaDetalle.data().any()) {
            var data = tablaDetalle.data().toArray();
            var headers = ['Fecha', 'Asiento N°', 'Comprobante', 'Descripción', 'Debe', 'Haber', 'Saldo'];
            var rows = data.map(function(row) {
                return [
                    row.fecha,
                    row.numero_asiento,
                    row.comprobante,
                    row.descripcion,
                    formatNumber(row.debe, 2),
                    formatNumber(row.haber, 2),
                    formatNumber(row.saldo, 2)
                ];
            });
            
            var csvContent = headers.join(',') + '\n' + 
                rows.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
            
            var blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.href = url;
            link.setAttribute('download', 'detalle_mayor.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            
            Swal.fire({
                icon: 'success',
                title: 'Exportado',
                text: 'El detalle ha sido exportado a CSV',
                showConfirmButton: false,
                timer: 1500
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Sin datos',
                text: 'No hay datos para exportar',
                confirmButtonText: 'Entendido'
            });
        }
    });
    
    // Pantalla completa
    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalDetalleMayor .modal-dialog');
        var btnIcon = $(this).find('i');
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });
    
    // Exportar desde botones del card
    $('#btnExportarExcel').click(function(e) {
        e.preventDefault();
        if (tablaMayores) {
            tablaMayores.button('.buttons-excel').trigger();
        }
    });
    
    $('#btnExportarPDF').click(function(e) {
        e.preventDefault();
        if (tablaMayores) {
            tablaMayores.button('.buttons-pdf').trigger();
        }
    });
    
    $('#btnExportarCSV').click(function(e) {
        e.preventDefault();
        if (tablaMayores) {
            tablaMayores.button('.buttons-csv').trigger();
        }
    });
    
    $('#btnExportarPrint').click(function(e) {
        e.preventDefault();
        if (tablaMayores) {
            tablaMayores.button('.buttons-print').trigger();
        }
    });
    
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var str = String(text);
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    
    // Inicialización
    inicializarFechas();
    inicializarSelect2();
    inicializarDataTable();
    
    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});