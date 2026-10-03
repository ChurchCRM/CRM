-- 7.8.0: Ministry calendars (Member Portal, #9866).
--
-- `calendars.ministry_id` names the volunteer ministry a calendar belongs to.
-- It is NULL for every calendar that exists today: a church calendar has no
-- owning ministry, which is exactly what "ministry_id IS NULL" means.
--
-- No foreign key here on purpose. `volunteer_ministry_vmin` is created by the
-- Volunteer v2 schema (epic #9701), which lands after this release; the
-- constraint `calendars_ministry_fk` REFERENCES `volunteer_ministry_vmin`
-- (`vmin_ID`) ON DELETE SET NULL is added by that schema, once the table it
-- points at exists. The index below is what makes the later ALTER cheap and
-- what "list the ministry calendars" reads.
ALTER TABLE `calendars`
  ADD COLUMN `ministry_id` INT NULL DEFAULT NULL,
  ADD KEY `calendars_ministry_idx` (`ministry_id`);
