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

-- ============================================================================
-- BEGIN: 7.8.0-email-log.sql
-- ============================================================================

-- Migration: add email_log_eml (issue #9877)
--
-- One row per recipient for every email ChurchCRM sends through BaseEmail::send():
-- composer messages, birthday greetings, family verification links, account emails,
-- notifications and the SMTP test. eml_per_ID / eml_fam_ID link the row to the record the
-- address belonged to at send time (NULL when no record matched); eml_usr_ID is the user who
-- sent a composer message (NULL for automated sends). eml_Body holds the rendered HTML only
-- for email classes that allow it (composer); account emails never store a body because they
-- carry passwords and one-time tokens. Rows are kept indefinitely.

CREATE TABLE IF NOT EXISTS `email_log_eml` (
  `eml_ID`        int(10) unsigned      NOT NULL AUTO_INCREMENT,
  `eml_per_ID`    mediumint(8) unsigned DEFAULT NULL,
  `eml_fam_ID`    mediumint(8) unsigned DEFAULT NULL,
  `eml_usr_ID`    mediumint(9) unsigned DEFAULT NULL,
  `eml_Address`   varchar(255)          NOT NULL,
  `eml_Kind`      varchar(50)           NOT NULL,
  `eml_Subject`   varchar(255)          NOT NULL DEFAULT '',
  `eml_Body`      longtext              DEFAULT NULL,
  `eml_Status`    varchar(20)           NOT NULL,
  `eml_Error`     text                  DEFAULT NULL,
  `eml_MessageID` varchar(255)          DEFAULT NULL,
  `eml_DateSent`  datetime              NOT NULL,
  PRIMARY KEY (`eml_ID`),
  KEY `idx_eml_per_ID`   (`eml_per_ID`),
  KEY `idx_eml_fam_ID`   (`eml_fam_ID`),
  KEY `idx_eml_DateSent` (`eml_DateSent`),
  KEY `idx_eml_Status`   (`eml_Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- END: 7.8.0-email-log.sql

-- ============================================================================
-- BEGIN: 7.8.0-member-portal-calendars.sql
-- ============================================================================

-- 7.8.0: Ministry calendars (Member Portal, #9866).
--
-- `calendars.ministry_id` names the volunteer ministry a calendar belongs to.
-- It is NULL for every calendar that exists today: a church calendar has no
-- owning ministry, which is exactly what "ministry_id IS NULL" means.
--
-- No foreign key here on purpose. `volunteer_ministry_vmin` is created by the
-- Volunteer v2 schema (epic #9701), which runs after this script; the
-- constraint `calendars_ministry_fk` REFERENCES `volunteer_ministry_vmin`
-- (`vmin_ID`) ON DELETE SET NULL is added by that schema, once the table it
-- points at exists. The index below is what makes the later ALTER cheap and
-- what "list the ministry calendars" reads.
ALTER TABLE `calendars`
  ADD COLUMN `ministry_id` INT NULL DEFAULT NULL,
  ADD KEY `calendars_ministry_idx` (`ministry_id`);

-- END: 7.8.0-member-portal-calendars.sql

-- ============================================================================
-- BEGIN: 7.8.0-volunteer-v2-schema.sql
-- ============================================================================

-- ChurchCRM 7.8.0 — Volunteer Management v2 core domain model
-- Implements #9705 (epic #9701); design: .agents/skills/churchcrm/volunteer-v2-design.md §2
--
-- Why these tables exist at all, in one line each (the long form, with the
-- evidence that reuse was insufficient, is design §8.1):
--
--   ministry / team      "Ministry" exists today only as group-type list option
--                        list_lst (3,1) — there is no entity for five tables and
--                        a core column to reference, and putting ministry
--                        identity on group_grp would hide it behind the
--                        bManageGroups model hooks and GroupQuery::preSelect().
--   position             Nothing in core models "a role a volunteer can serve in".
--   qualification        Person↔position many-to-many is structurally impossible
--                        on person2group2role_p2g2r: its primary key is
--                        (person, group), i.e. one role per person per group.
--   schedule/occurrence  There is no event series identifier anywhere in core —
--                        "repeat" bulk-inserts N unlinked events_event rows — so
--                        a schedule records how a team's events are found, and an
--                        occurrence row (always anchored to an event, D20) is what
--                        lets several ministries staff one event independently.
--   requirement          "This occurrence needs 2–3 espresso people" has no home;
--                        eventcounts_evtcnt is a per-type head count with no
--                        position concept and no foreign key.
--   assignment/response  person + position + occurrence + lifecycle does not
--                        exist; event_attend is (event, person) presence only.
--   swap                 No proposal/approval workflow exists in the codebase.
--   notification         There is no queue, outbox or send log in the schema; the
--                        only idempotency marker in the messaging stack is one
--                        config_cfg string with a check-then-set race.
--   scope                No table persists a user→object scope; every existing
--                        gate is a global boolean on user_usr.
--   calendar grant       Only Add Events may write to a church calendar; nothing
--                        can open one calendar to one ministry (D25).
--
-- Deliberately NOT here:
--   * Open Gap is derived (requirement minus live assignments), never stored — a
--     persisted gap is a cache that disagrees with the assignments the first time
--     a decline is processed outside the happy path.
--   * events_event.event_ministry_id ships with #9713, user_usr.usr_ManageMinistries
--     with #9706 and group_grp.grp_ministry_id with D19, each in its own migration
--     appended after this one — one script per CORE table V2 touches.
--   * The volunteer pool has no table. D19 makes a ministry own exactly one core
--     Group, linked by group_grp.grp_ministry_id, so the roster is the Group and
--     the link is a column on it. The volunteer_pool_vpol link table this replaces
--     never shipped.
--   * The V1 tables (volunteeropportunity_vol, person2volunteeropp_p2vo) are not
--     read, written, altered or dropped here. V1 data is #9702's concern.
--
-- CREATE TABLE IF NOT EXISTS throughout, so re-running the script is a no-op.
-- Tables are ordered so that every foreign-key target already exists.
--
-- volunteer_scope_vscp.vscp_ScopeId is polymorphic (ministry or team) and
-- therefore carries no foreign key — the same limitation record2property_r2p
-- lives with. The service layer enforces it.

CREATE TABLE IF NOT EXISTS `volunteer_ministry_vmin` (
  `vmin_ID`               int(11)               NOT NULL AUTO_INCREMENT,
  `vmin_Name`             varchar(100)          NOT NULL,
  `vmin_Description`      varchar(255)                   DEFAULT NULL,
  `vmin_Active`           tinyint(1) unsigned   NOT NULL DEFAULT 1,
  `vmin_CreatedDate`      datetime              NOT NULL,
  `vmin_CreatedBy_per_ID` mediumint(9) unsigned          DEFAULT NULL,
  -- D19 "Help wanted": the ministry advertises itself on the Open Opportunities
  -- page. Off by default, so an installation that never touches it looks exactly
  -- as it did before. The text is free prose written by the coordinator.
  `vmin_HelpWanted`       tinyint(1)            NOT NULL DEFAULT 0,
  `vmin_HelpWantedText`   text                           DEFAULT NULL,
  -- D29: the ministry may provide teachers for Sunday School. Off by default; only
  -- while it is on may its teams link a class and its schedules and events use one.
  `vmin_SundaySchool`     tinyint(1) unsigned   NOT NULL DEFAULT 0,
  PRIMARY KEY (`vmin_ID`),
  UNIQUE KEY `vmin_name_uidx`  (`vmin_Name`),
  KEY `vmin_active_idx`        (`vmin_Active`),
  KEY `vmin_created_by_idx`    (`vmin_CreatedBy_per_ID`),
  CONSTRAINT `fk_vmin_created_by` FOREIGN KEY (`vmin_CreatedBy_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_team_vtem` (
  `vtem_ID`          int(11)             NOT NULL AUTO_INCREMENT,
  `vtem_vmin_ID`     int(11)             NOT NULL,
  `vtem_Name`        varchar(100)        NOT NULL,
  `vtem_Description` varchar(255)                 DEFAULT NULL,
  `vtem_Active`      tinyint(1) unsigned NOT NULL DEFAULT 1,
  -- D23: the Sunday School class this team staffs; qualifications write its Teacher role.
  `vtem_grp_ID`      mediumint(8) unsigned          DEFAULT NULL,
  PRIMARY KEY (`vtem_ID`),
  UNIQUE KEY `vtem_ministry_name_uidx` (`vtem_vmin_ID`, `vtem_Name`),
  UNIQUE KEY `vtem_class_group_uidx`   (`vtem_grp_ID`),
  KEY `vtem_ministry_idx`              (`vtem_vmin_ID`),
  CONSTRAINT `fk_vtem_ministry` FOREIGN KEY (`vtem_vmin_ID`)
      REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vtem_class_group` FOREIGN KEY (`vtem_grp_ID`)
      REFERENCES `group_grp` (`grp_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_position_vpos` (
  `vpos_ID`          int(11)             NOT NULL AUTO_INCREMENT,
  `vpos_vmin_ID`     int(11)             NOT NULL,
  `vpos_vtem_ID`     int(11)             NOT NULL,
  `vpos_Name`        varchar(100)        NOT NULL,
  `vpos_Description` varchar(255)                 DEFAULT NULL,
  `vpos_Active`      tinyint(1) unsigned NOT NULL DEFAULT 1,
  -- "Recruit Volunteers": this position is advertised by name on the Open
  -- Opportunities page. Separate from the ministry-level vmin_HelpWanted advert
  -- and default OFF, so turning V2 on never publishes a position nobody chose.
  `vpos_Recruiting`  tinyint(1)          NOT NULL DEFAULT 0,
  `vpos_SelfAssignable` tinyint(1)        NOT NULL DEFAULT 1,
  `vpos_Order`       int(11)             NOT NULL DEFAULT 0,
  PRIMARY KEY (`vpos_ID`),
  -- vpos_vtem_ID is NOT NULL: every ministry is created with a team, so a position
  -- always belongs to one (D18). With no NULLs in the index MySQL's "NULLs are
  -- distinct" rule can no longer fire, so this unique key now catches every
  -- duplicate on its own; VolunteerSetupService::createPosition() keeps its
  -- case-insensitive check because the index is case-insensitive only by collation
  -- accident and the service owns the 409's wording.
  UNIQUE KEY `vpos_ministry_team_name_uidx` (`vpos_vmin_ID`, `vpos_vtem_ID`, `vpos_Name`),
  KEY `vpos_ministry_active_idx`            (`vpos_vmin_ID`, `vpos_Active`),
  KEY `vpos_team_idx`                       (`vpos_vtem_ID`),
  CONSTRAINT `fk_vpos_ministry` FOREIGN KEY (`vpos_vmin_ID`)
      REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vpos_team` FOREIGN KEY (`vpos_vtem_ID`)
      REFERENCES `volunteer_team_vtem` (`vtem_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_qualification_vqal` (
  `vqal_ID`               int(11)               NOT NULL AUTO_INCREMENT,
  `vqal_per_ID`           mediumint(9) unsigned NOT NULL,
  `vqal_vpos_ID`          int(11)               NOT NULL,
  `vqal_Active`           tinyint(1) unsigned   NOT NULL DEFAULT 1,
  `vqal_GrantedDate`      datetime              NOT NULL,
  `vqal_GrantedBy_per_ID` mediumint(9) unsigned          DEFAULT NULL,
  `vqal_Notes`            varchar(255)                   DEFAULT NULL,
  PRIMARY KEY (`vqal_ID`),
  -- One row per person per position — NOT one per person per team or ministry.
  -- This is what lets a volunteer hold several qualifications at once, which
  -- person2group2role_p2g2r structurally cannot express.
  UNIQUE KEY `vqal_person_position_uidx` (`vqal_per_ID`, `vqal_vpos_ID`),
  KEY `vqal_position_active_idx`         (`vqal_vpos_ID`, `vqal_Active`),
  KEY `vqal_person_idx`                  (`vqal_per_ID`),
  KEY `vqal_granted_by_idx`              (`vqal_GrantedBy_per_ID`),
  CONSTRAINT `fk_vqal_person` FOREIGN KEY (`vqal_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vqal_position` FOREIGN KEY (`vqal_vpos_ID`)
      REFERENCES `volunteer_position_vpos` (`vpos_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vqal_granted_by` FOREIGN KEY (`vqal_GrantedBy_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_schedule_vsch` (
  `vsch_ID`                int(11)                                        NOT NULL AUTO_INCREMENT,
  `vsch_vmin_ID`           int(11)                                        NOT NULL,
  `vsch_vtem_ID`           int(11)                                        NOT NULL,
  `vsch_Name`              varchar(100)                                   NOT NULL,
  `vsch_LinkMode`          enum('event_type','class','ministry','event')  NOT NULL,
  `vsch_event_type_id`     int(11)                                                 DEFAULT NULL,
  `vsch_TitleFilter`       varchar(255)                                            DEFAULT NULL,
  `vsch_grp_ID`            mediumint(8) unsigned                                   DEFAULT NULL,
  `vsch_event_id`          int(11)                                                 DEFAULT NULL,
  `vsch_StartOffsetMinutes` int(11)                                       NOT NULL DEFAULT 0,
  `vsch_EndOffsetMinutes`  int(11)                                        NOT NULL DEFAULT 0,
  `vsch_WindowStart`       date                                           NOT NULL,
  `vsch_WindowEnd`         date                                                    DEFAULT NULL,
  `vsch_Active`            tinyint(1) unsigned                            NOT NULL DEFAULT 1,
  `vsch_OneOff`            tinyint(1) unsigned                            NOT NULL DEFAULT 0,
  PRIMARY KEY (`vsch_ID`),
  KEY `vsch_ministry_idx`      (`vsch_vmin_ID`),
  KEY `vsch_team_idx`          (`vsch_vtem_ID`),
  KEY `vsch_type_idx`          (`vsch_event_type_id`),
  KEY `vsch_group_idx`         (`vsch_grp_ID`),
  KEY `vsch_event_idx`         (`vsch_event_id`),
  KEY `vsch_active_window_idx` (`vsch_Active`, `vsch_WindowStart`),
  CONSTRAINT `fk_vsch_ministry` FOREIGN KEY (`vsch_vmin_ID`)
      REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vsch_team` FOREIGN KEY (`vsch_vtem_ID`)
      REFERENCES `volunteer_team_vtem` (`vtem_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vsch_event_type` FOREIGN KEY (`vsch_event_type_id`)
      REFERENCES `event_types` (`type_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vsch_group` FOREIGN KEY (`vsch_grp_ID`)
      REFERENCES `group_grp` (`grp_ID`) ON DELETE SET NULL,
  CONSTRAINT `fk_vsch_event` FOREIGN KEY (`vsch_event_id`)
      REFERENCES `events_event` (`event_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_occurrence_vocc` (
  `vocc_ID`             int(11)                          NOT NULL AUTO_INCREMENT,
  `vocc_vsch_ID`        int(11)                          NOT NULL,
  `vocc_event_id`       int(11)                                   DEFAULT NULL,
  `vocc_OccurrenceDate` date                             NOT NULL,
  `vocc_Status`         enum('scheduled','cancelled')    NOT NULL DEFAULT 'scheduled',
  `vocc_Notes`          varchar(255)                              DEFAULT NULL,
  `vocc_GeneratedDate`  datetime                         NOT NULL,
  PRIMARY KEY (`vocc_ID`),
  -- Generation is idempotent through this key. It is per SCHEDULE, so
  -- occurrences of different schedules may share one events_event row — that is
  -- two ministries staffing the same service, and it is by design.
  UNIQUE KEY `vocc_schedule_event_uidx` (`vocc_vsch_ID`, `vocc_event_id`),
  KEY `vocc_date_idx`  (`vocc_OccurrenceDate`),
  KEY `vocc_event_idx` (`vocc_event_id`),
  CONSTRAINT `fk_vocc_schedule` FOREIGN KEY (`vocc_vsch_ID`)
      REFERENCES `volunteer_schedule_vsch` (`vsch_ID`) ON DELETE CASCADE,
  -- SET NULL, not CASCADE: deleting a church event must not erase the record
  -- that people served. The occurrence survives on vocc_OccurrenceDate. That is
  -- the only reason the column is nullable: VolunteerScheduleService sets it on
  -- every insert (D20).
  CONSTRAINT `fk_vocc_event` FOREIGN KEY (`vocc_event_id`)
      REFERENCES `events_event` (`event_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_requirement_vreq` (
  `vreq_ID`       int(11)      NOT NULL AUTO_INCREMENT,
  `vreq_vsch_ID`  int(11)               DEFAULT NULL,
  `vreq_vocc_ID`  int(11)               DEFAULT NULL,
  `vreq_vpos_ID`  int(11)      NOT NULL,
  `vreq_MinCount` int(11)      NOT NULL DEFAULT 1,
  `vreq_MaxCount` int(11)               DEFAULT NULL,
  `vreq_Notes`    varchar(255)          DEFAULT NULL,
  PRIMARY KEY (`vreq_ID`),
  UNIQUE KEY `vreq_schedule_position_uidx`   (`vreq_vsch_ID`, `vreq_vpos_ID`),
  UNIQUE KEY `vreq_occurrence_position_uidx` (`vreq_vocc_ID`, `vreq_vpos_ID`),
  KEY `vreq_position_idx`                    (`vreq_vpos_ID`),
  CONSTRAINT `fk_vreq_schedule` FOREIGN KEY (`vreq_vsch_ID`)
      REFERENCES `volunteer_schedule_vsch` (`vsch_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vreq_occurrence` FOREIGN KEY (`vreq_vocc_ID`)
      REFERENCES `volunteer_occurrence_vocc` (`vocc_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vreq_position` FOREIGN KEY (`vreq_vpos_ID`)
      REFERENCES `volunteer_position_vpos` (`vpos_ID`) ON DELETE CASCADE,
  -- Exactly one parent: a template requirement belongs to a schedule, an
  -- override to an occurrence, never both and never neither. Enforced on
  -- MariaDB 10.2.1+ / MySQL 8.0.16+; parsed and ignored by MySQL 5.7.
  CONSTRAINT `vreq_one_parent_chk`
      CHECK ((`vreq_vsch_ID` IS NULL) <> (`vreq_vocc_ID` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- D35: a schedule's default volunteers, several per position, in vrdf_Sort order. Only a
-- template requirement (vreq_vsch_ID set) has them; the service refuses one on an
-- occurrence's override and never more than the position's Max. Unchecking the position
-- (its vreq row deleted) or deleting the person takes the row with it; vrdf_SetBy_per_ID
-- is who chose it, the assigner when the daily top-up, which runs as nobody, assigns it.
CREATE TABLE IF NOT EXISTS `volunteer_requirement_default_vrdf` (
  `vrdf_ID`           int(11)               NOT NULL AUTO_INCREMENT,
  `vrdf_vreq_ID`      int(11)               NOT NULL,
  `vrdf_per_ID`       mediumint(9) unsigned NOT NULL,
  `vrdf_Accepted`     tinyint(1) unsigned   NOT NULL DEFAULT 0,
  `vrdf_SetBy_per_ID` mediumint(9) unsigned          DEFAULT NULL,
  `vrdf_Sort`         smallint(6)           NOT NULL DEFAULT 0,
  PRIMARY KEY (`vrdf_ID`),
  UNIQUE KEY `vrdf_requirement_person_uidx` (`vrdf_vreq_ID`, `vrdf_per_ID`),
  KEY `vrdf_person_idx`                     (`vrdf_per_ID`),
  KEY `vrdf_set_by_idx`                     (`vrdf_SetBy_per_ID`),
  CONSTRAINT `fk_vrdf_requirement` FOREIGN KEY (`vrdf_vreq_ID`)
      REFERENCES `volunteer_requirement_vreq` (`vreq_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vrdf_person` FOREIGN KEY (`vrdf_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vrdf_set_by` FOREIGN KEY (`vrdf_SetBy_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_assignment_vasg` (
  `vasg_ID`                int(11)               NOT NULL AUTO_INCREMENT,
  `vasg_vocc_ID`           int(11)               NOT NULL,
  `vasg_vpos_ID`           int(11)               NOT NULL,
  `vasg_per_ID`            mediumint(9) unsigned NOT NULL,
  `vasg_vreq_ID`           int(11)                        DEFAULT NULL,
  `vasg_Status`            enum('pending','accepted','declined','cancelled','substituted','completed')
                                                 NOT NULL DEFAULT 'pending',
  `vasg_Source`            enum('coordinator','self_signup','substitute')
                                                 NOT NULL DEFAULT 'coordinator',
  `vasg_AssignedDate`      datetime              NOT NULL,
  `vasg_AssignedBy_per_ID` mediumint(9) unsigned          DEFAULT NULL,
  `vasg_RespondedDate`     datetime                       DEFAULT NULL,
  `vasg_Replaces_vasg_ID`  int(11)                        DEFAULT NULL,
  `vasg_Notes`             varchar(255)                   DEFAULT NULL,
  PRIMARY KEY (`vasg_ID`),
  -- One assignment per person per POSITION per occurrence. Note what this
  -- deliberately does not say: it is not (occurrence, person), so the same
  -- person may hold rows for two different positions on one occurrence — lead
  -- singing and serve communion at the same service. Do not tighten this index.
  UNIQUE KEY `vasg_occ_pos_per_uidx` (`vasg_vocc_ID`, `vasg_vpos_ID`, `vasg_per_ID`),
  KEY `vasg_occurrence_idx`     (`vasg_vocc_ID`, `vasg_Status`),
  KEY `vasg_person_status_idx`  (`vasg_per_ID`, `vasg_Status`),
  KEY `vasg_position_idx`       (`vasg_vpos_ID`),
  KEY `vasg_requirement_idx`    (`vasg_vreq_ID`),
  KEY `vasg_assigned_by_idx`    (`vasg_AssignedBy_per_ID`),
  KEY `vasg_replaces_idx`       (`vasg_Replaces_vasg_ID`),
  CONSTRAINT `fk_vasg_occurrence` FOREIGN KEY (`vasg_vocc_ID`)
      REFERENCES `volunteer_occurrence_vocc` (`vocc_ID`) ON DELETE CASCADE,
  -- RESTRICT: a position with assignments cannot be deleted, only deactivated,
  -- so deactivation never destroys history.
  CONSTRAINT `fk_vasg_position` FOREIGN KEY (`vasg_vpos_ID`)
      REFERENCES `volunteer_position_vpos` (`vpos_ID`) ON DELETE RESTRICT,
  CONSTRAINT `fk_vasg_person` FOREIGN KEY (`vasg_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vasg_requirement` FOREIGN KEY (`vasg_vreq_ID`)
      REFERENCES `volunteer_requirement_vreq` (`vreq_ID`) ON DELETE SET NULL,
  CONSTRAINT `fk_vasg_assigned_by` FOREIGN KEY (`vasg_AssignedBy_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE SET NULL,
  CONSTRAINT `fk_vasg_replaces` FOREIGN KEY (`vasg_Replaces_vasg_ID`)
      REFERENCES `volunteer_assignment_vasg` (`vasg_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_response_vrsp` (
  `vrsp_ID`           int(11)               NOT NULL AUTO_INCREMENT,
  `vrsp_vasg_ID`      int(11)               NOT NULL,
  `vrsp_per_ID`       mediumint(9) unsigned NOT NULL,
  `vrsp_Response`     enum('accepted','declined','cancelled','substitute_proposed','substitute_approved','substitute_rejected','substitute_withdrawn')
                                            NOT NULL,
  `vrsp_ResponseDate` datetime              NOT NULL,
  `vrsp_Channel`      enum('web','coordinator') NOT NULL DEFAULT 'web',
  `vrsp_Comment`      varchar(255)                   DEFAULT NULL,
  PRIMARY KEY (`vrsp_ID`),
  KEY `vrsp_assignment_idx` (`vrsp_vasg_ID`, `vrsp_ResponseDate`),
  KEY `vrsp_person_idx`     (`vrsp_per_ID`),
  CONSTRAINT `fk_vrsp_assignment` FOREIGN KEY (`vrsp_vasg_ID`)
      REFERENCES `volunteer_assignment_vasg` (`vasg_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vrsp_person` FOREIGN KEY (`vrsp_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_swap_vswp` (
  `vswp_ID`               int(11)               NOT NULL AUTO_INCREMENT,
  `vswp_vasg_ID`          int(11)               NOT NULL,
  `vswp_ProposedBy_per_ID` mediumint(9) unsigned NOT NULL,
  `vswp_Proposed_per_ID`  mediumint(9) unsigned NOT NULL,
  `vswp_Status`           enum('proposed','approved','rejected','withdrawn') NOT NULL DEFAULT 'proposed',
  `vswp_ProposedDate`     datetime              NOT NULL,
  `vswp_DecidedDate`      datetime                       DEFAULT NULL,
  `vswp_DecidedBy_per_ID` mediumint(9) unsigned          DEFAULT NULL,
  `vswp_Comment`          varchar(255)                   DEFAULT NULL,
  PRIMARY KEY (`vswp_ID`),
  -- "At most one proposed swap per assignment" is service-enforced: MySQL has no
  -- partial unique index, so it cannot be expressed here.
  KEY `vswp_assignment_status_idx` (`vswp_vasg_ID`, `vswp_Status`),
  KEY `vswp_proposed_person_idx`   (`vswp_Proposed_per_ID`),
  KEY `vswp_proposed_by_idx`       (`vswp_ProposedBy_per_ID`),
  KEY `vswp_decided_by_idx`        (`vswp_DecidedBy_per_ID`),
  CONSTRAINT `fk_vswp_assignment` FOREIGN KEY (`vswp_vasg_ID`)
      REFERENCES `volunteer_assignment_vasg` (`vasg_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vswp_proposed_by` FOREIGN KEY (`vswp_ProposedBy_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vswp_proposed_person` FOREIGN KEY (`vswp_Proposed_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vswp_decided_by` FOREIGN KEY (`vswp_DecidedBy_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_notification_vntf` (
  `vntf_ID`              int(11)               NOT NULL AUTO_INCREMENT,
  `vntf_Type`            enum('assignment','reminder','decline_alert','gap_alert','signup_confirm','swap_proposed','swap_resolved','help_offer')
                                               NOT NULL,
  `vntf_Channel`         enum('email')         NOT NULL DEFAULT 'email',
  `vntf_per_ID`          mediumint(9) unsigned NOT NULL,
  `vntf_vasg_ID`         int(11)                        DEFAULT NULL,
  `vntf_vocc_ID`         int(11)                        DEFAULT NULL,
  -- D19: opaque, type-specific context for a row that hangs off NEITHER an assignment
  -- nor an occurrence. `help_offer` is the first such type — it is about a ministry and
  -- a person, and the one fact the message needs ("were they already in the pool?") is
  -- true only at the moment of the click and cannot be recomputed at delivery time.
  -- JSON, read only by the type that wrote it.
  `vntf_Context`         varchar(190)                   DEFAULT NULL,
  -- 190, not 255: the InnoDB index prefix limit for a utf8mb4 unique key.
  `vntf_DedupeKey`       varchar(190)          NOT NULL,
  `vntf_ScheduledFor`    datetime              NOT NULL,
  `vntf_Status`          enum('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `vntf_Attempts`        int(11)               NOT NULL DEFAULT 0,
  `vntf_LastAttemptDate` datetime                       DEFAULT NULL,
  `vntf_SentDate`        datetime                       DEFAULT NULL,
  `vntf_LastError`       varchar(255)                   DEFAULT NULL,
  PRIMARY KEY (`vntf_ID`),
  -- The idempotency guarantee: enqueue is findOneOrCreate() on this key inside
  -- the same transaction as the state change, so a retried operation produces no
  -- second message. Same idiom as event_attend's UNIQUE(event_id, person_id).
  UNIQUE KEY `vntf_dedupe_uidx` (`vntf_DedupeKey`),
  KEY `vntf_due_idx`            (`vntf_Status`, `vntf_ScheduledFor`),
  KEY `vntf_assignment_idx`     (`vntf_vasg_ID`),
  KEY `vntf_person_idx`         (`vntf_per_ID`),
  KEY `vntf_occurrence_idx`     (`vntf_vocc_ID`),
  CONSTRAINT `fk_vntf_person` FOREIGN KEY (`vntf_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vntf_assignment` FOREIGN KEY (`vntf_vasg_ID`)
      REFERENCES `volunteer_assignment_vasg` (`vasg_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vntf_occurrence` FOREIGN KEY (`vntf_vocc_ID`)
      REFERENCES `volunteer_occurrence_vocc` (`vocc_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_scope_vscp` (
  `vscp_ID`               int(11)                 NOT NULL AUTO_INCREMENT,
  `vscp_per_ID`           mediumint(9) unsigned   NOT NULL,
  `vscp_ScopeType`        enum('ministry','team') NOT NULL,
  `vscp_ScopeId`          int(11)                 NOT NULL,
  `vscp_GrantedDate`      datetime                NOT NULL,
  `vscp_GrantedBy_per_ID` mediumint(9) unsigned            DEFAULT NULL,
  PRIMARY KEY (`vscp_ID`),
  -- Makes granting a scope idempotent, the same way event_attend's unique key
  -- makes checking someone in idempotent.
  UNIQUE KEY `vscp_person_scope_uidx` (`vscp_per_ID`, `vscp_ScopeType`, `vscp_ScopeId`),
  KEY `vscp_person_idx`               (`vscp_per_ID`),
  KEY `vscp_scope_idx`                (`vscp_ScopeType`, `vscp_ScopeId`),
  KEY `vscp_granted_by_idx`           (`vscp_GrantedBy_per_ID`),
  CONSTRAINT `fk_vscp_person` FOREIGN KEY (`vscp_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vscp_granted_by` FOREIGN KEY (`vscp_GrantedBy_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Member Portal (#9866 / #9869): the calendars -> volunteer_ministry_vmin ownership link.
--
-- The column and its index ship in 7.8.0-member-portal-calendars.sql, which lands with the
-- Member Portal epic and cannot declare the constraint: volunteer_ministry_vmin does not exist
-- until this script creates it. The constraint therefore lives here, at the end of the V2
-- schema, exactly as the sibling comment in that file promises.
--
-- ON DELETE SET NULL, matching group_grp and events_event: deleting a ministry must never delete
-- a church calendar through a cascade. VolunteerSetupService::deleteMinistry() removes the
-- ministry's own calendar explicitly, inside the same transaction, so the removal is a decision
-- the service makes rather than a side effect of a foreign key — and a calendar an administrator
-- later re-pointed at nothing simply becomes a church calendar again.
--
-- Plain ALTER TABLE (no IF NOT EXISTS) for the reason given in
-- 7.8.0-volunteer-v2-group-ministry.sql: the upgrade runner is version-gated, and IF NOT EXISTS
-- is a MariaDB-only extension MySQL does not accept.
--
ALTER TABLE `calendars`
    ADD CONSTRAINT `calendars_ministry_fk` FOREIGN KEY (`ministry_id`)
    REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE SET NULL;

--
-- D25: church calendars an administrator has opened to a ministry. A coordinator without Add
-- Events may pin the ministry's own events to these as well as to the ministry's own calendar.
--
-- vcal_calendar_id is int(11) because calendars.calendar_id is, on every install and upgrade
-- path; MySQL refuses a foreign key whose type differs from its parent's. Both keys cascade:
-- a grant means nothing once either side is gone.
--
CREATE TABLE IF NOT EXISTS `volunteer_calendar_vcal` (
  `vcal_calendar_id` int(11) NOT NULL,
  `vcal_vmin_ID`     int(11) NOT NULL,
  PRIMARY KEY (`vcal_calendar_id`, `vcal_vmin_ID`),
  KEY `vcal_ministry_idx` (`vcal_vmin_ID`),
  CONSTRAINT `fk_vcal_calendar` FOREIGN KEY (`vcal_calendar_id`)
      REFERENCES `calendars` (`calendar_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vcal_ministry` FOREIGN KEY (`vcal_vmin_ID`)
      REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- D29: a ministry that already staffs a class — a team linked to one, or a schedule of class
-- meetings — is a Sunday School ministry, so the switch starts on for it. Idempotent.
--
UPDATE `volunteer_ministry_vmin`
   SET `vmin_SundaySchool` = 1
 WHERE `vmin_SundaySchool` = 0
   AND (`vmin_ID` IN (SELECT `vtem_vmin_ID` FROM `volunteer_team_vtem` WHERE `vtem_grp_ID` IS NOT NULL)
        OR `vmin_ID` IN (SELECT `vsch_vmin_ID` FROM `volunteer_schedule_vsch` WHERE `vsch_LinkMode` = 'class'));

-- END: 7.8.0-volunteer-v2-schema.sql

-- ============================================================================
-- BEGIN: 7.8.0-volunteer-v2-manager-permission.sql
-- ============================================================================

-- Volunteer Management v2 (#9706, epic #9701): add the usr_ManageMinistries permission column.
--
-- Tier: Administrator > *Global Volunteer Manager* > Ministry Coordinator > Team Leader > Volunteer.
-- A global volunteer manager has authority over every ministry and every team without being a
-- system administrator; ministry/team-scoped authority lives in volunteer_scope_vscp instead
-- (created by 7.8.0-volunteer-v2-schema.sql, which must therefore run first).
--
-- Storage follows the usr_ManageFundraisers precedent (7.4.3-manage-fundraisers.sql): a first-class
-- permission is a user_usr boolean column, not a userconfig_ucfg row.
--
-- Note: plain ALTER TABLE (no IF NOT EXISTS) is safe here because the upgrade
-- runner is version-gated: fresh installs set the DB version to the current
-- release via installChurchCRMSchema() and never execute historical migration
-- scripts. IF NOT EXISTS is a MariaDB-only extension unsupported by MySQL.
ALTER TABLE `user_usr` ADD COLUMN `usr_ManageMinistries` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usr_ManageFundraisers`;
-- Manage My Ministries (2026-09-18, product owner): the permission an administrator gives a
-- ministry coordinator. It opens the Ministries heading, the Ministry Dashboard and the pages
-- of the ministries the login holds a coordinator scope for - and nothing else. Manage
-- Ministries above stays the global tier (every ministry, create, deactivate, delete).
ALTER TABLE `user_usr` ADD COLUMN `usr_ManageMyMinistries` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usr_ManageMinistries`;

-- END: 7.8.0-volunteer-v2-manager-permission.sql

-- ============================================================================
-- BEGIN: 7.8.0-volunteer-v2-event-ministry.sql
-- ============================================================================

-- Volunteer Management v2 (#9713, epic #9701): give an event an optional owning ministry.
--
-- Registered in the 7.8.0 block of src/mysql/upgrade.json, ordered AFTER
-- 7.8.0-volunteer-v2-schema.sql (which creates the FK target volunteer_ministry_vmin) and after
-- 7.8.0-volunteer-v2-manager-permission.sql.
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

-- END: 7.8.0-volunteer-v2-event-ministry.sql

-- ============================================================================
-- BEGIN: 7.8.0-volunteer-v2-group-ministry.sql
-- ============================================================================

-- Volunteer Management v2 (D19, epic #9701): give a core Group an optional owning ministry.
--
-- Registered in the 7.8.0 block of src/mysql/upgrade.json, ordered AFTER
-- 7.8.0-volunteer-v2-schema.sql (which creates the FK target volunteer_ministry_vmin) and after
-- 7.8.0-volunteer-v2-manager-permission.sql.
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

-- END: 7.8.0-volunteer-v2-group-ministry.sql

-- ============================================================================
-- BEGIN: 7.8.0-member-portal-activity.sql
-- ============================================================================

-- 7.8.0: the user_usr columns the Member Portal needs.
--
-- usr_LastPortalActivity (#9864). Nullable DATETIME, default NULL => every
-- existing account has never been seen in the Member Portal, which is exactly
-- true before this release. PortalAccessMiddleware stamps it at most once every
-- five minutes; the Admin → Member Portal statistics tab reads it for "active
-- in the last 15 minutes".
ALTER TABLE `user_usr`
  ADD COLUMN `usr_LastPortalActivity` datetime DEFAULT NULL;

-- Calendar subscription (design §5.3, "Subscribing").
--
-- usr_PortalCalendarToken is the bearer secret in the member's feed URL, 32
-- random bytes as 64 hex characters, minted the first time they save a
-- selection and rotated by "Reset link". NULL => this account has never asked
-- for a feed, and no URL answers for it. The UNIQUE index is what makes the
-- lookup a single indexed read and what stops two accounts ever sharing one
-- address; MySQL treats NULLs as distinct in a UNIQUE index, so every account
-- without a feed still fits.
--
-- usr_PortalCalendarSelection is a JSON array of the calendar ids the member
-- ticked ("calendar:3", "system:0"). TEXT rather than JSON so the column works
-- on the MySQL and MariaDB versions ChurchCRM supports; nothing queries inside
-- it. The feed always intersects it with the calendars the administrator
-- currently shares, so a stale id here can never widen what a feed serves.
ALTER TABLE `user_usr`
  ADD COLUMN `usr_PortalCalendarToken` VARCHAR(64) DEFAULT NULL,
  ADD COLUMN `usr_PortalCalendarSelection` TEXT DEFAULT NULL,
  ADD UNIQUE KEY `usr_PortalCalendarToken` (`usr_PortalCalendarToken`);

-- END: 7.8.0-member-portal-activity.sql
