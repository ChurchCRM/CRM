-- ---------------------------------------------------------------------
-- files
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `files`;

CREATE TABLE `files`
(
    `file_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    `file_sha256` VARCHAR(64) NOT NULL,
    `file_name` VARCHAR(255),
    `file_created` DATETIME,
    `file_modified` DATETIME,
    PRIMARY KEY (`file_id`)
) ENGINE=InnoDB COMMENT='Table containing metadata for ChurchCRM File attachments';
# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
-- ---------------------------------------------------------------------
-- file_associations
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `file_associations`;

CREATE TABLE `file_associations`
(
    `file_id` mediumint(8) unsigned NOT NULL,
    `person_id` mediumint(8) unsigned NOT NULL,
    PRIMARY KEY (`file_id`,`person_id`),
    INDEX `file_associations_fi_bd0b42` (`person_id`),
    CONSTRAINT `file_associations_fk_f03653`
        FOREIGN KEY (`file_id`)
        REFERENCES `files` (`file_id`),
    CONSTRAINT `file_associations_fk_bd0b42`
        FOREIGN KEY (`person_id`)
        REFERENCES `person_per` (`per_ID`)
) ENGINE=InnoDB COMMENT='This is a join-table to link files with other types';