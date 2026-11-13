import subprocess

script = """<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT * FROM conf__paginas WHERE pagina_id IN (87, 88, 89, 91, 105)");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
"""

with open('scratch/temp_paginas.php', 'w', encoding='utf-8') as f:
    f.write(script)

res = subprocess.run(['c:\\xampp\\php\\php.exe', 'scratch/temp_paginas.php'], capture_output=True, text=True)
print(res.stdout)
