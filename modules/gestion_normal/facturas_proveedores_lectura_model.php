<?php
// Carga de facturas de proveedores desde archivo: el código es único para los módulos 2 y 5
// (core/facturas_lectura/fp_lectura_model.php); acá solo se fija la página del módulo (conf__paginas 557).
if (!defined('FPL_PAGINA_IDX')) define('FPL_PAGINA_IDX', 557);
require_once __DIR__ . '/../../core/facturas_lectura/fp_lectura_model.php';
