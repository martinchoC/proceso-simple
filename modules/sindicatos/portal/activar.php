<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../core/assets.php';

$slug = htmlspecialchars(trim($_GET['s'] ?? 'sindicato'), ENT_QUOTES, 'UTF-8');
$token = htmlspecialchars(trim($_GET['token'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar Cuenta / Establecer Contraseña - Portal Sindical</title>
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
        .activar-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .activar-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            padding: 2.5rem 2rem;
            color: #ffffff;
            text-align: center;
        }
        .activar-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .activar-header p {
            color: #bfdbfe;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        .activar-body {
            padding: 2.25rem;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.2);
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
    <div class="activar-card">
        <div class="activar-header">
            <i class="fas fa-shield-alt fa-3x mb-3 text-white-50"></i>
            <h1 id="tituloHeader">Activación de Cuenta</h1>
            <p>Establezca una contraseña segura para acceder al Portal Sindical</p>
        </div>
        <div class="activar-body">
            <div id="alertaTokenInvalido" class="alert alert-danger d-none" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span id="txtErrorToken">El enlace de activación no es válido o ha expirado.</span>
            </div>

            <div id="cargandoToken" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2 mb-0">Verificando enlace de activación...</p>
            </div>

            <form id="formActivar" class="d-none">
                <input type="hidden" name="accion" value="activar_cuenta">
                <input type="hidden" name="slug" id="slug" value="<?= $slug ?>">
                <input type="hidden" name="token" id="token" value="<?= $token ?>">

                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold">Usuario / CUIT</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control bg-light" id="txtUsuario" readonly>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold">Razón Social</label>
                    <input type="text" class="form-control bg-light" id="txtNombre" readonly>
                </div>

                <div class="mb-3">
                    <label for="clave" class="form-label fw-semibold">Nueva Contraseña <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="clave" name="clave" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="clave_confirmacion" class="form-label fw-semibold">Confirmar Contraseña <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                        <input type="password" class="form-control" id="clave_confirmacion" name="clave_confirmacion" required minlength="6" placeholder="Repita la contraseña">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-submit text-white" id="btnGuardar">
                    <i class="fas fa-key me-2"></i> Activar y Acceder
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="../../../login.php" class="text-decoration-none small text-muted">
                    <i class="fas fa-arrow-left me-1"></i> Ir al Inicio de Sesión
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= asset_local('js/bootstrap-5.3.2/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const token = document.getElementById('token').value;
            const slug = document.getElementById('slug').value;

            if (!token) {
                document.getElementById('cargandoToken').classList.add('d-none');
                document.getElementById('alertaTokenInvalido').classList.remove('d-none');
                document.getElementById('txtErrorToken').textContent = 'No se proporcionó ningún token de activación en el enlace.';
                return;
            }

            // Validar token contra el backend
            fetch(`registro_ajax.php?accion=validar_token&slug=${encodeURIComponent(slug)}&token=${encodeURIComponent(token)}`)
                .then(r => r.json())
                .then(res => {
                    document.getElementById('cargandoToken').classList.add('d-none');
                    if (!res.success) {
                        document.getElementById('alertaTokenInvalido').classList.remove('d-none');
                        document.getElementById('txtErrorToken').textContent = res.message || 'El enlace ha expirado o no es válido.';
                        return;
                    }

                    document.getElementById('txtUsuario').value = res.data.usuario;
                    document.getElementById('txtNombre').value = res.data.usuario_nombre;
                    document.getElementById('formActivar').classList.remove('d-none');
                })
                .catch(() => {
                    document.getElementById('cargandoToken').classList.add('d-none');
                    document.getElementById('alertaTokenInvalido').classList.remove('d-none');
                    document.getElementById('txtErrorToken').textContent = 'Error de comunicación con el servidor.';
                });

            // Enviar formulario
            const form = document.getElementById('formActivar');
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const p1 = document.getElementById('clave').value;
                const p2 = document.getElementById('clave_confirmacion').value;

                if (p1.length < 6) {
                    Swal.fire('Atención', 'La contraseña debe contener al menos 6 caracteres.', 'warning');
                    return;
                }
                if (p1 !== p2) {
                    Swal.fire('Atención', 'Las contraseñas no coinciden.', 'warning');
                    return;
                }

                const btn = document.getElementById('btnGuardar');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';

                const formData = new FormData(form);

                fetch('registro_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-key me-2"></i> Activar y Acceder';

                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Cuenta Activada!',
                            text: res.message,
                            confirmButtonText: 'Ir a Iniciar Sesión'
                        }).then(() => {
                            window.location.href = '../../../login.php';
                        });
                    } else {
                        Swal.fire('Error', res.message || 'No se pudo activar la cuenta.', 'error');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-key me-2"></i> Activar y Acceder';
                    Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                });
            });
        });
    </script>
</body>
</html>
