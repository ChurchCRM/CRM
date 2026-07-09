-- 7.2.0: Drop cfg_id column and make cfg_name the primary key
-- cfg_name already has a UNIQUE constraint, so uniqueness is guaranteed.
-- No data loss: this is a purely structural change.

ALTER TABLE `config_cfg` DROP PRIMARY KEY;
ALTER TABLE `config_cfg` DROP INDEX `cfg_name`;
ALTER TABLE `config_cfg` ADD PRIMARY KEY (`cfg_name`);
ALTER TABLE `config_cfg` DROP COLUMN `cfg_id`;
