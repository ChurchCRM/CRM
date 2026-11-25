SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pledge_plg' AND COLUMN_NAME='plg_installment_amount') = 0,
    'ALTER TABLE pledge_plg ADD COLUMN plg_installment_amount DECIMAL(8,2) DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pledge_plg' AND COLUMN_NAME='plg_first_installment') = 0,
    'ALTER TABLE pledge_plg ADD COLUMN plg_first_installment DATE DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pledge_plg' AND COLUMN_NAME='plg_last_installment') = 0,
    'ALTER TABLE pledge_plg ADD COLUMN plg_last_installment DATE DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

