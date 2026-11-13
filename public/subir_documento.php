<?php
/**
 * Portal público para recepción de comprobantes vía enlace externo (Fase 2).
 * Sin sesión requerida: autenticación exclusiva mediante token criptográfico en ?t=<token>.
 *
 * GET: Muestra la pantalla de carga responsiva (mobile y desktop).
 * POST: Recibe y valida comprobantes (PDF, JPG, PNG) guardándolos en la bandeja con origen 'PORTAL'.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/bandeja/bandeja_service.php';
if (!defined('FPL_PAGINA_IDX')) {
    define('FPL_PAGINA_IDX', 0);
}
require_once __DIR__ . '/../core/facturas_lectura/fp_lectura_model.php';

$token = trim((string)($_REQUEST['t'] ?? ''));
$enlace = $token !== '' ? bdj_enlace_validar($conn, $token) : null;

// ---------------------------------------------------------------------------------------------------
// Manejo de peticiones POST (Subida de archivos por AJAX)
// ---------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$enlace) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'El enlace no es válido o ha expirado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($enlace['cupo_disponible'] <= 0) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Se ha alcanzado el límite diario de comprobantes permitidos para este enlace (' . $enlace['max_archivos_dia'] . ' envíos/día).'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $archivo = $_FILES['archivo'] ?? null;
    if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        $err = intval($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        $msg = in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'El archivo supera el tamaño máximo permitido por el servidor.'
            : 'No se recibió ningún archivo o la subida fue interrumpida.';
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar tamaño contra el límite del enlace
    if ($archivo['size'] > $enlace['max_bytes_archivo']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El archivo supera el tamaño máximo permitido de ' . round($enlace['max_bytes_archivo'] / 1048576) . ' MB.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $res = fplGuardarArchivoDesdeRuta(
            $conn,
            (int)$enlace['empresa_id'],
            (int)($enlace['creado_por'] ?? 0),
            $archivo['tmp_name'],
            (string)($archivo['name'] ?? ''),
            true,
            'PORTAL',
            (int)$enlace['estudio_cliente_id'],
            (int)$enlace['enlace_id']
        );

        $esDup = ($res['bandeja_estado'] === 'DUPLICADO');
        $mensaje = $esDup
            ? 'El comprobante ya fue recibido previamente (duplicado).'
            : 'Comprobante recibido exitosamente.';

        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'data' => [
                'id' => (int)$res['bandeja_documento_id'],
                'estado' => $res['bandeja_estado'],
                'duplicado' => $esDup,
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ---------------------------------------------------------------------------------------------------
// Manejo de peticiones GET (Pantalla del portal)
// ---------------------------------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="google" content="notranslate">
    <title>Recepción de Comprobantes</title>
    <link rel="stylesheet" href="<?= asset_local('css/bootstrap-5.3.3/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_local('css/bootstrap-icons-1.11.3/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_local('fonts/inter/inter.css') ?>">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f4f6f9;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .portal-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .dropzone-box {
            border: 2px dashed #0d6efd;
            border-radius: 12px;
            background: #f8fafc;
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .dropzone-box:hover, .dropzone-box.dragover {
            background: #eef2ff;
            border-color: #0b5ed7;
            transform: scale(1.005);
        }
        .file-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .progress-thin {
            height: 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body>

    <header class="portal-header shadow-sm">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary text-white rounded p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-inbox-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold">Portal de Recepción de Comprobantes</h5>
                        <small class="text-muted">Carga segura de facturas, recibos y extractos</small>
                    </div>
                </div>
                <?php if ($enlace): ?>
                    <div class="text-end">
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-shield-check text-success me-1"></i>Enlace verificado
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container my-auto py-3" style="max-width: 760px;">
        <?php if (!$enlace): ?>
            <div class="card shadow-sm border-0 text-center p-4">
                <div class="card-body py-4">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3.5rem;"></i>
                    </div>
                    <h4 class="fw-bold text-dark">Enlace no válido o expirado</h4>
                    <p class="text-muted mb-4">
                        El enlace utilizado no es válido, ha caducado o ha sido desactivado. Por favor, comuníquese con el estudio contable o la empresa emisora para solicitar un nuevo enlace de subida.
                    </p>
                    <a href="javascript:location.reload()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reintentar
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-2">
                                <?= htmlspecialchars($enlace['empresa_nombre']) ?>
                            </span>
                            <?php if (!empty($enlace['cliente_razon_social'])): ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-2 ms-1">
                                    Cliente: <?= htmlspecialchars($enlace['cliente_razon_social']) ?>
                                </span>
                            <?php endif; ?>
                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($enlace['etiqueta']) ?></h4>
                            <p class="text-muted small mb-0">
                                Suba sus comprobantes para que sean procesados por el equipo administrativo y contable.
                            </p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-secondary border">
                                Cupo hoy: <strong class="text-dark" id="txtCupo"><?= $enlace['cupo_disponible'] ?></strong> / <?= $enlace['max_archivos_dia'] ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($enlace['cupo_disponible'] <= 0): ?>
                        <div class="alert alert-warning d-flex align-items-center mb-0">
                            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                            <div>Se ha completado el cupo diario de envíos para este enlace. Podrá continuar enviando comprobantes a partir de mañana.</div>
                        </div>
                    <?php else: ?>
                        <!-- Dropzone -->
                        <div class="dropzone-box" id="dropzone">
                            <input type="file" id="inputFile" multiple accept="application/pdf,image/jpeg,image/png" class="d-none">
                            <div class="mb-3">
                                <i class="bi bi-cloud-arrow-up-fill text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="fw-bold mb-1">Arrastre sus comprobantes aquí</h5>
                            <p class="text-muted small mb-3">o haga clic para seleccionarlos desde su computadora o teléfono</p>
                            <button type="button" class="btn btn-primary px-4" id="btnExaminar">
                                <i class="bi bi-folder2-open me-2"></i>Elegir archivos / Tomar foto
                            </button>
                            <div class="mt-3 text-muted" style="font-size: 0.8rem;">
                                Formatos permitidos: <strong>PDF, JPG, PNG</strong> · Máximo: <strong><?= round($enlace['max_bytes_archivo'] / 1048576) ?> MB</strong> por archivo
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lista de subidas en esta sesión -->
            <div class="card shadow-sm border-0 d-none" id="cardHistorial">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Comprobantes enviados en esta sesión</h6>
                    <span class="badge bg-primary" id="badgeContador">0</span>
                </div>
                <div class="card-body p-3" id="listaArchivos"></div>
            </div>

            <div class="text-center text-muted small mt-4">
                <i class="bi bi-lock-fill me-1"></i>Conexión cifrada. Sus comprobantes se procesan de forma privada y segura.
            </div>
        <?php endif; ?>
    </main>

    <footer class="text-center py-3 text-muted small mt-auto border-top bg-white">
        &copy; <?= date('Y') ?> Sistema de Gestión · Todos los derechos reservados.
    </footer>

    <?php if ($enlace && $enlace['cupo_disponible'] > 0): ?>
    <script>
    (function() {
        const token = <?= json_encode($token) ?>;
        const dropzone = document.getElementById('dropzone');
        const inputFile = document.getElementById('inputFile');
        const btnExaminar = document.getElementById('btnExaminar');
        const cardHistorial = document.getElementById('cardHistorial');
        const listaArchivos = document.getElementById('listaArchivos');
        const badgeContador = document.getElementById('badgeContador');
        const txtCupo = document.getElementById('txtCupo');

        let cupo = <?= (int)$enlace['cupo_disponible'] ?>;
        let totalEnviados = 0;
        let cola = [];
        let procesando = false;

        btnExaminar.addEventListener('click', () => inputFile.click());
        dropzone.addEventListener('click', (e) => {
            if (e.target !== btnExaminar) inputFile.click();
        });

        ['dragenter', 'dragover'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files) {
                agregarArchivos(e.dataTransfer.files);
            }
        });

        inputFile.addEventListener('change', () => {
            if (inputFile.files) {
                agregarArchivos(inputFile.files);
                inputFile.value = '';
            }
        });

        function agregarArchivos(files) {
            if (!files.length) return;
            cardHistorial.classList.remove('d-none');

            Array.from(files).forEach(file => {
                const item = crearItemUi(file);
                listaArchivos.prepend(item.el);
                cola.push({ file, ui: item });
            });

            procesarCola();
        }

        function crearItemUi(file) {
            const el = document.createElement('div');
            el.className = 'file-item';
            const sizeMb = (file.size / 1048576).toFixed(2);
            el.innerHTML = `
                <div class="d-flex align-items-center gap-3 w-100 me-3">
                    <i class="bi bi-file-earmark-text fs-4 text-secondary"></i>
                    <div class="flex-grow-1 overflow-hidden" style="max-width: 480px;">
                        <div class="fw-semibold text-truncate small">${escapeHtml(file.name)}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">${sizeMb} MB</div>
                        <div class="progress progress-thin mt-1 d-none">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="item-status text-end" style="min-width: 140px;">
                    <span class="badge bg-secondary-subtle text-secondary border">En espera</span>
                </div>
            `;
            return {
                el,
                progressBar: el.querySelector('.progress-bar'),
                progressWrap: el.querySelector('.progress'),
                status: el.querySelector('.item-status')
            };
        }

        async function procesarCola() {
            if (procesando || !cola.length) return;
            procesando = true;

            const actual = cola.shift();
            await subirArchivo(actual.file, actual.ui);

            procesando = false;
            procesarCola();
        }

        function subirArchivo(file, ui) {
            return new Promise((resolve) => {
                if (cupo <= 0) {
                    ui.status.innerHTML = `<span class="badge bg-danger">Cupo agotado</span>`;
                    resolve();
                    return;
                }

                ui.progressWrap.classList.remove('d-none');
                ui.status.innerHTML = `<span class="badge bg-primary-subtle text-primary">Enviando...</span>`;

                const formData = new FormData();
                formData.append('t', token);
                formData.append('archivo', file);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        const pct = Math.round((e.loaded / e.total) * 100);
                        ui.progressBar.style.width = pct + '%';
                    }
                };

                xhr.onload = () => {
                    ui.progressWrap.classList.add('d-none');
                    try {
                        const resp = JSON.parse(xhr.responseText || '{}');
                        if (xhr.status === 200 && resp.success) {
                            totalEnviados++;
                            badgeContador.textContent = totalEnviados;
                            cupo = Math.max(0, cupo - 1);
                            txtCupo.textContent = cupo;

                            if (resp.data && resp.data.duplicado) {
                                ui.status.innerHTML = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="${escapeHtml(resp.message)}"><i class="bi bi-exclamation-triangle me-1"></i>Ya existía</span>`;
                            } else {
                                ui.status.innerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Recibido</span>`;
                            }
                        } else {
                            ui.status.innerHTML = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="${escapeHtml(resp.message || 'Error')}"><i class="bi bi-x-circle me-1"></i>Error</span>`;
                        }
                    } catch (e) {
                        ui.status.innerHTML = `<span class="badge bg-danger">Error de respuesta</span>`;
                    }
                    resolve();
                };

                xhr.onerror = () => {
                    ui.progressWrap.classList.add('d-none');
                    ui.status.innerHTML = `<span class="badge bg-danger">Fallo de red</span>`;
                    resolve();
                };

                xhr.send(formData);
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>
