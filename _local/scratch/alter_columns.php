<?php
require_once __DIR__ . '/../../db.php';

echo "Altering gestion__empresas_parametros...\n";
$chk1 = mysqli_query($conn, "SHOW COLUMNS FROM `gestion__empresas_parametros` LIKE 'iibb_regimen_id'");
if (mysqli_num_rows($chk1) == 0) {
    mysqli_query($conn, "ALTER TABLE `gestion__empresas_parametros` ADD COLUMN `iibb_regimen_id` TINYINT(3) UNSIGNED NULL DEFAULT 1 AFTER `ingresos_brutos_nro`");
    echo "Added iibb_regimen_id to gestion__empresas_parametros.\n";
} else {
    echo "iibb_regimen_id exists in gestion__empresas_parametros.\n";
}

$chk2 = mysqli_query($conn, "SHOW COLUMNS FROM `gestion__empresas_parametros` LIKE 'jurisdiccion_sede_id'");
if (mysqli_num_rows($chk2) == 0) {
    mysqli_query($conn, "ALTER TABLE `gestion__empresas_parametros` ADD COLUMN `jurisdiccion_sede_id` SMALLINT(5) UNSIGNED NULL DEFAULT 2 AFTER `iibb_regimen_id`");
    echo "Added jurisdiccion_sede_id to gestion__empresas_parametros.\n";
} else {
    echo "jurisdiccion_sede_id exists in gestion__empresas_parametros.\n";
}

echo "Altering conf__empresas...\n";
$chk3 = mysqli_query($conn, "SHOW COLUMNS FROM `conf__empresas` LIKE 'iibb_regimen_id'");
if (mysqli_num_rows($chk3) == 0) {
    mysqli_query($conn, "ALTER TABLE `conf__empresas` ADD COLUMN `iibb_regimen_id` TINYINT(3) UNSIGNED NULL DEFAULT 1 AFTER `condicion_fiscal_id`");
    echo "Added iibb_regimen_id to conf__empresas.\n";
} else {
    echo "iibb_regimen_id exists in conf__empresas.\n";
}

$chk4 = mysqli_query($conn, "SHOW COLUMNS FROM `conf__empresas` LIKE 'jurisdiccion_sede_id'");
if (mysqli_num_rows($chk4) == 0) {
    mysqli_query($conn, "ALTER TABLE `conf__empresas` ADD COLUMN `jurisdiccion_sede_id` SMALLINT(5) UNSIGNED NULL DEFAULT 2 AFTER `iibb_regimen_id`");
    echo "Added jurisdiccion_sede_id to conf__empresas.\n";
} else {
    echo "jurisdiccion_sede_id exists in conf__empresas.\n";
}

mysqli_query($conn, "UPDATE `gestion__empresas_parametros` SET `iibb_regimen_id` = 1 WHERE `iibb_regimen_id` IS NULL");
mysqli_query($conn, "UPDATE `gestion__empresas_parametros` SET `jurisdiccion_sede_id` = 2 WHERE `jurisdiccion_sede_id` IS NULL");
mysqli_query($conn, "UPDATE `conf__empresas` SET `iibb_regimen_id` = 1 WHERE `iibb_regimen_id` IS NULL");
mysqli_query($conn, "UPDATE `conf__empresas` SET `jurisdiccion_sede_id` = 2 WHERE `jurisdiccion_sede_id` IS NULL");

echo "Columns verified and updated successfully.\n";
