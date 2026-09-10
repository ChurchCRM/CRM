-- Core-owned ledger. No foreign key to removable plugin configuration.
CREATE TABLE IF NOT EXISTS `plugin_migration_pmg` (
  `pmg_PluginId` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `pmg_MigrationId` varchar(95) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `pmg_Checksum` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `pmg_StartedAt` datetime NOT NULL,
  `pmg_AppliedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`pmg_PluginId`, `pmg_MigrationId`)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
