-- =====================================================================================================
-- Asistente IA (2026-11-04): página 139 (gestion) / 639 (gestion_normal), menú Ventas
-- -----------------------------------------------------------------------------------------------------
-- asistente_ia.php: chat en el que el usuario pide en lenguaje natural "factura B a Ferretería López con
-- 10 tornillos 8mm", "remito a ..." o "dar de alta este cliente" (texto o la constancia de inscripción de ARCA
-- en PDF / imagen). La IA consulta clientes, productos, precios y puntos de venta con herramientas propias y
-- arma una PROPUESTA; nada se graba hasta que el usuario la confirma en pantalla, y entonces se graba con las
-- funciones de siempre (guardarVentaFactura, agregarRemitoVenta, alta de entidad), en Borrador.
-- Diseño: _local/docs/gestion/14_asistente_ia.md. Código: core/asistente_ia/.
--
--   1. Columnas ia_asistente_habilitado / ia_asistente_tope_diario en gestion__empresas_parametros
--      (deshabilitado por defecto; la clave de la API es la misma de la lectura de facturas).
--   2. Tablas gestion__ia_asistente_conversaciones / _mensajes / _propuestas.
--   3. Página 139 / 639 (sin tabla de proceso: tabla_id 0), función 3527 / 8527 "Usar Asistente IA"
--      (consultar, 0 -> 0).
--   4. Permisos: los perfiles que pueden agregar en Facturación, Remitos, Clientes o Proveedores
--      (funciones 223, 343, 414, 419 / 5223, 5343, 5414, 5419). Grabar exige además, en cada propuesta,
--      la función "agregar" de la página de destino.
-- Reanudable. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;

-- 1. Parámetros de empresa ------------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_parametros' AND COLUMN_NAME = 'ia_asistente_habilitado');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__empresas_parametros ADD COLUMN ia_asistente_habilitado TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Asistente IA (página 139 / 639): 1 = se puede usar'' AFTER ia_lectura_tope_diario',
    'SELECT ''gestion__empresas_parametros.ia_asistente_habilitado ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__empresas_parametros' AND COLUMN_NAME = 'ia_asistente_tope_diario');
