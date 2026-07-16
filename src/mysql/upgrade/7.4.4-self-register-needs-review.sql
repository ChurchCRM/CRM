-- ChurchCRM 7.4.4 Schema Migration
-- Add NeedsReview flags for self-registered families/persons (issue #3639)
--
-- Background:
--   Public self-registration (src/api/routes/public/public-register.php) saves new
--   Family/Person records directly to the database. per_EnteredBy/fam_EnteredBy
--   already tag these rows with Person::SELF_REGISTER, but there was no way to
--   mark them as pending admin review. These columns let the app flag
--   self-registered records as needing review, and clear the flag once a
--   system user approves them.

ALTER TABLE `person_per` ADD COLUMN IF NOT EXISTS `per_NeedsReview` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `per_LinkedIn`;
ALTER TABLE `family_fam` ADD COLUMN IF NOT EXISTS `fam_NeedsReview` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `fam_Envelope`;
