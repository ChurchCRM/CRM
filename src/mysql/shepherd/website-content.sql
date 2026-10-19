-- Shepherd 7.6.1: administrator-managed public website text.
CREATE TABLE IF NOT EXISTS `shepherd_website_content` (
  `page_key` varchar(64) NOT NULL,
  `content_json` mediumtext NOT NULL,
  `revision` char(64) NOT NULL,
  `updated_by` mediumint unsigned NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
