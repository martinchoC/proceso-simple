<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "1. db.php...\n";
require_once __DIR__ . '/../../db.php';

echo "2. sindi_fiscalizacion_core_model.php...\n";
require_once __DIR__ . '/../../modules/sindicatos/sindi_fiscalizacion_core_model.php';

echo "3. sindi_actas_core_model.php...\n";
require_once __DIR__ . '/../../modules/sindicatos/sindi_actas_core_model.php';

echo "4. sindi_planes_core_model.php...\n";
require_once __DIR__ . '/../../modules/sindicatos/sindi_planes_core_model.php';

echo "5. sindi_ddjj_core_model.php...\n";
require_once __DIR__ . '/../../modules/sindicatos/sindi_ddjj_core_model.php';

echo "Todos los requires cargaron OK!\n";
