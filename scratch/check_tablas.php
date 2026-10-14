<?php
require_once __DIR__ . '/../db.php';
try {
    $res = $conn->query("DESCRIBE gestion__comprobantes");
    while ($r = $res->fetch_assoc()) {
        echo "{$r['Field']}\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
