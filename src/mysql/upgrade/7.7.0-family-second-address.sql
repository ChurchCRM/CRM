-- ChurchCRM 7.7.0 — Optional second family address plus a "mailing address" flag
-- Feature #9743: a family can record a second address (PO box, winter address,
-- care-of address). When `fam_SecondIsMailing` is set that second address is
-- where mail goes; the primary address stays the physical location used for
-- maps, directions, geocoding and "find neighbours".
--
-- Every column is nullable or defaults to 0, so the upgrade is a no-op for
-- existing data: no family has a second address, the flag is off, and the
-- resolved mailing address is still the primary one. Rollback is a plain
-- `ALTER TABLE family_fam DROP COLUMN ...` for the seven columns.
--
-- Column sizes deliberately mirror the primary columns (`fam_Address1`/`2` are
-- varchar(255); city/state/zip/country are varchar(50)). The flag follows the
-- modern boolean convention in orm/schema.xml (tinyint(1) unsigned NOT NULL
-- DEFAULT 0) rather than the legacy enum('FALSE','TRUE') of fam_SendNewsLetter.

ALTER TABLE `family_fam`
  ADD COLUMN `fam_SecondAddress1`  varchar(255)        DEFAULT NULL AFTER `fam_Country`,
  ADD COLUMN `fam_SecondAddress2`  varchar(255)        DEFAULT NULL AFTER `fam_SecondAddress1`,
  ADD COLUMN `fam_SecondCity`      varchar(50)         DEFAULT NULL AFTER `fam_SecondAddress2`,
  ADD COLUMN `fam_SecondState`     varchar(50)         DEFAULT NULL AFTER `fam_SecondCity`,
  ADD COLUMN `fam_SecondZip`       varchar(50)         DEFAULT NULL AFTER `fam_SecondState`,
  ADD COLUMN `fam_SecondCountry`   varchar(50)         DEFAULT NULL AFTER `fam_SecondZip`,
  ADD COLUMN `fam_SecondIsMailing` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `fam_SecondCountry`;
