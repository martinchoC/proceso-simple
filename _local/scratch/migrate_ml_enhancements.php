<?php
/**
 * Migración:
 * 1. Agregar columnas sigla, icono, color_hex, imagen_ref a ml__obligaciones
 * 2. Crear tabla ml__ciclos_tareas_mensajes para hilos de conversación en tareas
 * 3. Sembrar siglas e iconos en obligaciones existentes
 */

require_once __DIR__ . '/../../db.php';

echo "=== MIGRACION ML: SIGLAS Y MENSAJES EN TAREAS ===\n\n";

// 1. Columnas en ml__obligaciones
$cols_to_add = [
    'sigla' => "VARCHAR(30) DEFAULT NULL AFTER codigo",
    'icono' => "VARCHAR(60) DEFAULT 'fas fa-balance-scale' AFTER sigla",
    'color_hex' => "VARCHAR(20) DEFAULT '#0d6efd' AFTER icono",
    'imagen_ref' => "VARCHAR(255) DEFAULT NULL AFTER color_hex"
];

foreach ($cols_to_add as $col => $def) {
    $chk = mysqli_query($conexion, "SHOW COLUMNS FROM ml__obligaciones LIKE '$col'");
    if (mysqli_num_rows($chk) === 0) {
        $sql = "ALTER TABLE ml__obligaciones ADD COLUMN $col $def";
        if (mysqli_query($conexion, $sql)) {
            echo "[OK] Columna $col agregada a ml__obligaciones.\n";
        } else {
            echo "[ERROR] Error al agregar $col: " . mysqli_error($conexion) . "\n";
        }
    } else {
        echo "[INFO] Columna $col ya existe en ml__obligaciones.\n";
    }
}

