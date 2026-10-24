-- ChurchCRM 7.9.0 upgrade script

-- Member sign-in row 0 (#10197): counters for login throttling and temporary lockouts.
CREATE TABLE IF NOT EXISTS `rate_limit_rlm` (
  `rlm_ID`      int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rlm_Bucket`  varchar(64)      NOT NULL,
  `rlm_KeyHash` char(64)         NOT NULL,
  `rlm_Hits`    int(10) unsigned NOT NULL DEFAULT 0,
  `rlm_ResetAt` int(10) unsigned NOT NULL,
  PRIMARY KEY (`rlm_ID`),
  UNIQUE KEY `rlm_bucket_key_uidx` (`rlm_Bucket`, `rlm_KeyHash`),
  KEY `rlm_reset_at_idx` (`rlm_ResetAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member sign-in, row 1 (#10463): the person-keyed tables for the whole epic
-- (design docs/member-sign-in-design.md §13). Nothing is copied into them here;
-- the release-day cutover (row 13) moves the portal data from user_usr.

CREATE TABLE IF NOT EXISTS `person_signin_psi` (
  `psi_per_ID`            mediumint(9) unsigned NOT NULL,
  `psi_SessionVersion`    int(11)               NOT NULL DEFAULT 0,
  `psi_SignInDisabled`    tinyint(1) unsigned   NOT NULL DEFAULT 0,
  `psi_DisabledBy_usr_ID` mediumint(9) unsigned          DEFAULT NULL,
  `psi_DisabledAt`        datetime                       DEFAULT NULL,
  `psi_DisabledReason`    varchar(255)                   DEFAULT NULL,
  `psi_ProvenEmail`       varchar(255)                   DEFAULT NULL,
  `psi_ProvenAt`          datetime                       DEFAULT NULL,
  `psi_UnconfirmedEmail`  varchar(255)                   DEFAULT NULL,
  `psi_UnconfirmedSource` varchar(32)                    DEFAULT NULL,
  `psi_UnconfirmedAt`     datetime                       DEFAULT NULL,
  `psi_FirstSignIn`       datetime                       DEFAULT NULL,
  `psi_LastSignIn`        datetime                       DEFAULT NULL,
  `psi_LastMethod`        varchar(20)                    DEFAULT NULL,
  `psi_LastActivity`      datetime                       DEFAULT NULL,
  `psi_CalendarToken`     varchar(64)                    DEFAULT NULL,
  `psi_CalendarSelection` text                           DEFAULT NULL,
  PRIMARY KEY (`psi_per_ID`),
  UNIQUE KEY `psi_calendar_token_uidx` (`psi_CalendarToken`),
  KEY `psi_last_signin_idx`            (`psi_LastSignIn`),
  CONSTRAINT `fk_psi_person` FOREIGN KEY (`psi_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `person_signin_request_psr` (
  `psr_ID`          int(11)               NOT NULL AUTO_INCREMENT,
  `psr_per_ID`      mediumint(9) unsigned NOT NULL,
  `psr_Email`       varchar(255)          NOT NULL,
  `psr_TokenHash`   char(64)              NOT NULL,
  `psr_CodeHash`    char(64)              NOT NULL,
  `psr_BrowserHash` char(64)              NOT NULL,
  `psr_Attempts`    int(11)               NOT NULL DEFAULT 0,
  `psr_CreatedAt`   datetime              NOT NULL,
  `psr_ExpiresAt`   datetime              NOT NULL,
  `psr_UsedAt`      datetime                       DEFAULT NULL,
  `psr_CancelledAt` datetime                       DEFAULT NULL,
  PRIMARY KEY (`psr_ID`),
  UNIQUE KEY `psr_token_uidx` (`psr_TokenHash`),
  KEY `psr_person_idx`        (`psr_per_ID`),
  KEY `psr_expires_idx`       (`psr_ExpiresAt`),
  CONSTRAINT `fk_psr_person` FOREIGN KEY (`psr_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `person_device_pdv` (
  `pdv_ID`         int(11)                 NOT NULL AUTO_INCREMENT,
  `pdv_per_ID`     mediumint(9) unsigned   NOT NULL,
  `pdv_TokenHash`  char(64)                NOT NULL,
  `pdv_Kind`       enum('browser','app')   NOT NULL DEFAULT 'browser',
  `pdv_Label`      varchar(255)                     DEFAULT NULL,
  `pdv_CreatedAt`  datetime                NOT NULL,
  `pdv_LastUsedAt` datetime                         DEFAULT NULL,
  `pdv_ExpiresAt`  datetime                NOT NULL,
  `pdv_RevokedAt`  datetime                         DEFAULT NULL,
  PRIMARY KEY (`pdv_ID`),
  UNIQUE KEY `pdv_token_uidx` (`pdv_TokenHash`),
  KEY `pdv_person_idx`        (`pdv_per_ID`),
  CONSTRAINT `fk_pdv_person` FOREIGN KEY (`pdv_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `person_passkey_ppk` (
  `ppk_ID`             int(11)               NOT NULL AUTO_INCREMENT,
  `ppk_per_ID`         mediumint(9) unsigned NOT NULL,
  `ppk_CredentialId`   text                  NOT NULL,
  `ppk_CredentialHash` char(64)              NOT NULL,
  `ppk_PublicKey`      text                  NOT NULL,
  `ppk_SignCount`      bigint(20)            NOT NULL DEFAULT 0,
  `ppk_UserHandle`     varchar(128)                   DEFAULT NULL,
  `ppk_Transports`     varchar(255)                   DEFAULT NULL,
  `ppk_Label`          varchar(255)                   DEFAULT NULL,
  `ppk_CreatedAt`      datetime              NOT NULL,
  `ppk_LastUsedAt`     datetime                       DEFAULT NULL,
  PRIMARY KEY (`ppk_ID`),
  UNIQUE KEY `ppk_credential_uidx` (`ppk_CredentialHash`),
  KEY `ppk_person_idx`             (`ppk_per_ID`),
  CONSTRAINT `fk_ppk_person` FOREIGN KEY (`ppk_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portal_grant_pgr` (
  `pgr_ID`               int(11)               NOT NULL AUTO_INCREMENT,
  `pgr_per_ID`           mediumint(9) unsigned NOT NULL,
  `pgr_Capability`       varchar(128)          NOT NULL,
  `pgr_ScopeType`        varchar(32)           NOT NULL DEFAULT 'none',
  `pgr_ScopeId`          int(11)               NOT NULL DEFAULT 0,
  `pgr_EndsAt`           datetime                       DEFAULT NULL,
  `pgr_Reason`           varchar(255)                   DEFAULT NULL,
  `pgr_GrantedBy_usr_ID` mediumint(9) unsigned          DEFAULT NULL,
  `pgr_GrantedAt`        datetime              NOT NULL,
  PRIMARY KEY (`pgr_ID`),
  UNIQUE KEY `pgr_person_capability_scope_uidx` (`pgr_per_ID`, `pgr_Capability`, `pgr_ScopeType`, `pgr_ScopeId`),
  KEY `pgr_capability_idx`                      (`pgr_Capability`),
  CONSTRAINT `fk_pgr_person` FOREIGN KEY (`pgr_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portal_signin_audit_psa` (
  `psa_ID`           int(11)               NOT NULL AUTO_INCREMENT,
  `psa_Time`         datetime              NOT NULL,
  `psa_per_ID`       mediumint(9) unsigned          DEFAULT NULL,
  `psa_Event`        varchar(32)           NOT NULL,
  `psa_Method`       varchar(20)                    DEFAULT NULL,
  `psa_Reason`       varchar(64)                    DEFAULT NULL,
  `psa_Actor_usr_ID` mediumint(9) unsigned          DEFAULT NULL,
  `psa_Email`        varchar(255)                   DEFAULT NULL,
  `psa_IP`           varchar(45)                    DEFAULT NULL,
  `psa_Detail`       varchar(255)                   DEFAULT NULL,
  PRIMARY KEY (`psa_ID`),
  KEY `psa_person_time_idx` (`psa_per_ID`, `psa_Time`),
  KEY `psa_time_idx`        (`psa_Time`),
  KEY `psa_email_time_idx`  (`psa_Email`, `psa_Time`),
  KEY `psa_ip_time_idx`     (`psa_IP`, `psa_Time`),
  CONSTRAINT `fk_psa_person` FOREIGN KEY (`psa_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member sign-in, row 1 (#10463): the member rule starts with the church's own
-- head-of-household and spouse roles ticked (design §4), whatever their ids are.
SET @msi_head = (SELECT `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'sDirRoleHead');
SET @msi_spouse = (SELECT `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'sDirRoleSpouse');
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
VALUES ('sMemberSignInFamilyRoles', CONCAT(COALESCE(NULLIF(@msi_head, ''), '1'), ',', COALESCE(NULLIF(@msi_spouse, ''), '2')));