SET @sql := IF(@existe = 0,
    'ALTER TABLE gestion__empresas_parametros ADD COLUMN ia_asistente_tope_diario SMALLINT UNSIGNED NOT NULL DEFAULT 50 COMMENT ''Pedidos al Asistente IA por día para la empresa'' AFTER ia_asistente_habilitado',
    'SELECT ''gestion__empresas_parametros.ia_asistente_tope_diario ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Tablas -----------------------------------------------------------------------------------------------
-- historial: la conversación en el formato del proveedor de IA (Claude o Gemini), tal cual se le reenvía en cada
-- pedido (incluye los bloques de razonamiento, que hay que devolver sin tocar). No se muestra.
CREATE TABLE IF NOT EXISTS gestion__ia_asistente_conversaciones (
    conversacion_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id        INT UNSIGNED NOT NULL,
    usuario_id        INT UNSIGNED NOT NULL,
    modulo_id         SMALLINT UNSIGNED NOT NULL,
    titulo            VARCHAR(200) NOT NULL DEFAULT '',
    proveedor         VARCHAR(20) NOT NULL DEFAULT '',
    modelo            VARCHAR(80) NOT NULL DEFAULT '',
    historial         LONGTEXT NULL,
    tokens_entrada    INT UNSIGNED NOT NULL DEFAULT 0,
    tokens_salida     INT UNSIGNED NOT NULL DEFAULT 0,
    costo             DECIMAL(12,6) NOT NULL DEFAULT 0,
    fecha_alta        DATETIME NOT NULL,
    fecha_actualizada DATETIME NOT NULL,
    tabla_estado_registro_id SMALLINT NOT NULL DEFAULT 1,
    PRIMARY KEY (conversacion_id),
    KEY idx_ia_conv_usuario (empresa_id, usuario_id, fecha_actualizada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lo que se ve en pantalla y el registro de auditoría: cada pedido del usuario, cada respuesta y cada herramienta
-- que usó la IA. El tope diario cuenta los mensajes USUARIO de la empresa en el día.
CREATE TABLE IF NOT EXISTS gestion__ia_asistente_mensajes (
    mensaje_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversacion_id  INT UNSIGNED NOT NULL,
    empresa_id       INT UNSIGNED NOT NULL,
    rol              VARCHAR(12) NOT NULL COMMENT 'USUARIO, ASISTENTE, HERRAMIENTA, ERROR',
    texto            MEDIUMTEXT NULL,
    herramienta      VARCHAR(60) NULL,
    datos            MEDIUMTEXT NULL COMMENT 'Entrada y resultado de la herramienta (JSON)',
    archivo_nombre   VARCHAR(255) NULL,
    archivo_ruta     VARCHAR(255) NULL COMMENT 'Relativa a uploads/asistente_ia/',
    propuesta_id     INT UNSIGNED NULL,
    fecha            DATETIME NOT NULL,
    PRIMARY KEY (mensaje_id),
    KEY idx_ia_msg_conv (conversacion_id, mensaje_id),
    KEY idx_ia_msg_empresa_fecha (empresa_id, rol, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lo que la IA propone grabar. datos = lo validado por el servidor (ids, cantidades, precios); al confirmar se
-- vuelve a validar todo y se graba con el usuario y los permisos de quien confirma.
CREATE TABLE IF NOT EXISTS gestion__ia_asistente_propuestas (
    propuesta_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversacion_id  INT UNSIGNED NOT NULL,
    empresa_id       INT UNSIGNED NOT NULL,
    usuario_id       INT UNSIGNED NOT NULL,
    tipo             VARCHAR(20) NOT NULL COMMENT 'FACTURA_VENTA, REMITO_VENTA, ALTA_ENTIDAD',
    datos            MEDIUMTEXT NOT NULL,
    estado           VARCHAR(12) NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, CONFIRMADA, DESCARTADA, ERROR',
    registro_id      INT UNSIGNED NULL COMMENT 'venta_factura_id / venta_remito_id / entidad_id creado',
    mensaje          VARCHAR(500) NULL,
    fecha_alta       DATETIME NOT NULL,
    fecha_resolucion DATETIME NULL,
    usuario_resolucion INT UNSIGNED NULL,
    PRIMARY KEY (propuesta_id),
    KEY idx_ia_prop_conv (conversacion_id),
    KEY idx_ia_prop_empresa (empresa_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Página y función --------------------------------------------------------------------------------------
START TRANSACTION;

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 139, 2, 'Asistente IA', 'asistente_ia.php', 21,
       'Pedidos en lenguaje natural: facturas, remitos y altas de clientes y proveedores (también desde la constancia de ARCA)',
       48, 0, 0, 0, 1, 1, 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 139)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 48);

INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
                           requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT 639, 5, 'Asistente IA', 'asistente_ia.php', 21,
       'Pedidos en lenguaje natural: facturas, remitos y altas de clientes y proveedores (también desde la constancia de ARCA)',
       548, 0, 0, 0, 1, 1, 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 639)
  AND EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 548);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 3527, 139, 21, 1, 1, 'Usar Asistente IA', 'consultar', '', 0, 0, 1, 1 FROM DUAL
WHERE EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 139)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 3527);

INSERT INTO conf__paginas_funciones (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion,
                                     tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT 8527, 639, 21, 1, 1, 'Usar Asistente IA', 'consultar', '', 0, 0, 1, 1 FROM DUAL
WHERE EXISTS (SELECT 1 FROM conf__paginas WHERE pagina_id = 639)
  AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones WHERE pagina_funcion_id = 8527);

-- 4. Permisos: perfiles que agregan en Facturación, Remitos, Clientes o Proveedores ------------------------
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT pfa.perfil_id, IF(pfa.pagina_funcion_id < 5000, 3527, 8527), 1
FROM conf__perfiles_funciones pfa
WHERE pfa.pagina_funcion_id IN (223, 343, 414, 419, 5223, 5343, 5414, 5419) AND pfa.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = IF(pfa.pagina_funcion_id < 5000, 3527, 8527))
  AND NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x
                  WHERE x.perfil_id = pfa.perfil_id AND x.pagina_funcion_id = IF(pfa.pagina_funcion_id < 5000, 3527, 8527));

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT ep.empresa_id, ep.empresa_perfil_id, IF(epa.pagina_funcion_id < 5000, 3527, 8527), 1
FROM conf__empresas_perfiles_funciones epa
JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epa.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
WHERE epa.pagina_funcion_id IN (223, 343, 414, 419, 5223, 5343, 5414, 5419) AND epa.asignado = 1
  AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_funcion_id = IF(epa.pagina_funcion_id < 5000, 3527, 8527))
  AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = ep.empresa_perfil_id
                    AND x.pagina_funcion_id = IF(epa.pagina_funcion_id < 5000, 3527, 8527));

COMMIT;

-- Control (solo lectura)
SELECT p.pagina_id, p.pagina, p.padre_id, f.pagina_funcion_id,
       (SELECT COUNT(*) FROM conf__perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_base,
       (SELECT COUNT(*) FROM conf__empresas_perfiles_funciones x WHERE x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1) AS perfiles_empresa
FROM conf__paginas p
LEFT JOIN conf__paginas_funciones f ON f.pagina_id = p.pagina_id
WHERE p.pagina_id IN (139, 639);
