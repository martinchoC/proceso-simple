<?php
require_once __DIR__ . '/../../db.php';

echo "1. Creating gestion__iibb_regimenes...\n";
$sqlRegimenes = "CREATE TABLE IF NOT EXISTS `gestion__iibb_regimenes` (
  `iibb_regimen_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `iibb_regimen_codigo` varchar(30) NOT NULL,
  `iibb_regimen_nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden` tinyint(3) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`iibb_regimen_id`),
  UNIQUE KEY `uk_regimen_cod` (`iibb_regimen_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conn, $sqlRegimenes)) {
    die("Error creating gestion__iibb_regimenes: " . mysqli_error($conn) . "\n");
}

// Seed regimenes
$regimenes = [
    [1, 'LOCAL', 'Local / Simplificado', 'Inscripto únicamente en una jurisdicción (ej. ARBA en Bs. As., AGIP en CABA)', 1],
    [2, 'CONVENIO_MULTILATERAL', 'Convenio Multilateral', 'Desarrolla actividad en múltiples provincias (requiere número de inscripción interprovincial)', 2],
    [3, 'EXENTO', 'Exento / No alcanzado', 'Por tipo de actividad o estatus legal', 3]
];

foreach ($regimenes as $r) {
    $stmt = mysqli_prepare($conn, "INSERT INTO `gestion__iibb_regimenes` (`iibb_regimen_id`, `iibb_regimen_codigo`, `iibb_regimen_nombre`, `descripcion`, `orden`, `tabla_estado_registro_id`) 
        VALUES (?, ?, ?, ?, ?, 1) 
        ON DUPLICATE KEY UPDATE `iibb_regimen_nombre` = VALUES(`iibb_regimen_nombre`), `descripcion` = VALUES(`descripcion`), `orden` = VALUES(`orden`)");
    mysqli_stmt_bind_param($stmt, "isssi", $r[0], $r[1], $r[2], $r[3], $r[4]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
echo "gestion__iibb_regimenes seeded successfully.\n";

echo "2. Creating gestion__iibb_jurisdicciones_sedes...\n";
$sqlSedes = "CREATE TABLE IF NOT EXISTS `gestion__iibb_jurisdicciones_sedes` (
  `jurisdiccion_sede_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `codigo_comarb` varchar(5) NOT NULL,
  `jurisdiccion_sede_nombre` varchar(100) NOT NULL,
  `organismo` varchar(100) DEFAULT NULL,
  `provincia_id` int(10) unsigned DEFAULT NULL,
  `orden` smallint(5) unsigned DEFAULT 1,
  `tabla_estado_registro_id` smallint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`jurisdiccion_sede_id`),
  UNIQUE KEY `uk_cod_comarb` (`codigo_comarb`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conn, $sqlSedes)) {
    die("Error creating gestion__iibb_jurisdicciones_sedes: " . mysqli_error($conn) . "\n");
}

// 24 jurisdictions
$sedes = [
    [1, '901', 'Ciudad Autónoma de Buenos Aires', 'AGIP', 33, 1],
    [2, '902', 'Buenos Aires', 'ARBA', 10, 2],
    [3, '903', 'Catamarca', 'ARCA Catamarca', 11, 3],
    [4, '904', 'Córdoba', 'DGR Córdoba', 14, 4],
    [5, '905', 'Corrientes', 'DGR Corrientes', 15, 5],
    [6, '906', 'Chaco', 'ATP Chaco', 12, 6],
    [7, '907', 'Chubut', 'DGR Chubut', 13, 7],
    [8, '908', 'Entre Ríos', 'ATER Entre Ríos', 16, 8],
    [9, '909', 'Formosa', 'DGR Formosa', 17, 9],
    [10, '910', 'Jujuy', 'DPR Jujuy', 18, 10],
    [11, '911', 'La Pampa', 'DGR La Pampa', 19, 11],
    [12, '912', 'La Rioja', 'DGIP La Rioja', 20, 12],
    [13, '913', 'Mendoza', 'ATM Mendoza', 21, 13],
    [14, '914', 'Misiones', 'ATM Misiones', 22, 14],
    [15, '915', 'Neuquén', 'DPR Neuquén', 23, 15],
    [16, '916', 'Río Negro', 'Agencia de Recaudación RN', 24, 16],
    [17, '917', 'Salta', 'DGR Salta', 25, 17],
    [18, '918', 'San Juan', 'DGR San Juan', 26, 18],
    [19, '919', 'San Luis', 'DPI San Luis', 27, 19],
    [20, '920', 'Santa Cruz', 'ASIP Santa Cruz', 28, 20],
    [21, '921', 'Santa Fe', 'API Santa Fe', 29, 21],
    [22, '922', 'Santiago del Estero', 'DGR Santiago del Estero', 30, 22],
    [23, '923', 'Tucumán', 'DGR Tucumán', 32, 23],
    [24, '924', 'Tierra del Fuego', 'AREF Tierra del Fuego', 31, 24]
];

foreach ($sedes as $s) {
    $stmt = mysqli_prepare($conn, "INSERT INTO `gestion__iibb_jurisdicciones_sedes` (`jurisdiccion_sede_id`, `codigo_comarb`, `jurisdiccion_sede_nombre`, `organismo`, `provincia_id`, `orden`, `tabla_estado_registro_id`)
        VALUES (?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE `jurisdiccion_sede_nombre` = VALUES(`jurisdiccion_sede_nombre`), `organismo` = VALUES(`organismo`), `provincia_id` = VALUES(`provincia_id`), `orden` = VALUES(`orden`)");
    mysqli_stmt_bind_param($stmt, "isssii", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
echo "gestion__iibb_jurisdicciones_sedes seeded successfully.\n";

echo "3. Altering gestion__empresas_parametros...\n";
$chk1 = mysqli_query($conn, "SHOW COLUMNS FROM `gestion__empresas_parametros` LIKE 'iibb_regimen_id'");
if (mysqli_num_rows($chk1) == 0) {
    mysqli_query($conn, "ALTER TABLE `gestion__empresas_parametros` ADD COLUMN `iibb_regimen_id` TINYINT(3) UNSIGNED NULL DEFAULT 1 AFTER `ingresos_brutos_nro`");
    echo "Added iibb_regimen_id to gestion__empresas_parametros.\n";
} else {
    echo "iibb_regimen_id already exists in gestion__empresas_parametros.\n";
}

$chk2 = mysqli_query($conn, "SHOW COLUMNS FROM `gestion__empresas_parametros` LIKE 'jurisdiccion_sede_id'");
if (mysqli_num_rows($chk2) == 0) {
    mysqli_query($conn, "ALTER TABLE `gestion__empresas_parametros` ADD COLUMN `jurisdiccion_sede_id` SMALLINT(5) UNSIGNED NULL DEFAULT 2 AFTER `iibb_regimen_id`");
    echo "Added jurisdiccion_sede_id to gestion__empresas_parametros.\n";
} else {
    echo "jurisdiccion_sede_id already exists in gestion__empresas_parametros.\n";
}

echo "4. Altering conf__empresas...\n";
$chk3 = mysqli_query($conn, "SHOW COLUMNS FROM `conf__empresas` LIKE 'iibb_regimen_id'");
if (mysqli_num_rows($chk3) == 0) {
    mysqli_query($conn, "ALTER TABLE `conf__empresas` ADD COLUMN `iibb_regimen_id` TINYINT(3) UNSIGNED NULL DEFAULT 1 AFTER `condicion_fiscal_id`");
    echo "Added iibb_regimen_id to conf__empresas.\n";
} else {
    echo "iibb_regimen_id already exists in conf__empresas.\n";
}

$chk4 = mysqli_query($conn, "SHOW COLUMNS FROM `conf__empresas` LIKE 'jurisdiccion_sede_id'");
if (mysqli_num_rows($chk4) == 0) {
    mysqli_query($conn, "ALTER TABLE `conf__empresas` ADD COLUMN `jurisdiccion_sede_id` SMALLINT(5) UNSIGNED NULL DEFAULT 2 AFTER `iibb_regimen_id`");
    echo "Added jurisdiccion_sede_id to conf__empresas.\n";
} else {
    echo "jurisdiccion_sede_id already exists in conf__empresas.\n";
}

// Update existing active rows with default sede 902 (Buenos Aires) if null
mysqli_query($conn, "UPDATE `gestion__empresas_parametros` SET `iibb_regimen_id` = 1 WHERE `iibb_regimen_id` IS NULL");
mysqli_query($conn, "UPDATE `gestion__empresas_parametros` SET `jurisdiccion_sede_id` = 2 WHERE `jurisdiccion_sede_id` IS NULL");
mysqli_query($conn, "UPDATE `conf__empresas` SET `iibb_regimen_id` = 1 WHERE `iibb_regimen_id` IS NULL");
mysqli_query($conn, "UPDATE `conf__empresas` SET `jurisdiccion_sede_id` = 2 WHERE `jurisdiccion_sede_id` IS NULL");

echo "Migration completed successfully!\n";
