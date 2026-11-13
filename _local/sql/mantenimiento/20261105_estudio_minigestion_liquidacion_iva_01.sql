-- Mini-gestión del estudio: liquidación mensual de IVA por cliente (clientes MANUAL; los de modo ERP liquidan en Posición de IVA)
-- Estados como en gestion__iva_posiciones: 5 = vigente, 38 = anulada (nada se borra; volver a liquidar crea otra fila).
CREATE TABLE IF NOT EXISTS `estudio__minigestion_liquidaciones_iva` (
  `liquidacion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL COMMENT 'Tenant del estudio',
  `cliente_id` bigint(20) unsigned NOT NULL,
  `periodo` int(10) unsigned NOT NULL COMMENT 'YYYYMM',
  `debito_fiscal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `credito_fiscal` decimal(16,2) NOT NULL DEFAULT 0.00,
  `percepciones_iva` decimal(16,2) NOT NULL DEFAULT 0.00,
  `retenciones_iva` decimal(16,2) NOT NULL DEFAULT 0.00 COMMENT 'Retenciones / pagos a cuenta informados al liquidar',
  `saldo_favor_anterior` decimal(16,2) NOT NULL DEFAULT 0.00,
  `saldo_a_pagar` decimal(16,2) NOT NULL DEFAULT 0.00,
  `saldo_a_favor` decimal(16,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `tabla_estado_registro_id` int(10) unsigned NOT NULL DEFAULT 5 COMMENT '5 vigente, 38 anulada',
  `created_at` datetime DEFAULT current_timestamp(),
  `anulada_at` datetime DEFAULT NULL,
  PRIMARY KEY (`liquidacion_id`),
  KEY `idx_cliente_periodo` (`empresa_id`,`cliente_id`,`periodo`,`tabla_estado_registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
