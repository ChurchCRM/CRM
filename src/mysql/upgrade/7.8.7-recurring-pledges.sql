-- Re-issue of the recurring-pledges migration (originally 7.8.5-recurring-pledges.sql).
-- That file was registered in the already-shipped pre-7.8.5 upgrade step, so installs
-- whose database was already at 7.8.5 when they upgraded to 7.8.6 never ran it, and
-- the 7.8.6 family view then fails on the missing pledge_plg columns (#11874).
-- The upgrade runner walks every step in upgrade.json in one run, so registering this
-- copy in the current (7.8.6 -> 7.8.7) step heals those installs and also covers
-- installs jumping from 7.8.5 straight to 7.8.7. The information_schema guards make
-- it a no-op on installs that already have the columns.
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

