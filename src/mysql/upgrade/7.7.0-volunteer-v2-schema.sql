-- ChurchCRM 7.7.0 — Volunteer Management v2 core domain model
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
--   pool                 The group↔owner link has nowhere to live; event_audience
--                        is documented as an advertising audience, not ownership.
--   position             Nothing in core models "a role a volunteer can serve in".
--   qualification        Person↔position many-to-many is structurally impossible
--                        on person2group2role_p2g2r: its primary key is
--                        (person, group), i.e. one role per person per group.
--   schedule/occurrence  There is no event series identifier anywhere in core —
--                        "repeat" bulk-inserts N unlinked events_event rows — so
--                        V2 must own the series, and an occurrence row is what
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
--
-- Deliberately NOT here:
--   * Open Gap is derived (requirement minus live assignments), never stored — a
--     persisted gap is a cache that disagrees with the assignments the first time
--     a decline is processed outside the happy path.
--   * events_event.event_ministry_id ships with #9713 and user_usr.usr_VolunteerManager
--     with #9706, each in its own migration appended after this one.
--   * The V1 tables (volunteeropportunity_vol, person2volunteeropp_p2vo) are not
--     read, written, altered or dropped here. V1 data is #9702's concern.
--
-- CREATE TABLE IF NOT EXISTS throughout, so re-running the script is a no-op.
-- Tables are ordered so that every foreign-key target already exists.
--
-- volunteer_pool_vpol.vpol_OwnerId and volunteer_scope_vscp.vscp_ScopeId are
-- polymorphic (ministry or team) and therefore carry no foreign key — the same
-- limitation record2property_r2p lives with. The service layer enforces those.

