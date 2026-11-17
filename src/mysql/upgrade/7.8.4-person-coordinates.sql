-- Stored coordinates for a person's own address (#10888): group and cart maps read these instead of geocoding on every load.
-- GeocodeTriedAt set with no coordinates means the address was tried and not found; NULL means never tried.
-- Each column is guarded so the file can run again after a partial run.

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='person_per' AND COLUMN_NAME='per_Latitude')=0,
    'ALTER TABLE `person_per` ADD COLUMN `per_Latitude` double DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='person_per' AND COLUMN_NAME='per_Longitude')=0,
    'ALTER TABLE `person_per` ADD COLUMN `per_Longitude` double DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='person_per' AND COLUMN_NAME='per_GeocodeTriedAt')=0,
    'ALTER TABLE `person_per` ADD COLUMN `per_GeocodeTriedAt` timestamp NULL DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='family_fam' AND COLUMN_NAME='fam_GeocodeTriedAt')=0,
    'ALTER TABLE `family_fam` ADD COLUMN `fam_GeocodeTriedAt` timestamp NULL DEFAULT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;
