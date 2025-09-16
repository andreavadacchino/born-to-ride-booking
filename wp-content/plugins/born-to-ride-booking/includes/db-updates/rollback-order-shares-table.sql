-- Born to Ride Booking - Rollback Order Shares Table
-- Version: 1.0.0
-- Description: Script di rollback per rimuovere la tabella order_shares

-- Backup data before dropping (optional - uncomment if needed)
-- CREATE TABLE IF NOT EXISTS `{prefix}btr_order_shares_backup_YYYYMMDD` AS 
-- SELECT * FROM `{prefix}btr_order_shares`;

-- Remove foreign key constraint first
ALTER TABLE `{prefix}btr_order_shares` 
DROP FOREIGN KEY IF EXISTS `fk_order_shares_order`;

-- Drop the table
DROP TABLE IF EXISTS `{prefix}btr_order_shares`;

-- Remove version tracking from options
-- This should be handled by PHP code, not SQL
-- DELETE FROM `{prefix}options` WHERE `option_name` = 'btr_order_shares_db_version';