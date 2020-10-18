/**
    autopayment_aut had a warning about removing data, now time to remove.
 */
DROP TABLE if exists autopayment_aut;

/*
    Drop index where they are a primary key
 */
ALTER TABLE `canvassdata_can`  DROP INDEX `can_ID`;
ALTER TABLE `config_cfg`       DROP INDEX `cfg_id`;
ALTER TABLE `donateditem_di`   DROP INDEX `di_ID`;
ALTER TABLE `donationfund_fun` DROP INDEX `fun_ID`;
ALTER TABLE `family_fam`       DROP INDEX `fam_ID`;
ALTER TABLE `fundraiser_fr`    DROP INDEX `fr_ID`;
ALTER TABLE `group_grp`        DROP INDEX `grp_ID_2`, DROP INDEX `grp_ID`;
ALTER TABLE `multibuy_mb`      DROP INDEX `mb_ID`;
ALTER TABLE `paddlenum_pn`     DROP INDEX `pn_ID`;
ALTER TABLE `person_per`       DROP INDEX `per_ID`;
ALTER TABLE `person2volunteeropp_p2vo` DROP INDEX `p2vo_ID`;
ALTER TABLE `property_pro`     DROP INDEX `pro_ID_2`, DROP INDEX `pro_ID`;
ALTER TABLE `propertytype_prt` DROP INDEX `prt_ID_2`, DROP INDEX `prt_ID`;
ALTER TABLE `query_qry`        DROP INDEX `qry_ID_2`, DROP INDEX `qry_ID`;
ALTER TABLE `queryparameteroptions_qpo` DROP INDEX `qpo_ID`;
ALTER TABLE `queryparameters_qrp` DROP INDEX `qrp_ID_2`, DROP INDEX `qrp_ID`;
ALTER TABLE `user_usr`         DROP INDEX `usr_per_ID`;
ALTER TABLE `volunteeropportunity_vol` DROP INDEX `vol_ID`;
ALTER TABLE `kioskdevice_kdev` DROP INDEX `kdev_ID`;

/**
  Missing auto increment for the table
 */
ALTER TABLE `locations` CHANGE `location_id` `location_id` INT(11) NOT NULL AUTO_INCREMENT;
