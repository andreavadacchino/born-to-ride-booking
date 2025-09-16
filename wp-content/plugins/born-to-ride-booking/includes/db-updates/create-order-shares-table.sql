-- Born to Ride Booking - Order Shares Table
-- Version: 1.0.0
-- Description: Tabella per gestire quote di pagamento individuali per ordini di gruppo

CREATE TABLE IF NOT EXISTS `{prefix}btr_order_shares` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID ordine WooCommerce',
    `participant_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID partecipante dal preventivo',
    `participant_name` varchar(255) NOT NULL COMMENT 'Nome completo partecipante',
    `participant_email` varchar(255) NOT NULL COMMENT 'Email per invio link pagamento',
    `participant_phone` varchar(50) DEFAULT NULL COMMENT 'Telefono opzionale',
    `amount_assigned` decimal(10,2) NOT NULL COMMENT 'Importo assegnato da pagare',
    `amount_paid` decimal(10,2) DEFAULT 0.00 COMMENT 'Importo già pagato',
    `currency` varchar(3) DEFAULT 'EUR' COMMENT 'Valuta pagamento',
    `payment_method` varchar(50) DEFAULT NULL COMMENT 'Metodo pagamento utilizzato',
    `payment_status` enum('pending','processing','paid','failed','expired','cancelled','refunded') DEFAULT 'pending' COMMENT 'Stato pagamento',
    `payment_link` varchar(500) DEFAULT NULL COMMENT 'Link pagamento individuale',
    `payment_token` varchar(64) DEFAULT NULL COMMENT 'Token sicuro per validazione',
    `token_expires_at` datetime DEFAULT NULL COMMENT 'Scadenza token sicurezza',
    `transaction_id` varchar(255) DEFAULT NULL COMMENT 'ID transazione gateway',
    `paid_at` datetime DEFAULT NULL COMMENT 'Data/ora pagamento completato',
    `failed_at` datetime DEFAULT NULL COMMENT 'Data/ora ultimo fallimento',
    `failure_reason` text DEFAULT NULL COMMENT 'Motivo fallimento pagamento',
    `reminder_sent_at` datetime DEFAULT NULL COMMENT 'Data/ora ultimo reminder',
    `reminder_count` int(11) DEFAULT 0 COMMENT 'Numero reminder inviati',
    `next_reminder_at` datetime DEFAULT NULL COMMENT 'Data/ora prossimo reminder',
    `notes` text DEFAULT NULL COMMENT 'Note interne admin',
    `metadata` longtext DEFAULT NULL COMMENT 'Dati aggiuntivi JSON',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Data creazione record',
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Ultimo aggiornamento',
    `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete timestamp',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_payment_token` (`payment_token`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_participant_email` (`participant_email`),
    KEY `idx_payment_status` (`payment_status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_deleted_at` (`deleted_at`),
    KEY `idx_composite_order_status` (`order_id`, `payment_status`, `deleted_at`),
    KEY `idx_reminder_schedule` (`next_reminder_at`, `payment_status`),
    CONSTRAINT `fk_order_shares_order` FOREIGN KEY (`order_id`) 
        REFERENCES `{prefix}posts` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quote pagamento per ordini di gruppo Born to Ride';

-- Indici per performance queries comuni
-- idx_composite_order_status: Per query "tutte le quote di un ordine con stato X"
-- idx_reminder_schedule: Per cron job che cerca quote da sollecitare

-- Trigger per gestione updated_at (se MySQL < 5.6)
-- DROP TRIGGER IF EXISTS `btr_order_shares_updated_at`;
-- DELIMITER $$
-- CREATE TRIGGER `btr_order_shares_updated_at` 
-- BEFORE UPDATE ON `{prefix}btr_order_shares` 
-- FOR EACH ROW
-- BEGIN
--     SET NEW.updated_at = CURRENT_TIMESTAMP;
-- END$$
-- DELIMITER ;