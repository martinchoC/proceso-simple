-- Esquema para métricas de performance
CREATE TABLE IF NOT EXISTS `perf_corridas` (
    `corrida_id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATETIME NOT NULL,
    `entorno` VARCHAR(50) NOT NULL DEFAULT 'desarrollo',
    `version_codigo` VARCHAR(50) DEFAULT NULL,
    `observaciones` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `perf_paginas` (
    `pagina_id` INT AUTO_INCREMENT PRIMARY KEY,
    `modulo` VARCHAR(100) NOT NULL,
    `archivo` VARCHAR(150) NOT NULL,
    `ruta` VARCHAR(255) NOT NULL,
    `ejecutable_dinamico` TINYINT(1) NOT NULL DEFAULT 1,
    `motivo_no_ejecutada` VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY `uk_modulo_archivo` (`modulo`, `archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `perf_estatico` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `corrida_id` INT NOT NULL,
    `pagina_id` INT NOT NULL,
    `kb_archivo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `lineas` INT NOT NULL DEFAULT 0,
    `kb_includes` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cant_consultas` INT NOT NULL DEFAULT 0,
    `consultas_en_bucle` INT NOT NULL DEFAULT 0,
    `usa_select_star` TINYINT(1) NOT NULL DEFAULT 0,
    `sin_limit` TINYINT(1) NOT NULL DEFAULT 0,
    `cant_joins` INT NOT NULL DEFAULT 0,
    `carga_js_css_kb` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `observaciones` TEXT DEFAULT NULL,
    KEY `idx_corrida` (`corrida_id`),
    KEY `idx_pagina` (`pagina_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `perf_dinamico` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `corrida_id` INT NOT NULL,
    `pagina_id` INT NOT NULL,
    `escenario` VARCHAR(100) NOT NULL DEFAULT 'default',
    `ms_promedio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `ms_max` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `kb_html` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `kb_html_gzip` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `memoria_pico_mb` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cant_consultas` INT NOT NULL DEFAULT 0,
    `ms_mysql` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `filas_max` INT NOT NULL DEFAULT 0,
    KEY `idx_corrida` (`corrida_id`),
    KEY `idx_pagina` (`pagina_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `perf_consultas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `corrida_id` INT NOT NULL,
    `pagina_id` INT NOT NULL,
    `sql_normalizado` TEXT NOT NULL,
    `veces_ejecutada` INT NOT NULL DEFAULT 1,
    `ms_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `filas` INT NOT NULL DEFAULT 0,
    `usa_indice` VARCHAR(100) DEFAULT NULL,
    `indice_sugerido` TEXT DEFAULT NULL,
    KEY `idx_corrida` (`corrida_id`),
    KEY `idx_pagina` (`pagina_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `perf_resumen_modulo` (
    `corrida_id` INT NOT NULL,
    `modulo` VARCHAR(100) NOT NULL,
    `cant_paginas` INT NOT NULL DEFAULT 0,
    `ms_promedio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `kb_html_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `consultas_total` INT NOT NULL DEFAULT 0,
    `score_modulo` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`corrida_id`, `modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