// 2. Crear tabla ml__ciclos_tareas_mensajes
$sql_table = "CREATE TABLE IF NOT EXISTS `ml__ciclos_tareas_mensajes` (
  `tarea_mensaje_id` int(11) NOT NULL AUTO_INCREMENT,
  `ciclo_tarea_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COMENTARIO',
  `empresa_id` smallint(5) unsigned NOT NULL,
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tarea_mensaje_id`),
  KEY `ix_tm_tarea` (`ciclo_tarea_id`),
  KEY `ix_tm_usuario` (`usuario_id`),
  CONSTRAINT `fk_tm_tarea` FOREIGN KEY (`ciclo_tarea_id`) REFERENCES `ml__ciclos_tareas` (`ciclo_tarea_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `conf__usuarios` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conexion, $sql_table)) {
    echo "[OK] Tabla ml__ciclos_tareas_mensajes lista.\n";
} else {
    echo "[ERROR] Error al crear ml__ciclos_tareas_mensajes: " . mysqli_error($conexion) . "\n";
}

// 3. Sembrar siglas, iconos y colores en las obligaciones
$obligaciones_meta = [
    'OBL-IVA-MENSUAL' => [
        'sigla' => 'IVA',
        'icono' => 'fas fa-file-invoice-dollar',
        'color_hex' => '#0284c7' // Cyan/Sky
    ],
    'OBL-SEN-785-ANUAL' => [
        'sigla' => 'SEN-785',
        'icono' => 'fas fa-oil-can',
        'color_hex' => '#ea580c' // Orange
    ],
    'OBL-MA-EVT-DERRAME' => [
        'sigla' => 'PLANACON',
        'icono' => 'fas fa-water',
        'color_hex' => '#dc2626' // Red
    ],
    'OBL-ISO14-ANUAL' => [
        'sigla' => 'ISO-14001',
        'icono' => 'fas fa-leaf',
        'color_hex' => '#16a34a' // Green
    ],
    'OBL-SOC-01' => [
        'sigla' => 'SOCIETARIO',
        'icono' => 'fas fa-landmark',
        'color_hex' => '#7c3aed' // Violet
    ],
    'OBL-HS-01' => [
        'sigla' => 'SEG-HIG',
        'icono' => 'fas fa-hard-hat',
        'color_hex' => '#d97706' // Amber
    ],
    'OBL-MA-01' => [
        'sigla' => 'RESIDUOS',
        'icono' => 'fas fa-recycle',
        'color_hex' => '#059669' // Emerald
    ]
];

foreach ($obligaciones_meta as $cod => $meta) {
    $sql_upd = "UPDATE ml__obligaciones 
                SET sigla = '{$meta['sigla']}', 
                    icono = '{$meta['icono']}', 
                    color_hex = '{$meta['color_hex']}' 
                WHERE codigo = '$cod'";
    mysqli_query($conexion, $sql_upd);
    echo "[OK] Actualizada sigla {$meta['sigla']} para $cod\n";
}

// 4. Sembrar mensajes de ejemplo en los hilos de conversación de tareas
$mensajes_ejemplo = [
    // Tarea 25: Pago VEP IVA
    [
        'ciclo_tarea_id' => 25,
        'usuario_id' => 2, // Daniela Passoni
        'mensaje' => 'Generé el VEP N° 88129031 por el saldo de $ 1.450.800 tras la presentación de la DDJJ. Lo dejé remitido para cancelación.',
        'creado_en' => '2026-10-20 18:50:00',
        'tipo' => 'COMENTARIO'
    ],
    [
        'ciclo_tarea_id' => 25,
        'usuario_id' => 3, // Martín Legal
        'mensaje' => 'Verifiqué que los créditos fiscales computados concilian exactamente con el subdiario de compras. Aprobado para Tesorería.',
        'creado_en' => '2026-10-20 19:15:00',
        'tipo' => 'COMENTARIO'
    ],
    [
        'ciclo_tarea_id' => 25,
        'usuario_id' => 1, // Pablo Passoni
        'mensaje' => 'Recibido en Tesorería. Fondos disponibles en Banco Galicia cuenta 1245-8. Programada la transferencia en Interbanking para mañana a primera hora.',
        'creado_en' => '2026-10-20 19:40:00',
        'tipo' => 'COMENTARIO'
    ],

    // Tarea 8: Medición de espesores TK-101
    [
        'ciclo_tarea_id' => 8,
        'usuario_id' => 7, // Eduardo Zamudio
        'mensaje' => 'Comenzamos el ensayo no destructivo con medidor ultrasonido en la virola 1 y 2. Detectamos corrosión superficial localizada en la chapa este.',
        'creado_en' => '2026-10-09 14:10:00',
        'tipo' => 'COMENTARIO'
    ],
    [
        'ciclo_tarea_id' => 8,
        'usuario_id' => 6, // Eduardo Archilla
        'mensaje' => 'Por favor profundizar las tomas en la zona de interfase líquido-vapor para certificar el espesor remanente según API 653.',
        'creado_en' => '2026-10-09 16:30:00',
        'tipo' => 'COMENTARIO'
    ],

    // Tarea 14: Despliegue de barreras PNA
    [
        'ciclo_tarea_id' => 14,
        'usuario_id' => 3, // Martín Legal
        'mensaje' => 'Prefectura Naval Argentina ya labró el acta de intervención en el buque. El derrame fue circunscripto en un 100% sin afectación de tomas de agua.',
        'creado_en' => '2026-10-23 11:30:00',
        'tipo' => 'COMENTARIO'
    ]
];

foreach ($mensajes_ejemplo as $m) {
    $chk_m = mysqli_query($conexion, "SELECT tarea_mensaje_id FROM ml__ciclos_tareas_mensajes WHERE ciclo_tarea_id = {$m['ciclo_tarea_id']} AND mensaje = '" . mysqli_real_escape_string($conexion, $m['mensaje']) . "' LIMIT 1");
    if (!mysqli_fetch_assoc($chk_m)) {
        $sql_ins_m = "INSERT INTO ml__ciclos_tareas_mensajes 
            (ciclo_tarea_id, usuario_id, mensaje, tipo, empresa_id, creado_en)
            VALUES ({$m['ciclo_tarea_id']}, {$m['usuario_id']}, '" . mysqli_real_escape_string($conexion, $m['mensaje']) . "', '{$m['tipo']}', 2, '{$m['creado_en']}')";
        mysqli_query($conexion, $sql_ins_m);
        echo "[OK] Mensaje de ejemplo sembrado en Tarea {$m['ciclo_tarea_id']}.\n";
    }
}

echo "\n=== MIGRACION FINALIZADA CON EXITO ===\n";
