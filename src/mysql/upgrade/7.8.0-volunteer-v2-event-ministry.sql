-- Volunteer Management v2 (#9713, epic #9701): give an event an optional owning ministry.
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
-- D9: a ministry coordinator creates and owns events for their ministry without holding the
-- global AddEvent right. There is no per-row event authorization in ChurchCRM today, and
-- event_audience cannot carry the link — it is documented as "a prospective audience for the
-- purpose of advertising / outreach", it points at groups, and it is many-per-event. So the
-- ownership link is one nullable column on the event row itself.
--
-- NULL means "no ministry owner", which is every event that exists before this migration and
-- every event created the way events have always been created. Nothing about those changes.
--
-- ON DELETE SET NULL: deleting a ministry must never delete church events. The same rule the
-- occurrence link already follows (volunteer_occurrence_vocc.vocc_event_id).
--
-- Note: plain ALTER TABLE (no IF NOT EXISTS) is correct here because the upgrade runner is
-- version-gated: fresh installs set the DB version to the current release via
-- installChurchCRMSchema() and never execute historical migration scripts. IF NOT EXISTS is a
-- MariaDB-only extension unsupported by MySQL (see 7.4.3-manage-fundraisers.sql).
ALTER TABLE `events_event` ADD COLUMN `event_ministry_id` int(11) DEFAULT NULL AFTER `secondary_contact_person_id`;

ALTER TABLE `events_event` ADD KEY `event_ministry_idx` (`event_ministry_id`);

ALTER TABLE `events_event`
    ADD CONSTRAINT `events_event_FK_ministry` FOREIGN KEY (`event_ministry_id`)
    REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE SET NULL;
