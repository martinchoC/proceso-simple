-- Permite importar extractos bancarios directamente con IA sin requerir un formato rígido (formato_id NULL = Lectura IA)
ALTER TABLE `gestion__bancos_importaciones`
    MODIFY COLUMN `formato_id` int(10) unsigned DEFAULT NULL COMMENT 'Formato usado (NULL = Lectura IA)';

