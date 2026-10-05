<?php
require_once __DIR__ . '/../db.php';
try {
    $r_chk = $conn->query("SELECT pagina_id FROM conf__paginas WHERE pagina_id = 107 OR url = 'cheques_cartera.php'");
    if ($r_chk->num_rows == 0) {
        $conn->query("INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, pagina_descripcion, padre_id, orden, tabla_id, tabla_estado_registro_id)
                      VALUES (107, 2, 'Cartera de Cheques', 'cheques_cartera.php', 'Administración de Cartera de Cheques de Terceros y Cheques Propios', 100, 30, 288, 1)");
        echo "Página 107 (Cartera de Cheques) registrada con éxito.\n";
    } else {
        echo "Página 107 ya existe.\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
