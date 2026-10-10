-- ChurchCRM 7.8.0 consolidated upgrade script
-- Merged from individual scripts on 2026-10-10
-- Order preserved from upgrade.json current block


-- ============================================================================
-- BEGIN: 7.8.0-family-second-address.sql
-- ============================================================================

-- ChurchCRM 7.8.0 — Optional second family address plus a "mailing address" flag
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

-- END: 7.8.0-family-second-address.sql


-- ============================================================================
-- BEGIN: 7.8.0-fund-category.sql
-- ============================================================================

-- ChurchCRM 7.8.0 Donation Fund Category
-- Add fun_Category column to donationfund_fun table for better fund organization.

ALTER TABLE `donationfund_fun`
    ADD COLUMN `fun_Category` varchar(50) DEFAULT NULL;

-- END: 7.8.0-fund-category.sql


-- ============================================================================
-- BEGIN: 7.8.0-remove-people-report-queries.sql
-- ============================================================================

-- ChurchCRM 7.8.0 — Drop the predefined-query tables.
-- People queries now live at /people/reports. The two pledge queries that
-- remained are retired with the tables. Idempotent.

DROP TABLE IF EXISTS `queryparameteroptions_qpo`;
DROP TABLE IF EXISTS `queryparameters_qrp`;
DROP TABLE IF EXISTS `query_qry`;

DELETE FROM `config_cfg` WHERE `cfg_name` = 'aFinanceQueries';

-- END: 7.8.0-remove-people-report-queries.sql


-- ============================================================================
-- BEGIN: 7.8.0-self-register-needs-review.sql
-- ============================================================================

-- ChurchCRM 7.8.0 Schema Migration
-- Add NeedsReview flags for self-registered families/persons (issue #3639)
--
-- Background:
--   Public self-registration (src/api/routes/public/public-register.php) saves new
--   Family/Person records directly to the database. per_EnteredBy/fam_EnteredBy
--   already tag these rows with Person::SELF_REGISTER, but there was no way to
--   mark them as pending admin review. These columns let the app flag
--   self-registered records as needing review, and clear the flag once a
--   system user approves them.

-- The version-gated upgrade runner applies these additions once.
ALTER TABLE `person_per` ADD COLUMN `per_NeedsReview` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `per_DateDeactivated`;
ALTER TABLE `family_fam` ADD COLUMN `fam_NeedsReview` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `fam_Envelope`;

-- Backfill: existing self-registered records (per_EnteredBy/fam_EnteredBy = Person::SELF_REGISTER)
-- predate this flag. Flag the ones nobody has edited since sign-up; a record a staff
-- user has already edited (EditedBy set) counts as reviewed.
UPDATE `person_per` SET `per_NeedsReview` = 1 WHERE `per_EnteredBy` = -1 AND COALESCE(`per_EditedBy`, 0) = 0;
UPDATE `family_fam` SET `fam_NeedsReview` = 1 WHERE `fam_EnteredBy` = -1 AND COALESCE(`fam_EditedBy`, 0) = 0;

-- END: 7.8.0-self-register-needs-review.sql
