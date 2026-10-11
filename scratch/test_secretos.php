<?php
require_once __DIR__ . '/../core/secretos.php';

$txt = 'MiClaveSecreta123!';
$c = secreto_cifrar($txt);
$d = secreto_descifrar($c);

echo "Original: $txt\n";
echo "Cifrado: $c\n";
echo "Descifrado: $d\n";
echo "Match: " . ($txt === $d ? "OK" : "FAIL") . "\n";
