<?php
/**
 * Dashboard Estudio: Estudio Contable y Auditoría Integrada
 * Módulo 4: Estudio Contable (dashboard_estudio.php)
 * Página ID: 401
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Estudio Contable - Dashboard";
$currentPage = 'dashboard_estudio';
$modudo_idx = 4;
$pagina_idx = 401;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = (int) $current_empresa_id; // empresa validada por header1.php, sin default

// Métricas de Estudio Contable
// 1. Clientes
$res_c = mysqli_query($conn, "SELECT COUNT(*) as cant FROM estudio__clientes WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id = 1");
$cant_clientes = mysqli_fetch_assoc($res_c)['cant'] ?? 0;

// 2. Vencimientos del mes pendientes
$periodo_actual = intval(date('Ym'));
$res_v = mysqli_query($conn, "SELECT COUNT(*) as cant FROM estudio__vencimientos_generados WHERE empresa_id = {$empresa_id} AND periodo_fiscal = {$periodo_actual} AND tabla_estado_registro_id IN (17, 18, 5)");
$cant_vencimientos = mysqli_fetch_assoc($res_v)['cant'] ?? 0;

// 3. Tareas RACI pendientes/en proceso
$res_t = mysqli_query($conn, "SELECT COUNT(*) as cant FROM estudio__tareas WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id IN (17, 18)");
$cant_tareas = mysqli_fetch_assoc($res_t)['cant'] ?? 0;

// 4. Abonos del mes
$res_ab = mysqli_query($conn, "SELECT COALESCE(SUM(importe_base), 0) as total FROM estudio__clientes_abonos WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id = 1");
$total_abonos = mysqli_fetch_assoc($res_ab)['total'] ?? 0;

// 5. Activos fijos registrados
$res_act = mysqli_query($conn, "SELECT COUNT(*) as cant, COALESCE(SUM(valor_origen), 0) as total_val FROM gestion__activos_fijos WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id = 1");
$row_act = mysqli_fetch_assoc($res_act);
$cant_activos = $row_act['cant'] ?? 0;
$valor_activos = $row_act['total_val'] ?? 0;
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-balance-scale me-2 text-primary"></i>Panel de Control del Estudio Contable
                    </h3>
                    <small class="text-muted">Centro de control tributario, operativo, facturación y auditoría fiscal</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/casalucho/index.php">Inicio</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Dashboard Estudio</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Widgets de Métricas Rápidas -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm bg-gradient bg-primary text-white h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 text-uppercase fw-bold mb-1">Clientes Activos</h6>
                                <h2 class="mb-0 fw-bold"><?php echo fmt_numero($cant_clientes, 0); ?></h2>
                                <small class="text-white-50"><a href="estudio_clientes.php" class="text-white text-decoration-underline">Ver padrón completo &raquo;</a></small>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm bg-gradient bg-danger text-white h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 text-uppercase fw-bold mb-1">Vencimientos Mes</h6>
                                <h2 class="mb-0 fw-bold"><?php echo fmt_numero($cant_vencimientos, 0); ?></h2>
                                <small class="text-white-50"><a href="vencimientos_tablero.php" class="text-white text-decoration-underline">Tablero operativo &raquo;</a></small>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm bg-gradient bg-warning text-dark h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-dark-50 text-uppercase fw-bold mb-1">Tareas RACI Activas</h6>
                                <h2 class="mb-0 fw-bold"><?php echo fmt_numero($cant_tareas, 0); ?></h2>
                                <small><a href="tareas_raci.php" class="text-dark fw-bold text-decoration-underline">Bandeja de tareas &raquo;</a></small>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm bg-gradient bg-success text-white h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 text-uppercase fw-bold mb-1">Abonos Mensuales</h6>
                                <h2 class="mb-0 fw-bold"><?php echo fmt_moneda($total_abonos, 0); ?></h2>
                                <small class="text-white-50"><a href="honorarios_facturacion.php" class="text-white text-decoration-underline">Facturación masiva &raquo;</a></small>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accesos directos y navegabilidad por pilares -->
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title fw-bold mb-0 text-primary">
                                <i class="fas fa-th-large me-2"></i>Módulos Operativos y Flujos de Trabajo
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="p-3 border rounded h-100 hover-shadow">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="rounded-circle bg-primary-subtle text-primary p-2 me-2">
                                                <i class="fas fa-address-book fa-lg"></i>
                                            </div>
                                            <h6 class="fw-bold mb-0">Padrón de Clientes</h6>
                                        </div>
                                        <p class="text-muted small mb-2">Fichas integrales con CUIT, condición fiscal, matriz territorial y asignación RACI.</p>
                                        <a href="estudio_clientes.php" class="btn btn-outline-primary btn-sm">Gestionar Clientes &raquo;</a>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="p-3 border rounded h-100 hover-shadow">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="rounded-circle bg-danger-subtle text-danger p-2 me-2">
                                                <i class="fas fa-calendar-check fa-lg"></i>
                                            </div>
                                            <h6 class="fw-bold mb-0">Tablero y Generador</h6>
                                        </div>
                                        <p class="text-muted small mb-2">Generación masiva mensual por CUIT y tablero operativo con semáforo de vencimientos.</p>
                                        <a href="vencimientos_tablero.php" class="btn btn-outline-danger btn-sm me-1">Tablero</a>
                                        <a href="vencimientos_generador.php" class="btn btn-outline-secondary btn-sm">Generador</a>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="p-3 border rounded h-100 hover-shadow">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="rounded-circle bg-success-subtle text-success p-2 me-2">
                                                <i class="fas fa-file-invoice-dollar fa-lg"></i>
                                            </div>
                                            <h6 class="fw-bold mb-0">Abonos y Facturación</h6>
                                        </div>
                                        <p class="text-muted small mb-2">Consolidación periódica de honorarios, novedades adicionales y emisión en lote sincronizada a ventas.</p>
                                        <a href="honorarios_facturacion.php" class="btn btn-outline-success btn-sm me-1">Facturación</a>
                                        <a href="honorarios_conceptos.php" class="btn btn-outline-secondary btn-sm">Tarifarios</a>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="p-3 border rounded h-100 hover-shadow">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2 me-2">
                                                <i class="fas fa-gavel fa-lg"></i>
                                            </div>
                                            <h6 class="fw-bold mb-0">Motor de Normas</h6>
                                        </div>
                                        <p class="text-muted small mb-2">Carga de resoluciones oficiales y motor de evaluación de reglas dinámicas sobre clientes.</p>
                                        <a href="normas_gestion.php" class="btn btn-outline-warning btn-sm">Normas y Reglas &raquo;</a>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="p-3 border rounded h-100 hover-shadow">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="rounded-circle bg-info-subtle text-info p-2 me-2">
                                                <i class="fas fa-tasks fa-lg"></i>
                                            </div>
                                            <h6 class="fw-bold mb-0">Matriz RACI y Time Tracking</h6>
                                        </div>
                                        <p class="text-muted small mb-2">Gestión de tareas operativas por roles (R/A/C/I) y bitácora de minutos dedicados.</p>
                                        <a href="tareas_raci.php" class="btn btn-outline-info btn-sm">Bandeja RACI &raquo;</a>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="p-3 border rounded h-100 hover-shadow">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="rounded-circle bg-secondary-subtle text-dark p-2 me-2">
                                                <i class="fas fa-boxes fa-lg"></i>
                                            </div>
                                            <h6 class="fw-bold mb-0">Activos Fijos y Amortización</h6>
                                        </div>
                                        <p class="text-muted small mb-2">Padrón de bienes de uso, amortizaciones mensuales lineales y asientos contables automáticos.</p>
                                        <a href="activos_amortizaciones.php" class="btn btn-outline-dark btn-sm">Activos Fijos &raquo;</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Tarjeta de Licencias y Suplencias -->
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fas fa-user-clock me-2 text-primary"></i>Licencias y Suplencias
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Gestione ausencias programadas y delegaciones automáticas de tareas operativas a suplentes con reversión garantizada.</p>
                            <a href="licencias_gestion.php" class="btn btn-sm btn-primary w-100">
                                <i class="fas fa-calendar-alt me-1"></i>Wizard de Licencias
                            </a>
                        </div>
                    </div>

                    <!-- Auditoría y Seguridad -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fas fa-shield-alt me-2 text-success"></i>Auditoría e Integridad ERP
                            </h6>
                        </div>
                        <div class="card-body small">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Multi-tenancy reforzado por <code>empresa_id</code></li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Integración a <code>gestion__comprobantes</code> vía <code>syncComprobante()</code></li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Asientos contables automáticos (origen AUTO)</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Cola de sincronización ARCA activa</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tablero entre clientes -->
            <div class="card shadow-sm border-0 mt-3" id="cardTableroClientes">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0 text-primary"><i class="fas fa-traffic-light me-2"></i>Situación por cliente</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTableroRecargar"><i class="fas fa-sync-alt"></i></button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="tablaTableroClientes">
                            <thead class="table-light">
                                <tr>
                                    <th></th><th>Cliente</th><th>Modo</th><th>Próx. vencimiento</th>
                                    <th class="text-center">Vencidos</th><th class="text-center">Tareas atrasadas</th>
                                    <th>IVA mes anterior</th><th></th>
                                </tr>
                            </thead>
                            <tbody><tr><td colspan="8" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var colores = { rojo: 'danger', amarillo: 'warning', verde: 'success' };
                var ivaTxt = { LIQUIDADO: 'Liquidado', PENDIENTE: 'Pendiente', SIN_PERMISO: 'Sin permiso', REMOTO: 'Servidor propio', EXTERNO: 'Software externo', NO_APLICA: '—' };
                function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
                function cargar() {
                    $.getJSON('estudio_tablero_clientes_ajax.php', function (r) {
                        var $tb = $('#tablaTableroClientes tbody').empty();
                        if (!r.success || !r.data.length) {
                            $tb.append('<tr><td colspan="8" class="text-center text-muted py-3">' + esc(r.success ? 'Sin clientes activos.' : r.message) + '</td></tr>');
                            return;
                        }
                        // Los que requieren atención primero
                        var orden = { rojo: 0, amarillo: 1, verde: 2 };
                        r.data.sort(function (a, b) { return orden[a.semaforo] - orden[b.semaforo]; });
                        r.data.forEach(function (c) {
                            var iva = ivaTxt[c.iva_estado] || c.iva_estado;
                            if (c.iva_saldo !== null) { iva += ' (' + (c.iva_saldo > 0 ? 'a pagar ' : 'a favor ') + Fmt.moneda(Math.abs(c.iva_saldo)) + ')'; }
                            var prox = c.proximo_venc ? esc(Fmt.fecha(c.proximo_venc)) + (c.proximo_impuesto ? ' · ' + esc(c.proximo_impuesto) : '') : '—';
                            var accion = (c.modo === 'REMOTO' || c.modo === 'PADRON' || c.modo === 'EXTERNO') ? '' : '<a class="btn btn-sm btn-outline-primary" href="minigestion.php?cliente_id=' + parseInt(c.cliente_id, 10) + '">Gestionar</a>';
                            $tb.append(
                                '<tr><td><span class="badge text-bg-' + colores[c.semaforo] + '">&nbsp;</span></td>' +
                                '<td>' + esc(c.razon_social) + '<br><small class="text-muted">' + esc(c.cuit) + '</small></td>' +
                                '<td>' + esc(c.nivel_servicio || c.modo) + '<br><small class="text-muted">' + esc(c.modo) + '</small></td><td>' + prox + '</td>' +
                                '<td class="text-center">' + (c.venc_vencidos > 0 ? '<span class="badge text-bg-danger">' + c.venc_vencidos + '</span>' : '0') + '</td>' +
                                '<td class="text-center">' + (c.tareas_atrasadas > 0 ? '<span class="badge text-bg-danger">' + c.tareas_atrasadas + '</span>' : '0') + '</td>' +
                                '<td>' + esc(iva) + '</td><td class="text-end">' + accion + '</td></tr>');
                        });
                    }).fail(function () {
                        $('#tablaTableroClientes tbody').html('<tr><td colspan="8" class="text-center text-danger py-3">Fallo de comunicación.</td></tr>');
                    });
                }
                $(function () { cargar(); $('#btnTableroRecargar').on('click', cargar); });
            })();
            </script>
        </div>
    </div>
</main>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
