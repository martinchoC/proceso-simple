<?php
$res = @file_get_contents('http://localhost/casalucho/login.php');
if ($res !== false) {
    echo "HTTP OK, length: " . strlen($res) . "\n";
} else {
    echo "HTTP FAIL\n";
}
