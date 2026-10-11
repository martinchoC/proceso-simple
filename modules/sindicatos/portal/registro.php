<?php
declare(strict_types=1);

$slug = htmlspecialchars(trim($_GET['s'] ?? 'sindicato'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autoalta de Empleador - Portal Sindical</title>
    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
        .registro-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 720px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .registro-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            padding: 2.5rem 2rem;
            color: #ffffff;
            text-align: center;
        }
        .registro-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .registro-header p {
            color: #bfdbfe;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        .registro-body {
            padding: 2.5rem;
        }
        .form-control:focus, .form-select:focus {
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
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.4);
        }
    </style>
</head>
<body>
    <div class="registro-card">
        <div class="registro-header">
            <i class="fas fa-landmark fa-3x mb-3 text-white-50"></i>
            <h1 id="tituloSindicato">Portal de Empleadores</h1>
            <p>Formulario de adhesión y autoalta para presentación de DDJJ y aportes sindicales</p>
        </div>
        <div class="registro-body">
            <form id="formRegistroPortal">
                <input type="hidden" name="accion" value="registrar_empleador">
                <input type="hidden" name="slug" id="slug" value="<?= $slug ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="cuit" class="form-label fw-semibold">CUIT de la Empresa <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="cuit" name="cuit" placeholder="ej: 30123456789" required maxlength="11">
                        </div>
                        <small class="text-muted">11 dígitos sin guiones. Se utilizará como su usuario de acceso.</small>
                    </div>

                    <div class="col-md-6">
                        <label for="razon_social" class="form-label fw-semibold">Razón Social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="razon_social" name="razon_social" placeholder="Denominación fiscal o comercial" required maxlength="150">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold">Correo Electrónico Oficial <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="rrhh@empresa.com" required>
                        </div>
                        <small class="text-muted">Recibirá el enlace para crear su contraseña segura.</small>
                    </div>

                    <div class="col-md-6">
                        <label for="telefono" class="form-label fw-semibold">Teléfono de Contacto</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control" id="telefono" name="telefono" placeholder="Cod. área y número">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label for="domicilio" class="form-label fw-semibold">Domicilio Legal</label>
                        <input type="text" class="form-control" id="domicilio" name="domicilio" placeholder="Calle, número, localidad">
                    </div>

                    <div class="col-md-4">
                        <label for="rubro" class="form-label fw-semibold">Rubro / Actividad</label>
                        <input type="text" class="form-control" id="rubro" name="rubro" placeholder="Comercio, servicios, etc.">
                    </div>

                    <div class="col-12 mt-4 text-center">
                        <button type="submit" class="btn btn-primary btn-submit w-100 shadow" id="btnEnviar">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud de Alta
                        </button>
                    </div>
                </div>
            </form>
            <div class="text-center mt-4 d-flex justify-content-between">
                <a href="../../../login.php" class="text-decoration-none small text-muted">
                    <i class="fas fa-sign-in-alt me-1"></i>¿Ya tiene cuenta? Iniciar sesión
                </a>
                <a href="recuperar.php?s=<?= $slug ?>" class="text-decoration-none small text-muted">
                    <i class="fas fa-key me-1"></i>¿Olvidó su contraseña?
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function () {
            var slug = $('#slug').val();

            // Consultar datos institucionales
            $.get('registro_ajax.php', { accion: 'info_sindicato', slug: slug }, function (res) {
                if (res.success && res.data) {
                    $('#tituloSindicato').text(res.data.sindicato_nombre);
                }
            }, 'json');

            $('#formRegistroPortal').on('submit', function (e) {
                e.preventDefault();
                var cuit = $('#cuit').val().replace(/\D/g, '');
                if (cuit.length !== 11) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'CUIT Inválido',
                        text: 'El CUIT debe contener exactamente 11 dígitos numéricos.'
                    });
                    return;
                }

                var btn = $('#btnEnviar');
                var orig = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Procesando alta...');

                $.ajax({
                    url: 'registro_ajax.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function (res) {
                        btn.prop('disabled', false).html(orig);
                        if (res.success) {
                            var extraHtml = res.message;
                            if (res.token_simulado) {
                                var actLink = `activar.php?s=${encodeURIComponent(slug)}&token=${res.token_simulado}`;
                                extraHtml += `<br><br><a href="${actLink}" class="btn btn-success"><i class="fas fa-check-circle me-1"></i>Establecer Contraseña Ahora</a>`;
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Solicitud Registrada',
                                html: extraHtml,
                                showConfirmButton: !res.token_simulado,
                                confirmButtonText: 'Aceptar'
                            });
                            $('#formRegistroPortal')[0].reset();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo completar el registro',
                                text: res.message
                            });
                        }
                    },
                    error: function () {
                        btn.prop('disabled', false).html(orig);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo comunicar con el servidor.'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
