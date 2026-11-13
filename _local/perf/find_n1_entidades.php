<?php
$lines = file(__DIR__ . '/../../modules/gestion/entidades_model.php');
$code = file_get_contents(__DIR__ . '/../../modules/gestion/entidades_model.php');
$tokens = token_get_all($code);

$loopDepth = 0;
$braceDepth = 0;
$loopBraceDepths = [];

foreach ($tokens as $t) {
    if (is_array($t)) {
        $id = $t[0];
        $text = $t[1];
        $line = $t[2];
        
        if ($id === T_WHILE || $id === T_FOR || $id === T_FOREACH || $id === T_DO) {
            $loopDepth++;
            $loopBraceDepths[] = $braceDepth;
        }
        
        if ($id === T_STRING && in_array(strtolower($text), ['mysqli_query', 'mysqli_prepare', 'mysqli_stmt_execute'])) {
            if ($loopDepth > 0) {
                echo "Línea $line: $text en bucle (nivel $loopDepth)\n";
                $start = max(0, $line - 3);
                $end = min(count($lines), $line + 2);
                for ($k = $start; $k < $end; $k++) {
                    echo "  " . ($k + 1) . ": " . trim($lines[$k]) . "\n";
                }
                echo "---\n";
            }
        }
    } else {
        if ($t === '{') {
            $braceDepth++;
        } elseif ($t === '}') {
            $braceDepth--;
            if (!empty($loopBraceDepths) && end($loopBraceDepths) >= $braceDepth) {
                array_pop($loopBraceDepths);
                $loopDepth = max(0, $loopDepth - 1);
            }
        }
    }
}
