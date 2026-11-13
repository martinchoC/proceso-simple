<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../core/assets.php';

$slug = htmlspecialchars(trim($_GET['s'] ?? 'sindicato'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Portal Sindical</title>
    <!-- Google Font: Inter -->
    <link href="<?= asset_local('fonts/inter/inter-300-700.css') ?>" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="<?= asset_local('css/bootstrap-5.3.2/bootstrap.min.css') ?>" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="<?= asset_local('css/fontawesome-6.4.2/css/all.min.css') ?>">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?= asset_local('css/sweetalert2-11.26.25/sweetalert2.min.css') ?>">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            color: #1e293b;
        }
        .recuperar-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .recuperar-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            padding: 2.5rem 2rem;
            color: #ffffff;
            text-align: center;
        }
        .recuperar-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .recuperar-header p {
            color: #bfdbfe;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        .recuperar-body {
            padding: 2.25rem;
        }
        .btn-submit {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            border: none;
            padding: 0.85rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
            width: 100%;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.4);
        }
    </style>
</head>
<body>
    <div class="recuperar-card">
        <div class="recuperar-header">
            <i class="fas fa-unlock-alt fa-3x mb-3 text-white-50"></i>
            <h1>Recupero de Contraseña</h1>
            <p>Ingrese sus datos para recibir un enlace de restablecimiento seguro</p>
        </div>
        <div class="recuperar-body">
            <form id="formRecuperar">
                <input type="hidden" name="accion" value="solicitar_recupero">
                <input type="hidden" name="slug" id="slug" value="<?= $slug ?>">

                <div class="mb-3">
                    <label for="cuit" class="form-label fw-semibold">CUIT <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        <input type="text" class="form-control" id="cuit" name="cuit" required maxlength="11" placeholder="11 dígitos sin guiones">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="email" class="form-label fw-semibold">Correo Electrónico Registrado <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-submit text-white" id="btnEnviar">
                    <i class="fas fa-paper-plane me-2"></i> Solicitar Restablecimiento
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="../../../login.php" class="text-decoration-none small text-muted">
                    <i class="fas fa-arrow-left me-1"></i> Volver a Iniciar Sesión
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= asset_local('js/bootstrap-5.3.2/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        document.getElementById('formRecuperar').addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnEnviar');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';

            const formData = new FormData(document.getElementById('formRecuperar'));

            fetch('registro_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Solicitar Restablecimiento';

                if (res.success) {
                    let mensajeExtra = '';
                    if (res.token_simulado) {
                        const link = `activar.php?s=${encodeURIComponent(document.getElementById('slug').value)}&token=${res.token_simulado}`;
                        mensajeExtra = `<br><br><small class="text-muted">Enlace para desarrollo local:<br><a href="${link}" class="btn btn-sm btn-outline-primary mt-2">Continuar con este enlace</a></small>`;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Solicitud Enviada',
                        html: res.message + mensajeExtra
                    });
                } else {
                    Swal.fire('Error', res.message || 'No se pudo procesar la solicitud.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Solicitar Restablecimiento';
                Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
            });
        });
    </script>
</body>
</html>
