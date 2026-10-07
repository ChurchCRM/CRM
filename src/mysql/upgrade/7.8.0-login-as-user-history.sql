-- Login as User history (#9843).
--
-- user_masquerade_session_ums: one row per session an administrator spends signed
-- in as another user. ums_Ended / ums_EndReason stay NULL until the session ends by
-- the exit control ('exit'), a sign-out or a new login in the same browser
-- ('signout'), or ChurchCRM's idle session timeout ('timeout').
--
-- user_masquerade_action_uma: one row per write request made during a session, with
-- the path stripped of its query string and the response status.

CREATE TABLE IF NOT EXISTS `user_masquerade_session_ums` (
  `ums_ID`            int(10) unsigned      NOT NULL AUTO_INCREMENT,
  `ums_admin_usr_ID`  mediumint(9) unsigned NOT NULL DEFAULT 0,
  `ums_target_usr_ID` mediumint(9) unsigned NOT NULL DEFAULT 0,
  `ums_Started`       datetime              NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ums_Ended`         datetime              DEFAULT NULL,
  `ums_EndReason`     varchar(10)           DEFAULT NULL,
  PRIMARY KEY (`ums_ID`),
  KEY `idx_ums_admin_usr_ID`  (`ums_admin_usr_ID`),
  KEY `idx_ums_target_usr_ID` (`ums_target_usr_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_masquerade_action_uma` (
  `uma_ID`     int(10) unsigned     NOT NULL AUTO_INCREMENT,
  `uma_ums_ID` int(10) unsigned     NOT NULL DEFAULT 0,
  `uma_Method` varchar(10)          NOT NULL DEFAULT '',
  `uma_Path`   varchar(255)         NOT NULL DEFAULT '',
  `uma_Status` smallint(5) unsigned DEFAULT NULL,
  `uma_Time`   datetime             NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uma_ID`),
  KEY `idx_uma_ums_ID` (`uma_ums_ID`),
  CONSTRAINT `fk_uma_session` FOREIGN KEY (`uma_ums_ID`)
      REFERENCES `user_masquerade_session_ums` (`ums_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
