-- Role tables and usr_IsOwner. Additive, and safe to run again after a partial run.

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_usr' AND COLUMN_NAME = 'usr_IsOwner') = 0,
    'ALTER TABLE `user_usr` ADD COLUMN `usr_IsOwner` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usr_Admin`',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

CREATE TABLE IF NOT EXISTS `role_rol` (
  `rol_ID`         int(11)      NOT NULL AUTO_INCREMENT,
  `rol_Key`        varchar(64)  NOT NULL,
  `rol_Name`       varchar(128) NOT NULL,
  `rol_Kind`       varchar(16)  NOT NULL,
  `rol_Version`    int(11)      NOT NULL DEFAULT 1,
  `rol_CopiedFrom` varchar(64)           DEFAULT NULL,
  PRIMARY KEY (`rol_ID`),
  UNIQUE KEY `rol_key_uidx` (`rol_Key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_grant_rgr` (
  `rgr_ID`         int(11)     NOT NULL AUTO_INCREMENT,
  `rgr_rol_ID`     int(11)     NOT NULL,
  `rgr_Permission` varchar(64) NOT NULL,
  `rgr_Scope`      varchar(32) NOT NULL DEFAULT 'all',
  PRIMARY KEY (`rgr_ID`),
  UNIQUE KEY `rgr_role_permission_scope_uidx` (`rgr_rol_ID`, `rgr_Permission`, `rgr_Scope`),
  CONSTRAINT `fk_rgr_role` FOREIGN KEY (`rgr_rol_ID`)
      REFERENCES `role_rol` (`rol_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_role_uro` (
  `uro_usr_ID`             mediumint(9) unsigned NOT NULL,
  `uro_rol_ID`             int(11)               NOT NULL,
  `uro_AssignedBy_usr_ID`  mediumint(9) unsigned          DEFAULT NULL,
  `uro_ApprovedBy_usr_ID`  mediumint(9) unsigned          DEFAULT NULL,
  `uro_AssignedAt`         datetime              NOT NULL,
  PRIMARY KEY (`uro_usr_ID`, `uro_rol_ID`),
  KEY `uro_rol_idx` (`uro_rol_ID`),
  CONSTRAINT `fk_uro_user` FOREIGN KEY (`uro_usr_ID`)
      REFERENCES `user_usr` (`usr_per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_uro_role` FOREIGN KEY (`uro_rol_ID`)
      REFERENCES `role_rol` (`rol_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_audit_rau` (
  `rau_ID`             int(11)               NOT NULL AUTO_INCREMENT,
  `rau_Actor_usr_ID`   mediumint(9) unsigned          DEFAULT NULL,
  `rau_Subject_usr_ID` mediumint(9) unsigned          DEFAULT NULL,
  `rau_Action`         varchar(32)           NOT NULL,
  `rau_Detail`         text                           DEFAULT NULL,
  `rau_Time`           datetime              NOT NULL,
  PRIMARY KEY (`rau_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