CREATE TABLE IF NOT EXISTS `volunteer_ministry_vmin` (
  `vmin_ID`               int(11)               NOT NULL AUTO_INCREMENT,
  `vmin_Name`             varchar(100)          NOT NULL,
  `vmin_Description`      varchar(255)                   DEFAULT NULL,
  `vmin_Active`           tinyint(1) unsigned   NOT NULL DEFAULT 1,
  `vmin_CreatedDate`      datetime              NOT NULL,
  `vmin_CreatedBy_per_ID` mediumint(9) unsigned          DEFAULT NULL,
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
  PRIMARY KEY (`vtem_ID`),
  UNIQUE KEY `vtem_ministry_name_uidx` (`vtem_vmin_ID`, `vtem_Name`),
  KEY `vtem_ministry_idx`              (`vtem_vmin_ID`),
  CONSTRAINT `fk_vtem_ministry` FOREIGN KEY (`vtem_vmin_ID`)
      REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_pool_vpol` (
  `vpol_ID`        int(11)                     NOT NULL AUTO_INCREMENT,
  `vpol_OwnerType` enum('ministry','team')     NOT NULL,
  `vpol_OwnerId`   int(11)                     NOT NULL,
  `vpol_grp_ID`    mediumint(8) unsigned       NOT NULL,
  `vpol_Label`     varchar(100)                         DEFAULT NULL,
  PRIMARY KEY (`vpol_ID`),
  UNIQUE KEY `vpol_owner_group_uidx` (`vpol_OwnerType`, `vpol_OwnerId`, `vpol_grp_ID`),
  KEY `vpol_group_idx`               (`vpol_grp_ID`),
  CONSTRAINT `fk_vpol_group` FOREIGN KEY (`vpol_grp_ID`)
      REFERENCES `group_grp` (`grp_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_position_vpos` (
  `vpos_ID`          int(11)             NOT NULL AUTO_INCREMENT,
  `vpos_vmin_ID`     int(11)             NOT NULL,
  `vpos_vtem_ID`     int(11)                      DEFAULT NULL,
  `vpos_Name`        varchar(100)        NOT NULL,
  `vpos_Description` varchar(255)                 DEFAULT NULL,
  `vpos_Active`      tinyint(1) unsigned NOT NULL DEFAULT 1,
  `vpos_Order`       int(11)             NOT NULL DEFAULT 0,
  PRIMARY KEY (`vpos_ID`),
  -- MySQL treats NULLs as distinct inside a UNIQUE index, so this does not stop
  -- two ministry-wide (vpos_vtem_ID IS NULL) positions sharing a name. That is
  -- deliberate: VolunteerSetupService::createPosition() does the case-insensitive
  -- check and the API returns 409. Do not "fix" it with a NOT NULL DEFAULT 0
  -- sentinel — that is the event_types.type_grpid anti-pattern and it breaks the FK.
  UNIQUE KEY `vpos_ministry_team_name_uidx` (`vpos_vmin_ID`, `vpos_vtem_ID`, `vpos_Name`),
  KEY `vpos_ministry_active_idx`            (`vpos_vmin_ID`, `vpos_Active`),
  KEY `vpos_team_idx`                       (`vpos_vtem_ID`),
  CONSTRAINT `fk_vpos_ministry` FOREIGN KEY (`vpos_vmin_ID`)
      REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vpos_team` FOREIGN KEY (`vpos_vtem_ID`)
      REFERENCES `volunteer_team_vtem` (`vtem_ID`) ON DELETE SET NULL
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
  `vsch_vtem_ID`           int(11)                                                 DEFAULT NULL,
  `vsch_Name`              varchar(100)                                   NOT NULL,
  `vsch_LinkMode`          enum('event_type','standalone')                NOT NULL,
  `vsch_event_type_id`     int(11)                                                 DEFAULT NULL,
  `vsch_TitleFilter`       varchar(255)                                            DEFAULT NULL,
  `vsch_RecurType`         enum('none','weekly','monthly','yearly')       NOT NULL DEFAULT 'none',
  `vsch_RecurDOW`          enum('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  `vsch_RecurDOM`          tinyint(3)                                              DEFAULT NULL,
  `vsch_StartTime`         time                                                    DEFAULT NULL,
  `vsch_EndTime`           time                                                    DEFAULT NULL,
  `vsch_WindowStart`       date                                           NOT NULL,
  `vsch_WindowEnd`         date                                                    DEFAULT NULL,
  `vsch_GenerateAheadDays` int(11)                                        NOT NULL DEFAULT 56,
  `vsch_Active`            tinyint(1) unsigned                            NOT NULL DEFAULT 1,
  PRIMARY KEY (`vsch_ID`),
  KEY `vsch_ministry_idx`      (`vsch_vmin_ID`),
  KEY `vsch_team_idx`          (`vsch_vtem_ID`),
  KEY `vsch_type_idx`          (`vsch_event_type_id`),
  KEY `vsch_active_window_idx` (`vsch_Active`, `vsch_WindowStart`),
  CONSTRAINT `fk_vsch_ministry` FOREIGN KEY (`vsch_vmin_ID`)
      REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_vsch_team` FOREIGN KEY (`vsch_vtem_ID`)
      REFERENCES `volunteer_team_vtem` (`vtem_ID`) ON DELETE SET NULL,
  CONSTRAINT `fk_vsch_event_type` FOREIGN KEY (`vsch_event_type_id`)
      REFERENCES `event_types` (`type_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_occurrence_vocc` (
  `vocc_ID`             int(11)                          NOT NULL AUTO_INCREMENT,
  `vocc_vsch_ID`        int(11)                          NOT NULL,
  `vocc_event_id`       int(11)                                   DEFAULT NULL,
  `vocc_OccurrenceDate` date                             NOT NULL,
  `vocc_StartDateTime`  datetime                                  DEFAULT NULL,
  `vocc_EndDateTime`    datetime                                  DEFAULT NULL,
  `vocc_Status`         enum('scheduled','cancelled')    NOT NULL DEFAULT 'scheduled',
  `vocc_Notes`          varchar(255)                              DEFAULT NULL,
  `vocc_GeneratedDate`  datetime                         NOT NULL,
  PRIMARY KEY (`vocc_ID`),
  -- Two unique keys, one per link mode, each idempotent for its own generation
  -- path. MySQL permits several NULLs in a unique index, so linked rows never
  -- collide on the standalone key and vice versa. The keys are per SCHEDULE, so
  -- occurrences of different schedules may share one events_event row — that is
  -- two ministries staffing the same service, and it is by design.
  UNIQUE KEY `vocc_schedule_event_uidx` (`vocc_vsch_ID`, `vocc_event_id`),
  UNIQUE KEY `vocc_schedule_start_uidx` (`vocc_vsch_ID`, `vocc_StartDateTime`),
  KEY `vocc_date_idx`  (`vocc_OccurrenceDate`),
  KEY `vocc_event_idx` (`vocc_event_id`),
  CONSTRAINT `fk_vocc_schedule` FOREIGN KEY (`vocc_vsch_ID`)
      REFERENCES `volunteer_schedule_vsch` (`vsch_ID`) ON DELETE CASCADE,
  -- SET NULL, not CASCADE: deleting a church event must not erase the record
  -- that people served. The occurrence survives on vocc_OccurrenceDate.
  CONSTRAINT `fk_vocc_event` FOREIGN KEY (`vocc_event_id`)
      REFERENCES `events_event` (`event_id`) ON DELETE SET NULL
  -- No CHECK "standalone rows must have a start time": once fk_vocc_event has
  -- SET NULL a deleted event, a formerly linked row legitimately has neither an
  -- event nor a start time (its date lives in vocc_OccurrenceDate), and MariaDB
  -- refuses a CHECK on a column an FK action can change anyway. The generator
  -- (VolunteerScheduleService, #9708) always sets vocc_StartDateTime for
  -- standalone schedules; vocc_schedule_start_uidx deduplicates those rows.
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
  `vntf_Type`            enum('assignment','reminder','decline_alert','gap_alert','signup_confirm','swap_proposed','swap_resolved')
                                               NOT NULL,
  `vntf_Channel`         enum('email')         NOT NULL DEFAULT 'email',
  `vntf_per_ID`          mediumint(9) unsigned NOT NULL,
  `vntf_vasg_ID`         int(11)                        DEFAULT NULL,
  `vntf_vocc_ID`         int(11)                        DEFAULT NULL,
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
