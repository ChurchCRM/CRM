-- Important dates: church-defined yearly dates (baptism, confirmation,
-- dedication...) recorded on people and families. Three new tables; no
-- existing column changes. Safe to run again: every statement is guarded.

CREATE TABLE IF NOT EXISTS `important_date_type_idt` (
  `idt_ID`             int(11)              NOT NULL AUTO_INCREMENT,
  `idt_Key`            varchar(50)          NOT NULL,
  `idt_Name`           varchar(100)         NOT NULL,
  `idt_AppliesTo`      varchar(10)          NOT NULL,
  `idt_AllowMultiple`  tinyint(1) unsigned  NOT NULL DEFAULT 0,
  `idt_ShowOnCalendar` tinyint(1) unsigned  NOT NULL DEFAULT 1,
  `idt_Active`         tinyint(1) unsigned  NOT NULL DEFAULT 1,
  `idt_Order`          smallint(5)          NOT NULL DEFAULT 0,
  PRIMARY KEY (`idt_ID`),
  UNIQUE KEY `idt_key_uidx` (`idt_Key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `person_important_date_pid` (
  `pid_ID`             int(11)               NOT NULL AUTO_INCREMENT,
  `pid_per_ID`         mediumint(9) unsigned NOT NULL,
  `pid_idt_ID`         int(11)               NOT NULL,
  `pid_Month`          tinyint(3) unsigned   NOT NULL,
  `pid_Day`            tinyint(3) unsigned   NOT NULL,
  `pid_Year`           smallint(5) unsigned           DEFAULT NULL,
  `pid_Note`           varchar(255)                   DEFAULT NULL,
  `pid_DateEntered`    datetime              NOT NULL,
  `pid_EnteredBy`      mediumint(9) unsigned          DEFAULT NULL,
  `pid_DateLastEdited` datetime                       DEFAULT NULL,
  `pid_EditedBy`       mediumint(9) unsigned          DEFAULT NULL,
  PRIMARY KEY (`pid_ID`),
  KEY `pid_owner_type_idx` (`pid_per_ID`, `pid_idt_ID`),
  KEY `pid_type_month_day_idx` (`pid_idt_ID`, `pid_Month`, `pid_Day`),
  CONSTRAINT `fk_pid_person` FOREIGN KEY (`pid_per_ID`)
      REFERENCES `person_per` (`per_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_pid_type` FOREIGN KEY (`pid_idt_ID`)
      REFERENCES `important_date_type_idt` (`idt_ID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `family_important_date_fid` (
  `fid_ID`             int(11)               NOT NULL AUTO_INCREMENT,
  `fid_fam_ID`         mediumint(9) unsigned NOT NULL,
  `fid_idt_ID`         int(11)               NOT NULL,
  `fid_Month`          tinyint(3) unsigned   NOT NULL,
  `fid_Day`            tinyint(3) unsigned   NOT NULL,
  `fid_Year`           smallint(5) unsigned           DEFAULT NULL,
  `fid_Note`           varchar(255)                   DEFAULT NULL,
  `fid_DateEntered`    datetime              NOT NULL,
  `fid_EnteredBy`      mediumint(9) unsigned          DEFAULT NULL,
  `fid_DateLastEdited` datetime                       DEFAULT NULL,
  `fid_EditedBy`       mediumint(9) unsigned          DEFAULT NULL,
  PRIMARY KEY (`fid_ID`),
  KEY `fid_owner_type_idx` (`fid_fam_ID`, `fid_idt_ID`),
  KEY `fid_type_month_day_idx` (`fid_idt_ID`, `fid_Month`, `fid_Day`),
  CONSTRAINT `fk_fid_family` FOREIGN KEY (`fid_fam_ID`)
      REFERENCES `family_fam` (`fam_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_fid_type` FOREIGN KEY (`fid_idt_ID`)
      REFERENCES `important_date_type_idt` (`idt_ID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
