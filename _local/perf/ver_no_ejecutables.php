<?php
require_once __DIR__ . '/../../db.php';
$r = mysqli_query($conn, 'SELECT modulo, archivo, motivo_no_ejecutada FROM perf_paginas WHERE ejecutable_dinamico = 0 ORDER BY modulo, archivo');
while($row = mysqli_fetch_assoc($r)) {
    printf("%-18s %-35s %s\n", $row['modulo'], $row['archivo'], $row['motivo_no_ejecutada']);
}
