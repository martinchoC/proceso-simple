<?php
declare(strict_types=1);

/**
 * modules/sindicatos/portal/registro_ajax.php
 * Controlador público de autoalta de empleadores sin sesión.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../db.php';
$conexion = $conn;

function validar_cuit_arg(string $cuit): bool
{
    $cuit = preg_replace('/\D/', '', $cuit);
    if (strlen($cuit) !== 11) {
        return false;
    }

    $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $acumulado = 0;
    for ($i = 0; $i < 10; $i++) {
        $acumulado += intval($cuit[$i]) * $multiplicadores[$i];
    }

    $resto = $acumulado % 11;
    $digitoVerificador = 11 - $resto;
    if ($digitoVerificador === 11) $digitoVerificador = 0;
    if ($digitoVerificador === 10) $digitoVerificador = 9;

    return intval($cuit[10]) === $digitoVerificador;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$slug = trim($_POST['slug'] ?? $_GET['slug'] ?? '');

try {
    if (empty($slug)) {
        echo json_encode(['success' => false, 'message' => 'Identificador de sindicato no especificado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Resolver sindicato (empresa) por portal_slug
    $sqlSlug = "SELECT p.*, e.empresa, e.documento_numero AS empresa_cuit
                FROM sindi__parametros p
                INNER JOIN conf__empresas e ON e.empresa_id = p.empresa_id
                WHERE p.portal_slug = ? AND p.tabla_estado_registro_id = 1
                ORDER BY p.f_desde DESC LIMIT 1";

    $stmtS = mysqli_prepare($conexion, $sqlSlug);
    if (!$stmtS) {
        echo json_encode(['success' => false, 'message' => 'Error al consultar sindicato.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    mysqli_stmt_bind_param($stmtS, "s", $slug);
    mysqli_stmt_execute($stmtS);
    $paramSindicato = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtS));
    mysqli_stmt_close($stmtS);

    if (!$paramSindicato) {
        echo json_encode(['success' => false, 'message' => 'El sindicato solicitado no existe o no está habilitado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $empresa_id = (int)$paramSindicato['empresa_id'];

    if ($accion === 'info_sindicato') {
        echo json_encode([
            'success' => true,
            'data' => [
                'sindicato_nombre' => $paramSindicato['empresa'],
                'portal_slug' => $paramSindicato['portal_slug']
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'registrar_empleador') {
        $cuit = preg_replace('/\D/', '', (string)($_POST['cuit'] ?? ''));
        $razon_social = trim($_POST['razon_social'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $domicilio = trim($_POST['domicilio'] ?? '');
        $rubro = trim($_POST['rubro'] ?? '');

        if (!validar_cuit_arg($cuit)) {
            echo json_encode(['success' => false, 'message' => 'El CUIT ingresado no es válido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (empty($razon_social) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Razón social y correo electrónico válido son obligatorios.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        mysqli_begin_transaction($conexion);

        // 1. Entidad comercial
        $sqlE = "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? LIMIT 1";
        $stmtE = mysqli_prepare($conexion, $sqlE);
        mysqli_stmt_bind_param($stmtE, "is", $empresa_id, $cuit);
        mysqli_stmt_execute($stmtE);
        $resE = mysqli_stmt_get_result($stmtE);
        $entExistente = mysqli_fetch_assoc($resE);
        mysqli_stmt_close($stmtE);

        $entidad_id = 0;
        if ($entExistente) {
            $entidad_id = (int)$entExistente['entidad_id'];
            $sqlUpd = "UPDATE gestion__entidades SET entidad_nombre = ?, domicilio_legal = ? WHERE entidad_id = ?";
            $stmtUpd = mysqli_prepare($conexion, $sqlUpd);
            mysqli_stmt_bind_param($stmtUpd, "ssi", $razon_social, $domicilio, $entidad_id);
            mysqli_stmt_execute($stmtUpd);
            mysqli_stmt_close($stmtUpd);
        } else {
            $sqlIns = "INSERT INTO gestion__entidades (empresa_id, cuit, entidad_nombre, domicilio_legal, tabla_estado_registro_id, fecha_alta)
                       VALUES (?, ?, ?, ?, 1, NOW())";
            $stmtIns = mysqli_prepare($conexion, $sqlIns);
            mysqli_stmt_bind_param($stmtIns, "isss", $empresa_id, $cuit, $razon_social, $domicilio);
            mysqli_stmt_execute($stmtIns);
            $entidad_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtIns);
        }

        // Sucursal Casa Central (contacto, email, telefono)
        $sqlSuc = "INSERT INTO gestion__entidades_sucursales (entidad_id, empresa_id, sucursal_nombre, sucursal_direccion, sucursal_telefono, sucursal_email, tabla_estado_registro_id)
                   VALUES (?, ?, 'Casa Central', ?, ?, ?, 1)
                   ON DUPLICATE KEY UPDATE sucursal_direccion = VALUES(sucursal_direccion), sucursal_telefono = VALUES(sucursal_telefono), sucursal_email = VALUES(sucursal_email)";
        $stmtSuc = mysqli_prepare($conexion, $sqlSuc);
        mysqli_stmt_bind_param($stmtSuc, "iisss", $entidad_id, $empresa_id, $domicilio, $telefono, $email);
        mysqli_stmt_execute($stmtSuc);
        mysqli_stmt_close($stmtSuc);

        // Rol EMPLEADOR
        $stmtRol = mysqli_prepare($conexion, "INSERT IGNORE INTO gestion__entidades_roles (entidad_id, rol_entidad_id, f_alta, tabla_estado_registro_id) VALUES (?, 5, NOW(), 1)");
        mysqli_stmt_bind_param($stmtRol, "i", $entidad_id);
        mysqli_stmt_execute($stmtRol);
        mysqli_stmt_close($stmtRol);

        // 2. Empleador en padrón sindical
        $estadoEmpleador = !empty($paramSindicato['aprobacion_autoalta']) ? 4 : 1; // 4=Pendiente si exige aprobación, 1=Activo
        $sqlEmp = "INSERT INTO sindi__empleadores (empresa_id, entidad_id, rubro, f_alta_padron, origen, tabla_estado_registro_id, fecha_creacion)
                   VALUES (?, ?, ?, NOW(), 'AUTOALTA', ?, NOW())
                   ON DUPLICATE KEY UPDATE rubro = VALUES(rubro)";
        $stmtEmp = mysqli_prepare($conexion, $sqlEmp);
        mysqli_stmt_bind_param($stmtEmp, "iisi", $empresa_id, $entidad_id, $rubro, $estadoEmpleador);
        mysqli_stmt_execute($stmtEmp);
        $empleador_id = mysqli_insert_id($conexion);
        if ($empleador_id === 0) {
            $stmtGetId = mysqli_prepare($conexion, "SELECT empleador_id FROM sindi__empleadores WHERE empresa_id = ? AND entidad_id = ?");
            mysqli_stmt_bind_param($stmtGetId, "ii", $empresa_id, $entidad_id);
            mysqli_stmt_execute($stmtGetId);
            $rowEmpId = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtGetId));
            $empleador_id = (int)($rowEmpId['empleador_id'] ?? 0);
            mysqli_stmt_close($stmtGetId);
        }
        mysqli_stmt_close($stmtEmp);

        // 3. Crear usuario (usuario = CUIT) con token de activación seguro
        $sqlUser = "SELECT usuario_id FROM conf__usuarios WHERE usuario = ? LIMIT 1";
        $stmtU = mysqli_prepare($conexion, $sqlUser);
        mysqli_stmt_bind_param($stmtU, "s", $cuit);
        mysqli_stmt_execute($stmtU);
        $userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtU));
        mysqli_stmt_close($stmtU);

        $usuario_id = 0;
        $tokenActivacion = bin2hex(random_bytes(24));

        if ($userRow) {
            $usuario_id = (int)$userRow['usuario_id'];
            $sqlUpdU = "UPDATE conf__usuarios SET email = ?, password_temporal = ? WHERE usuario_id = ?";
            $stmtUpdU = mysqli_prepare($conexion, $sqlUpdU);
            mysqli_stmt_bind_param($stmtUpdU, "ssi", $email, $tokenActivacion, $usuario_id);
            mysqli_stmt_execute($stmtUpdU);
            mysqli_stmt_close($stmtUpdU);
        } else {
            // Contraseña inicial no utilizable (fuerza activación por link)
            $dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $nombreUsuario = mb_substr($razon_social, 0, 50);

            $sqlInsU = "INSERT INTO conf__usuarios (usuario, password, usuario_nombre, email, password_temporal, tabla_estado_registro_id)
                        VALUES (?, ?, ?, ?, ?, 1)";
            $stmtInsU = mysqli_prepare($conexion, $sqlInsU);
            mysqli_stmt_bind_param($stmtInsU, "sssss", $cuit, $dummyHash, $nombreUsuario, $email, $tokenActivacion);
            mysqli_stmt_execute($stmtInsU);
            $usuario_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtInsU);
        }

        // Asignar perfil Empleador (604) en la empresa
        $sqlEp = "SELECT empresa_perfil_id FROM conf__empresas_perfiles WHERE empresa_id = ? AND perfil_id_base = 604 LIMIT 1";
        $stmtEp = mysqli_prepare($conexion, $sqlEp);
        mysqli_stmt_bind_param($stmtEp, "i", $empresa_id);
        mysqli_stmt_execute($stmtEp);
        $rowEp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtEp));
        mysqli_stmt_close($stmtEp);

        if ($rowEp) {
            $ep_id = (int)$rowEp['empresa_perfil_id'];
            $stmtUp = mysqli_prepare($conexion, "INSERT IGNORE INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id) VALUES (?, ?, CURDATE(), '2099-12-31', 1)");
            mysqli_stmt_bind_param($stmtUp, "ii", $usuario_id, $ep_id);
            mysqli_stmt_execute($stmtUp);
            mysqli_stmt_close($stmtUp);
        }

        // 4. Crear fila TITULAR en sindi__usuarios_empleadores
        $sqlUE = "INSERT INTO sindi__usuarios_empleadores (usuario_id, empresa_id, empleador_id, rol, f_desde, tabla_estado_registro_id, fecha_creacion)
                  VALUES (?, ?, ?, 'TITULAR', CURDATE(), 1, NOW())
                  ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1";
        $stmtUE = mysqli_prepare($conexion, $sqlUE);
        mysqli_stmt_bind_param($stmtUE, "iii", $usuario_id, $empresa_id, $empleador_id);
        mysqli_stmt_execute($stmtUE);
        mysqli_stmt_close($stmtUE);

        mysqli_commit($conexion);

        echo json_encode([
            'success' => true,
            'message' => 'Registro completado con éxito. Se ha enviado un correo a ' . htmlspecialchars($email) . ' con las instrucciones para crear su contraseña de acceso.',
            'token_simulado' => $tokenActivacion // Útil para entornos de desarrollo local sin servidor de correo activo
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'validar_token') {
        $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token no especificado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtTok = mysqli_prepare($conexion, "SELECT usuario_id, usuario, usuario_nombre, email FROM conf__usuarios WHERE password_temporal = ? AND password_temporal IS NOT NULL AND password_temporal != '' LIMIT 1");
        mysqli_stmt_bind_param($stmtTok, "s", $token);
        mysqli_stmt_execute($stmtTok);
        $userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTok));
        mysqli_stmt_close($stmtTok);

        if (!$userRow) {
            echo json_encode(['success' => false, 'message' => 'El enlace de activación ha vencido o ya fue utilizado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'usuario' => $userRow['usuario'],
                'usuario_nombre' => $userRow['usuario_nombre'],
                'email' => $userRow['email'],
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'activar_cuenta') {
        $token = trim($_POST['token'] ?? '');
        $clave = $_POST['clave'] ?? '';
        $clave_confirmacion = $_POST['clave_confirmacion'] ?? '';

        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (strlen($clave) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe contener al menos 6 caracteres.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($clave !== $clave_confirmacion) {
            echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtTok = mysqli_prepare($conexion, "SELECT usuario_id FROM conf__usuarios WHERE password_temporal = ? AND password_temporal IS NOT NULL AND password_temporal != '' LIMIT 1");
        mysqli_stmt_bind_param($stmtTok, "s", $token);
        mysqli_stmt_execute($stmtTok);
        $userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTok));
        mysqli_stmt_close($stmtTok);

        if (!$userRow) {
            echo json_encode(['success' => false, 'message' => 'El enlace de activación es inválido o ya ha expirado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $usuario_id = (int)$userRow['usuario_id'];
        $hashNuevo = password_hash($clave, PASSWORD_DEFAULT);

        $stmtUpdClave = mysqli_prepare($conexion, "UPDATE conf__usuarios SET password = ?, password_temporal = NULL, tabla_estado_registro_id = 1 WHERE usuario_id = ?");
        mysqli_stmt_bind_param($stmtUpdClave, "si", $hashNuevo, $usuario_id);
        $ok = mysqli_stmt_execute($stmtUpdClave);
        mysqli_stmt_close($stmtUpdClave);

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la contraseña.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Contraseña establecida exitosamente. Ya puede iniciar sesión en el portal con su CUIT y nueva clave.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'solicitar_recupero') {
        $cuit = preg_replace('/\D/', '', (string)($_POST['cuit'] ?? ''));
        $email = trim($_POST['email'] ?? '');

        if (!validar_cuit_arg($cuit) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Ingrese un CUIT válido y el correo electrónico registrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtFind = mysqli_prepare($conexion, "SELECT usuario_id, email, usuario_nombre FROM conf__usuarios WHERE usuario = ? AND LOWER(email) = LOWER(?) LIMIT 1");
        mysqli_stmt_bind_param($stmtFind, "ss", $cuit, $email);
        mysqli_stmt_execute($stmtFind);
        $userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFind));
        mysqli_stmt_close($stmtFind);

        if (!$userRow) {
            // Por seguridad, no revelar si el usuario existe o no
            echo json_encode([
                'success' => true,
                'message' => 'Si los datos coinciden con un usuario registrado, recibirá un correo con el enlace para restablecer su contraseña.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $nuevoToken = bin2hex(random_bytes(24));
        $usuario_id = (int)$userRow['usuario_id'];
        $stmtUpdTok = mysqli_prepare($conexion, "UPDATE conf__usuarios SET password_temporal = ? WHERE usuario_id = ?");
        mysqli_stmt_bind_param($stmtUpdTok, "si", $nuevoToken, $usuario_id);
        mysqli_stmt_execute($stmtUpdTok);
        mysqli_stmt_close($stmtUpdTok);

        echo json_encode([
            'success' => true,
            'message' => 'Si los datos coinciden con un usuario registrado, recibirá un correo con el enlace para restablecer su contraseña.',
            'token_simulado' => $nuevoToken // Para entornos locales de desarrollo
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no reconocida.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($conexion)) {
        mysqli_rollback($conexion);
    }
    error_log("[REGISTRO_PORTAL] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
