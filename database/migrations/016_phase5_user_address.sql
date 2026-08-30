-- =====================================================
-- Phase 5 follow-up: address fields on users
-- All optional. Idempotent — re-running is harmless.
-- =====================================================

SET @schema = DATABASE();

-- Helper: only ALTER if the column doesn't yet exist.
DROP PROCEDURE IF EXISTS add_user_addr_col;
DELIMITER //
CREATE PROCEDURE add_user_addr_col(IN col_name VARCHAR(64), IN col_def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = col_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE users ADD COLUMN ', col_name, ' ', col_def);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL add_user_addr_col('address_line1', 'VARCHAR(150) DEFAULT NULL AFTER phone');
CALL add_user_addr_col('address_line2', 'VARCHAR(150) DEFAULT NULL AFTER address_line1');
CALL add_user_addr_col('city',          'VARCHAR(100) DEFAULT NULL AFTER address_line2');
CALL add_user_addr_col('state',         'VARCHAR(100) DEFAULT NULL AFTER city');
CALL add_user_addr_col('postcode',      'VARCHAR(20)  DEFAULT NULL AFTER state');
CALL add_user_addr_col('country',       'VARCHAR(80)  DEFAULT NULL AFTER postcode');

DROP PROCEDURE IF EXISTS add_user_addr_col;
