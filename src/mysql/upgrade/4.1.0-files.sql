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
