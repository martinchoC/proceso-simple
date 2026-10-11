<?php
declare(strict_types=1);

/**
 * modules/sindicatos/medios_pago_model.php
 * Modelo para configuración de medios de cobro y credenciales bancarias (Página 1006).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/secretos.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_medios_pago(mysqli $conexion, int $empresa_id, int $pagina_id): array
{
    $sql = "SELECT mp.*, er.estado_registro
            FROM sindi__medios_pago mp
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = mp.tabla_estado_registro_id
            WHERE mp.empresa_id = ?
            ORDER BY mp.orden ASC, mp.codigo ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $medioPagoId = (int)$row['medio_pago_id'];

        // Enmascarar credenciales
        $row['tiene_clave'] = !empty($row['clave']);
        $row['tiene_client_secret'] = !empty($row['client_secret']);
        $row['tiene_sftp_clave'] = !empty($row['sftp_clave']);
        unset($row['clave'], $row['client_secret'], $row['sftp_clave']);

        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $medioPagoId);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_medio_pago(mysqli $conexion, int $empresa_id, int $medio_pago_id): ?array
{
    $sql = "SELECT * FROM sindi__medios_pago WHERE medio_pago_id = ? AND empresa_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "ii", $medio_pago_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($row) {
        $row['tiene_clave'] = !empty($row['clave']);
        $row['tiene_client_secret'] = !empty($row['client_secret']);
        $row['tiene_sftp_clave'] = !empty($row['sftp_clave']);
        // Limpiar para no enviar secretos en claro
        $row['clave'] = $row['tiene_clave'] ? '••••••••' : '';
        $row['client_secret'] = $row['tiene_client_secret'] ? '••••••••' : '';
        $row['sftp_clave'] = $row['tiene_sftp_clave'] ? '••••••••' : '';
    }

    return $row;
}

function sindi_guardar_medio_pago(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $id = isset($data['medio_pago_id']) ? (int)$data['medio_pago_id'] : 0;
    $codigo = strtoupper(trim($data['codigo'] ?? ''));
    $nombre = trim($data['nombre'] ?? '');
    $driver = in_array($data['driver'] ?? '', ['BNA_BARRAS','RED_LINK','INTERBANKING','BAPRO','MANUAL']) ? $data['driver'] : 'MANUAL';
    $ambiente = in_array($data['ambiente'] ?? '', ['PRUEBA','PRODUCCION']) ? $data['ambiente'] : 'PRUEBA';
    $orden = intval($data['orden'] ?? 0);
    $tesoreria_cuenta_codigo = trim($data['tesoreria_cuenta_codigo'] ?? '');
    $ente_acuerdo = trim($data['ente_acuerdo'] ?? '');
    $url_servicio = trim($data['url_servicio'] ?? '');
    $url_autenticacion = trim($data['url_autenticacion'] ?? '');
    $client_id = trim($data['client_id'] ?? '');
    $usuario = trim($data['usuario'] ?? '');
    $publica_deuda = !empty($data['publica_deuda']) ? 1 : 0;
    $rendicion_modo = in_array($data['rendicion_modo'] ?? '', ['API','SFTP','ARCHIVO_MANUAL']) ? $data['rendicion_modo'] : 'ARCHIVO_MANUAL';
    $sftp_host = trim($data['sftp_host'] ?? '');
    $sftp_puerto = intval($data['sftp_puerto'] ?? 22);
    $sftp_usuario = trim($data['sftp_usuario'] ?? '');
    $sftp_carpeta_remota = trim($data['sftp_carpeta_remota'] ?? '');
    $sftp_patron_archivo = trim($data['sftp_patron_archivo'] ?? '');
    $barras_prefijo = trim($data['barras_prefijo'] ?? '');

    $nueva_clave = trim($data['clave'] ?? '');
    $nueva_client_secret = trim($data['client_secret'] ?? '');
    $nueva_sftp_clave = trim($data['sftp_clave'] ?? '');

    if ($codigo === '' || $nombre === '') {
        return ['success' => false, 'message' => 'El código y el nombre del medio de pago son obligatorios.'];
    }

    // Obtener actual si edita
    $actual = null;
    if ($id > 0) {
        $stmtA = mysqli_prepare($conexion, "SELECT * FROM sindi__medios_pago WHERE medio_pago_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmtA, "ii", $id, $empresa_id);
        mysqli_stmt_execute($stmtA);
        $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));
        mysqli_stmt_close($stmtA);
    }

    $clave_final = $actual['clave'] ?? null;
    if ($nueva_clave !== '' && $nueva_clave !== '••••••••') {
        $clave_final = secreto_cifrar($nueva_clave);
    }

    $secret_final = $actual['client_secret'] ?? null;
    if ($nueva_client_secret !== '' && $nueva_client_secret !== '••••••••') {
        $secret_final = secreto_cifrar($nueva_client_secret);
    }

    $sftp_clave_final = $actual['sftp_clave'] ?? null;
    if ($nueva_sftp_clave !== '' && $nueva_sftp_clave !== '••••••••') {
        $sftp_clave_final = secreto_cifrar($nueva_sftp_clave);
    }

    if ($id > 0) {
        $sql = "UPDATE sindi__medios_pago SET
                codigo = ?, nombre = ?, driver = ?, ambiente = ?, orden = ?,
                tesoreria_cuenta_codigo = ?, ente_acuerdo = ?, url_servicio = ?, url_autenticacion = ?,
                client_id = ?, client_secret = ?, usuario = ?, clave = ?, publica_deuda = ?,
                rendicion_modo = ?, sftp_host = ?, sftp_puerto = ?, sftp_usuario = ?, sftp_clave = ?,
                sftp_carpeta_remota = ?, sftp_patron_archivo = ?, barras_prefijo = ?,
                usuario_actualizacion_id = ?, fecha_actualizacion = NOW()
                WHERE medio_pago_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return ['success' => false, 'message' => 'Error al preparar actualización: ' . mysqli_error($conexion)];

        mysqli_stmt_bind_param(
            $stmt,
            "ssssissssssssisississsiii",
            $codigo, $nombre, $driver, $ambiente, $orden,
            $tesoreria_cuenta_codigo, $ente_acuerdo, $url_servicio, $url_autenticacion,
            $client_id, $secret_final, $usuario, $clave_final, $publica_deuda,
            $rendicion_modo, $sftp_host, $sftp_puerto, $sftp_usuario, $sftp_clave_final,
            $sftp_carpeta_remota, $sftp_patron_archivo, $barras_prefijo,
            $usuario_id, $id, $empresa_id
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) return ['success' => false, 'message' => 'Error al guardar medio de pago: ' . mysqli_error($conexion)];
        return ['success' => true, 'message' => 'Medio de pago actualizado correctamente.'];
    } else {
        $sql = "INSERT INTO sindi__medios_pago (
                empresa_id, codigo, nombre, driver, ambiente, orden,
                tesoreria_cuenta_codigo, ente_acuerdo, url_servicio, url_autenticacion,
                client_id, client_secret, usuario, clave, publica_deuda,
                rendicion_modo, sftp_host, sftp_puerto, sftp_usuario, sftp_clave,
                sftp_carpeta_remota, sftp_patron_archivo, barras_prefijo,
                tabla_estado_registro_id, usuario_creacion_id, fecha_creacion
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW()
            )";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return ['success' => false, 'message' => 'Error al preparar inserción: ' . mysqli_error($conexion)];

        mysqli_stmt_bind_param(
            $stmt,
            "issssissssssssisississsi",
            $empresa_id, $codigo, $nombre, $driver, $ambiente, $orden,
            $tesoreria_cuenta_codigo, $ente_acuerdo, $url_servicio, $url_autenticacion,
            $client_id, $secret_final, $usuario, $clave_final, $publica_deuda,
            $rendicion_modo, $sftp_host, $sftp_puerto, $sftp_usuario, $sftp_clave_final,
            $sftp_carpeta_remota, $sftp_patron_archivo, $barras_prefijo,
            $usuario_id
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) return ['success' => false, 'message' => 'Error al registrar medio de pago: ' . mysqli_error($conexion)];
        return ['success' => true, 'message' => 'Medio de pago registrado exitosamente.'];
    }
}
