<?php
$cmd = 'C:\\xampp\\php\\php.exe -d auto_prepend_file=perf/prepend_profiler.php -d display_errors=1 perf/run_page.php --target=modules/conf/estados_registros_ajax.php --params="accion=listar"';
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];
$proc = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/../..');
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$code = proc_close($proc);

echo "CODE: $code\n";
echo "STDOUT:\n$stdout\n";
echo "STDERR:\n$stderr\n";
