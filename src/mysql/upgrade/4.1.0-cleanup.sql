/**
    autopayment_aut had a warning about removing data, now time to remove.
 */
DROP TABLE if exists autopayment_aut;

/*
 * Drop index where they are a primary key
 */

/* make drop index if exists procedure because the app still supports old mysql versions */
DROP PROCEDURE IF EXISTS DropIndexIfExists;
CREATE PROCEDURE DropIndexIfExists(IN tableName VARCHAR(255), IN indexName VARCHAR(255))
BEGIN
    DECLARE indexExists INT;

    -- Check if the index exists
SELECT COUNT(*)
INTO indexExists
FROM information_schema.statistics
WHERE table_name = tableName AND index_name = indexName;

-- Drop the index if it exists
IF indexExists > 0 THEN
        SET @dropIndexQuery = CONCAT('ALTER TABLE `', tableName, '` DROP INDEX `', indexName, '`');
PREPARE stmt FROM @dropIndexQuery;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
END IF;
END;

/* remove the indexes */
CALL DropIndexIfExists('canvassdata_can', 'can_ID');
CALL DropIndexIfExists('config_cfg', 'cfg_id');
CALL DropIndexIfExists('donateditem_di', 'di_ID');
CALL DropIndexIfExists('donationfund_fun', 'fun_ID');
CALL DropIndexIfExists('family_fam', 'fam_ID');
CALL DropIndexIfExists('fundraiser_fr', 'fr_ID');
CALL DropIndexIfExists('group_grp', 'grp_ID_2');
CALL DropIndexIfExists('group_grp', 'grp_ID');
CALL DropIndexIfExists('multibuy_mb', 'mb_ID');
CALL DropIndexIfExists('paddlenum_pn', 'pn_ID');
CALL DropIndexIfExists('person_per', 'per_ID');
CALL DropIndexIfExists('person2volunteeropp_p2vo', 'p2vo_ID');
CALL DropIndexIfExists('property_pro', 'pro_ID_2');
CALL DropIndexIfExists('property_pro', 'pro_ID');
CALL DropIndexIfExists('propertytype_prt', 'prt_ID_2');
CALL DropIndexIfExists('propertytype_prt', 'prt_ID');
CALL DropIndexIfExists('query_qry', 'qry_ID_2');
CALL DropIndexIfExists('query_qry', 'qry_ID');
CALL DropIndexIfExists('queryparameteroptions_qpo', 'qpo_ID');
CALL DropIndexIfExists('queryparameters_qrp', 'qrp_ID_2');
CALL DropIndexIfExists('queryparameters_qrp', 'qrp_ID');
CALL DropIndexIfExists('user_usr', 'usr_per_ID');
CALL DropIndexIfExists('volunteeropportunity_vol', 'vol_ID');
CALL DropIndexIfExists('kioskdevice_kdev', 'kdev_ID');

/* drop the procedure after usage */
DROP PROCEDURE IF EXISTS DropIndexIfExists;

/**
  Missing auto increment for the table
 */
ALTER TABLE `locations` CHANGE `location_id` `location_id` INT(11) NOT NULL AUTO_INCREMENT;
