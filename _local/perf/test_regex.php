<?php
$c = file_get_contents(__DIR__ . '/../../modules/gestion/clientes.php');
if (preg_match('/mail\(|sendgrid|phpmailer/i', $c, $matches)) {
    echo "Matched: " . print_r($matches, true);
} else {
    echo "No match";
}
