<?php
require_once __DIR__ . '/../../db.php';
$res = mysqli_query($conn, "DESCRIBE estudio__vinculaciones");
while ($r = mysqli_fetch_assoc($res)) {
    echo sprintf("%-30s %-25s %s\n", $r['Field'], $r['Type'], $r['Null']);
}
