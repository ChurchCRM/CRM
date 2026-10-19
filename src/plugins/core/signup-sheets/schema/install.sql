-- Signup Sheets plugin schema.
-- Volunteer / potluck style signup sheets: a sheet holds slots, people claim slots.
--
-- Applied by PluginManager when the plugin is enabled, so the tables exist on
-- any database regardless of which release it was installed or upgraded from.
-- Every statement must stay idempotent: this file runs on every enable.

CREATE TABLE IF NOT EXISTS `signupsheet_shs` (
  `shs_ID` int NOT NULL AUTO_INCREMENT,
  `shs_title` varchar(255) NOT NULL,
  `shs_description` text NULL,
  `shs_event_id` int NULL,
  `shs_location` varchar(255) NULL,
  `shs_starts` datetime NULL,
  `shs_ends` datetime NULL,
  `shs_status` varchar(16) NOT NULL DEFAULT 'draft',
  `shs_close_at` datetime NULL,
  `shs_is_public` tinyint(1) NOT NULL DEFAULT 0,
  `shs_public_token` char(32) NULL,
  `shs_require_email` tinyint(1) NOT NULL DEFAULT 1,
  `shs_allow_comments` tinyint(1) NOT NULL DEFAULT 1,
  `shs_created_by` mediumint unsigned NULL,
  `shs_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `shs_updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`shs_ID`),
  UNIQUE KEY `signupsheet_shs_token` (`shs_public_token`),
  KEY `signupsheet_shs_event` (`shs_event_id`),
  KEY `signupsheet_shs_status` (`shs_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signupslot_sls` (
  `sls_ID` int NOT NULL AUTO_INCREMENT,
  `sls_sheet_id` int NOT NULL,
  `sls_category` varchar(100) NULL,
  `sls_title` varchar(255) NOT NULL,
  `sls_description` varchar(1000) NULL,
  `sls_starts` datetime NULL,
  `sls_ends` datetime NULL,
  `sls_capacity` smallint unsigned NOT NULL DEFAULT 1,
  `sls_allow_quantity` tinyint(1) NOT NULL DEFAULT 0,
  `sls_sort_order` smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (`sls_ID`),
  KEY `signupslot_sls_sheet` (`sls_sheet_id`),
  CONSTRAINT `signupslot_sls_sheet_fk` FOREIGN KEY (`sls_sheet_id`)
    REFERENCES `signupsheet_shs` (`shs_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signupclaim_sgc` (
  `sgc_ID` int NOT NULL AUTO_INCREMENT,
  `sgc_slot_id` int NOT NULL,
  `sgc_person_id` int NULL,
  `sgc_name` varchar(255) NOT NULL,
  `sgc_email` varchar(254) NULL,
  `sgc_phone` varchar(50) NULL,
  `sgc_quantity` smallint unsigned NOT NULL DEFAULT 1,
  `sgc_comment` varchar(1000) NULL,
  `sgc_source` varchar(16) NOT NULL DEFAULT 'internal',
  `sgc_manage_token` char(32) NULL,
  `sgc_created_by` mediumint unsigned NULL,
  `sgc_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sgc_ID`),
  UNIQUE KEY `signupclaim_sgc_token` (`sgc_manage_token`),
  KEY `signupclaim_sgc_slot` (`sgc_slot_id`),
  KEY `signupclaim_sgc_person` (`sgc_person_id`),
  CONSTRAINT `signupclaim_sgc_slot_fk` FOREIGN KEY (`sgc_slot_id`)
    REFERENCES `signupslot_sls` (`sls_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sga_created_at has no default on purpose: every row is written with an
-- explicit UTC_TIMESTAMP() so the rate-limit window compares like with like.
-- A CURRENT_TIMESTAMP default would follow the MySQL session timezone and skew
-- that window, so the column is left to fail loudly if a writer forgets it.
CREATE TABLE IF NOT EXISTS `signupaudit_sga` (
  `sga_ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sga_sheet_id` int NULL,
  `sga_event_type` varchar(48) NOT NULL,
  `sga_ip_hash` char(64) NULL,
  `sga_created_at` datetime NOT NULL,
  PRIMARY KEY (`sga_ID`),
  KEY `signupaudit_sga_ip` (`sga_ip_hash`, `sga_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
