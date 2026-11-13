<?php
// Ejecuta un proceso de cron/ contra la COPIA de la base.
// Uso: SIM_CRON=<archivo en cron/> SIM_DB_COPIA=<base> php run_cron.php [--opciones del cron]
// El cron y la base van por variables de entorno porque getopt() deja de leer en el primer argumento
// que no es una opción: así las opciones (--empresa=7 ...) le llegan al cron tal cual.
$base = getenv('SIM_DB_COPIA') ?: 'gestion_multipyme_sim';
$cron = basename((string)getenv('SIM_CRON'));
if ($base === 'gestion_multipyme') {
    fwrite(STDERR, "run_cron.php: no se ejecuta contra la base real\n");
    exit(2);
}
if ($cron === '' || !is_file(__DIR__ . '/../../../cron/' . $cron)) {
    fwrite(STDERR, "run_cron.php: cron inexistente '{$cron}'\n");
    exit(2);
}
define('DB_NAME', $base);
set_error_handler(function ($no, $str) {
    return strpos($str, 'already defined') !== false;
});
require_once __DIR__ . '/../../../.env';
restore_error_handler();
require __DIR__ . '/../../../cron/' . $cron;
