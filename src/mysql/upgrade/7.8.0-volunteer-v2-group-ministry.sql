-- Volunteer Management v2 (D19, epic #9701): give a core Group an optional owning ministry.
--
-- NOT REGISTERED YET. Volunteer v2 is excluded from 7.7.0 and targets 7.8.0, so this script is
-- deliberately absent from src/mysql/upgrade.json: a future migration must not sit in the active
-- 7.7.0 upgrade graph. It gets registered when the 7.8.0 development/version boundary is opened,
-- and it must be ordered AFTER 7.8.0-volunteer-v2-schema.sql (which creates the FK target
-- volunteer_ministry_vmin) and after 7.8.0-volunteer-v2-manager-permission.sql.
--
-- Fresh installs and the Cypress seed database get the column from src/mysql/install/Install.sql
-- and cypress/data/seed.sql respectively, so only an upgrading installation needs this script.
--
-- Why this lives in its own file rather than in 7.8.0-volunteer-v2-schema.sql: `group_grp` is a
-- CORE table. The V2 schema script creates V2's own tables; a core column that V2 owns is the
-- same shape as events_event.event_ministry_id (#9713) and follows the same precedent — one
-- script per core table V2 touches, so a reviewer of core can see the whole change in one file.
--
-- D19 replaces the volunteer_pool_vpol link table with this column. A ministry now owns exactly
-- one Group — its volunteer pool — created with the ministry and named after it. The Group is
-- still an ordinary group_grp row: it appears in the Groups module, its membership is still
-- person2group2role_p2g2r, and anyone with Manage Groups may still add and remove people there.
-- What the column adds is (a) the ownership link five V2 screens need, and (b) the fact that
-- carries the coordinator exception in Group::preSave() and friends.
--
-- NULL means "not a ministry's volunteer pool", which is every group that exists before this
-- migration and every group created the way groups have always been created. Nothing about
-- those changes — the model hooks take their V1 path byte for byte when this column is NULL.
--
-- ON DELETE SET NULL: deleting a ministry must never delete a church group through a cascade.
-- The ministry-deletion path in VolunteerSetupService deletes the pool Group explicitly, inside
-- the same transaction and through the managed-write context, so the removal is a decision the
-- service makes rather than a side effect of a foreign key.
--
-- Note: plain ALTER TABLE (no IF NOT EXISTS) is correct here because the upgrade runner is
-- version-gated: fresh installs set the DB version to the current release via
-- installChurchCRMSchema() and never execute historical migration scripts. IF NOT EXISTS is a
-- MariaDB-only extension unsupported by MySQL (see 7.4.3-manage-fundraisers.sql).
ALTER TABLE `group_grp` ADD COLUMN `grp_ministry_id` int(11) DEFAULT NULL AFTER `grp_include_email_export`;

ALTER TABLE `group_grp` ADD KEY `grp_ministry_idx` (`grp_ministry_id`);

ALTER TABLE `group_grp`
    ADD CONSTRAINT `group_grp_FK_ministry` FOREIGN KEY (`grp_ministry_id`)
    REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE SET NULL;
