<?php
/**
 * scratch/crear_empresa_sindicato.php
 * Script para crear y aprovisionar una nueva empresa con el Módulo 6 (Sindicatos)
 * completamente configurado, con catálogos, cuentas, perfiles, accesos y datos de prueba.
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/permisos.php';

echo "====================================================================\n";
echo "CREACION Y CONFIGURACION DE NUEVA EMPRESA PARA MODULO SINDICATOS\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$conn->begin_transaction();

try {
    // 1. Determinar ID y crear la empresa en conf__empresas
    $nombreEmpresa = "Asociación Empleados de Comercio (AEC)";
    $cuitEmpresa = "30645678901";
    
    // Verificar si ya existe para no duplicar
    $chk = $conn->query("SELECT empresa_id FROM conf__empresas WHERE documento_numero = '$cuitEmpresa' LIMIT 1");
    if ($row = $chk->fetch_assoc()) {
        $nuevaEmpresaId = (int)$row['empresa_id'];
        echo "[-] La empresa ya existía con ID: $nuevaEmpresaId. Se actualizarán sus configuraciones.\n";
        $conn->query("UPDATE conf__empresas SET empresa = '$nombreEmpresa', tabla_estado_registro_id = 1 WHERE empresa_id = $nuevaEmpresaId");
    } else {
        $maxRes = $conn->query("SELECT MAX(empresa_id) AS max_id FROM conf__empresas");
        $maxRow = $maxRes->fetch_assoc();
        $nuevaEmpresaId = max(7, (int)($maxRow['max_id'] ?? 0) + 1);
        
        $sqlInsEmp = "INSERT INTO conf__empresas (
            empresa_id, empresa, documento_tipo_id, documento_numero, telefono,
            domicilio, localidad_id, condicion_fiscal_id, iibb_regimen_id,
            jurisdiccion_sede_id, email, tabla_estado_registro_id
        ) VALUES (
            $nuevaEmpresaId, '$nombreEmpresa', 1, '$cuitEmpresa', '0291-4550000',
            'Rodríguez 143', 26985, 4, 1, 1, 'contacto@aecbb.org.ar', 1
        )";
        $conn->query($sqlInsEmp);
        echo "[+] Empresa creada exitosamente con ID: $nuevaEmpresaId ($nombreEmpresa)\n";
    }

    // 2. Habilitar Módulos en conf__empresas_modulos
    // Modulo 6: Sindicatos, Modulo 2: Gestión de Empresas, Modulo 5: Gestión Normal
    $modulosHabilitar = [6, 2, 5];
    foreach ($modulosHabilitar as $modId) {
        $conn->query("INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id) 
                      VALUES ($nuevaEmpresaId, $modId, 1) 
                      ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1");
    }
    echo "[+] Módulos 6 (Sindicatos), 2 (Gestión) y 5 (Gestión Normal) habilitados.\n";

    // 3. Crear Sucursal Central en gestion__sucursales
    $chkSuc = $conn->query("SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = $nuevaEmpresaId LIMIT 1");
    if (!$chkSuc->fetch_assoc()) {
        $maxSucRes = $conn->query("SELECT MAX(sucursal_id) AS max_suc FROM gestion__sucursales");
        $nuevaSucId = ((int)$maxSucRes->fetch_assoc()['max_suc']) + 1;
        $conn->query("INSERT INTO gestion__sucursales (
            sucursal_id, empresa_id, sucursal_tipo_id, sucursal_nombre, descripcion,
            localidad_id, direccion, telefono, email, tabla_estado_registro_id
        ) VALUES (
            $nuevaSucId, $nuevaEmpresaId, 5, 'Sede Central AEC', 'Sede Gremial y Administrativa',
            26985, 'Rodríguez 143', '0291-4550000', 'contacto@aecbb.org.ar', 1
        )");
        echo "[+] Sucursal Central creada con ID: $nuevaSucId.\n";
    }

    // 4. Clonar parámetros de sindicato (sindi__parametros)
    $conn->query("DELETE FROM sindi__parametros WHERE empresa_id = $nuevaEmpresaId");
    $conn->query("INSERT INTO sindi__parametros (
        empresa_id, f_desde, f_hasta, convenio_id_default, portal_slug, dias_gracia,
        aprobacion_autoalta, email_avisos, media_jornada_piso, media_jornada_factor,
        smtp_host, smtp_puerto, smtp_seguridad, smtp_usuario, smtp_clave,
        tabla_estado_registro_id, fecha_creacion
    ) SELECT 
        $nuevaEmpresaId, f_desde, f_hasta, convenio_id_default, 'aecbb', dias_gracia,
        aprobacion_autoalta, 'contacto@aecbb.org.ar', media_jornada_piso, media_jornada_factor,
        smtp_host, smtp_puerto, smtp_seguridad, smtp_usuario, smtp_clave,
        tabla_estado_registro_id, NOW()
      FROM sindi__parametros WHERE empresa_id = 3");
    echo "[+] Parámetros sindicales configurados desde empresa 3.\n";

    // 5. Clonar Convenios y Conceptos desde empresa 3
    $conn->query("INSERT INTO sindi__convenios (empresa_id, codigo, nombre, descripcion, tabla_estado_registro_id)
                  SELECT $nuevaEmpresaId, codigo, nombre, descripcion, tabla_estado_registro_id 
                  FROM sindi__convenios WHERE empresa_id = 3
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");
    
    // Obtener nuevo convenio_id
    $convRes = $conn->query("SELECT convenio_id FROM sindi__convenios WHERE empresa_id = $nuevaEmpresaId LIMIT 1");
    $nuevoConvenioId = (int)($convRes->fetch_assoc()['convenio_id'] ?? 1);

    // Actualizar default en sindi__parametros
    $conn->query("UPDATE sindi__parametros SET convenio_id_default = $nuevoConvenioId WHERE empresa_id = $nuevaEmpresaId");

    // Conceptos
    $conn->query("INSERT INTO sindi__conceptos (
        empresa_id, convenio_id, codigo, descripcion, base_calculo, porcentaje,
        valor_fijo, solo_afiliados, aplica_piso_escala, incluye_sac, comprobante_tipo_id,
        cuenta_concepto_codigo, f_desde, f_hasta, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, $nuevoConvenioId, codigo, descripcion, base_calculo, porcentaje,
        valor_fijo, solo_afiliados, aplica_piso_escala, incluye_sac, comprobante_tipo_id,
        cuenta_concepto_codigo, f_desde, f_hasta, tabla_estado_registro_id
      FROM sindi__conceptos WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    echo "[+] Convenio 130/75 y Conceptos de Aporte clonados.\n";

    // 6. Clonar Categorías, Escalas y Categorías de Mora
    $conn->query("INSERT INTO sindi__categorias (
        empresa_id, convenio_id, codigo, nombre, descripcion, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, $nuevoConvenioId, codigo, nombre, descripcion, tabla_estado_registro_id 
      FROM sindi__categorias WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");

    // Mapear categorias_escalas
    $conn->query("INSERT INTO sindi__categorias_escalas (
        empresa_id, categoria_id, periodo, basico, rem_1, rem_2, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, c_nueva.categoria_id, ce.periodo, ce.basico, ce.rem_1, ce.rem_2, ce.tabla_estado_registro_id
      FROM sindi__categorias_escalas ce
      JOIN sindi__categorias c_orig ON c_orig.categoria_id = ce.categoria_id AND c_orig.empresa_id = 3
      JOIN sindi__categorias c_nueva ON c_nueva.codigo = c_orig.codigo AND c_nueva.empresa_id = $nuevaEmpresaId
      WHERE ce.empresa_id = 3
      ON DUPLICATE KEY UPDATE basico = VALUES(basico)");

    // Categorias de Mora
    $conn->query("INSERT INTO sindi__categorias_mora (
        empresa_id, codigo, nombre, dias_desde, dias_hasta, color, orden, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, codigo, nombre, dias_desde, dias_hasta, color, orden, tabla_estado_registro_id
      FROM sindi__categorias_mora WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");
    echo "[+] Categorías, Escalas Salariales y Categorías de Mora clonadas.\n";

    // 7. Clonar Medios de Pago y Tipos de Plan de Pagos
    $conn->query("INSERT INTO sindi__medios_pago (
        empresa_id, codigo, nombre, driver, ambiente, orden, tesoreria_cuenta_codigo,
        medio_pago_codigo, entidad_bancaria_id, url_servicio, url_autenticacion,
        ente_acuerdo, client_id, client_secret, usuario, clave, certificado_path,
        clave_privada_path, verificar_ssl, publica_deuda, concepto_defecto,
        rendicion_modo, sftp_host, sftp_puerto, sftp_usuario, sftp_clave,
        sftp_carpeta_remota, sftp_patron_archivo, barras_prefijo, barras_simbologia,
        tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, codigo, nombre, driver, ambiente, orden, tesoreria_cuenta_codigo,
        medio_pago_codigo, entidad_bancaria_id, url_servicio, url_autenticacion,
        ente_acuerdo, client_id, client_secret, usuario, clave, certificado_path,
        clave_privada_path, verificar_ssl, publica_deuda, concepto_defecto,
        rendicion_modo, sftp_host, sftp_puerto, sftp_usuario, sftp_clave,
        sftp_carpeta_remota, sftp_patron_archivo, barras_prefijo, barras_simbologia,
        tabla_estado_registro_id
      FROM sindi__medios_pago WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");

    $conn->query("INSERT INTO sindi__planes_tipos (
        empresa_id, codigo, nombre, sistema, cuotas_max, tasa_mensual,
        anticipo_min_porc, f_desde, f_hasta, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, codigo, nombre, sistema, cuotas_max, tasa_mensual,
        anticipo_min_porc, f_desde, f_hasta, tabla_estado_registro_id
      FROM sindi__planes_tipos WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");

    $conn->query("INSERT INTO sindi__afiliaciones_tipos (
        empresa_id, codigo, nombre, grupo, alta, requiere_empleador,
        paga_cuota_directa, importe_cuota, requiere_titular, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, codigo, nombre, grupo, alta, requiere_empleador,
        paga_cuota_directa, importe_cuota, requiere_titular, tabla_estado_registro_id
      FROM sindi__afiliaciones_tipos WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");

    $conn->query("INSERT INTO sindi__bajas_motivos (
        empresa_id, codigo, descripcion, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, codigo, descripcion, tabla_estado_registro_id 
      FROM sindi__bajas_motivos WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    echo "[+] Medios de Pago, Tipos de Planes, Afiliaciones y Motivos de Baja clonados.\n";

    // 8. Clonar Tasas de Interés y Vencimientos
    $conn->query("INSERT INTO sindi__tasas_interes (
        empresa_id, clase, tasa_mensual, f_desde, f_hasta, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, clase, tasa_mensual, f_desde, f_hasta, tabla_estado_registro_id
      FROM sindi__tasas_interes WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE tasa_mensual = VALUES(tasa_mensual)");

    $conn->query("INSERT INTO sindi__vencimientos (
        empresa_id, periodo, terminacion_cuit, tipo_vencimiento, f_vto, tabla_estado_registro_id
    ) SELECT 
        $nuevaEmpresaId, periodo, terminacion_cuit, tipo_vencimiento, f_vto, tabla_estado_registro_id
      FROM sindi__vencimientos WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE f_vto = VALUES(f_vto)");
    echo "[+] Tasas de Interés y Cronograma de Vencimientos clonados.\n";

    // 9. Cuentas Contables y Conceptos en gestion__empresas_cuentas_conceptos
    $conn->query("INSERT INTO gestion__empresas_cuentas_conceptos (
        empresa_id, concepto, cont_cuenta_id, tabla_estado_registro_id, fecha_creacion, usuario_id
    ) SELECT 
        $nuevaEmpresaId, concepto, cont_cuenta_id, tabla_estado_registro_id, NOW(), 1
      FROM gestion__empresas_cuentas_conceptos 
      WHERE empresa_id = 3
      ON DUPLICATE KEY UPDATE cont_cuenta_id = VALUES(cont_cuenta_id)");
    echo "[+] Cuentas y Conceptos Contables mapeados.\n";

    // 10. Perfiles y Permisos para el Módulo 6 y Módulo 2
    // Sembrar conf__empresas_perfiles
    $conn->query("INSERT IGNORE INTO conf__empresas_perfiles (
        empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id
    ) SELECT $nuevaEmpresaId, em.modulo_id, p.perfil_id, p.perfil_nombre, 1
      FROM conf__empresas_modulos em
      JOIN conf__perfiles p ON p.modulo_id = em.modulo_id
      WHERE em.empresa_id = $nuevaEmpresaId AND em.tabla_estado_registro_id = 1");

    // Perfil "Acceso total" para la nueva empresa
    foreach ([6, 2, 5] as $mId) {
        $chkAt = $conn->query("SELECT empresa_perfil_id FROM conf__empresas_perfiles WHERE empresa_id = $nuevaEmpresaId AND modulo_id = $mId AND empresa_perfil_nombre = 'Acceso total' LIMIT 1");
        if (!$chkAt->fetch_assoc()) {
            $basePerfilRes = $conn->query("SELECT perfil_id FROM conf__perfiles WHERE modulo_id = $mId ORDER BY perfil_id ASC LIMIT 1");
            $basePId = $basePerfilRes->fetch_assoc()['perfil_id'] ?? 1;
            $conn->query("INSERT INTO conf__empresas_perfiles (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id)
                          VALUES ($nuevaEmpresaId, $mId, $basePId, 'Acceso total', 1)");
        }
    }

    // Asignar todas las funciones a todos los perfiles de la empresa en conf__empresas_perfiles_funciones
    $conn->query("INSERT IGNORE INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
                  SELECT ep.empresa_id, ep.empresa_perfil_id, pf.pagina_funcion_id, 1
                  FROM conf__empresas_perfiles ep
                  JOIN conf__paginas p ON p.modulo_id = ep.modulo_id AND p.tabla_estado_registro_id = 1
                  JOIN conf__paginas_funciones pf ON pf.pagina_id = p.pagina_id AND pf.tabla_estado_registro_id = 1
                  WHERE ep.empresa_id = $nuevaEmpresaId AND ep.tabla_estado_registro_id = 1");

    // Asignar los perfiles a los usuarios administradores (1: pablo, 11: jgili, 12: gblanco)
    $usuariosAdmin = [1, 11, 12];
    foreach ($usuariosAdmin as $uId) {
        $conn->query("INSERT IGNORE INTO conf__usuarios_perfiles (
            usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, usuario_creacion, tabla_estado_registro_id
        ) SELECT $uId, ep.empresa_perfil_id, '2026-01-01', '2050-12-31', 1, 1
          FROM conf__empresas_perfiles ep
          WHERE ep.empresa_id = $nuevaEmpresaId AND ep.tabla_estado_registro_id = 1");
    }
    echo "[+] Perfiles y accesos otorgados a usuarios Pablo (1), Jose Gili (11) y Gustavo Blanco (12).\n";

    // 11. Habilitar accesos a sucursales en gestion__perfiles_sucursales
    $conn->query("INSERT IGNORE INTO gestion__perfiles_sucursales (
        empresa_id, empresa_perfil_id, sucursal_id, tabla_estado_registro_id, fecha_creacion
    ) SELECT ep.empresa_id, ep.empresa_perfil_id, NULL, 1, NOW()
      FROM conf__empresas_perfiles ep
      WHERE ep.empresa_id = $nuevaEmpresaId");
    echo "[+] Accesos a sucursal y comprobantes asignados.\n";

    // 12. Sembrar Empleador y Nómina de Prueba para que esté lista para operar de inmediato
    // A. Entidad empleador en gestion__entidades
    $cuitTest = "30711223344";
    $chkEnt = $conn->query("SELECT entidad_id FROM gestion__entidades WHERE cuit = '$cuitTest' LIMIT 1");
    if ($entRow = $chkEnt->fetch_assoc()) {
        $entidadId = (int)$entRow['entidad_id'];
    } else {
        $conn->query("INSERT INTO gestion__entidades (
            empresa_id, entidad_nombre, entidad_fantasia, entidad_tipo_id, cuit,
            domicilio_legal, localidad_id, tabla_estado_registro_id
        ) VALUES (
            $nuevaEmpresaId, 'Distribuidora del Sur SA', 'Distribuidora del Sur', 2, '$cuitTest',
            'Donado 450, Bahía Blanca', 26985, 1
        )");
        $entidadId = (int)$conn->insert_id;
    }

    // B. Empleador en sindi__empleadores
    $chkEmp = $conn->query("SELECT empleador_id FROM sindi__empleadores WHERE empresa_id = $nuevaEmpresaId AND entidad_id = $entidadId LIMIT 1");
    if ($empRow = $chkEmp->fetch_assoc()) {
        $empleadorId = (int)$empRow['empleador_id'];
    } else {
        $conn->query("INSERT INTO sindi__empleadores (
            empresa_id, entidad_id, rubro, convenio_id, categoria_mora_id, f_alta_padron,
            origen, tabla_estado_registro_id, fecha_creacion
        ) VALUES (
            $nuevaEmpresaId, $entidadId, 'Comercio Mayorista y Minorista', $nuevoConvenioId, 1, '2026-01-01',
            'AUTOALTA', 1, NOW()
        )");
        $empleadorId = (int)$conn->insert_id;
    }

    // C. Trabajadores en sindi__personas y sindi__relaciones_laborales
    $personasTest = [
        [
            'cuil' => '20334455667', 'nombre' => 'Carlos', 'apellido' => 'González',
            'doc_nro' => '33445566', 'sexo' => 'M', 'sueldo' => 850000.00
        ],
        [
            'cuil' => '27356677882', 'nombre' => 'Ana', 'apellido' => 'Martínez',
            'doc_nro' => '35667788', 'sexo' => 'F', 'sueldo' => 920000.00
        ],
        [
            'cuil' => '20389900114', 'nombre' => 'Lucas', 'apellido' => 'Pérez',
            'doc_nro' => '38990011', 'sexo' => 'M', 'sueldo' => 780000.00
        ]
    ];

    foreach ($personasTest as $p) {
        $cuil = $p['cuil'];
        $chkP = $conn->query("SELECT persona_id FROM sindi__personas WHERE empresa_id = $nuevaEmpresaId AND cuil = '$cuil' LIMIT 1");
        if ($rowP = $chkP->fetch_assoc()) {
            $personaId = (int)$rowP['persona_id'];
        } else {
            $conn->query("INSERT INTO sindi__personas (
                empresa_id, cuil, apellido, nombre, documento_tipo_id, documento_nro,
                sexo, email, telefono, tabla_estado_registro_id, fecha_creacion
            ) VALUES (
                $nuevaEmpresaId, '$cuil', '{$p['apellido']}', '{$p['nombre']}', 1, '{$p['doc_nro']}',
                '{$p['sexo']}', '{$p['nombre']}.{$p['apellido']}@test.com', '0291-154000000', 1, NOW()
            )");
            $personaId = (int)$conn->insert_id;
        }

        // Relación laboral
        $conn->query("INSERT INTO sindi__relaciones_laborales (
            empresa_id, empleador_id, persona_id, f_alta,
            tabla_estado_registro_id, fecha_creacion
        ) VALUES (
            $nuevaEmpresaId, $empleadorId, $personaId, '2024-03-01',
            1, NOW()
        ) ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1");
    }
    echo "[+] Empleador de prueba 'Distribuidora del Sur SA' con 3 empleados activos incorporados al padrón.\n";

    // D. Abrir período actual y anterior en sindi__empleadores_periodos
    $conn->query("INSERT INTO sindi__empleadores_periodos (
        empresa_id, empleador_id, periodo, f_vto, situacion, tabla_estado_registro_id
    ) VALUES 
    ($nuevaEmpresaId, $empleadorId, 202609, '2026-10-15', 'PENDIENTE', 1),
    ($nuevaEmpresaId, $empleadorId, 202610, '2026-11-15', 'PENDIENTE', 1)
    ON DUPLICATE KEY UPDATE situacion = VALUES(situacion)");
    echo "[+] Períodos 202609 y 202610 abiertos en cuenta corriente del empleador.\n";

    $conn->commit();
    echo "\n====================================================================\n";
    echo "¡EMPRESA CONFIGURADA CON EXITO!\n";
    echo "Empresa ID:      $nuevaEmpresaId\n";
    echo "Razón Social:    $nombreEmpresa\n";
    echo "CUIT:            $cuitEmpresa\n";
    echo "Módulo:          6 - Sindicatos\n";
    echo "Empleador Demo:  Distribuidora del Sur SA (CUIT $cuitTest)\n";
    echo "====================================================================\n";

} catch (Throwable $e) {
    $conn->rollback();
    echo "\n[ERROR CRITICO]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
